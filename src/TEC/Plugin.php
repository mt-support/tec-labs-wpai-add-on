<?php
/**
 * Plugin Class.
 *
 * @since 1.0.0
 *
 * @package TEC\Extensions\WpaiAddOn
 */

namespace TEC\Extensions\WpaiAddOn;

use TEC\Common\Contracts\Service_Provider;

/**
 * Class Plugin
 *
 * @since 1.0.0
 *
 * @package TEC\Extensions\WpaiAddOn
 */
class Plugin extends Service_Provider {
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
	const SLUG = TEC_EXTENSION_WPAI_ADD_ON_SLUG;

	/**
	 * Stores the base slug for the extension.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const FILE = TEC_EXTENSION_WPAI_ADD_ON_FILE;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin Directory.
	 */
	public string $plugin_dir;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin path.
	 */
	public string $plugin_path;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin URL.
	 */
	public string $plugin_url;

	/**
	 * @since 1.0.0
	 *
	 * @var Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private Settings $settings;

	/**
	 * Stores the meta key name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $meta_key = "";

	/**
	 * Stores the meta value.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $meta_value = "";

	/**
	 * Stores the old ID of the linked post.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private int $old_linked_post_id;

	/**
	 * Stores the new ID of the linked post.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private int $new_linked_post_id;


	/**
	 * Set up the Extension's properties.
	 *
	 * This always executes even if the required plugins are not present.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
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
		//$this->get_settings();

		// Start binds.

		add_filter( 'tec_tickets_commerce_attendee_post_type_args', [ $this, 'tc_attendees_label' ] );
		add_filter( 'tec_tickets_commerce_order_post_type_args', [ $this, 'tc_orders_label' ] );
		add_filter( 'tribe_tickets_register_attendee_post_type_args', [ $this, 'rsvp_attendees_label' ] );
		add_filter( 'tribe_tickets_register_order_post_type_args', [ $this, 'tpp_orders_label' ] );

		add_filter( 'tec_events_custom_tables_v1_tracked_meta_keys', [ $this, 'modify_tracked_meta_keys' ] );

		add_filter( 'wp_all_import_is_post_to_create', [ $this, 'maybe_create_post' ], 10, 3 );

		add_action( 'pmxi_update_post_meta', [ $this, 'maybe_skip_post_meta' ], 10, 3 );

		add_action( 'pmxi_saved_post', [ $this, 'maybe_update_post' ], 10, 3 );

		// End binds.

		$this->container->register( Hooks::class );
		$this->container->register( Assets::class );
	}

	/**
	 * Check whether a post should to be imported or not.
	 * We only do this for post types that must have a connection.
	 * - Check for data validity.
	 * - Check if the connection exists.
	 *
	 * This filter is used to determine if a post should be created or skipped.
	 * The returned value should be either true to create the post or false to skip it.
	 *
	 * @see https://www.wpallimport.com/documentation/action-reference/#wp_all_import_is_post_to_create
	 *
	 * @param bool  $continue_import True to import, false to skip import.
	 * @param array $data            Array of data to import.
	 * @param int   $import_id       The ID of the import.
	 *
	 * @return bool
	 */
	public function maybe_create_post( bool $continue_import, array $data, int $import_id ): bool {
		$this->add_to_log( "<strong>THE EVENTS CALENDAR EXTENSION: WPAI ADD-ON:</strong>" );

		// Bail if it's not a supported post type.
		if (
			! isset( $data['posttype'] )
			|| ! in_array( $data['posttype'], $this->get_supported_post_types( false ), true )
		) {
			$this->add_to_log( "Not supported post type. Skipping.");
			return false;
		}

		// Bail if data is not valid.
		if ( ! $this->check_data_validity( $data ) ) {
			return false;
		}

		/**
		 * Filter to allow forcing the import if the related post doesn't exist.
		 *
		 * @var array $data      Array of data to import.
		 * @var int   $import_id The ID of the import
		 */
		if ( apply_filters( 'tec_labs_wpai_force_import_' . $data['posttype'], false, $data, $import_id ) ) {
			$pto = get_post_type_object( $data['posttype'] );
			// Get post type label
			$this->add_to_log(
			// Translators: 1) Singular label of the post type being imported. 2) Title of the post currently imported.
				sprintf(
					'%1$s `%2$s` will be force-imported, even if a related post does not exist.',
					$pto->labels->singular_name,
					$data['title'],
				)
			);

			return true;
		}

		// Check if relation exists and proceed accordingly.
		return $this->check_relation_exists( $data );
	}

