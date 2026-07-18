<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'body_class', 'wctf_add_modern_thankyou_body_class' );
add_action( 'wp_enqueue_scripts', 'wctf_enqueue_modern_thankyou_styles' );
add_action( 'woocommerce_before_thankyou', 'wctf_prepare_modern_thankyou_layout', 1, 1 );
add_action( 'woocommerce_before_thankyou', 'wctf_render_modern_thankyou_hero', 10, 1 );
add_filter( 'woocommerce_thankyou_order_received_text', 'wctf_filter_modern_thankyou_received_text', 10, 2 );
add_filter( 'woocommerce_get_order_item_totals', 'wctf_filter_modern_thankyou_order_totals', 20, 3 );
add_filter( 'woocommerce_order_item_name', 'wctf_filter_modern_thankyou_order_item_name', 20, 3 );
add_filter(
    'woocommerce_order_item_get_formatted_meta_data',
    'wctf_filter_modern_thankyou_order_item_meta',
    20,
    2
);

/**
 * Return the order key supplied by the standard Order Received request.
 *
 * @return string
 */
function wctf_get_modern_thankyou_request_order_key() {
    foreach ( array( 'key', 'order_key' ) as $parameter ) {
        if ( isset( $_GET[ $parameter ] ) && is_scalar( $_GET[ $parameter ] ) ) {
            $order_key = wc_clean( wp_unslash( $_GET[ $parameter ] ) );

            if ( '' !== $order_key ) {
                return $order_key;
            }
        }
    }

    return '';
}

/**
 * Load and authorize the current Order Received order.
 *
 * A supplied order key must always match. Registered customers may otherwise
 * view their own order; guest access always requires the valid order key.
 *
 * @param int $context_order_id Optional order ID supplied by a WooCommerce hook.
 * @return WC_Order|false
 */
function wctf_get_modern_thankyou_order( $context_order_id = 0 ) {
    if (
        is_admin()
        || wp_doing_ajax()
        || ! function_exists( 'is_order_received_page' )
        || ! is_order_received_page()
        || ! function_exists( 'wc_get_order' )
    ) {
        return false;
    }

    $endpoint_order_id = absint( get_query_var( 'order-received' ) );
    $context_order_id  = absint( $context_order_id );

    if ( 0 < $endpoint_order_id && 0 < $context_order_id && $endpoint_order_id !== $context_order_id ) {
        return false;
    }

    $order_id = 0 < $endpoint_order_id ? $endpoint_order_id : $context_order_id;
    $order    = 0 < $order_id ? wc_get_order( $order_id ) : false;

    if ( ! $order instanceof WC_Order ) {
        return false;
    }

    $provided_order_key = wctf_get_modern_thankyou_request_order_key();
    $stored_order_key   = (string) $order->get_order_key();
    $valid_order_key    = '' !== $provided_order_key
        && '' !== $stored_order_key
        && hash_equals( $stored_order_key, $provided_order_key );

    if ( '' !== $provided_order_key && ! $valid_order_key ) {
        return false;
    }

    $customer_id = absint( $order->get_customer_id() );
    $is_owner     = 0 < $customer_id
        && is_user_logged_in()
        && $customer_id === get_current_user_id();

    if ( 0 < $customer_id && ! $is_owner && ! $valid_order_key ) {
        return false;
    }

    if ( 0 === $customer_id && ! $valid_order_key ) {
        return false;
    }

    return $order;
}

/**
 * Determine whether the modern success layout may render for this request.
 *
 * @param int $order_id Optional contextual order ID.
 * @return WC_Order|false
 */
function wctf_get_modern_thankyou_success_order( $order_id = 0 ) {
    $order = wctf_get_modern_thankyou_order( $order_id );

    if ( ! $order instanceof WC_Order || $order->has_status( 'failed' ) ) {
        return false;
    }

    return $order;
}

/**
 * Normalize one WooCommerce variation-attribute metadata key for comparison.
 *
 * @param mixed $key Raw metadata or product-attribute key.
 * @return string
 */
