<?php
/**
 * Unit tests for CartShare_Cart::restore().
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * WC cart stub used by the restore tests.
 *
 * Supports configurable per-product and per-coupon return values so individual
 * tests can simulate deleted products or expired coupons.
 */
class Stub_WC_Cart_Restore {

	/** @var array product_id => return value for add_to_cart() */
	public $add_results = array();

	/** @var array coupon code => bool return value for apply_coupon() */
	public $coupon_results = array();

	/** @var bool Whether empty_cart() has been called. */
	public $emptied = false;

	/** @var bool Whether calculate_totals() has been called. */
	public $totals_calculated = false;

	/**
	 * Report that the cart is initially empty.
	 *
	 * @return bool
	 */
	public function is_empty() {
		return true;
	}

	/**
	 * Mark the cart as emptied.
	 *
	 * @return void
	 */
	public function empty_cart() {
		$this->emptied = true;
	}

	/**
	 * Mark totals as calculated.
	 *
	 * @return void
	 */
	public function calculate_totals() {
		$this->totals_calculated = true;
	}

	/**
	 * Simulate adding a product to the cart.
	 *
	 * Returns the configured result for the product ID, or a generated key
	 * string to indicate success when no override is configured.
	 *
	 * @param int   $product_id   Product ID.
	 * @param int   $qty          Quantity.
	 * @param int   $variation_id Variation ID.
	 * @param array $variation    Variation attributes.
	 * @param array $item_data    Extra cart item data.
	 * @return string|false
	 */
	public function add_to_cart( $product_id, $qty, $variation_id = 0, $variation = array(), $item_data = array() ) {
		if ( array_key_exists( $product_id, $this->add_results ) ) {
			return $this->add_results[ $product_id ];
		}
		return $product_id . '_key'; // success
	}

	/**
	 * Simulate applying a coupon.
	 *
	 * Returns the configured result for the coupon code, or true when no
	 * override is configured.
	 *
	 * @param string $code Coupon code.
	 * @return bool
	 */
	public function apply_coupon( $code ) {
		if ( array_key_exists( $code, $this->coupon_results ) ) {
			return $this->coupon_results[ $code ];
		}
		return true;
	}
}

/**
 * Class Test_CartShare_Cart_Restore
 *
 * Tests the restore() method of CartShare_Cart including deleted-product and
 * expired-coupon warning handling.
 */
class Test_CartShare_Cart_Restore extends TestCase {

	/** @var Stub_WC_Cart_Restore */
	protected $cart_stub;

	/**
	 * Reset the global WC instance before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['cartshare_wc_instance'] = new CartShare_Stub_WC();
		$this->cart_stub                  = new Stub_WC_Cart_Restore();
		WC()->cart                        = $this->cart_stub;
	}

	/**
	 * Helper: create a CartShare_Cart with a no-op DB mock.
	 *
	 * @return CartShare_Cart
	 */
	private function make_cart(): CartShare_Cart {
		$db = $this->createMock( CartShare_DB::class );
		return new CartShare_Cart( $db );
	}

	/**
	 * A deleted product (add_to_cart returns false) must produce one warning
	 * that mentions the product ID.
	 */
	public function test_restore_returns_warning_for_deleted_product() {
		$this->cart_stub->add_results = array( 99 => false );

		$cart_data = array(
			'items'   => array(
				array( 'product_id' => 99, 'variation_id' => 0, 'quantity' => 1, 'variation' => array(), 'cart_item_data' => array() ),
				array( 'product_id' => 42, 'variation_id' => 0, 'quantity' => 1, 'variation' => array(), 'cart_item_data' => array() ),
			),
			'coupons' => array(),
		);

		$warnings = $this->make_cart()->restore( $cart_data );

		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( '99', $warnings[0] );
	}

	/**
	 * When one product is deleted the remaining items must still be restored
	 * (only one warning for the deleted product).
	 */
	public function test_restore_succeeds_for_remaining_items_when_one_deleted() {
		$this->cart_stub->add_results = array( 99 => false );

		$cart_data = array(
			'items'   => array(
				array( 'product_id' => 99, 'variation_id' => 0, 'quantity' => 1, 'variation' => array(), 'cart_item_data' => array() ),
				array( 'product_id' => 42, 'variation_id' => 0, 'quantity' => 1, 'variation' => array(), 'cart_item_data' => array() ),
			),
			'coupons' => array(),
		);

		$warnings = $this->make_cart()->restore( $cart_data );

		$this->assertCount( 1, $warnings );
	}

	/**
	 * A valid coupon must be applied without producing any warning.
	 */
	public function test_restore_applies_valid_coupon_without_warning() {
		$this->cart_stub->coupon_results = array( 'SAVE10' => true );

		$cart_data = array(
			'items'   => array(),
			'coupons' => array( 'SAVE10' ),
		);

		$warnings = $this->make_cart()->restore( $cart_data );

		$this->assertCount( 0, $warnings );
	}

	/**
	 * An expired coupon (apply_coupon returns false) must produce one warning
	 * that mentions the coupon code.
	 */
	public function test_restore_returns_warning_for_invalid_coupon() {
		$this->cart_stub->coupon_results = array( 'EXPIRED' => false );

		$cart_data = array(
			'items'   => array(),
			'coupons' => array( 'EXPIRED' ),
		);

		$warnings = $this->make_cart()->restore( $cart_data );

		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'EXPIRED', $warnings[0] );
	}
}
