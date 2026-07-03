<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'woocommerce_checkout_create_order_line_item', 'wctf_save_order_item_customer_fields', 10, 4 );
add_action( 'woocommerce_checkout_create_order_line_item', 'wctf_snapshot_fazercards_order_item_binding', 20, 4 );
add_action( 'woocommerce_after_order_itemmeta', 'wctf_display_fazercards_order_item_payload', 10, 3 );

/**
 * Save validated customer fields as visible WooCommerce order item metadata.
 *
 * @param WC_Order_Item_Product $item          Order line item.
 * @param string                $cart_item_key Cart item key.
 * @param array                 $values        Cart item values.
 * @param WC_Order              $order         Order object.
 */
function wctf_save_order_item_customer_fields( $item, $cart_item_key, $values, $order ) {
    unset( $cart_item_key, $order );

    $field_keys = wctf_get_cart_item_customer_field_keys( $values );
    $field_data = isset( $values['wctf_customer_fields'] ) && is_array( $values['wctf_customer_fields'] )
        ? $values['wctf_customer_fields']
        : array();

    foreach ( $field_keys as $field_key ) {
        if ( ! isset( $field_data[ $field_key ] ) ) {
            continue;
        }

        $value = wctf_sanitize_customer_field_value( $field_key, $field_data[ $field_key ] );

        if (
            '' === $value
            || ( wctf_is_email_customer_field( $field_key ) && ! is_email( $value ) )
        ) {
            continue;
        }

        $item->add_meta_data(
            wctf_get_customer_field_label( $field_key ),
            $value,
            true
        );
    }
}

/**
 * Snapshot FazerCards product binding data onto an eligible order line item.
 *
 * @param WC_Order_Item_Product $item          Order line item.
 * @param string                $cart_item_key Cart item key.
 * @param array                 $values        Cart item values.
 * @param WC_Order              $order         WooCommerce order.
 */
function wctf_snapshot_fazercards_order_item_binding( $item, $cart_item_key, $values, $order ) {
    unset( $cart_item_key, $order );

    if (
        ! $item instanceof WC_Order_Item_Product
        || ! is_array( $values )
        || ! isset( $values['data'] )
        || ! $values['data'] instanceof WC_Product
    ) {
        return;
    }

    $product = $values['data'];

    if ( ! in_array( $product->get_type(), array( 'simple', 'game' ), true ) ) {
        return;
    }

    $product_id = absint( $product->get_id() );
    $offer_id   = wctf_normalize_fazercards_payload_value( get_post_meta( $product_id, '_fazer_offer_id', true ) );

    if ( '' === $offer_id ) {
        return;
    }

    $snapshot = array(
        '_wctf_fazer_category_id'        => wctf_normalize_fazercards_payload_value( get_post_meta( $product_id, '_fazer_category_id', true ) ),
        '_wctf_fazer_offer_id'           => $offer_id,
        '_wctf_fazer_offer_name'         => wctf_normalize_fazercards_payload_value( get_post_meta( $product_id, '_fazer_offer_name', true ) ),
        '_wctf_fazer_price_usd'          => wctf_normalize_fazercards_payload_value( get_post_meta( $product_id, '_fazer_price_usd', true ) ),
        '_wctf_fazer_product_id'         => $product_id,
        '_wctf_fazer_snapshot_created_at' => wctf_normalize_fazercards_payload_value( current_time( 'mysql', true ) ),
    );

    foreach ( $snapshot as $meta_key => $meta_value ) {
        $item->add_meta_data( $meta_key, $meta_value, true );
    }
}

/**
 * Prepare a local FazerCards payload preview for an order line item.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    Order line item.
 * @param int                   $item_id Order item ID.
 * @return array
 */
