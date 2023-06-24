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
 * @since 0.1.1
 *
 * @package Tribe\Extensions\WPAI
 */

namespace Tribe\Extensions\WPAI;

use TEC\Common\Contracts\Service_Provider;

/**
 * Register Assets.
 *
 * @since 0.1.1
 *
 * @package Tribe\Extensions\WPAI
 */
class Assets extends Service_Provider {
	/**
	 * Binds and sets up implementations.
	 *
	 * @since 0.1.1
	 */
	public function register() {
		$this->container->singleton( static::class, $this );
		$this->container->singleton( 'extension.wpai.assets', $this );

		$plugin = tribe( Plugin::class );

	}
}
