<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'woocommerce_checkout_create_order_line_item', 'wctf_save_order_item_customer_fields', 10, 4 );

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
