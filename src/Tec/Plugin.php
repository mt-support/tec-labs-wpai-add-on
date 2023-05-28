<?php
/**
 * Plugin Class.
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\WpaiAddOn
 */

namespace Tribe\Extensions\WpaiAddOn;

/**
 * Class Plugin
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\WpaiAddOn
 */
class Plugin extends \tad_DI52_ServiceProvider {
	/**
	 * Stores the version for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Stores the base slug for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const SLUG = 'wpai-add-on';

	/**
	 * Stores the base slug for the extension.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const FILE = TRIBE_EXTENSION_WPAI_ADD_ON_FILE;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin Directory.
	 */
	public $plugin_dir;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin path.
	 */
	public $plugin_path;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin URL.
	 */
	public $plugin_url;

	/**
	 * @since 1.0.0
	 *
	 * @var Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private $settings;

	/**
	 * Stores the meta key name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $meta_key = "";

	/**
	 * Stores the meta value.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $meta_value = "";

	/**
	 * Stores the old ID of the linked post.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private $old_linked_post_id;

	/**
	 * Stores the new ID of the linked post.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private $new_linked_post_id;


	/**
	 * Setup the Extension's properties.
	 *
	 * This always executes even if the required plugins are not present.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Set up the plugin provider properties.
		$this->plugin_path = trailingslashit( dirname( static::FILE ) );
		$this->plugin_dir  = trailingslashit( basename( $this->plugin_path ) );
		$this->plugin_url  = plugins_url( $this->plugin_dir, $this->plugin_path );

		// Register this provider as the main one and use a bunch of aliases.
		$this->container->singleton( static::class, $this );
		$this->container->singleton( 'extension.wpai_add_on', $this );
		$this->container->singleton( 'extension.wpai_add_on.plugin', $this );
		$this->container->register( PUE::class );

		if ( ! $this->check_plugin_dependencies() ) {
			// If the plugin dependency manifest is not met, then bail and stop here.
			return;
		}

		// Do the settings.
		// TODO: Remove if not using settings
		$this->get_settings();

		// Start binds.

		add_filter( 'tec_tickets_commerce_attendee_post_type_args', [ $this, 'tc_attendees_label' ] );
		add_filter( 'tec_tickets_commerce_order_post_type_args', [ $this, 'tc_orders_label' ] );

		add_filter( 'tec_events_custom_tables_v1_tracked_meta_keys', [ $this, 'modify_tracked_meta_keys' ] );

		add_filter( 'wp_all_import_is_post_to_create', [ $this, 'maybe_create_post' ], 10, 3 );

		add_action( 'pmxi_saved_post', [ $this, 'maybe_update_post' ], 10, 3 );

		// End binds.

		$this->container->register( Hooks::class );
		$this->container->register( Assets::class );
	}

	/**
	 * Check whether a post needs to be imported or not.
	 *
	 * @param bool  $continue_import True to import, false to skip import.
	 * @param array $data            Array of data to import.
	 * @param int   $import_id       The ID of the import.
	 *
	 * @return bool
	 */
	public function maybe_create_post( $continue_import, $data, $import_id ) {

		if (
			$data['posttype'] == 'tribe_rsvp_tickets'
			|| $data['posttype'] == 'tribe_rsvp_attendees'
			|| $data['posttype'] == 'tec_tc_ticket'
			|| $data['posttype'] == 'tec_tc_order'
			|| $data['posttype'] == 'tec_tc_attendee'
		) {

			$msg = "<strong>THE EVENTS CALENDAR EXTENSION: WPAI ADD-ON:</strong>";
			$this->add_to_log( $msg );

			if ( ! $this->check_data_validity( $data ) ) {
				return false;
			}

			/**
			 * Filter to allow forcing the import if the related post doesn't exist.
			 */
			if ( apply_filters( 'tec_labs_wpai_force_import_' . $data['posttype'], false, $data, $import_id ) ) {
				$pto = get_post_type_object( $data['posttype'] );
				// Get post type label
				$this->add_to_log(
				// Translators: 1) Singular label of the post type being imported. 2) Title of the post currently imported.
					sprintf(
						'%1$s `%2$s` will be force-imported, despite a non-existent related post.',
						$pto->labels->singular_name,
						$data['title'],
					)
				);

				return true;
			}


			return $this->check_relation_exists( $data );
		}

		return true;
	}