	/**
	 * Check if data is valid.
	 * Note: at this point $data['posttype'] exists.
	 *
	 * @param array $data Array of data to import.
	 *
	 * @return bool
	 */
	private function check_data_validity( array $data ): bool {
		$this->add_to_log( "Checking if data is valid..." );

		/**
		 * For Tickets Commerce ticket:
		 * Bail if
		 * - Link to event missing
		 */
		if (
			$data['posttype'] === 'tec_tc_ticket'
			&& isset( $data['_tec_tickets_commerce_event'] )
			&& is_array( $data['_tec_tickets_commerce_event'] )
			&& empty( $data['_tec_tickets_commerce_event'] )
		) {
			$this->add_to_log( "Corrupt data." );
			$this->add_to_log( "-> Link to event is missing." );
			$this->add_to_log( "-> Skipping record." );

			return false;
		}

		/**
		 * For Tickets Commerce Order:
		 * Bail if
		 * - Order total value doesn't exist
		 * - Post status doesn't exist
		 * - Post status is anything else than "tec-tc-xxx"
		 */
		if ( $data['posttype'] === 'tec_tc_order' ) {
			if (
				(
					isset( $data['_tec_tc_order_total_value'] )
					&& is_array( $data['_tec_tc_order_total_value'] )
					&& empty( $data['_tec_tc_order_total_value'] )
				)
				|| ! isset( $data['status'] )
				|| ! str_starts_with( $data['status'], 'tec-tc-' )
			) {
				$this->add_to_log( "Corrupt data:" );
				$this->add_to_log( "-> Data corrupt OR post status missing OR post status incorrect." );
				$this->add_to_log( "-> Order value: " . $data['_tec_tc_order_total_value'] . "; Post status: " . $data['status'] . ")" );
				$this->add_to_log( "-> Skipping record." );
				return false;
			}
		}

		/**
		 * For Tickets Commerce Attendees:
		 * Bail if
		 * - There is no link to the ticket
		 * - There is no link to the event
		 */
		if ( $data['posttype'] == 'tec_tc_attendee' ) {
			if (
				(
					isset( $data['_tec_tickets_commerce_ticket'] )
					&& is_array( $data['_tec_tickets_commerce_ticket'] )
					&& empty( $data['_tec_tickets_commerce_ticket'] )
				)
				||
				(
					isset( $data['_tec_tickets_commerce_event'] )
					&& is_array( $data['_tec_tickets_commerce_event'] )
					&& empty( $data['_tec_tickets_commerce_event'] )
				)
			) {
				$this->add_to_log( "Corrupt data." );
				$this->add_to_log( "-> Link to ticket or event is missing." );
				$this->add_to_log( "-> Skipping record." );
				return false;
			}
		}
		$this->add_to_log( "Yes, data is valid. Moving forward..." );

		return true;
	}

	/**
	 * Check if the related post the current one being imported depends on exists.
	 * Note: at this point $data['posttype'] exists.
	 *
	 * @param array $data Array of the data being imported.
	 *
	 * @return bool
	 */
	function check_relation_exists( array $data ): bool {
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

		if ( ! array_key_exists( $data['posttype'], $relations ) ) {
			$this->add_to_log( "Post type has no linked posts. Moving forward..." );
			return true;
		}

		$this->add_to_log( "Checking if linked posts exist..." );

		$links = $relations[ $data['posttype'] ];
		foreach( $links as $link ) {
			// Stop immediately if there is an issue. Otherwise, keep cycling.
			if ( ! $this->check_link( $link, $data ) ) {
				return false;
			}
		}

		// Go ahead if there are no issues.
		$this->add_to_log( "All linked posts found. Moving forward..." );
		return true;
	}

