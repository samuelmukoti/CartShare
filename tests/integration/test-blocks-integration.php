<?php
/**
 * Integration tests for the WooCommerce Blocks integration wiring.
 *
 * block-cart.js is the only script that registers the Save & Share button in
 * the Cart and Checkout blocks, so the integration must be registered with
 * both block registries.
 *
 * @package CartShare\Tests\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	return;
}

/**
 * Class Test_CartShare_Blocks_Integration
 */
class Test_CartShare_Blocks_Integration extends WP_UnitTestCase {

	/**
	 * The integration is hooked into both the Cart and Checkout block registries.
	 *
	 * @return void
	 */
	public function test_integration_registered_for_cart_and_checkout_blocks() {
		$this->assertNotFalse( has_action( 'woocommerce_blocks_cart_block_registration' ) );
		$this->assertNotFalse( has_action( 'woocommerce_blocks_checkout_block_registration' ) );
	}

	/**
	 * Registering the integration exposes the block-cart script handle, which
	 * depends on wc-blocks-checkout so WooCommerce loads it in block context.
	 *
	 * @return void
	 */
	public function test_block_cart_script_declares_wc_blocks_checkout_dependency() {
		require_once CARTSHARE_PATH . 'includes/class-cartshare-blocks-integration.php';

		$integration = new CartShare_Blocks_Integration();
		$integration->initialize();

		$this->assertSame( array( 'cartshare-block-cart' ), $integration->get_script_handles() );
		$this->assertContains( 'wc-blocks-checkout', wp_scripts()->registered['cartshare-block-cart']->deps );
	}
}
