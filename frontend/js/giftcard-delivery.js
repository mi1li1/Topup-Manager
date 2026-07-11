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

    function renderItems( container, items ) {
        var heading = element( 'h2', '', config.labels.heading );

        container.replaceChildren( heading );

        if ( ! Array.isArray( items ) || 0 === items.length ) {
            container.appendChild( element( 'p', 'wctf-giftcard-delivery-message', config.labels.blocked ) );
            return;
        }

        items.forEach( function( item ) {
            var article = element( 'article', 'wctf-giftcard-delivery-item' );
            var status = text( item && item.status );
            var entries = item && Array.isArray( item.entries ) ? item.entries : [];

            article.appendChild( element( 'h3', '', item && item.product_name ) );

            if ( 'ready' === status && entries.length ) {
                entries.slice( 0, 100 ).forEach( function( entry, index ) {
                    var entryWrap = element( 'div', 'wctf-giftcard-delivery-entry' );
                    var value = element( 'pre', 'wctf-giftcard-delivery-value', entry );
                    var copyButton = element( 'button', 'button wctf-giftcard-delivery-copy', config.labels.copy );

                    copyButton.type = 'button';
                    entryWrap.appendChild( element( 'strong', '', config.labels.card + ' #' + ( index + 1 ) ) );
                    entryWrap.appendChild( value );
                    entryWrap.appendChild( copyButton );
                    article.appendChild( entryWrap );
                } );

                article.appendChild( element( 'p', 'wctf-giftcard-delivery-safe', config.labels.keepSafe ) );
            } else if ( 'preparing' === status ) {
                article.appendChild( element( 'p', 'wctf-giftcard-delivery-message', config.labels.preparing ) );
            } else {
                article.appendChild( element( 'p', 'wctf-giftcard-delivery-message', config.labels.blocked ) );
            }

            container.appendChild( article );
        } );
    }

    function copyVisibleValue( button ) {
        var valueNode = button.previousElementSibling;

        if ( ! valueNode || ! valueNode.classList.contains( 'wctf-giftcard-delivery-value' ) ) {
            return;
        }

        if ( ! navigator.clipboard || ! navigator.clipboard.writeText ) {
            return;
        }

        navigator.clipboard.writeText( valueNode.textContent || '' ).then( function() {
            var original = button.textContent;

            button.textContent = config.labels.copied;
            window.setTimeout( function() {
                button.textContent = original;
            }, 1500 );
        } ).catch( function() {} );
    }

    function start() {
        var container = document.getElementById( text( config.containerId ) );
        var startedAt = Date.now();
        var stopped = 'preparing' !== text( config.initialStatus );
        var interval = Math.max( 5000, Number( config.pollInterval ) || 5000 );
        var maxTime = Math.min( 120000, Math.max( interval, Number( config.maxPollTime ) || 120000 ) );
        var timer = null;

        if ( ! container ) {
            return;
        }

        container.addEventListener( 'click', function( event ) {
            var button = event.target.closest
                ? event.target.closest( '.wctf-giftcard-delivery-copy' )
                : null;

            if ( button && container.contains( button ) ) {
                copyVisibleValue( button );
            }
        } );

        function stop() {
            stopped = true;

            if ( timer ) {
                window.clearTimeout( timer );
                timer = null;
            }
        }

        function schedule() {
            if ( stopped || Date.now() - startedAt >= maxTime ) {
                stop();
                return;
            }

            timer = window.setTimeout( poll, interval );
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
                    renderItems( container, [] );
                    return;
                }

                renderItems( container, data.items );

                if ( 'preparing' === text( data.status ) ) {
                    schedule();
                } else {
                    stop();
                }
            } ).catch( function() {
                schedule();
            } );
        }

        if ( ! stopped ) {
            schedule();
        }
    }

    if ( 'loading' === document.readyState ) {
        document.addEventListener( 'DOMContentLoaded', start );
    } else {
        start();
    }
}() );
