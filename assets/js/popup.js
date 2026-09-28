/**
 * CartShare popup JavaScript.
 *
 * Handles: open/close, saving a cart via the REST API, copy-to-clipboard,
 * social share intent URLs, email sub-form, and the block-cart button
 * registration (no JSX / no build step required).
 *
 * All DOM references are scoped to the #cartshare-popup element.
 * The global `CartShareData` object is provided by wp_localize_script().
 *
 * @package CartShare
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------
	   Guard: data must be present (localized by CartShare_Frontend)
	------------------------------------------------------------------ */
	var data = window.CartShareData;
	if ( ! data ) {
		return;
	}

	/* ------------------------------------------------------------------
	   Apply CSS custom properties from admin colour settings
	------------------------------------------------------------------ */
	if ( data.colors ) {
		var root = document.documentElement;
		root.style.setProperty( '--cartshare-primary',     data.colors.primary    || '#4f46e5' );
		root.style.setProperty( '--cartshare-button-bg',   data.colors.buttonBg   || '#4f46e5' );
		root.style.setProperty( '--cartshare-button-text', data.colors.buttonText || '#ffffff' );
	}

	/* ------------------------------------------------------------------
	   Show only the channels that are enabled in admin settings
	------------------------------------------------------------------ */
	var enabledChannels = data.channels || [];

	function showEnabledChannels() {
		var popup = document.getElementById( 'cartshare-popup' );
		if ( ! popup ) { return; }

		// Map channel slugs to the data-channel attribute values used in the template.
		enabledChannels.forEach( function ( slug ) {
			var btn = popup.querySelector( '[data-channel="' + slug + '"]' );
			if ( btn ) {
				btn.removeAttribute( 'hidden' );
			}
		} );
	}

	/* ------------------------------------------------------------------
	   Popup open / close
	------------------------------------------------------------------ */
	var lastFocus = null;

	/**
	 * Open the share popup.
	 *
	 * @return {void}
	 */
	function openPopup() {
		var overlay = document.getElementById( 'cartshare-popup' );
		if ( ! overlay ) { return; }

		lastFocus = document.activeElement;
		overlay.classList.add( 'is-open' );
		overlay.setAttribute( 'aria-hidden', 'false' );

		// Move focus to the dialog for keyboard/AT users.
		var dialog = overlay.querySelector( '.cartshare-popup-dialog' );
		if ( dialog ) {
			dialog.setAttribute( 'tabindex', '-1' );
			dialog.focus();
		}

		document.addEventListener( 'keydown', handleKeyDown );
	}

	/**
	 * Close the share popup and restore focus.
	 *
	 * @return {void}
	 */
	function closePopup() {
		var overlay = document.getElementById( 'cartshare-popup' );
		if ( ! overlay ) { return; }

		overlay.classList.remove( 'is-open' );
		overlay.setAttribute( 'aria-hidden', 'true' );
		document.removeEventListener( 'keydown', handleKeyDown );

		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	/**
	 * Keyboard handler: Escape closes the popup.
	 *
	 * @param {KeyboardEvent} e
	 * @return {void}
	 */
	function handleKeyDown( e ) {
		if ( e.key === 'Escape' || e.keyCode === 27 ) {
			closePopup();
		}

		// Basic focus trap inside the dialog.
		if ( e.key === 'Tab' ) {
			trapFocus( e );
		}
	}

	/**
	 * Trap keyboard focus within the popup dialog.
	 *
	 * @param {KeyboardEvent} e
	 * @return {void}
	 */
	function trapFocus( e ) {
		var overlay  = document.getElementById( 'cartshare-popup' );
		if ( ! overlay ) { return; }

		var focusable = overlay.querySelectorAll(
			'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
		);

		if ( ! focusable.length ) { return; }

		var first = focusable[ 0 ];
		var last  = focusable[ focusable.length - 1 ];

		if ( e.shiftKey ) {
			if ( document.activeElement === first ) {
				last.focus();
				e.preventDefault();
			}
		} else {
			if ( document.activeElement === last ) {
				first.focus();
				e.preventDefault();
			}
		}
	}

	/* ------------------------------------------------------------------
	   Status / notice helpers
	------------------------------------------------------------------ */

	/**
	 * Display a status message inside the popup.
	 *
	 * @param {string} message
	 * @param {string} type    'info' | 'success' | 'error'
	 * @return {void}
	 */
	function setStatus( message, type ) {
		var el = document.querySelector( '#cartshare-popup .cartshare-status' );
		if ( ! el ) { return; }

		el.className  = 'cartshare-status is-' + ( type || 'info' );
		el.textContent = message;
	}

	/**
	 * Clear the status notice.
	 *
	 * @return {void}
	 */
	function clearStatus() {
		var el = document.querySelector( '#cartshare-popup .cartshare-status' );
		if ( el ) {
			el.className   = 'cartshare-status';
			el.textContent = '';
		}
	}

	/* ------------------------------------------------------------------
	   Save cart via REST API
	------------------------------------------------------------------ */

	/** @type {string|null} The share URL returned by the last successful save. */
	var currentShareUrl = null;

	/**
	 * POST to cartshare/v1/save and return the share URL.
	 *
	 * @param {string} [cartName]  Optional cart name from the user.
	 * @return {Promise<string>}   Resolves with the share URL.
	 */
	function saveCart( cartName ) {
		var endpoint = data.restUrl + '/save';
		var body     = {};
		if ( cartName ) {
			body.name = cartName;
		}

		return fetch( endpoint, {
			method:  'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   data.nonce,
			},
			body: JSON.stringify( body ),
		} ).then( function ( response ) {
			return response.json().then( function ( json ) {
				if ( ! response.ok ) {
					throw new Error( ( json && json.message ) || ( data.labels && data.labels.errorGeneric ) );
				}
				return json.share_url;
			} );
		} );
	}

	/**
	 * Handle the Save Cart button click: call the API then update the UI.
	 *
	 * @return {void}
	 */
	function handleSave() {
		var saveBtn = document.querySelector( '#cartshare-popup .cartshare-btn-save' );
		if ( ! saveBtn ) { return; }

		clearStatus();
		saveBtn.disabled    = true;
		saveBtn.textContent = ( data.labels && data.labels.saving ) || 'Saving…';

		saveCart().then( function ( shareUrl ) {
			currentShareUrl = shareUrl;
			saveBtn.disabled    = false;
			saveBtn.textContent = ( data.labels && data.labels.save ) || 'Save Cart';

			// Show the share URL input row.
			var linkRow = document.querySelector( '#cartshare-popup .cartshare-share-link-row' );
			var urlInput = document.querySelector( '#cartshare-popup .cartshare-share-url' );
			if ( linkRow && urlInput ) {
				urlInput.value = shareUrl;
				linkRow.removeAttribute( 'hidden' );
			}

			// Wire share intent URLs.
			wireChannelLinks( shareUrl );

			// Show enabled channel buttons (except save which was already visible).
			showEnabledChannels();

			setStatus( ( data.labels && data.labels.copied ) || 'Cart saved!', 'success' );
		} ).catch( function ( err ) {
			saveBtn.disabled    = false;
			saveBtn.textContent = ( data.labels && data.labels.save ) || 'Save Cart';
			setStatus( err.message || ( data.labels && data.labels.errorGeneric ), 'error' );
		} );
	}

	/* ------------------------------------------------------------------
	   Social / channel share intent URLs
	------------------------------------------------------------------ */

	/**
	 * Map of channel slug → function that returns the intent URL.
	 *
	 * @param {string} url The encoded share URL.
	 * @return {Object}
	 */
	function getIntentUrls( url ) {
		var encoded  = encodeURIComponent( url );
		var message  = ( data.shareMessage ) ? data.shareMessage + ' ' : '';
		var waText   = encodeURIComponent( message + url );
		var twText   = encodeURIComponent( message.trim() );
		return {
			facebook:  'https://www.facebook.com/sharer/sharer.php?u=' + encoded,
			messenger: 'https://www.facebook.com/dialog/send?link=' + encoded + '&app_id=291494419107518&redirect_uri=' + encoded,
			whatsapp:  'https://wa.me/?text=' + waText,
			twitter:   'https://twitter.com/intent/tweet?url=' + encoded + ( twText ? '&text=' + twText : '' ),
			linkedin:  'https://www.linkedin.com/sharing/share-offsite/?url=' + encoded,
			skype:     'https://web.skype.com/share?url=' + encoded,
		};
	}

	/**
	 * Set href attributes on social link elements after the cart is saved.
	 *
	 * @param {string} shareUrl
	 * @return {void}
	 */
	function wireChannelLinks( shareUrl ) {
		var intents = getIntentUrls( shareUrl );
		var popup   = document.getElementById( 'cartshare-popup' );
		if ( ! popup ) { return; }

		Object.keys( intents ).forEach( function ( channel ) {
			var el = popup.querySelector( '[data-channel="' + channel + '"]' );
			if ( el ) {
				el.setAttribute( 'href', intents[ channel ] );
			}
		} );
	}

	/* ------------------------------------------------------------------
	   Copy to clipboard
	------------------------------------------------------------------ */

	/**
	 * Copy the share URL to the clipboard and update the button text briefly.
	 *
	 * @return {void}
	 */
	function handleCopy() {
		var urlInput = document.querySelector( '#cartshare-popup .cartshare-share-url' );
		var copyBtn  = document.querySelector( '#cartshare-popup .cartshare-copy-btn' );

		if ( ! urlInput || ! copyBtn ) { return; }

		var url = urlInput.value;
		if ( ! url ) { return; }

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( function () {
				showCopied( copyBtn );
			} );
		} else {
			// Fallback for older browsers.
			urlInput.select();
			document.execCommand( 'copy' );
			showCopied( copyBtn );
		}
	}

	/**
	 * Temporarily change the copy button label to "Copied!".
	 *
	 * @param {HTMLElement} btn
	 * @return {void}
	 */
	function showCopied( btn ) {
		var original = btn.textContent;
		btn.textContent = ( data.labels && data.labels.copied ) || 'Copied!';
		setTimeout( function () {
			btn.textContent = original;
		}, 2000 );
	}

	/* ------------------------------------------------------------------
	   Inline copy channel (separate from the link-row button)
	------------------------------------------------------------------ */

	/**
	 * Handle the copy_link channel button.
	 *
	 * @return {void}
	 */
	function handleChannelCopy() {
		if ( currentShareUrl ) {
			handleCopy();
		}
	}

	/* ------------------------------------------------------------------
	   Print channel
	------------------------------------------------------------------ */

	/**
	 * Open the cart print view in a new tab (or trigger window.print).
	 *
	 * @return {void}
	 */
	function handlePrint() {
		if ( currentShareUrl ) {
			// Build a print URL from the token embedded in the share URL.
			var token  = extractToken( currentShareUrl );
			var printUrl = ( data.siteUrl || window.location.origin ) + '/?cartshare_print=' + token;
			window.open( printUrl, '_blank' );
		}
	}

	/**
	 * Extract the 32-character token from a share URL.
	 *
	 * Handles two URL formats:
	 *  - Query-param:  /?cartshare_restore=TOKEN  (frontend restore landing page)
	 *  - Path-segment: /wp-json/cartshare/v1/restore/TOKEN  (legacy REST format)
	 *
	 * @param {string} url
	 * @return {string}
	 */
	function extractToken( url ) {
		// Prefer the query-param format used by the restore landing page.
		var match = url.match( /[?&]cartshare_restore=([A-Za-z0-9]{32})/ );
		if ( match ) {
			return match[ 1 ];
		}
		// Fallback: last path segment (covers any REST-style URL).
		var parts = url.split( '/' );
		return parts[ parts.length - 1 ] || '';
	}

	/* ------------------------------------------------------------------
	   Email sub-form
	------------------------------------------------------------------ */

	/**
	 * Show the email sub-form.
	 *
	 * @return {void}
	 */
	function showEmailForm() {
		var form = document.querySelector( '#cartshare-popup .cartshare-email-form' );
		if ( form ) {
			form.removeAttribute( 'hidden' );
			var input = form.querySelector( '.cartshare-email-recipient' );
			if ( input ) { input.focus(); }
		}
	}

	/**
	 * Hide the email sub-form.
	 *
	 * @return {void}
	 */
	function hideEmailForm() {
		var form = document.querySelector( '#cartshare-popup .cartshare-email-form' );
		if ( form ) {
			form.setAttribute( 'hidden', '' );
		}
	}

	/**
	 * Send the share email via the REST endpoint.
	 *
	 * @return {void}
	 */
	function handleEmailSend() {
		var recipientInput = document.querySelector( '#cartshare-popup .cartshare-email-recipient' );
		var messageInput   = document.querySelector( '#cartshare-popup .cartshare-email-message' );
		if ( ! recipientInput ) { return; }

		var recipient = recipientInput.value.trim();
		var message   = messageInput ? messageInput.value.trim() : '';

		if ( ! recipient ) {
			setStatus( data.labels && data.labels.errorGeneric || 'Please enter a recipient email.', 'error' );
			return;
		}

		var endpoint = data.restUrl + '/share/email';

		fetch( endpoint, {
			method:  'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   data.nonce,
			},
			body: JSON.stringify( {
				share_url: currentShareUrl,
				to:        recipient,
				message:   message,
			} ),
		} ).then( function ( response ) {
			return response.json().then( function ( json ) {
				if ( ! response.ok ) {
					throw new Error( ( json && json.message ) || ( data.labels && data.labels.errorGeneric ) );
				}
				setStatus( json.message || 'Email sent!', 'success' );
				hideEmailForm();
			} );
		} ).catch( function ( err ) {
			setStatus( err.message || ( data.labels && data.labels.errorGeneric ), 'error' );
		} );
	}

	/* ------------------------------------------------------------------
	   Event wiring
	------------------------------------------------------------------ */

	/**
	 * Wire all popup interactions after the DOM is ready.
	 *
	 * @return {void}
	 */
	function init() {
		var popup = document.getElementById( 'cartshare-popup' );
		if ( ! popup ) { return; }

		// Close button.
		var closeBtn = popup.querySelector( '.cartshare-popup-close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', closePopup );
		}

		// Click on overlay backdrop closes the popup.
		popup.addEventListener( 'click', function ( e ) {
			if ( e.target === popup ) {
				closePopup();
			}
		} );

		// Save Cart button.
		var saveBtn = popup.querySelector( '.cartshare-btn-save' );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', handleSave );
		}

		// Copy-link row button.
		var copyBtn = popup.querySelector( '.cartshare-copy-btn' );
		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', handleCopy );
		}

		// Copy channel button.
		var copyChannel = popup.querySelector( '.cartshare-btn-copy' );
		if ( copyChannel ) {
			copyChannel.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				handleChannelCopy();
			} );
		}

		// Print channel button.
		var printChannel = popup.querySelector( '.cartshare-btn-print' );
		if ( printChannel ) {
			printChannel.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				handlePrint();
			} );
		}

		// Email channel button.
		var emailChannel = popup.querySelector( '.cartshare-btn-email' );
		if ( emailChannel ) {
			emailChannel.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				showEmailForm();
			} );
		}

		// Email form — send.
		var emailSend = popup.querySelector( '.cartshare-email-send' );
		if ( emailSend ) {
			emailSend.addEventListener( 'click', handleEmailSend );
		}

		// Email form — cancel.
		var emailCancel = popup.querySelector( '.cartshare-email-cancel' );
		if ( emailCancel ) {
			emailCancel.addEventListener( 'click', hideEmailForm );
		}

		// Analytics: dispatch a custom event for each channel button click.
		var allChannelBtns = popup.querySelectorAll( '[data-channel]' );
		Array.prototype.forEach.call( allChannelBtns, function ( el ) {
			el.addEventListener( 'click', function () {
				var slug = el.getAttribute( 'data-channel' );
				document.dispatchEvent( new CustomEvent( 'cartshare:channel_click', {
					bubbles: true,
					detail: {
						channel:  slug,
						shareUrl: currentShareUrl || null,
					},
				} ) );
			} );
		} );

		// Trigger buttons outside the popup (e.g. the classic-cart button).
		document.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.classList.contains( 'cartshare-open' ) ) {
				openPopup();
			}
		} );
	}

	/* ------------------------------------------------------------------
	   Boot
	------------------------------------------------------------------ */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Expose openPopup globally so the block-cart button and other JS can call it.
	window.CartShare = window.CartShare || {};
	window.CartShare.openPopup = openPopup;

}() );
