<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action(
    'woocommerce_order_details_after_order_table',
    'wctf_render_fazercards_giftcard_customer_delivery_box',
    20,
    1
);
add_action(
    'woocommerce_order_item_meta_end',
    'wctf_render_fazercards_giftcard_customer_delivery_item_fallback',
    20,
    4
);
add_action( 'woocommerce_view_order', 'wctf_render_fazercards_giftcard_customer_delivery_by_order_id', 20, 1 );
add_action( 'woocommerce_thankyou', 'wctf_render_fazercards_giftcard_customer_delivery_by_order_id', 20, 1 );
add_action( 'add_meta_boxes', 'wctf_register_fazercards_giftcard_customer_delivery_diagnostics' );
add_action(
    'wctf_fazercards_giftcard_ready_to_deliver',
    'wctf_schedule_fazercards_giftcard_customer_delivery',
    10,
    2
);
add_action(
    'wctf_fazercards_giftcard_customer_delivery',
    'wctf_run_fazercards_giftcard_customer_delivery',
    10,
    2
);
add_action(
    'admin_post_wctf_fazercards_giftcard_send_customer_delivery',
    'wctf_handle_fazercards_giftcard_send_customer_delivery'
);
add_action(
    'admin_post_wctf_fazercards_giftcard_resend_customer_delivery',
    'wctf_handle_fazercards_giftcard_resend_customer_delivery'
);
add_action(
    'wc_ajax_wctf_fazercards_giftcard_delivery_status',
    'wctf_handle_fazercards_giftcard_customer_delivery_status'
);
add_action(
    'wc_ajax_nopriv_wctf_fazercards_giftcard_delivery_status',
    'wctf_handle_fazercards_giftcard_customer_delivery_status'
);
add_action( 'template_redirect', 'wctf_maybe_disable_fazercards_giftcard_customer_page_cache', 1 );

/**
 * Return the request's WooCommerce order key without logging or storing it.
 *
 * @return string
 */
function wctf_get_fazercards_giftcard_customer_request_order_key() {
    $candidates = array();

    if ( isset( $_POST['order_key'] ) && is_scalar( $_POST['order_key'] ) ) {
        $candidates[] = wp_unslash( $_POST['order_key'] );
    }

    if ( isset( $_GET['key'] ) && is_scalar( $_GET['key'] ) ) {
        $candidates[] = wp_unslash( $_GET['key'] );
    }

    if ( isset( $_GET['order_key'] ) && is_scalar( $_GET['order_key'] ) ) {
        $candidates[] = wp_unslash( $_GET['order_key'] );
    }

    foreach ( $candidates as $candidate ) {
        $candidate = wc_clean( (string) $candidate );

        if ( '' !== $candidate ) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Determine whether the current frontend request may view one order's secrets.
 *
 * @param WC_Order $order             WooCommerce order.
 * @param string   $provided_order_key Optional supplied order key.
 * @return bool
 */
function wctf_is_fazercards_giftcard_customer_authorized( $order, $provided_order_key = '' ) {
    if ( ! $order instanceof WC_Order ) {
        return false;
    }

    $customer_id = absint( $order->get_customer_id() );

    if (
        0 < $customer_id
        && is_user_logged_in()
        && $customer_id === get_current_user_id()
    ) {
        return true;
    }

    if ( 0 < $customer_id ) {
        return false;
    }

    $provided_order_key = is_scalar( $provided_order_key )
        ? wc_clean( (string) $provided_order_key )
        : '';
    $stored_order_key   = (string) $order->get_order_key();

    return '' !== $provided_order_key
        && '' !== $stored_order_key
        && hash_equals( $stored_order_key, $provided_order_key );
}

/**
 * Detect a Gift Card line item without relying on current product data alone.
 *
 * Current product data is used only as a final diagnostic/rendering hint. It
 * never replaces the immutable order-item snapshot for secret validation.
 *
 * @param WC_Order_Item_Product $item WooCommerce order line item.
 * @return bool
 */
function wctf_is_fazercards_giftcard_customer_order_item( $item ) {
    if ( ! $item instanceof WC_Order_Item_Product ) {
        return false;
    }

    if ( 'giftcard' === sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) ) ) {
        return true;
    }

    if (
        '' !== sanitize_text_field( (string) $item->get_meta( '_wctf_fazer_giftcard_card_id', true ) )
        || '' !== sanitize_text_field( (string) $item->get_meta( '_wctf_fazer_giftcard_snapshot_created_at', true ) )
    ) {
        return true;
    }

    $product = $item->get_product();

    return $product instanceof WC_Product
        && 'giftcard' === sanitize_key( (string) $product->get_meta( '_topup_type', true ) )
        && 'giftcard' === sanitize_key( (string) $product->get_meta( '_wctf_fazer_product_kind', true ) );
}

/**
 * Determine whether WooCommerce treats an order as paid.
 *
 * Some paid orders do not retain a paid date, so the paid-status list is an
 * accepted fallback to WC_Order::is_paid().
 *
 * @param WC_Order $order WooCommerce order.
 * @return bool
 */
function wctf_is_fazercards_giftcard_customer_order_paid( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return false;
    }

    if ( $order->is_paid() ) {
        return true;
    }

    $paid_statuses = function_exists( 'wc_get_is_paid_statuses' )
        ? array_map( 'sanitize_key', (array) wc_get_is_paid_statuses() )
        : array();

    return in_array( sanitize_key( (string) $order->get_status() ), $paid_statuses, true );
}

/**
 * Return the customer-status nonce action for the authorized order context.
 *
 * @param WC_Order $order     WooCommerce order.
 * @param string   $order_key Valid supplied guest order key.
 * @return string
 */
function wctf_get_fazercards_giftcard_customer_nonce_action( $order, $order_key = '' ) {
    if ( ! $order instanceof WC_Order ) {
        return '';
    }

    $customer_id = absint( $order->get_customer_id() );

    if ( 0 < $customer_id && is_user_logged_in() && $customer_id === get_current_user_id() ) {
        $context = 'user_' . $customer_id;
    } else {
        if ( 0 < $customer_id ) {
            return '';
        }

        $order_key = is_scalar( $order_key ) ? wc_clean( (string) $order_key ) : '';

        if ( '' === $order_key || ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
            return '';
        }

        $context = 'guest_' . substr( hash_hmac( 'sha256', $order_key, wp_salt( 'nonce' ) ), 0, 24 );
    }

    return 'wctf_gc_customer_delivery_' . absint( $order->get_id() ) . '_' . $context;
}

/**
 * Normalize one card/code entry for temporary customer rendering.
 *
 * @param mixed $entry One top-level cards/codes entry.
 * @return string|WP_Error
 */
function wctf_normalize_fazercards_giftcard_customer_entry( $entry ) {
    if ( is_object( $entry ) ) {
        $entry = get_object_vars( $entry );
    }

    if ( is_array( $entry ) ) {
        $value = wp_json_encode(
            $entry,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );

        if ( false === $value ) {
            return new WP_Error( 'wctf_giftcard_customer_entry_invalid' );
        }
    } elseif ( is_bool( $entry ) ) {
        $value = $entry ? 'true' : 'false';
    } elseif ( null === $entry ) {
        $value = 'null';
    } elseif ( is_scalar( $entry ) ) {
        $value = (string) $entry;
    } else {
        return new WP_Error( 'wctf_giftcard_customer_entry_invalid' );
    }

    $value = wp_check_invalid_utf8( $value, true );
    $value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );

    if ( ! is_string( $value ) || '' === $value || 32768 < strlen( $value ) ) {
        return new WP_Error( 'wctf_giftcard_customer_entry_size_invalid' );
    }

    return $value;
}

/**
 * Authenticate, decrypt and extract only customer-displayable cards/codes.
 *
 * Returned plaintext exists in memory only and must never be persisted.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order item.
 * @param int                   $item_id WooCommerce order item ID.
 * @return array|WP_Error
 */
