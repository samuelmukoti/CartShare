<?php
/**
 * Unit tests for the restore landing page helpers on CartShare_Frontend.
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_CartShare_Restore_Landing
 *
 * Covers get_brand_logo() fallback order and build_restore_preview()
 * pricing, availability and coupon filtering.
 */
class Test_CartShare_Restore_Landing extends TestCase {

	/**
	 * Frontend instance under test.
	 *
	 * @var CartShare_Frontend
	 */
	private $frontend;

	/**
	 * Reset captured side-effects before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		CartShare_Test_State::reset();
		$this->frontend = new CartShare_Frontend();
	}

	/**
	 * The theme's custom logo wins over every other source.
	 *
	 * @return void
	 */
	public function test_brand_logo_prefers_theme_custom_logo() {
		CartShare_Test_State::$theme_mods['custom_logo'] = 7;
		CartShare_Test_State::$attachment_urls[7]        = 'http://example.org/logo.png';
		CartShare_Test_State::$site_icon_url             = 'http://example.org/icon.png';
		update_option( 'cartshare_email_logo_url', 'http://example.org/email-logo.png' );

		$logo = $this->frontend->get_brand_logo();

		$this->assertSame( 'http://example.org/logo.png', $logo['url'] );
		$this->assertSame( 'Test Store', $logo['alt'] );
	}

	/**
	 * Without a theme logo, the CartShare email logo is used, then the site icon.
	 *
	 * @return void
	 */
	public function test_brand_logo_falls_back_to_email_logo_then_site_icon() {
		CartShare_Test_State::$site_icon_url = 'http://example.org/icon.png';
		update_option( 'cartshare_email_logo_url', 'http://example.org/email-logo.png' );
		$this->assertSame( 'http://example.org/email-logo.png', $this->frontend->get_brand_logo()['url'] );

		update_option( 'cartshare_email_logo_url', '' );
		$this->assertSame( 'http://example.org/icon.png', $this->frontend->get_brand_logo()['url'] );
	}

	/**
	 * With no logo anywhere the URL is empty so the template shows a wordmark.
	 *
	 * @return void
	 */
	public function test_brand_logo_empty_when_nothing_configured() {
		$this->assertSame( '', $this->frontend->get_brand_logo()['url'] );
	}

	/**
	 * Line totals, sale prices, item count and subtotal use live prices, and
	 * variations are looked up by variation ID.
	 *
	 * @return void
	 */
	public function test_preview_totals_use_live_prices() {
		CartShare_Test_State::$products[1]  = new CartShare_Stub_Product(
			array(
				'name'          => 'Serum',
				'price'         => 29.0,
				'regular_price' => 38.0,
				'on_sale'       => true,
			)
		);
		CartShare_Test_State::$products[22] = new CartShare_Stub_Product(
			array(
				'name'  => 'Tee - Blue',
				'price' => 15.5,
			)
		);

		$preview = $this->frontend->build_restore_preview(
			array(
				'items' => array(
					array(
						'product_id' => 1,
						'quantity'   => 2,
					),
					array(
						'product_id'   => 2,
						'variation_id' => 22,
						'quantity'     => 1,
					),
				),
			)
		);

		$this->assertCount( 2, $preview['items'] );
		$this->assertSame( 58.0, $preview['items'][0]['line_total'] );
		$this->assertSame( 38.0, $preview['items'][0]['regular_price'] );
		$this->assertSame( 'Tee - Blue', $preview['items'][1]['name'] );
		$this->assertSame( 3, $preview['item_count'] );
		$this->assertSame( 73.5, $preview['subtotal'] );
	}

	/**
	 * Deleted and out-of-stock products are listed but excluded from totals,
	 * matching what restore() will actually add.
	 *
	 * @return void
	 */
	public function test_preview_excludes_unavailable_and_out_of_stock_from_totals() {
		CartShare_Test_State::$products[1] = new CartShare_Stub_Product( array( 'price' => 10.0 ) );
		CartShare_Test_State::$products[2] = new CartShare_Stub_Product(
			array(
				'price'    => 50.0,
				'in_stock' => false,
			)
		);

		$preview = $this->frontend->build_restore_preview(
			array(
				'items' => array(
					array(
						'product_id' => 1,
						'quantity'   => 1,
					),
					array(
						'product_id' => 2,
						'quantity'   => 1,
					),
					array(
						'product_id' => 999,
						'quantity'   => 3,
					),
				),
			)
		);

		$this->assertSame( array( 'available', 'out_of_stock', 'unavailable' ), array_column( $preview['items'], 'status' ) );
		$this->assertSame( '', $preview['items'][2]['url'] );
		$this->assertSame( 1, $preview['item_count'] );
		$this->assertSame( 10.0, $preview['subtotal'] );
	}

	/**
	 * Only coupons that exist and have not expired are advertised.
	 *
	 * @return void
	 */
	public function test_preview_only_lists_valid_coupons() {
		CartShare_Test_State::$coupons = array(
			'spring15' => array(
				'id'      => 5,
				'expires' => time() + DAY_IN_SECONDS,
			),
			'old10'    => array(
				'id'      => 6,
				'expires' => time() - DAY_IN_SECONDS,
			),
			'forever'  => array( 'id' => 7 ),
		);

		$preview = $this->frontend->build_restore_preview(
			array(
				'items'   => array(),
				'coupons' => array( 'SPRING15', 'old10', 'missing', 'forever', '' ),
			)
		);

		$this->assertSame( array( 'SPRING15', 'forever' ), $preview['coupons'] );
	}
}
