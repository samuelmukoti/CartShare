/**
 * CartShare Admin Cart Builder JS
 *
 * Handles:
 *  - Product search with debounced keyup
 *  - Adding products to the cart builder list
 *  - Loading product variations for variable products
 *  - Customer search with debounced keyup
 *  - Generating a shareable cart link via the REST API
 *  - Copy-link button behaviour
 *
 * Depends on: jQuery (enqueued by CartShare_Admin::enqueue_assets).
 *
 * @package CartShare
 */
( function ( $, data ) {
	'use strict';

	if ( ! $ || ! data ) {
		return;
	}

	/**
	 * Debounce a function call.
	 *
	 * @param {Function} fn    Function to debounce.
	 * @param {number}   delay Delay in milliseconds.
	 * @return {Function} Debounced function.
	 */
	function debounce( fn, delay ) {
		var timer;
		return function () {
			var context = this;
			var args    = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () {
				fn.apply( context, args );
			}, delay );
		};
	}

	/**
	 * Initialise debounced product search on #cartshare-product-search.
	 *
	 * Fires a GET request after the user has typed at least 2 characters,
	 * with a 300 ms debounce. Renders clickable result rows beneath the input.
	 */
	function initProductSearch() {
		var $input   = $( '#cartshare-product-search' );
		var $results = $( '#cartshare-product-results' );

		if ( ! $input.length ) {
			return;
		}

		$input.on(
			'keyup',
			debounce( function () {
				var val = $input.val().trim();

				$results.empty();

				if ( val.length < 2 ) {
					return;
				}

				$.get(
					data.ajaxUrl,
					{
						action: 'cartshare_search_products',
						nonce:  data.ajaxNonce,
						term:   val,
					},
					function ( response ) {
						$results.empty();

						if ( ! response.success || ! response.data || ! response.data.length ) {
							return;
						}

						$.each( response.data, function ( i, product ) {
							var $row = $( '<div class="cartshare-search-result">' )
								.text( product.name )
								.attr( 'data-product-id', product.id );

							$row.on( 'click', function () {
								addProduct( product );
								$results.empty();
								$input.val( '' );
							} );

							$results.append( $row );
						} );
					}
				);
			}, 300 )
		);

		// Hide results when clicking outside.
		$( document ).on( 'click', function ( e ) {
			if ( ! $( e.target ).closest( '#cartshare-product-search, #cartshare-product-results' ).length ) {
				$results.empty();
			}
		} );
	}

	/**
	 * Add a product to the cart builder list.
	 *
	 * If the product is already present its quantity is incremented.
	 * Variable products immediately trigger variation loading.
	 *
	 * @param {Object} product Product data object from search results.
	 */
	function addProduct( product ) {
		var $tbody   = $( '#cartshare-item-list tbody' );
		var $existing = $tbody.find( 'tr[data-product-id="' + product.id + '"]' );

		if ( $existing.length ) {
			var $qtyInput = $existing.find( '.cartshare-qty' );
			$qtyInput.val( parseInt( $qtyInput.val(), 10 ) + 1 );
			return;
		}

		var $row = $(
			'<tr data-product-id="' + product.id + '">' +
				'<td class="cartshare-product-name">' + $( '<span>' ).text( product.name ).html() + '</td>' +
				'<td><input type="number" class="cartshare-qty" value="1" min="1"></td>' +
				'<td><div class="cartshare-variation-container"></div></td>' +
				'<td><button type="button" class="button cartshare-remove-item">' + ( data.removeLabel || 'Remove' ) + '</button></td>' +
			'</tr>'
		);

		$row.find( '.cartshare-remove-item' ).on( 'click', function () {
			$row.remove();
		} );

		$tbody.append( $row );

		if ( product.is_variable ) {
			loadVariations( product.id, $row );
		}
	}

	/**
	 * Load variations for a variable product and render a <select> in the row.
	 *
	 * On change, stores data-variation-id and data-variation-attrs (JSON) on
	 * the parent <tr>.
	 *
	 * @param {number} productId WooCommerce product ID.
	 * @param {jQuery} $row      The table row element for this product.
	 */
	function loadVariations( productId, $row ) {
		var $container = $row.find( '.cartshare-variation-container' );

		$container.html( '<span class="cartshare-loading">' + ( data.loadingLabel || 'Loading variations…' ) + '</span>' );

		$.get(
			data.ajaxUrl,
			{
				action:     'cartshare_get_variations',
				nonce:      data.ajaxNonce,
				product_id: productId,
			},
			function ( response ) {
				$container.empty();

				if ( ! response.success || ! response.data || ! response.data.length ) {
					$container.html( '<span class="cartshare-error">' + ( data.noVariationsLabel || 'No available variations.' ) + '</span>' );
					return;
				}

				var $select = $( '<select class="cartshare-variation-select">' )
					.append( $( '<option value="">' ).text( data.selectVariationLabel || '— Select variation —' ) );

				$.each( response.data, function ( i, variation ) {
					$select.append(
						$( '<option>' )
							.val( variation.id )
							.attr( 'data-attrs', JSON.stringify( variation.attributes ) )
							.text( variation.name )
					);
				} );

				$select.on( 'change', function () {
					var $option = $select.find( 'option:selected' );
					var varId   = $option.val();
					if ( varId ) {
						$row.attr( 'data-variation-id',    varId );
						$row.attr( 'data-variation-attrs', $option.data( 'attrs' ) );
					} else {
						$row.removeAttr( 'data-variation-id' );
						$row.removeAttr( 'data-variation-attrs' );
					}
				} );

				$container.append( $select );
			}
		);
	}

	/**
	 * Initialise debounced customer search on #cartshare-customer-search.
	 *
	 * On selection, writes the customer ID into #cartshare-customer-id.
	 */
	function initCustomerSearch() {
		var $input   = $( '#cartshare-customer-search' );
		var $results = $( '#cartshare-customer-results' );
		var $idField = $( '#cartshare-customer-id' );

		if ( ! $input.length ) {
			return;
		}

		$input.on(
			'keyup',
			debounce( function () {
				var val = $input.val().trim();

				$results.empty();

				if ( val.length < 2 ) {
					return;
				}

				$.get(
					data.ajaxUrl,
					{
						action: 'cartshare_search_customers',
						nonce:  data.ajaxNonce,
						term:   val,
					},
					function ( response ) {
						$results.empty();

						if ( ! response.success || ! response.data || ! response.data.length ) {
							return;
						}

						$.each( response.data, function ( i, customer ) {
							var $row = $( '<div class="cartshare-search-result">' )
								.text( customer.name + ' (' + customer.email + ')' )
								.attr( 'data-customer-id', customer.id );

							$row.on( 'click', function () {
								$idField.val( customer.id );
								$input.val( customer.name + ' (' + customer.email + ')' );
								$results.empty();
							} );

							$results.append( $row );
						} );
					}
				);
			}, 300 )
		);

		// Clear customer ID if user clears the search field.
		$input.on( 'input', function () {
			if ( ! $input.val().trim() ) {
				$idField.val( '' );
			}
		} );

		// Hide results when clicking outside.
		$( document ).on( 'click', function ( e ) {
			if ( ! $( e.target ).closest( '#cartshare-customer-search, #cartshare-customer-results' ).length ) {
				$results.empty();
			}
		} );
	}

	/**
	 * Show an inline error message near the generate button.
	 *
	 * @param {string} message Error text to display.
	 */
	function showInlineError( message ) {
		var $error = $( '.cartshare-inline-error' );
		if ( $error.length ) {
			$error.text( message ).show();
		}
	}

	/**
	 * Hide any visible inline error message.
	 */
	function hideInlineError() {
		$( '.cartshare-inline-error' ).hide().text( '' );
	}

	/**
	 * Collect line items from the cart builder DOM rows.
	 *
	 * @return {Array} Array of item objects ready for the REST payload.
	 */
	function collectItems() {
		var items = [];

		$( '#cartshare-item-list tbody tr[data-product-id]' ).each( function () {
			var $row        = $( this );
			var productId   = parseInt( $row.attr( 'data-product-id' ), 10 );
			var variationId = parseInt( $row.attr( 'data-variation-id' ) || 0, 10 );
			var qty         = parseInt( $row.find( '.cartshare-qty' ).val(), 10 ) || 1;
			var item        = {
				product_id: productId,
				quantity:   qty,
			};

			if ( variationId ) {
				item.variation_id = variationId;
				try {
					item.variation = JSON.parse( $row.attr( 'data-variation-attrs' ) || '{}' );
				} catch ( e ) {
					item.variation = {};
				}
			}

			items.push( item );
		} );

		return items;
	}

	/**
	 * Wire the Generate Link button.
	 *
	 * Validates that items exist and that all variable products have a
	 * variation selected, then POSTs to the REST API save endpoint.
	 */
	function initGenerateLink() {
		$( document ).on( 'click', '#cartshare-generate-btn', function () {
			hideInlineError();

			var items = collectItems();

			if ( ! items.length ) {
				showInlineError( data.noItemsError || 'Please add at least one product.' );
				return;
			}

			// Validate all variable products have a variation selected.
			var missingVariation = false;

			$( '#cartshare-item-list tbody tr[data-product-id]' ).each( function () {
				var $row     = $( this );
				var $select  = $row.find( '.cartshare-variation-select' );
				if ( $select.length && ! $select.val() ) {
					missingVariation = true;
					return false; // break.
				}
			} );

			if ( missingVariation ) {
				showInlineError( data.noVariationSelectedError || 'Please select a variation for all variable products.' );
				return;
			}

			var customerId = parseInt( $( '#cartshare-customer-id' ).val() || 0, 10 );
			var payload    = {
				items:       items,
				source:      'admin-created',
				customer_id: customerId || undefined,
			};

			$.ajax( {
				url:         data.restUrl + 'save',
				method:      'POST',
				contentType: 'application/json',
				dataType:    'json',
				headers:     { 'X-WP-Nonce': data.restNonce },
				data:        JSON.stringify( payload ),
				success:     function ( response ) {
					var url = response && response.url ? response.url : '';
					if ( url ) {
						$( '#cartshare-share-url' ).val( url );
						$( '#cartshare-link-container' ).show();
					}
				},
				error: function ( xhr ) {
					var message = data.saveError || 'Could not generate cart link.';
					try {
						var body = JSON.parse( xhr.responseText );
						if ( body.message ) {
							message = body.message;
						}
					} catch ( e ) {
						// Use default message.
					}
					showInlineError( message );
				},
			} );
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
	 * Wire the copy-link button for the generated cart URL.
	 *
	 * Uses navigator.clipboard when available with an execCommand fallback
	 * for older environments.
	 */
	function initCopyLink() {
		$( document ).on( 'click', '#cartshare-copy-link-btn', function () {
			var $btn = $( this );
			var url  = $( '#cartshare-share-url' ).val();

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
	 * Initialise all cart-builder components when the DOM is ready.
	 */
	$( function () {
		initProductSearch();
		initCustomerSearch();
		initGenerateLink();
		initCopyLink();
	} );

} )( window.jQuery, window.CartShareCartBuilder );