function wctf_get_fazercards_giftcard_customer_delivery_data( $order, $item, $item_id ) {
    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        return new WP_Error( 'wctf_giftcard_customer_context_invalid' );
    }

    $item_id = absint( $item_id );
    $owned   = $order->get_item( $item_id );

    if (
        ! $owned instanceof WC_Order_Item_Product
        || absint( $owned->get_id() ) !== absint( $item->get_id() )
        || 'giftcard' !== sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) )
        || ! wctf_is_fazercards_giftcard_customer_order_paid( $order )
        || 'ready_to_deliver' !== sanitize_key(
            (string) $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true )
        )
        || ! function_exists( 'wctf_fazercards_giftcard_has_secret_payload' )
        || ! wctf_fazercards_giftcard_has_secret_payload( $item )
        || ! function_exists( 'wctf_fazercards_giftcard_crypto_status' )
        || ! function_exists( 'wctf_fazercards_giftcard_get_secret_payload_wrapper' )
        || ! function_exists( 'wctf_fazercards_giftcard_detect_codes_count' )
    ) {
        return new WP_Error( 'wctf_giftcard_customer_not_ready' );
    }

    $crypto_status = wctf_fazercards_giftcard_crypto_status();

    if ( empty( $crypto_status['ready'] ) ) {
        return new WP_Error( 'wctf_giftcard_customer_crypto_not_ready' );
    }

    $wrapper = wctf_fazercards_giftcard_get_secret_payload_wrapper( $item );

    if (
        is_wp_error( $wrapper )
        || ! is_array( $wrapper )
        || ! isset(
            $wrapper['schema'],
            $wrapper['woocommerce_order_id'],
            $wrapper['woocommerce_order_item_id'],
            $wrapper['order']
        )
        || 'wctf-giftcard-secret-v1' !== $wrapper['schema']
        || absint( $order->get_id() ) !== absint( $wrapper['woocommerce_order_id'] )
        || $item_id !== absint( $wrapper['woocommerce_order_item_id'] )
        || ! is_array( $wrapper['order'] )
        || empty( $wrapper['order'] )
    ) {
        unset( $wrapper );
        return new WP_Error( 'wctf_giftcard_customer_wrapper_invalid' );
    }

    $order_payload = $wrapper['order'];
    $detected      = wctf_fazercards_giftcard_detect_codes_count( $order_payload );

    if ( isset( $order_payload['cards'] ) && is_array( $order_payload['cards'] ) && ! empty( $order_payload['cards'] ) ) {
        $mode    = 'cards';
        $entries = array_values( $order_payload['cards'] );
    } elseif ( isset( $order_payload['codes'] ) && is_array( $order_payload['codes'] ) && ! empty( $order_payload['codes'] ) ) {
        $mode    = 'codes';
        $entries = array_values( $order_payload['codes'] );
    } else {
        unset( $wrapper, $order_payload );
        return new WP_Error( 'wctf_giftcard_customer_sections_missing' );
    }

    if ( null === $detected || 1 > absint( $detected ) || 100 < count( $entries ) ) {
        unset( $wrapper, $order_payload, $entries );
        return new WP_Error( 'wctf_giftcard_customer_count_invalid' );
    }

    $normalized = array();
    $total_size = 0;

    foreach ( $entries as $entry ) {
        $value = wctf_normalize_fazercards_giftcard_customer_entry( $entry );

        if ( is_wp_error( $value ) ) {
            unset( $wrapper, $order_payload, $entries, $normalized );
            return $value;
        }

        $total_size += strlen( $value );

        if ( 262144 < $total_size ) {
            unset( $wrapper, $order_payload, $entries, $normalized, $value );
            return new WP_Error( 'wctf_giftcard_customer_payload_too_large' );
        }

        $normalized[] = $value;
    }

    $result = array(
        'mode'         => $mode,
        'product_name' => sanitize_text_field( $item->get_name() ),
        'entries'      => $normalized,
    );

    unset( $wrapper, $order_payload, $entries, $normalized );
    return $result;
}

/**
 * Build the current customer-safe display state for all Gift Card line items.
 *
 * @param WC_Order $order WooCommerce order.
 * @return array
 */
function wctf_get_fazercards_giftcard_customer_order_state( $order ) {
    $result = array(
        'status' => 'blocked',
        'items'  => array(),
    );

    if ( ! $order instanceof WC_Order ) {
        return $result;
    }

    $has_preparing = false;
    $has_blocked   = false;
    $order_is_paid = wctf_is_fazercards_giftcard_customer_order_paid( $order );

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( ! wctf_is_fazercards_giftcard_customer_order_item( $item ) ) {
            continue;
        }

        $item_state = array(
            'item_id'      => absint( $item_id ),
            'product_name' => sanitize_text_field( $item->get_name() ),
            'status'       => 'preparing',
            'mode'         => '',
            'entries'      => array(),
        );
        $fulfillment_status = sanitize_key(
            (string) $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true )
        );

        if ( ! $order_is_paid ) {
            $item_state['status'] = 'blocked';
            $has_blocked          = true;
            $result['items'][]    = $item_state;
            continue;
        }

        if ( 'ready_to_deliver' !== $fulfillment_status ) {
            $has_preparing    = true;
            $result['items'][] = $item_state;
            continue;
        }

        $delivery_data = wctf_get_fazercards_giftcard_customer_delivery_data( $order, $item, $item_id );

        if ( is_wp_error( $delivery_data ) ) {
            $item_state['status'] = 'blocked';
            $has_blocked          = true;
        } else {
            $item_state['status']  = 'ready';
            $item_state['mode']    = $delivery_data['mode'];
            $item_state['entries'] = $delivery_data['entries'];
        }

        $result['items'][] = $item_state;
        unset( $delivery_data );
    }

    if ( empty( $result['items'] ) ) {
        return $result;
    }

    if ( $has_preparing ) {
        $result['status'] = 'preparing';
    } elseif ( $has_blocked ) {
        $result['status'] = 'blocked';
    } else {
        $result['status'] = 'ready';
    }

    return $result;
}

/**
 * Render one authorized customer Gift Card delivery box.
 *
 * @param WC_Order $order WooCommerce order.
 * @return void
 */
function wctf_render_fazercards_giftcard_customer_delivery_box( $order ) {
    if ( is_admin() || ! $order instanceof WC_Order ) {
        return;
    }

    $order_id = absint( $order->get_id() );

    if ( wctf_has_rendered_fazercards_giftcard_customer_delivery_box( $order_id ) ) {
        return;
    }

    $order_key = wctf_get_fazercards_giftcard_customer_request_order_key();

    if ( ! wctf_is_fazercards_giftcard_customer_authorized( $order, $order_key ) ) {
        return;
    }

    $state = wctf_get_fazercards_giftcard_customer_order_state( $order );

    if ( empty( $state['items'] ) ) {
        return;
    }

    wctf_has_rendered_fazercards_giftcard_customer_delivery_box( $order_id, true );
    wctf_enqueue_fazercards_giftcard_customer_delivery_script( $order, $order_key, $state['status'] );

    echo '<section id="wctf-giftcard-delivery-' . esc_attr( $order_id ) . '" class="wctf-giftcard-delivery" aria-live="polite">';
    echo '<h2>' . esc_html__( 'Your Gift Cards', 'wc-topup-fields' ) . '</h2>';
    wctf_render_fazercards_giftcard_customer_items( $state['items'] );
    echo '</section>';

    unset( $state );
}

/**
 * Render the customer delivery box from an explicit WooCommerce order hook.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function wctf_render_fazercards_giftcard_customer_delivery_by_order_id( $order_id ) {
    $order = wc_get_order( absint( $order_id ) );

    if ( $order instanceof WC_Order ) {
        wctf_render_fazercards_giftcard_customer_delivery_box( $order );
    }
}

/**
 * Track customer delivery rendering once per order for the current request.
 *
 * @param int  $order_id WooCommerce order ID.
 * @param bool $mark     Whether to mark the order as rendered.
 * @return bool
 */
function wctf_has_rendered_fazercards_giftcard_customer_delivery_box( $order_id, $mark = false ) {
    static $rendered_orders = array();

    $order_id = absint( $order_id );

    if ( $mark && 0 < $order_id ) {
        $rendered_orders[ $order_id ] = true;
    }

    return 0 < $order_id && isset( $rendered_orders[ $order_id ] );
}

/**
 * Render through the order-item hook when a theme omits the primary hook.
 *
 * The endpoint guard prevents this shared WooCommerce hook from rendering
 * Gift Card secrets in emails or unrelated frontend contexts.
 *
 * @param int                   $item_id   WooCommerce order item ID.
 * @param WC_Order_Item_Product $item      WooCommerce order item.
 * @param WC_Order              $order     WooCommerce order.
 * @param bool                  $plain_text Whether the current output is plain text.
 * @return void
 */
