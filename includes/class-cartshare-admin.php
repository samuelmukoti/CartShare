<?php
/**
 * Admin settings page for CartShare.
 *
 * Registers a submenu page under WooCommerce, renders five tabs
 * (General, Sharing, Email, Appearance, History), handles form
 * submissions with proper nonce and capability checks, and sanitizes
 * every saved value.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Admin
 *
 * Wires the CartShare settings panel into WP Admin under the WooCommerce menu.
 * All state-changing handlers enforce current_user_can('manage_woocommerce')
 * and check_admin_referer().  Every stored value is sanitized on write and
 * escaped on output.
 */
class CartShare_Admin {

	/**
	 * Number of history rows to show per page.
	 *
	 * @var int
	 */
	const HISTORY_PER_PAGE = 20;

	/**
	 * Valid tab slugs.
	 *
	 * @var string[]
	 */
	private $tabs = array(
		'general',
		'sharing',
		'email',
		'appearance',
		'history',
	);

	/**
	 * All share-channel slugs managed by this plugin.
	 *
	 * @var string[]
	 */
	private $channels = array(
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
	 * Register WordPress / WooCommerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_cartshare_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_cartshare_delete_history', array( $this, 'handle_delete_history' ) );
	}

	/**
	 * Register the CartShare submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Save & Share Cart Settings', 'cartshare' ),
			__( 'Save & Share Cart', 'cartshare' ),
			'manage_woocommerce',
			'cartshare-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS only on the CartShare settings page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'cartshare-settings' ) ) {
			return;
		}

		// WordPress color picker (bundled with WP core).
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'cartshare-admin',
			CARTSHARE_URL . 'assets/css/admin.css',
			array(),
			CARTSHARE_VERSION
		);

		wp_enqueue_script(
			'cartshare-admin',
			CARTSHARE_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			CARTSHARE_VERSION,
			true
		);

		wp_localize_script(
			'cartshare-admin',
			'CartShareAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-post.php' ),
				'deleteNonce'   => wp_create_nonce( 'cartshare_delete_history' ),
				'confirmDelete' => __( 'Are you sure you want to delete this cart? This action cannot be undone.', 'cartshare' ),
				/* translators: %s: share URL */
				'copyLabel'     => __( 'Copy Link', 'cartshare' ),
				'copiedLabel'   => __( 'Copied!', 'cartshare' ),
			)
		);
	}

	/**
	 * Render the settings page by including the admin-settings template.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cartshare' ) );
		}

		$template = CARTSHARE_PATH . 'templates/admin-settings.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Handle settings form submission for all tabs.
	 *
	 * Fired via admin_post_cartshare_save_settings (action=cartshare_save_settings).
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'cartshare' ) );
		}

		check_admin_referer( 'cartshare_save_settings' );

		// Nonce already verified above via check_admin_referer().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_admin_referer() above.
		$tab = isset( $_POST['cartshare_tab'] ) ? sanitize_key( wp_unslash( $_POST['cartshare_tab'] ) ) : 'general';

		if ( ! in_array( $tab, $this->tabs, true ) ) {
			$tab = 'general';
		}

		switch ( $tab ) {
			case 'general':
				$this->save_general_settings();
				break;
			case 'sharing':
				$this->save_sharing_settings();
				break;
			case 'email':
				$this->save_email_settings();
				break;
			case 'appearance':
				$this->save_appearance_settings();
				break;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'cartshare-settings',
					'tab'     => $tab,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save General tab settings.
	 *
	 * @return void
	 */
	private function save_general_settings(): void {
		// Nonce already verified by check_admin_referer() in handle_save_settings().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$redirect        = isset( $_POST['cartshare_restore_redirect'] ) ? sanitize_key( wp_unslash( $_POST['cartshare_restore_redirect'] ) ) : 'cart';
		$expiry_value    = isset( $_POST['cartshare_expiry_value'] ) ? absint( wp_unslash( $_POST['cartshare_expiry_value'] ) ) : 30;
		$expiry_unit     = isset( $_POST['cartshare_expiry_unit'] ) ? sanitize_key( wp_unslash( $_POST['cartshare_expiry_unit'] ) ) : 'days';
		$multi_cart      = isset( $_POST['cartshare_multi_cart'] ) ? '1' : '0';
		$flush_on_save   = isset( $_POST['cartshare_flush_on_save'] ) ? '1' : '0';
		$flush_on_replace = isset( $_POST['cartshare_flush_on_replace'] ) ? '1' : '0';
		$replace_button  = isset( $_POST['cartshare_enable_replace_button'] ) ? '1' : '0';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$redirect = in_array( $redirect, array( 'cart', 'checkout' ), true ) ? $redirect : 'cart';
		$expiry_unit = in_array( $expiry_unit, array( 'days', 'weeks', 'months' ), true ) ? $expiry_unit : 'days';

		update_option( 'cartshare_restore_redirect', $redirect );
		update_option( 'cartshare_expiry_value', $expiry_value );
		update_option( 'cartshare_expiry_unit', $expiry_unit );
		update_option( 'cartshare_multi_cart', $multi_cart );
		update_option( 'cartshare_flush_on_save', $flush_on_save );
		update_option( 'cartshare_flush_on_replace', $flush_on_replace );
		update_option( 'cartshare_enable_replace_button', $replace_button );

		// Recompute expiry_days for use by the REST API / DB layer.
		$this->update_expiry_days( $expiry_value, $expiry_unit );
	}

	/**
	 * Compute and store expiry in seconds from value + unit.
	 *
	 * @param int    $value Numeric amount.
	 * @param string $unit  'days', 'weeks', or 'months'.
	 * @return void
	 */
	private function update_expiry_days( int $value, string $unit ): void {
		$multipliers = array(
			'days'   => DAY_IN_SECONDS,
			'weeks'  => WEEK_IN_SECONDS,
			'months' => 30 * DAY_IN_SECONDS,
		);
		$seconds = $value * ( $multipliers[ $unit ] ?? DAY_IN_SECONDS );
		update_option( 'cartshare_expiry_seconds', $seconds );
	}

	/**
	 * Save Sharing tab settings.
	 *
	 * @return void
	 */
	private function save_sharing_settings(): void {
		foreach ( $this->channels as $channel ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$enabled = isset( $_POST[ 'cartshare_channel_' . $channel ] ) ? '1' : '0';
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			update_option( 'cartshare_channel_' . $channel, $enabled );
		}
	}

	/**
	 * Save Email tab settings.
	 *
	 * @return void
	 */
	private function save_email_settings(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$logo_url      = isset( $_POST['cartshare_email_logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['cartshare_email_logo_url'] ) ) : '';
		$header_color  = isset( $_POST['cartshare_color_primary'] ) ? sanitize_hex_color( wp_unslash( $_POST['cartshare_color_primary'] ) ) : '#4f46e5';
		$footer_color  = isset( $_POST['cartshare_email_footer_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['cartshare_email_footer_color'] ) ) : '#f3f4f6';
		$button_bg     = isset( $_POST['cartshare_color_button_bg'] ) ? sanitize_hex_color( wp_unslash( $_POST['cartshare_color_button_bg'] ) ) : '#4f46e5';
		$button_text   = isset( $_POST['cartshare_color_button_text'] ) ? sanitize_hex_color( wp_unslash( $_POST['cartshare_color_button_text'] ) ) : '#ffffff';
		$subject       = isset( $_POST['cartshare_email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['cartshare_email_subject'] ) ) : '';
		$button_label  = isset( $_POST['cartshare_email_button_label'] ) ? sanitize_text_field( wp_unslash( $_POST['cartshare_email_button_label'] ) ) : '';
		$header_text   = isset( $_POST['cartshare_email_header_text'] ) ? sanitize_text_field( wp_unslash( $_POST['cartshare_email_header_text'] ) ) : '';
		$footer_text   = isset( $_POST['cartshare_email_footer_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cartshare_email_footer_text'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_option( 'cartshare_email_logo_url', $logo_url );
		update_option( 'cartshare_color_primary', $header_color ?: '#4f46e5' );
		update_option( 'cartshare_email_footer_color', $footer_color ?: '#f3f4f6' );
		update_option( 'cartshare_color_button_bg', $button_bg ?: '#4f46e5' );
		update_option( 'cartshare_color_button_text', $button_text ?: '#ffffff' );
		update_option( 'cartshare_email_subject', $subject );
		update_option( 'cartshare_email_button_label', $button_label );
		update_option( 'cartshare_email_header_text', $header_text );
		update_option( 'cartshare_email_footer_text', $footer_text );
	}

	/**
	 * Save Appearance tab settings.
	 *
	 * @return void
	 */
	private function save_appearance_settings(): void {
		$hex_fields = array(
			'cartshare_popup_header_bg',
			'cartshare_popup_header_text',
			'cartshare_popup_body_bg',
			'cartshare_popup_body_text',
			'cartshare_popup_footer_bg',
			'cartshare_popup_footer_text',
			'cartshare_popup_overlay_color',
		);

		foreach ( $hex_fields as $field ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$raw = isset( $_POST[ $field ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $field ] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			if ( $raw ) {
				update_option( $field, $raw );
			}
		}

		$text_fields = array(
			'cartshare_button_label',
			'cartshare_popup_title',
			'cartshare_label_save',
			'cartshare_label_copy',
			'cartshare_label_print',
			'cartshare_label_email',
			'cartshare_label_facebook',
			'cartshare_label_messenger',
			'cartshare_label_whatsapp',
			'cartshare_label_twitter',
			'cartshare_label_linkedin',
			'cartshare_label_skype',
		);

		foreach ( $text_fields as $field ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$raw = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			update_option( $field, $raw );
		}

		// Overlay opacity (0.0 – 1.0).
		// Nonce already verified by check_admin_referer() in handle_save_settings().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$opacity = isset( $_POST['cartshare_popup_overlay_opacity'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['cartshare_popup_overlay_opacity'] ) ) : 0.5;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$opacity = max( 0.0, min( 1.0, $opacity ) );
		update_option( 'cartshare_popup_overlay_opacity', (string) $opacity );
	}

	/**
	 * Handle deleting a single cart from the History tab.
	 *
	 * Fired via admin_post_cartshare_delete_history.
	 *
	 * @return void
	 */
	public function handle_delete_history(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'cartshare' ) );
		}

		check_admin_referer( 'cartshare_delete_history' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$token = isset( $_POST['cartshare_token'] ) ? sanitize_text_field( wp_unslash( $_POST['cartshare_token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'cartshare_carts';
			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table,
				array( 'token' => $token ),
				array( '%s' )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'cartshare-settings',
					'tab'     => 'history',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Return paginated history rows for the History tab template.
	 *
	 * @param int $page   Current page (1-based).
	 * @param int $offset Computed offset.
	 * @return array { rows: array, total: int, pages: int }
	 */
	public function get_history_page( int $page = 1, int &$offset = 0 ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'cartshare_carts';
		$limit  = self::HISTORY_PER_PAGE;
		$offset = ( max( 1, $page ) - 1 ) * $limit;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'rows'  => $rows ?: array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $limit ),
		);
	}
}
