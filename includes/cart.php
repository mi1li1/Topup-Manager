<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'woocommerce_checkout_process', 'wctf_validate_checkout_customer_fields' );
add_filter( 'woocommerce_get_item_data', 'wctf_display_cart_item_customer_fields', 10, 2 );

/**
 * Get normalized customer field keys configured for a product.
 *
 * @param int $product_id Product ID.
 * @return array
 */
function wctf_get_product_customer_field_keys( $product_id ) {
    $configured_fields = get_post_meta( $product_id, '_topup_fields', true );

    if ( ! is_string( $configured_fields ) || '' === trim( $configured_fields ) ) {
        return array();
    }

    $field_keys = array();

    foreach ( explode( ',', $configured_fields ) as $configured_field ) {
        $field_key = sanitize_key( trim( $configured_field ) );

        if ( '' === $field_key || in_array( $field_key, $field_keys, true ) ) {
            continue;
        }

        $field_keys[] = $field_key;
    }

    return $field_keys;
}

/**
 * Get customer field keys for an eligible simple cart item.
 *
 * @param array $cart_item WooCommerce cart item.
 * @return array
 */
function wctf_get_cart_item_customer_field_keys( $cart_item ) {
    if (
        ! is_array( $cart_item )
        || ! isset( $cart_item['data'] )
        || ! $cart_item['data'] instanceof WC_Product
        || ! $cart_item['data']->is_type( 'simple' )
    ) {
        return array();
    }

    $product_id = $cart_item['data']->get_id();
    $offer_id   = get_post_meta( $product_id, '_fazer_offer_id', true );

    if ( ! is_scalar( $offer_id ) || '' === sanitize_text_field( (string) $offer_id ) ) {
        return array();
    }

    return wctf_get_product_customer_field_keys( $product_id );
}

/**
 * Get a readable label for a normalized customer field key.
 *
 * @param string $field_key Normalized field key.
 * @return string
 */
function wctf_get_customer_field_label( $field_key ) {
    $labels = array(
        'player_id' => __( 'Player ID', 'wc-topup-fields' ),
        'server'    => __( 'Server', 'wc-topup-fields' ),
        'zone_id'   => __( 'Zone ID', 'wc-topup-fields' ),
        'email'     => __( 'Email', 'wc-topup-fields' ),
        'nickname'  => __( 'Nickname', 'wc-topup-fields' ),
    );

    if ( isset( $labels[ $field_key ] ) ) {
        return $labels[ $field_key ];
    }

    return ucwords( str_replace( array( '_', '-' ), ' ', $field_key ) );
}

/**
 * Determine whether a customer field should be treated as an email address.
 *
 * @param string $field_key Normalized field key.
 * @return bool
 */
function wctf_is_email_customer_field( $field_key ) {
    return false !== strpos( $field_key, 'email' );
}

/**
 * Sanitize a submitted customer field value.
 *
 * @param string $field_key Normalized field key.
 * @param mixed  $raw_value Submitted value.
 * @return string
 */
function wctf_sanitize_customer_field_value( $field_key, $raw_value ) {
    if ( ! is_scalar( $raw_value ) ) {
        return '';
    }

    $raw_value = wp_unslash( (string) $raw_value );

    if ( wctf_is_email_customer_field( $field_key ) ) {
        return sanitize_email( $raw_value );
    }

    return sanitize_text_field( $raw_value );
}

/**
 * Validate and store customer fields by cart item key during checkout.
 */
function wctf_validate_checkout_customer_fields() {
    if ( ! WC()->cart ) {
        return;
    }

    $submitted_fields = isset( $_POST['wctf_customer_fields'] ) && is_array( $_POST['wctf_customer_fields'] )
        ? $_POST['wctf_customer_fields']
        : array();

    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $field_keys = wctf_get_cart_item_customer_field_keys( $cart_item );

        if ( empty( $field_keys ) ) {
            unset( WC()->cart->cart_contents[ $cart_item_key ]['wctf_customer_fields'] );
            continue;
        }

        $submitted_item_fields = isset( $submitted_fields[ $cart_item_key ] )
            && is_array( $submitted_fields[ $cart_item_key ] )
            ? $submitted_fields[ $cart_item_key ]
            : array();
        $validated_fields = array();
        $product_name     = wp_strip_all_tags( $cart_item['data']->get_name() );

        foreach ( $field_keys as $field_key ) {
            $raw_value = isset( $submitted_item_fields[ $field_key ] )
                ? $submitted_item_fields[ $field_key ]
                : '';
            $value     = wctf_sanitize_customer_field_value( $field_key, $raw_value );
            $label     = wctf_get_customer_field_label( $field_key );

            if ( '' === $value ) {
                wc_add_notice(
                    sprintf(
                        /* translators: 1: customer field label, 2: product name. */
                        __( 'Please enter %1$s for %2$s.', 'wc-topup-fields' ),
                        $label,
                        $product_name
                    ),
                    'error'
                );
                continue;
            }

            if ( wctf_is_email_customer_field( $field_key ) && ! is_email( $value ) ) {
                wc_add_notice(
                    sprintf(
                        /* translators: 1: customer field label, 2: product name. */
                        __( 'Please enter a valid %1$s for %2$s.', 'wc-topup-fields' ),
                        $label,
                        $product_name
                    ),
                    'error'
                );
                continue;
            }

            $validated_fields[ $field_key ] = $value;
        }

        WC()->cart->cart_contents[ $cart_item_key ]['wctf_customer_fields'] = $validated_fields;
    }

    WC()->cart->set_session();
}

/**
 * Display collected customer fields with their cart line item.
 *
 * @param array $item_data Existing item data.
 * @param array $cart_item WooCommerce cart item.
 * @return array
 */
function wctf_display_cart_item_customer_fields( $item_data, $cart_item ) {
    $field_keys = wctf_get_cart_item_customer_field_keys( $cart_item );
    $values     = isset( $cart_item['wctf_customer_fields'] ) && is_array( $cart_item['wctf_customer_fields'] )
        ? $cart_item['wctf_customer_fields']
        : array();

    foreach ( $field_keys as $field_key ) {
        if ( ! isset( $values[ $field_key ] ) || ! is_scalar( $values[ $field_key ] ) ) {
            continue;
        }

        $value = wctf_sanitize_customer_field_value( $field_key, $values[ $field_key ] );

        if ( '' === $value ) {
            continue;
        }

        $item_data[] = array(
            'key'     => wctf_get_customer_field_label( $field_key ),
            'value'   => $value,
            'display' => esc_html( $value ),
        );
    }

    return $item_data;
}
