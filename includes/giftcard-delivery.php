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
    'wctf_handle_fazercards_giftcard_completed_email_item_ready',
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
    'wctf_fazercards_giftcard_send_completed_order_email',
    'wctf_run_fazercards_giftcard_completed_order_email',
    10,
    1
);
add_action(
    'admin_post_wctf_fazercards_giftcard_send_completed_order_email',
    'wctf_handle_fazercards_giftcard_send_completed_order_email'
);
add_action(
    'admin_post_wctf_fazercards_giftcard_resend_completed_order_email',
    'wctf_handle_fazercards_giftcard_resend_completed_order_email'
);
add_action(
    'admin_post_wctf_fazercards_giftcard_fast_send_completed_email',
    'wctf_handle_fazercards_giftcard_fast_send_completed_email'
);
add_action(
    'admin_post_nopriv_wctf_fazercards_giftcard_fast_send_completed_email',
    'wctf_handle_fazercards_giftcard_fast_send_completed_email'
);
add_filter(
    'woocommerce_email_enabled_customer_completed_order',
    'wctf_filter_fazercards_giftcard_completed_order_email_enabled',
    20,
    3
);
add_action(
    'woocommerce_order_status_completed',
    'wctf_handle_fazercards_giftcard_order_completed_email',
    25,
    3
);
add_action(
    'woocommerce_email_after_order_table',
    'wctf_render_fazercards_giftcard_completed_order_email_content',
    20,
    4
);
add_action(
    'woocommerce_email_sent',
    'wctf_observe_fazercards_giftcard_completed_order_email_sent',
    20,
    3
);
add_filter(
    'woocommerce_mail_callback',
    'wctf_filter_fazercards_giftcard_completed_order_mail_callback',
    PHP_INT_MAX,
    2
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
        || ! function_exists( 'wctf_get_fazercards_giftcard_required_codes_count' )
    ) {
        return new WP_Error( 'wctf_giftcard_customer_not_ready' );
    }

    $required_count = wctf_get_fazercards_giftcard_required_codes_count( $item );

    if ( null === $required_count ) {
        return new WP_Error( 'wctf_giftcard_customer_quantity_invalid' );
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

    if (
        null === $detected
        || 100 < absint( $detected )
        || 100 < count( $entries )
    ) {
        unset( $wrapper, $order_payload, $entries );
        return new WP_Error( 'wctf_giftcard_customer_count_invalid' );
    }

    if (
        absint( $detected ) < $required_count
        || count( $entries ) < $required_count
    ) {
        unset( $wrapper, $order_payload, $entries );
        return new WP_Error( 'wctf_giftcard_customer_count_incomplete' );
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

    if ( count( $normalized ) < $required_count ) {
        unset( $wrapper, $order_payload, $entries, $normalized );
        return new WP_Error( 'wctf_giftcard_customer_count_incomplete' );
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
            if ( 'wctf_giftcard_customer_count_incomplete' === $delivery_data->get_error_code() ) {
                $item_state['status'] = 'preparing';
                $has_preparing        = true;
            } else {
                $item_state['status'] = 'blocked';
                $has_blocked          = true;
            }
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
            __( 'FazerCards Gift Card Completed Order Email', 'wc-topup-fields' ),
            'wctf_render_fazercards_giftcard_completed_email_admin_meta_box',
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
    $required_codes_count = function_exists( 'wctf_get_fazercards_giftcard_required_codes_count' )
        ? wctf_get_fazercards_giftcard_required_codes_count( $item )
        : null;

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
    } elseif ( null === $required_codes_count ) {
        $reason = __( 'Gift Card order-item quantity is invalid.', 'wc-topup-fields' );
    } elseif ( null === $codes_count || absint( $codes_count ) < $required_codes_count ) {
        $reason = __( 'The complete customer delivery card/code quantity is still preparing.', 'wc-topup-fields' );
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
        'required_codes_count' => $required_codes_count,
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
            __( 'Required customer delivery cards/codes count', 'wc-topup-fields' ) => null === $diagnostics['required_codes_count'] ? __( 'Invalid quantity', 'wc-topup-fields' ) : (string) absint( $diagnostics['required_codes_count'] ),
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
 * Detect immutable Gift Card delivery evidence on an order item.
 *
 * Current product metadata is deliberately ignored.
 *
 * @param WC_Order_Item_Product $item WooCommerce order item.
 * @return bool
 */
function wctf_is_fazercards_giftcard_completed_email_item( $item ) {
    if ( ! $item instanceof WC_Order_Item_Product ) {
        return false;
    }

    if ( 'giftcard' === sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) ) ) {
        return true;
    }

    return '' !== sanitize_text_field(
        (string) $item->get_meta( '_wctf_fazer_giftcard_snapshot_created_at', true )
    )
        || '' !== sanitize_text_field(
            (string) $item->get_meta( '_wctf_fazer_giftcard_category_id', true )
        )
        || '' !== sanitize_text_field(
            (string) $item->get_meta( '_wctf_fazer_giftcard_card_id', true )
        );
}

/**
 * Determine whether an order contains immutable Gift Card delivery items.
 *
 * @param WC_Order $order WooCommerce order.
 * @return bool
 */
function wctf_fazercards_giftcard_order_has_delivery_items( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return false;
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        if ( wctf_is_fazercards_giftcard_completed_email_item( $item ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Normalize the order-level Completed email coordinator status.
 *
 * @param mixed $status Raw status.
 * @return string
 */
function wctf_normalize_fazercards_giftcard_completed_email_status( $status ) {
    $status  = is_scalar( $status ) ? sanitize_key( (string) $status ) : '';
    $allowed = array(
        'not_started',
        'held',
        'scheduled',
        'sending',
        'sent',
        'failed',
        'blocked',
        'legacy_delivered',
    );

    return in_array( $status, $allowed, true ) ? $status : 'not_started';
}

/**
 * Store or inspect the request-local authorized Completed email context.
 *
 * No decrypted content is retained here.
 *
 * @param string $operation get, set, clear, injected, or observed.
 * @param mixed  $value     Context or observed boolean.
 * @return array|null
 */
function wctf_fazercards_giftcard_completed_email_runtime( $operation = 'get', $value = null ) {
    static $context = null;

    if ( 'set' === $operation ) {
        $context = is_array( $value ) ? $value : null;
    } elseif ( 'clear' === $operation ) {
        $context = null;
    } elseif ( 'injected' === $operation && is_array( $context ) ) {
        $context['injected'] = true;
    } elseif ( 'observed' === $operation && is_array( $context ) ) {
        $context['send_observed'] = true;
        $context['send_result']   = (bool) $value;
    }

    return is_array( $context ) ? $context : null;
}

/**
 * Build whole-order readiness for the standard Completed Order email.
 *
 * Validated plaintext entries are returned for immediate in-memory rendering
 * only and must never be persisted by callers.
 *
 * @param WC_Order $order WooCommerce order.
 * @return array
 */
function wctf_get_fazercards_giftcard_completed_email_readiness( $order ) {
    $result = array(
        'has_giftcards' => false,
        'status'        => 'held',
        'reason'        => __( 'No Gift Card order items were detected.', 'wc-topup-fields' ),
        'item_ids'      => array(),
        'items'         => array(),
    );

    if ( ! $order instanceof WC_Order ) {
        $result['status'] = 'blocked';
        $result['reason'] = __( 'The WooCommerce order could not be loaded.', 'wc-topup-fields' );
        return $result;
    }

    $required_items = array();

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( wctf_is_fazercards_giftcard_completed_email_item( $item ) ) {
            $required_items[ absint( $item_id ) ] = $item;
        }
    }

    if ( empty( $required_items ) ) {
        return $result;
    }

    $result['has_giftcards'] = true;

    if ( 'completed' !== sanitize_key( (string) $order->get_status() ) ) {
        $result['reason'] = __( 'The order is not completed yet.', 'wc-topup-fields' );
        return $result;
    }

    if ( ! wctf_is_fazercards_giftcard_customer_order_paid( $order ) ) {
        $result['status'] = 'blocked';
        $result['reason'] = __( 'The completed order does not pass WooCommerce paid validation.', 'wc-topup-fields' );
        return $result;
    }

    $recipient = sanitize_email( (string) $order->get_billing_email() );

    if ( '' === $recipient || ! is_email( $recipient ) ) {
        $result['status'] = 'blocked';
        $result['reason'] = __( 'The WooCommerce billing email is missing or invalid.', 'wc-topup-fields' );
        return $result;
    }

    $has_held = false;

    foreach ( $required_items as $item_id => $item ) {
        $kind        = sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) );
        $snapshot_at = sanitize_text_field(
            (string) $item->get_meta( '_wctf_fazer_giftcard_snapshot_created_at', true )
        );
        $category_id = sanitize_text_field(
            (string) $item->get_meta( '_wctf_fazer_giftcard_category_id', true )
        );
        $card_id     = sanitize_text_field(
            (string) $item->get_meta( '_wctf_fazer_giftcard_card_id', true )
        );

        if ( 'giftcard' !== $kind || '' === $snapshot_at || '' === $category_id || '' === $card_id ) {
            $result['status'] = 'blocked';
            $result['reason'] = __( 'A Gift Card order-item snapshot is incomplete or malformed.', 'wc-topup-fields' );
            unset( $result['items'] );
            $result['items'] = array();
            return $result;
        }

        $purchase_status = function_exists( 'wctf_normalize_fazercards_giftcard_purchase_status' )
            ? wctf_normalize_fazercards_giftcard_purchase_status(
                $item->get_meta( '_wctf_fazer_giftcard_purchase_status', true )
            )
            : sanitize_key( (string) $item->get_meta( '_wctf_fazer_giftcard_purchase_status', true ) );
        $fulfillment_status = sanitize_key(
            (string) $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true )
        );

        if (
            in_array( $purchase_status, array( 'failed', 'failed_uncertain', 'storage_failed' ), true )
            || in_array( $fulfillment_status, array( 'needs_admin_review', 'stopped' ), true )
        ) {
            $result['status'] = 'blocked';
            $result['reason'] = __( 'A Gift Card item requires administrator review before email delivery.', 'wc-topup-fields' );
            $result['items']  = array();
            return $result;
        }

        if ( 'ready_to_deliver' !== $fulfillment_status ) {
            $has_held = true;
            continue;
        }

        $delivery_data = wctf_get_fazercards_giftcard_customer_delivery_data(
            $order,
            $item,
            $item_id
        );

        if (
            is_wp_error( $delivery_data )
            && 'wctf_giftcard_customer_count_incomplete' === $delivery_data->get_error_code()
        ) {
            unset( $delivery_data );
            $has_held = true;
            continue;
        }

        if ( is_wp_error( $delivery_data ) || empty( $delivery_data['entries'] ) ) {
            unset( $delivery_data );
            $result['status'] = 'blocked';
            $result['reason'] = __( 'An encrypted Gift Card payload failed authenticated delivery validation.', 'wc-topup-fields' );
            $result['items']  = array();
            return $result;
        }

        $result['item_ids'][] = $item_id;
        $result['items'][]    = array(
            'item_id'      => $item_id,
            'product_name' => sanitize_text_field( $item->get_name() ),
            'quantity'     => max( 0, (int) $item->get_quantity() ),
            'mode'         => isset( $delivery_data['mode'] )
                ? sanitize_key( (string) $delivery_data['mode'] )
                : '',
            'entries'      => array_values( $delivery_data['entries'] ),
        );
        unset( $delivery_data );
    }

    if ( $has_held || count( $result['item_ids'] ) !== count( $required_items ) ) {
        $result['status'] = 'held';
        $result['reason'] = __( 'Gift Card Completed Order email is waiting for all Gift Card items to become ready.', 'wc-topup-fields' );
        $result['items']  = array();
        return $result;
    }

    $result['status'] = 'ready';
    $result['reason'] = __( 'All Gift Card items are ready for the WooCommerce Completed Order email.', 'wc-topup-fields' );

    return $result;
}