	/**
	 * Check if the linked post entry exists.
	 *
	 * @param array $link Array of data of the linked post type.
	 *                    'linked_post_type': the post type
	 *                    'meta_key': the meta key used to connect the two post types.
	 * @param array $data Array of the data being imported.
	 *
	 * @return bool
	 */
	public function check_link( array $link, array $data ): bool {
		$lpto = get_post_type_object( $link['linked_post_type'] );

		$hash_meta_key = '_' . $link['linked_post_type'] . '_export_hash';

		// Check if meta key exists.
		if ( ! isset ( $data[ $link['meta_key'] ] ) ) {
			$this->add_to_log(
			// Translators: 1) Title of the post being imported.
				sprintf(
					"The required meta_key does not exist. %s will NOT be imported.",
					$data['title']
				)
			);

			return false;
		}

		// We need to handle an array because Tickets Commerce orders can have multiple tickets.

		// Bail if not string.
		if ( ! is_string( $data[ $link['meta_key'] ] ) ) {
			$this->add_to_log( '`meta_key` is not a string. Skipping.');
			return false;
		}

		$post_ids = $this->maybe_explode( $data[ $link['meta_key'] ] );
		foreach ( $post_ids as $post_id ) {
			$hash_meta_value = $this->hashit( $post_id );
			$post_exists     = $this->get_post_id_from_meta( $hash_meta_key, $hash_meta_value );

			if ( ! $post_exists ) {
				$this->add_to_log(
				// Translators: 1) Singular label of the related post type. 2) Title of the post being imported.
					sprintf(
						'Related `%1$s` post for `%2$s` does not exist. It will NOT be imported.',
						$lpto->labels->singular_name,
						$data['title']
					)
				);

				return false;
			}
		}

		return true;
	}

	/**
	 * Maybe delete metadata with empty values.
	 * This fires when WP All Import creates or updates post meta (custom fields).
	 * The post ID, field name, and field value are provided.
	 *
	 * @see https://www.wpallimport.com/documentation/action-reference/#pmxi_update_post_meta
	 *
	 * @param int    $post_id    The ID of the current post.
	 * @param string $meta_key   The meta key being imported.
	 * @param mixed  $meta_value The meta value being imported.
	 *
	 * @return void
	 */
	public function maybe_skip_post_meta( int $post_id, string $meta_key, $meta_value ): void {
		$post_type = get_post_type( $post_id );

		// Bail (don't delete) if it's a post type that we don't care about.
		if ( ! in_array( $post_type, $this->get_supported_post_types( false ), true ) ) {
			return;
		}

		/**
		 * Filter to allow keeping empty metadata.
		 */
		$keep_empty_meta = apply_filters( 'tec_labs_wpai_keep_empty_meta', false );

		// Bail if we want to keep empty metadata.
		if ( $keep_empty_meta ) {
			$this->add_to_log( "Keeping empty post meta for all." );
			return;
		}

		// An array of meta keys that should be preserved even with empty values.
		$keep_post_meta_meta_keys = [];

		/**
		 * Allows filtering the meta keys that should be imported even with an empty value.
		 *
		 * @var array $keep_post_meta_meta_keys
		 */
		$keep_post_meta_meta_keys = apply_filters( 'tec_labs_wpai_keep_post_meta_meta_keys', $keep_post_meta_meta_keys );

		// Bail (don't delete) if we want to keep that empty post meta.
		if ( in_array( $meta_key, $keep_post_meta_meta_keys, true ) ) {
			$this->add_to_log( "Keeping empty post meta for `" . $meta_key . "` based on filter." );
			return;
		}

		// If the meta value is empty then delete it.
		if ( empty( $meta_value ) ) {
			delete_post_meta( $post_id, $meta_key );

			if ( metadata_exists( 'post', $post_id, $meta_key ) ) {
				$this->add_to_log( "<span style='color:red;'>Post meta value for $meta_key was empty BUT post meta could not be deleted.</span>" );
			} else {
				$this->add_to_log( "Post meta value for `" . $meta_key . "` was empty and was deleted (or cannot be found)." );
			}
		}
	}

