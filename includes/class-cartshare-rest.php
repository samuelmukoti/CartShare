<?php
/**
 * REST API controller for CartShare.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_REST
 *
 * Registers and handles all REST API endpoints under the cartshare/v1 namespace:
 *
 * - POST   /save                            — serialize and persist current cart
 * - GET    /restore/(?P<token>[A-Za-z0-9]{32}) — restore a cart by token (public share link)
 * - GET    /list                            — list saved carts for logged-in user
 * - DELETE /delete/(?P<token>[A-Za-z0-9]{32}) — delete own cart by token
 */
class CartShare_REST {

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
	 * Register WordPress hooks.
	 *
	 * Hooks register_routes() onto rest_api_init so all routes are registered
	 * at the correct point in the WP bootstrap.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST routes for the cartshare/v1 namespace.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'cartshare/v1',
			'/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save' ),
				'permission_callback' => array( $this, 'check_save_nonce' ),
			)
		);

		register_rest_route(
			'cartshare/v1',
			'/restore/(?P<token>[A-Za-z0-9]{32})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'restore' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'cartshare/v1',
			'/list',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_carts' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		register_rest_route(
			'cartshare/v1',
			'/delete/(?P<token>[A-Za-z0-9]{32})',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'cartshare/v1',
			'/share/email',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'share_email' ),
				'permission_callback' => array( $this, 'check_save_nonce' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Verify that a valid wp_rest nonce is present.
	 *
	 * Reads the nonce from the X-WP-Nonce request header (set automatically by
	 * wp.apiFetch / jQuery.ajax when the nonce is passed via wp_localize_script)
	 * or from a _wpnonce query/body parameter as a fallback.
	 *
	 * Both guests and logged-in users must supply the nonce; this prevents CSRF.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return true|WP_Error True if nonce is valid, WP_Error with 403 status otherwise.
	 */
	public function check_save_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( ! $nonce || false === wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid or missing nonce.', 'cartshare' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Require the caller to be a logged-in WordPress user.
	 *
	 * Used for the /list endpoint which is My-Account scoped.
	 *
	 * @return true|WP_Error True if logged in, WP_Error with 401 status otherwise.
	 */
	public function check_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to view saved carts.', 'cartshare' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Route handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle POST /save — serialize the current cart and persist it.
	 *
	 * When an explicit `items` array is provided (admin cart-builder path), the
	 * cart session is bypassed entirely and `sanitize_items()` is used to build
	 * the payload.  Otherwise the existing WC()->cart serialization path runs.
	 *
	 * On success returns the opaque token and the shareable restore URL.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		// NEW: If an explicit items payload is provided, use it directly.
		$items_param = $request->get_param( 'items' );
		if ( is_array( $items_param ) && ! empty( $items_param ) ) {
			$cart_data = array(
				'items'   => $this->sanitize_items( $items_param ),
				'coupons' => array(),
			);
		} else {
			// Existing path: serialize from WC()->cart.
			// In REST / admin contexts the cart session may not be initialised yet.
			if ( null === WC()->cart ) {
				wc_load_cart();
			}

			$cart_helper = new CartShare_Cart( $this->db );

			if ( $cart_helper->is_cart_empty() ) {
				return new WP_Error(
					'cartshare_empty_cart',
					__( 'Cannot save an empty cart.', 'cartshare' ),
					array( 'status' => 400 )
				);
			}

			$cart_data = $cart_helper->serialize_current_cart();
		}

		$source = sanitize_key( (string) ( $request->get_param( 'source' ) ?? '' ) );
		$source = '' !== $source ? $source : null;

		// Resolve user_id: admin may supply customer_id to associate cart with a specific customer.
		$customer_id_param = absint( $request->get_param( 'customer_id' ) ?? 0 );
		if ( $customer_id_param > 0 && current_user_can( 'manage_woocommerce' ) ) {
			$user_id  = $customer_id_param;
			$guest_id = null;
		} else {
			$user_id  = get_current_user_id() ?: null;
			$guest_id = ( ! $user_id && WC()->session ) ? WC()->session->get_customer_id() : null;
		}

		$ttl_days    = absint( get_option( 'cartshare_expiry_days', 30 ) );
		$ttl_seconds = $ttl_days > 0 ? $ttl_days * DAY_IN_SECONDS : null;

		$name = sanitize_text_field( (string) ( $request->get_param( 'name' ) ?? '' ) );
		$name = '' !== $name ? $name : null;

		$token = $this->db->insert( $cart_data, $user_id, $guest_id, $ttl_seconds, $name, $source );

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		return rest_ensure_response(
			array(
				'token'     => $token,
				'share_url' => add_query_arg( 'cartshare_restore', $token, home_url( '/' ) ),
			)
		);
	}

	/**
	 * Sanitize a raw items array from an admin REST payload.
	 *
	 * Filters out items with no product_id, clamps quantity to a minimum of 1,
	 * uses absint() for all IDs, and sanitizes variation attribute values.
	 *
	 * @param array $items Raw items array from the REST request.
	 * @return array Sanitized items array ready for storage.
	 */
	private function sanitize_items( array $items ): array {
		$clean = array();
		foreach ( $items as $item ) {
			if ( empty( $item['product_id'] ) ) {
				continue;
			}
			$clean[] = array(
				'product_id'     => absint( $item['product_id'] ),
				'variation_id'   => absint( $item['variation_id'] ?? 0 ),
				'quantity'       => max( 1, absint( $item['quantity'] ?? 1 ) ),
				'variation'      => isset( $item['variation'] ) && is_array( $item['variation'] )
					? array_map( 'sanitize_text_field', $item['variation'] )
					: array(),
				'cart_item_data' => array(),
			);
		}
		return $clean;
	}

	/**
	 * Handle GET /restore/{token} — restore a saved cart into the current session.
	 *
	 * Returns 404 for both missing tokens and expired carts (no existence leakage).
	 * Restoration is graceful: warnings are collected per failed item/coupon rather
	 * than aborting the whole operation.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore( WP_REST_Request $request ) {
		$token = sanitize_text_field( $request->get_param( 'token' ) );
		$row   = $this->db->find_by_token( $token );

		if ( null === $row ) {
			return new WP_Error(
				'cartshare_not_found',
				__( 'Cart not found or has expired.', 'cartshare' ),
				array( 'status' => 404 )
			);
		}

		// Treat expired rows the same as missing (no leakage of past existence).
		if ( ! empty( $row['expires_at'] ) ) {
			$expires_ts = strtotime( $row['expires_at'] );
			if ( $expires_ts && $expires_ts < time() ) {
				return new WP_Error(
					'cartshare_not_found',
					__( 'Cart not found or has expired.', 'cartshare' ),
					array( 'status' => 404 )
				);
			}
		}

		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		$cart_data   = json_decode( $row['cart_data'], true );
		$cart_helper = new CartShare_Cart( $this->db );
		$warnings    = $cart_helper->restore( $cart_data );

		// Resolve the post-restore redirect target from admin settings.
		$redirect_setting = get_option( 'cartshare_restore_redirect', 'cart' );
		$redirect_url     = ( 'checkout' === $redirect_setting )
			? wc_get_checkout_url()
			: wc_get_cart_url();

		return rest_ensure_response(
			array(
				'warnings'     => $warnings,
				'redirect_url' => $redirect_url,
			)
		);
	}

	/**
	 * Handle GET /list — return all saved carts for the current logged-in user.
	 *
	 * Only called when check_logged_in() has already confirmed a valid session.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response
	 */
	public function list_carts( WP_REST_Request $request ) {
		$carts = $this->db->list_for_user( get_current_user_id() );

		return rest_ensure_response( $carts );
	}

	/**
	 * Handle POST /share/email — send a share-by-email message for a saved cart.
	 *
	 * Returns WP_Error with 403 when the admin has disabled the email sharing
	 * channel.  Returns 400 when required parameters are missing or invalid.
	 * Delegates sending to CartShare_Email::send().
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function share_email( WP_REST_Request $request ) {
		// Honor the admin 'Enable email sharing' toggle.
		if ( '1' !== get_option( 'cartshare_channel_email', '1' ) ) {
			return new WP_Error(
				'cartshare_email_disabled',
				__( 'Email sharing is disabled.', 'cartshare' ),
				array( 'status' => 403 )
			);
		}

		$to          = sanitize_email( (string) ( $request->get_param( 'to' ) ?? '' ) );
		$subject     = sanitize_text_field( (string) ( $request->get_param( 'subject' ) ?? '' ) );
		$message     = sanitize_textarea_field( (string) ( $request->get_param( 'message' ) ?? '' ) );
		$share_url   = esc_url_raw( (string) ( $request->get_param( 'share_url' ) ?? '' ) );
		$sender_name = $request->get_param( 'sender_name' );
		$sender_name = $sender_name ? sanitize_text_field( (string) $sender_name ) : null;

		if ( '' === $to ) {
			return new WP_Error(
				'cartshare_invalid_email',
				__( 'A valid recipient email address is required.', 'cartshare' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $share_url ) {
			return new WP_Error(
				'cartshare_missing_share_url',
				__( 'A share URL is required.', 'cartshare' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( 'CartShare_Email' ) ) {
			return new WP_Error(
				'cartshare_email_unavailable',
				__( 'Email sending is not available.', 'cartshare' ),
				array( 'status' => 500 )
			);
		}

		$email_sender = new CartShare_Email();
		$sent         = $email_sender->send( $to, $subject, $message, $share_url, $sender_name );

		if ( ! $sent ) {
			return new WP_Error(
				'cartshare_email_failed',
				__( 'Failed to send the email. Please try again.', 'cartshare' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( array( 'sent' => true ) );
	}

	/**
	 * Handle DELETE /delete/{token} — delete a cart owned by the caller.
	 *
	 * Ownership is enforced inside CartShare_DB::delete_by_token() using either
	 * the logged-in user ID or the WooCommerce guest session ID. A false return
	 * means either "not found" or "ownership mismatch"; both surface as 403 to
	 * avoid leaking the existence of a cart that belongs to another user.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete( WP_REST_Request $request ) {
		$token    = sanitize_text_field( $request->get_param( 'token' ) );
		$user_id  = get_current_user_id() ?: null;
		$guest_id = ( ! $user_id && WC()->session ) ? WC()->session->get_customer_id() : null;

		$deleted = $this->db->delete_by_token( $token, $user_id, $guest_id );

		if ( false === $deleted ) {
			return new WP_Error(
				'cartshare_forbidden',
				__( 'You do not have permission to delete this cart.', 'cartshare' ),
				array( 'status' => 403 )
			);
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