	/**
	 * Check if data is valid.
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	private function check_data_validity( array $data ) {
		$this->add_to_log( "Checking if data is valid..." );

		$valid = true;

		/**
		 * Bail if
		 * - Link to event missing
		 */
		if (
			$data['posttype'] == 'tec_tc_ticket'
			&& is_array( $data['_tec_tickets_commerce_event'] )
			&& empty( $data['_tec_tickets_commerce_event'] )
		) {
			$valid = false;
			$this->add_to_log( "Link to event missing..." );
		}

		/**
		 * Bail if
		 * - Order total value doesn't exist
		 * - Post status doesn't exist
		 * - Post status is anything else than "tec-tc-xxx"
		 */
		if ( $data['posttype'] == 'tec_tc_order' ) {
			if (
				(
					is_array( $data['_tec_tc_order_total_value'] )
					&& empty( $data['_tec_tc_order_total_value'] )
				)
				|| ! isset( $record['status'] )
				|| ! str_starts_with( $record['status'], 'tec-tc-' )
			) {
				$valid = false;
				$this->add_to_log( "Data corrupt OR post status missing OR post status incorrect..." );
			}
		}

		/**
		 * Bail if
		 * - There is no link to the ticket
		 * - There is no link to the event
		 */
		if ( $data['posttype'] == 'tec_tc_attendee' ) {
			if (
				(
					is_array( $data['_tec_tickets_commerce_ticket'] )
					&& empty( $data['_tec_tickets_commerce_ticket'] )
				)
				||
				(
					is_array( $data['_tec_tickets_commerce_event'] )
					&& empty( $data['_tec_tickets_commerce_event'] )
				)
			) {
				$valid = false;
				$this->add_to_log( "Link to ticket or event missing..." );
			}
		}

		if ( ! $valid ) {
			$this->add_to_log( "Borked data. Skipping." );
		}