function wctf_normalize_modern_thankyou_variation_attribute_key( $key ) {
    if ( ! is_scalar( $key ) ) {
        return '';
    }

    $key = trim( rawurldecode( wp_strip_all_tags( (string) $key ) ) );

    if ( 0 === stripos( $key, 'attribute_' ) ) {
        $key = substr( $key, strlen( 'attribute_' ) );
    }

    return function_exists( 'mb_strtolower' )
        ? mb_strtolower( $key, 'UTF-8' )
        : strtolower( $key );
}

/**
 * Add one product variation-attribute key to a normalized lookup table.
 *
 * @param array $keys Attribute-key lookup table.
 * @param mixed $key  Raw product attribute key.
 * @return void
 */
function wctf_add_modern_thankyou_variation_attribute_key( &$keys, $key ) {
    $key = wctf_normalize_modern_thankyou_variation_attribute_key( $key );

    if ( '' !== $key ) {
        $keys[ $key ] = true;
    }
}

/**
 * Identify variation attribute keys without using current product values.
 *
 * The order item remains the source of displayed values. Current variation or
 * parent data only helps identify which public metadata records are standard
 * variation attributes; global `pa_` and `attribute_` keys remain identifiable
 * when a variation no longer exists.
 *
 * @param WC_Order_Item_Product $item Exact WooCommerce order line item.
 * @return array
 */
function wctf_get_modern_thankyou_variation_attribute_keys( $item ) {
    $keys = array();

    if ( ! $item instanceof WC_Order_Item_Product || 0 === absint( $item->get_variation_id() ) ) {
        return $keys;
    }

    $product = $item->get_product();

    if ( $product instanceof WC_Product_Variation ) {
        foreach ( array_keys( (array) $product->get_variation_attributes( false ) ) as $attribute_key ) {
            wctf_add_modern_thankyou_variation_attribute_key( $keys, $attribute_key );
        }
    }

    if ( function_exists( 'wc_get_product_variation_attributes' ) ) {
        foreach ( array_keys( (array) wc_get_product_variation_attributes( $item->get_variation_id() ) ) as $attribute_key ) {
            wctf_add_modern_thankyou_variation_attribute_key( $keys, $attribute_key );
        }
    }

    $parent = function_exists( 'wc_get_product' )
        ? wc_get_product( absint( $item->get_product_id() ) )
        : false;

    if ( $parent instanceof WC_Product && method_exists( $parent, 'get_attributes' ) ) {
        foreach ( (array) $parent->get_attributes() as $attribute_key => $attribute ) {
            if (
                ! is_object( $attribute )
                || ! method_exists( $attribute, 'get_variation' )
                || ! $attribute->get_variation()
            ) {
                continue;
            }

            wctf_add_modern_thankyou_variation_attribute_key( $keys, $attribute_key );

            if ( method_exists( $attribute, 'get_name' ) ) {
                wctf_add_modern_thankyou_variation_attribute_key( $keys, $attribute->get_name() );
            }
        }
    }

    return $keys;
}

/**
 * Return the customer-visible name for one saved variation attribute value.
 *
 * @param string $meta_key  Saved order-item metadata key.
 * @param mixed  $raw_value Saved order-item metadata value.
 * @return string
 */
function wctf_get_modern_thankyou_variation_attribute_value( $meta_key, $raw_value ) {
    if ( ! is_scalar( $raw_value ) ) {
        return '';
    }

    $value        = (string) $raw_value;
    $taxonomy_key = wctf_normalize_modern_thankyou_variation_attribute_key( $meta_key );

    if ( '' !== $taxonomy_key && taxonomy_exists( $taxonomy_key ) ) {
        $term = get_term_by( 'slug', $value, $taxonomy_key );

        if ( $term instanceof WP_Term ) {
            $value = $term->name;
        }
    }

    $charset = get_bloginfo( 'charset' );
    $charset = is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';
    $value   = html_entity_decode( $value, ENT_QUOTES, $charset );

    return sanitize_text_field( wp_strip_all_tags( $value, true ) );
}

/**
 * Read standard purchased variation attributes from the exact order item.
 *
 * @param WC_Order_Item_Product $item Exact WooCommerce order line item.
 * @return array<int,array{meta_id:int,value:string}>
 */
