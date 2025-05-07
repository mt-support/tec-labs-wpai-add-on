<?php
/**
 * Plugin Name:       The Events Calendar Extension: WP All Import Add-On
 * Plugin URI:        https://theeventscalendar.com/extensions/wpai-add-on/
 * GitHub Plugin URI: https://github.com/mt-support/tec-labs-wpai-add-on
 * Description:       WP All Import add-on for The Events Calendar. It can handle Events, Venues, and multiple Organizers, RSVPs, RSVP Attendees, Tickets Commerce Tickets, Tickets Commerce Attendees, and Tickets Commerce Orders. The following are NOT supported: Series, Recurring Events, WooCommerce Tickets, WooCommerce Orders, WooCommerce Attendees.
 * Version:           1.2.0-dev
 * Author:            The Events Calendar
 * Author URI:        https://evnt.is/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tec-labs-wpai-add-on
 *
 *     This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

/**
 * Define the base file that loaded the plugin for determining plugin path and other variables.
 *
 * @since 1.0.0
 *
 * @var string Base file that loaded the plugin.
 */
define( 'TEC_EXTENSION_WPAI_ADD_ON_FILE', __FILE__ );

/**
 * A base slug used to uniquely identify this extension.
 *
 * @since 1.0.0
 *
 * @var string A unique slug used in context to uniquely identify this extension.
 */
define( 'TEC_EXTENSION_WPAI_ADD_ON_SLUG', 'tec-labs-wpai-add-on' );

/**
 * Register and load the service provider for loading the extension.
 *
 * @since 1.0.0
 */
function tec_extension_wpai_add_on() {
	// When we don't have autoloader from common we bail.
	if ( ! class_exists( 'Tribe__Autoloader' ) ) {
		return;
	}

	// Register the namespace so we can the plugin on the service provider registration.
	Tribe__Autoloader::instance()->register_prefix(
		'\\TEC\\Extensions\\WpaiAddOn\\',
		__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'TEC',
		TEC_EXTENSION_WPAI_ADD_ON_SLUG
	);

	// Deactivates the plugin in case of the main class didn't autoload.
	if ( ! class_exists( '\TEC\Extensions\WpaiAddOn\Plugin' ) ) {
		tribe_transient_notice(
			TEC_EXTENSION_WPAI_ADD_ON_SLUG,
			'<p>' . esc_html__( 'Couldn\'t properly load "The Events Calendar Extension: WP All Import Add-On" the extension was deactivated.', TEC_EXTENSION_WPAI_ADD_ON_SLUG ) . '</p>',
			[],
			// 1 second after that make sure the transient is removed.
			1
		);

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		deactivate_plugins( __FILE__, true );
		return;
	}

	tribe_register_provider( '\TEC\Extensions\WpaiAddOn\Plugin' );
}

// Loads after common is already properly loaded.
add_action( 'tribe_common_loaded', 'tec_extension_wpai_add_on' );
