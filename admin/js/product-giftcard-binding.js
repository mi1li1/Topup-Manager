( function () {
	'use strict';

	var config = window.wctfProductGiftCardBinding || {};
	var searchInput = document.getElementById( 'wctf-fazercards-giftcard-search' );
	var searchButton = document.getElementById( 'wctf-fazercards-giftcard-search-button' );
	var clearButton = document.getElementById( 'wctf-fazercards-giftcard-clear-button' );
	var categoryIdInput = document.getElementById( '_wctf_fazer_giftcard_category_id' );
	var cardIdInput = document.getElementById( '_wctf_fazer_giftcard_card_id' );
	var cardKeyInput = document.getElementById( '_wctf_fazer_giftcard_offer_key' );
	var status = document.getElementById( 'wctf-fazercards-giftcard-status' );
	var results = document.getElementById( 'wctf-fazercards-giftcard-results' );
	var noSelection = document.getElementById( 'wctf-fazercards-giftcard-no-selection' );
	var selectionDetails = document.getElementById( 'wctf-fazercards-giftcard-selection-details' );
	var selectedCategoryId = document.getElementById( 'wctf-fazercards-giftcard-selected-category-id' );
	var selectedCategoryName = document.getElementById( 'wctf-fazercards-giftcard-selected-category-name' );
	var selectedCardId = document.getElementById( 'wctf-fazercards-giftcard-selected-card-id' );
	var selectedCardName = document.getElementById( 'wctf-fazercards-giftcard-selected-card-name' );
	var selectedPriceUsd = document.getElementById( 'wctf-fazercards-giftcard-selected-price-usd' );
	var selectedCurrency = document.getElementById( 'wctf-fazercards-giftcard-selected-currency' );
	var selectedRegion = document.getElementById( 'wctf-fazercards-giftcard-selected-region' );
	var selectedStock = document.getElementById( 'wctf-fazercards-giftcard-selected-stock' );
	var selectedMinQuantity = document.getElementById( 'wctf-fazercards-giftcard-selected-min-quantity' );
	var selectedMaxQuantity = document.getElementById( 'wctf-fazercards-giftcard-selected-max-quantity' );
	var isLoading = false;

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

		selectedCategoryId.textContent = hasCard ? normalizeValue( card.category_id ) : '';
		selectedCategoryName.textContent = hasCard ? normalizeValue( card.category_name ) : '';
		selectedCardId.textContent = hasCard ? normalizeValue( card.card_id ) : '';
		selectedCardName.textContent = hasCard ? normalizeValue( card.name ) : '';
		selectedPriceUsd.textContent = hasCard ? normalizeValue( card.price_usd ) : '';
		selectedCurrency.textContent = hasCard ? normalizeValue( card.currency ) : '';
		selectedRegion.textContent = hasCard ? normalizeValue( card.region ) : '';
		selectedStock.textContent = hasCard ? normalizeValue( card.stock ) : '';
		selectedMinQuantity.textContent = hasCard ? normalizeValue( card.min_order_quantity ) : '';
		selectedMaxQuantity.textContent = hasCard ? normalizeValue( card.max_order_quantity ) : '';
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

			details.appendChild( createField( 'Category ID', card.category_id ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Category Name', card.category_name ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Card ID', card.card_id ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Card Name', card.name ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Price USD', card.price_usd ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Currency', card.currency ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Region', card.region ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Stock', card.stock ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Min', card.min_order_quantity ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Max', card.max_order_quantity ) );

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
}() );
