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

	function hasCompleteGiftCardBinding() {
		var categoryIdInput = document.getElementById( '_wctf_fazer_giftcard_category_id' );
		var cardIdInput = document.getElementById( '_wctf_fazer_giftcard_card_id' );
		var cardKeyInput = document.getElementById( '_wctf_fazer_giftcard_offer_key' );
		var categoryId = categoryIdInput ? categoryIdInput.value.trim() : '';
		var cardId = cardIdInput ? cardIdInput.value.trim() : '';
		var cardKey = cardKeyInput ? cardKeyInput.value.trim() : '';

		return '' !== categoryId &&
			'' !== cardId &&
			'' !== cardKey &&
			categoryId + '::' + cardId === cardKey;
	}

	function updateGiftCardAutoPurchaseControl() {
		var wrapper = document.getElementById( 'wctf-fazer-giftcard-auto-purchase-field' );
		var checkbox = document.getElementById( '_wctf_fazer_giftcard_auto_purchase_enabled' );
		var bindingRequired = document.getElementById( 'wctf-fazer-giftcard-auto-purchase-binding-required' );
		var topupTypeSelector = document.getElementById( '_topup_type' );
		var productTypeSelector = document.getElementById( 'product-type' );
		var isGiftCardProduct = productTypeSelector &&
			'simple' === productTypeSelector.value &&
			'giftcard' === getSelectedTopupType( topupTypeSelector );
		var hasBinding = isGiftCardProduct && hasCompleteGiftCardBinding();

		if ( ! wrapper || ! checkbox ) {
			return;
		}

		if ( ! isGiftCardProduct ) {
			checkbox.checked = false;
			checkbox.disabled = true;
			wrapper.hidden = true;
			wrapper.style.display = 'none';
			wrapper.setAttribute( 'aria-hidden', 'true' );

			if ( bindingRequired ) {
				bindingRequired.hidden = false;
			}

			return;
		}

		wrapper.hidden = false;
		wrapper.style.display = '';
		wrapper.setAttribute( 'aria-hidden', 'false' );
		checkbox.disabled = ! hasBinding;

		if ( ! hasBinding ) {
			checkbox.checked = false;
		}

		if ( bindingRequired ) {
			bindingRequired.hidden = hasBinding;
		}
	}

	window.wctfUpdateGiftCardAutoPurchaseControl = updateGiftCardAutoPurchaseControl;

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
		} else if ( 'game' === selectedType ) {
			showPanel( panels.topup );
		} else if ( 'account' === selectedType ) {
			showPanel( panels.account );
		}

		updateGiftCardAutoPurchaseControl();
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

		document.addEventListener( 'wctf:giftcard-binding-changed', updateGiftCardAutoPurchaseControl );

		document.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if (
				target &&
				(
					'product-type' === target.id ||
					'_topup_type' === target.id ||
					'_wctf_fazer_giftcard_category_id' === target.id ||
					'_wctf_fazer_giftcard_card_id' === target.id ||
					'_wctf_fazer_giftcard_offer_key' === target.id
				)
			) {
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