function wctf_prepare_fazercards_order_item_payload( $order, $item, $item_id ) {
    $result = array(
        'success'  => false,
        'warnings' => array(),
        'payload'  => array(),
    );

    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        $result['warnings'][] = __( 'The WooCommerce order item is invalid.', 'wc-topup-fields' );
        return $result;
    }

    $snapshot_keys = array(
        '_wctf_fazer_category_id',
        '_wctf_fazer_offer_id',
        '_wctf_fazer_offer_name',
        '_wctf_fazer_price_usd',
        '_wctf_fazer_product_id',
        '_wctf_fazer_snapshot_created_at',
    );
    $has_snapshot = false;

    foreach ( $snapshot_keys as $snapshot_key ) {
        if ( $item->meta_exists( $snapshot_key ) ) {
            $has_snapshot = true;
            break;
        }
    }

    if ( $has_snapshot ) {
        $product_id         = absint( $item->get_meta( '_wctf_fazer_product_id', true ) );
        $category_id        = wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_category_id', true ) );
        $offer_id           = wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_offer_id', true ) );
        $offer_name         = wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_offer_name', true ) );
        $price_usd          = wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_price_usd', true ) );
        $snapshot_created_at = wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_snapshot_created_at', true ) );
    } else {
        $product = $item->get_product();

        if ( ! $product ) {
            $result['warnings'][] = __( 'The product no longer exists.', 'wc-topup-fields' );
            return $result;
        }

        if ( ! in_array( $product->get_type(), array( 'simple', 'game' ), true ) ) {
            $result['warnings'][] = __( 'Only simple and game products are supported.', 'wc-topup-fields' );
            return $result;
        }

        $product_id         = absint( $product->get_id() );
        $category_id        = wctf_normalize_fazercards_payload_value( get_post_meta( $product_id, '_fazer_category_id', true ) );
        $offer_id           = wctf_normalize_fazercards_payload_value( get_post_meta( $product_id, '_fazer_offer_id', true ) );
        $offer_name         = wctf_normalize_fazercards_payload_value( get_post_meta( $product_id, '_fazer_offer_name', true ) );
        $price_usd          = wctf_normalize_fazercards_payload_value( get_post_meta( $product_id, '_fazer_price_usd', true ) );
        $snapshot_created_at = '';

        if ( '' === $offer_id ) {
            $result['warnings'][] = __( 'The product is not bound to a FazerCards offer.', 'wc-topup-fields' );
            return $result;
        }
    }

    $binding_fields = array(
        'product_id'  => $product_id,
        'category_id' => $category_id,
        'offer_id'    => $offer_id,
        'offer_name'  => $offer_name,
        'price_usd'   => $price_usd,
    );

    foreach ( $binding_fields as $binding_key => $binding_value ) {
        if ( ( 'product_id' === $binding_key && 0 !== $binding_value ) || ( 'product_id' !== $binding_key && '' !== $binding_value ) ) {
            continue;
        }

        $result['warnings'][] = sprintf(
            /* translators: %s: missing binding field key. */
            __( 'Missing product binding data: %s.', 'wc-topup-fields' ),
            $binding_key
        );
    }

    if ( $has_snapshot && '' === $snapshot_created_at ) {
        $result['warnings'][] = __( 'Missing snapshot data: snapshot_created_at.', 'wc-topup-fields' );
    }

    $customer_fields = array();
    $field_keys      = wctf_get_product_customer_field_keys( $product_id );

    if ( empty( $field_keys ) ) {
        $result['warnings'][] = __( 'No customer fields are configured in _topup_fields.', 'wc-topup-fields' );
    }

    foreach ( $field_keys as $field_key ) {
        $field_label = wctf_get_customer_field_label( $field_key );
        $stored_value = $item->get_meta( $field_label, true );
        $field_value  = wctf_is_email_customer_field( $field_key )
            ? sanitize_email( is_scalar( $stored_value ) ? (string) $stored_value : '' )
            : wctf_normalize_fazercards_payload_value( $stored_value );

        if ( '' === $field_value ) {
            $result['warnings'][] = sprintf(
                /* translators: %s: missing customer field label. */
                __( 'Missing customer field: %s.', 'wc-topup-fields' ),
                $field_label
            );
            continue;
        }

        if ( wctf_is_email_customer_field( $field_key ) && ! is_email( $field_value ) ) {
            $result['warnings'][] = sprintf(
                /* translators: %s: invalid customer field label. */
                __( 'Invalid customer field: %s.', 'wc-topup-fields' ),
                $field_label
            );
            continue;
        }

        $customer_fields[ $field_key ] = $field_value;
    }

    $result['success'] = true;
    $result['payload'] = array(
        'woocommerce_order_id'      => absint( $order->get_id() ),
        'woocommerce_order_item_id' => absint( $item_id ),
        'product_id'                => $product_id,
        'category_id'               => $category_id,
        'offer_id'                  => $offer_id,
        'offer_name'                => $offer_name,
        'price_usd'                 => $price_usd,
        'quantity'                  => max( 0, (int) $item->get_quantity() ),
        'customer_fields'           => $customer_fields,
    );

    return $result;
}

/**
 * Normalize a scalar value for a local FazerCards payload preview.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function wctf_normalize_fazercards_payload_value( $value ) {
    if ( ! is_scalar( $value ) ) {
        return '';
    }

    return sanitize_text_field( (string) $value );
}

/**
 * Display a local FazerCards payload preview below an admin order item.
 *
 * @param int                   $item_id Order item ID.
 * @param WC_Order_Item_Product $item    Order line item.
 * @param WC_Product|null       $product Product object.
 */
function wctf_display_fazercards_order_item_payload( $item_id, $item, $product ) {
    unset( $product );

    if ( ! is_admin() || ! $item instanceof WC_Order_Item_Product ) {
        return;
    }

    $order = wc_get_order( $item->get_order_id() );

    if ( ! $order ) {
        return;
    }

    $prepared = wctf_prepare_fazercards_order_item_payload( $order, $item, $item_id );

    if ( empty( $prepared['success'] ) ) {
        return;
    }

    $json = wp_json_encode(
        $prepared['payload'],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ( false === $json ) {
        $json = '{}';
        $prepared['warnings'][] = __( 'The payload preview could not be encoded.', 'wc-topup-fields' );
    }

    ?>
    <div class="wctf-fazercards-payload-preview">
        <h4><?php esc_html_e( 'FazerCards Payload Preview', 'wc-topup-fields' ); ?></h4>

        <?php if ( ! empty( $prepared['warnings'] ) ) : ?>
            <div class="notice notice-warning inline" role="alert">
                <p><strong><?php esc_html_e( 'Payload warnings:', 'wc-topup-fields' ); ?></strong></p>
                <ul>
                    <?php foreach ( $prepared['warnings'] as $warning ) : ?>
                        <li><?php echo esc_html( $warning ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <pre><code><?php echo esc_html( $json ); ?></code></pre>
    </div>
    <?php
}