		return $valid;
	}

	/**
	 * Check if the related post the current one being imported depends on exists.
	 *
	 * @param array  $data        Array of the data being imported.
	 *
	 * @return bool
	 */
	function check_relation_exists( $data ) {
		$relations = [
			'tribe_rsvp_tickets'   => [
				0 =>[
					'linked_post_type' => 'tribe_events',
					'meta_key'         => '_tribe_rsvp_for_event',
				],
			],
			'tribe_rsvp_attendees' => [
				0 => [
					'linked_post_type' => 'tribe_events',
					'meta_key'         => '_tribe_rsvp_event',
				],
				1 => [
					'linked_post_type' => 'tribe_rsvp_tickets',
					'meta_key'         => '_tribe_rsvp_product',
				],
			],
			'tec_tc_ticket'   => [
				0 =>[
					'linked_post_type' => 'tribe_events',
					'meta_key'         => '_tec_tickets_commerce_event',
				],
			],
			'tec_tc_attendee'   => [
				0 =>[
					'linked_post_type' => 'tribe_events',
					'meta_key'         => '_tec_tickets_commerce_event',
				],
				1 =>[
					'linked_post_type' => 'tec_tc_ticket',
					'meta_key'         => '_tec_tickets_commerce_ticket',
				],
			],
			'tec_tc_order'   => [
				0 =>[
					'linked_post_type' => 'tribe_events',
					'meta_key'         => '_tec_tc_order_events_in_order',
				],
				1 => [
					'linked_post_type' => 'tec_tc_ticket',
					'meta_key'         => '_tec_tc_order_tickets_in_order',
				],
			],
		];

		$this->add_to_log( "Checking if linked post exists..." );

		$links = $relations[ $data['posttype'] ];
		foreach( $links as $link ) {
			$lpto = get_post_type_object( $link['linked_post_type'] );

			$hash_meta_key = '_' . $link['linked_post_type'] . '_export_hash';
			// We need to handle an array because Tickets Commerce orders can have multiple tickets.
			$post_ids      = $this->maybe_explode( $data[ $link['meta_key'] ] );
			foreach ( $post_ids as $post_id ) {
				$hash_meta_value = $this->hashit( $post_id );
				$post_exists     = $this->get_post_id_from_meta( $hash_meta_key, $hash_meta_value );

				if ( ! $post_exists ) {
					$this->add_to_log(
					// Translators: 1) Singular label of the related post type. 2) Title of the post being imported.
						sprintf(
							'Related `%1$s` post for `%2$s` doesn\'t exist. It will NOT be imported.',
							$lpto->labels->singular_name,
							$data['title']
						)
					);

					return false;
				}
			}
		}
		$this->add_to_log( "All linked posts found. Moving forward..." );

		return true;
	}

	/**
	 * Do modifications after a post and its post meta have been saved.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $xml_node  The post data in XML format.
	 * @param bool  $is_update Whether it's an update or not.
	 * @param int   $post_id   The ID of the inserted post.
	 *
	 * @return void
	 *
	 */
	function maybe_update_post( $post_id, $xml_node, $is_update ) {
		// Convert SimpleXml object to array for easier use.
		$record = json_decode( json_encode( ( array ) $xml_node ), 1 );

		// Grab the post type of the post being imported.
		$post_type = get_post_type( $post_id );

		// Check if the post type after the import is still the same.
		// TODO: Should we delete the imported
		if ( $post_type != $record['posttype'] ) {
			$this->add_to_log( "<span style='color:red;'><strong>POST TYPES DON'T MATCH!!!</strong></span> Original post type: `" . $record['posttype'] . "`. Post type after import: `" . $post_type . "`. Imported post will be deleted." );

			/**
			 * Filter to allow keeping a post even if the new post type doesn't match the original one.
			 */
			if ( apply_filters( 'tec_labs_wpai_delete_mismatching_post_type', true ) ) {
				wp_delete_post( $post_id, true );
				$this->add_to_log( "Post (ID: " . $post_id . ") deleted" );
			}
		}

		// Update origin for Venues.
		if ( $post_type == 'tribe_venue' ) {
			$data = [
				'create_hash'     => true,
				'origin_meta_key' => '_VenueOrigin',
			];
		}

		// Update origin for Organizers.
		if ( $post_type == 'tribe_organizer' ) {
			$data = [
				'create_hash'     => true,
				'origin_meta_key' => '_OrganizerOrigin',
			];
		}

		if ( $post_type == 'tribe_events' ) {
			$data                  = [
				'create_hash'     => true,
				'origin_meta_key' => '_EventOrigin',
				'connections'     => [
					0 => [
						'multiple'            => false,
						'record_meta_key'     => '_eventvenueid',
						'connection_meta_key' => '_EventVenueID',
						'linked_post_type'    => 'tribe_venue',
					],
					1 => [
						'multiple'            => true,
						'record_meta_key'     => '_eventorganizerid',
						'connection_meta_key' => '_EventOrganizerID',
						'linked_post_type'    => 'tribe_organizer',
					],
				],
			];
		}

		/**
		 * RSVP tickets
		 */
		if ( $post_type == 'tribe_rsvp_tickets' ) {
			$data                  = [
				'create_hash'     => true,
				'origin_meta_key' => '_RsvpOrigin',
				'connections'     => [
					0 => [
						'multiple'         => false,
						'record_meta_key'  => '_tribe_rsvp_for_event',
						'linked_post_type' => 'tribe_events',
					],
				],
			];
		}

		/**
		 * Attendees for RSVPs
		 */
		if ( $post_type == 'tribe_rsvp_attendees' ) {
			$data                  = [
				'create_hash'     => false,
				'origin_meta_key' => '_RsvpAttendeeOrigin',
				'connections'     => [
					0 => [
						'multiple'         => false,
						'record_meta_key'  => '_tribe_rsvp_event',
						'linked_post_type' => 'tribe_events',
					],
					1 => [
						'multiple'         => false,
						'record_meta_key'  => '_tribe_rsvp_product',
						'linked_post_type' => 'tribe_rsvp_tickets',
					],
				],
			];
		}

		/**
		 * Tickets with Tickets Commerce (Stripe)
		 */
		if ( $post_type == 'tec_tc_ticket' ) {
			$data                  = [
				'create_hash'     => true,
				'origin_meta_key' => '_TcTicketOrigin',
				'connections'     => [
					0 => [
						'multiple'         => false,
						'record_meta_key'  => '_tec_tickets_commerce_event',
						'linked_post_type' => 'tribe_events',
					],
				],
			];
		}

		/**
		 * Orders with Tickets Commerce (Stripe)
		 */
		if ( $post_type == 'tec_tc_order' ) {
			$data                  = [
				'create_hash'     => true,
				'origin_meta_key' => '_TCOrderOrigin',
				'connections'     => [
					0 => [
						'multiple'         => false,
						'record_meta_key'  => '_tec_tc_order_events_in_order',
						'linked_post_type' => 'tribe_events',
					],
					1 => [
						'multiple'         => true,
						'record_meta_key'  => '_tec_tc_order_tickets_in_order',
						'linked_post_type' => 'tec_tc_ticket',
					],
				],
			];
		}

		/**
		 * Attendees for Tickets Commerce (Stripe)
		 */
		if ( $post_type == 'tec_tc_attendee' ) {
			$data                  = [
				'create_hash'     => false,
				'origin_meta_key' => '_TcAttendeeOrigin',
				'connections'     => [
					0 => [
						'multiple'         => false,
						'record_meta_key'  => '_tec_tickets_commerce_event',
						'linked_post_type' => 'tribe_events',
					],
					1 => [
						'multiple'         => false,
						'record_meta_key'  => '_tec_tickets_commerce_ticket',
						'linked_post_type' => 'tec_tc_ticket',
					],
				],
			];
		}

		if ( ! empty( $data ) ) {
			$this->relink_posts( $data, $post_id, $post_type, $record );
		}
	}

	/**
	 * Adjust the label for Tickets Commerce Attendees to reflect vendor.
	 *
	 * @param array $args Post type arguments.
	 *
	 * @return array
	 */
	function tc_attendees_label( $args ) {
		$args['label'] = "Attendees - Tickets Commerce";

		return $args;
	}

	/**
	 * Adjust the label for Tickets Commerce Orders to reflect vendor.
	 *
	 * @param array $args Post type arguments.
	 *
	 * @return array
	 */
	function tc_orders_label( $args ) {
		$args['label'] = "Orders - Tickets Commerce";

		return $args;
	}

	/**
	 * Adds '_EventOrigin' to the tracked keys.
	 * Note: Updating a tracked key triggers the creation or update of the Custom Table entries.
	 *
	 * @see   \TEC\Events\Custom_Tables\V1\Updates\Meta_Watcher::get_tracked_meta_keys()
	 *
	 * @since 0.1.0
	 *
	 * @param array $tracked_keys Array of the tracked keys.
	 *
	 * @return array
	 *
	 */
	public function modify_tracked_meta_keys( $tracked_keys ) {
		$tracked_keys[] = '_EventOrigin';

		return $tracked_keys;
	}

	/**
	 * Add a message to the WP All Import log.
	 *
	 * @param $m
	 *
	 * @return void
	 */
	function add_to_log( $m ) {
		printf(
			"<div class='progress-msg tec-labs-migration-add-on'><span style='color: #334aff;'>[%s] TEC - $m</span></div>",
			date( "H:i:s" )
		);
		flush();
	}

	/**
	 * Checks whether the plugin dependency manifest is satisfied or not.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the plugin dependency manifest is satisfied or not.
	 */
	protected function check_plugin_dependencies() {
		$this->register_plugin_dependencies();

		return tribe_check_plugin( static::class );
	}

	/**
	 * Registers the plugin and dependency manifest among those managed by Tribe Common.
	 *
	 * @since 1.0.0
	 */
	protected function register_plugin_dependencies() {
		$plugin_register = new Plugin_Register();
		$plugin_register->register_plugin();

		$this->container->singleton( Plugin_Register::class, $plugin_register );
		$this->container->singleton( 'extension.wpai_add_on', $plugin_register );
	}

	/**
	 * Get this plugin's options prefix.
	 *
	 * Settings_Helper will append a trailing underscore before each option.
	 *
	 * @return string
     *
	 * @see \Tribe\Extensions\WpaiAddOn\Settings::set_options_prefix()
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_options_prefix() {
		return (string) str_replace( '-', '_', 'tec-labs-wpai-add-on' );
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_settings() {
		if ( empty( $this->settings ) ) {
			$this->settings = new Settings( $this->get_options_prefix() );
		}

		return $this->settings;
	}

	/**
	 * Get all of this extension's options.
	 *
	 * @return array
	 *
	 * TODO: Remove if not using settings
	 */
	public function get_all_options() {
		$settings = $this->get_settings();

		return $settings->get_all_options();
	}

	/**
	 * Get a specific extension option.
	 *
	 * @param $option
	 * @param string $default
	 *
	 * @return array
	 *
	 * TODO: Remove if not using settings
	 */
	public function get_option( $option, $default = '' ) {
		$settings = $this->get_settings();

		return $settings->get_option( $option, $default );
	}
}
