<?php
/**
 * CartShare popup modal markup.
 *
 * Included by CartShare_Frontend::render_popup() in wp_footer on cart,
 * checkout, and account pages.  All dynamic output is escaped.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	id="cartshare-popup"
	class="cartshare-popup-overlay"
	role="dialog"
	aria-modal="true"
	aria-label="<?php esc_attr_e( 'Save &amp; Share Cart', 'cartshare' ); ?>"
	aria-hidden="true"
	tabindex="-1"
>
	<div class="cartshare-popup-dialog">

		<!-- Header -->
		<div class="cartshare-popup-header">
			<h2 class="cartshare-popup-title">
				<?php esc_html_e( 'Save &amp; Share Cart', 'cartshare' ); ?>
			</h2>
			<button
				type="button"
				class="cartshare-popup-close"
				aria-label="<?php esc_attr_e( 'Close', 'cartshare' ); ?>"
			>
				<span aria-hidden="true">&times;</span>
			</button>
		</div><!-- /.cartshare-popup-header -->

		<!-- Body -->
		<div class="cartshare-popup-body">

			<!-- Status / messages -->
			<div class="cartshare-status" aria-live="polite" aria-atomic="true"></div>

			<!-- Share-link row (shown after a cart is saved) -->
			<div class="cartshare-share-link-row" hidden>
				<label for="cartshare-share-url" class="screen-reader-text">
					<?php esc_html_e( 'Share URL', 'cartshare' ); ?>
				</label>
				<input
					id="cartshare-share-url"
					type="url"
					class="cartshare-share-url"
					readonly
					aria-readonly="true"
					value=""
				/>
				<button type="button" class="cartshare-copy-btn button">
					<?php esc_html_e( 'Copy Link', 'cartshare' ); ?>
				</button>
			</div><!-- /.cartshare-share-link-row -->

			<!-- Channel buttons -->
			<div class="cartshare-channels" role="group" aria-label="<?php esc_attr_e( 'Sharing channels', 'cartshare' ); ?>">

				<!-- Save / generate link (always first) -->
				<button type="button" class="cartshare-channel-btn cartshare-btn-save" data-channel="save">
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'Save Cart', 'cartshare' ); ?></span>
				</button>

				<!-- Copy Link -->
				<button type="button" class="cartshare-channel-btn cartshare-btn-copy" data-channel="copy_link" hidden>
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'Copy Link', 'cartshare' ); ?></span>
				</button>

				<!-- Email -->
				<button type="button" class="cartshare-channel-btn cartshare-btn-email" data-channel="email" hidden>
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'Email', 'cartshare' ); ?></span>
				</button>

				<!-- Print -->
				<button type="button" class="cartshare-channel-btn cartshare-btn-print" data-channel="print" hidden>
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'Print', 'cartshare' ); ?></span>
				</button>

				<!-- Facebook -->
				<a
					href="#"
					class="cartshare-channel-btn cartshare-btn-facebook"
					data-channel="facebook"
					data-share-url=""
					target="_blank"
					rel="noopener noreferrer"
					hidden
				>
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'Facebook', 'cartshare' ); ?></span>
				</a>

				<!-- Messenger -->
				<a
					href="#"
					class="cartshare-channel-btn cartshare-btn-messenger"
					data-channel="messenger"
					data-share-url=""
					target="_blank"
					rel="noopener noreferrer"
					hidden
				>
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M12 2C6.477 2 2 6.145 2 11.25c0 2.826 1.323 5.35 3.4 7.07V22l3.1-1.706A10.6 10.6 0 0 0 12 20.5c5.523 0 10-4.145 10-9.25S17.523 2 12 2zm1.07 12.448-2.554-2.72-4.984 2.72 5.484-5.824 2.614 2.72 4.924-2.72-5.484 5.824z"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'Messenger', 'cartshare' ); ?></span>
				</a>

				<!-- WhatsApp -->
				<a
					href="#"
					class="cartshare-channel-btn cartshare-btn-whatsapp"
					data-channel="whatsapp"
					data-share-url=""
					target="_blank"
					rel="noopener noreferrer"
					hidden
				>
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'WhatsApp', 'cartshare' ); ?></span>
				</a>

				<!-- X / Twitter -->
				<a
					href="#"
					class="cartshare-channel-btn cartshare-btn-twitter"
					data-channel="twitter"
					data-share-url=""
					target="_blank"
					rel="noopener noreferrer"
					hidden
				>
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'X / Twitter', 'cartshare' ); ?></span>
				</a>

				<!-- LinkedIn -->
				<a
					href="#"
					class="cartshare-channel-btn cartshare-btn-linkedin"
					data-channel="linkedin"
					data-share-url=""
					target="_blank"
					rel="noopener noreferrer"
					hidden
				>
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'LinkedIn', 'cartshare' ); ?></span>
				</a>

				<!-- Skype -->
				<a
					href="#"
					class="cartshare-channel-btn cartshare-btn-skype"
					data-channel="skype"
					data-share-url=""
					target="_blank"
					rel="noopener noreferrer"
					hidden
				>
					<span class="cartshare-channel-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M22.986 13.5A10.985 10.985 0 0 1 12 24a10.98 10.98 0 0 1-6.134-1.87A6.482 6.482 0 0 1 1 16.5a6.45 6.45 0 0 1 1.366-3.971A11 11 0 0 1 2 11 10.985 10.985 0 0 1 12.988 0 10.978 10.978 0 0 1 19 1.878 6.5 6.5 0 0 1 22.986 13.5zM12 19.007c3.22 0 5.254-1.59 5.254-4.02 0-1.71-1.058-3.12-3.044-3.753l-2.396-.742c-.887-.28-1.55-.593-1.55-1.22 0-.72.64-1.18 1.694-1.18 1.827 0 1.972 1.218 3.008 1.218.569 0 1.054-.3 1.054-.89 0-1.447-2.162-2.496-4.025-2.496-2.748 0-4.876 1.39-4.876 3.83 0 1.667 1.076 2.95 3.1 3.583l2.365.73c1.063.33 1.508.71 1.508 1.36 0 .777-.7 1.307-1.94 1.307-2.12 0-2.34-1.538-3.565-1.538-.603 0-1.054.36-1.054.968 0 1.22 1.742 2.843 4.467 2.843z"/></svg>
					</span>
					<span class="cartshare-channel-label"><?php esc_html_e( 'Skype', 'cartshare' ); ?></span>
				</a>

			</div><!-- /.cartshare-channels -->

			<!-- Email sub-form (shown when email channel is activated) -->
			<div class="cartshare-email-form" hidden>
				<p>
					<label for="cartshare-email-recipient">
						<?php esc_html_e( 'Recipient email', 'cartshare' ); ?>
					</label>
					<input
						id="cartshare-email-recipient"
						type="email"
						class="cartshare-email-recipient"
						autocomplete="email"
					/>
				</p>
				<p>
					<label for="cartshare-email-message">
						<?php esc_html_e( 'Message (optional)', 'cartshare' ); ?>
					</label>
					<textarea
						id="cartshare-email-message"
						class="cartshare-email-message"
						rows="3"
					></textarea>
				</p>
				<button type="button" class="cartshare-email-send button alt">
					<?php esc_html_e( 'Send', 'cartshare' ); ?>
				</button>
				<button type="button" class="cartshare-email-cancel button">
					<?php esc_html_e( 'Cancel', 'cartshare' ); ?>
				</button>
			</div><!-- /.cartshare-email-form -->

		</div><!-- /.cartshare-popup-body -->

	</div><!-- /.cartshare-popup-dialog -->
</div><!-- /#cartshare-popup -->