function wctf_render_fazercards_giftcard_customer_delivery_item_fallback( $item_id, $item, $order, $plain_text ) {
    unset( $item_id );

    if (
        $plain_text
        || is_admin()
        || ! $item instanceof WC_Order_Item_Product
        || ! $order instanceof WC_Order
        || ! wctf_is_fazercards_giftcard_customer_order_item( $item )
        || ! function_exists( 'is_order_received_page' )
        || ! function_exists( 'is_wc_endpoint_url' )
        || ( ! is_order_received_page() && ! is_wc_endpoint_url( 'view-order' ) )
    ) {
        return;
    }

    wctf_render_fazercards_giftcard_customer_delivery_box( $order );
}

/**
 * Register safe customer-delivery diagnostics for classic and HPOS orders.
 *
 * @return void
 */
function wctf_register_fazercards_giftcard_customer_delivery_diagnostics() {
    $screens = array( 'shop_order' );

    if ( function_exists( 'wc_get_page_screen_id' ) ) {
        $screens[] = wc_get_page_screen_id( 'shop-order' );
    }

    foreach ( array_unique( $screens ) as $screen ) {
        add_meta_box(
            'wctf-fazercards-giftcard-customer-delivery-diagnostics',
            __( 'Gift Card Customer Delivery Diagnostics', 'wc-topup-fields' ),
            'wctf_render_fazercards_giftcard_customer_delivery_diagnostics',
            $screen,
            'normal',
            'default'
        );
        add_meta_box(
            'wctf-fazercards-giftcard-customer-delivery',
            __( 'FazerCards Gift Card Customer Delivery', 'wc-topup-fields' ),
            'wctf_render_fazercards_giftcard_customer_delivery_admin_meta_box',
            $screen,
            'normal',
            'default'
        );
    }
}

/**
 * Build credential-free customer-delivery diagnostics for one order item.
 *
 * The decrypted wrapper is inspected only in memory and is never returned.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order line item.
 * @param int                   $item_id WooCommerce order item ID.
 * @return array
 */
function wctf_get_fazercards_giftcard_customer_delivery_diagnostics( $order, $item, $item_id ) {
    $paid_statuses = function_exists( 'wc_get_is_paid_statuses' )
        ? array_map( 'sanitize_key', (array) wc_get_is_paid_statuses() )
        : array();
    $kind = sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) );
    $fulfillment_status = sanitize_key(
        (string) $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true )
    );
    $has_secret = function_exists( 'wctf_fazercards_giftcard_has_secret_payload' )
        && wctf_fazercards_giftcard_has_secret_payload( $item );
    $crypto_ready = false;
    $context_valid = false;
    $codes_count = null;

    if ( function_exists( 'wctf_fazercards_giftcard_crypto_status' ) ) {
        $crypto_status = wctf_fazercards_giftcard_crypto_status();
        $crypto_ready  = is_array( $crypto_status ) && ! empty( $crypto_status['ready'] );
        unset( $crypto_status );
    }

    if (
        $has_secret
        && $crypto_ready
        && function_exists( 'wctf_fazercards_giftcard_get_secret_payload_wrapper' )
        && function_exists( 'wctf_fazercards_giftcard_detect_codes_count' )
    ) {
        $wrapper = wctf_fazercards_giftcard_get_secret_payload_wrapper( $item );

        if (
            is_array( $wrapper )
            && isset(
                $wrapper['schema'],
                $wrapper['woocommerce_order_id'],
                $wrapper['woocommerce_order_item_id'],
                $wrapper['order']
            )
            && 'wctf-giftcard-secret-v1' === $wrapper['schema']
            && absint( $order->get_id() ) === absint( $wrapper['woocommerce_order_id'] )
            && absint( $item_id ) === absint( $wrapper['woocommerce_order_item_id'] )
            && is_array( $wrapper['order'] )
            && ! empty( $wrapper['order'] )
        ) {
            $context_valid = true;
            $codes_count   = wctf_fazercards_giftcard_detect_codes_count( $wrapper['order'] );
        }

        unset( $wrapper );
    }

    $is_paid          = (bool) $order->is_paid();
    $paid_status_match = in_array(
        sanitize_key( (string) $order->get_status() ),
        $paid_statuses,
        true
    );
    $paid_validation = $is_paid || $paid_status_match;
    $delivery_data   = wctf_get_fazercards_giftcard_customer_delivery_data( $order, $item, $item_id );
    $display_ready   = ! is_wp_error( $delivery_data );

    if ( 'giftcard' !== $kind ) {
        $reason = __( 'Gift Card order-item kind snapshot is missing.', 'wc-topup-fields' );
    } elseif ( ! $paid_validation ) {
        $reason = __( 'The order does not pass paid validation.', 'wc-topup-fields' );
    } elseif ( 'ready_to_deliver' !== $fulfillment_status ) {
        $reason = __( 'Gift Card fulfillment is still preparing.', 'wc-topup-fields' );
    } elseif ( ! $has_secret ) {
        $reason = __( 'The encrypted Gift Card payload is missing.', 'wc-topup-fields' );
    } elseif ( ! $crypto_ready ) {
        $reason = __( 'Gift Card encryption is not ready.', 'wc-topup-fields' );
    } elseif ( ! $context_valid ) {
        $reason = __( 'Encrypted payload authentication or order-item context validation failed.', 'wc-topup-fields' );
    } elseif ( null === $codes_count || 1 > absint( $codes_count ) ) {
        $reason = __( 'No customer delivery cards or codes were detected.', 'wc-topup-fields' );
    } elseif ( ! $display_ready ) {
        $reason = __( 'Customer delivery payload validation failed.', 'wc-topup-fields' );
    } else {
        $reason = __( 'Customer Gift Card delivery is ready.', 'wc-topup-fields' );
    }

    unset( $delivery_data );

    return array(
        'display_ready'     => $display_ready,
        'item_kind'         => '' === $kind ? 'other' : $kind,
        'paid_validation'   => $paid_validation,
        'order_is_paid'     => $is_paid,
        'paid_status_match' => $paid_status_match,
        'fulfillment_status' => '' === $fulfillment_status ? 'not_set' : $fulfillment_status,
        'has_secret'        => $has_secret,
        'crypto_ready'      => $crypto_ready,
        'context_valid'     => $context_valid,
        'codes_count'       => $codes_count,
        'reason'            => sanitize_text_field( $reason ),
    );
}

/**
 * Render safe admin-only Gift Card customer-delivery diagnostics.
 *
 * @param WP_Post|WC_Order $post_or_order_object Order screen object.
 * @return void
 */