/**
 * Persist safe order-level Completed email coordinator state.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param string   $status Coordinator status.
 * @param string   $error  Safe short error.
 * @return void
 */
function wctf_save_fazercards_giftcard_completed_email_state( $order, $status, $error = '' ) {
    if ( ! $order instanceof WC_Order || ! wctf_fazercards_giftcard_order_has_delivery_items( $order ) ) {
        return;
    }

    $status   = wctf_normalize_fazercards_giftcard_completed_email_status( $status );
    $error    = wctf_sanitize_fazercards_giftcard_delivery_error( $error );
    $previous = wctf_normalize_fazercards_giftcard_completed_email_status(
        $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
    );
    $now      = gmdate( 'Y-m-d H:i:s' );

    $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_status', $status );

    if ( 'held' === $status && '' === (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_held_at', true ) ) {
        $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_held_at', $now );
    }

    if ( '' === $error ) {
        $order->delete_meta_data( '_wctf_fazer_giftcard_completed_email_last_error' );
    } else {
        $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_last_error', $error );
    }

    $order->save();

    if ( $previous === $status ) {
        return;
    }

    if ( 'held' === $status ) {
        $order->add_order_note(
            __( 'Gift Card Completed Order email held until all Gift Card items are ready.', 'wc-topup-fields' ),
            0,
            false
        );
    } elseif ( 'blocked' === $status ) {
        $order->add_order_note(
            __( 'WooCommerce Completed Order email blocked; administrator review required.', 'wc-topup-fields' ),
            0,
            false
        );
    }
}

/**
 * Apply conservative compatibility for orders that predate the coordinator.
 *
 * @param WC_Order $order    WooCommerce order.
 * @param bool     $new_flow Whether this is a new completed transition.
 * @return string
 */
function wctf_apply_fazercards_giftcard_completed_email_legacy_state( $order, $new_flow = false ) {
    if ( ! $order instanceof WC_Order ) {
        return 'not_started';
    }

    $raw_status = sanitize_key(
        (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
    );

    if ( '' !== $raw_status ) {
        return wctf_normalize_fazercards_giftcard_completed_email_status( $raw_status );
    }

    $legacy_delivered = false;

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        if (
            wctf_is_fazercards_giftcard_completed_email_item( $item )
            && 'delivered' === sanitize_key(
                (string) $item->get_meta( '_wctf_fazer_giftcard_delivery_status', true )
            )
        ) {
            $legacy_delivered = true;
            break;
        }
    }

    if ( $legacy_delivered || ( ! $new_flow && 'completed' === $order->get_status() ) ) {
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_status',
            'legacy_delivered'
        );
        $order->save();
        return 'legacy_delivered';
    }

    if ( $new_flow ) {
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_status',
            'not_started'
        );
        $order->save();
    }

    return 'not_started';
}

/**
 * Determine whether the order-level Completed email action is scheduled.
 *
 * @param int $order_id WooCommerce order ID.
 * @return bool
 */
function wctf_is_fazercards_giftcard_completed_email_scheduled( $order_id ) {
    $scheduled = wctf_get_fazercards_giftcard_completed_email_future_action( $order_id );

    return ! empty( $scheduled['scheduled'] );
}

/**
 * Inspect only a future action for this exact order and scheduler group.
 *
 * Historical completed actions never count as duplicates.
 *
 * @param int $order_id WooCommerce order ID.
 * @return array
 */
function wctf_get_fazercards_giftcard_completed_email_future_action( $order_id ) {
    $hook  = 'wctf_fazercards_giftcard_send_completed_order_email';
    $args  = array( 'order_id' => absint( $order_id ) );
    $group = 'wctf-giftcards';
    $result = array(
        'scheduled' => false,
        'backend'   => function_exists( 'as_schedule_single_action' )
            ? 'action_scheduler'
            : 'wp_cron',
        'timestamp' => 0,
    );

    if ( function_exists( 'as_next_scheduled_action' ) ) {
        $timestamp = as_next_scheduled_action( $hook, $args, $group );

        if ( false !== $timestamp && 0 < absint( $timestamp ) ) {
            $result['scheduled'] = true;
            $result['timestamp'] = absint( $timestamp );
        }

        return $result;
    }

    if ( function_exists( 'as_has_scheduled_action' ) ) {
        $result['scheduled'] = (bool) as_has_scheduled_action( $hook, $args, $group );
        return $result;
    }

    $timestamp = wp_next_scheduled( $hook, $args );

    if ( false !== $timestamp && 0 < absint( $timestamp ) ) {
        $result['scheduled'] = true;
        $result['timestamp'] = absint( $timestamp );
    }

    return $result;
}

/**
 * Schedule one order-level WooCommerce Completed Order email.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param string   $reason Safe scheduling reason.
 * @return array
 */
function wctf_schedule_fazercards_giftcard_completed_order_email( $order, $reason = '' ) {
    $result = array(
        'result'    => 'skipped',
        'backend'   => function_exists( 'as_schedule_single_action' )
            ? 'action_scheduler'
            : 'wp_cron',
        'timestamp' => 0,
        'reason'    => wctf_sanitize_fazercards_giftcard_delivery_error( $reason ),
    );

    if ( ! $order instanceof WC_Order ) {
        return $result;
    }

    $order_id = absint( $order->get_id() );
    $status   = wctf_normalize_fazercards_giftcard_completed_email_status(
        $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
    );

    if ( in_array( $status, array( 'sending', 'sent', 'legacy_delivered' ), true ) ) {
        return $result;
    }

    $future_action = wctf_get_fazercards_giftcard_completed_email_future_action( $order_id );

    if ( ! empty( $future_action['scheduled'] ) ) {
        $result['result']    = 'already_scheduled';
        $result['backend']   = $future_action['backend'];
        $result['timestamp'] = absint( $future_action['timestamp'] );

        if ( 'scheduled' !== $status ) {
            $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_status', 'scheduled' );
            $order->update_meta_data(
                '_wctf_fazer_giftcard_completed_email_scheduled_at',
                0 < $result['timestamp']
                    ? gmdate( 'Y-m-d H:i:s', $result['timestamp'] )
                    : gmdate( 'Y-m-d H:i:s' )
            );
            $order->update_meta_data(
                '_wctf_fazer_giftcard_completed_email_scheduler_backend',
                sanitize_key( $result['backend'] )
            );
            $order->update_meta_data(
                '_wctf_fazer_giftcard_completed_email_schedule_reason',
                $result['reason']
            );
            $order->delete_meta_data( '_wctf_fazer_giftcard_completed_email_last_error' );
            $order->save();
        }

        return $result;
    }

    $hook      = 'wctf_fazercards_giftcard_send_completed_order_email';
    $args      = array( 'order_id' => $order_id );
    $timestamp = time() + 1;
    $scheduled = false;

    if ( function_exists( 'as_schedule_single_action' ) ) {
        $scheduled = (bool) as_schedule_single_action(
            $timestamp,
            $hook,
            $args,
            'wctf-giftcards'
        );
    } else {
        $scheduled = (bool) wp_schedule_single_event( $timestamp, $hook, $args );
    }

    if ( ! $scheduled ) {
        wctf_save_fazercards_giftcard_completed_email_state(
            $order,
            'blocked',
            __( 'The WooCommerce Completed Order email could not be scheduled.', 'wc-topup-fields' )
        );
        $result['result'] = 'failed';
        return $result;
    }

    $result['result']    = 'scheduled';
    $result['timestamp'] = $timestamp;
    $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_status', 'scheduled' );
    $order->update_meta_data(
        '_wctf_fazer_giftcard_completed_email_scheduled_at',
        gmdate( 'Y-m-d H:i:s', $timestamp )
    );
    $order->update_meta_data(
        '_wctf_fazer_giftcard_completed_email_scheduler_backend',
        sanitize_key( $result['backend'] )
    );
    $order->update_meta_data(
        '_wctf_fazer_giftcard_completed_email_schedule_reason',
        $result['reason']
    );
    $order->delete_meta_data( '_wctf_fazer_giftcard_completed_email_last_error' );
    $order->save();

    return $result;
}

