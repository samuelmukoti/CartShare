<?php
/**
 * My Account — Saved Carts tab template.
 *
 * Renders the table of saved carts for the logged-in user.
 * Provides Restore and Delete action buttons protected by nonces.
 *
 * Variables available from CartShare_MyAccount::render_tab():
 *   $saved_carts  array  List of cart rows (associative arrays) for the current user.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="cartshare-myaccount-saved-carts">

	<?php if ( empty( $saved_carts ) ) : ?>

		<p class="cartshare-no-carts woocommerce-message">
			<?php esc_html_e( 'You have no saved carts yet. Add items to your cart and click "Save &amp; Share Cart" to save one.', 'cartshare' ); ?>
		</p>

	<?php else : ?>

		<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive cartshare-saved-carts-table">
			<thead>
				<tr>
					<th class="cartshare-col-name"><?php esc_html_e( 'Cart Name', 'cartshare' ); ?></th>
					<th class="cartshare-col-date"><?php esc_html_e( 'Saved', 'cartshare' ); ?></th>
					<th class="cartshare-col-expires"><?php esc_html_e( 'Expires', 'cartshare' ); ?></th>
					<th class="cartshare-col-actions"><?php esc_html_e( 'Actions', 'cartshare' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $saved_carts as $cart_row ) : ?>
					<?php
					$token     = esc_attr( $cart_row['token'] );
					$cart_name = ! empty( $cart_row['name'] )
						? $cart_row['name']
						: __( '(Unnamed)', 'cartshare' );

					$created_ts  = strtotime( $cart_row['created_at'] );
					$created_str = $created_ts
						? date_i18n( get_option( 'date_format' ), $created_ts )
						: '—';

					$expires_str = '—';
					if ( ! empty( $cart_row['expires_at'] ) ) {
						$expires_ts  = strtotime( $cart_row['expires_at'] );
						$expires_str = $expires_ts
							? date_i18n( get_option( 'date_format' ), $expires_ts )
							: '—';
					}

					$is_expired = ! empty( $cart_row['expires_at'] )
						&& strtotime( $cart_row['expires_at'] ) < time();
					?>
					<tr class="<?php echo esc_attr( 'cartshare-saved-cart-row' . ( $is_expired ? ' cartshare-expired' : '' ) ); ?>">
						<td class="cartshare-col-name" data-title="<?php esc_attr_e( 'Cart Name', 'cartshare' ); ?>">
							<?php echo esc_html( $cart_name ); ?>
							<?php if ( $is_expired ) : ?>
								<span class="cartshare-expired-badge">
									<?php esc_html_e( '(Expired)', 'cartshare' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="cartshare-col-date" data-title="<?php esc_attr_e( 'Saved', 'cartshare' ); ?>">
							<?php echo esc_html( $created_str ); ?>
						</td>
						<td class="cartshare-col-expires" data-title="<?php esc_attr_e( 'Expires', 'cartshare' ); ?>">
							<?php echo esc_html( $expires_str ); ?>
						</td>
						<td class="cartshare-col-actions" data-title="<?php esc_attr_e( 'Actions', 'cartshare' ); ?>">

							<?php if ( ! $is_expired ) : ?>
								<!-- Restore form -->
								<form method="post" class="cartshare-action-form cartshare-restore-form">
									<input type="hidden" name="cartshare_action" value="restore">
									<input type="hidden" name="cartshare_token" value="<?php echo esc_attr( $token ); ?>">
									<?php wp_nonce_field( 'cartshare_myaccount_restore' ); ?>
									<button
										type="submit"
										class="button cartshare-btn-restore"
									><?php esc_html_e( 'Restore', 'cartshare' ); ?></button>
								</form>
							<?php endif; ?>

							<!-- Delete form -->
							<form method="post" class="cartshare-action-form cartshare-delete-form">
								<input type="hidden" name="cartshare_action" value="delete">
								<input type="hidden" name="cartshare_token" value="<?php echo esc_attr( $token ); ?>">
								<?php wp_nonce_field( 'cartshare_myaccount_delete' ); ?>
								<button
									type="submit"
									class="button cartshare-btn-delete"
									onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this cart?', 'cartshare' ) ); ?>')"
								><?php esc_html_e( 'Delete', 'cartshare' ); ?></button>
							</form>

						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php endif; ?>

</div>
