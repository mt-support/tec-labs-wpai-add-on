<?php
/**
 * Handles registering all Assets for the Plugin.
 *
 * To remove an Asset you can use the global assets handler:
 *
 * ```php
 *  tribe( 'assets' )->remove( 'asset-name' );
 * ```
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\WpaiAddOn
 */

namespace Tribe\Extensions\WpaiAddOn;

/**
 * Register Assets.
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\WpaiAddOn
 */
class Assets extends \tad_DI52_ServiceProvider {
	/**
	 * Binds and sets up implementations.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		$this->container->singleton( static::class, $this );
		$this->container->singleton( 'extension.wpai_add_on.assets', $this );

		$plugin = tribe( Plugin::class );

	}
}