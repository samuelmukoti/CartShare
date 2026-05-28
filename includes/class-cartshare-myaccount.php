<?php
/**
 * My Account "Saved Carts" tab for CartShare.
 *
 * Registers the saved-carts rewrite endpoint, adds the tab to the My Account
 * navigation menu, serves the tab template, and handles form POST actions
 * (restore / delete) with nonce verification and ownership checks.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_MyAccount
 *
 * Wires the "Saved Carts" tab into WooCommerce My Account for registered users.
 * All state-changing actions require a valid nonce and ownership check
 * (user_id === get_current_user_id()) before any data is modified.
 */
class CartShare_MyAccount {

	/**
	 * CartShare_DB instance.
	 *
	 * @var CartShare_DB
	 */
	private $db;

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
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_saved-carts_endpoint', array( $this, 'render_tab' ) );
		add_action( 'template_redirect', array( $this, 'handle_actions' ) );
	}

	/**
	 * Register the saved-carts rewrite endpoint with WordPress.
	 *
	 * Must fire on init so the endpoint is registered before any potential
	 * rewrite-rule flush.  EP_ROOT | EP_PAGES ensures the endpoint works on
	 * both root and page-based My Account installations.
	 *
	 * @return void
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( 'saved-carts', EP_ROOT | EP_PAGES );
	}

	/**
	 * Insert the "Saved Carts" tab into the My Account navigation menu.
	 *
	 * Inserts the tab before the logout item so it appears near the bottom
	 * of the list but before the sign-out link.
	 *
	 * @param array $items Existing menu items (slug => label).
	 * @return array
	 */
	public function add_menu_item( array $items ): array {
		// Insert before 'customer-logout' for a logical placement.
		$new_items = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new_items['saved-carts'] = __( 'Saved Carts', 'cartshare' );
			}
			$new_items[ $key ] = $label;
		}

		// Fallback: if logout item was not found, append to the end.
		if ( ! isset( $new_items['saved-carts'] ) ) {
			$new_items['saved-carts'] = __( 'Saved Carts', 'cartshare' );
		}

		return $new_items;
	}

	/**
	 * Render the Saved Carts tab content by including the template file.
	 *
	 * Passes the current user's saved carts to the template via a local variable.
	 *
	 * @return void
	 */
	public function render_tab(): void {
		$user_id    = get_current_user_id();
		$saved_carts = $this->db->list_for_user( $user_id );

		$template = CARTSHARE_PATH . 'templates/myaccount-saved-carts.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Handle POST actions (restore / delete) submitted from the Saved Carts tab.
	 *
	 * Both actions require:
	 *   - A valid nonce (checked via check_admin_referer).
	 *   - The requesting user to own the cart row (user_id === get_current_user_id()).
	 *
	 * Fires on template_redirect so the response can issue a redirect before
	 * the theme template renders.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! is_account_page() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by check_admin_referer() below for non-empty actions.
		$action = isset( $_POST['cartshare_action'] ) ? sanitize_key( wp_unslash( $_POST['cartshare_action'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! in_array( $action, array( 'restore', 'delete' ), true ) ) {
			return;
		}

		// Verify the nonce for the specific action.
		check_admin_referer( 'cartshare_myaccount_' . $action );

		if ( ! is_user_logged_in() ) {
			wc_add_notice( __( 'You must be logged in to manage saved carts.', 'cartshare' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'saved-carts' ) );
			exit;
		}

		$token = isset( $_POST['cartshare_token'] ) ? sanitize_text_field( wp_unslash( $_POST['cartshare_token'] ) ) : '';

		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			wc_add_notice( __( 'Invalid cart token.', 'cartshare' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'saved-carts' ) );
			exit;
		}

		$row = $this->db->find_by_token( $token );

		if ( null === $row ) {
			wc_add_notice( __( 'Cart not found.', 'cartshare' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'saved-carts' ) );
			exit;
		}

		// Ownership check: the logged-in user must own this cart row.
		if ( (int) $row['user_id'] !== get_current_user_id() ) {
			wc_add_notice( __( 'You do not have permission to manage this cart.', 'cartshare' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'saved-carts' ) );
			exit;
		}

		if ( 'delete' === $action ) {
			$this->handle_delete( $token );
		} elseif ( 'restore' === $action ) {
			$this->handle_restore( $row );
		}
	}

	/**
	 * Delete a saved cart row by token.
	 *
	 * Ownership has already been verified by handle_actions() before this method
	 * is called, but delete_by_token() performs a second ownership check at the
	 * DB layer as a belt-and-braces measure.
	 *
	 * @param string $token The 32-character cart token.
	 * @return void
	 */
	private function handle_delete( string $token ): void {
		$deleted = $this->db->delete_by_token( $token, get_current_user_id(), null );

		if ( $deleted ) {
			wc_add_notice( __( 'Cart deleted successfully.', 'cartshare' ), 'success' );
		} else {
			wc_add_notice( __( 'Could not delete the cart. Please try again.', 'cartshare' ), 'error' );
		}

		wp_safe_redirect( wc_get_account_endpoint_url( 'saved-carts' ) );
		exit;
	}

	/**
	 * Restore a saved cart into the active WooCommerce session.
	 *
	 * Loads the WC cart if necessary, empties the current cart, then restores
	 * items and coupons from the saved cart data.  Any warnings (deleted products,
	 * invalid coupons, etc.) are surfaced via WooCommerce notices.
	 *
	 * @param array $row The cart row from the database (associative array).
	 * @return void
	 */
	private function handle_restore( array $row ): void {
		// Ensure the WooCommerce cart is available in this context.
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		$cart_data = json_decode( $row['cart_data'], true );

		if ( ! is_array( $cart_data ) ) {
			wc_add_notice( __( 'Cart data is invalid and could not be restored.', 'cartshare' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'saved-carts' ) );
			exit;
		}

		if ( class_exists( 'CartShare_Cart' ) ) {
			$cart_handler = new CartShare_Cart( $this->db );
			$warnings     = $cart_handler->restore( $cart_data );
		} else {
			// Fallback inline restore if CartShare_Cart is unavailable.
			$warnings = $this->restore_cart_inline( $cart_data );
		}

		if ( ! empty( $warnings ) ) {
			foreach ( $warnings as $warning ) {
				wc_add_notice( esc_html( $warning ), 'notice' );
			}
		}

		wc_add_notice( __( 'Cart restored successfully.', 'cartshare' ), 'success' );

		$redirect = get_option( 'cartshare_restore_redirect', 'cart' );
		$url      = 'checkout' === $redirect ? wc_get_checkout_url() : wc_get_cart_url();

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Inline cart restore fallback used when CartShare_Cart is not loaded.
	 *
	 * Mirrors the restore logic in CartShare_Cart::restore() so that the
	 * My Account tab works even if the cart class file has not yet been created.
	 *
	 * @param array $cart_data The decoded cart data array (items + coupons).
	 * @return string[] Array of warning strings for items/coupons that failed.
	 */
	private function restore_cart_inline( array $cart_data ): array {
		$warnings = array();

		WC()->cart->empty_cart();

		$items = isset( $cart_data['items'] ) && is_array( $cart_data['items'] ) ? $cart_data['items'] : array();
		foreach ( $items as $item ) {
			$added = WC()->cart->add_to_cart(
				(int) ( $item['product_id'] ?? 0 ),
				(int) ( $item['quantity'] ?? 1 ),
				(int) ( $item['variation_id'] ?? 0 ),
				isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array(),
				isset( $item['cart_item_data'] ) && is_array( $item['cart_item_data'] ) ? $item['cart_item_data'] : array()
			);

			if ( false === $added ) {
				$warnings[] = sprintf(
					/* translators: %d: product ID */
					__( 'Could not restore product #%d (deleted or out of stock).', 'cartshare' ),
					(int) ( $item['product_id'] ?? 0 )
				);
			}
		}

		$coupons = isset( $cart_data['coupons'] ) && is_array( $cart_data['coupons'] ) ? $cart_data['coupons'] : array();
		foreach ( $coupons as $code ) {
			if ( ! WC()->cart->apply_coupon( $code ) ) {
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
}