function wctf_render_fazercards_giftcard_customer_delivery_diagnostics( $post_or_order_object ) {
    if ( function_exists( 'wctf_get_fazercards_giftcard_order_from_screen' ) ) {
        $order = wctf_get_fazercards_giftcard_order_from_screen( $post_or_order_object );
    } elseif ( $post_or_order_object instanceof WC_Order ) {
        $order = $post_or_order_object;
    } elseif ( $post_or_order_object instanceof WP_Post ) {
        $order = wc_get_order( $post_or_order_object->ID );
    } else {
        $order = false;
    }

    if ( ! $order instanceof WC_Order ) {
        echo '<p>' . esc_html__( 'The WooCommerce order could not be loaded.', 'wc-topup-fields' ) . '</p>';
        return;
    }

    $order_id = absint( $order->get_id() );

    if (
        ! current_user_can( 'manage_woocommerce' )
        || ( ! current_user_can( 'edit_shop_order', $order_id ) && ! current_user_can( 'edit_post', $order_id ) )
    ) {
        echo '<p>' . esc_html__( 'You are not allowed to view these diagnostics.', 'wc-topup-fields' ) . '</p>';
        return;
    }

    $found = false;

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( ! wctf_is_fazercards_giftcard_customer_order_item( $item ) ) {
            continue;
        }

        $found       = true;
        $diagnostics = wctf_get_fazercards_giftcard_customer_delivery_diagnostics( $order, $item, $item_id );
        $yes_no      = static function ( $value ) {
            return $value ? __( 'Yes', 'wc-topup-fields' ) : __( 'No', 'wc-topup-fields' );
        };
        $rows        = array(
            __( 'Customer display ready', 'wc-topup-fields' )          => $yes_no( $diagnostics['display_ready'] ),
            __( 'Delivery module loaded', 'wc-topup-fields' )          => __( 'Yes', 'wc-topup-fields' ),
            __( 'Item kind', 'wc-topup-fields' )                       => $diagnostics['item_kind'],
            __( 'Paid validation', 'wc-topup-fields' )                 => $diagnostics['paid_validation'] ? __( 'Passed', 'wc-topup-fields' ) : __( 'Failed', 'wc-topup-fields' ),
            __( 'Order is_paid', 'wc-topup-fields' )                   => $yes_no( $diagnostics['order_is_paid'] ),
            __( 'Order status paid-status match', 'wc-topup-fields' )  => $yes_no( $diagnostics['paid_status_match'] ),
            __( 'Fulfillment status', 'wc-topup-fields' )              => $diagnostics['fulfillment_status'],
            __( 'Encrypted secret stored', 'wc-topup-fields' )         => $yes_no( $diagnostics['has_secret'] ),
            __( 'Crypto ready', 'wc-topup-fields' )                    => $yes_no( $diagnostics['crypto_ready'] ),
            __( 'Decrypt/context validation', 'wc-topup-fields' )      => $diagnostics['context_valid'] ? __( 'Passed', 'wc-topup-fields' ) : __( 'Failed', 'wc-topup-fields' ),
            __( 'Detected customer delivery cards/codes count', 'wc-topup-fields' ) => null === $diagnostics['codes_count'] ? __( 'Unknown', 'wc-topup-fields' ) : (string) absint( $diagnostics['codes_count'] ),
            __( 'Last customer display safe reason', 'wc-topup-fields' ) => $diagnostics['reason'],
        );

        echo '<h4>' . esc_html( sprintf( __( 'Order item #%1$d: %2$s', 'wc-topup-fields' ), absint( $item_id ), sanitize_text_field( $item->get_name() ) ) ) . '</h4>';
        echo '<table class="widefat striped"><tbody>';

        foreach ( $rows as $label => $value ) {
            echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
        }

        echo '</tbody></table>';
        unset( $diagnostics, $rows );
    }

    if ( ! $found ) {
        echo '<p>' . esc_html__( 'No Gift Card order items were detected.', 'wc-topup-fields' ) . '</p>';
    }
}

/**
 * Normalize the private customer email delivery status.
 *
 * @param mixed $status Raw delivery status.
 * @return string
 */
function wctf_normalize_fazercards_giftcard_customer_delivery_status( $status ) {
    $status  = sanitize_key( is_scalar( $status ) ? (string) $status : '' );
    $allowed = array( 'not_started', 'ready', 'delivering', 'delivered', 'failed', 'blocked' );

    return in_array( $status, $allowed, true ) ? $status : 'not_started';
}

/**
 * Return a short, non-sensitive delivery error.
 *
 * @param mixed $message Potential error message.
 * @return string
 */
function wctf_sanitize_fazercards_giftcard_delivery_error( $message ) {
    $message = is_scalar( $message ) ? sanitize_text_field( (string) $message ) : '';

    return substr( $message, 0, 300 );
}

/**
 * Return the atomic customer-delivery lock option name.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_customer_delivery_lock_key( $order_id, $item_id ) {
    return 'wctf_fazer_giftcard_delivery_lock_' . absint( $order_id ) . '_' . absint( $item_id );
}

/**
 * Acquire a five-minute atomic customer-delivery lock.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string|WP_Error Owner token or safe error.
 */
function wctf_acquire_fazercards_giftcard_customer_delivery_lock( $order_id, $item_id ) {
    $lock_key = wctf_get_fazercards_giftcard_customer_delivery_lock_key( $order_id, $item_id );
    $token    = function_exists( 'wp_generate_uuid4' )
        ? wp_generate_uuid4()
        : wp_hash( absint( $order_id ) . '|' . absint( $item_id ) . '|' . microtime( true ) );
    $now      = time();
    $value    = wp_json_encode(
        array(
            'owner'      => $token,
            'created_at' => $now,
            'expires_at' => $now + 300,
        )
    );

    if ( add_option( $lock_key, $value, '', 'no' ) ) {
        return $token;
    }

    $existing = json_decode( (string) get_option( $lock_key, '' ), true );

    if ( is_array( $existing ) && isset( $existing['expires_at'] ) && (int) $existing['expires_at'] < $now ) {
        delete_option( $lock_key );

        if ( add_option( $lock_key, $value, '', 'no' ) ) {
            return $token;
        }
    }

    return new WP_Error(
        'wctf_giftcard_delivery_locked',
        __( 'Another Gift Card customer delivery process is already active.', 'wc-topup-fields' )
    );
}

/**
 * Release an atomic lock only when the current process owns it.
 *
 * @param int    $order_id WooCommerce order ID.
 * @param int    $item_id  WooCommerce order item ID.
 * @param string $token    Owner token.
 * @return void
 */
function wctf_release_fazercards_giftcard_customer_delivery_lock( $order_id, $item_id, $token ) {
    $lock_key = wctf_get_fazercards_giftcard_customer_delivery_lock_key( $order_id, $item_id );
    $existing = json_decode( (string) get_option( $lock_key, '' ), true );
    $owner    = is_array( $existing ) && isset( $existing['owner'] ) && is_string( $existing['owner'] )
        ? $existing['owner']
        : '';

    if ( '' !== $owner && '' !== $token && hash_equals( $owner, $token ) ) {
        delete_option( $lock_key );
    }
}

/**
 * Determine whether one customer-delivery job is already scheduled.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return bool
 */
function wctf_is_fazercards_giftcard_customer_delivery_scheduled( $order_id, $item_id ) {
    $hook  = 'wctf_fazercards_giftcard_customer_delivery';
    $args  = array( absint( $order_id ), absint( $item_id ) );
    $group = 'wctf-fazercards-giftcard-delivery';

    if ( function_exists( 'as_has_scheduled_action' ) ) {
        return (bool) as_has_scheduled_action( $hook, $args, $group );
    }

    if ( function_exists( 'as_next_scheduled_action' ) ) {
        return false !== as_next_scheduled_action( $hook, $args, $group );
    }

    return false !== wp_next_scheduled( $hook, $args );
}

/**
 * Schedule a separate customer-delivery job after fulfillment becomes ready.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return void
 */
function wctf_schedule_fazercards_giftcard_customer_delivery( $order_id, $item_id ) {
    $order_id = absint( $order_id );
    $item_id  = absint( $item_id );
    $order    = wc_get_order( $order_id );
    $item     = $order instanceof WC_Order ? $order->get_item( $item_id ) : false;

    if (
        ! $order instanceof WC_Order
        || ! $item instanceof WC_Order_Item_Product
        || absint( $item->get_order_id() ) !== $order_id
        || 'giftcard' !== sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) )
        || 'ready_to_deliver' !== sanitize_key( (string) $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true ) )
    ) {
        return;
    }

    $status = wctf_normalize_fazercards_giftcard_customer_delivery_status(
        $item->get_meta( '_wctf_fazer_giftcard_delivery_status', true )
    );

    if ( in_array( $status, array( 'delivering', 'delivered', 'failed', 'blocked' ), true ) ) {
        return;
    }

    if ( wctf_is_fazercards_giftcard_customer_delivery_scheduled( $order_id, $item_id ) ) {
        return;
    }

    $hook      = 'wctf_fazercards_giftcard_customer_delivery';
    $args      = array( $order_id, $item_id );
    $scheduled = false;

    if ( function_exists( 'as_schedule_single_action' ) ) {
        $scheduled = (bool) as_schedule_single_action(
            time() + 1,
            $hook,
            $args,
            'wctf-fazercards-giftcard-delivery'
        );
    } else {
        $scheduled = (bool) wp_schedule_single_event( time() + 1, $hook, $args );
    }

    if ( $scheduled ) {
        $item->update_meta_data( '_wctf_fazer_giftcard_delivery_status', 'ready' );
        $item->delete_meta_data( '_wctf_fazer_giftcard_last_delivery_error' );
    } else {
        $item->update_meta_data( '_wctf_fazer_giftcard_delivery_status', 'blocked' );
        $item->update_meta_data(
            '_wctf_fazer_giftcard_last_delivery_error',
            __( 'The customer delivery job could not be scheduled.', 'wc-topup-fields' )
        );
    }

    $item->save_meta_data();
}

/**
 * Build the customer email in memory from validated top-level entries only.
 *
 * @param WC_Order $order        WooCommerce order.
 * @param string   $product_name Product name.
 * @param array    $entries      Validated cards/codes entries.
 * @return array Subject and plain-text body.
 */
