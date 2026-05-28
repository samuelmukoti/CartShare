<?php
/**
 * Unit tests for CartShare_Cart::serialize_current_cart().
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * WC cart stub used exclusively by the serialize tests.
 */
class Stub_WC_Cart_Serialize {

	/** @var array Cart item to return from get_cart(). */
	public $cart_item = array();

	/**
	 * Return the current cart contents.
	 *
	 * @return array
	 */
	public function get_cart() {
		return array( $this->cart_item );
	}

	/**
	 * Return applied coupon codes.
	 *
	 * @return string[]
	 */
	public function get_applied_coupons() {
		return array( 'SAVE10' );
	}

	/**
	 * Return whether the cart is empty.
	 *
	 * @return bool
	 */
	public function is_empty() {
		return false;
	}
}

/**
 * Class Test_CartShare_Cart_Serialize
 *
 * Tests the serialize_current_cart() method of CartShare_Cart.
 */
class Test_CartShare_Cart_Serialize extends TestCase {

	/** @var array Sample cart item used across tests. */
	protected $cart_item = array();

	/**
	 * Set up a fresh WC stub and cart item before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Reset the global WC instance so each test starts clean.
		$GLOBALS['cartshare_wc_instance'] = new CartShare_Stub_WC();

		$this->cart_item = array(
			'product_id'        => 42,
			'variation_id'      => 7,
			'quantity'          => 3,
			'variation'         => array( 'attribute_color' => 'red' ),
			'data'              => null,
			'data_hash'         => 'abc123hash',
			'line_subtotal'     => 30.0,
			'line_subtotal_tax' => 0.0,
			'line_total'        => 30.0,
			'line_tax'          => 0.0,
			'line_tax_data'     => array(),
			'key'               => 'somekey123',
			'custom_field'      => 'custom_value',
		);

		$stub             = new Stub_WC_Cart_Serialize();
		$stub->cart_item  = $this->cart_item;
		WC()->cart        = $stub;
	}

	/**
	 * Helper: run serialize_current_cart() with a null-DB CartShare_Cart.
	 *
	 * @return array
	 */
	private function do_serialize(): array {
		$db   = $this->createMock( CartShare_DB::class );
		$cart = new CartShare_Cart( $db );
		return $cart->serialize_current_cart();
	}

	/**
	 * Serialized item must include product_id.
	 */
	public function test_serialized_item_includes_product_id() {
		$result = $this->do_serialize();
		$this->assertSame( 42, $result['items'][0]['product_id'] );
	}

	/**
	 * Serialized item must include variation_id.
	 */
	public function test_serialized_item_includes_variation_id() {
		$result = $this->do_serialize();
		$this->assertSame( 7, $result['items'][0]['variation_id'] );
	}

	/**
	 * Serialized item must include quantity.
	 */
	public function test_serialized_item_includes_quantity() {
		$result = $this->do_serialize();
		$this->assertSame( 3, $result['items'][0]['quantity'] );
	}

	/**
	 * Serialized item must include variation attributes.
	 */
	public function test_serialized_item_includes_variation() {
		$result = $this->do_serialize();
		$this->assertSame( array( 'attribute_color' => 'red' ), $result['items'][0]['variation'] );
	}

	/**
	 * Serialized item must include the cart_item_data key.
	 */
	public function test_serialized_item_includes_cart_item_data_key() {
		$result = $this->do_serialize();
		$this->assertArrayHasKey( 'cart_item_data', $result['items'][0] );
	}

	/**
	 * Serialized item must NOT expose line_total.
	 */
	public function test_serialized_item_excludes_line_total() {
		$result = $this->do_serialize();
		$this->assertArrayNotHasKey( 'line_total', $result['items'][0] );
	}

	/**
	 * Serialized item must NOT expose data_hash.
	 */
	public function test_serialized_item_excludes_data_hash() {
		$result = $this->do_serialize();
		$this->assertArrayNotHasKey( 'data_hash', $result['items'][0] );
	}

	/**
	 * Serialized item must NOT expose the cart key.
	 */
	public function test_serialized_item_excludes_key() {
		$result = $this->do_serialize();
		$this->assertArrayNotHasKey( 'key', $result['items'][0] );
	}

	/**
	 * Coupons array must contain all applied coupons.
	 */
	public function test_serialized_coupons_included() {
		$result = $this->do_serialize();
		$this->assertSame( array( 'SAVE10' ), $result['coupons'] );
	}
}
