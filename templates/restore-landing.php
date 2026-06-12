<?php
/**
 * CartShare restore-cart landing page template.
 *
 * Served when a visitor arrives at /?cartshare_restore={token}.
 * Provides a "Restore Cart" confirmation screen before any cart data is
 * modified — if the visitor's current cart is non-empty they see a warning
 * that their existing items will be replaced (E2E scenario 6).
 *
 * Variables available from CartShare_Frontend::maybe_restore_cart():
 *   $cart_row  array|null  DB row from cartshare_carts (null if not found/expired).
 *   $token     string      The validated 32-char alphanumeric token.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name = get_bloginfo( 'name' );
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php printf( /* translators: %s: site name */ esc_html__( 'Restore Cart - %s', 'cartshare' ), esc_html( $site_name ) ); ?></title>
	<style>
		*, *::before, *::after { box-sizing: border-box; }

		body {
			margin: 0;
			background: #f6f7f9;
			color: #1f2933;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
			font-size: 16px;
			line-height: 1.5;
		}

		a { color: #0a66c2; }

		.cartshare-restore-page {
			min-height: 100vh;
			padding: 48px 20px;
		}

		.cartshare-restore-landing {
			width: 100%;
			max-width: 640px;
			margin: 0 auto;
			padding: 32px;
			background: #fff;
			border: 1px solid #d8dee4;
			border-radius: 8px;
			box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
		}

		.cartshare-restore-heading {
			margin: 0 0 16px;
			color: #111827;
			font-size: 32px;
			line-height: 1.15;
		}

		.cartshare-restore-cart-name,
		.cartshare-restore-copy {
			margin: 0 0 20px;
			color: #4b5563;
		}

		.woocommerce-info,
		.cartshare-restore-notice {
			margin: 0 0 24px;
			padding: 16px 18px;
			background: #eff6ff;
			border-left: 4px solid #2271b1;
			color: #1f2937;
		}

		.cartshare-restore-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 12px;
			align-items: center;
		}

		.button,
		button.button {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 42px;
			padding: 10px 16px;
			border: 1px solid #1d4ed8;
			border-radius: 4px;
			background: #1d4ed8;
			color: #fff;
			font: inherit;
			font-weight: 600;
			line-height: 1.2;
			text-decoration: none;
			cursor: pointer;
		}

		.button:not(.alt) {
			background: #fff;
			color: #1d4ed8;
		}

		@media (max-width: 600px) {
			.cartshare-restore-page { padding: 24px 12px; }
			.cartshare-restore-landing { padding: 24px; }
			.cartshare-restore-heading { font-size: 28px; }
			.cartshare-restore-actions { align-items: stretch; flex-direction: column; }
			.button,
			button.button { width: 100%; }
		}
	</style>
</head>
<body>

<main class="cartshare-restore-page">
<div class="cartshare-restore-landing">

	<?php if ( null === $cart_row ) : ?>

		<div class="woocommerce-info">
			<?php esc_html_e( 'This cart link is invalid or has expired. Please ask the sender to share a new link.', 'cartshare' ); ?>
		</div>

		<p>
			<a href="<?php echo esc_url( wc_get_shop_url() ); ?>" class="button">
				<?php esc_html_e( 'Continue Shopping', 'cartshare' ); ?>
			</a>
		</p>

	<?php else : ?>

		<?php
		$cart_name = ! empty( $cart_row['name'] ) ? $cart_row['name'] : '';

		// Detect whether the visitor already has items in their cart so we can
		// warn them before overwriting (E2E scenario 6: non-empty cart confirmation).
		$is_non_empty = false;
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( null !== WC()->cart && ! WC()->cart->is_empty() ) {
			$is_non_empty = true;
		}

		// Build the form action URL (current page URL with the token parameter).
		$form_action = add_query_arg( 'cartshare_restore', esc_attr( $token ), home_url( '/' ) );
		?>

		<h1 class="cartshare-restore-heading">
			<?php esc_html_e( 'Someone shared a cart with you!', 'cartshare' ); ?>
		</h1>

		<?php if ( $cart_name ) : ?>
			<p class="cartshare-restore-cart-name">
				<?php
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $cart_name is esc_html()-escaped; surrounding <strong> tags are safe static HTML.
				printf(
					/* translators: %s: saved cart name wrapped in <strong> */
					esc_html__( 'Cart: %s', 'cartshare' ),
					'<strong>' . esc_html( $cart_name ) . '</strong>'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</p>
		<?php endif; ?>

		<p class="cartshare-restore-copy">
			<?php esc_html_e( 'Click the button below to load this cart and start shopping.', 'cartshare' ); ?>
		</p>

		<?php if ( $is_non_empty ) : ?>
			<div class="cartshare-restore-notice">
				<strong><?php esc_html_e( 'Heads up!', 'cartshare' ); ?></strong>
				<?php esc_html_e( 'Restoring this shared cart will replace your current cart items. Your existing items will be removed.', 'cartshare' ); ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="cartshare-restore-form">
			<?php wp_nonce_field( 'cartshare_restore_' . $token ); ?>

			<div class="cartshare-restore-actions">
				<button type="submit" class="button alt">
					<?php
					if ( $is_non_empty ) {
						esc_html_e( 'Replace My Cart & Restore', 'cartshare' );
					} else {
						esc_html_e( 'Restore Cart', 'cartshare' );
					}
					?>
				</button>

				<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'cartshare' ); ?>
				</a>
			</div>
		</form>

	<?php endif; ?>

</div><!-- /.cartshare-restore-landing -->
</main>

</body>
</html>
