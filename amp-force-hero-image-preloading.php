<?php
/**
 * AMP Force Hero Image Preloading plugin bootstrap.
 *
 * @package   Google\AmpHeroImagePreloading
 * @author    Weston Ruter, Google
 * @license   GPL-2.0-or-later
 * @copyright 2021 Google Inc.
 *
 * @wordpress-plugin
 * Plugin Name: AMP Force Hero Image Preloading
 * Plugin URI: https://gist.github.com/westonruter/50abd71d9becb5a34f01136052effef5
 * Description: Forcing hero images to be preloaded, even when they are responsive and lack media attributes. This is a workaround until <a href="https://github.com/ampproject/amp-toolbox/issues/1230">amp-toolbox#1230</a> is implemented.
 * Version: 0.1
 * Author: Weston Ruter, Google
 * Author URI: https://weston.ruter.net/
 * License: GNU General Public License v2 (or later)
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Gist Plugin URI: https://gist.github.com/westonruter/50abd71d9becb5a34f01136052effef5
 */

namespace Google\AmpForceHeroImagePreloading;

use AmpProject\Optimizer;
use AmpProject\Optimizer\Transformer\ReorderHead;

/**
 * Filter configuration array to register the transformer.
 *
 * @param array $configuration Associative array of configuration data.
 * @return array Configuration.
 */
function filter_amp_optimizer_config( array $configuration ) {
	require_once __DIR__ . '/ForcePreloadHeroImage.php';

	$transformers = $configuration[ Optimizer\Configuration::KEY_TRANSFORMERS ];

	// Add ForcePreloadHeroImage right before the ReorderHead transformer.
	$reorder_head_position = array_search( ReorderHead::class, $transformers );
	if ( false !== $reorder_head_position ) {
		array_splice( $transformers, $reorder_head_position, 0, [ ForcePreloadHeroImage::class ] );
	} else {
		$transformers[] = ForcePreloadHeroImage::class;
	}

	$configuration[ Optimizer\Configuration::KEY_TRANSFORMERS ] = array_values( $transformers );
	return $configuration;
}

if ( empty( $_GET['amp_disable_force_preload_hero_image'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	add_filter( 'amp_optimizer_config', __NAMESPACE__ . '\filter_amp_optimizer_config' );
}
