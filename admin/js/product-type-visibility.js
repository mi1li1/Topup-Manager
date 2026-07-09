( function () {
	'use strict';

	function getPanels() {
		return {
			topup: document.getElementById( 'wctf-fazer-offer-binding' ),
			giftcard: document.getElementById( 'wctf-fazercards-giftcard-binding' ),
			account: document.getElementById( 'wctf-account-topup-binding' )
		};
	}

	function hidePanel( panel ) {
		if ( ! panel ) {
			return;
		}

		panel.hidden = true;
		panel.style.display = 'none';
		panel.setAttribute( 'aria-hidden', 'true' );
	}

	function showPanel( panel ) {
		if ( ! panel ) {
			return;
		}

		panel.hidden = false;
		panel.style.display = '';
		panel.setAttribute( 'aria-hidden', 'false' );
	}

	function getSelectedTopupType( selector ) {
		var selectedType = selector ? selector.value : '';

		if ( 'giftcard' === selectedType || 'game' === selectedType || 'account' === selectedType ) {
			return selectedType;
		}

		return '';
	}

	function applyVisibility() {
		var selector = document.getElementById( '_topup_type' );
		var selectedType = getSelectedTopupType( selector );
		var panels = getPanels();
		var root = document.body || document.documentElement;

		hidePanel( panels.topup );
		hidePanel( panels.giftcard );
		hidePanel( panels.account );

		if ( root ) {
			root.setAttribute( 'data-wctf-topup-type', selectedType || 'none' );
		}

		if ( 'giftcard' === selectedType ) {
			showPanel( panels.giftcard );
			return;
		}

		if ( 'game' === selectedType ) {
			showPanel( panels.topup );
			return;
		}

		if ( 'account' === selectedType ) {
			showPanel( panels.account );
		}
	}

	function applyVisibilitySoon() {
		applyVisibility();
		window.setTimeout( applyVisibility, 50 );
		window.setTimeout( applyVisibility, 250 );
	}

	function init() {
		var selector = document.getElementById( '_topup_type' );

		applyVisibilitySoon();

		if ( selector ) {
			selector.addEventListener( 'change', applyVisibilitySoon );
		}

		document.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if ( target && ( 'product-type' === target.id || '_topup_type' === target.id ) ) {
				applyVisibilitySoon();
			}
		} );

		if ( window.jQuery ) {
			window.jQuery( document.body ).on(
				'woocommerce-product-type-change woocommerce_variations_loaded',
				applyVisibilitySoon
			);
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
