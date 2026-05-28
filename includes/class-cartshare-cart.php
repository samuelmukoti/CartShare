<?php
/**
 * Cart serialization and restoration for CartShare.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Cart
 *
 * Handles serializing the current WooCommerce cart to a storable array and
 * restoring a previously saved cart from that array. Gracefully handles
 * deleted products, out-of-stock items, removed variations, and expired coupons
 * by collecting per-item warnings instead of aborting the whole restore.
 */
class CartShare_Cart {

	/**
	 * CartShare_DB instance.
	 *
	 * @var CartShare_DB
	 */
	private $db;

	/**
	 * Keys computed/derived at runtime that must be stripped before persisting.
	 * Totals and hashes are recomputed fresh on restore.
	 *
	 * @var string[]
	 */
	private static $computed_keys = array(
		'key',
		'product_id',
		'variation_id',
		'quantity',
		'variation',
		'data',
		'data_hash',
		'line_tax_data',
		'line_subtotal',
		'line_subtotal_tax',
		'line_total',
		'line_tax',
	);

	/**
	 * Constructor.
	 *
	 * @param CartShare_DB $db CartShare database instance.
	 */
	public function __construct( CartShare_DB $db ) {
		$this->db = $db;
	}

	/**
	 * Register WordPress / WooCommerce hooks.
	 *
	 * CartShare_Cart has no hooks of its own at this phase — it is called
	 * directly by the REST controller. This method exists so the plugin
	 * orchestrator can call init_hooks() on every subsystem uniformly.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		// No hooks required at this phase.
	}

	/**
	 * Serialize the current WooCommerce cart to a plain array.
	 *
	 * Iterates every cart item and captures the fields needed to faithfully
	 * reproduce the cart on a different session or device. Computed/derived
	 * keys (totals, hashes) are stripped because they are recalculated by
	 * WooCommerce on restore — persisting them would cause stale price data.
	 *
	 * The returned array is safe to pass to wp_json_encode().
	 *
	 * @return array {
	 *     @type array[] $items   Each item: product_id, variation_id, quantity, variation, cart_item_data.
	 *     @type string[] $coupons Applied coupon codes.
	 * }
	 */
	public function serialize_current_cart(): array {
		$this->maybe_load_cart();

		$items = array();

		foreach ( WC()->cart->get_cart() as $item ) {
			$items[] = array(
				'product_id'     => (int) $item['product_id'],
				'variation_id'   => (int) ( $item['variation_id'] ?? 0 ),
				'quantity'       => (int) $item['quantity'],
				'variation'      => $item['variation'] ?? array(),
				'cart_item_data' => array_diff_key(
					$item,
					array_flip( self::$computed_keys )
				),
			);
		}

		return array(
			'items'   => $items,
			'coupons' => WC()->cart->get_applied_coupons(),
		);
	}

	/**
	 * Restore a previously serialized cart into the current WooCommerce session.
	 *
	 * The existing cart is emptied first — the frontend must have already shown
	 * a confirmation prompt before calling this method. Each item and coupon is
	 * applied individually; failures produce a warning entry rather than
	 * aborting the whole restore, so the shopper at least gets a partial cart
	 * with clear messaging about what could not be restored.
	 *
	 * Always calls calculate_totals() at the end so prices reflect current
	 * WooCommerce pricing rules.
	 *
	 * @param array $cart_data {
	 *     @type array[] $items   Each item: product_id, variation_id, quantity, variation, cart_item_data.
	 *     @type string[] $coupons Coupon codes to re-apply.
	 * }
	 *
	 * @return string[] Array of human-readable warning strings (empty on full success).
	 */
	public function restore( array $cart_data ): array {
		$this->maybe_load_cart();

		$warnings = array();

		WC()->cart->empty_cart();

		$items   = isset( $cart_data['items'] ) ? (array) $cart_data['items'] : array();
		$coupons = isset( $cart_data['coupons'] ) ? (array) $cart_data['coupons'] : array();

		foreach ( $items as $item ) {
			$product_id     = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$quantity       = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			$variation_id   = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			$variation      = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();
			$cart_item_data = isset( $item['cart_item_data'] ) && is_array( $item['cart_item_data'] ) ? $item['cart_item_data'] : array();

			$added = WC()->cart->add_to_cart(
				$product_id,
				$quantity,
				$variation_id,
				$variation,
				$cart_item_data
			);

			if ( false === $added ) {
				$warnings[] = sprintf(
					/* translators: %d: WooCommerce product ID */
					__( 'Could not restore product #%d (deleted or out of stock).', 'cartshare' ),
					$product_id
				);
			}
		}

		foreach ( $coupons as $code ) {
			$code    = sanitize_text_field( (string) $code );
			$applied = WC()->cart->apply_coupon( $code );

			if ( ! $applied ) {
				$warnings[] = sprintf(
					/* translators: %s: coupon code */
					__( 'Coupon "%s" is no longer valid.', 'cartshare' ),
					$code
				);
			}
		}

		WC()->cart->calculate_totals();

		return $warnings;
	}

	/**
	 * Check whether the current WooCommerce cart is empty.
	 *
	 * @return bool True if the cart contains no items.
	 */
	public function is_cart_empty(): bool {
		$this->maybe_load_cart();
		return WC()->cart->is_empty();
	}

	/**
	 * Defensively ensure WC()->cart is initialised.
	 *
	 * In REST / admin contexts the cart session may not have been loaded yet.
	 * wc_load_cart() is a no-op when the cart is already available.
	 *
	 * @return void
	 */
	private function maybe_load_cart(): void {
		if ( null === WC()->cart ) {
			wc_load_cart();
		}
	}
}
