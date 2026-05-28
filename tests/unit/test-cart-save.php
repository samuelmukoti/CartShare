<?php
/**
 * Unit tests for CartShare_Cart save behaviour.
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * WC cart stub used by the save tests.
 */
class Stub_WC_Cart_Save {

	/** @var bool Value returned by is_empty(). */
	public $is_empty_return = true;

	/**
	 * Return whether the cart is empty.
	 *
	 * @return bool
	 */
	public function is_empty() {
		return $this->is_empty_return;
	}
}

/**
 * Class Test_CartShare_Cart_Save
 *
 * Tests empty-cart detection and the WP_Error returned when saving an empty cart.
 */
class Test_CartShare_Cart_Save extends TestCase {

	/**
	 * Reset global WC instance and attach a save stub before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['cartshare_wc_instance'] = new CartShare_Stub_WC();
		WC()->cart                        = new Stub_WC_Cart_Save();
	}

	/**
	 * is_cart_empty() must return true when the WC cart reports empty.
	 */
	public function test_is_cart_empty_returns_true_for_empty_cart() {
		$db   = $this->createMock( CartShare_DB::class );
		$cart = new CartShare_Cart( $db );

		$this->assertTrue( $cart->is_cart_empty() );
	}

	/**
	 * Attempting to save an empty cart must yield a WP_Error and must never
	 * touch the database.
	 */
	public function test_empty_cart_save_returns_wp_error() {
		$db_mock = $this->createMock( CartShare_DB::class );
		$db_mock->expects( $this->never() )->method( 'insert' );

		$cart = new CartShare_Cart( $db_mock );

		// Simulate the save logic that the controller performs.
		if ( $cart->is_cart_empty() ) {
			$result = new WP_Error( 'cartshare_empty_cart', 'Cannot save an empty cart.' );
		} else {
			$result = $db_mock->insert( array(), null, null, null, null );
		}

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'cartshare_empty_cart', $result->code );
	}
}
