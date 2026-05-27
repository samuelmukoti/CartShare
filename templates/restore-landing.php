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

get_header();
?>

<div id="primary" class="content-area">
<main id="main" class="site-main">

<div class="cartshare-restore-landing woocommerce" style="max-width:640px;margin:40px auto;padding:0 20px;">

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

		<h1 class="cartshare-restore-heading" style="margin-bottom:16px;">
			<?php esc_html_e( 'Someone shared a cart with you!', 'cartshare' ); ?>
		</h1>

		<?php if ( $cart_name ) : ?>
			<p class="cartshare-restore-cart-name" style="font-size:1.05em;margin-bottom:20px;">
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

		<p style="color:#555;margin-bottom:24px;">
			<?php esc_html_e( 'Click the button below to load this cart and start shopping.', 'cartshare' ); ?>
		</p>

		<?php if ( $is_non_empty ) : ?>
			<div class="woocommerce-info" style="margin-bottom:24px;">
				<strong><?php esc_html_e( 'Heads up!', 'cartshare' ); ?></strong>
				<?php esc_html_e( 'Restoring this shared cart will replace your current cart items. Your existing items will be removed.', 'cartshare' ); ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="cartshare-restore-form">
			<?php wp_nonce_field( 'cartshare_restore_' . $token ); ?>

			<button type="submit" class="button alt" style="margin-right:12px;">
				<?php
				if ( $is_non_empty ) {
					esc_html_e( 'Replace My Cart &amp; Restore', 'cartshare' );
				} else {
					esc_html_e( 'Restore Cart', 'cartshare' );
				}
				?>
			</button>

			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="button">
				<?php esc_html_e( 'Cancel', 'cartshare' ); ?>
			</a>
		</form>

	<?php endif; ?>

</div><!-- /.cartshare-restore-landing -->

</main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