function wctf_get_modern_thankyou_order_item_variation_attributes( $item ) {
    static $attributes_by_item = array();

    if ( ! $item instanceof WC_Order_Item_Product || 0 === absint( $item->get_variation_id() ) ) {
        return array();
    }

    $item_id = absint( $item->get_id() );

    if ( 0 < $item_id && isset( $attributes_by_item[ $item_id ] ) ) {
        return $attributes_by_item[ $item_id ];
    }

    $known_keys = wctf_get_modern_thankyou_variation_attribute_keys( $item );
    $attributes = array();

    foreach ( $item->get_meta_data() as $metadata ) {
        $metadata = is_object( $metadata ) && method_exists( $metadata, 'get_data' )
            ? $metadata->get_data()
            : array();
        $meta_id  = isset( $metadata['id'] ) ? absint( $metadata['id'] ) : 0;
        $meta_key = isset( $metadata['key'] ) && is_scalar( $metadata['key'] )
            ? (string) $metadata['key']
            : '';
        $key      = wctf_normalize_modern_thankyou_variation_attribute_key( $meta_key );
        $is_standard_variation_key = '' !== $key
            && (
                isset( $known_keys[ $key ] )
                || 0 === stripos( $meta_key, 'attribute_' )
                || 0 === strpos( $key, 'pa_' )
                || taxonomy_exists( $key )
            );

        if (
            0 === $meta_id
            || '' === $meta_key
            || 0 === strpos( $meta_key, '_' )
            || ! $is_standard_variation_key
        ) {
            continue;
        }

        $value = wctf_get_modern_thankyou_variation_attribute_value(
            $meta_key,
            isset( $metadata['value'] ) ? $metadata['value'] : ''
        );

        if ( '' !== $value ) {
            $attributes[] = array(
                'meta_id' => $meta_id,
                'value'   => $value,
            );
        }
    }

    if ( 0 < $item_id ) {
        $attributes_by_item[ $item_id ] = $attributes;
    }

    return $attributes;
}

/**
 * Determine whether a display title already clearly contains one value.
 *
 * @param string $title Existing plain-text title.
 * @param string $value Variation attribute display value.
 * @return bool
 */
function wctf_modern_thankyou_title_contains_variation_value( $title, $value ) {
    $title = preg_replace( '/\s+/u', ' ', trim( (string) $title ) );
    $value = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );

    if ( ! is_string( $title ) || ! is_string( $value ) || '' === $value ) {
        return false;
    }

    $matched = preg_match(
        '/(?<![\p{L}\p{N}])' . preg_quote( $value, '/' ) . '(?![\p{L}\p{N}])/iu',
        $title
    );

    if ( false !== $matched ) {
        return 1 === $matched;
    }

    return false !== stripos( $title, $value );
}

/**
 * Append purchased variation values to a Gift Card title on Thank You only.
 *
 * @param string                $product_name Existing escaped WooCommerce title markup.
 * @param WC_Order_Item_Product $item         Exact WooCommerce order line item.
 * @param bool                  $is_visible   Whether WooCommerce linked the product.
 * @return string
 */
function wctf_filter_modern_thankyou_order_item_name( $product_name, $item, $is_visible = false ) {
    unset( $is_visible );

    if (
        ! $item instanceof WC_Order_Item_Product
        || ! function_exists( 'wctf_is_fazercards_giftcard_customer_order_item' )
        || ! wctf_is_fazercards_giftcard_customer_order_item( $item )
        || 0 === absint( $item->get_variation_id() )
    ) {
        return $product_name;
    }

    $order = wctf_get_modern_thankyou_success_order( $item->get_order_id() );

    if ( ! $order instanceof WC_Order || absint( $order->get_id() ) !== absint( $item->get_order_id() ) ) {
        return $product_name;
    }

    $display_title = html_entity_decode(
        wp_strip_all_tags( (string) $product_name ),
        ENT_QUOTES,
        get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8'
    );
    $suffix_values = array();

    foreach ( wctf_get_modern_thankyou_order_item_variation_attributes( $item ) as $attribute ) {
        $value = isset( $attribute['value'] ) ? (string) $attribute['value'] : '';

        if ( '' === $value || wctf_modern_thankyou_title_contains_variation_value( $display_title, $value ) ) {
            continue;
        }

        $suffix_values[] = $value;
        $display_title  .= ' ' . $value;
    }

    return empty( $suffix_values )
        ? $product_name
        : $product_name . ' ' . esc_html( implode( ' ', $suffix_values ) );
}

