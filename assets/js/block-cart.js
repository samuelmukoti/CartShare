/**
 * CartShare block-cart integration.
 *
 * Injects the Save & Share button into the WooCommerce Cart block
 * using wp.element.createElement and wp.plugins.registerPlugin —
 * no JSX or build step required.
 *
 * The button delegates to window.CartShare.openPopup() which is
 * defined by popup.js and works identically to the classic cart path.
 */
( function () {
	if ( ! window.wp || ! window.wp.element || ! window.wp.plugins ) {
		return;
	}
	if ( ! window.wc || ! window.wc.blocksCheckout ) {
		return;
	}

	var registerPlugin    = wp.plugins.registerPlugin;
	// Prefer the experimental slot (current WooCommerce); fall back to a
	// stable export if it is ever promoted. Bail rather than render an
	// undefined component, which would crash the whole Cart block.
	var ExperimentalOrderMeta = wc.blocksCheckout.ExperimentalOrderMeta || wc.blocksCheckout.OrderMeta;
	var el                = wp.element.createElement;

	if ( ! registerPlugin || ! ExperimentalOrderMeta ) {
		return;
	}

	var buttonLabel = ( window.CartShareData && window.CartShareData.buttonLabel )
		? window.CartShareData.buttonLabel
		: 'Save & Share Cart';

	registerPlugin( 'cartshare-save-share', {
		scope: 'woocommerce-checkout',
		render: function () {
			return el(
				ExperimentalOrderMeta,
				{},
				el(
					'div',
					{ className: 'cartshare-button-wrap' },
					el(
						'button',
						{
							type:         'button',
							className:    'cartshare-open button alt',
							'aria-haspopup': 'dialog',
							onClick: function () {
								if ( window.CartShare && typeof window.CartShare.openPopup === 'function' ) {
									window.CartShare.openPopup();
								}
							},
						},
						buttonLabel
					)
				)
			);
		},
	} );
} )();
