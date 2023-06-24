<?php
/**
 * Plugin Name:       TEC Labs Extension: WP All Import add-on for The Events Calendar
 * Plugin URI:        __TRIBE_URL__
 * GitHub Plugin URI: https://github.com/mt-support/tec-labs-__TRIBE_SLUG__
 * Description:       __TRIBE_DESCRIPTION__
 * Version:           0.1.1
 * Author:            The Events Calendar
 * Author URI:        https://evnt.is/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tec-labs-wpai
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
 * @since 0.1.1
 *
 * @var string Base file that loaded the plugin.
 */
define( 'TRIBE_EXTENSION_WPAI_FILE', __FILE__ );

/**
 * Register and load the service provider for loading the extension.
 *
 * @since 0.1.1
 */
function tribe_extension_wpai() {
	// When we don't have autoloader from common we bail.
	if ( ! class_exists( 'Tribe__Autoloader' ) ) {
		return;
	}

	// Register the namespace so we can the plugin on the service provider registration.
	Tribe__Autoloader::instance()->register_prefix(
		'\\Tribe\\Extensions\\WPAI\\',
		__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Tec',
		'__TRIBE_SLUG__'
	);

	// Deactivates the plugin in case of the main class didn't autoload.
	if ( ! class_exists( '\Tribe\Extensions\WPAI\Plugin' ) ) {
		tribe_transient_notice(
			'__TRIBE_SLUG__',
			'<p>' . esc_html__( 'Couldn\'t properly load "TEC Labs Extension: WP All Import add-on for The Events Calendar" the extension was deactivated.', 'tec-labs-wpai' ) . '</p>',
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

	tribe_register_provider( '\Tribe\Extensions\WPAI\Plugin' );
}

// Loads after common is already properly loaded.
add_action( 'tribe_common_loaded', 'tribe_extension_wpai' );