/**
 * Remove only the standard variation metadata moved into the Thank You title.
 *
 * @param array                 $formatted_meta Formatted public order-item metadata.
 * @param WC_Order_Item_Product $item           Exact WooCommerce order line item.
 * @return array
 */
function wctf_filter_modern_thankyou_order_item_meta( $formatted_meta, $item ) {
    if (
        ! is_array( $formatted_meta )
        || ! $item instanceof WC_Order_Item_Product
        || ! function_exists( 'wctf_is_fazercards_giftcard_customer_order_item' )
        || ! wctf_is_fazercards_giftcard_customer_order_item( $item )
        || 0 === absint( $item->get_variation_id() )
        || ! ( wctf_get_modern_thankyou_success_order( $item->get_order_id() ) instanceof WC_Order )
    ) {
        return $formatted_meta;
    }

    $variation_meta_ids = array();

    foreach ( wctf_get_modern_thankyou_order_item_variation_attributes( $item ) as $attribute ) {
        if ( ! empty( $attribute['meta_id'] ) ) {
            $variation_meta_ids[ absint( $attribute['meta_id'] ) ] = true;
        }
    }

    foreach ( array_keys( $formatted_meta ) as $meta_id ) {
        if ( isset( $variation_meta_ids[ absint( $meta_id ) ] ) ) {
            unset( $formatted_meta[ $meta_id ] );
        }
    }

    return $formatted_meta;
}

/**
 * Add the scoped body class only to an authorized, non-failed Order Received page.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function wctf_add_modern_thankyou_body_class( $classes ) {
    if ( wctf_get_modern_thankyou_success_order() instanceof WC_Order ) {
        $classes[] = 'wctf-modern-thankyou-page';
    }

    return array_values( array_unique( $classes ) );
}

/**
 * Enqueue the isolated Thank You page stylesheet.
 *
 * @return void
 */
function wctf_enqueue_modern_thankyou_styles() {
    if ( ! wctf_get_modern_thankyou_success_order() instanceof WC_Order ) {
        return;
    }

    $style_path = WCTF_PATH . 'frontend/css/thankyou-page.css';
    $version    = file_exists( $style_path ) ? (string) filemtime( $style_path ) : wctf_plugin_version();

    wp_enqueue_style(
        'wctf-modern-thankyou',
        plugin_dir_url( WCTF_PATH . 'wc-topup-fields.php' ) . 'frontend/css/thankyou-page.css',
        array(),
        $version
    );
}

/**
 * Remove standalone standard customer/order-again output from this one layout.
 *
 * Payment-gateway callbacks and the order-details template hooks remain intact.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function wctf_prepare_modern_thankyou_layout( $order_id ) {
    if ( ! wctf_get_modern_thankyou_success_order( $order_id ) instanceof WC_Order ) {
        return;
    }

    remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_customer_details', 10 );
    remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button', 10 );
}

/**
 * Determine whether the immutable order snapshot contains a Gift Card item.
 *
 * @param WC_Order $order WooCommerce order.
 * @return bool
 */
