<?php
/**
 * Email sender for CartShare.
 *
 * Handles rendering the HTML share-by-email template and dispatching it
 * via wp_mail() with a scoped text/html Content-Type filter.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Email
 *
 * Sends a share-by-email message using the templates/email-share.php template.
 * All inputs are sanitized before use; the Content-Type header is set only for
 * the duration of the wp_mail() call and is immediately restored afterwards.
 */
class CartShare_Email {

	/**
	 * Send a share-by-email message.
	 *
	 * Validates the recipient address, sanitizes every user-supplied value,
	 * renders the HTML template with placeholder substitution, temporarily
	 * hooks the text/html Content-Type filter, and delegates to wp_mail().
	 *
	 * @param string      $to          Recipient email address.
	 * @param string      $subject     Email subject line.
	 * @param string      $message     Optional personal message from the sender.
	 * @param string      $share_url   The tokenized cart restore URL.
	 * @param string|null $sender_name Display name of the person sharing the cart.
	 * @return bool True on success, false on failure.
	 */
	public function send(
		string $to,
		string $subject,
		string $message,
		string $share_url,
		?string $sender_name
	): bool {
		// Validate recipient address — bail early on invalid email.
		if ( ! is_email( $to ) ) {
			return false;
		}

		// Sanitize all user-supplied values before use.
		$to          = sanitize_email( $to );
		$subject     = sanitize_text_field( $subject );
		$message     = sanitize_textarea_field( $message );
		$share_url   = esc_url_raw( $share_url );
		$sender_name = $sender_name ? sanitize_text_field( $sender_name ) : get_bloginfo( 'name' );

		// Fall back to a sensible default subject if empty.
		if ( '' === $subject ) {
			$subject = sprintf(
				/* translators: %s: sender display name */
				__( '%s shared a cart with you', 'cartshare' ),
				$sender_name
			);
		}

		// Build the HTML body from the template.
		$body = $this->render_template( $share_url, $message, $sender_name );

		if ( '' === $body ) {
			return false;
		}

		// Set Content-Type to text/html for this call only, then remove it.
		$set_html_content_type = static function () {
			return 'text/html';
		};

		add_filter( 'wp_mail_content_type', $set_html_content_type );

		$sent = wp_mail( $to, $subject, $body );

		remove_filter( 'wp_mail_content_type', $set_html_content_type );

		return $sent;
	}

	/**
	 * Render the HTML email template with placeholder substitution.
	 *
	 * Captures the output of templates/email-share.php via output buffering,
	 * then replaces {sender}, {message}, {share_url}, and {logo} tokens.
	 * The <!-- BEGIN:message --> / <!-- END:message --> conditional block is
	 * stripped when the personal message is empty.
	 *
	 * @param string $share_url   The tokenized cart restore URL.
	 * @param string $message     Optional personal message from the sender.
	 * @param string $sender_name Display name of the person sharing the cart.
	 * @return string Rendered HTML, or empty string if the template is missing.
	 */
	private function render_template(
		string $share_url,
		string $message,
		string $sender_name
	): string {
		$template_path = CARTSHARE_PATH . 'templates/email-share.php';

		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		// Logo URL from admin options (empty string when not configured).
		$logo_url = esc_url_raw( get_option( 'cartshare_email_logo_url', '' ) );

		ob_start();
		include $template_path;
		$html = ob_get_clean();

		if ( false === $html ) {
			return '';
		}

		// Replace scalar placeholders.
		$html = str_replace(
			array( '{sender}', '{share_url}', '{logo}' ),
			array(
				esc_html( $sender_name ),
				esc_url( $share_url ),
				esc_url( $logo_url ),
			),
			$html
		);

		// Handle the optional personal-message block.
		if ( '' !== $message ) {
			// Replace {message} inside the block, then strip the block markers.
			$html = str_replace( '{message}', esc_html( $message ), $html );
			$html = str_replace( '<!-- BEGIN:message -->', '', $html );
			$html = str_replace( '<!-- END:message -->', '', $html );
		} else {
			// Remove the entire conditional block including its content.
			$html = preg_replace(
				'/<!--\s*BEGIN:message\s*-->.*?<!--\s*END:message\s*-->/s',
				'',
				$html
			);
		}

		return $html;
	}
}
