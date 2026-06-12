<?php
/**
 * Unit tests for CartShare_Cart_Builder.
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_CartShare_Cart_Builder
 *
 * Exercises the capability check and asset-enqueue guard in
 * CartShare_Cart_Builder without a live WordPress install.
 */
class Test_CartShare_Cart_Builder extends TestCase {

	/**
	 * Reset test state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		CartShare_Test_State::reset();
	}

	/**
	 * Restore test state after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		CartShare_Test_State::reset();
		parent::tearDown();
	}

	/**
	 * ajax_search_products() must send a 403 JSON error when the user lacks
	 * the manage_woocommerce capability.
	 *
	 * wp_send_json_error() throws CartShare_Test_Json_Die in unit tests (to
	 * simulate the wp_die() call WordPress makes after sending the response).
	 */
	public function test_cart_builder_capability_check() {
		CartShare_Test_State::$user_can = false;

		$builder = new CartShare_Cart_Builder();
		try {
			$builder->ajax_search_products();
		} catch ( CartShare_Test_Json_Die $e ) {
			// Expected: wp_send_json_error() terminates execution via this stub.
		}

		$this->assertCount( 1, CartShare_Test_State::$json_error_calls );
		$this->assertSame( 403, CartShare_Test_State::$json_error_calls[0]['status'] );
	}

	/**
	 * enqueue_assets() must skip enqueueing when the hook suffix does not
	 * belong to the CartShare cart builder page.
	 */
	public function test_enqueue_assets_skips_wrong_page() {
		$builder = new CartShare_Cart_Builder();
		$builder->enqueue_assets( 'toplevel_page_woocommerce' );

		$this->assertNotContains( 'cartshare-cart-builder', CartShare_Test_State::$enqueued_scripts );
		$this->assertNotContains( 'cartshare-cart-builder', CartShare_Test_State::$enqueued_styles );
	}
}