function wctf_build_fazercards_giftcard_customer_delivery_email( $order, $product_name, $entries ) {
    $order_number = sanitize_text_field( (string) $order->get_order_number() );
    $product_name = sanitize_text_field( (string) $product_name );
    $subject      = sprintf(
        __( 'Gift Card for Order #%1$s - %2$s', 'wc-topup-fields' ),
        $order_number,
        $product_name
    );
    $lines        = array(
        __( 'Hello,', 'wc-topup-fields' ),
        '',
        sprintf( __( 'Your Gift Card for WooCommerce order #%s is ready.', 'wc-topup-fields' ), $order_number ),
        sprintf( __( 'Product: %s', 'wc-topup-fields' ), $product_name ),
        '',
    );

    foreach ( array_values( $entries ) as $index => $entry ) {
        $lines[] = sprintf( __( 'Gift Card #%d:', 'wc-topup-fields' ), absint( $index + 1 ) );
        $lines[] = (string) $entry;
        $lines[] = '';
    }

    $lines[] = __( 'Keep this code safe.', 'wc-topup-fields' );
    $lines[] = __( 'If you need assistance, please contact store support and include your order number.', 'wc-topup-fields' );

    return array(
        'subject' => sanitize_text_field( $subject ),
        'body'    => implode( "\n", $lines ),
    );
}

/**
 * Process one locked customer delivery attempt.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order line item.
 * @param int                   $item_id WooCommerce order item ID.
 * @param string                $mode    auto, send, or resend.
 * @return array Safe result only.
 */
function wctf_process_fazercards_giftcard_customer_delivery( $order, $item, $item_id, $mode = 'auto' ) {
    $mode     = in_array( $mode, array( 'auto', 'send', 'resend' ), true ) ? $mode : 'auto';
    $order_id = $order instanceof WC_Order ? absint( $order->get_id() ) : 0;
    $item_id  = absint( $item_id );
    $result   = array(
        'success' => false,
        'status'  => 'blocked',
        'message' => __( 'The Gift Card customer delivery was not attempted.', 'wc-topup-fields' ),
    );

    if (
        ! $order instanceof WC_Order
        || ! $item instanceof WC_Order_Item_Product
        || absint( $item->get_order_id() ) !== $order_id
        || absint( $item->get_id() ) !== $item_id
    ) {
        return $result;
    }

    $lock_token = wctf_acquire_fazercards_giftcard_customer_delivery_lock( $order_id, $item_id );

    if ( is_wp_error( $lock_token ) ) {
        $result['message'] = wctf_sanitize_fazercards_giftcard_delivery_error( $lock_token->get_error_message() );
        return $result;
    }

    $delivery_data   = null;
    $email_content   = null;
    $mail_attempted  = false;
    $mail_accepted   = false;
    $previous_status = 'not_started';

    try {
        $item            = new WC_Order_Item_Product( $item_id );
        $previous_status = wctf_normalize_fazercards_giftcard_customer_delivery_status(
            $item->get_meta( '_wctf_fazer_giftcard_delivery_status', true )
        );

        if ( 'auto' === $mode && in_array( $previous_status, array( 'delivering', 'delivered', 'failed', 'blocked' ), true ) ) {
            $result['status']  = $previous_status;
            $result['message'] = __( 'Automatic Gift Card customer delivery was skipped safely.', 'wc-topup-fields' );
            return $result;
        }

        if ( 'send' === $mode && in_array( $previous_status, array( 'delivering', 'delivered', 'failed' ), true ) ) {
            $result['status']  = $previous_status;
            $result['message'] = __( 'This item is not eligible for the SEND action.', 'wc-topup-fields' );
            return $result;
        }

        if ( 'resend' === $mode && ! in_array( $previous_status, array( 'delivered', 'failed' ), true ) ) {
            $result['status']  = $previous_status;
            $result['message'] = __( 'This item is not eligible for the RESEND action.', 'wc-topup-fields' );
            return $result;
        }

        $email_to = sanitize_email( (string) $order->get_billing_email() );

        if ( '' === $email_to || ! is_email( $email_to ) ) {
            $invalid_email_status = in_array( $previous_status, array( 'delivered', 'failed' ), true )
                ? $previous_status
                : 'blocked';
            $item->update_meta_data( '_wctf_fazer_giftcard_delivery_status', $invalid_email_status );
            $item->update_meta_data(
                '_wctf_fazer_giftcard_last_delivery_error',
                __( 'The WooCommerce billing email is missing or invalid.', 'wc-topup-fields' )
            );
            $item->save_meta_data();
            $result['status']  = $invalid_email_status;
            $result['message'] = __( 'The WooCommerce billing email is missing or invalid.', 'wc-topup-fields' );
            return $result;
        }

        $item->update_meta_data( '_wctf_fazer_giftcard_delivery_status', 'delivering' );
        $item->save_meta_data();

        $delivery_data = wctf_get_fazercards_giftcard_customer_delivery_data( $order, $item, $item_id );

        if ( is_wp_error( $delivery_data ) ) {
            $fallback_status = in_array( $previous_status, array( 'delivered', 'failed' ), true )
                ? $previous_status
                : 'blocked';
            $item->update_meta_data( '_wctf_fazer_giftcard_delivery_status', $fallback_status );
            $item->update_meta_data(
                '_wctf_fazer_giftcard_last_delivery_error',
                __( 'Gift Card customer delivery validation failed.', 'wc-topup-fields' )
            );
            $item->save_meta_data();
            $result['status']  = $fallback_status;
            $result['message'] = __( 'Gift Card customer delivery validation failed.', 'wc-topup-fields' );
            return $result;
        }

        $email_content = wctf_build_fazercards_giftcard_customer_delivery_email(
            $order,
            $delivery_data['product_name'],
            $delivery_data['entries']
        );
        $attempted_at  = gmdate( 'Y-m-d H:i:s' );
        $attempts      = absint( $item->get_meta( '_wctf_fazer_giftcard_delivery_attempts', true ) ) + 1;

        $item->update_meta_data( '_wctf_fazer_giftcard_delivery_attempts', $attempts );
        $item->update_meta_data( '_wctf_fazer_giftcard_delivery_last_attempt_at', $attempted_at );
        $item->save_meta_data();

        // A true return value means the mail transport accepted the message; it does not guarantee inbox delivery.
        $mail_attempted = true;
        $mail_sent      = wp_mail(
            $email_to,
            $email_content['subject'],
            $email_content['body'],
            array( 'Content-Type: text/plain; charset=UTF-8' )
        );
        $mail_accepted  = (bool) $mail_sent;

        unset( $email_content, $delivery_data );

        if ( $mail_sent ) {
            $delivered_at = gmdate( 'Y-m-d H:i:s' );
            $delivered_by = 'auto' === $mode ? 'auto' : 'manual_' . $mode;

            $item->update_meta_data( '_wctf_fazer_giftcard_delivery_status', 'delivered' );
            $item->update_meta_data( '_wctf_fazer_giftcard_delivered_at', $delivered_at );
            $item->update_meta_data( '_wctf_fazer_giftcard_delivered_by', $delivered_by );
            $item->update_meta_data( '_wctf_fazer_giftcard_delivery_email_to', $email_to );
            $item->delete_meta_data( '_wctf_fazer_giftcard_last_delivery_error' );
            $item->save_meta_data();

            $note = 'resend' === $mode
                ? sprintf( __( 'Gift Card resent to customer for item #%d.', 'wc-topup-fields' ), $item_id )
                : sprintf( __( 'Gift Card delivered to customer for item #%d.', 'wc-topup-fields' ), $item_id );
            $order->add_order_note( sanitize_text_field( $note ), 0, false );

            $result['success'] = true;
            $result['status']  = 'delivered';
            $result['message'] = __( 'The Gift Card email was accepted by the mail transport.', 'wc-topup-fields' );
        } else {
            $failure_status = 'delivered' === $previous_status ? 'delivered' : 'failed';
            $item->update_meta_data( '_wctf_fazer_giftcard_delivery_status', $failure_status );
            $item->update_meta_data(
                '_wctf_fazer_giftcard_last_delivery_error',
                __( 'The WordPress mail transport did not accept the Gift Card email.', 'wc-topup-fields' )
            );
            $item->save_meta_data();
            $result['status']  = $failure_status;
            $result['message'] = __( 'The WordPress mail transport did not accept the Gift Card email.', 'wc-topup-fields' );
        }
    } catch ( Throwable $throwable ) {
        unset( $throwable, $email_content, $delivery_data );

        $item           = new WC_Order_Item_Product( $item_id );
        $current_status = wctf_normalize_fazercards_giftcard_customer_delivery_status(
            $item->get_meta( '_wctf_fazer_giftcard_delivery_status', true )
        );

        if ( 'delivered' === $previous_status || 'delivered' === $current_status ) {
            $failure_status = 'delivered';
        } elseif ( $mail_attempted ) {
            $failure_status = 'delivering';
        } else {
            $failure_status = 'failed' === $previous_status ? 'failed' : 'blocked';
        }

        $failure_message = $mail_accepted
            ? __( 'The mail transport accepted the Gift Card email, but the final delivery status could not be saved. Admin review is required.', 'wc-topup-fields' )
            : __( 'The Gift Card email delivery process failed safely.', 'wc-topup-fields' );

        $item->update_meta_data( '_wctf_fazer_giftcard_delivery_status', $failure_status );
        $item->update_meta_data(
            '_wctf_fazer_giftcard_last_delivery_error',
            $failure_message
        );
        $item->save_meta_data();
        $result['status']  = $failure_status;
        $result['message'] = $failure_message;
    } finally {
        unset( $email_content, $delivery_data );
        wctf_release_fazercards_giftcard_customer_delivery_lock( $order_id, $item_id, $lock_token );
    }

    return $result;
}

