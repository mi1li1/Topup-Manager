( function() {
    'use strict';

    var config = window.wctfGiftCardDelivery || null;

    if ( ! config ) {
        return;
    }

    function text( value ) {
        if ( null === value || 'undefined' === typeof value ) {
            return '';
        }

        return String( value );
    }

    function element( tagName, className, content ) {
        var node = document.createElement( tagName );

        if ( className ) {
            node.className = className;
        }

        if ( 'undefined' !== typeof content ) {
            node.textContent = text( content );
        }

        return node;
    }

    function svgElement( tagName, attributes ) {
        var node = document.createElementNS( 'http://www.w3.org/2000/svg', tagName );

        Object.keys( attributes || {} ).forEach( function( name ) {
            node.setAttribute( name, attributes[ name ] );
        } );

        return node;
    }

    function copyIcon() {
        var icon = svgElement( 'svg', {
            'class': 'wctf-thankyou-copy-icon',
            'viewBox': '0 0 24 24',
            'aria-hidden': 'true',
            'focusable': 'false'
        } );

        icon.appendChild( svgElement( 'rect', {
            'x': '8',
            'y': '8',
            'width': '11',
            'height': '11',
            'rx': '2',
            'fill': 'none',
            'stroke': 'currentColor',
            'stroke-width': '1.8'
        } ) );
        icon.appendChild( svgElement( 'path', {
            'd': 'M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2',
            'fill': 'none',
            'stroke': 'currentColor',
            'stroke-linecap': 'round',
            'stroke-width': '1.8'
        } ) );

        return icon;
    }

    function checkIcon() {
        var icon = svgElement( 'svg', {
            'class': 'wctf-thankyou-check-icon',
            'viewBox': '0 0 24 24',
            'aria-hidden': 'true',
            'focusable': 'false'
        } );

        icon.appendChild( svgElement( 'path', {
            'd': 'M5 12.5 9.2 17 19 7',
            'fill': 'none',
            'stroke': 'currentColor',
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
            'stroke-width': '2.2'
        } ) );

        return icon;
    }

    function orderItemId( value ) {
        value = text( value );

        return /^[1-9][0-9]*$/.test( value ) ? value : '';
    }

    function thankyouContainers() {
        var prefix = text( config.itemContainerPrefix );

        return Array.prototype.slice.call(
            document.querySelectorAll( '.wctf-giftcard-delivery-item-container[data-order-item-id]' )
        ).filter( function( container ) {
            var itemId = orderItemId( container.getAttribute( 'data-order-item-id' ) );

            return itemId && container.id === prefix + itemId;
        } );
    }

    function isThankyouProductRow( row ) {
        return row
            && 'TR' === row.tagName
            && (
                row.classList.contains( 'woocommerce-table__line-item' )
                || row.classList.contains( 'order_item' )
            );
    }

    function visibleColumnCount( row ) {
        if ( ! row || ! row.cells ) {
            return 0;
        }

        return Array.prototype.reduce.call( row.cells, function( count, cell ) {
            var style;

            if ( cell.hidden ) {
                return count;
            }

            if ( window.getComputedStyle ) {
                style = window.getComputedStyle( cell );

                if ( style && 'none' === style.display ) {
                    return count;
                }
            }

            return count + Math.max( 1, Number( cell.colSpan ) || 1 );
        }, 0 );
    }

    function thankyouTableColumnCount( table, productRow ) {
        var count = visibleColumnCount( productRow );

        if ( table && table.tHead && table.tHead.rows ) {
            Array.prototype.forEach.call( table.tHead.rows, function( headerRow ) {
                count = Math.max( count, visibleColumnCount( headerRow ) );
            } );
        }

        return Math.max( 2, count );
    }

    function syncDeliveryRowVisibility( container ) {
        var row = container && container.closest
            ? container.closest( 'tr.wctf-giftcard-delivery-row' )
            : null;
        var hasContent;

        if ( ! row ) {
            return;
        }

        hasContent = Boolean(
            container.querySelector( '.wctf-thankyou-code-row, .wctf-thankyou-delivery-status' )
        );
        row.hidden = ! hasContent;
        row.classList.toggle( 'is-empty', ! hasContent );
    }

    function promoteThankyouContainer( container ) {
        var itemId = orderItemId( container.getAttribute( 'data-order-item-id' ) );
        var orderId = orderItemId( config.orderId );
        var rowId = orderId && itemId
            ? 'wctf-giftcard-delivery-row-' + orderId + '-' + itemId
            : '';
        var row = rowId ? document.getElementById( rowId ) : null;
        var productRow;
        var table;
        var cell;

        if ( ! rowId ) {
            return;
        }

        if ( row ) {
            if (
                'TR' !== row.tagName
                || ! row.classList.contains( 'wctf-giftcard-delivery-row' )
                || itemId !== orderItemId( row.getAttribute( 'data-order-item-id' ) )
            ) {
                return;
            }

            productRow = isThankyouProductRow( row.wctfGiftCardProductRow )
                && row.wctfGiftCardProductRow.parentNode === row.parentNode
                ? row.wctfGiftCardProductRow
                : row.previousElementSibling;
            table = row.closest( 'table.woocommerce-table--order-details' );
            cell = 1 === row.cells.length
                && row.cells[ 0 ].classList.contains( 'wctf-giftcard-delivery-cell' )
                ? row.cells[ 0 ]
                : null;

            if (
                ! cell
                || ! table
                || ! isThankyouProductRow( productRow )
                || productRow.parentNode !== row.parentNode
            ) {
                return;
            }

            row.wctfGiftCardProductRow = productRow;
            cell.colSpan = thankyouTableColumnCount( table, productRow );

            if ( row.previousElementSibling !== productRow ) {
                productRow.parentNode.insertBefore( row, productRow.nextSibling );
            }

            if ( container.parentNode !== cell ) {
                cell.appendChild( container );
            }

            syncDeliveryRowVisibility( container );
            return;
        }

        productRow = container.closest ? container.closest( 'tr' ) : null;

        if ( ! isThankyouProductRow( productRow ) ) {
            return;
        }

        table = productRow.closest( 'table.woocommerce-table--order-details' );

        if ( ! table || ! productRow.parentNode ) {
            return;
        }

        row = element( 'tr', 'wctf-giftcard-delivery-row' );
        row.id = rowId;
        row.setAttribute( 'data-order-item-id', itemId );
        row.wctfGiftCardProductRow = productRow;

        cell = element( 'td', 'wctf-giftcard-delivery-cell' );
        cell.colSpan = thankyouTableColumnCount( table, productRow );
        row.appendChild( cell );
        productRow.parentNode.insertBefore( row, productRow.nextSibling );
        cell.appendChild( container );
        syncDeliveryRowVisibility( container );
    }

    function promoteThankyouContainers() {
        var containers = thankyouContainers();
        var isModernThankyou = document.body
            && document.body.classList.contains( 'wctf-modern-thankyou-page' );

        if ( ! isModernThankyou || 'thankyou_items' !== text( config.presentation ) ) {
            return containers;
        }

        containers.forEach( function( container ) {
            promoteThankyouContainer( container );
            syncDeliveryRowVisibility( container );
        } );

        return containers;
    }

    function appendThankyouStatus( container, status ) {
        if ( 'preparing' === status ) {
            var preparing = element( 'div', 'wctf-thankyou-delivery-status is-preparing' );
            var spinner = element( 'span', 'wctf-thankyou-delivery-spinner' );

            spinner.setAttribute( 'aria-hidden', 'true' );
            preparing.appendChild( spinner );
            preparing.appendChild( element( 'span', '', config.labels.thankyouPreparing ) );
            container.appendChild( preparing );
            syncDeliveryRowVisibility( container );
            return;
        }

        container.appendChild(
            element( 'div', 'wctf-thankyou-delivery-status is-blocked', config.labels.thankyouBlocked )
        );
        syncDeliveryRowVisibility( container );
    }

    function appendThankyouEntries( container, entries ) {
        entries.forEach( function( entry ) {
            var row = element( 'div', 'wctf-thankyou-code-row' );
            var value = element( 'code', 'wctf-thankyou-code-value', entry );
            var button = element( 'button', 'wctf-thankyou-code-copy' );

            button.type = 'button';
            button.setAttribute( 'aria-label', config.labels.copyLabel );
            button.setAttribute( 'title', config.labels.copyLabel );
            button.appendChild( copyIcon() );
            row.appendChild( value );
            row.appendChild( button );
            container.appendChild( row );
        } );

        syncDeliveryRowVisibility( container );
    }

    function renderThankyouItems( items, aggregateStatus ) {
        var containers = thankyouContainers();
        var firstContainer = containers.length ? containers[ 0 ] : null;
        var status = text( aggregateStatus );
        var containersByItemId = {};
        var entriesByItemId = {};
        var valid = true;

        containers.forEach( function( container ) {
            container.replaceChildren();
            syncDeliveryRowVisibility( container );
        } );

        if ( ! firstContainer ) {
            return;
        }

        if ( 'ready' !== status ) {
            appendThankyouStatus( firstContainer, 'preparing' === status ? 'preparing' : 'blocked' );
            return;
        }

        containers.forEach( function( container ) {
            var itemId = orderItemId( container.getAttribute( 'data-order-item-id' ) );

            if ( ! itemId || containersByItemId[ itemId ] ) {
                valid = false;
                return;
            }

            containersByItemId[ itemId ] = container;
        } );

        if ( ! Array.isArray( items ) || ! items.length ) {
            valid = false;
        } else {
            items.forEach( function( item ) {
                var itemId = orderItemId( item && item.item_id );
                var entries = item && Array.isArray( item.entries ) ? item.entries : [];

                if (
                    ! itemId
                    || 'ready' !== text( item && item.status )
                    || ! entries.length
                    || ! containersByItemId[ itemId ]
                    || entriesByItemId[ itemId ]
                ) {
                    valid = false;
                    return;
                }

                entriesByItemId[ itemId ] = entries;
            } );
        }

        if (
            ! valid
            || Object.keys( containersByItemId ).length !== Object.keys( entriesByItemId ).length
        ) {
            appendThankyouStatus( firstContainer, 'blocked' );
            return;
        }

        Object.keys( entriesByItemId ).forEach( function( itemId ) {
            appendThankyouEntries( containersByItemId[ itemId ], entriesByItemId[ itemId ] );
        } );
    }

    function renderItems( container, items, aggregateStatus ) {
        if ( 'thankyou_items' === text( config.presentation ) ) {
            renderThankyouItems( items, aggregateStatus );
            return;
        }

        var heading = element( 'h2', 'wctf-giftcard-delivery-heading', config.labels.heading );

        container.replaceChildren( heading );

        if ( ! Array.isArray( items ) || 0 === items.length ) {
            var blockedArticle = element( 'article', 'wctf-giftcard-delivery-item is-blocked' );
            var blockedStatus = element( 'div', 'wctf-giftcard-delivery-status' );
            var blockedContent = element( 'div', 'wctf-giftcard-delivery-status__content' );

            blockedContent.appendChild( element( 'h3', 'wctf-giftcard-delivery-status__title', config.labels.blockedTitle ) );
            blockedContent.appendChild( element( 'p', 'wctf-giftcard-delivery-message', config.labels.blocked ) );
            blockedStatus.appendChild( blockedContent );
            blockedArticle.appendChild( blockedStatus );
            container.appendChild( blockedArticle );
            return;
        }

        items.forEach( function( item ) {
            var status = text( item && item.status );
            var entries = item && Array.isArray( item.entries ) ? item.entries : [];
            var article;

            if ( 'ready' !== status && 'preparing' !== status ) {
                status = 'blocked';
            }

            if ( 'ready' === status && ! entries.length ) {
                status = 'blocked';
            }

            article = element( 'article', 'wctf-giftcard-delivery-item is-' + status );

            article.appendChild( element( 'h3', 'wctf-giftcard-delivery-product', item && item.product_name ) );

            if ( 'ready' === status && entries.length ) {
                entries.slice( 0, 100 ).forEach( function( entry, index ) {
                    var entryWrap = element( 'div', 'wctf-giftcard-delivery-entry' );
                    var value = element( 'pre', 'wctf-giftcard-delivery-value', entry );
                    var copyButton = element( 'button', 'button wctf-giftcard-delivery-copy', config.labels.copy );

                    copyButton.type = 'button';
                    copyButton.setAttribute( 'aria-label', config.labels.copyLabel );

                    if ( 1 < entries.length ) {
                        entryWrap.appendChild( element( 'strong', 'wctf-giftcard-delivery-label', config.labels.card + ' ' + ( index + 1 ) ) );
                    }

                    entryWrap.appendChild( value );
                    entryWrap.appendChild( copyButton );
                    article.appendChild( entryWrap );
                } );

                article.appendChild( element( 'p', 'wctf-giftcard-delivery-safe', config.labels.keepSafe ) );
            } else if ( 'preparing' === status ) {
                var preparingStatus = element( 'div', 'wctf-giftcard-delivery-status' );
                var spinner = element( 'span', 'wctf-giftcard-delivery-spinner' );
                var preparingContent = element( 'div', 'wctf-giftcard-delivery-status__content' );

                spinner.setAttribute( 'aria-hidden', 'true' );
                preparingContent.appendChild( element( 'h4', 'wctf-giftcard-delivery-status__title', config.labels.preparingTitle ) );
                preparingContent.appendChild( element( 'p', 'wctf-giftcard-delivery-message', config.labels.preparing ) );
                preparingContent.appendChild( element( 'p', 'wctf-giftcard-delivery-note', config.labels.preparingNote ) );
                preparingStatus.appendChild( spinner );
                preparingStatus.appendChild( preparingContent );
                article.appendChild( preparingStatus );
            } else {
                var blockedItemStatus = element( 'div', 'wctf-giftcard-delivery-status' );
                var blockedItemContent = element( 'div', 'wctf-giftcard-delivery-status__content' );

                blockedItemContent.appendChild( element( 'h4', 'wctf-giftcard-delivery-status__title', config.labels.blockedTitle ) );
                blockedItemContent.appendChild( element( 'p', 'wctf-giftcard-delivery-message', config.labels.blocked ) );
                blockedItemStatus.appendChild( blockedItemContent );
                article.appendChild( blockedItemStatus );
            }

            container.appendChild( article );
        } );
    }

    function fallbackCopyVisibleValue( valueNode ) {
        return new Promise( function( resolve, reject ) {
            var selection = window.getSelection ? window.getSelection() : null;
            var savedRanges = [];
            var range;
            var copied = false;

            if ( ! selection || ! document.createRange || ! document.execCommand ) {
                reject();
                return;
            }

            for ( var index = 0; index < selection.rangeCount; index += 1 ) {
                savedRanges.push( selection.getRangeAt( index ).cloneRange() );
            }

            range = document.createRange();
            range.selectNodeContents( valueNode );
            selection.removeAllRanges();
            selection.addRange( range );

            try {
                copied = document.execCommand( 'copy' );
            } catch ( error ) {
                copied = false;
            }

            selection.removeAllRanges();
            savedRanges.forEach( function( savedRange ) {
                selection.addRange( savedRange );
            } );

            if ( copied ) {
                resolve();
            } else {
                reject();
            }
        } );
    }

    function resetThankyouCopyButton( button ) {
        button.replaceChildren( copyIcon() );
        button.classList.remove( 'is-copied' );
        button.setAttribute( 'aria-label', config.labels.copyLabel );
        button.setAttribute( 'title', config.labels.copyLabel );
        button.wctfThankyouCopyResetTimer = null;
    }

    function copyVisibleValue( button ) {
        var valueNode = button.previousElementSibling;
        var copyPromise;

        if (
            ! valueNode
            || (
                ! valueNode.classList.contains( 'wctf-giftcard-delivery-value' )
                && ! valueNode.classList.contains( 'wctf-thankyou-code-value' )
            )
        ) {
            return;
        }

        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            copyPromise = navigator.clipboard.writeText( valueNode.textContent || '' );
        } else {
            copyPromise = fallbackCopyVisibleValue( valueNode );
        }

        copyPromise.then( function() {
            if ( button.classList.contains( 'wctf-thankyou-code-copy' ) ) {
                if ( button.wctfThankyouCopyResetTimer ) {
                    window.clearTimeout( button.wctfThankyouCopyResetTimer );
                }

                button.replaceChildren( checkIcon() );
                button.classList.add( 'is-copied' );
                button.setAttribute( 'aria-label', config.labels.copiedLabel );
                button.setAttribute( 'title', config.labels.copiedLabel );
                button.wctfThankyouCopyResetTimer = window.setTimeout( function() {
                    resetThankyouCopyButton( button );
                }, 2000 );
                return;
            }

            var original = button.textContent;

            button.textContent = config.labels.copied;
            window.setTimeout( function() {
                button.textContent = original;
            }, 1500 );
        } ).then( null, function() {} );
    }

    function start() {
        var container = document.getElementById( text( config.containerId ) );
        var isThankyouItems = 'thankyou_items' === text( config.presentation );
        var copyContainers = isThankyouItems ? promoteThankyouContainers() : ( container ? [ container ] : [] );
        var startedAt = Date.now();
        var stopped = 'preparing' !== text( config.initialStatus );
        var interval = Math.max( 5000, Number( config.pollInterval ) || 5000 );
        var earlyInterval = Math.min( interval, Math.max( 1000, Number( config.earlyPollInterval ) || 2000 ) );
        var earlyTime = Math.min( 45000, Math.max( 12000, Number( config.earlyPollTime ) || 45000 ) );
        var maxTime = Math.min( 120000, Math.max( interval, Number( config.maxPollTime ) || 120000 ) );
        var timer = null;

        if ( ! copyContainers.length ) {
            return;
        }

        copyContainers.forEach( function( copyContainer ) {
            if ( copyContainer.wctfGiftCardDeliveryClickBound ) {
                return;
            }

            copyContainer.wctfGiftCardDeliveryClickBound = true;
            copyContainer.addEventListener( 'click', function( event ) {
                var button = event.target.closest
                    ? event.target.closest( '.wctf-giftcard-delivery-copy, .wctf-thankyou-code-copy' )
                    : null;

                if ( button && copyContainer.contains( button ) ) {
                    copyVisibleValue( button );
                }
            } );
        } );

        function stop() {
            stopped = true;

            if ( timer ) {
                window.clearTimeout( timer );
                timer = null;
            }
        }

        function schedule() {
            var elapsed = Date.now() - startedAt;
            var nextInterval = elapsed < earlyTime ? earlyInterval : interval;

            if ( stopped || elapsed >= maxTime ) {
                stop();
                return;
            }

            timer = window.setTimeout( poll, nextInterval );
        }

        function poll() {
            var body;

            if ( stopped || Date.now() - startedAt >= maxTime ) {
                stop();
                return;
            }

            body = new URLSearchParams();
            body.set( 'order_id', text( config.orderId ) );
            body.set( 'order_key', text( config.orderKey ) );
            body.set( 'nonce', text( config.nonce ) );

            window.fetch( text( config.endpointUrl ), {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            } ).then( function( response ) {
                return response.json();
            } ).then( function( response ) {
                var data = response && response.success && response.data ? response.data : null;

                if ( ! data ) {
                    stop();
                    renderItems( container, [], 'blocked' );
                    return;
                }

                renderItems( container, data.items, data.status );

                if ( 'preparing' === text( data.status ) ) {
                    schedule();
                } else {
                    stop();
                }
            } ).then( null, function() {
                schedule();
            } );
        }

        if ( ! stopped ) {
            poll();
        }
    }

    if ( 'loading' === document.readyState ) {
        document.addEventListener( 'DOMContentLoaded', start );
    } else {
        start();
    }
}() );
