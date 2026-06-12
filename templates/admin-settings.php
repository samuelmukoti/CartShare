<?php
/**
 * Admin settings page template for CartShare.
 *
 * Included by CartShare_Admin::render_settings_page(). Renders a tabbed
 * settings panel under WooCommerce → WP CartShare Pro.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Capability check — belt-and-braces; already verified by the controller.
// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers manage_woocommerce.
if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'cartshare' ) );
}

// Determine active tab.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$default_tab = '1' === get_option( 'cartshare_onboarding_complete', '0' ) ? 'general' : 'setup';
$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default_tab;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$valid_tabs = array( 'setup', 'general', 'sharing', 'email', 'appearance', 'history' );
if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
	$active_tab = $default_tab;
}

// Success notice.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$updated = isset( $_GET['updated'] ) && '1' === sanitize_key( wp_unslash( $_GET['updated'] ) );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Helper: read option with esc_attr for form field values.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $opt() closure always returns esc_attr()-escaped output; PHPCS cannot analyse closures.
$opt = static function ( string $name, string $fallback = '' ): string {
	return esc_attr( get_option( $name, $fallback ) );
};

// All share channels.
$channels = array(
	'email'     => __( 'Email', 'cartshare' ),
	'copy_link' => __( 'Copy Link', 'cartshare' ),
	'print'     => __( 'Print', 'cartshare' ),
	'facebook'  => __( 'Facebook', 'cartshare' ),
	'messenger' => __( 'Messenger', 'cartshare' ),
	'whatsapp'  => __( 'WhatsApp', 'cartshare' ),
	'twitter'   => __( 'X / Twitter', 'cartshare' ),
	'linkedin'  => __( 'LinkedIn', 'cartshare' ),
	'skype'     => __( 'Skype', 'cartshare' ),
);

// Settings page URL base.
$settings_url = admin_url( 'admin.php?page=cartshare-settings' );
$admin_obj    = new CartShare_Admin();
$setup_status = $admin_obj->get_setup_status();
$setup_label  = $setup_status['onboarding_complete'] ? esc_html__( 'Complete', 'cartshare' ) : esc_html__( 'Open', 'cartshare' );
?>

<div class="wrap cartshare-admin">
	<h1><?php esc_html_e( 'WP CartShare Pro Settings', 'cartshare' ); ?></h1>

	<?php if ( $updated ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'cartshare' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'setup', $settings_url ) ); ?>"
			class="nav-tab <?php echo 'setup' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Setup', 'cartshare' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'general', $settings_url ) ); ?>"
			class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'General', 'cartshare' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'sharing', $settings_url ) ); ?>"
			class="nav-tab <?php echo 'sharing' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Sharing', 'cartshare' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'email', $settings_url ) ); ?>"
			class="nav-tab <?php echo 'email' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Email', 'cartshare' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'appearance', $settings_url ) ); ?>"
			class="nav-tab <?php echo 'appearance' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Appearance', 'cartshare' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $settings_url ) ); ?>"
			class="nav-tab <?php echo 'history' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'History', 'cartshare' ); ?>
		</a>
	</nav>

	<!-- ======================================================
		SETUP TAB
		====================================================== -->
	<?php if ( 'setup' === $active_tab ) : ?>
		<section class="cartshare-setup-hero">
			<div>
				<p class="cartshare-eyebrow"><?php esc_html_e( 'Onboarding', 'cartshare' ); ?></p>
				<h2><?php esc_html_e( 'Configure cart sharing before launch', 'cartshare' ); ?></h2>
				<p><?php esc_html_e( 'Use this setup flow to confirm WooCommerce readiness, choose the restore behavior, set link lifetime, and decide which sharing channels customers can use.', 'cartshare' ); ?></p>
			</div>
			<div class="cartshare-setup-score" aria-label="<?php esc_attr_e( 'Setup status', 'cartshare' ); ?>">
				<span><?php echo esc_html( $setup_label ); ?></span>
				<strong><?php echo esc_html( $setup_status['enabled_channels'] ); ?>/<?php echo esc_html( $setup_status['total_channels'] ); ?></strong>
				<small><?php esc_html_e( 'channels enabled', 'cartshare' ); ?></small>
			</div>
		</section>

		<div class="cartshare-status-grid">
			<div class="cartshare-status-card <?php echo esc_attr( $setup_status['woocommerce_active'] ? 'is-ready' : 'needs-attention' ); ?>">
				<span class="cartshare-status-dot"></span>
				<h3><?php esc_html_e( 'WooCommerce', 'cartshare' ); ?></h3>
				<p><?php echo esc_html( $setup_status['woocommerce_active'] ? esc_html__( 'Active and available.', 'cartshare' ) : esc_html__( 'Install and activate WooCommerce.', 'cartshare' ) ); ?></p>
			</div>
			<div class="cartshare-status-card <?php echo esc_attr( $setup_status['table_exists'] ? 'is-ready' : 'needs-attention' ); ?>">
				<span class="cartshare-status-dot"></span>
				<h3><?php esc_html_e( 'Storage', 'cartshare' ); ?></h3>
				<p><?php echo esc_html( $setup_status['table_exists'] ? esc_html__( 'Saved cart table is ready.', 'cartshare' ) : esc_html__( 'Reactivate the plugin to create its table.', 'cartshare' ) ); ?></p>
			</div>
			<div class="cartshare-status-card <?php echo esc_attr( $setup_status['cart_page_ready'] ? 'is-ready' : 'needs-attention' ); ?>">
				<span class="cartshare-status-dot"></span>
				<h3><?php esc_html_e( 'Cart Page', 'cartshare' ); ?></h3>
				<p>
					<?php if ( $setup_status['cart_page_ready'] ) : ?>
						<a href="<?php echo esc_url( $setup_status['cart_page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open cart page', 'cartshare' ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'Assign a published WooCommerce cart page.', 'cartshare' ); ?>
					<?php endif; ?>
				</p>
			</div>
			<div class="cartshare-status-card <?php echo esc_attr( $setup_status['checkout_page_ready'] ? 'is-ready' : 'needs-attention' ); ?>">
				<span class="cartshare-status-dot"></span>
				<h3><?php esc_html_e( 'Checkout Page', 'cartshare' ); ?></h3>
				<p>
					<?php if ( $setup_status['checkout_page_ready'] ) : ?>
						<a href="<?php echo esc_url( $setup_status['checkout_page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open checkout page', 'cartshare' ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'Assign a published WooCommerce checkout page.', 'cartshare' ); ?>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<div class="cartshare-setup-layout">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cartshare-settings-form cartshare-setup-panel">
				<?php wp_nonce_field( 'cartshare_save_settings' ); ?>
				<input type="hidden" name="action" value="cartshare_save_settings">
				<input type="hidden" name="cartshare_tab" value="setup">
				<?php if ( '1' === get_option( 'cartshare_flush_on_save', '0' ) ) : ?>
					<input type="hidden" name="cartshare_flush_on_save" value="1">
				<?php endif; ?>
				<?php if ( '1' === get_option( 'cartshare_flush_on_replace', '0' ) ) : ?>
					<input type="hidden" name="cartshare_flush_on_replace" value="1">
				<?php endif; ?>

				<h2><?php esc_html_e( 'Quick Configuration', 'cartshare' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="cartshare_setup_restore_redirect"><?php esc_html_e( 'After Restore', 'cartshare' ); ?></label>
						</th>
						<td>
							<select name="cartshare_restore_redirect" id="cartshare_setup_restore_redirect">
								<option value="cart" <?php selected( get_option( 'cartshare_restore_redirect', 'cart' ), 'cart' ); ?>>
									<?php esc_html_e( 'Send customers to the cart page', 'cartshare' ); ?>
								</option>
								<option value="checkout" <?php selected( get_option( 'cartshare_restore_redirect', 'cart' ), 'checkout' ); ?>>
									<?php esc_html_e( 'Send customers directly to checkout', 'cartshare' ); ?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cartshare_setup_expiry_value"><?php esc_html_e( 'Share Link Lifetime', 'cartshare' ); ?></label>
						</th>
						<td>
							<input type="number" name="cartshare_expiry_value" id="cartshare_setup_expiry_value"
									value="<?php echo $opt( 'cartshare_expiry_value', '30' ); ?>"
									min="0" step="1" class="small-text">
							<select name="cartshare_expiry_unit">
								<option value="days" <?php selected( get_option( 'cartshare_expiry_unit', 'days' ), 'days' ); ?>><?php esc_html_e( 'Days', 'cartshare' ); ?></option>
								<option value="weeks" <?php selected( get_option( 'cartshare_expiry_unit', 'days' ), 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'cartshare' ); ?></option>
								<option value="months" <?php selected( get_option( 'cartshare_expiry_unit', 'days' ), 'months' ); ?>><?php esc_html_e( 'Months', 'cartshare' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Set to 0 if share links should never expire.', 'cartshare' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cart Handling', 'cartshare' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cartshare_multi_cart" value="1"
										<?php checked( get_option( 'cartshare_multi_cart', '1' ), '1' ); ?>>
								<?php esc_html_e( 'Allow multiple saved carts per customer', 'cartshare' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="cartshare_enable_replace_button" value="1"
										<?php checked( get_option( 'cartshare_enable_replace_button', '1' ), '1' ); ?>>
								<?php esc_html_e( 'Show a replace-cart choice when restoring into a non-empty cart', 'cartshare' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sharing Channels', 'cartshare' ); ?></th>
						<td>
							<div class="cartshare-channel-grid">
								<?php foreach ( $channels as $slug => $label ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( 'cartshare_channel_' . $slug ); ?>" value="1"
												<?php checked( get_option( 'cartshare_channel_' . $slug, '1' ), '1' ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Setup and Finish', 'cartshare' ) ); ?>
			</form>

			<aside class="cartshare-setup-panel cartshare-launch-checklist">
				<h2><?php esc_html_e( 'Launch Checklist', 'cartshare' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Confirm the cart and checkout pages are published.', 'cartshare' ); ?></li>
					<li><?php esc_html_e( 'Add a product to the cart and save a test share link.', 'cartshare' ); ?></li>
					<li><?php esc_html_e( 'Open the share link in a private browser window and restore it.', 'cartshare' ); ?></li>
					<li><?php esc_html_e( 'Send a test email if the Email channel is enabled.', 'cartshare' ); ?></li>
					<li><?php esc_html_e( 'Review labels and colors in the Appearance tab.', 'cartshare' ); ?></li>
				</ol>
				<p>
					<?php
					printf(
						/* translators: %d: saved cart count */
						esc_html__( 'Saved carts recorded: %d', 'cartshare' ),
						(int) $setup_status['total_carts']
					);
					?>
				</p>
			</aside>
		</div>
	<?php endif; ?>

	<!-- ======================================================
		GENERAL TAB
		====================================================== -->
	<?php if ( 'general' === $active_tab ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cartshare-settings-form">
		<?php wp_nonce_field( 'cartshare_save_settings' ); ?>
		<input type="hidden" name="action" value="cartshare_save_settings">
		<input type="hidden" name="cartshare_tab" value="general">

		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">
					<label for="cartshare_restore_redirect"><?php esc_html_e( 'After Restore, Redirect To', 'cartshare' ); ?></label>
				</th>
				<td>
					<select name="cartshare_restore_redirect" id="cartshare_restore_redirect">
						<option value="cart" <?php selected( get_option( 'cartshare_restore_redirect', 'cart' ), 'cart' ); ?>>
							<?php esc_html_e( 'Cart page', 'cartshare' ); ?>
						</option>
						<option value="checkout" <?php selected( get_option( 'cartshare_restore_redirect', 'cart' ), 'checkout' ); ?>>
							<?php esc_html_e( 'Checkout page', 'cartshare' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Where to send the visitor after a share link restores their cart.', 'cartshare' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cartshare_expiry_value"><?php esc_html_e( 'Cart Expiration', 'cartshare' ); ?></label>
				</th>
				<td>
					<input type="number" name="cartshare_expiry_value" id="cartshare_expiry_value"
							value="<?php echo $opt( 'cartshare_expiry_value', '30' ); ?>"
							min="0" step="1" class="small-text">
					<select name="cartshare_expiry_unit">
						<option value="days" <?php selected( get_option( 'cartshare_expiry_unit', 'days' ), 'days' ); ?>>
							<?php esc_html_e( 'Days', 'cartshare' ); ?>
						</option>
						<option value="weeks" <?php selected( get_option( 'cartshare_expiry_unit', 'days' ), 'weeks' ); ?>>
							<?php esc_html_e( 'Weeks', 'cartshare' ); ?>
						</option>
						<option value="months" <?php selected( get_option( 'cartshare_expiry_unit', 'days' ), 'months' ); ?>>
							<?php esc_html_e( 'Months', 'cartshare' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'How long saved cart share links remain valid. Set to 0 to never expire.', 'cartshare' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Allow Multiple Saved Carts', 'cartshare' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="cartshare_multi_cart" value="1"
								<?php checked( get_option( 'cartshare_multi_cart', '1' ), '1' ); ?>>
						<?php esc_html_e( 'Allow users to save more than one cart at a time', 'cartshare' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Flush Cart on Save', 'cartshare' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="cartshare_flush_on_save" value="1"
								<?php checked( get_option( 'cartshare_flush_on_save', '0' ), '1' ); ?>>
						<?php esc_html_e( 'Empty the active cart after saving it to a share link', 'cartshare' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Flush Cart on Replace', 'cartshare' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="cartshare_flush_on_replace" value="1"
								<?php checked( get_option( 'cartshare_flush_on_replace', '0' ), '1' ); ?>>
						<?php esc_html_e( 'Empty the active cart before restoring a shared cart', 'cartshare' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Replace Button', 'cartshare' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="cartshare_enable_replace_button" value="1"
								<?php checked( get_option( 'cartshare_enable_replace_button', '1' ), '1' ); ?>>
						<?php esc_html_e( 'Show a "Replace Cart" confirmation button when restoring into a non-empty cart', 'cartshare' ); ?>
					</label>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save General Settings', 'cartshare' ) ); ?>
	</form>
	<?php endif; ?>

	<!-- ======================================================
		SHARING TAB
		====================================================== -->
	<?php if ( 'sharing' === $active_tab ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cartshare-settings-form">
		<?php wp_nonce_field( 'cartshare_save_settings' ); ?>
		<input type="hidden" name="action" value="cartshare_save_settings">
		<input type="hidden" name="cartshare_tab" value="sharing">

		<table class="form-table" role="presentation">
			<?php foreach ( $channels as $slug => $label ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $label ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( 'cartshare_channel_' . $slug ); ?>" value="1"
								<?php checked( get_option( 'cartshare_channel_' . $slug, '1' ), '1' ); ?>>
						<?php
						/* translators: %s: channel name */
						printf( esc_html__( 'Enable the %s sharing channel', 'cartshare' ), esc_html( $label ) );
						?>
					</label>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		<?php submit_button( __( 'Save Sharing Settings', 'cartshare' ) ); ?>
	</form>
	<?php endif; ?>

	<!-- ======================================================
		EMAIL TAB
		====================================================== -->
	<?php if ( 'email' === $active_tab ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cartshare-settings-form">
		<?php wp_nonce_field( 'cartshare_save_settings' ); ?>
		<input type="hidden" name="action" value="cartshare_save_settings">
		<input type="hidden" name="cartshare_tab" value="email">

		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">
					<label for="cartshare_email_logo_url"><?php esc_html_e( 'Email Logo URL', 'cartshare' ); ?></label>
				</th>
				<td>
					<input type="url" name="cartshare_email_logo_url" id="cartshare_email_logo_url"
							value="<?php echo esc_attr( get_option( 'cartshare_email_logo_url', '' ) ); ?>"
							class="regular-text">
					<p class="description"><?php esc_html_e( 'Absolute URL of the logo image shown at the top of share emails. Leave blank to use no logo.', 'cartshare' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cartshare_color_primary"><?php esc_html_e( 'Header / Accent Color', 'cartshare' ); ?></label>
				</th>
				<td>
					<input type="text" name="cartshare_color_primary" id="cartshare_color_primary"
							value="<?php echo $opt( 'cartshare_color_primary', '#4f46e5' ); ?>"
							class="cartshare-color-picker" data-default-color="#4f46e5">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cartshare_email_footer_color"><?php esc_html_e( 'Email Footer Background Color', 'cartshare' ); ?></label>
				</th>
				<td>
					<input type="text" name="cartshare_email_footer_color" id="cartshare_email_footer_color"
							value="<?php echo $opt( 'cartshare_email_footer_color', '#f3f4f6' ); ?>"
							class="cartshare-color-picker" data-default-color="#f3f4f6">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cartshare_color_button_bg"><?php esc_html_e( 'CTA Button Background Color', 'cartshare' ); ?></label>
				</th>
				<td>
					<input type="text" name="cartshare_color_button_bg" id="cartshare_color_button_bg"
							value="<?php echo $opt( 'cartshare_color_button_bg', '#4f46e5' ); ?>"
							class="cartshare-color-picker" data-default-color="#4f46e5">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cartshare_color_button_text"><?php esc_html_e( 'CTA Button Text Color', 'cartshare' ); ?></label>
				</th>
				<td>
					<input type="text" name="cartshare_color_button_text" id="cartshare_color_button_text"
							value="<?php echo $opt( 'cartshare_color_button_text', '#ffffff' ); ?>"
							class="cartshare-color-picker" data-default-color="#ffffff">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cartshare_email_subject"><?php esc_html_e( 'Email Subject', 'cartshare' ); ?></label>
				</th>
				<td>
					<input type="text" name="cartshare_email_subject" id="cartshare_email_subject"
							value="<?php echo $opt( 'cartshare_email_subject', '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Someone shared a cart with you!', 'cartshare' ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cartshare_email_button_label"><?php esc_html_e( 'Email Button Label', 'cartshare' ); ?></label>
				</th>
				<td>
					<input type="text" name="cartshare_email_button_label" id="cartshare_email_button_label"
							value="<?php echo $opt( 'cartshare_email_button_label', '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'View Shared Cart', 'cartshare' ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cartshare_email_header_text"><?php esc_html_e( 'Email Header Text', 'cartshare' ); ?></label>
				</th>
				<td>
					<input type="text" name="cartshare_email_header_text" id="cartshare_email_header_text"
							value="<?php echo $opt( 'cartshare_email_header_text', '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Someone shared a cart with you', 'cartshare' ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="cartshare_email_footer_text"><?php esc_html_e( 'Email Footer Text', 'cartshare' ); ?></label>
				</th>
				<td>
					<textarea name="cartshare_email_footer_text" id="cartshare_email_footer_text"
								rows="3" class="large-text"><?php echo esc_textarea( get_option( 'cartshare_email_footer_text', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Optional text shown at the bottom of share emails.', 'cartshare' ); ?></p>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Email Settings', 'cartshare' ) ); ?>
	</form>
	<?php endif; ?>

	<!-- ======================================================
		APPEARANCE TAB
		====================================================== -->
	<?php if ( 'appearance' === $active_tab ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cartshare-settings-form">
		<?php wp_nonce_field( 'cartshare_save_settings' ); ?>
		<input type="hidden" name="action" value="cartshare_save_settings">
		<input type="hidden" name="cartshare_tab" value="appearance">

		<h2><?php esc_html_e( 'Popup Colors', 'cartshare' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="cartshare_popup_header_bg"><?php esc_html_e( 'Header Background', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_popup_header_bg" id="cartshare_popup_header_bg"
							value="<?php echo $opt( 'cartshare_popup_header_bg', '#4f46e5' ); ?>"
							class="cartshare-color-picker" data-default-color="#4f46e5">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartshare_popup_header_text"><?php esc_html_e( 'Header Text', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_popup_header_text" id="cartshare_popup_header_text"
							value="<?php echo $opt( 'cartshare_popup_header_text', '#ffffff' ); ?>"
							class="cartshare-color-picker" data-default-color="#ffffff">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartshare_popup_body_bg"><?php esc_html_e( 'Body Background', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_popup_body_bg" id="cartshare_popup_body_bg"
							value="<?php echo $opt( 'cartshare_popup_body_bg', '#ffffff' ); ?>"
							class="cartshare-color-picker" data-default-color="#ffffff">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartshare_popup_body_text"><?php esc_html_e( 'Body Text', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_popup_body_text" id="cartshare_popup_body_text"
							value="<?php echo $opt( 'cartshare_popup_body_text', '#111827' ); ?>"
							class="cartshare-color-picker" data-default-color="#111827">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartshare_popup_footer_bg"><?php esc_html_e( 'Footer Background', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_popup_footer_bg" id="cartshare_popup_footer_bg"
							value="<?php echo $opt( 'cartshare_popup_footer_bg', '#f9fafb' ); ?>"
							class="cartshare-color-picker" data-default-color="#f9fafb">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartshare_popup_footer_text"><?php esc_html_e( 'Footer Text', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_popup_footer_text" id="cartshare_popup_footer_text"
							value="<?php echo $opt( 'cartshare_popup_footer_text', '#6b7280' ); ?>"
							class="cartshare-color-picker" data-default-color="#6b7280">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartshare_popup_overlay_color"><?php esc_html_e( 'Overlay Color', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_popup_overlay_color" id="cartshare_popup_overlay_color"
							value="<?php echo $opt( 'cartshare_popup_overlay_color', '#000000' ); ?>"
							class="cartshare-color-picker" data-default-color="#000000">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartshare_popup_overlay_opacity"><?php esc_html_e( 'Overlay Opacity', 'cartshare' ); ?></label></th>
				<td>
					<input type="number" name="cartshare_popup_overlay_opacity" id="cartshare_popup_overlay_opacity"
							value="<?php echo $opt( 'cartshare_popup_overlay_opacity', '0.5' ); ?>"
							min="0" max="1" step="0.05" class="small-text">
					<p class="description"><?php esc_html_e( '0 = transparent, 1 = fully opaque.', 'cartshare' ); ?></p>
				</td>
			</tr>

		</table>

		<h2><?php esc_html_e( 'Labels & Text', 'cartshare' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="cartshare_button_label"><?php esc_html_e( 'Trigger Button Label', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_button_label" id="cartshare_button_label"
							value="<?php echo $opt( 'cartshare_button_label', '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Save & Share Cart', 'cartshare' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartshare_popup_title"><?php esc_html_e( 'Popup Title', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_popup_title" id="cartshare_popup_title"
							value="<?php echo $opt( 'cartshare_popup_title', '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Save & Share Your Cart', 'cartshare' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartshare_label_save"><?php esc_html_e( '"Save" Button Label', 'cartshare' ); ?></label></th>
				<td>
					<input type="text" name="cartshare_label_save" id="cartshare_label_save"
							value="<?php echo $opt( 'cartshare_label_save', '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Save Cart', 'cartshare' ); ?>">
				</td>
			</tr>

			<?php
			$channel_label_map = array(
				'copy'      => __( '"Copy Link" Button Label', 'cartshare' ),
				'print'     => __( '"Print" Button Label', 'cartshare' ),
				'email'     => __( '"Email" Button Label', 'cartshare' ),
				'facebook'  => __( '"Facebook" Button Label', 'cartshare' ),
				'messenger' => __( '"Messenger" Button Label', 'cartshare' ),
				'whatsapp'  => __( '"WhatsApp" Button Label', 'cartshare' ),
				'twitter'   => __( '"X / Twitter" Button Label', 'cartshare' ),
				'linkedin'  => __( '"LinkedIn" Button Label', 'cartshare' ),
				'skype'     => __( '"Skype" Button Label', 'cartshare' ),
			);
			foreach ( $channel_label_map as $slug => $label_name ) :
				$field = 'cartshare_label_' . $slug;
				?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label_name ); ?></label></th>
				<td>
					<input type="text" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>"
							value="<?php echo $opt( $field, '' ); ?>"
							class="regular-text">
				</td>
			</tr>
			<?php endforeach; ?>

		</table>

		<?php submit_button( __( 'Save Appearance Settings', 'cartshare' ) ); ?>
	</form>
	<?php endif; ?>

	<!-- ======================================================
		HISTORY TAB
		====================================================== -->
	<?php if ( 'history' === $active_tab ) : ?>
		<?php
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$offset      = 0;
		$history     = $admin_obj->get_history_page( $current_page, $offset );
		$rows        = $history['rows'];
		$total       = $history['total'];
		$total_pages = $history['pages'];
		?>

		<div class="cartshare-history-header">
			<h2><?php esc_html_e( 'Cart History', 'cartshare' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: %d: total number of saved carts */
					esc_html__( '%d saved carts in total.', 'cartshare' ),
					(int) $total
				);
				?>
			</p>
		</div>

		<?php if ( empty( $rows ) ) : ?>
			<p><?php esc_html_e( 'No saved carts found.', 'cartshare' ); ?></p>
		<?php else : ?>

		<table class="wp-list-table widefat fixed striped cartshare-history-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Cart ID', 'cartshare' ); ?></th>
					<th><?php esc_html_e( 'Customer / Guest', 'cartshare' ); ?></th>
					<th><?php esc_html_e( 'Created', 'cartshare' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'cartshare' ); ?></th>
					<th><?php esc_html_e( 'Items', 'cartshare' ); ?></th>
					<th><?php esc_html_e( 'Token Preview', 'cartshare' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'cartshare' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) :
					$cart_data     = json_decode( $row['cart_data'], true );
					$item_count    = is_array( $cart_data ) && isset( $cart_data['items'] ) ? count( $cart_data['items'] ) : 0;
					$token         = $row['token'];
					$token_preview = substr( $token, 0, 8 ) . '…';
					$owner         = '';
					if ( ! empty( $row['user_id'] ) ) {
						$user  = get_userdata( (int) $row['user_id'] );
						$owner = $user ? ( $user->user_login . ' (#' . $row['user_id'] . ')' ) : ( '#' . $row['user_id'] );
					} elseif ( ! empty( $row['guest_id'] ) ) {
						/* translators: guest identifier prefix in history table */
						$owner = __( 'Guest: ', 'cartshare' ) . substr( $row['guest_id'], 0, 12 ) . '…';
					} else {
						$owner = __( '(Unknown)', 'cartshare' );
					}
					$created    = ! empty( $row['created_at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] ) ) : '—';
					$expires    = ! empty( $row['expires_at'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $row['expires_at'] ) ) : esc_html__( 'Never', 'cartshare' );
					$is_expired = ! empty( $row['expires_at'] ) && strtotime( $row['expires_at'] ) < time();
					$share_url  = rest_url( 'cartshare/v1/restore/' . $token );
					?>
				<tr class="<?php echo esc_attr( $is_expired ? 'cartshare-expired' : '' ); ?>">
					<td><?php echo esc_html( $row['id'] ); ?></td>
					<td><?php echo esc_html( $owner ); ?></td>
					<td><?php echo esc_html( $created ); ?></td>
					<td>
						<?php echo esc_html( $expires ); ?>
						<?php if ( $is_expired ) : ?>
							<span class="cartshare-badge cartshare-badge--expired"><?php esc_html_e( 'Expired', 'cartshare' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $item_count ); ?></td>
					<td>
						<code title="<?php echo esc_attr( $token ); ?>"><?php echo esc_html( $token_preview ); ?></code>
					</td>
					<td class="cartshare-history-actions">
						<!-- Copy share link -->
						<button type="button" class="button button-small cartshare-copy-link"
								data-url="<?php echo esc_attr( $share_url ); ?>">
							<?php esc_html_e( 'Copy Link', 'cartshare' ); ?>
						</button>

						<!-- Restore for testing -->
						<a href="<?php echo esc_url( $share_url ); ?>" target="_blank"
							class="button button-small">
							<?php esc_html_e( 'Restore', 'cartshare' ); ?>
						</a>

						<!-- Delete -->
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								class="cartshare-delete-form" style="display:inline;">
							<?php wp_nonce_field( 'cartshare_delete_history' ); ?>
							<input type="hidden" name="action" value="cartshare_delete_history">
							<input type="hidden" name="cartshare_token" value="<?php echo esc_attr( $token ); ?>">
							<button type="submit" class="button button-small button-link-delete cartshare-delete-cart">
								<?php esc_html_e( 'Delete', 'cartshare' ); ?>
							</button>
						</form>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %d: number of items */
						esc_html__( '%d items', 'cartshare' ),
						(int) $total
					);
					?>
				</span>
				<span class="pagination-links">
						<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
							<?php if ( $p === $current_page ) : ?>
								<span class="page-numbers current"><?php echo (int) $p; ?></span>
							<?php else : ?>
								<?php
								$page_url = add_query_arg(
									array(
										'page'  => 'cartshare-settings',
										'tab'   => 'history',
										'paged' => $p,
									),
									admin_url( 'admin.php' )
								);
								?>
								<a href="<?php echo esc_url( $page_url ); ?>"
									class="page-numbers"><?php echo (int) $p; ?></a>
							<?php endif; ?>
						<?php endfor; ?>
				</span>
			</div>
		</div>
		<?php endif; ?>

		<?php endif; // end if empty rows. ?>
	<?php endif; // end history tab. ?>

</div><!-- .cartshare-admin -->
<?php // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped ?>
