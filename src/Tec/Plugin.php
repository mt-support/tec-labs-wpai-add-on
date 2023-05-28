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



		// End binds.

		$this->container->register( Hooks::class );
		$this->container->register( Assets::class );
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
