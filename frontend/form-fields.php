<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'woocommerce_checkout_billing', 'wctf_render_checkout_customer_fields' );

/**
 * Render required customer fields for eligible simple products at checkout.
 */
function wctf_render_checkout_customer_fields() {
    if ( ! WC()->cart ) {
        return;
    }

    $eligible_items = array();

    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $field_keys = wctf_get_cart_item_customer_field_keys( $cart_item );

        if ( empty( $field_keys ) ) {
            continue;
        }

        $eligible_items[ $cart_item_key ] = array(
            'cart_item'  => $cart_item,
            'field_keys' => $field_keys,
        );
    }

    if ( empty( $eligible_items ) ) {
        return;
    }

    ?>
    <div class="wctf-checkout-fields">
        <h3><?php esc_html_e( 'Product Information', 'wc-topup-fields' ); ?></h3>

        <?php foreach ( $eligible_items as $cart_item_key => $eligible_item ) : ?>
            <?php
            $cart_item  = $eligible_item['cart_item'];
            $product    = $cart_item['data'];
            $saved_data = isset( $cart_item['wctf_customer_fields'] ) && is_array( $cart_item['wctf_customer_fields'] )
                ? $cart_item['wctf_customer_fields']
                : array();
            ?>
            <fieldset class="wctf-checkout-product-fields">
                <legend><?php echo esc_html( $product->get_name() ); ?></legend>

                <?php foreach ( $eligible_item['field_keys'] as $field_key ) : ?>
                    <?php
                    $label       = wctf_get_customer_field_label( $field_key );
                    $input_type  = wctf_is_email_customer_field( $field_key ) ? 'email' : 'text';
                    $input_id    = 'wctf_customer_field_' . sanitize_html_class( $cart_item_key . '_' . $field_key );
                    $field_value = isset( $saved_data[ $field_key ] ) && is_scalar( $saved_data[ $field_key ] )
                        ? (string) $saved_data[ $field_key ]
                        : '';
                    ?>
                    <p class="form-row form-row-wide">
                        <label for="<?php echo esc_attr( $input_id ); ?>">
                            <?php echo esc_html( $label ); ?>
                            <span class="required" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="<?php echo esc_attr( $input_type ); ?>"
                            class="input-text"
                            id="<?php echo esc_attr( $input_id ); ?>"
                            name="wctf_customer_fields[<?php echo esc_attr( $cart_item_key ); ?>][<?php echo esc_attr( $field_key ); ?>]"
                            value="<?php echo esc_attr( $field_value ); ?>"
                            required
                            aria-required="true"
                        >
                    </p>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>
    </div>
    <?php
}
