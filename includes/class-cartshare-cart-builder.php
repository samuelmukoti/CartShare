<?php
/**
 * Admin Cart Builder page for CartShare.
 *
 * Registers a "Create Shared Cart" submenu page under WooCommerce,
 * provides product-search and variation-fetch AJAX endpoints, and
 * renders the admin cart builder template.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Cart_Builder
 *
 * Wires the CartShare cart builder panel into WP Admin under the WooCommerce menu.
 * All state-changing handlers enforce current_user_can('manage_woocommerce')
 * and check_ajax_referer(). AJAX endpoints are admin-only (no nopriv variants).
 */
class CartShare_Cart_Builder {

	/**
	 * Register WordPress / WooCommerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_cartshare_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_cartshare_get_variations', array( $this, 'ajax_get_variations' ) );
		add_action( 'wp_ajax_cartshare_search_customers', array( $this, 'ajax_search_customers' ) );
	}

	/**
	 * Register the Cart Builder submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Create Shared Cart', 'cartshare' ),
			__( 'Create Shared Cart', 'cartshare' ),
			// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers manage_woocommerce.
			'manage_woocommerce',
			'cartshare-cart-builder',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS only on the CartShare Cart Builder page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'cartshare-cart-builder' ) ) {
			return;
		}

		wp_enqueue_style(
			'cartshare-cart-builder',
			CARTSHARE_URL . 'assets/css/admin-cart-builder.css',
			array(),
			CARTSHARE_VERSION
		);

		wp_enqueue_script(
			'cartshare-cart-builder',
			CARTSHARE_URL . 'assets/js/admin-cart-builder.js',
			array( 'jquery' ),
			CARTSHARE_VERSION,
			true
		);

		wp_localize_script(
			'cartshare-cart-builder',
			'CartShareCartBuilder',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => rest_url( 'cartshare/v1/' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'ajaxNonce' => wp_create_nonce( 'cartshare_cart_builder' ),
				'currency'  => get_woocommerce_currency_symbol(),
				'i18n'      => array(
					'generateLink' => __( 'Generate Link', 'cartshare' ),
					'generating'   => __( 'Generating…', 'cartshare' ),
					'copyLink'     => __( 'Copy Link', 'cartshare' ),
					'copied'       => __( 'Copied!', 'cartshare' ),
					'noProducts'   => __( 'No products found.', 'cartshare' ),
					'emptyCart'    => __( 'Add at least one product before generating a link.', 'cartshare' ),
				),
			)
		);
	}

	/**
	 * Search products by name and SKU via AJAX.
	 *
	 * @return void
	 */
	public function ajax_search_products(): void {
		check_ajax_referer( 'cartshare_cart_builder', 'nonce' );

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers manage_woocommerce.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'cartshare' ) ), 403 );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above via check_ajax_referer.
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		// Search by product name.
		$by_name = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 20,
				'type'   => array( 'simple', 'variable' ),
				's'      => $term,
			)
		);

		// Also search by SKU.
		$by_sku = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 10,
				'sku'    => $term,
			)
		);

		// Merge and deduplicate by ID.
		$all = array();
		foreach ( array_merge( $by_name, $by_sku ) as $product ) {
			$all[ $product->get_id() ] = $product;
		}

		$results = array();
		foreach ( $all as $product ) {
			$image_id  = $product->get_image_id();
			$thumb_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : false;
			$results[] = array(
				'id'          => $product->get_id(),
				'name'        => $product->get_name(),
				'sku'         => $product->get_sku(),
				'type'        => $product->get_type(),
				'price'       => wc_price( $product->get_price() ),
				'thumbnail'   => $thumb_url ? $thumb_url : wc_placeholder_img_src( 'thumbnail' ),
				'is_variable' => $product->is_type( 'variable' ),
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * Fetch available variations for a variable product via AJAX.
	 *
	 * @return void
	 */
	public function ajax_get_variations(): void {
		check_ajax_referer( 'cartshare_cart_builder', 'nonce' );

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers manage_woocommerce.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'cartshare' ) ), 403 );
		}

		$product_id = isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above via check_ajax_referer.
		$product    = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			wp_send_json_error( array( 'message' => __( 'Product not found or not variable.', 'cartshare' ) ) );
		}

		$variations = $product->get_available_variations();
		$results    = array_map(
			function ( $v ) {
				return array(
					'variation_id' => $v['variation_id'],
					'attributes'   => $v['attributes'],
					'sku'          => $v['sku'],
					'price'        => $v['display_price'],
					'available'    => $v['is_in_stock'],
					'description'  => $v['variation_description'],
					'image'        => isset( $v['image']['thumb_src'] ) ? $v['image']['thumb_src'] : '',
				);
			},
			$variations
		);

		wp_send_json_success( $results );
	}

	/**
	 * Search registered customers by name, email, or login via AJAX.
	 *
	 * @return void
	 */
	public function ajax_search_customers(): void {
		check_ajax_referer( 'cartshare_cart_builder', 'nonce' );

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers manage_woocommerce.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'cartshare' ) ), 403 );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above via check_ajax_referer.
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		$users = get_users(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'number'         => 20,
				'fields'         => array( 'ID', 'display_name', 'user_email' ),
			)
		);

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'    => (int) $user->ID,
				'name'  => $user->display_name,
				'email' => $user->user_email,
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * Render the cart builder admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers manage_woocommerce.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cartshare' ) );
		}

		$template = CARTSHARE_PATH . 'templates/admin-cart-builder.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