/**
 * Return the transient key for one signed fast-dispatch token hash.
 *
 * @param string $token_hash SHA-256 token hash.
 * @return string
 */
function wctf_get_fazercards_giftcard_completed_email_fast_token_key( $token_hash ) {
    $token_hash = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $token_hash ) );

    return 'wctf_gc_email_fast_' . substr( $token_hash, 0, 32 );
}

/**
 * Determine whether the order-level Completed email send lock is active.
 *
 * Stale lock data is deliberately treated as inactive; the authoritative
 * worker performs its own atomic stale-lock recovery when it starts.
 *
 * @param int $order_id WooCommerce order ID.
 * @return bool
 */
function wctf_is_fazercards_giftcard_completed_email_lock_active( $order_id ) {
    $existing = json_decode(
        (string) get_option( wctf_get_fazercards_giftcard_completed_email_lock_key( $order_id ), '' ),
        true
    );

    return is_array( $existing )
        && isset( $existing['expires_at'] )
        && (int) $existing['expires_at'] >= time();
}

/**
 * Dispatch one signed, non-blocking request to the existing email worker.
 *
 * The fallback queue action must already exist before this helper is called.
 * No customer, Gift Card, email body, or API data is placed in the request.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param string   $reason Safe dispatch reason.
 * @return array
 */
function wctf_dispatch_fazercards_giftcard_completed_email_fast_send( $order, $reason = '' ) {
    $result = array(
        'dispatched' => false,
        'status'     => 'fallback_only',
    );

    if ( ! $order instanceof WC_Order ) {
        return $result;
    }

    $order_id = absint( $order->get_id() );
    $issued_at = time();

    try {
        $token = bin2hex( random_bytes( 32 ) );
    } catch ( Throwable $throwable ) {
        unset( $throwable );
        $token = wp_generate_password( 64, false, false );
    }

    if ( '' === $token ) {
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_status',
            'fallback_only'
        );
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_at',
            gmdate( 'Y-m-d H:i:s', $issued_at )
        );
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_last_error',
            __( 'The fast email dispatch could not be prepared; the fallback queue remains active.', 'wc-topup-fields' )
        );
        $order->save();
        return $result;
    }

    $token_hash    = hash( 'sha256', $token );
    $transient_key = wctf_get_fazercards_giftcard_completed_email_fast_token_key( $token_hash );
    $token_stored  = set_transient(
        $transient_key,
        array(
            'token_hash' => $token_hash,
            'order_id'   => $order_id,
            'issued_at'  => $issued_at,
        ),
        5 * MINUTE_IN_SECONDS
    );

    if ( ! $token_stored ) {
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_status',
            'fallback_only'
        );
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_at',
            gmdate( 'Y-m-d H:i:s', $issued_at )
        );
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_last_error',
            __( 'The fast email dispatch token could not be stored; the fallback queue remains active.', 'wc-topup-fields' )
        );
        $order->save();
        return $result;
    }

    $message   = 'v1|' . $order_id . '|' . $issued_at . '|' . $token;
    $signature = hash_hmac( 'sha256', $message, wp_salt( 'auth' ) );
    $order->update_meta_data(
        '_wctf_fazer_giftcard_completed_email_fast_dispatch_status',
        'dispatched'
    );
    $order->update_meta_data(
        '_wctf_fazer_giftcard_completed_email_fast_dispatch_at',
        gmdate( 'Y-m-d H:i:s', $issued_at )
    );
    $order->delete_meta_data( '_wctf_fazer_giftcard_completed_email_fast_dispatch_last_error' );
    $order->save();

    $response  = wp_remote_post(
        admin_url( 'admin-post.php' ),
        array(
            'blocking'  => false,
            'timeout'   => 1,
            'sslverify' => true,
            'body'      => array(
                'action'    => 'wctf_fazercards_giftcard_fast_send_completed_email',
                'order_id'  => $order_id,
                'issued_at' => $issued_at,
                'token'     => $token,
                'signature' => $signature,
            ),
        )
    );

    if ( is_wp_error( $response ) ) {
        delete_transient( $transient_key );
        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return $result;
        }

        $current_fast_status = sanitize_key(
            (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_fast_dispatch_status', true )
        );

        if ( in_array( $current_fast_status, array( 'running', 'completed' ), true ) ) {
            return $result;
        }

        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_status',
            'fallback_only'
        );
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_last_error',
            __( 'The fast email dispatch request could not be started; the fallback queue remains active.', 'wc-topup-fields' )
        );
        $order->save();
        return $result;
    }

    unset( $response, $reason );
    $result['dispatched'] = true;
    $result['status']     = 'dispatched';

    return $result;
}

/**
 * Start fast dispatch only after the fallback action is confirmed pending.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param string   $reason Safe dispatch reason.
 * @return array
 */