/**
 * Run one separately scheduled automatic customer-delivery job.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return void
 */
function wctf_run_fazercards_giftcard_customer_delivery( $order_id, $item_id ) {
    $order = wc_get_order( absint( $order_id ) );
    $item  = $order instanceof WC_Order ? $order->get_item( absint( $item_id ) ) : false;

    if ( $order instanceof WC_Order && $item instanceof WC_Order_Item_Product ) {
        wctf_process_fazercards_giftcard_customer_delivery( $order, $item, absint( $item_id ), 'auto' );
    }
}

/**
 * Return the current user's safe admin delivery notice transient key.
 *
 * @param int $order_id WooCommerce order ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_customer_delivery_notice_key( $order_id ) {
    return 'wctf_gc_delivery_notice_' . absint( $order_id ) . '_' . absint( get_current_user_id() );
}

/**
 * Finish one manual delivery action with a safe notice and redirect.
 *
 * @param WC_Order $order WooCommerce order.
 * @param array    $result Safe result data.
 * @return void
 */
function wctf_finish_fazercards_giftcard_customer_delivery_action( $order, $result ) {
    $notice = array(
        'success' => ! empty( $result['success'] ),
        'message' => isset( $result['message'] )
            ? wctf_sanitize_fazercards_giftcard_delivery_error( $result['message'] )
            : __( 'The Gift Card customer delivery action finished.', 'wc-topup-fields' ),
    );

    set_transient(
        wctf_get_fazercards_giftcard_customer_delivery_notice_key( $order->get_id() ),
        $notice,
        5 * MINUTE_IN_SECONDS
    );

    wp_safe_redirect( $order->get_edit_order_url() );
    exit;
}

/**
 * Handle the confirmed manual SEND action.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_send_customer_delivery() {
    wctf_handle_fazercards_giftcard_customer_delivery_admin_action( 'send' );
}

/**
 * Handle the confirmed manual RESEND action.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_resend_customer_delivery() {
    wctf_handle_fazercards_giftcard_customer_delivery_admin_action( 'resend' );
}

/**
 * Validate and run one admin-only manual customer delivery action.
 *
 * @param string $mode send or resend.
 * @return void
 */
function wctf_handle_fazercards_giftcard_customer_delivery_admin_action( $mode ) {
    $mode     = 'resend' === $mode ? 'resend' : 'send';
    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
        : 0;
    $item_id  = isset( $_POST['item_id'] ) && is_scalar( $_POST['item_id'] )
        ? absint( wp_unslash( $_POST['item_id'] ) )
        : 0;
    $order    = 0 < $order_id ? wc_get_order( $order_id ) : false;

    if ( ! $order instanceof WC_Order ) {
        wp_die(
            esc_html__( 'The WooCommerce order could not be loaded.', 'wc-topup-fields' ),
            esc_html__( 'Invalid order', 'wc-topup-fields' ),
            array( 'response' => 400 )
        );
    }

    if (
        ! current_user_can( 'manage_woocommerce' )
        || ( ! current_user_can( 'edit_shop_order', $order_id ) && ! current_user_can( 'edit_post', $order_id ) )
    ) {
        wp_die(
            esc_html__( 'You are not allowed to deliver Gift Cards for this order.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $nonce = isset( $_POST['nonce'] ) && is_scalar( $_POST['nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
        : '';
    $nonce_action = 'wctf_gc_customer_delivery_' . $mode . '_' . $order_id . '_' . $item_id;

    if ( '' === $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
        wctf_finish_fazercards_giftcard_customer_delivery_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'The customer delivery request reached the server, but its security nonce was invalid.', 'wc-topup-fields' ),
            )
        );
    }

    $item = $order->get_item( $item_id );

    if ( ! $item instanceof WC_Order_Item_Product || absint( $item->get_order_id() ) !== $order_id ) {
        wctf_finish_fazercards_giftcard_customer_delivery_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'The selected order item does not belong to this order.', 'wc-topup-fields' ),
            )
        );
    }

    $confirmed = isset( $_POST['delivery_confirmed'] ) && is_scalar( $_POST['delivery_confirmed'] )
        ? sanitize_text_field( wp_unslash( $_POST['delivery_confirmed'] ) )
        : '';
    $confirmation_text = isset( $_POST['delivery_confirmation_text'] ) && is_scalar( $_POST['delivery_confirmation_text'] )
        ? (string) wp_unslash( $_POST['delivery_confirmation_text'] )
        : '';
    $expected = 'resend' === $mode ? 'RESEND' : 'SEND';

    if ( '1' !== $confirmed || $expected !== $confirmation_text ) {
        wctf_finish_fazercards_giftcard_customer_delivery_action(
            $order,
            array(
                'success' => false,
                'message' => sprintf(
                    __( 'Both the checkbox and exact %s confirmation are required.', 'wc-topup-fields' ),
                    $expected
                ),
            )
        );
    }

    $result = wctf_process_fazercards_giftcard_customer_delivery( $order, $item, $item_id, $mode );
    wctf_finish_fazercards_giftcard_customer_delivery_action( $order, $result );
}

/**
 * Render the admin-only customer delivery status and SEND/RESEND controls.
 *
 * @param WP_Post|WC_Order $post_or_order_object Order screen object.
 * @return void
 */
