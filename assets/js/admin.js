/**
 * CartShare Admin JS
 *
 * Handles:
 *  - wp-color-picker initialisation on .cartshare-color-picker inputs
 *  - Copy-link button behaviour in the History table
 *  - Delete confirmation prompts in the History table
 *
 * Depends on: jQuery, wp-color-picker (both enqueued by CartShare_Admin::enqueue_assets).
 *
 * @package CartShare
 */
( function ( $, data ) {
	'use strict';

	if ( ! $ || ! data ) {
		return;
	}

	/**
	 * Initialise wp-color-picker on every .cartshare-color-picker input.
	 */
	function initColorPickers() {
		$( '.cartshare-color-picker' ).wpColorPicker();
	}

	/**
	 * Wire copy-link buttons in the History table.
	 *
	 * Uses navigator.clipboard when available with an execCommand fallback
	 * for older environments.
	 */
	function initCopyLinks() {
		$( document ).on( 'click', '.cartshare-copy-link', function () {
			var $btn = $( this );
			var url  = $btn.data( 'url' );

			if ( ! url ) {
				return;
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( url ).then( function () {
					showCopied( $btn );
				} ).catch( function () {
					fallbackCopy( url, $btn );
				} );
			} else {
				fallbackCopy( url, $btn );
			}
		} );
	}

	/**
	 * Fallback clipboard copy using a temporary textarea + execCommand.
	 *
	 * @param {string} text Text to copy.
	 * @param {jQuery} $btn Originating button (for visual feedback).
	 */
	function fallbackCopy( text, $btn ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity  = '0';
		document.body.appendChild( ta );
		ta.focus();
		ta.select();
		try {
			document.execCommand( 'copy' );
			showCopied( $btn );
		} catch ( err ) {
			// Silent fail — clipboard API unavailable.
		}
		document.body.removeChild( ta );
	}

	/**
	 * Briefly change button text to indicate successful copy.
	 *
	 * @param {jQuery} $btn The button element.
	 */
	function showCopied( $btn ) {
		var originalLabel = $btn.text();
		$btn.text( data.copiedLabel || 'Copied!' );
		setTimeout( function () {
			$btn.text( originalLabel );
		}, 2000 );
	}

	/**
	 * Wire delete-confirmation prompts on the History table forms.
	 *
	 * Prevents accidental deletions by requiring user confirmation before
	 * the form is submitted.
	 */
	function initDeleteConfirm() {
		$( document ).on( 'submit', '.cartshare-delete-form', function ( e ) {
			var message = data.confirmDelete || 'Are you sure you want to delete this cart?';
			if ( ! window.confirm( message ) ) { // eslint-disable-line no-alert
				e.preventDefault();
				return false;
			}
		} );
	}

	/**
	 * Initialise all admin components when the DOM is ready.
	 */
	$( function () {
		initColorPickers();
		initCopyLinks();
		initDeleteConfirm();
	} );

} )( window.jQuery, window.CartShareAdmin );