function wctf_maybe_dispatch_fazercards_giftcard_completed_email_fast_send( $order, $reason = '' ) {
    $result = array(
        'dispatched' => false,
        'status'     => 'skipped',
    );

    if (
        ! $order instanceof WC_Order
        || ! wctf_fazercards_giftcard_order_has_delivery_items( $order )
        || 'completed' !== sanitize_key( (string) $order->get_status() )
        || ! wctf_is_fazercards_giftcard_customer_order_paid( $order )
    ) {
        return $result;
    }

    $order_id = absint( $order->get_id() );
    $status   = wctf_normalize_fazercards_giftcard_completed_email_status(
        $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
    );

    if (
        'scheduled' !== $status
        || wctf_is_fazercards_giftcard_completed_email_lock_active( $order_id )
    ) {
        return $result;
    }

    $readiness = wctf_get_fazercards_giftcard_completed_email_readiness( $order );

    if ( 'ready' !== $readiness['status'] ) {
        unset( $readiness );
        return $result;
    }

    unset( $readiness );
    $future_action = wctf_get_fazercards_giftcard_completed_email_future_action( $order_id );

    if ( empty( $future_action['scheduled'] ) ) {
        return $result;
    }

    $dispatch_status = sanitize_key(
        (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_fast_dispatch_status', true )
    );
    $dispatched_at   = sanitize_text_field(
        (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_fast_dispatch_at', true )
    );
    $dispatched_time = '' !== $dispatched_at ? strtotime( $dispatched_at . ' UTC' ) : false;

    if (
        in_array( $dispatch_status, array( 'dispatched', 'running', 'completed', 'fallback_only', 'failed' ), true )
        && ( false === $dispatched_time || $dispatched_time >= time() - ( 5 * MINUTE_IN_SECONDS ) )
    ) {
        return $result;
    }

    return wctf_dispatch_fazercards_giftcard_completed_email_fast_send( $order, $reason );
}

/**
 * Coordinate one completed-order or item-ready event.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param string   $reason Safe coordination reason.
 * @return void
 */
function wctf_maybe_coordinate_fazercards_giftcard_completed_email( $order, $reason = '' ) {
    if (
        ! $order instanceof WC_Order
        || ! wctf_fazercards_giftcard_order_has_delivery_items( $order )
        || 'completed' !== sanitize_key( (string) $order->get_status() )
    ) {
        return;
    }

    $status = wctf_normalize_fazercards_giftcard_completed_email_status(
        $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
    );

    if ( in_array( $status, array( 'sending', 'sent', 'legacy_delivered' ), true ) ) {
        return;
    }

    $readiness = wctf_get_fazercards_giftcard_completed_email_readiness( $order );

    if ( 'ready' === $readiness['status'] ) {
        $attempts = absint(
            $order->get_meta( '_wctf_fazer_giftcard_completed_email_attempts', true )
        );

        if ( 'failed' === $status || ( 'blocked' === $status && 0 < $attempts ) ) {
            unset( $readiness );
            return;
        }

        $schedule_result = wctf_schedule_fazercards_giftcard_completed_order_email( $order, $reason );

        if ( in_array( $schedule_result['result'], array( 'scheduled', 'already_scheduled' ), true ) ) {
            try {
                $worker_result = wctf_process_fazercards_giftcard_completed_order_email(
                    absint( $order->get_id() ),
                    'automatic'
                );
            } catch ( Throwable $throwable ) {
                unset( $throwable );
                $worker_result = array();
            }

            unset( $worker_result );
        }

        unset( $schedule_result );
    } elseif ( 'blocked' === $readiness['status'] ) {
        wctf_save_fazercards_giftcard_completed_email_state(
            $order,
            'blocked',
            $readiness['reason']
        );
    } else {
        wctf_save_fazercards_giftcard_completed_email_state( $order, 'held', '' );
    }

    unset( $readiness );
}

/**
 * Persist only safe order-level Completed email repair diagnostics.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param string   $result Safe repair result.
 * @return void
 */
function wctf_save_fazercards_giftcard_completed_email_repair_diagnostics( $order, $result ) {
    $result  = sanitize_key( $result );
    $allowed = array( 'coordinated', 'already_sending', 'already_sent', 'not_ready', 'locked', 'skipped' );

    if ( ! $order instanceof WC_Order || ! in_array( $result, $allowed, true ) ) {
        return;
    }

    $previous = sanitize_key(
        (string) $order->get_meta( '_wctf_fazer_giftcard_email_repair_result', true )
    );
    $last_at  = sanitize_text_field(
        (string) $order->get_meta( '_wctf_fazer_giftcard_email_repair_last_at', true )
    );

    if ( 'coordinated' === $previous && in_array( $result, array( 'already_sending', 'already_sent' ), true ) ) {
        return;
    }

    if ( $previous === $result && '' !== $last_at ) {
        return;
    }

    try {
        $order->update_meta_data(
            '_wctf_fazer_giftcard_email_repair_last_at',
            gmdate( 'Y-m-d H:i:s' )
        );
        $order->update_meta_data( '_wctf_fazer_giftcard_email_repair_result', $result );
        $order->save();
    } catch ( Throwable $throwable ) {
        unset( $throwable );
    }
}

/**
 * Idempotently repair missed Completed Order email coordination.
 *
 * This helper never applies legacy delivery state and never creates a separate
 * email path. It invokes the existing authoritative coordinator only for a
 * completed, wholly ready order that has never attempted delivery.
 *
 * @param WC_Order $order          WooCommerce order.
 * @param array    $customer_state Authorized aggregate customer delivery state.
 * @return string Safe repair result.
 */
function wctf_maybe_repair_fazercards_giftcard_completed_email_coordination( $order, $customer_state ) {
    if ( ! $order instanceof WC_Order || ! wctf_fazercards_giftcard_order_has_delivery_items( $order ) ) {
        return 'skipped';
    }

    $result = 'skipped';
    $status = wctf_normalize_fazercards_giftcard_completed_email_status(
        $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
    );

    if ( in_array( $status, array( 'sent', 'legacy_delivered' ), true ) ) {
        $result = 'already_sent';
    } elseif ( in_array( $status, array( 'scheduled', 'sending' ), true ) ) {
        $result = 'already_sending';
    } elseif (
        ! is_array( $customer_state )
        || ! isset( $customer_state['status'] )
        || 'ready' !== sanitize_key( (string) $customer_state['status'] )
        || 'completed' !== sanitize_key( (string) $order->get_status() )
    ) {
        $result = 'not_ready';
    } else {
        $readiness = wctf_get_fazercards_giftcard_completed_email_readiness( $order );

        if ( 'ready' !== $readiness['status'] ) {
            $result = 'not_ready';
        } else {
            $attempts = absint(
                $order->get_meta( '_wctf_fazer_giftcard_completed_email_attempts', true )
            );
            $sent_at  = sanitize_text_field(
                (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_sent_at', true )
            );

            if ( '' !== $sent_at ) {
                $result = 'already_sent';
            } elseif (
                ! in_array( $status, array( 'not_started', 'held' ), true )
                || 0 !== $attempts
            ) {
                $result = 'skipped';
            } elseif ( wctf_is_fazercards_giftcard_completed_email_lock_active( $order->get_id() ) ) {
                $result = 'locked';
            } else {
                wctf_maybe_coordinate_fazercards_giftcard_completed_email(
                    $order,
                    'authorized_customer_delivery_status_repair'
                );
                $result = 'coordinated';
            }
        }

        unset( $readiness );
    }

    $diagnostic_order = wc_get_order( $order->get_id() );

    if ( $diagnostic_order instanceof WC_Order ) {
        wctf_save_fazercards_giftcard_completed_email_repair_diagnostics(
            $diagnostic_order,
            $result
        );
    }

    unset( $diagnostic_order );

    return $result;
}

/**
 * Coordinate the new Completed transition before automatic Gift Card purchase.
 *
 * @param int      $order_id         WooCommerce order ID.
 * @param WC_Order $order            WooCommerce order object when supplied.
 * @param array    $status_transition Status transition context.
 * @return void
 */
function wctf_handle_fazercards_giftcard_order_completed_email( $order_id, $order = null, $status_transition = array() ) {
    unset( $status_transition );

    if ( ! $order instanceof WC_Order ) {
        $order = wc_get_order( absint( $order_id ) );
    }

    if ( ! $order instanceof WC_Order || ! wctf_fazercards_giftcard_order_has_delivery_items( $order ) ) {
        return;
    }

    $order->update_meta_data(
        '_wctf_fazer_giftcard_completed_email_completed_hook_at',
        gmdate( 'Y-m-d H:i:s' )
    );
    $order->save();

    $status = wctf_apply_fazercards_giftcard_completed_email_legacy_state( $order, true );

    if ( 'legacy_delivered' !== $status ) {
        wctf_maybe_coordinate_fazercards_giftcard_completed_email(
            $order,
            'woocommerce_order_status_completed'
        );
    }
}

/**
 * Recheck the whole order when one Gift Card item becomes ready.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return void
 */
function wctf_handle_fazercards_giftcard_completed_email_item_ready( $order_id, $item_id ) {
    $order = wc_get_order( absint( $order_id ) );
    $item  = $order instanceof WC_Order ? $order->get_item( absint( $item_id ) ) : false;

    if (
        ! $order instanceof WC_Order
        || ! $item instanceof WC_Order_Item_Product
        || absint( $item->get_order_id() ) !== absint( $order->get_id() )
        || ! wctf_is_fazercards_giftcard_completed_email_item( $item )
        || ! wctf_fazercards_giftcard_order_has_delivery_items( $order )
    ) {
        return;
    }

    $order->update_meta_data(
        '_wctf_fazer_giftcard_completed_email_ready_hook_at',
        gmdate( 'Y-m-d H:i:s' )
    );
    $order->save();

    if ( 'completed' !== sanitize_key( (string) $order->get_status() ) ) {
        return;
    }

    $new_flow = doing_action( 'woocommerce_order_status_completed' );
    $status   = wctf_apply_fazercards_giftcard_completed_email_legacy_state( $order, $new_flow );

    if ( 'legacy_delivered' !== $status ) {
        wctf_maybe_coordinate_fazercards_giftcard_completed_email(
            $order,
            'wctf_fazercards_giftcard_ready_to_deliver'
        );
    }
}

/**
 * Suppress uncoordinated Gift Card Completed Order emails.
 *
 * @param bool          $enabled Original WooCommerce setting result.
 * @param object|false  $object  Email object, usually WC_Order.
 * @param WC_Email|null $email   WooCommerce email instance when supplied.
 * @return bool
 */
function wctf_filter_fazercards_giftcard_completed_order_email_enabled( $enabled, $object = false, $email = null ) {
    $order = $object instanceof WC_Order
        ? $object
        : ( $email instanceof WC_Email && $email->object instanceof WC_Order ? $email->object : false );

    if ( ! $order instanceof WC_Order || ! wctf_fazercards_giftcard_order_has_delivery_items( $order ) ) {
        return $enabled;
    }

    $context = wctf_fazercards_giftcard_completed_email_runtime();

    if (
        ! is_array( $context )
        || ! isset( $context['order_id'] )
        || absint( $context['order_id'] ) !== absint( $order->get_id() )
    ) {
        return false;
    }

    $readiness = wctf_get_fazercards_giftcard_completed_email_readiness( $order );
    $status    = wctf_normalize_fazercards_giftcard_completed_email_status(
        $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
    );
    $mode      = isset( $context['mode'] ) ? sanitize_key( $context['mode'] ) : 'automatic';

    if ( 'ready' !== $readiness['status'] ) {
        unset( $readiness );
        return false;
    }

    unset( $readiness );

    if ( 'resend' === $mode ) {
        return in_array( $status, array( 'sending', 'sent', 'legacy_delivered' ), true )
            ? (bool) $enabled
            : false;
    }

    return in_array( $status, array( 'sending', 'scheduled' ), true )
        ? (bool) $enabled
        : false;
}

/**
 * Return the order-level Completed email lock option name.
 *
 * @param int $order_id WooCommerce order ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_completed_email_lock_key( $order_id ) {
    return 'wctf_fazer_giftcard_completed_email_lock_' . absint( $order_id );
}

/**
 * Acquire a five-minute atomic order-level email lock.
 *
 * @param int $order_id WooCommerce order ID.
 * @return string|WP_Error
 */
function wctf_acquire_fazercards_giftcard_completed_email_lock( $order_id ) {
    $lock_key = wctf_get_fazercards_giftcard_completed_email_lock_key( $order_id );
    $token    = function_exists( 'wp_generate_uuid4' )
        ? wp_generate_uuid4()
        : wp_hash( absint( $order_id ) . '|' . microtime( true ) );
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
        'wctf_giftcard_completed_email_locked',
        __( 'Another Gift Card Completed Order email process is already active.', 'wc-topup-fields' )
    );
}

/**
 * Release the email lock only for its current owner.
 *
 * @param int    $order_id WooCommerce order ID.
 * @param string $token    Owner token.
 * @return void
 */
function wctf_release_fazercards_giftcard_completed_email_lock( $order_id, $token ) {
    $lock_key = wctf_get_fazercards_giftcard_completed_email_lock_key( $order_id );
    $existing = json_decode( (string) get_option( $lock_key, '' ), true );
    $owner    = is_array( $existing ) && isset( $existing['owner'] ) && is_string( $existing['owner'] )
        ? $existing['owner']
        : '';

    if ( '' !== $owner && '' !== $token && hash_equals( $owner, $token ) ) {
        delete_option( $lock_key );
    }
}

/**
 * Find the standard WooCommerce customer Completed Order email object.
 *
 * @return WC_Email|false
 */
function wctf_get_fazercards_giftcard_customer_completed_order_email() {
    if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->mailer() ) {
        return false;
    }

    foreach ( (array) WC()->mailer()->get_emails() as $email ) {
        if ( $email instanceof WC_Email && 'customer_completed_order' === (string) $email->id ) {
            return $email;
        }
    }

    return false;
}

/**
 * Process one locked standard WooCommerce Completed Order email attempt.
 *
 * @param int    $order_id WooCommerce order ID.
 * @param string $mode     automatic, send, or resend.
 * @return array
 */
function wctf_process_fazercards_giftcard_completed_order_email( $order_id, $mode = 'automatic' ) {
    $mode   = in_array( $mode, array( 'automatic', 'send', 'resend' ), true ) ? $mode : 'automatic';
    $order  = wc_get_order( absint( $order_id ) );
    $result = array(
        'success' => false,
        'status'  => 'blocked',
        'message' => __( 'The WooCommerce Completed Order email was not sent.', 'wc-topup-fields' ),
    );

    if ( ! $order instanceof WC_Order || ! wctf_fazercards_giftcard_order_has_delivery_items( $order ) ) {
        return $result;
    }

    $lock_token = wctf_acquire_fazercards_giftcard_completed_email_lock( $order->get_id() );

    if ( is_wp_error( $lock_token ) ) {
        $result['message'] = wctf_sanitize_fazercards_giftcard_delivery_error(
            $lock_token->get_error_message()
        );
        return $result;
    }

    $previous_status = wctf_normalize_fazercards_giftcard_completed_email_status(
        $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
    );
    $attempt_started = false;

    try {
        if ( 'automatic' === $mode && in_array( $previous_status, array( 'sending', 'sent', 'failed', 'blocked', 'legacy_delivered' ), true ) ) {
            $result['status']  = $previous_status;
            $result['message'] = __( 'Automatic Completed Order email was skipped safely.', 'wc-topup-fields' );
            return $result;
        }

        if ( 'send' === $mode && ! in_array( $previous_status, array( 'not_started', 'held', 'scheduled', 'failed', 'blocked' ), true ) ) {
            $result['status']  = $previous_status;
            $result['message'] = __( 'This order is not eligible for the SEND action.', 'wc-topup-fields' );
            return $result;
        }

        if ( 'resend' === $mode && ! in_array( $previous_status, array( 'sent', 'legacy_delivered' ), true ) ) {
            $result['status']  = $previous_status;
            $result['message'] = __( 'This order is not eligible for the RESEND action.', 'wc-topup-fields' );
            return $result;
        }

        $readiness = wctf_get_fazercards_giftcard_completed_email_readiness( $order );

        if ( 'ready' !== $readiness['status'] ) {
            $result['status']  = $readiness['status'];
            $result['message'] = $readiness['reason'];

            if ( 'automatic' === $mode ) {
                wctf_save_fazercards_giftcard_completed_email_state(
                    $order,
                    'blocked' === $readiness['status'] ? 'blocked' : 'held',
                    'blocked' === $readiness['status'] ? $readiness['reason'] : ''
                );
            }

            unset( $readiness );
            return $result;
        }

        unset( $readiness );

        $email = wctf_get_fazercards_giftcard_customer_completed_order_email();

        if ( ! $email instanceof WC_Email || ! method_exists( $email, 'trigger' ) ) {
            wctf_save_fazercards_giftcard_completed_email_state(
                $order,
                'blocked',
                __( 'The WooCommerce Customer Completed Order email object is unavailable.', 'wc-topup-fields' )
            );
            return $result;
        }

        $email_enabled = method_exists( $email, 'get_option' )
            ? (string) $email->get_option( 'enabled', 'yes' )
            : ( isset( $email->enabled ) ? (string) $email->enabled : 'no' );

        if ( 'yes' !== $email_enabled ) {
            $result['message'] = __( 'The WooCommerce Customer Completed Order email is disabled.', 'wc-topup-fields' );
            wctf_save_fazercards_giftcard_completed_email_state(
                $order,
                'blocked',
                $result['message']
            );
            return $result;
        }

        $recipient = sanitize_email( (string) $order->get_billing_email() );
        $attempts  = absint(
            $order->get_meta( '_wctf_fazer_giftcard_completed_email_attempts', true )
        ) + 1;
        $attempt_token = function_exists( 'wp_generate_uuid4' )
            ? wp_generate_uuid4()
            : wp_hash( $order->get_id() . '|' . $attempts . '|' . microtime( true ) );

        $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_status', 'sending' );
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_last_attempt_at',
            gmdate( 'Y-m-d H:i:s' )
        );
        $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_attempts', $attempts );
        $order->delete_meta_data( '_wctf_fazer_giftcard_completed_email_last_error' );
        $order->save();
        $attempt_started = true;

        wctf_fazercards_giftcard_completed_email_runtime(
            'set',
            array(
                'order_id'      => absint( $order->get_id() ),
                'mode'          => $mode,
                'attempt_token' => sanitize_text_field( $attempt_token ),
                'injected'      => false,
                'send_observed' => false,
                'send_result'   => false,
            )
        );

        try {
            $email->trigger( $order->get_id(), $order );
            $runtime = wctf_fazercards_giftcard_completed_email_runtime();
        } finally {
            wctf_fazercards_giftcard_completed_email_runtime( 'clear' );
        }

        $injected = is_array( $runtime ) && ! empty( $runtime['injected'] );
        $observed = is_array( $runtime ) && ! empty( $runtime['send_observed'] );
        $accepted = $observed && ! empty( $runtime['send_result'] );

        if ( $accepted && $injected ) {
            $sent_at = gmdate( 'Y-m-d H:i:s' );
            $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_status', 'sent' );

            if ( '' === (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_sent_at', true ) ) {
                $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_sent_at', $sent_at );
            }

            $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_recipient', $recipient );
            $order->delete_meta_data( '_wctf_fazer_giftcard_completed_email_last_error' );
            $order->save();
            $order->add_order_note(
                'resend' === $mode
                    ? __( 'WooCommerce Completed Order email resent by administrator.', 'wc-topup-fields' )
                    : __( 'WooCommerce Completed Order email sent with Gift Card delivery content.', 'wc-topup-fields' ),
                0,
                false
            );
            $result['success'] = true;
            $result['status']  = 'sent';
            $result['message'] = __( 'The WooCommerce Completed Order email was accepted by the mail transport.', 'wc-topup-fields' );
        } else {
            if ( ! $injected ) {
                $error = __( 'Gift Card content injection was not observed. The active email template must restore woocommerce_email_after_order_table.', 'wc-topup-fields' );
            } elseif ( ! $observed ) {
                $error = __( 'WooCommerce did not provide a reliable email-send result. Administrator review is required.', 'wc-topup-fields' );
            } else {
                $error = __( 'The WordPress mail transport did not accept the WooCommerce Completed Order email.', 'wc-topup-fields' );
            }

            $failure_status = 'resend' === $mode
                ? $previous_status
                : ( $injected && $observed ? 'failed' : 'blocked' );
            $order->update_meta_data(
                '_wctf_fazer_giftcard_completed_email_status',
                $failure_status
            );
            $order->update_meta_data(
                '_wctf_fazer_giftcard_completed_email_last_error',
                wctf_sanitize_fazercards_giftcard_delivery_error( $error )
            );
            $order->save();
            $result['status']  = $failure_status;
            $result['message'] = $error;

            if ( 'resend' !== $mode ) {
                $order->add_order_note(
                    __( 'WooCommerce Completed Order email blocked; administrator review required.', 'wc-topup-fields' ),
                    0,
                    false
                );
            }
        }
    } catch ( Throwable $throwable ) {
        unset( $throwable );
        wctf_fazercards_giftcard_completed_email_runtime( 'clear' );

        $error = $attempt_started
            ? __( 'The WooCommerce Completed Order email outcome is uncertain. Administrator review is required.', 'wc-topup-fields' )
            : __( 'The WooCommerce Completed Order email could not be started safely.', 'wc-topup-fields' );
        $failure_status = 'resend' === $mode ? $previous_status : 'blocked';
        $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_status', $failure_status );
        $order->update_meta_data( '_wctf_fazer_giftcard_completed_email_last_error', $error );
        $order->save();
        $result['status']  = $failure_status;
        $result['message'] = $error;
    } finally {
        wctf_fazercards_giftcard_completed_email_runtime( 'clear' );
        wctf_release_fazercards_giftcard_completed_email_lock(
            $order->get_id(),
            $lock_token
        );
    }

    return $result;
}

/**
 * Run one scheduled Completed Order email coordinator action.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function wctf_run_fazercards_giftcard_completed_order_email( $order_id ) {
    wctf_process_fazercards_giftcard_completed_order_email( absint( $order_id ), 'automatic' );
}

/**
 * End a fast-dispatch request without exposing validation or worker details.
 *
 * @return void
 */
function wctf_finish_fazercards_giftcard_completed_email_fast_send_request() {
    nocache_headers();
    status_header( 204 );
    exit;
}

/**
 * Validate and run one signed internal fast-dispatch request.
 *
 * The one-time token is consumed before the existing automatic worker starts.
 * The scheduled queue action remains available as the authoritative fallback.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_fast_send_completed_email() {
    $request_method = isset( $_SERVER['REQUEST_METHOD'] ) && is_scalar( $_SERVER['REQUEST_METHOD'] )
        ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
        : '';

    if ( 'POST' !== $request_method ) {
        wctf_finish_fazercards_giftcard_completed_email_fast_send_request();
    }

    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
        : 0;
    $issued_at = isset( $_POST['issued_at'] ) && is_scalar( $_POST['issued_at'] )
        ? absint( wp_unslash( $_POST['issued_at'] ) )
        : 0;
    $token = isset( $_POST['token'] ) && is_scalar( $_POST['token'] )
        ? sanitize_text_field( wp_unslash( $_POST['token'] ) )
        : '';
    $signature = isset( $_POST['signature'] ) && is_scalar( $_POST['signature'] )
        ? strtolower( sanitize_text_field( wp_unslash( $_POST['signature'] ) ) )
        : '';
    $now = time();

    if (
        0 >= $order_id
        || 0 >= $issued_at
        || $issued_at > $now + 60
        || $now - $issued_at > 5 * MINUTE_IN_SECONDS
        || ! preg_match( '/\A[a-zA-Z0-9]{32,128}\z/', $token )
        || ! preg_match( '/\A[a-f0-9]{64}\z/', $signature )
    ) {
        wctf_finish_fazercards_giftcard_completed_email_fast_send_request();
    }

    $message            = 'v1|' . $order_id . '|' . $issued_at . '|' . $token;
    $expected_signature = hash_hmac( 'sha256', $message, wp_salt( 'auth' ) );

    if ( ! hash_equals( $expected_signature, $signature ) ) {
        wctf_finish_fazercards_giftcard_completed_email_fast_send_request();
    }

    $token_hash    = hash( 'sha256', $token );
    $transient_key = wctf_get_fazercards_giftcard_completed_email_fast_token_key( $token_hash );
    $stored_token  = get_transient( $transient_key );

    if (
        ! is_array( $stored_token )
        || ! isset( $stored_token['token_hash'], $stored_token['order_id'], $stored_token['issued_at'] )
        || ! is_string( $stored_token['token_hash'] )
        || ! hash_equals( $stored_token['token_hash'], $token_hash )
        || absint( $stored_token['order_id'] ) !== $order_id
        || absint( $stored_token['issued_at'] ) !== $issued_at
    ) {
        wctf_finish_fazercards_giftcard_completed_email_fast_send_request();
    }

    $order = wc_get_order( $order_id );

    if (
        ! $order instanceof WC_Order
        || ! wctf_fazercards_giftcard_order_has_delivery_items( $order )
        || 'completed' !== sanitize_key( (string) $order->get_status() )
        || ! wctf_is_fazercards_giftcard_customer_order_paid( $order )
    ) {
        wctf_finish_fazercards_giftcard_completed_email_fast_send_request();
    }

    $coordinator_status = wctf_normalize_fazercards_giftcard_completed_email_status(
        $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
    );

    if ( in_array( $coordinator_status, array( 'sending', 'sent', 'legacy_delivered' ), true ) ) {
        wctf_finish_fazercards_giftcard_completed_email_fast_send_request();
    }

    $readiness = wctf_get_fazercards_giftcard_completed_email_readiness( $order );

    if ( 'ready' !== $readiness['status'] ) {
        unset( $readiness );
        wctf_finish_fazercards_giftcard_completed_email_fast_send_request();
    }

    unset( $readiness );

    if ( ! delete_transient( $transient_key ) ) {
        wctf_finish_fazercards_giftcard_completed_email_fast_send_request();
    }

    $order->update_meta_data(
        '_wctf_fazer_giftcard_completed_email_fast_dispatch_status',
        'running'
    );
    $order->save();

    try {
        $worker_result = wctf_process_fazercards_giftcard_completed_order_email(
            $order_id,
            'automatic'
        );
    } catch ( Throwable $throwable ) {
        unset( $throwable );
        $worker_result = array(
            'success' => false,
            'status'  => 'blocked',
            'message' => __( 'The fast email worker could not complete safely; the fallback queue remains active.', 'wc-topup-fields' ),
        );
    }

    $order = wc_get_order( $order_id );

    if ( $order instanceof WC_Order ) {
        $coordinator_status = wctf_normalize_fazercards_giftcard_completed_email_status(
            $order->get_meta( '_wctf_fazer_giftcard_completed_email_status', true )
        );
        $fast_status = 'sent' === $coordinator_status ? 'completed' : 'fallback_only';

        if ( in_array( $coordinator_status, array( 'failed', 'blocked' ), true ) ) {
            $fast_status = 'failed';
        }

        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_status',
            $fast_status
        );
        $order->update_meta_data(
            '_wctf_fazer_giftcard_completed_email_fast_dispatch_completed_at',
            gmdate( 'Y-m-d H:i:s' )
        );

        if ( 'failed' === $fast_status ) {
            $safe_error = isset( $worker_result['message'] )
                ? wctf_sanitize_fazercards_giftcard_delivery_error( $worker_result['message'] )
                : __( 'The fast email worker did not complete successfully.', 'wc-topup-fields' );
            $order->update_meta_data(
                '_wctf_fazer_giftcard_completed_email_fast_dispatch_last_error',
                $safe_error
            );
        } else {
            $order->delete_meta_data( '_wctf_fazer_giftcard_completed_email_fast_dispatch_last_error' );
        }

        $order->save();
    }

    unset( $worker_result );
    wctf_finish_fazercards_giftcard_completed_email_fast_send_request();
}

/**
 * Observe only the currently authorized Completed Order email result.
 *
 * @param bool     $sent  Whether the transport accepted the message.
 * @param string   $id    WooCommerce email ID.
 * @param WC_Email $email WooCommerce email object.
 * @return void
 */
function wctf_observe_fazercards_giftcard_completed_order_email_sent( $sent, $id = '', $email = null ) {
    $context = wctf_fazercards_giftcard_completed_email_runtime();
    $email_matches = is_array( $context )
        && isset( $context['order_id'] )
        && (
            null === $email
            || (
                $email instanceof WC_Email
                && $email->object instanceof WC_Order
                && absint( $email->object->get_id() ) === absint( $context['order_id'] )
            )
        );

    if (
        ! is_array( $context )
        || 'customer_completed_order' !== (string) $id
        || ! $email_matches
    ) {
        return;
    }

    wctf_fazercards_giftcard_completed_email_runtime( 'observed', (bool) $sent );
}

/**
 * Prevent transport when the active template omitted Gift Card injection.
 *
 * @param callable|string $callback Current mail callback.
 * @param WC_Email        $email    WooCommerce email object.
 * @return callable|string
 */
function wctf_filter_fazercards_giftcard_completed_order_mail_callback( $callback, $email = null ) {
    $context = wctf_fazercards_giftcard_completed_email_runtime();
    $email_matches = is_array( $context )
        && isset( $context['order_id'] )
        && (
            null === $email
            || (
                $email instanceof WC_Email
                && 'customer_completed_order' === (string) $email->id
                && $email->object instanceof WC_Order
                && absint( $email->object->get_id() ) === absint( $context['order_id'] )
            )
        );

    if (
        is_array( $context )
        && $email_matches
        && empty( $context['injected'] )
    ) {
        return 'wctf_block_fazercards_giftcard_uninjected_completed_email';
    }

    return $callback;
}

/**
 * Fail-closed mail callback used when content injection was not observed.
 *
 * @param mixed $to          Recipient supplied by WooCommerce.
 * @param mixed $subject     Subject supplied by WooCommerce.
 * @param mixed $message     Message supplied by WooCommerce.
 * @param mixed $headers     Headers supplied by WooCommerce.
 * @param mixed $attachments Attachments supplied by WooCommerce.
 * @return bool
 */
function wctf_block_fazercards_giftcard_uninjected_completed_email( $to = null, $subject = null, $message = null, $headers = null, $attachments = null ) {
    unset( $to, $subject, $message, $headers, $attachments );
    return false;
}

/**
 * Render validated Gift Card entries in the standard Completed Order email.
 *
 * @param WC_Order $order         WooCommerce order.
 * @param bool     $sent_to_admin Whether this is an admin email.
 * @param bool     $plain_text    Whether plain-text output is requested.
 * @param WC_Email $email         WooCommerce email object.
 * @return void
 */
function wctf_render_fazercards_giftcard_completed_order_email_content( $order, $sent_to_admin, $plain_text = false, $email = null ) {
    $context = wctf_fazercards_giftcard_completed_email_runtime();

    if (
        $sent_to_admin
        || ! $order instanceof WC_Order
        || ! $email instanceof WC_Email
        || 'customer_completed_order' !== (string) $email->id
        || ! is_array( $context )
        || ! isset( $context['order_id'] )
        || absint( $context['order_id'] ) !== absint( $order->get_id() )
    ) {
        return;
    }

    $readiness = wctf_get_fazercards_giftcard_completed_email_readiness( $order );

    if ( 'ready' !== $readiness['status'] || empty( $readiness['items'] ) ) {
        unset( $readiness );
        return;
    }

    wctf_fazercards_giftcard_completed_email_runtime( 'injected' );

    if ( $plain_text ) {
        echo "\n" . esc_html__( 'Gift Card Delivery', 'wc-topup-fields' ) . "\n";
        echo "==============================\n";

        foreach ( $readiness['items'] as $delivery_item ) {
            $quantity = max( 0, absint( $delivery_item['quantity'] ) );
            echo esc_html( $delivery_item['product_name'] );

            if ( 0 < $quantity ) {
                echo ' x ' . esc_html( (string) $quantity );
            }

            echo "\n";

            foreach ( $delivery_item['entries'] as $index => $entry ) {
                echo esc_html( sprintf( __( 'Gift Card #%d', 'wc-topup-fields' ), absint( $index + 1 ) ) ) . ":\n";
                echo esc_html( (string) $entry ) . "\n";
            }

            echo "\n";
        }

        echo esc_html__( 'Keep this code safe.', 'wc-topup-fields' ) . "\n\n";
    } else {
        echo '<section class="wctf-giftcard-completed-email">';
        echo '<h2>' . esc_html__( 'Gift Card Delivery', 'wc-topup-fields' ) . '</h2>';

        foreach ( $readiness['items'] as $delivery_item ) {
            $quantity = max( 0, absint( $delivery_item['quantity'] ) );
            echo '<h3>' . esc_html( $delivery_item['product_name'] );

            if ( 0 < $quantity ) {
                echo ' &times; ' . esc_html( (string) $quantity );
            }

            echo '</h3>';

            foreach ( $delivery_item['entries'] as $index => $entry ) {
                echo '<p><strong>' . esc_html( sprintf( __( 'Gift Card #%d', 'wc-topup-fields' ), absint( $index + 1 ) ) ) . '</strong></p>';
                echo '<pre style="white-space:pre-wrap;word-break:break-word">' . esc_html( (string) $entry ) . '</pre>';
            }
        }

        echo '<p><strong>' . esc_html__( 'Keep this code safe.', 'wc-topup-fields' ) . '</strong></p>';
        echo '</section>';
    }

    unset( $readiness );
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
    unset( $order_id, $item_id );
    return;

    // Legacy implementation retained below for source compatibility only.
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
    return array(
        'subject' => '',
        'body'    => '',
    );

    // Legacy implementation retained below for source compatibility only.
    $order_number = sanitize_text_field( (string) $order->get_order_number() );
    $product_name = sanitize_text_field( (string) $product_name );
    $subject      = '';
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
    return array(
        'success' => false,
        'status'  => 'blocked',
        'message' => __( 'The retired separate Gift Card email path is disabled.', 'wc-topup-fields' ),
    );

    // Legacy implementation retained below for source compatibility only.
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
        $mail_sent      = false;
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
    unset( $order_id, $item_id );
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
 * Handle a confirmed order-level SEND action.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_send_completed_order_email() {
    wctf_handle_fazercards_giftcard_completed_email_admin_action( 'send' );
}

/**
 * Handle a confirmed order-level RESEND action.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_resend_completed_order_email() {
    wctf_handle_fazercards_giftcard_completed_email_admin_action( 'resend' );
}

/**
 * Validate and run one administrator-confirmed Completed Order email action.
 *
 * @param string $mode send or resend.
 * @return void
 */
function wctf_handle_fazercards_giftcard_completed_email_admin_action( $mode ) {
    $mode     = 'resend' === $mode ? 'resend' : 'send';
    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
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
            esc_html__( 'You are not allowed to send the Completed Order email for this order.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $nonce = isset( $_POST['nonce'] ) && is_scalar( $_POST['nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
        : '';
    $nonce_action = 'wctf_gc_completed_email_' . $mode . '_' . $order_id;

    if ( '' === $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
        wctf_finish_fazercards_giftcard_customer_delivery_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'The Completed Order email request reached the server, but its security nonce was invalid.', 'wc-topup-fields' ),
            )
        );
    }

    $confirmed = isset( $_POST['completed_email_confirmed'] ) && is_scalar( $_POST['completed_email_confirmed'] )
        ? sanitize_text_field( wp_unslash( $_POST['completed_email_confirmed'] ) )
        : '';
    $confirmation_text = isset( $_POST['completed_email_confirmation_text'] ) && is_scalar( $_POST['completed_email_confirmation_text'] )
        ? (string) wp_unslash( $_POST['completed_email_confirmation_text'] )
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

    $readiness = wctf_get_fazercards_giftcard_completed_email_readiness( $order );

    if ( 'ready' !== $readiness['status'] ) {
        $message = $readiness['reason'];
        unset( $readiness );
        wctf_finish_fazercards_giftcard_customer_delivery_action(
            $order,
            array(
                'success' => false,
                'message' => $message,
            )
        );
    }

    unset( $readiness );
    $result = wctf_process_fazercards_giftcard_completed_order_email( $order_id, $mode );
    wctf_finish_fazercards_giftcard_customer_delivery_action( $order, $result );
}

