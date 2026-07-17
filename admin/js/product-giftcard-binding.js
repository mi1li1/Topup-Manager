( function () {
	'use strict';

	var config = window.wctfProductGiftCardBinding || {};

	function getMessage( key, fallback ) {
		if ( config.messages && 'string' === typeof config.messages[ key ] ) {
			return config.messages[ key ];
		}

		return fallback;
	}

	function normalizeValue( value ) {
		if ( null === value || 'undefined' === typeof value ) {
			return '';
		}

		return String( value );
	}

	function findRole( editor, role ) {
		return editor.querySelector( '[data-wctf-giftcard-role="' + role + '"]' );
	}

	function initializeEditor( editor ) {
		var searchInput;
		var searchButton;
		var clearButton;
		var categoryIdInput;
		var cardIdInput;
		var cardKeyInput;
		var status;
		var results;
		var noSelection;
		var selectionDetails;
		var autoPurchase;
		var bindingRequired;
		var selectedFields;
		var isLoading = false;

		if ( 'yes' === editor.getAttribute( 'data-wctf-giftcard-initialized' ) ) {
			return;
		}

		searchInput = findRole( editor, 'search' );
		searchButton = findRole( editor, 'search-button' );
		clearButton = findRole( editor, 'clear-button' );
		categoryIdInput = findRole( editor, 'category-id' );
		cardIdInput = findRole( editor, 'card-id' );
		cardKeyInput = findRole( editor, 'card-key' );
		status = findRole( editor, 'status' );
		results = findRole( editor, 'results' );
		noSelection = findRole( editor, 'no-selection' );
		selectionDetails = findRole( editor, 'selection-details' );
		autoPurchase = findRole( editor, 'auto-purchase' );
		bindingRequired = findRole( editor, 'binding-required' );
		selectedFields = {
			categoryId: findRole( editor, 'selected-category-id' ),
			categoryName: findRole( editor, 'selected-category-name' ),
			cardId: findRole( editor, 'selected-card-id' ),
			cardName: findRole( editor, 'selected-card-name' ),
			priceUsd: findRole( editor, 'selected-price-usd' ),
			currency: findRole( editor, 'selected-currency' ),
			region: findRole( editor, 'selected-region' ),
			stock: findRole( editor, 'selected-stock' ),
			minQuantity: findRole( editor, 'selected-min-quantity' ),
			maxQuantity: findRole( editor, 'selected-max-quantity' )
		};

		if (
			! searchInput ||
			! searchButton ||
			! clearButton ||
			! categoryIdInput ||
			! cardIdInput ||
			! cardKeyInput ||
			! status ||
			! results ||
			! noSelection ||
			! selectionDetails
		) {
			return;
		}

		editor.setAttribute( 'data-wctf-giftcard-initialized', 'yes' );

		function hasCompleteBinding() {
			var categoryId = categoryIdInput.value.trim();
			var cardId = cardIdInput.value.trim();
			var cardKey = cardKeyInput.value.trim();

			return '' !== categoryId &&
				'' !== cardId &&
				'' !== cardKey &&
				categoryId + '::' + cardId === cardKey;
		}

		function updateAutoPurchaseControl() {
			var hasBinding = hasCompleteBinding();

			if ( autoPurchase ) {
				autoPurchase.disabled = ! hasBinding;

				if ( ! hasBinding ) {
					autoPurchase.checked = false;
				}
			}

			if ( bindingRequired ) {
				bindingRequired.hidden = hasBinding;
			}

			if ( 'function' === typeof window.wctfUpdateGiftCardAutoPurchaseControl ) {
				window.wctfUpdateGiftCardAutoPurchaseControl();
			}

			document.dispatchEvent( new CustomEvent( 'wctf:giftcard-binding-changed' ) );
		}

		function setStatus( message ) {
			status.textContent = normalizeValue( message );
		}

		function clearResults() {
			while ( results.firstChild ) {
				results.removeChild( results.firstChild );
			}

			results.hidden = true;
		}

		function setLoading( loading ) {
			isLoading = loading;
			searchInput.disabled = loading;
			searchButton.disabled = loading;
			clearButton.disabled = loading;
			results.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
		}

		function updateText( field, value ) {
			if ( field ) {
				field.textContent = normalizeValue( value );
			}
		}

		function updateSelection( card ) {
			var hasCard = card &&
				'' !== normalizeValue( card.card_key ) &&
				'' !== normalizeValue( card.category_id ) &&
				'' !== normalizeValue( card.card_id );

			categoryIdInput.value = hasCard ? normalizeValue( card.category_id ) : '';
			cardIdInput.value = hasCard ? normalizeValue( card.card_id ) : '';
			cardKeyInput.value = hasCard ? normalizeValue( card.card_key ) : '';
			noSelection.hidden = hasCard;
			selectionDetails.hidden = ! hasCard;

			updateText( selectedFields.categoryId, hasCard ? card.category_id : '' );
			updateText( selectedFields.categoryName, hasCard ? card.category_name : '' );
			updateText( selectedFields.cardId, hasCard ? card.card_id : '' );
			updateText( selectedFields.cardName, hasCard ? card.name : '' );
			updateText( selectedFields.priceUsd, hasCard ? card.price_usd : '' );
			updateText( selectedFields.currency, hasCard ? card.currency : '' );
			updateText( selectedFields.region, hasCard ? card.region : '' );
			updateText( selectedFields.stock, hasCard ? card.stock : '' );
			updateText( selectedFields.minQuantity, hasCard ? card.min_order_quantity : '' );
			updateText( selectedFields.maxQuantity, hasCard ? card.max_order_quantity : '' );
			updateAutoPurchaseControl();
		}

		function createField( label, value ) {
			var field = document.createElement( 'span' );
			var strong = document.createElement( 'strong' );

			strong.textContent = label + ': ';
			field.appendChild( strong );
			field.appendChild( document.createTextNode( normalizeValue( value ) ) );

			return field;
		}

		function renderCards( cards ) {
			var fragment = document.createDocumentFragment();

			clearResults();

			if ( ! Array.isArray( cards ) || 0 === cards.length ) {
				setStatus( getMessage( 'empty', 'No matching Gift Cards found.' ) );
				return;
			}

			cards.forEach( function ( card ) {
				var item = document.createElement( 'li' );
				var details = document.createElement( 'div' );
				var selectButton = document.createElement( 'button' );
				var fields = [
					[ 'Category ID', card.category_id ],
					[ 'Category Name', card.category_name ],
					[ 'Card ID', card.card_id ],
					[ 'Card Name', card.name ],
					[ 'Price USD', card.price_usd ],
					[ 'Currency', card.currency ],
					[ 'Region', card.region ],
					[ 'Stock', card.stock ],
					[ 'Min', card.min_order_quantity ],
					[ 'Max', card.max_order_quantity ]
				];

				fields.forEach( function ( field, index ) {
					if ( 0 < index ) {
						details.appendChild( document.createTextNode( ' | ' ) );
					}

					details.appendChild( createField( field[ 0 ], field[ 1 ] ) );
				} );

				selectButton.type = 'button';
				selectButton.className = 'button';
				selectButton.textContent = getMessage( 'selectCard', 'Select Gift Card' );
				selectButton.addEventListener( 'click', function () {
					updateSelection( card );
					clearResults();
					setStatus( getMessage( 'selected', 'Gift Card selected. Save the product to apply the binding.' ) );
				} );

				item.appendChild( details );
				item.appendChild( selectButton );
				fragment.appendChild( item );
			} );

			results.appendChild( fragment );
			results.hidden = false;
			setStatus( '' );
		}

		function getErrorMessage( response ) {
			if (
				response &&
				response.data &&
				'string' === typeof response.data.message &&
				'' !== response.data.message
			) {
				return response.data.message;
			}

			return getMessage( 'error', 'The local Gift Card search could not be completed.' );
		}

		function searchCards() {
			var requestBody;

			if ( isLoading ) {
				return;
			}

			if ( ! config.ajaxUrl || ! config.nonce || ! config.action ) {
				setStatus( getMessage( 'missingConfig', 'Gift Card search configuration is unavailable.' ) );
				return;
			}

			requestBody = new URLSearchParams();
			requestBody.set( 'action', config.action );
			requestBody.set( 'nonce', config.nonce );
			requestBody.set( 'search', searchInput.value.trim() );

			clearResults();
			setLoading( true );
			setStatus( getMessage( 'loading', 'Searching local Gift Cards...' ) );

			window.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: requestBody.toString()
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						throw new Error( getErrorMessage( response ) );
					}

					renderCards( response.data && response.data.items ? response.data.items : [] );
				} )
				.catch( function ( error ) {
					clearResults();
					setStatus( error && error.message ? error.message : getMessage( 'error', 'The local Gift Card search could not be completed.' ) );
				} )
				.finally( function () {
					setLoading( false );
				} );
		}

		searchButton.addEventListener( 'click', searchCards );
		searchInput.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				searchCards();
			}
		} );
		clearButton.addEventListener( 'click', function () {
			updateSelection( null );
			clearResults();
			setStatus( getMessage( 'cleared', 'Gift Card binding cleared. Save the product to apply this change.' ) );
		} );

		updateAutoPurchaseControl();
	}

	function initializeEditors() {
		var editors = document.querySelectorAll( '.wctf-fazercards-giftcard-binding-editor' );

		Array.prototype.forEach.call( editors, initializeEditor );
	}

	function init() {
		initializeEditors();

		if ( window.jQuery ) {
			window.jQuery( document.body ).on(
				'woocommerce_variations_loaded woocommerce_variations_added',
				initializeEditors
			);
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