function wctf_modern_thankyou_order_has_giftcard( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return false;
    }

    if ( function_exists( 'wctf_fazercards_giftcard_order_has_delivery_items' ) ) {
        return wctf_fazercards_giftcard_order_has_delivery_items( $order );
    }

    if ( ! function_exists( 'wctf_is_fazercards_giftcard_customer_order_item' ) ) {
        return false;
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        if ( wctf_is_fazercards_giftcard_customer_order_item( $item ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Render the status-aware success hero before WooCommerce's Thank You content.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function wctf_render_modern_thankyou_hero( $order_id ) {
    $order = wctf_get_modern_thankyou_success_order( $order_id );

    if ( ! $order instanceof WC_Order ) {
        return;
    }

    if ( $order->has_status( 'completed' ) ) {
        $title    = __( '支付成功，订单已完成', 'wc-topup-fields' );
        $subtitle = wctf_modern_thankyou_order_has_giftcard( $order )
            ? __( '感谢您的购买，订单信息和卡密已发送至您的邮箱。', 'wc-topup-fields' )
            : __( '感谢您的购买，订单信息已发送至您的邮箱。', 'wc-topup-fields' );
    } elseif ( $order->has_status( 'processing' ) ) {
        $title    = __( '支付成功，订单正在处理', 'wc-topup-fields' );
        $subtitle = __( '卡密准备完成后会自动显示在本页面，并发送至您的邮箱。', 'wc-topup-fields' );
    } elseif ( $order->has_status( 'on-hold' ) ) {
        $title    = __( '订单已成功提交', 'wc-topup-fields' );
        $subtitle = __( '订单确认后，我们会尽快处理并发送商品信息。', 'wc-topup-fields' );
    } else {
        $title    = __( '感谢您的购买', 'wc-topup-fields' );
        $subtitle = __( '您的订单已经收到，我们正在为您处理。', 'wc-topup-fields' );
    }
    ?>
    <section class="wctf-thankyou-hero" aria-labelledby="wctf-thankyou-hero-title">
        <span class="wctf-thankyou-hero__decoration wctf-thankyou-hero__decoration--one" aria-hidden="true"></span>
        <span class="wctf-thankyou-hero__decoration wctf-thankyou-hero__decoration--two" aria-hidden="true"></span>
        <span class="wctf-thankyou-hero__decoration wctf-thankyou-hero__decoration--three" aria-hidden="true"></span>
        <span class="wctf-thankyou-hero__decoration wctf-thankyou-hero__decoration--four" aria-hidden="true"></span>
        <span class="wctf-thankyou-hero__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false" role="presentation">
                <path d="M20 6 9 17l-5-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4"></path>
            </svg>
        </span>
        <div class="wctf-thankyou-hero__content">
            <h2 id="wctf-thankyou-hero-title" class="wctf-thankyou-hero__title"><?php echo esc_html( $title ); ?></h2>
            <p class="wctf-thankyou-hero__subtitle"><?php echo esc_html( $subtitle ); ?></p>
        </div>
    </section>
    <?php
}

/**
 * Replace the duplicate default sentence with safe screen-reader context.
 *
 * @param string   $text  Default WooCommerce received text.
 * @param WC_Order $order WooCommerce order.
 * @return string
 */
function wctf_filter_modern_thankyou_received_text( $text, $order ) {
    if ( ! $order instanceof WC_Order ) {
        return $text;
    }

    $authorized_order = wctf_get_modern_thankyou_success_order( $order->get_id() );

    if ( ! $authorized_order instanceof WC_Order ) {
        return $text;
    }

    return '<span class="wctf-thankyou-received-text">' . esc_html__( '订单已收到。', 'wc-topup-fields' ) . '</span>';
}

/**
 * Remove duplicate totals and append the authorized recipient email row.
 *
 * @param array    $total_rows  Displayed order total rows.
 * @param WC_Order $order       WooCommerce order.
 * @param string   $tax_display Tax-display context.
 * @return array
 */
function wctf_filter_modern_thankyou_order_totals( $total_rows, $order, $tax_display = '' ) {
    unset( $tax_display );

    if ( ! is_array( $total_rows ) || ! $order instanceof WC_Order ) {
        return $total_rows;
    }

    $authorized_order = wctf_get_modern_thankyou_success_order( $order->get_id() );

    if ( ! $authorized_order instanceof WC_Order ) {
        return $total_rows;
    }

    $email       = sanitize_email( (string) $authorized_order->get_billing_email() );
    $email_row   = '' !== $email
        ? array(
            'label' => __( '接收邮箱：', 'wc-topup-fields' ),
            'value' => esc_html( $email ),
        )
        : null;
    $filtered    = array();
    $email_added = false;

    foreach ( $total_rows as $key => $row ) {
        if ( in_array( $key, array( 'cart_subtotal', 'order_total' ), true ) ) {
            continue;
        }

        $filtered[ $key ] = $row;

        if ( 'payment_method' === $key && is_array( $email_row ) ) {
            $filtered['wctf_recipient_email'] = $email_row;
            $email_added                      = true;
        }
    }

    if ( is_array( $email_row ) && ! $email_added ) {
        $filtered['wctf_recipient_email'] = $email_row;
    }

    return $filtered;
}