/**
 * Render the order-level Completed Order email coordinator status and controls.
 *
 * @param WP_Post|WC_Order $post_or_order_object Order screen object.
 * @return void
 */
function wctf_render_fazercards_giftcard_completed_email_admin_meta_box( $post_or_order_object ) {
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
        echo '<p>' . esc_html__( 'You are not allowed to manage this Completed Order email.', 'wc-topup-fields' ) . '</p>';
        return;
    }

    if ( ! wctf_fazercards_giftcard_order_has_delivery_items( $order ) ) {
        echo '<p>' . esc_html__( 'No Gift Card snapshot items were detected.', 'wc-topup-fields' ) . '</p>';
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

    $status = wctf_apply_fazercards_giftcard_completed_email_legacy_state( $order, false );
    $order  = wc_get_order( $order_id );
    $readiness = wctf_get_fazercards_giftcard_completed_email_readiness( $order );
    $status_labels = array(
        'not_started'     => __( 'Not started', 'wc-topup-fields' ),
        'held'            => __( 'Held', 'wc-topup-fields' ),
        'scheduled'       => __( 'Scheduled', 'wc-topup-fields' ),
        'sending'         => __( 'Sending - admin review required if stale', 'wc-topup-fields' ),
        'sent'            => __( 'Sent', 'wc-topup-fields' ),
        'failed'          => __( 'Failed', 'wc-topup-fields' ),
        'blocked'         => __( 'Blocked', 'wc-topup-fields' ),
        'legacy_delivered' => __( 'Legacy delivered', 'wc-topup-fields' ),
    );
    $readiness_labels = array(
        'ready'   => __( 'Ready', 'wc-topup-fields' ),
        'held'    => __( 'Held', 'wc-topup-fields' ),
        'blocked' => __( 'Blocked', 'wc-topup-fields' ),
    );
    $fast_dispatch_labels = array(
        'not_started'  => __( 'Not started', 'wc-topup-fields' ),
        'dispatched'   => __( 'Dispatched', 'wc-topup-fields' ),
        'running'      => __( 'Running', 'wc-topup-fields' ),
        'completed'    => __( 'Completed', 'wc-topup-fields' ),
        'fallback_only' => __( 'Fallback queue only', 'wc-topup-fields' ),
        'failed'       => __( 'Failed', 'wc-topup-fields' ),
    );
    $recipient = sanitize_email(
        (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_recipient', true )
    );
    $future_action = wctf_get_fazercards_giftcard_completed_email_future_action( $order_id );
    $future_timestamp = ! empty( $future_action['timestamp'] )
        ? gmdate( 'Y-m-d H:i:s', absint( $future_action['timestamp'] ) )
        : '';
    $backend_labels = array(
        'action_scheduler' => __( 'Action Scheduler', 'wc-topup-fields' ),
        'wp_cron'          => __( 'WP-Cron fallback', 'wc-topup-fields' ),
    );
    $stored_backend = sanitize_key(
        (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_scheduler_backend', true )
    );
    $display_backend = isset( $backend_labels[ $stored_backend ] )
        ? $backend_labels[ $stored_backend ]
        : ( isset( $backend_labels[ $future_action['backend'] ] )
            ? $backend_labels[ $future_action['backend'] ]
            : __( 'Not available', 'wc-topup-fields' ) );
    $fast_dispatch_status = sanitize_key(
        (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_fast_dispatch_status', true )
    );
    $fast_dispatch_status = isset( $fast_dispatch_labels[ $fast_dispatch_status ] )
        ? $fast_dispatch_status
        : 'not_started';
    $rows = array(
        __( 'Coordinator status', 'wc-topup-fields' ) => $status_labels[ $status ],
        __( 'Whole-order readiness', 'wc-topup-fields' ) => isset( $readiness_labels[ $readiness['status'] ] )
            ? $readiness_labels[ $readiness['status'] ]
            : __( 'Blocked', 'wc-topup-fields' ),
        __( 'Readiness reason', 'wc-topup-fields' ) => sanitize_text_field( $readiness['reason'] ),
        __( 'Recipient', 'wc-topup-fields' ) => '' === $recipient
            ? __( 'Not sent', 'wc-topup-fields' )
            : $recipient,
        __( 'Held at (UTC)', 'wc-topup-fields' ) => sanitize_text_field(
            (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_held_at', true )
        ),
        __( 'Scheduled at (UTC)', 'wc-topup-fields' ) => sanitize_text_field(
            (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_scheduled_at', true )
        ),
        __( 'Completed hook observed at (UTC)', 'wc-topup-fields' ) => sanitize_text_field(
            (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_completed_hook_at', true )
        ),
        __( 'Ready hook observed at (UTC)', 'wc-topup-fields' ) => sanitize_text_field(
            (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_ready_hook_at', true )
        ),
        __( 'Email repair last at (UTC)', 'wc-topup-fields' ) => sanitize_text_field(
            (string) $order->get_meta( '_wctf_fazer_giftcard_email_repair_last_at', true )
        ),
        __( 'Email repair result', 'wc-topup-fields' ) => sanitize_key(
            (string) $order->get_meta( '_wctf_fazer_giftcard_email_repair_result', true )
        ),
        __( 'Last schedule reason', 'wc-topup-fields' ) => wctf_sanitize_fazercards_giftcard_delivery_error(
            $order->get_meta( '_wctf_fazer_giftcard_completed_email_schedule_reason', true )
        ),
        __( 'Queue backend', 'wc-topup-fields' ) => $display_backend,
        __( 'Fallback Scheduled Action', 'wc-topup-fields' ) => ! empty( $future_action['scheduled'] )
            ? __( 'Yes', 'wc-topup-fields' )
            : __( 'No', 'wc-topup-fields' ),
        __( 'Future action timestamp (UTC)', 'wc-topup-fields' ) => $future_timestamp,
        __( 'Fast dispatch status', 'wc-topup-fields' ) => $fast_dispatch_labels[ $fast_dispatch_status ],
        __( 'Fast dispatch attempted at (UTC)', 'wc-topup-fields' ) => sanitize_text_field(
            (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_fast_dispatch_at', true )
        ),
        __( 'Fast dispatch completed at (UTC)', 'wc-topup-fields' ) => sanitize_text_field(
            (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_fast_dispatch_completed_at', true )
        ),
        __( 'Fast dispatch last safe error', 'wc-topup-fields' ) => wctf_sanitize_fazercards_giftcard_delivery_error(
            $order->get_meta( '_wctf_fazer_giftcard_completed_email_fast_dispatch_last_error', true )
        ),
        __( 'Last attempt (UTC)', 'wc-topup-fields' ) => sanitize_text_field(
            (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_last_attempt_at', true )
        ),
        __( 'First sent at (UTC)', 'wc-topup-fields' ) => sanitize_text_field(
            (string) $order->get_meta( '_wctf_fazer_giftcard_completed_email_sent_at', true )
        ),
        __( 'Attempts', 'wc-topup-fields' ) => (string) absint(
            $order->get_meta( '_wctf_fazer_giftcard_completed_email_attempts', true )
        ),
        __( 'Last safe error', 'wc-topup-fields' ) => wctf_sanitize_fazercards_giftcard_delivery_error(
            $order->get_meta( '_wctf_fazer_giftcard_completed_email_last_error', true )
        ),
        __( 'Legacy delivered', 'wc-topup-fields' ) => 'legacy_delivered' === $status
            ? __( 'Yes', 'wc-topup-fields' )
            : __( 'No', 'wc-topup-fields' ),
    );

    echo '<p>' . esc_html__( 'This coordinator sends the standard WooCommerce Customer Completed Order email only after every Gift Card item is safely deliverable.', 'wc-topup-fields' ) . '</p>';
    echo '<p>' . esc_html__( 'A successful result means the mail transport accepted the message; it does not guarantee inbox delivery.', 'wc-topup-fields' ) . '</p>';
    echo '<table class="widefat striped"><tbody>';

    foreach ( $rows as $label => $value ) {
        $value = '' === $value ? __( 'Not available', 'wc-topup-fields' ) : $value;
        echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
    }

    echo '</tbody></table>';

    if ( 'ready' === $readiness['status'] ) {
        if ( in_array( $status, array( 'not_started', 'held', 'scheduled', 'failed', 'blocked' ), true ) ) {
            wctf_render_fazercards_giftcard_completed_email_admin_control( $order_id, 'send' );
        } elseif ( in_array( $status, array( 'sent', 'legacy_delivered' ), true ) ) {
            wctf_render_fazercards_giftcard_completed_email_admin_control( $order_id, 'resend' );
        }
    }

    unset( $readiness, $rows, $future_action );
    wctf_render_fazercards_giftcard_completed_email_admin_script();
}

/**
 * Render an order-level standalone SEND or RESEND control.
 *
 * @param int    $order_id WooCommerce order ID.
 * @param string $mode     send or resend.
 * @return void
 */
function wctf_render_fazercards_giftcard_completed_email_admin_control( $order_id, $mode ) {
    $mode         = 'resend' === $mode ? 'resend' : 'send';
    $expected     = 'resend' === $mode ? 'RESEND' : 'SEND';
    $confirmed_id = 'wctf-gc-completed-email-confirmed-' . $mode . '-' . absint( $order_id );
    $text_id      = 'wctf-gc-completed-email-text-' . $mode . '-' . absint( $order_id );
    $action       = 'wctf_fazercards_giftcard_' . $mode . '_completed_order_email';
    $nonce        = wp_create_nonce(
        'wctf_gc_completed_email_' . $mode . '_' . absint( $order_id )
    );

    echo '<div class="wctf-giftcard-completed-email-control">';
    echo '<p><strong>';
    echo esc_html(
        'resend' === $mode
            ? __( 'This will resend the standard WooCommerce Completed Order email with all validated Gift Card codes.', 'wc-topup-fields' )
            : __( 'This will send the standard WooCommerce Completed Order email with all validated Gift Card codes.', 'wc-topup-fields' )
    );
    echo '</strong></p>';
    echo '<p><label for="' . esc_attr( $confirmed_id ) . '"><input type="checkbox" id="' . esc_attr( $confirmed_id ) . '"> ';
    echo esc_html( sprintf( __( 'I understand this will %s the customer Completed Order email.', 'wc-topup-fields' ), strtolower( $expected ) ) );
    echo '</label></p>';
    echo '<p><label for="' . esc_attr( $text_id ) . '">' . esc_html( sprintf( __( 'Type %s to confirm:', 'wc-topup-fields' ), $expected ) ) . '</label> ';
    echo '<input type="text" id="' . esc_attr( $text_id ) . '" autocomplete="off"></p>';
    echo '<p><button type="button" class="button button-primary wctf-giftcard-completed-email-action"';
    echo ' data-action-url="' . esc_url( admin_url( 'admin-post.php' ) ) . '"';
    echo ' data-action="' . esc_attr( $action ) . '"';
    echo ' data-order-id="' . esc_attr( absint( $order_id ) ) . '"';
    echo ' data-nonce="' . esc_attr( $nonce ) . '"';
    echo ' data-confirmed-id="' . esc_attr( $confirmed_id ) . '"';
    echo ' data-text-id="' . esc_attr( $text_id ) . '"';
    echo ' data-expected="' . esc_attr( $expected ) . '">';
    echo esc_html(
        'resend' === $mode
            ? __( 'Resend WooCommerce Completed Order Email', 'wc-topup-fields' )
            : __( 'Send WooCommerce Completed Order Email', 'wc-topup-fields' )
    );
    echo '</button></p>';
    echo '</div>';
}

/**
 * Render the order-level standalone-form controller.
 *
 * @return void
 */
function wctf_render_fazercards_giftcard_completed_email_admin_script() {
    ?>
    <script>
    ( function() {
        if ( window.wctfFazerCardsGiftCardCompletedEmailBound ) {
            return;
        }

        window.wctfFazerCardsGiftCardCompletedEmailBound = true;

        function addField( form, name, value ) {
            var field = document.createElement( 'input' );
            field.type = 'hidden';
            field.name = name;
            field.value = value;
            form.appendChild( field );
        }

        document.addEventListener( 'click', function( event ) {
            var button = event.target.closest
                ? event.target.closest( '.wctf-giftcard-completed-email-action' )
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
                    text.addEventListener( 'input', function clearCompletedEmailError() {
                        text.setCustomValidity( '' );
                        text.removeEventListener( 'input', clearCompletedEmailError );
                    } );
                }
                return;
            }

            var form = document.createElement( 'form' );
            form.method = 'post';
            form.action = button.getAttribute( 'data-action-url' );

            addField( form, 'action', button.getAttribute( 'data-action' ) || '' );
            addField( form, 'order_id', button.getAttribute( 'data-order-id' ) || '' );
            addField( form, 'nonce', button.getAttribute( 'data-nonce' ) || '' );
            addField( form, 'completed_email_confirmed', '1' );
            addField( form, 'completed_email_confirmation_text', text.value );

            document.body.appendChild( form );
            form.submit();
        } );
    }() );
    </script>
    <?php
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

    echo '<p>' . esc_html__( 'Email delivery is independent from the customer order-page display. Mail transport acceptance does not guarantee inbox delivery.', 'wc-topup-fields' ) . '</p>';

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
            'earlyPollInterval' => 2000,
            'earlyPollTime' => 45000,
            'pollInterval'  => 5000,
            'maxPollTime'   => 120000,
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

    if ( function_exists( 'wctf_maybe_run_fazercards_giftcard_customer_early_retrieval' ) ) {
        $customer_early_authorized_at = time();

        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            wctf_maybe_run_fazercards_giftcard_customer_early_retrieval(
                $order,
                $item,
                absint( $item_id ),
                $customer_early_authorized_at
            );
        }

        $refreshed_order = wc_get_order( $order_id );

        if ( $refreshed_order instanceof WC_Order ) {
            $order = $refreshed_order;
        }

        unset( $refreshed_order, $customer_early_authorized_at );
    }

    $state = wctf_get_fazercards_giftcard_customer_order_state( $order );

    if ( function_exists( 'wctf_maybe_repair_fazercards_giftcard_completed_email_coordination' ) ) {
        wctf_maybe_repair_fazercards_giftcard_completed_email_coordination(
            $order,
            $state
        );

        $coordinated_order = wc_get_order( $order_id );

        if ( $coordinated_order instanceof WC_Order ) {
            $order = $coordinated_order;
            $state = wctf_get_fazercards_giftcard_customer_order_state( $order );
        }

        unset( $coordinated_order );
    }

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
