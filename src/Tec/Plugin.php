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
	 * Maybe delete meta data with empty values.
	 *
	 * @param int    $post_id    The ID of the current post.
	 * @param string $meta_key   The meta key being imported.
	 * @param mixed  $meta_value The meta value being imported.
	 *
	 * @return void
	 */
	public function maybe_skip_post_meta( int $post_id, string $meta_key, mixed $meta_value ) {
		$post_type = get_post_type( $post_id );

		// Bail (don't delete) if it's a post type that we don't care about.
		if ( ! in_array( $post_type, $this->get_supported_post_types() ) ) {
			return;
		}

		/**
		 * Filter to allow keeping empty meta data.
		 */
		$keep_empty_meta = apply_filters( 'tec_labs_wpai_delete_empty_meta', false );

		// Bail if we want to keep empty meta data.
		if ( $keep_empty_meta ) {
			$this->add_to_log( "Keeping empty post meta for all." );
			return;
		}

		$keep_post_meta_meta_keys = [];

		/**
		 * Allows filtering the meta keys that should be imported even with an empty value.
		 *
		 * @var array $keep_post_meta_meta_keys
		 */
		$keep_post_meta_meta_keys = apply_filters( 'tec_labs_wpai_keep_post_meta_meta_keys', $keep_post_meta_meta_keys );

		// Bail (don't delete) if we want to keep that empty post meta.
		if ( in_array( $meta_key, (array) $keep_post_meta_meta_keys ) ) {
			$this->add_to_log( "Keeping empty post meta for `" . $meta_key . "` based on filter." );
			return;
		}

		// If the meta value is empty then delete it.
		if ( empty( $meta_value ) ) {
			if ( delete_post_meta( $post_id, $meta_key ) ) {
				$this->add_to_log( "Post meta value for `" . $meta_key . "` was empty and was deleted." );
			}
			else {
				$this->add_to_log( "<span style='color:red;'>Post meta value for " . $meta_key . "was empty BUT post meta could not be deleted.</span>" );
			}
		}
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
				'connection_meta_key' => '_tec_tc_order_events_in_order',  // optional, if different than record_meta_key
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
	function relink_posts( $data, $post_id, $post_type, $record ) {
		// Start logging
		$msg = "<strong>TEC - Starting relinking process...</strong>";
		$this->add_to_log( $msg );

		// If title is empty use post ID.
		$post_title = empty( $record['title'] ) ? "post (ID: " . $post_id . ")" : "`" . $record['title'] . "`";

		// 1. Create and save the hash based on the old post ID.
		if ( $data['create_hash'] ) {
			$hash_meta_key = "_" . $post_type . "_export_hash";

			$msg = "Creating hash for " . $post_title . " was ";
			$msg .= update_post_meta( $post_id, $hash_meta_key, $this->hashit( $record['id'] ) ) ? "successful" : "NOT successful (or entry already exists)";
			$this->add_to_log( $msg );
		}

		// 2. Save / Update the origin of the post type.
		if ( ! empty( $data['origin_meta_key'] ) ) {
			$msg = "Updating origin for " . $post_title . " was ";
			$msg .= update_post_meta( $post_id, $data['origin_meta_key'], 'WPAI' ) ? "successful" : "NOT successful (or entry already exists)";
			$this->add_to_log( $msg );
		}

		// 3. Update all the links between the post types.
		if ( ! empty ( $data['connections'] ) ) {
			$update_successful = false;

			foreach ( $data['connections'] as $connection ) {
				$record_meta_key = $connection['record_meta_key'];

				// If the given meta key has a value in the record, do it.
				if ( ! empty ( $record[ $record_meta_key ] ) ) {

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
							$multiple = true;

							// Update the ticket IDs in the metadata
							if ( $post_type == 'tec_tc_order' && $record_meta_key == '_tec_tc_order_tickets_in_order' && $update_successful ) {
								$this->replace_ids_in_metavalue( $post_id, $data, $record );
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

			// 4. Resave _tribe_default_ticket_provider for tribe_events
			// Because WPAI runs wp_unslash()
			if ( $post_type == 'tribe_events' ) {
				if ( ! empty( $record['_tribe_default_ticket_provider'] ) ) {
					update_post_meta( $post_id, '_tribe_default_ticket_provider', $record['_tribe_default_ticket_provider'] );
					$this->add_to_log( "Ticket provider updated" );
				}
			}

			// 5. Update post_name (new id) and post_parent (new order id) for tc attendees
			if ( $post_type == 'tec_tc_attendee' ) {
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
						$this->add_to_log( 'Post parent couldn\'t be found in database...' );
						$stop = true;
					}
				}

				if ( $stop ) {
					$this->add_to_log( 'Deleting post' );
					wp_delete_post( $post_id );
				} else {
					$args    = [
						'ID'             => $post_id,
						'post_name'      => $post_id,
						'post_parent'    => $new_parent,
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
					];
					$success = wp_update_post( $args );

					// Logging
					$msg = "Updating post name and post parent for Attendee ";
					$msg .= $success ? "successful" : "NOT successful";
					$this->add_to_log( $msg );
				}
			}
		}
	}

	/**
	 * Hashing function.
	 *
	 * @param string $subject The subject to be hashed.
	 *
	 * @return string The hashed string.
	 */
	function hashit( $subject ) {
		return hash( 'sha256', $subject, false );
	}

	/**
	 * Resets the delimiter to the pipe (|) character and creates an array of values.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $value The delimiter separated meta value.
	 *
	 * @return string[]    String or array of meta values.
	 */
	private function maybe_explode( mixed $value ) {
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
	 * @since 0.1.0
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
	private function update_linked_post_meta( $linked_post_type, $meta_key_to_update, $post_id, $record, $multiple = false ) {
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
	function get_post_id_from_meta( $meta_key, $meta_value ) {
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
	 * Replace the old post ID with the new post ID in the postmeta table.
	 *
	 * @param int   $post_id The post ID for which the meta data needs to be updated.
	 * @param array $data    Data defining the connections and what needs to be updated.
	 * @param array $record  The post data.
	 *
	 * @return void
	 */
	private function replace_ids_in_metavalue( int $post_id, array $data, array $record ) {
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
	 * @param wpdb|QM_DB $wpdb
	 * @param string     $meta_key
	 * @param string     $meta_value
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
	 * @return array The supported post types.
	 */
	public function get_supported_post_types(): array {
		$supported_post_types = [
			'tribe_events',
			'tribe_venue',
			'tribe_organizer',
			'tribe_rsvp_tickets',
			'tribe_rsvp_attendees',
			'tec_tc_ticket',
			'tec_tc_order',
			'tec_tc_attendee',
		];

		/**
		 * Allows filtering the supported post types.
		 *
		 * @var array $supported_post_types
		 */
		return apply_filters( 'tec_labs_wpai_supported_post_types', $supported_post_types );
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
