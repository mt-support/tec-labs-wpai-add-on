<?php
/**
 * Plugin Class.
 *
 * @since   1.0.0
 *
 * @package TEC\Extensions\WpaiAddOn
 */

namespace TEC\Extensions\WpaiAddOn;

use TEC\Common\Contracts\Service_Provider;
use TEC\Extensions\WpaiAddOn\Import\Post_Handler;

/**
 * Class Plugin
 *
 * @since   1.0.0
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
	const VERSION = '1.1.0';

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
		$this->plugin_path = trailingslashit( dirname( TEC_EXTENSION_WPAI_ADD_ON_FILE ) );
		$this->plugin_dir  = trailingslashit( basename( $this->plugin_path ) );
		$this->plugin_url  = plugins_url( $this->plugin_dir, $this->plugin_path );

		// Register this provider as the main one and use a bunch of aliases.
		$this->container->singleton( static::class, $this );
		$this->container->singleton( 'extension.wpai_add_on', $this );
		$this->container->singleton( 'extension.wpai_add_on.plugin', $this );
		$this->container->singleton( Post_Handler::class, new Post_Handler() );
		$this->container->register( PUE::class );

		if ( ! $this->check_plugin_dependencies() ) {
			// If the plugin dependency manifest is not met, then bail and stop here.
			return;
		}

		// Run the label hooks
		add_action( 'plugins_loaded', [ $this, 'init_label_hooks' ] );

		// Start binds before WP All Import starts the import.
		if ( ! has_action( 'pmxi_before_post_import', [ $this, 'init_import_hooks' ] ) ) {
			add_action( 'pmxi_before_post_import', [ $this, 'init_import_hooks' ] );
		}

		// End binds.
		$this->container->register( Hooks::class );
		$this->container->register( Assets::class );
	}

	/**
	 * Setup our import hooks.
	 *
	 * @since 1.0.0
	 */
	public function init_import_hooks() {
		// WP All Import specific hooks.
		add_filter( 'tec_events_custom_tables_v1_tracked_meta_keys', [ $this, 'modify_tracked_meta_keys' ] );
		add_filter( 'wp_all_import_is_post_to_create', [ $this, 'maybe_create_post' ], 10, 3 );
		add_action( 'pmxi_update_post_meta', [ $this, 'maybe_skip_post_meta' ], 10, 3 );
		add_action( 'pmxi_saved_post', [ $this, 'maybe_update_post' ], 10, 3 );
		add_filter( 'pmxi_custom_field', [ $this, 'relink_posts_to_series' ], 10, 6 );
		// Clean ourselves up after hooks.
		remove_action( 'pmxi_before_post_import', [ $this, 'init_import_hooks' ] );
	}

	/**
	 * Run our label hooks.
	 *
	 * @since 1.0.0
	 */
	public function init_label_hooks() {
		if ( isset( $_GET['page'] ) &&
		     (
				$_GET['page'] == 'pmxe-admin-export'
				|| $_GET['page'] == 'pmxi-admin-import'
		     )
		) {
			add_filter( 'tec_tickets_commerce_attendee_post_type_args', [ $this, 'tc_attendees_label' ] );
			add_filter( 'tec_tickets_commerce_order_post_type_args', [ $this, 'tc_orders_label' ] );
			add_filter( 'tribe_tickets_register_attendee_post_type_args', [ $this, 'rsvp_attendees_label' ] );
			add_filter( 'tribe_tickets_register_order_post_type_args', [ $this, 'tpp_orders_label' ] );
		}

		// Clean ourselves up after hooks.
		remove_action( 'plugins_loaded', [ $this, 'init_label_hooks' ] );
	}

	/**
	 * Check whether a post should be imported or not.
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
		return $this->container->make( Post_Handler::class )->maybe_create_post( $continue_import, $data, $import_id );
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
		$this->container->make( Post_Handler::class )->maybe_skip_post_meta( $post_id, $meta_key, $meta_value );
	}

	/**
	 * Do modifications after a post and its post meta have been saved.
	 *
	 * This action fires when WP All Import saves a post of any type. The post ID, the record's data
	 * from your file, and a boolean value showing if the post is being updated are provided.
	 *
	 * @see   https://www.wpallimport.com/documentation/action-reference/#pmxi_saved_post
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
		$this->container->make( Post_Handler::class )->maybe_update_post( $post_id, $xml_node, $is_update );
	}

	public function relink_posts_to_series( $value, $post_id, $key, $original_value, $existing_meta_keys, $import_id ) {
		return $this->container->make( Post_Handler::class )->relink_posts_to_series( $value, $post_id, $key, $original_value, $existing_meta_keys, $import_id );
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
		return $this->container->make( Post_Handler::class )->tc_attendees_label( $args );
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
		return $this->container->make( Post_Handler::class )->tc_orders_label( $args );
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
		return $this->container->make( Post_Handler::class )->rsvp_attendees_label( $args );
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
		return $this->container->make( Post_Handler::class )->tpp_orders_label( $args );
	}

	/**
	 * Adds '_EventOrigin' to the tracked keys.
	 * Note: Updating a tracked key triggers the creation or update of the Custom Table entries.
	 *
	 * Allows filtering the list of meta keys that, when modified, should trigger an update to the custom tables’ data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tracked_keys Array of the tracked keys.
	 *
	 * @return array
	 *
	 * @see     https://docs.theeventscalendar.com/reference/hooks/tec_events_custom_tables_v1_tracked_meta_keys/
	 *
	 * @see     \TEC\Events\Custom_Tables\V1\Updates\Meta_Watcher::get_tracked_meta_keys()
	 */
	public function modify_tracked_meta_keys( array $tracked_keys ): array {
		return $this->container->make( Post_Handler::class )->modify_tracked_meta_keys( $tracked_keys );
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
}
