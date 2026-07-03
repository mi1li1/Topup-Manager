( function () {
	'use strict';

	var config = window.wctfProductOfferBinding || {};
	var searchInput = document.getElementById( 'wctf-fazer-offer-search' );
	var searchButton = document.getElementById( 'wctf-fazer-offer-search-button' );
	var clearButton = document.getElementById( 'wctf-fazer-offer-clear-button' );
	var offerIdInput = document.getElementById( '_fazer_offer_id' );
	var offerKeyInput = document.getElementById( '_wctf_fazer_offer_key' );
	var status = document.getElementById( 'wctf-fazer-offer-status' );
	var results = document.getElementById( 'wctf-fazer-offer-results' );
	var noSelection = document.getElementById( 'wctf-fazer-no-selection' );
	var selectionDetails = document.getElementById( 'wctf-fazer-selection-details' );
	var selectedOfferId = document.getElementById( 'wctf-fazer-selected-offer-id' );
	var selectedCategoryId = document.getElementById( 'wctf-fazer-selected-category-id' );
	var selectedCategoryName = document.getElementById( 'wctf-fazer-selected-category-name' );
	var selectedOfferName = document.getElementById( 'wctf-fazer-selected-offer-name' );
	var selectedPriceUsd = document.getElementById( 'wctf-fazer-selected-price-usd' );
	var isLoading = false;

	if ( ! searchInput || ! searchButton || ! clearButton || ! offerIdInput || ! offerKeyInput || ! status || ! results ) {
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

	function updateSelection( offer ) {
		var hasOffer = offer &&
			'' !== normalizeValue( offer.offer_key ) &&
			'' !== normalizeValue( offer.offer_id );

		offerIdInput.value = hasOffer ? normalizeValue( offer.offer_id ) : '';
		offerKeyInput.value = hasOffer ? normalizeValue( offer.offer_key ) : '';
		noSelection.hidden = hasOffer;
		selectionDetails.hidden = ! hasOffer;
		selectedOfferId.textContent = hasOffer ? normalizeValue( offer.offer_id ) : '';
		selectedCategoryId.textContent = hasOffer ? normalizeValue( offer.category_id ) : '';
		selectedCategoryName.textContent = hasOffer ? normalizeValue( offer.category_name ) : '';
		selectedOfferName.textContent = hasOffer ? normalizeValue( offer.name ) : '';
		selectedPriceUsd.textContent = hasOffer ? normalizeValue( offer.price_usd ) : '';
	}

	function createField( label, value ) {
		var field = document.createElement( 'span' );
		var strong = document.createElement( 'strong' );

		strong.textContent = label + ': ';
		field.appendChild( strong );
		field.appendChild( document.createTextNode( normalizeValue( value ) ) );

		return field;
	}

	function renderOffers( offers ) {
		var fragment = document.createDocumentFragment();

		clearResults();

		if ( ! Array.isArray( offers ) || 0 === offers.length ) {
			setStatus( getMessage( 'empty', 'No matching offers found.' ) );
			return;
		}

		offers.forEach( function ( offer ) {
			var item = document.createElement( 'li' );
			var details = document.createElement( 'div' );
			var selectButton = document.createElement( 'button' );

			details.appendChild( createField( 'Offer ID', offer.offer_id ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Category ID', offer.category_id ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Category Name', offer.category_name ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Offer Name', offer.name ) );
			details.appendChild( document.createTextNode( ' | ' ) );
			details.appendChild( createField( 'Price USD', offer.price_usd ) );

			selectButton.type = 'button';
			selectButton.className = 'button';
			selectButton.textContent = getMessage( 'selectOffer', 'Select offer' );
			selectButton.addEventListener( 'click', function () {
				updateSelection( offer );
				clearResults();
				setStatus( getMessage( 'selected', 'Offer selected. Save the product to apply the binding.' ) );
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

		return getMessage( 'error', 'The local offer search could not be completed.' );
	}

	function searchOffers() {
		var requestBody;

		if ( isLoading ) {
			return;
		}

		if ( ! config.ajaxUrl || ! config.nonce || ! config.action ) {
			setStatus( getMessage( 'missingConfig', 'Offer search configuration is unavailable.' ) );
			return;
		}

		requestBody = new URLSearchParams();
		requestBody.set( 'action', config.action );
		requestBody.set( 'nonce', config.nonce );
		requestBody.set( 'page', '1' );
		requestBody.set( 'search', searchInput.value.trim() );

		clearResults();
		setLoading( true );
		setStatus( getMessage( 'loading', 'Searching local offers...' ) );

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

				renderOffers( response.data && response.data.items ? response.data.items : [] );
			} )
			.catch( function ( error ) {
				clearResults();
				setStatus( error && error.message ? error.message : getMessage( 'error', 'The local offer search could not be completed.' ) );
			} )
			.finally( function () {
				setLoading( false );
			} );
	}

	searchButton.addEventListener( 'click', searchOffers );

	searchInput.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			searchOffers();
		}
	} );

	clearButton.addEventListener( 'click', function () {
		updateSelection( null );
		clearResults();
		setStatus( getMessage( 'cleared', 'Binding cleared. Save the product to apply this change.' ) );
	} );
}() );
