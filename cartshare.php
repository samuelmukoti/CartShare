<?php
/**
 * Plugin Name: WP CartShare Pro
 * Plugin URI:  https://github.com/cartshare/cartshare
 * Description: Persist and share WooCommerce carts via secure tokenized URLs.
 * Version:     1.0.1
 * Author:      Samuel Mukoti
 * Author URI:  mailto:sam@melivo.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * WC requires at least: 8.2
 * WC tested up to: 11.1
 * Text Domain: cartshare
 * Domain Path: /languages
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CARTSHARE_VERSION', '1.0.1' );
define( 'CARTSHARE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CARTSHARE_URL', plugin_dir_url( __FILE__ ) );

// Load activator/deactivator at file scope so the activation/deactivation
// hook callbacks can resolve the class names below.
require_once CARTSHARE_PATH . 'includes/class-cartshare-activator.php';
require_once CARTSHARE_PATH . 'includes/class-cartshare-deactivator.php';

register_activation_hook( __FILE__, array( 'CartShare_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CartShare_Deactivator', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		// Belt-and-braces: `Requires Plugins` header is only enforced on WP 6.5+,
		// so guard with class_exists for sites on WP 6.2-6.4.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		require_once CARTSHARE_PATH . 'includes/class-cartshare-plugin.php';
		CartShare_Plugin::instance()->boot();
	}
);

// Text domain must load on `init` per WP 6.7+ (loading earlier triggers
// `_doing_it_wrong`). Translation files live in /languages/.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'cartshare', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