	/**
	 * Do modifications after a post and its post meta have been saved.
	 *
	 * This action fires when WP All Import saves a post of any type. The post ID, the record's data
	 * from your file, and a boolean value showing if the post is being updated are provided.
	 *
	 * @see https://www.wpallimport.com/documentation/action-reference/#pmxi_saved_post
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id   The ID of the inserted post.
	 * @param mixed $xml_node  The post data in XML format.
	 * @param bool  $is_update Whether it's an update or not.
	 *
	 * @return void
	 */
	function maybe_update_post( int $post_id, $xml_node, bool $is_update ): void {
		// Convert SimpleXml object to array for easier use.
		$record = json_decode( json_encode( ( array ) $xml_node ), 1 );

		// Grab the post type of the post being imported.
		$post_type = get_post_type( $post_id );

		// Check if the post type after the import is still the same.
		if ( $post_type != $record['posttype'] ) {
			$this->add_to_log( "<span style='color:red;'><strong>POST TYPES DON'T MATCH!!!</strong></span> Original post type: `" . $record['posttype'] . "`. Post type after import: `" . $post_type . "`." );
			/**
			 * Filter to allow keeping a post even if the new post type doesn't match the original one.
			 */
			if ( apply_filters( 'tec_labs_wpai_delete_mismatching_post_type', true ) ) {
				wp_delete_post( $post_id, true );
				$this->add_to_log( "Post (ID: " . $post_id . ") deleted." );
			} else {
				$this->add_to_log( "Post (ID: " . $post_id . ") will be imported based on filter." );
			}
		}

		switch ( $post_type ) {
			case "tribe_venue":
				$data = [
					'create_hash'     => true,
					'origin_meta_key' => '_VenueOrigin',
				];
				break;
			case "tribe_organizer":
				$data = [
					'create_hash'     => true,
					'origin_meta_key' => '_OrganizerOrigin',
				];
				break;
			case "tribe_events":
				$data = [
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
				break;
			case "tribe_rsvp_tickets":
				$data = [
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
				break;
			case "tribe_rsvp_attendees":
				$data = [
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
				break;
			case "tec_tc_ticket":
				$data = [
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
				break;
			case "tec_tc_order":
				$data = [
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
				break;
			case "tec_tc_attendee":
				$data = [
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
				break;
			default:
				break;
		}

		if ( ! empty( $data ) ) {
			$this->relink_posts( $data, $post_id, $post_type, $record );
		}
	}

	/**
	 * Create the new links between the posts.
	 *
	 * Sample:
	 *
	$data = [
		'create_hash'     => true,
		'origin_meta_key' => '_TCOrderOrigin',
		'connections'     => [
			0 => [
		        'multiple'            => false,
				'record_meta_key'     => '_tec_tc_order_events_in_order',
				'connection_meta_key' => '_tec_tc_order_events_in_order',  // optional, if different from record_meta_key
				'linked_post_type'    => 'tribe_events',
			],
		],
	];
	 *
	 * @param array  $data      Data defining the connections and what needs to be updated.
	 * @param int    $post_id   The new post ID.
	 * @param string $post_type The post type.
	 * @param array  $record    The post data.
	 *
	 * @return void
	 */
	function relink_posts( array $data, int $post_id, string $post_type, array $record ): void {
		// Start logging
		$msg = "<strong>TEC - Starting relinking process...</strong>";
		$this->add_to_log( $msg );

		// If title is empty use post ID. (Used for log messages only.)
		$post_title = empty( $record['title'] ) ? "post (ID: " . $post_id . ")" : "`" . $record['title'] . "`";

		// 1. Create and save the hash based on the old post ID.
		if ( $data['create_hash'] ) {
			$this->create_hash( $post_id, $post_title, $post_type, $record['id'] );
		}

		// 2. Save / Update the origin of the post type.
		if ( ! empty( $data['origin_meta_key'] ) ) {
			$this->update_post_origin( $post_title, $post_id, $data['origin_meta_key'] );
		}

		// 3. Update all the links between the post types.
		if ( ! empty ( $data['connections'] ) ) {

			foreach ( $data['connections'] as $connection ) {
				$this->update_post_type_connections( $connection, $record, $post_id, $post_title, $post_type );
			}

			// 4. Re-save _tribe_default_ticket_provider for tribe_events
			// Because WPAI runs wp_unslash()
			if ( $post_type == 'tribe_events' ) {
				$this->resave_ticket_provider_for_event( $record, $post_id );
			}

			// 5. Update post_name (new id) and post_parent (new order id) for tc attendees
			if ( $post_type == 'tec_tc_attendee' ) {
				$this->maybe_update_post_data_for_attendee( $record, $post_id );
			}
		}
	}

	/**
	 * Create the hash based on the old post ID and save it as metadata.
	 *
	 * @param int    $post_id    The new post ID.
	 * @param string $post_title The post title (used for log messages).
	 * @param string $post_type  The post type.
	 * @param int    $record_id  The old post ID.
	 *
	 * @return void
	 */
	public function create_hash( int $post_id, string $post_title, string $post_type, int $record_id ): void {
			$hash_meta_key = "_" . $post_type . "_export_hash";
			$msg = "Creating hash for " . $post_title . " was ";
			$msg .= update_post_meta( $post_id, $hash_meta_key, $this->hashit( $record_id ) ) ? "successful" : "NOT successful (or entry already exists)";
			$this->add_to_log( $msg );
	}

	/**
	 * Set or update the post origin.
	 *
	 * @param string $post_title      The post title (used for log messages).
	 * @param int    $post_id         The new post ID.
	 * @param string $origin_meta_key The metakey used to save the origin value.
	 *
	 * @return void
	 */
	public function update_post_origin( string $post_title, int $post_id, string $origin_meta_key ): void {
		$msg = "Updating origin for " . $post_title . " was ";
		$msg .= update_post_meta( $post_id, $origin_meta_key, 'WPAI' ) ? "successful" : "NOT successful (or entry already exists)";
		$this->add_to_log( $msg );
	}

	/**
	 * Update the connections between the post types.
	 *
	 * @param array  $connection Array containing information about the connections.
	 * @param array  $record     The post data.
	 * @param int    $post_id    The new post ID.
	 * @param string $post_title The post title (used for logging).
	 * @param string $post_type  The post type.
	 *
	 * @return void
	 */
	public function update_post_type_connections( array $connection, array $record, int $post_id, string $post_title, string $post_type ): void {
		$record_meta_key = $connection['record_meta_key'];

		// If the given meta key has a value in the record, and it is a string, do it.
		if (
			! empty ( $record[ $record_meta_key ] )
			&& is_string( $record[ $record_meta_key ] )
		)
		{
			// If there are multiple connections, e.g. more organizers for an event.
			if ( $connection['multiple'] ) {
				$multiple = false;
				$ids      = $this->maybe_explode( $record[ $record_meta_key ] );
				foreach ( $ids as $id ) {
					$this->old_linked_post_id = $id;
					$record[ $record_meta_key ] = $id;
					$update_successful = $this->update_linked_post_meta(
						$connection['linked_post_type'],
						! empty ( $connection['connection_meta_key'] ) ? $connection['connection_meta_key'] : $record_meta_key,
						$post_id,
						$record,
						$multiple
					);

					$msg = $multiple ? "Adding " : "Updating ";
					$msg .= "metadata `" . $record_meta_key . "` for " . $post_title . " was ";
					$msg .= $update_successful ? "successful" : "NOT successful (or linked post doesn't exist)";
					$this->add_to_log( $msg );
					// Set to `true` after first.
					$multiple = true;

					// Update the ticket IDs in the metadata
					if ( $post_type == 'tec_tc_order' && $record_meta_key == '_tec_tc_order_tickets_in_order' && $update_successful ) {
						$this->replace_ids_in_metavalue( $post_id );
					}
				}
			} else {
				$update_successful = $this->update_linked_post_meta( $connection['linked_post_type'], $record_meta_key, $post_id, $record );
				$msg = "Updating metadata `" . $record_meta_key . "` for " . $post_title . " was ";
				$msg .= $update_successful ? "successful" : "NOT successful";
				$this->add_to_log( $msg );
			}
		}
	}

	/**
	 * Re-save _tribe_default_ticket_provider for tribe_event.
	 * WP All Import runs wp_unslash() and destroys "TEC\Tickets\Commerce\Module"
	 *
	 * @param array $record  The post data.
	 * @param int   $post_id The new post ID.
	 *
	 * @return void
	 */
	public function resave_ticket_provider_for_event( array $record, int $post_id ): void {
		if (
			! empty( $record['_tribe_default_ticket_provider'] )
		    && $record['_tribe_default_ticket_provider'] == "TEC\Tickets\Commerce\Module"
		) {
			if ( $this->fix_ticket_provider( $post_id ) ) {
				$this->add_to_log( "Ticket provider successfully updated." );
			} else {
				$this->add_to_log( "Ticket provider update failed." );
			}
		}
	}

	/**
	 * Update post_name (new id) and post_parent (new order id) for Tickets Commerce Attendees.
	 *
	 * @param array $record  The post data.
	 * @param int   $post_id The new post ID.
	 *
	 * @return void
	 */
	public function maybe_update_post_data_for_attendee( array $record, int $post_id ): void {
		$stop = false;

		// If there is no record for the parent in the source data, then stop.
		if ( ! isset( $record['parent'] ) ) {
			$this->add_to_log( 'Post parent missing from import data...' );
			$stop = true;
		} else {
			// Hash the old linked post type ID (tec_tc_order).
			$this->meta_value = $this->hashit( $record['parent'] );
			$this->meta_key   = "_tec_tc_order_export_hash";

			// Grab the new post ID based on the hash.
			$new_parent = $this->grab_post_id_based_on_meta();

			// If the parent cannot be found in the database then stop.
			if ( $new_parent == null ) {
				$this->add_to_log( 'Post parent could not be found in database...' );
				$stop = true;
			}
		}

		if ( $stop ) {
			$this->add_to_log( 'Deleting post.' );
			wp_delete_post( $post_id );
		} else {
			$args    = [
				'ID'             => $post_id,
				'post_name'      => $post_id,
				'post_parent'    => $new_parent,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			];
			$success = wp_update_post( $args, true );

			// Logging
			$msg = "Updating post name and post parent for Attendee ";
			if ( $success > 0 ) {
				$msg .= "successful.";
			}
			elseif ( $success <= 0 ) {
				$msg .= "NOT successful";
			}
			elseif ( is_wp_error( $success ) ) {
				$msg .= "failed with the following error: ";
				$msg .= $success->get_error_message();
			}
			$this->add_to_log( $msg );
		}
	}

	/**
	 * Hashing function.
	 *
	 * @param string $subject The subject to be hashed.
	 *
	 * @return string The hashed string.
	 */
	function hashit( string $subject ): string {
		return hash( 'sha256', $subject, false );
	}

	/**
	 * Resets the delimiter to the pipe (|) character and creates an array of values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The delimiter separated meta value.
	 *
	 * @return string[]     String or array of meta values.
	 */
	private function maybe_explode( string $value ): array {
		// Not digits
		$pattern = '/(\D)/i';

		// Reset the delimiter to pipe (|)
		$value = preg_replace( $pattern, "|", $value );

		// Explode the list of IDs into an array.
		return explode( "|", $value );
	}

	/**
	 * Update the meta field based on the found hash
	 *
	 * @since 1.0.0
	 *
	 * @param string $linked_post_type   The connected post type.
	 * @param string $meta_key_to_update The meta field that needs to be updated.
	 * @param int    $post_id            The ID of the last inserted post.
	 * @param array  $record             The data of the last inserted post.
	 * @param bool   $multiple           Whether we are importing more values for the same meta key.
	 *
	 * @return int|false                 Meta ID (add) or true (update) on success, false on failure.
	 *
	 */
	private function update_linked_post_meta( string $linked_post_type, string $meta_key_to_update, int $post_id, array $record, bool $multiple = false ) {
		$this->add_to_log( "Updating linked post meta..." );
		$meta_key       = "_" . $linked_post_type . "_export_hash";
		$this->meta_key = $meta_key;

		$metafield_lowercase = strtolower( $meta_key_to_update );  // In the WPAI $record the meta keys come through as lowercase.

		// Hash the old linked post type ID.
		$meta_value = $this->hashit( $record[ $metafield_lowercase ] );
		$this->meta_value = $meta_value;

		// Grab the new post ID based on the hash.
		$new_linked_post_id       = $this->grab_post_id_based_on_meta();
		$this->new_linked_post_id = $new_linked_post_id;

		// If there's an ID, update.
		if ( $new_linked_post_id ) {
			if ( $multiple ) {
				return add_post_meta( $post_id, $meta_key_to_update, $new_linked_post_id );
			} else {
				return update_post_meta( $post_id, $meta_key_to_update, $new_linked_post_id );
			}
		}
		return false;
	}

	/**
	 * Retrieve post ID based on meta key = meta value pair.
	 *
	 * @param string $meta_key   The meta key.
	 * @param string $meta_value The meta value.
	 *
	 * @return false|string|null
	 */
	function get_post_id_from_meta( string $meta_key, string $meta_value ) {
		global $wpdb;
		$pid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `post_id` 
			FROM $wpdb->postmeta 
			WHERE `meta_value` = %s 
			AND `meta_key` = %s 
			ORDER BY `post_id` 
			LIMIT 1",
				[ $meta_value, $meta_key ]
			)
		);
		if( $pid != '' ) {
			return $pid;
		} else {
			return false;
		}
	}

	/**
	 * Update the '_tribe_default_ticket_provider'.
	 * WPAI uses "wp_unslash()" before saving the data.
	 * This method corrects that.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 */
	function fix_ticket_provider( int $post_id ) {
		global $wpdb;
		$success = $wpdb->query(
			$wpdb->prepare(
			"UPDATE $wpdb->postmeta 
			SET `meta_value` = %s 
			WHERE `post_id` = %s 
			AND `meta_key` = %s 
			AND `meta_value` = %s",
			'TEC\Tickets\Commerce\Module',
			$post_id,
			'_tribe_default_ticket_provider',
			'TECTicketsCommerceModule'
			)
		);

		return $success;
	}

	/**
	 * Replace the old post ID with the new post ID in the postmeta table.
	 *
	 * @param int $post_id The post ID for which the metadata needs to be updated.
	 *
	 * @return void
	 */
	private function replace_ids_in_metavalue( int $post_id ): void {
		$old_linked_post_id = $this->old_linked_post_id;
		$new_linked_post_id = $this->new_linked_post_id;

		// Grab the meta entry, we need the new ID
		$meta = get_post_meta( $post_id, '_tec_tc_order_items', true );

		// Grab the part with the current ID
		// Copy the part to the new ID
		$meta[$new_linked_post_id] = $meta[$old_linked_post_id];
		$meta[$new_linked_post_id]['ticket_id'] = $new_linked_post_id;

		// Remove the part with the current ID
		unset( $meta[$old_linked_post_id] );

		// Re-save meta entry
		$success = update_post_meta( $post_id, '_tec_tc_order_items', $meta );

		// Logging
		$msg = "Updating IDs in meta value ";
		$msg .= $success ? "successful" : "NOT successful";
		$this->add_to_log( $msg );
	}

	/**
	 * Retrieve the post ID from the postmeta table based on a metakey=metavalue pair.
	 *
	 * @return string|null
	 */
	private function grab_post_id_based_on_meta(): ?string {
		global $wpdb;
		$meta_key = $this->meta_key;
		$meta_value = $this->meta_value;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"
					SELECT post_id 
					FROM $wpdb->postmeta 
					WHERE meta_key=%s 
					AND meta_value=%s
				",
				[ $meta_key, $meta_value ]
			)
		);

		return $post_id;
	}

	/**
	 * Get the post types supported by the extension.
	 *
	 * @param bool $with_connection Whether it is only the post types that require a connection (true) or all post types (false).
	 *
	 * @return array The supported post types.
	 */
	public function get_supported_post_types( bool $with_connection = true ): array {
		// Post types that need a connection.
		$supported_post_types = [
			'tribe_rsvp_tickets',
			'tribe_rsvp_attendees',
			'tec_tc_ticket',
			'tec_tc_order',
			'tec_tc_attendee',
		];

		// Post types that don't require a connection.
		if ( ! $with_connection ) {
			array_unshift(
				$supported_post_types,
				'tribe_events',
				'tribe_venue',
				'tribe_organizer'
			);
		}

		/**
		 * Allows filtering the supported post types.
		 *
		 * @var array $supported_post_types Array of the supported post types
		 * @var bool  $with_connection      Whether it is only the post types that require a connection (true) or all post types (false).
		 */
		return apply_filters( 'tec_labs_wpai_supported_post_types', $supported_post_types, $with_connection );
	}

	/**
	 * Adjust the label for Tickets Commerce Attendees to reflect eCommerce provider.
	 *
	 * @see https://docs.theeventscalendar.com/reference/hooks/tec_tickets_commerce_attendee_post_type_args/
	 *
	 * @param array $args Post type arguments.
	 *
	 * @return array
	 */
	function tc_attendees_label( array $args ): array {
		$args['label'] = "Tickets Commerce Attendees";

		return $args;
	}

	/**
	 * Adjust the label for Tickets Commerce Orders to reflect eCommerce provider.
	 *
	 * @see https://docs.theeventscalendar.com/reference/hooks/tec_tickets_commerce_order_post_type_args/
	 *
	 * @param array $args Post type arguments.
	 *
	 * @return array
	 */
	function tc_orders_label( array $args ): array {
		$args['label'] = "Tickets Commerce Orders";

		return $args;
	}

	/**
	 * Adjust the label for RSVP Attendees to reflect attendee type.
	 *
	 * @see https://docs.theeventscalendar.com/reference/hooks/tribe_tickets_register_attendee_post_type_args/
	 *
	 * @param array $args Post type arguments.
	 *
	 * @return array
	 */
	function rsvp_attendees_label( $args ) {
		if ( $args['hierarchical'] == true ) {
			$args['label'] = 'RSVP Attendees';
		} else {
			$args['label'] = 'Tribe Commerce Attendees';
		}

		return $args;
	}

	/**
	 * Adjust the label for Tribe Commerce Orders to reflect eCommerce provider.
	 * Note: Tribe Commerce has been deprecated in favor of Tickets Commerce.
	 *
	 * @see https://docs.theeventscalendar.com/reference/hooks/tribe_tickets_register_order_post_type_args/
	 *
	 * @param array $args Post type arguments.
	 *
	 * @return array
	 */
	function tpp_orders_label( array $args ): array {
		$args['label'] = "Tribe Commerce Orders";

		return $args;
	}

	/**
	 * Adds '_EventOrigin' to the tracked keys.
	 * Note: Updating a tracked key triggers the creation or update of the Custom Table entries.
	 *
	 * Allows filtering the list of meta keys that, when modified, should trigger an update to the custom tables’ data.
	 * @see     \TEC\Events\Custom_Tables\V1\Updates\Meta_Watcher::get_tracked_meta_keys()
	 * @see     https://docs.theeventscalendar.com/reference/hooks/tec_events_custom_tables_v1_tracked_meta_keys/
	 *
	 * @since   1.0.0
	 *
	 * @param array $tracked_keys Array of the tracked keys.
	 *
	 * @return array
	 *
	 */
	public function modify_tracked_meta_keys( array $tracked_keys ): array {
		$tracked_keys[] = '_EventOrigin';

		return $tracked_keys;
	}

	/**
	 * Add a message to the WP All Import log.
	 *
	 * @param string $message The message to be added to the log.
	 *
	 * @return void
	 */
	function add_to_log( string $message ): void {
		printf(
			"<div class='progress-msg tec-labs-migration-add-on'><span style='color: #334aff;'>[%s] TEC - $message</span></div>",
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
	protected function check_plugin_dependencies(): bool {
		$this->register_plugin_dependencies();

		return tribe_check_plugin( static::class );
	}

	/**
	 * Registers the plugin and dependency manifest among those managed by Tribe Common.
	 *
	 * @since 1.0.0
	 */
	protected function register_plugin_dependencies(): void {
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
	 * @see \TEC\Extensions\WpaiAddOn\Settings::set_options_prefix()
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_options_prefix(): string {
		return (string) str_replace( '-', '_', 'tec-labs-wpai-add-on' );
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_settings(): Settings {
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
	public function get_all_options(): array {
		$settings = $this->get_settings();

		return $settings->get_all_options();
	}

	/**
	 * Get a specific extension option.
	 *
	 * @param string $option  The option name.
	 * @param string $default The default option value.
	 *
	 * @return array
	 *
	 * TODO: Remove if not using settings
	 */
	public function get_option( string $option, string $default = '' ): array {
		$settings = $this->get_settings();

		return $settings->get_option( $option, $default );
	}
}
