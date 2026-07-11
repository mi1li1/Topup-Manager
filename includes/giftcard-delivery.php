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