function wctf_render_fazercards_giftcard_customer_delivery_admin_meta_box( $post_or_order_object ) {
    if ( function_exists( 'wctf_get_fazercards_giftcard_order_from_screen' ) ) {
        $order = wctf_get_fazercards_giftcard_order_from_screen( $post_or_order_object );
    } elseif ( $post_or_order_object instanceof WC_Order ) {
        $order = $post_or_order_object;
    } elseif ( $post_or_order_object instanceof WP_Post ) {
        $order = wc_get_order( $post_or_order_object->ID );
    } else {
        $order = false;
    }

    if ( ! $order instanceof WC_Order ) {
        echo '<p>' . esc_html__( 'The WooCommerce order could not be loaded.', 'wc-topup-fields' ) . '</p>';
        return;
    }

    $order_id = absint( $order->get_id() );

    if (
        ! current_user_can( 'manage_woocommerce' )
        || ( ! current_user_can( 'edit_shop_order', $order_id ) && ! current_user_can( 'edit_post', $order_id ) )
    ) {
        echo '<p>' . esc_html__( 'You are not allowed to manage Gift Card customer delivery.', 'wc-topup-fields' ) . '</p>';
        return;
    }

    $notice_key = wctf_get_fazercards_giftcard_customer_delivery_notice_key( $order_id );
    $notice     = get_transient( $notice_key );

    if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
        $notice_class = ! empty( $notice['success'] ) ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $notice_class ) . ' inline"><p>';
        echo esc_html( wctf_sanitize_fazercards_giftcard_delivery_error( $notice['message'] ) );
        echo '</p></div>';
        delete_transient( $notice_key );
    }

    echo '<p>' . esc_html__( 'Email delivery is independent from the customer order-page display. A successful wp_mail() result means the mail transport accepted the message, not that it reached the inbox.', 'wc-topup-fields' ) . '</p>';

    $found = false;

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( ! wctf_is_fazercards_giftcard_customer_order_item( $item ) ) {
            continue;
        }

        $found          = true;
        $status         = wctf_normalize_fazercards_giftcard_customer_delivery_status(
            $item->get_meta( '_wctf_fazer_giftcard_delivery_status', true )
        );
        $delivery_data  = wctf_get_fazercards_giftcard_customer_delivery_data( $order, $item, $item_id );
        $display_ready  = ! is_wp_error( $delivery_data );
        $delivered_at   = sanitize_text_field( (string) $item->get_meta( '_wctf_fazer_giftcard_delivered_at', true ) );
        $attempts       = absint( $item->get_meta( '_wctf_fazer_giftcard_delivery_attempts', true ) );
        $last_error     = wctf_sanitize_fazercards_giftcard_delivery_error(
            $item->get_meta( '_wctf_fazer_giftcard_last_delivery_error', true )
        );
        $delivery_email = sanitize_email( (string) $item->get_meta( '_wctf_fazer_giftcard_delivery_email_to', true ) );
        $billing_email  = sanitize_email( (string) $order->get_billing_email() );
        $can_email      = $display_ready && '' !== $billing_email && is_email( $billing_email );
        $status_labels  = array(
            'not_started' => __( 'Not started', 'wc-topup-fields' ),
            'ready'       => __( 'Ready', 'wc-topup-fields' ),
            'delivering'  => __( 'Delivering - admin review required if stale', 'wc-topup-fields' ),
            'delivered'   => __( 'Delivered', 'wc-topup-fields' ),
            'failed'      => __( 'Failed', 'wc-topup-fields' ),
            'blocked'     => __( 'Blocked', 'wc-topup-fields' ),
        );

        echo '<div class="wctf-giftcard-customer-delivery-admin-item">';
        echo '<h4>' . esc_html( sprintf( __( 'Order item #%1$d: %2$s', 'wc-topup-fields' ), absint( $item_id ), sanitize_text_field( $item->get_name() ) ) ) . '</h4>';
        echo '<ul>';
        echo '<li><strong>' . esc_html__( 'Delivery status:', 'wc-topup-fields' ) . '</strong> ' . esc_html( $status_labels[ $status ] ) . '</li>';
        echo '<li><strong>' . esc_html__( 'Customer page display ready:', 'wc-topup-fields' ) . '</strong> ' . esc_html( $display_ready ? __( 'Yes', 'wc-topup-fields' ) : __( 'No', 'wc-topup-fields' ) ) . '</li>';
        echo '<li><strong>' . esc_html__( 'Delivered at:', 'wc-topup-fields' ) . '</strong> ' . esc_html( '' === $delivered_at ? __( 'Not delivered', 'wc-topup-fields' ) : $delivered_at ) . '</li>';
        echo '<li><strong>' . esc_html__( 'Attempts:', 'wc-topup-fields' ) . '</strong> ' . esc_html( (string) $attempts ) . '</li>';
        echo '<li><strong>' . esc_html__( 'Last safe error:', 'wc-topup-fields' ) . '</strong> ' . esc_html( '' === $last_error ? __( 'None', 'wc-topup-fields' ) : $last_error ) . '</li>';
        echo '<li><strong>' . esc_html__( 'Delivery recipient:', 'wc-topup-fields' ) . '</strong> ' . esc_html( '' === $delivery_email ? __( 'Not sent', 'wc-topup-fields' ) : $delivery_email ) . '</li>';
        echo '</ul>';

        if ( $can_email && ! in_array( $status, array( 'delivering', 'delivered', 'failed' ), true ) ) {
            wctf_render_fazercards_giftcard_customer_delivery_admin_control( $order_id, $item_id, 'send' );
        } elseif ( $can_email && in_array( $status, array( 'delivered', 'failed' ), true ) ) {
            wctf_render_fazercards_giftcard_customer_delivery_admin_control( $order_id, $item_id, 'resend' );
        }

        echo '</div>';
        unset( $delivery_data );
    }

    if ( ! $found ) {
        echo '<p>' . esc_html__( 'No Gift Card order items were detected.', 'wc-topup-fields' ) . '</p>';
    }

    wctf_render_fazercards_giftcard_customer_delivery_admin_script();
}

/**
 * Render one standalone-form SEND or RESEND confirmation control.
 *
 * @param int    $order_id WooCommerce order ID.
 * @param int    $item_id  WooCommerce order item ID.
 * @param string $mode     send or resend.
 * @return void
 */
function wctf_render_fazercards_giftcard_customer_delivery_admin_control( $order_id, $item_id, $mode ) {
    $mode         = 'resend' === $mode ? 'resend' : 'send';
    $expected     = 'resend' === $mode ? 'RESEND' : 'SEND';
    $confirmed_id = 'wctf-gc-delivery-confirmed-' . $mode . '-' . absint( $item_id );
    $text_id      = 'wctf-gc-delivery-text-' . $mode . '-' . absint( $item_id );
    $action       = 'wctf_fazercards_giftcard_' . $mode . '_customer_delivery';
    $nonce        = wp_create_nonce(
        'wctf_gc_customer_delivery_' . $mode . '_' . absint( $order_id ) . '_' . absint( $item_id )
    );

    echo '<div class="wctf-giftcard-customer-delivery-control">';
    echo '<p><strong>' . esc_html( 'resend' === $mode ? __( 'This will email the Gift Card to the customer again.', 'wc-topup-fields' ) : __( 'This will email the Gift Card to the customer.', 'wc-topup-fields' ) ) . '</strong></p>';
    echo '<p><label for="' . esc_attr( $confirmed_id ) . '"><input type="checkbox" id="' . esc_attr( $confirmed_id ) . '"> ';
    echo esc_html( sprintf( __( 'I understand this will %s the Gift Card email.', 'wc-topup-fields' ), strtolower( $expected ) ) );
    echo '</label></p>';
    echo '<p><label for="' . esc_attr( $text_id ) . '">' . esc_html( sprintf( __( 'Type %s to confirm:', 'wc-topup-fields' ), $expected ) ) . '</label> ';
    echo '<input type="text" id="' . esc_attr( $text_id ) . '" autocomplete="off"></p>';
    echo '<p><button type="button" class="button button-primary wctf-giftcard-customer-delivery-action"';
    echo ' data-action-url="' . esc_url( admin_url( 'admin-post.php' ) ) . '"';
    echo ' data-action="' . esc_attr( $action ) . '"';
    echo ' data-order-id="' . esc_attr( absint( $order_id ) ) . '"';
    echo ' data-item-id="' . esc_attr( absint( $item_id ) ) . '"';
    echo ' data-nonce="' . esc_attr( $nonce ) . '"';
    echo ' data-confirmed-id="' . esc_attr( $confirmed_id ) . '"';
    echo ' data-text-id="' . esc_attr( $text_id ) . '"';
    echo ' data-expected="' . esc_attr( $expected ) . '">';
    echo esc_html( 'resend' === $mode ? __( 'Resend Gift Card to Customer', 'wc-topup-fields' ) : __( 'Send Gift Card to Customer', 'wc-topup-fields' ) );
    echo '</button></p>';
    echo '</div>';
}

/**
 * Render the admin-only dynamic standalone form controller.
 *
 * @return void
 */
