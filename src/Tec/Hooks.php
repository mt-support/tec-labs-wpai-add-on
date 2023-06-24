<?php
/**
 * Handles hooking all the actions and filters used by the module.
 *
 * To remove a filter:
 * ```php
 *  remove_filter( 'some_filter', [ tribe( Tribe\Extensions\WPAI\Hooks::class ), 'some_filtering_method' ] );
 *  remove_filter( 'some_filter', [ tribe( 'extension.wpai.hooks' ), 'some_filtering_method' ] );
 * ```
 *
 * To remove an action:
 * ```php
 *  remove_action( 'some_action', [ tribe( Tribe\Extensions\WPAI\Hooks::class ), 'some_method' ] );
 *  remove_action( 'some_action', [ tribe( 'extension.wpai.hooks' ), 'some_method' ] );
 * ```
 *
 * @since 0.1.1
 *
 * @package Tribe\Extensions\WPAI;
 */

namespace Tribe\Extensions\WPAI;

use TEC\Common\Contracts\Service_Provider;
use Tribe__Main as Common;

/**
 * Class Hooks.
 *
 * @since 0.1.1
 *
 * @package Tribe\Extensions\WPAI;
 */
class Hooks extends Service_Provider {

	/**
	 * Binds and sets up implementations.
	 *
	 * @since 0.1.1
	 */
	public function register() {
		$this->container->singleton( static::class, $this );
		$this->container->singleton( 'extension.wpai.hooks', $this );

		$this->add_actions();
		$this->add_filters();
	}

	/**
	 * Adds the actions required by the plugin.
	 *
	 * @since 0.1.1
	 */
	protected function add_actions() {
		add_action( 'tribe_load_text_domains', [ $this, 'load_text_domains' ] );
	}

	/**
	 * Adds the filters required by the plugin.
	 *
	 * @since 0.1.1
	 */
	protected function add_filters() {

	}

	/**
	 * Load text domain for localization of the plugin.
	 *
	 * @since 0.1.1
	 */
	public function load_text_domains() {
		$mopath = tribe( Plugin::class )->plugin_dir . 'lang/';
		$domain = 'tec-labs-wpai';

		// This will load `wp-content/languages/plugins` files first.
		Common::instance()->load_text_domain( $domain, $mopath );
	}
}
