<?php
/**
 * Frontend enqueue and button rendering for CartShare.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Frontend
 *
 * Enqueues popup assets exclusively on cart/checkout/account pages,
 * localizes the script with REST URL, nonce, enabled channels, labels,
 * and color CSS variables; renders the Save & Share button in the classic cart;
 * and outputs the popup modal markup via the footer action.
 */
class CartShare_Frontend {

	/**
	 * All share-channel slugs recognised by this plugin.
	 * Keys match the option names stored by the admin settings page.
	 *
	 * @var string[]
	 */
	private $all_channels = array(
		'email',
		'copy_link',
		'print',
		'facebook',
		'messenger',
		'whatsapp',
		'twitter',
		'linkedin',
		'skype',
	);

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_after_cart_totals', array( $this, 'render_button' ) );
		add_action( 'wp_footer', array( $this, 'render_popup' ) );
		add_action( 'woocommerce_blocks_cart_block_registration', array( $this, 'register_blocks_integration' ) );

		// Print-cart front controller: register the query var and intercept early.
		add_filter( 'query_vars', array( $this, 'add_print_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_print_cart' ) );

		// Restore-cart landing page front controller.
		add_filter( 'query_vars', array( $this, 'add_restore_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_restore_cart' ) );
	}

	/**
	 * Register the cartshare_print query variable with WordPress.
	 *
	 * Without this, get_query_var() will always return an empty string
	 * for unrecognised vars.
	 *
	 * @param string[] $vars Existing registered query variables.
	 * @return string[]
	 */
	public function add_print_query_var( array $vars ): array {
		$vars[] = 'cartshare_print';
		return $vars;
	}

	/**
	 * Register the cartshare_restore query variable with WordPress.
	 *
	 * Allows get_query_var( 'cartshare_restore' ) to return the token
	 * when the restore landing page is requested via ?cartshare_restore=TOKEN.
	 *
	 * @param string[] $vars Existing registered query variables.
	 * @return string[]
	 */
	public function add_restore_query_var( array $vars ): array {
		$vars[] = 'cartshare_restore';
		return $vars;
	}

	/**
	 * Detect ?cartshare_print=<token> and serve the printable cart page.
	 *
	 * Fires on template_redirect so it can exit before the theme template
	 * renders.  Resolves the cart row from the DB and passes it to the
	 * print template.  An invalid or expired token renders a friendly
	 * error message rather than a fatal.
	 *
	 * @return void
	 */
	public function maybe_print_cart(): void {
		$token = get_query_var( 'cartshare_print', '' );

		// Fallback to $_GET for setups where rewrite flushing has not run.
		if ( '' === $token && isset( $_GET['cartshare_print'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$token = sanitize_text_field( wp_unslash( $_GET['cartshare_print'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( '' === $token ) {
			return;
		}

		// Validate token format: 32 alphanumeric characters.
		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			$cart_row = null;
		} else {
			$db       = new CartShare_DB();
			$row      = $db->find_by_token( $token );

			// Treat expired rows as not found.
			if (
				null !== $row &&
				! empty( $row['expires_at'] ) &&
				strtotime( $row['expires_at'] ) < time()
			) {
				$row = null;
			}

			$cart_row = $row;
		}

		$template = CARTSHARE_PATH . 'templates/print-cart.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		exit;
	}

	/**
	 * Detect ?cartshare_restore=<token> and serve the restore-cart landing page.
	 *
	 * On GET: renders the restore-landing template so the visitor sees a
	 * confirmation screen before any cart is modified (E2E scenario 6:
	 * "restore into non-empty cart — confirmation prompt").
	 *
	 * On POST (confirmed): verifies the nonce, calls execute_restore(), and
	 * redirects to cart or checkout per admin setting.
	 *
	 * @return void
	 */
	public function maybe_restore_cart(): void {
		$token = get_query_var( 'cartshare_restore', '' );

		// Fallback to $_GET for setups where rewrite flushing has not run.
		if ( '' === $token && isset( $_GET['cartshare_restore'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$token = sanitize_text_field( wp_unslash( $_GET['cartshare_restore'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( '' === $token ) {
			return;
		}

		$cart_row = null;
		if ( preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			$db  = new CartShare_DB();
			$row = $db->find_by_token( $token );

			// Treat expired rows as not found (no existence leakage).
			if ( null !== $row &&
				! empty( $row['expires_at'] ) &&
				strtotime( $row['expires_at'] ) < time()
			) {
				$row = null;
			}

			$cart_row = $row;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REQUEST_METHOD is server-set, not user input.

		if ( 'POST' === $request_method && null !== $cart_row ) {
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is reading the nonce itself; wp_verify_nonce() is called immediately below.
			if ( wp_verify_nonce( $nonce, 'cartshare_restore_' . $token ) ) {
				$this->execute_restore( $cart_row );
				return; // execute_restore() always redirects + exits.
			}
			// Invalid nonce: fall through and re-render the landing page.
		}

		$template = CARTSHARE_PATH . 'templates/restore-landing.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		exit;
	}

	/**
	 * Perform the cart restore and redirect to the configured target page.
	 *
	 * Ownership is not checked here because the restore landing page is public
	 * by design — the token is the ownership credential for public share links.
	 * Warnings (deleted products, expired coupons) are surfaced via WC notices.
	 *
	 * @param array $row DB row from the cartshare_carts table.
	 * @return void  Never returns normally; always exits via wp_safe_redirect().
	 */
	private function execute_restore( array $row ): void {
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		$cart_data = json_decode( $row['cart_data'], true );

		if ( is_array( $cart_data ) ) {
			if ( class_exists( 'CartShare_Cart' ) ) {
				$cart_helper = new CartShare_Cart( new CartShare_DB() );
				$warnings    = $cart_helper->restore( $cart_data );
			} else {
				$warnings = array();
			}

			foreach ( $warnings as $warning ) {
				wc_add_notice( esc_html( $warning ), 'notice' );
			}
			wc_add_notice( __( 'Cart restored successfully.', 'cartshare' ), 'success' );
		} else {
			wc_add_notice( __( 'Cart data is invalid and could not be restored.', 'cartshare' ), 'error' );
		}

		$redirect = get_option( 'cartshare_restore_redirect', 'cart' );
		$url      = 'checkout' === $redirect ? wc_get_checkout_url() : wc_get_cart_url();
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Register the CartShare blocks integration with WooCommerce Blocks.
	 *
	 * Injects the Save & Share button into the block-based Cart without
	 * requiring a build step, using ExperimentalOrderMeta slot and
	 * wp.element.createElement.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $registry WooCommerce Blocks integration registry.
	 * @return void
	 */
	public function register_blocks_integration( $registry ): void {
		require_once CARTSHARE_PATH . 'includes/class-cartshare-blocks-integration.php';
		$registry->register( new CartShare_Blocks_Integration() );
	}

	/**
	 * Enqueue popup JS and CSS only on cart, checkout, and account pages.
	 *
	 * Scripts are never enqueued site-wide.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			return;
		}

		wp_enqueue_style(
			'cartshare-popup',
			CARTSHARE_URL . 'assets/css/popup.css',
			array(),
			CARTSHARE_VERSION
		);

		wp_enqueue_script(
			'cartshare-popup',
			CARTSHARE_URL . 'assets/js/popup.js',
			array(),
			CARTSHARE_VERSION,
			true
		);

		wp_localize_script(
			'cartshare-popup',
			'CartShareData',
			$this->get_script_data()
		);
	}

	/**
	 * Build the localisation data object passed to popup.js.
	 *
	 * Includes REST URL, nonce, enabled channels, UI labels, and colour values.
	 *
	 * @return array
	 */
	private function get_script_data(): array {
		return array(
			'restUrl'     => rest_url( 'cartshare/v1' ),
			'siteUrl'     => esc_url( home_url( '/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'channels'    => $this->get_enabled_channels(),
			'labels'      => array(
				'save'         => esc_html__( 'Save & Share Cart', 'cartshare' ),
				'saving'       => esc_html__( 'Saving…', 'cartshare' ),
				'copyLink'     => esc_html__( 'Copy Link', 'cartshare' ),
				'copied'       => esc_html__( 'Copied!', 'cartshare' ),
				'email'        => esc_html__( 'Email', 'cartshare' ),
				'print'        => esc_html__( 'Print', 'cartshare' ),
				'facebook'     => esc_html__( 'Facebook', 'cartshare' ),
				'messenger'    => esc_html__( 'Messenger', 'cartshare' ),
				'whatsapp'     => esc_html__( 'WhatsApp', 'cartshare' ),
				'twitter'      => esc_html__( 'X / Twitter', 'cartshare' ),
				'linkedin'     => esc_html__( 'LinkedIn', 'cartshare' ),
				'skype'        => esc_html__( 'Skype', 'cartshare' ),
				'close'        => esc_html__( 'Close', 'cartshare' ),
				'confirmTitle' => esc_html__( 'Replace your current cart?', 'cartshare' ),
				'confirmMsg'   => esc_html__( 'Your current cart items will be replaced. Continue?', 'cartshare' ),
				'confirmYes'   => esc_html__( 'Yes, restore', 'cartshare' ),
				'confirmNo'    => esc_html__( 'Cancel', 'cartshare' ),
				'errorEmpty'   => esc_html__( 'Your cart is empty. Add items before sharing.', 'cartshare' ),
				'errorGeneric' => esc_html__( 'Something went wrong. Please try again.', 'cartshare' ),
				'emailLabel'   => esc_html__( 'Recipient email', 'cartshare' ),
				'emailMsg'     => esc_html__( 'Message (optional)', 'cartshare' ),
				'emailSend'    => esc_html__( 'Send', 'cartshare' ),
			),
			'colors'      => $this->get_color_vars(),
			'buttonLabel' => esc_html( get_option( 'cartshare_button_label', __( 'Save & Share Cart', 'cartshare' ) ) ),
		);
	}

	/**
	 * Return the slugs of all channels that are currently enabled in admin settings.
	 *
	 * An option value of '0' means the channel is disabled; any other value (including
	 * the default of '1' when the option has never been saved) means it is enabled.
	 *
	 * @return string[]
	 */
	private function get_enabled_channels(): array {
		$enabled = array();
		foreach ( $this->all_channels as $channel ) {
			if ( '0' !== get_option( 'cartshare_channel_' . $channel, '1' ) ) {
				$enabled[] = $channel;
			}
		}
		return $enabled;
	}

	/**
	 * Return admin-configured colour values for use as CSS custom properties.
	 *
	 * Falls back to sensible defaults when options have not been saved yet.
	 *
	 * @return string[]
	 */
	private function get_color_vars(): array {
		$primary     = sanitize_hex_color( get_option( 'cartshare_color_primary', '#4f46e5' ) );
		$button_bg   = sanitize_hex_color( get_option( 'cartshare_color_button_bg', '#4f46e5' ) );
		$button_text = sanitize_hex_color( get_option( 'cartshare_color_button_text', '#ffffff' ) );

		return array(
			'primary'    => $primary ?: '#4f46e5',
			'buttonBg'   => $button_bg ?: '#4f46e5',
			'buttonText' => $button_text ?: '#ffffff',
		);
	}

	/**
	 * Render the Save & Share button below the classic cart totals block.
	 *
	 * Output is fully escaped; the button label comes from the admin option.
	 *
	 * @return void
	 */
	public function render_button(): void {
		$label = get_option( 'cartshare_button_label', __( 'Save & Share Cart', 'cartshare' ) );
		?>
		<div class="cartshare-button-wrap">
			<button
				type="button"
				class="cartshare-open button alt"
				aria-haspopup="dialog"
			><?php echo esc_html( $label ); ?></button>
		</div>
		<?php
	}

	/**
	 * Include the popup modal template in the page footer.
	 *
	 * Only included on cart, checkout, and account pages to avoid adding a hidden
	 * modal to every page of the site.
	 *
	 * @return void
	 */
	public function render_popup(): void {
		if ( ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			return;
		}

		$template = CARTSHARE_PATH . 'templates/popup.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