function wctf_render_fazercards_giftcard_customer_delivery_admin_script() {
    ?>
    <script>
    ( function() {
        if ( window.wctfFazerCardsGiftCardCustomerDeliveryBound ) {
            return;
        }

        window.wctfFazerCardsGiftCardCustomerDeliveryBound = true;

        function addField( form, name, value ) {
            var field = document.createElement( 'input' );
            field.type = 'hidden';
            field.name = name;
            field.value = value;
            form.appendChild( field );
        }

        document.addEventListener( 'click', function( event ) {
            var button = event.target.closest
                ? event.target.closest( '.wctf-giftcard-customer-delivery-action' )
                : null;

            if ( ! button ) {
                return;
            }

            event.preventDefault();

            var confirmed = document.getElementById( button.getAttribute( 'data-confirmed-id' ) );
            var text = document.getElementById( button.getAttribute( 'data-text-id' ) );
            var expected = button.getAttribute( 'data-expected' ) || '';

            if ( ! confirmed || ! confirmed.checked ) {
                if ( confirmed && confirmed.reportValidity ) {
                    confirmed.required = true;
                    confirmed.reportValidity();
                }
                return;
            }

            if ( ! text || expected !== text.value ) {
                if ( text && text.setCustomValidity && text.reportValidity ) {
                    text.setCustomValidity( '<?php echo esc_js( __( 'The confirmation text must match exactly.', 'wc-topup-fields' ) ); ?>' );
                    text.reportValidity();
                    text.addEventListener( 'input', function clearDeliveryError() {
                        text.setCustomValidity( '' );
                        text.removeEventListener( 'input', clearDeliveryError );
                    } );
                }
                return;
            }

            var form = document.createElement( 'form' );
            form.method = 'post';
            form.action = button.getAttribute( 'data-action-url' );

            addField( form, 'action', button.getAttribute( 'data-action' ) || '' );
            addField( form, 'order_id', button.getAttribute( 'data-order-id' ) || '' );
            addField( form, 'item_id', button.getAttribute( 'data-item-id' ) || '' );
            addField( form, 'nonce', button.getAttribute( 'data-nonce' ) || '' );
            addField( form, 'delivery_confirmed', '1' );
            addField( form, 'delivery_confirmation_text', text.value );

            document.body.appendChild( form );
            form.submit();
        } );
    }() );
    </script>
    <?php
}

/**
 * Render customer-safe Gift Card item state.
 *
 * @param array $items Customer-safe item state.
 * @return void
 */
function wctf_render_fazercards_giftcard_customer_items( $items ) {
    foreach ( $items as $item ) {
        $status       = isset( $item['status'] ) ? sanitize_key( (string) $item['status'] ) : 'blocked';
        $product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';

        echo '<article class="wctf-giftcard-delivery-item">';
        echo '<h3>' . esc_html( $product_name ) . '</h3>';

        if ( 'ready' === $status && ! empty( $item['entries'] ) && is_array( $item['entries'] ) ) {
            foreach ( $item['entries'] as $index => $entry ) {
                echo '<div class="wctf-giftcard-delivery-entry">';
                echo '<strong>' . esc_html( sprintf( __( 'Gift Card #%d', 'wc-topup-fields' ), absint( $index + 1 ) ) ) . '</strong>';
                echo '<pre class="wctf-giftcard-delivery-value">' . esc_html( (string) $entry ) . '</pre>';
                echo '<button type="button" class="button wctf-giftcard-delivery-copy">' . esc_html__( 'Copy', 'wc-topup-fields' ) . '</button>';
                echo '</div>';
            }

            echo '<p><strong>' . esc_html__( 'Keep this code safe.', 'wc-topup-fields' ) . '</strong></p>';
        } elseif ( 'preparing' === $status ) {
            echo '<p class="wctf-giftcard-delivery-message">' . esc_html__( 'Your gift card is being prepared. Please refresh this page shortly.', 'wc-topup-fields' ) . '</p>';
        } else {
            echo '<p class="wctf-giftcard-delivery-message">' . esc_html__( 'Your gift card cannot be displayed right now. Please contact support.', 'wc-topup-fields' ) . '</p>';
        }

        echo '</article>';
    }
}

/**
 * Enqueue the polling script with authorization context but no card data.
 *
 * @param WC_Order $order         WooCommerce order.
 * @param string   $order_key     Valid request order key, if required.
 * @param string   $initial_status Current aggregate display status.
 * @return void
 */
function wctf_enqueue_fazercards_giftcard_customer_delivery_script( $order, $order_key, $initial_status ) {
    if ( ! $order instanceof WC_Order || ! class_exists( 'WC_AJAX' ) ) {
        return;
    }

    $order_id    = absint( $order->get_id() );
    $customer_id = absint( $order->get_customer_id() );
    $key_required = 0 === $customer_id
        || ! is_user_logged_in()
        || $customer_id !== get_current_user_id();
    $nonce_action = wctf_get_fazercards_giftcard_customer_nonce_action( $order, $order_key );

    if ( '' === $nonce_action ) {
        return;
    }

    $script_path = WCTF_PATH . 'frontend/js/giftcard-delivery.js';
    $version     = file_exists( $script_path ) ? (string) filemtime( $script_path ) : wctf_plugin_version();

    wp_enqueue_script(
        'wctf-giftcard-delivery',
        plugin_dir_url( WCTF_PATH . 'wc-topup-fields.php' ) . 'frontend/js/giftcard-delivery.js',
        array(),
        $version,
        true
    );
    wp_localize_script(
        'wctf-giftcard-delivery',
        'wctfGiftCardDelivery',
        array(
            'endpointUrl'  => WC_AJAX::get_endpoint( 'wctf_fazercards_giftcard_delivery_status' ),
            'nonce'        => wp_create_nonce( $nonce_action ),
            'orderId'      => $order_id,
            'orderKey'     => $key_required ? (string) $order_key : '',
            'containerId'  => 'wctf-giftcard-delivery-' . $order_id,
            'initialStatus' => sanitize_key( $initial_status ),
            'pollInterval' => 5000,
            'maxPollTime'  => 120000,
            'labels'       => array(
                'heading'   => __( 'Your Gift Cards', 'wc-topup-fields' ),
                'preparing' => __( 'Your gift card is being prepared. Please refresh this page shortly.', 'wc-topup-fields' ),
                'blocked'   => __( 'Your gift card cannot be displayed right now. Please contact support.', 'wc-topup-fields' ),
                'keepSafe'  => __( 'Keep this code safe.', 'wc-topup-fields' ),
                'copy'      => __( 'Copy', 'wc-topup-fields' ),
                'copied'    => __( 'Copied', 'wc-topup-fields' ),
                'card'      => __( 'Gift Card', 'wc-topup-fields' ),
            ),
        )
    );
}

/**
 * Return current local delivery state to an authorized WooCommerce AJAX caller.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_customer_delivery_status() {
    nocache_headers();
    header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'Pragma: no-cache', true );
    header( 'X-Robots-Tag: noindex, nofollow', true );

    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
        : 0;
    $order_key = isset( $_POST['order_key'] ) && is_scalar( $_POST['order_key'] )
        ? wc_clean( wp_unslash( $_POST['order_key'] ) )
        : '';
    $nonce = isset( $_POST['nonce'] ) && is_scalar( $_POST['nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
        : '';
    $order = 0 < $order_id ? wc_get_order( $order_id ) : false;

    if ( ! $order instanceof WC_Order || ! wctf_is_fazercards_giftcard_customer_authorized( $order, $order_key ) ) {
        wp_send_json_error( array( 'status' => 'blocked' ), 404 );
    }

    $nonce_action = wctf_get_fazercards_giftcard_customer_nonce_action( $order, $order_key );

    if ( '' === $nonce_action || '' === $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
        wp_send_json_error( array( 'status' => 'blocked' ), 404 );
    }

    $state = wctf_get_fazercards_giftcard_customer_order_state( $order );
    wp_send_json_success( $state );
}

/**
 * Disable public caching for authorized Gift Card order pages.
 *
 * @return void
 */
function wctf_maybe_disable_fazercards_giftcard_customer_page_cache() {
    if (
        ! function_exists( 'is_order_received_page' )
        || ! function_exists( 'is_wc_endpoint_url' )
        || ( ! is_order_received_page() && ! is_wc_endpoint_url( 'view-order' ) )
    ) {
        return;
    }

    $order_id = is_wc_endpoint_url( 'view-order' )
        ? absint( get_query_var( 'view-order' ) )
        : absint( get_query_var( 'order-received' ) );
    $order_key = wctf_get_fazercards_giftcard_customer_request_order_key();

    if ( 1 > $order_id && '' !== $order_key && function_exists( 'wc_get_order_id_by_order_key' ) ) {
        $order_id = absint( wc_get_order_id_by_order_key( $order_key ) );
    }

    $order = 0 < $order_id ? wc_get_order( $order_id ) : false;

    if ( ! $order instanceof WC_Order || ! wctf_is_fazercards_giftcard_customer_authorized( $order, $order_key ) ) {
        return;
    }

    $has_giftcard = false;

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        if ( wctf_is_fazercards_giftcard_customer_order_item( $item ) ) {
            $has_giftcard = true;
            break;
        }
    }

    if ( ! $has_giftcard || headers_sent() ) {
        return;
    }

    nocache_headers();
    header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'Pragma: no-cache', true );
    header( 'X-Robots-Tag: noindex, nofollow', true );
}
