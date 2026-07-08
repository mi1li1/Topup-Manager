<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'woocommerce_checkout_create_order_line_item', 'wctf_save_order_item_customer_fields', 10, 4 );
add_action( 'woocommerce_checkout_create_order_line_item', 'wctf_snapshot_fazercards_order_item_binding', 20, 4 );
add_action( 'woocommerce_after_order_itemmeta', 'wctf_display_fazercards_order_item_payload', 10, 3 );
add_action( 'add_meta_boxes', 'wctf_register_fazercards_dry_run_meta_box' );
add_action( 'admin_post_wctf_fazercards_dry_run', 'wctf_handle_fazercards_dry_run' );
add_action( 'admin_post_wctf_fazercards_submit_order_item', 'wctf_handle_fazercards_order_item_submission' );
add_action( 'woocommerce_order_status_processing', 'wctf_handle_fazercards_auto_submission', 20, 3 );
add_action( 'woocommerce_order_status_completed', 'wctf_handle_fazercards_auto_submission', 20, 3 );
add_filter( 'manage_edit-shop_order_columns', 'wctf_add_fazercards_order_list_column', 20 );
add_action( 'manage_shop_order_posts_custom_column', 'wctf_render_fazercards_order_list_column_classic', 20, 2 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'wctf_add_fazercards_order_list_column', 20 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'wctf_render_fazercards_order_list_column_hpos', 20, 2 );

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
    $auto_submit = 'yes' === sanitize_key( (string) get_post_meta( $product_id, '_wctf_fazer_auto_submit_enabled', true ) )
        ? 'yes'
        : 'no';

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
        '_wctf_fazer_auto_submit_enabled_snapshot' => $auto_submit,
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

/**
 * Register the FazerCards Dry Run meta box on classic and HPOS order screens.
 */
function wctf_register_fazercards_dry_run_meta_box() {
    $screens = array( 'shop_order' );

    if ( function_exists( 'wc_get_page_screen_id' ) ) {
        $screens[] = wc_get_page_screen_id( 'shop-order' );
    }

    foreach ( array_unique( $screens ) as $screen ) {
        add_meta_box(
            'wctf-fazercards-dry-run',
            __( 'FazerCards Dry Run', 'wc-topup-fields' ),
            'wctf_render_fazercards_dry_run_meta_box',
            $screen,
            'normal',
            'default'
        );
    }
}

/**
 * Render the local FazerCards Dry Run controls and one-time result.
 *
 * @param WP_Post|WC_Order $post_or_order_object Order screen object.
 */
function wctf_render_fazercards_dry_run_meta_box( $post_or_order_object ) {
    if ( $post_or_order_object instanceof WC_Order ) {
        $order = $post_or_order_object;
    } elseif ( $post_or_order_object instanceof WP_Post ) {
        $order = wc_get_order( $post_or_order_object->ID );
    } else {
        $order = false;
    }

    if ( ! $order ) {
        echo '<p>' . esc_html__( 'The WooCommerce order could not be loaded.', 'wc-topup-fields' ) . '</p>';
        return;
    }

    $order_id = absint( $order->get_id() );
    $action_url = add_query_arg(
        array(
            'action'   => 'wctf_fazercards_dry_run',
            'order_id' => $order_id,
        ),
        admin_url( 'admin-post.php' )
    );
    $action_url = wp_nonce_url(
        $action_url,
        'wctf_fazercards_dry_run_' . $order_id,
        'wctf_fazercards_dry_run_nonce'
    );

    ?>
    <div class="notice notice-error inline" role="alert">
        <p><strong><?php esc_html_e( 'This will submit a real order to FazerCards.', 'wc-topup-fields' ); ?></strong></p>
    </div>
    <p>
        <?php esc_html_e( 'Prepare and validate local FazerCards payloads without submitting them.', 'wc-topup-fields' ); ?>
    </p>
    <p>
        <a class="button button-primary" href="<?php echo esc_url( $action_url ); ?>">
            <?php esc_html_e( 'FazerCards Dry Run', 'wc-topup-fields' ); ?>
        </a>
    </p>
    <?php

    $submission_result_key = wctf_get_fazercards_submission_result_transient_key(
        $order_id,
        get_current_user_id()
    );
    $submission_result = get_transient( $submission_result_key );

    if ( is_array( $submission_result ) ) {
        delete_transient( $submission_result_key );

        $notice_class = ! empty( $submission_result['success'] ) ? 'notice-success' : 'notice-error';
        $message      = isset( $submission_result['message'] ) && is_scalar( $submission_result['message'] )
            ? sanitize_text_field( (string) $submission_result['message'] )
            : __( 'The submission action finished without a result message.', 'wc-topup-fields' );

        echo '<div class="notice ' . esc_attr( $notice_class ) . ' inline"><p>';
        echo esc_html( $message );
        echo '</p></div>';
    }

    wctf_render_fazercards_submission_status_summary( $order );

    $transient_key = wctf_get_fazercards_dry_run_transient_key( $order_id, get_current_user_id() );
    $result        = get_transient( $transient_key );

    if ( false === $result || ! is_array( $result ) ) {
        echo '<p>' . esc_html__( 'No dry run result is currently available.', 'wc-topup-fields' ) . '</p>';
        return;
    }

    delete_transient( $transient_key );

    echo '<hr>';
    echo '<h4>' . esc_html__( 'Last Dry Run Result', 'wc-topup-fields' ) . '</h4>';

    if ( isset( $result['ran_at'] ) && is_scalar( $result['ran_at'] ) ) {
        echo '<p>';
        echo '<strong>' . esc_html__( 'Run at:', 'wc-topup-fields' ) . '</strong> ';
        echo esc_html( (string) $result['ran_at'] );
        echo '</p>';
    }

    if ( empty( $result['items'] ) ) {
        echo '<div class="notice notice-info inline"><p>';
        echo esc_html__( 'No FazerCards-bound order items were found.', 'wc-topup-fields' );
        echo '</p></div>';
        return;
    }

    $has_submit_controls = false;

    foreach ( $result['items'] as $item_result ) {
        if ( ! is_array( $item_result ) ) {
            continue;
        }

        $item_id   = isset( $item_result['order_item_id'] ) ? absint( $item_result['order_item_id'] ) : 0;
        $item_name = isset( $item_result['item_name'] ) && is_scalar( $item_result['item_name'] )
            ? sanitize_text_field( (string) $item_result['item_name'] )
            : '';
        $is_ready  = ! empty( $item_result['ready'] );
        $warnings  = isset( $item_result['warnings'] ) && is_array( $item_result['warnings'] )
            ? $item_result['warnings']
            : array();
        $payload   = isset( $item_result['payload'] ) && is_array( $item_result['payload'] )
            ? $item_result['payload']
            : array();
        $order_item = $order->get_item( $item_id );
        $submission_status = $order_item instanceof WC_Order_Item_Product
            ? sanitize_key( (string) $order_item->get_meta( '_wctf_fazer_submission_status', true ) )
            : '';

        if ( ! in_array( $submission_status, array( 'submitting', 'submitted', 'failed' ), true ) ) {
            $submission_status = 'not_submitted';
        }

        $quantity = isset( $payload['quantity'] ) ? (int) $payload['quantity'] : 0;

        if ( 1 !== $quantity ) {
            $warnings[] = __( 'Quantity must be exactly 1 for real submission.', 'wc-topup-fields' );
            $is_ready   = false;
        }

        if ( empty( $payload['category_id'] ) ) {
            $warnings[] = __( 'Category ID is required for real submission.', 'wc-topup-fields' );
            $is_ready   = false;
        }

        if ( empty( $payload['offer_id'] ) ) {
            $warnings[] = __( 'Offer ID is required for real submission.', 'wc-topup-fields' );
            $is_ready   = false;
        }

        if ( empty( $payload['customer_fields'] ) || ! is_array( $payload['customer_fields'] ) ) {
            $warnings[] = __( 'Customer fields are required for real submission.', 'wc-topup-fields' );
            $is_ready   = false;
        }

        $json      = wp_json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ( false === $json ) {
            $json       = '{}';
            $warnings[] = __( 'The payload result could not be encoded.', 'wc-topup-fields' );
            $is_ready   = false;
        }

        $can_submit = $is_ready
            && $order_item instanceof WC_Order_Item_Product
            && 'submitting' !== $submission_status
            && 'submitted' !== $submission_status;

        ?>
        <section class="wctf-fazercards-dry-run-item">
            <h4>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: order item ID, 2: product name. */
                        __( 'Order item #%1$d — %2$s', 'wc-topup-fields' ),
                        $item_id,
                        $item_name
                    )
                );
                ?>
            </h4>
            <p>
                <strong><?php esc_html_e( 'Status:', 'wc-topup-fields' ); ?></strong>
                <?php
                echo esc_html(
                    $is_ready
                        ? __( 'Ready', 'wc-topup-fields' )
                        : __( 'Not Ready', 'wc-topup-fields' )
                );
                ?>
            </p>
            <p>
                <strong><?php esc_html_e( 'Submission Status:', 'wc-topup-fields' ); ?></strong>
                <?php echo esc_html( $submission_status ); ?>
            </p>

            <?php if ( ! empty( $warnings ) ) : ?>
                <div class="notice notice-warning inline" role="alert">
                    <p><strong><?php esc_html_e( 'Warnings:', 'wc-topup-fields' ); ?></strong></p>
                    <ul>
                        <?php foreach ( $warnings as $warning ) : ?>
                            <?php if ( is_scalar( $warning ) ) : ?>
                                <li><?php echo esc_html( (string) $warning ); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <pre><code><?php echo esc_html( $json ); ?></code></pre>

            <?php if ( $can_submit ) : ?>
                <?php
                $has_submit_controls = true;
                $confirmation_id     = 'wctf-fazer-confirm-' . $item_id;
                $error_id            = 'wctf-fazer-confirm-error-' . $item_id;
                $submission_nonce    = wp_create_nonce(
                    'wctf_submit_fazercards_item_' . $order_id . '_' . $item_id
                );
                ?>
                <div class="notice notice-error inline">
                    <p><strong><?php esc_html_e( 'This will submit a real order to FazerCards.', 'wc-topup-fields' ); ?></strong></p>
                </div>
                <p>
                    <label for="<?php echo esc_attr( $confirmation_id ); ?>">
                        <input
                            type="checkbox"
                            id="<?php echo esc_attr( $confirmation_id ); ?>"
                            class="wctf-fazer-real-confirmation"
                            value="1"
                        >
                        <?php esc_html_e( 'I understand this will create a real FazerCards order.', 'wc-topup-fields' ); ?>
                    </label>
                </p>
                <p
                    id="<?php echo esc_attr( $error_id ); ?>"
                    class="notice notice-error inline"
                    role="alert"
                    hidden
                >
                    <?php esc_html_e( 'You must confirm the real order submission.', 'wc-topup-fields' ); ?>
                </p>
                <p>
                    <button
                        type="button"
                        class="button button-primary wctf-fazer-real-submit"
                        data-action-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                        data-order-id="<?php echo esc_attr( $order_id ); ?>"
                        data-item-id="<?php echo esc_attr( $item_id ); ?>"
                        data-nonce="<?php echo esc_attr( $submission_nonce ); ?>"
                        data-confirmation-id="<?php echo esc_attr( $confirmation_id ); ?>"
                        data-error-id="<?php echo esc_attr( $error_id ); ?>"
                    >
                        <?php esc_html_e( 'Submit to FazerCards (REAL)', 'wc-topup-fields' ); ?>
                    </button>
                </p>
            <?php endif; ?>
        </section>
        <?php
    }

    if ( $has_submit_controls ) {
        ?>
        <script>
        ( function () {
            'use strict';

            document.querySelectorAll( '.wctf-fazer-real-submit' ).forEach( function ( button ) {
                button.addEventListener( 'click', function () {
                    var confirmation = document.getElementById( button.dataset.confirmationId );
                    var error = document.getElementById( button.dataset.errorId );
                    var fields;
                    var form;

                    if ( ! confirmation || ! confirmation.checked ) {
                        if ( error ) {
                            error.hidden = false;
                        }

                        return;
                    }

                    if ( error ) {
                        error.hidden = true;
                    }

                    fields = {
                        action: 'wctf_fazercards_submit_order_item',
                        order_id: button.dataset.orderId,
                        item_id: button.dataset.itemId,
                        nonce: button.dataset.nonce,
                        confirmed: '1'
                    };
                    form = document.createElement( 'form' );
                    form.method = 'post';
                    form.action = button.dataset.actionUrl;

                    Object.keys( fields ).forEach( function ( fieldName ) {
                        var input = document.createElement( 'input' );

                        input.type = 'hidden';
                        input.name = fieldName;
                        input.value = fields[ fieldName ];
                        form.appendChild( input );
                    } );

                    button.disabled = true;
                    document.body.appendChild( form );
                    form.submit();
                } );
            } );
        }() );
        </script>
        <?php
    }
}

/**
 * Handle a nonce-protected local FazerCards Dry Run request.
 */
function wctf_handle_fazercards_dry_run() {
    $order_id = isset( $_GET['order_id'] )
        ? absint( wp_unslash( $_GET['order_id'] ) )
        : 0;

    check_admin_referer(
        'wctf_fazercards_dry_run_' . $order_id,
        'wctf_fazercards_dry_run_nonce'
    );

    if ( 0 === $order_id ) {
        wp_die(
            esc_html__( 'A valid WooCommerce order ID is required.', 'wc-topup-fields' ),
            esc_html__( 'Invalid order', 'wc-topup-fields' ),
            array( 'response' => 400 )
        );
    }

    if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
        wp_die(
            esc_html__( 'You are not allowed to edit this WooCommerce order.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        wp_die(
            esc_html__( 'The WooCommerce order could not be found.', 'wc-topup-fields' ),
            esc_html__( 'Order not found', 'wc-topup-fields' ),
            array( 'response' => 404 )
        );
    }

    $result = array(
        'ran_at'  => wctf_normalize_fazercards_payload_value( current_time( 'mysql' ) ),
        'order_id' => $order_id,
        'items'    => array(),
    );

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            continue;
        }

        $prepared = wctf_prepare_fazercards_order_item_payload( $order, $item, $item_id );

        if ( empty( $prepared['success'] ) ) {
            continue;
        }

        $warnings = isset( $prepared['warnings'] ) && is_array( $prepared['warnings'] )
            ? array_values(
                array_filter(
                    array_map(
                        'sanitize_text_field',
                        $prepared['warnings']
                    ),
                    'strlen'
                )
            )
            : array();

        $result['items'][] = array(
            'order_item_id' => absint( $item_id ),
            'item_name'     => sanitize_text_field( $item->get_name() ),
            'ready'         => empty( $warnings ),
            'warnings'      => $warnings,
            'payload'       => isset( $prepared['payload'] ) && is_array( $prepared['payload'] )
                ? $prepared['payload']
                : array(),
        );
    }

    set_transient(
        wctf_get_fazercards_dry_run_transient_key( $order_id, get_current_user_id() ),
        $result,
        5 * MINUTE_IN_SECONDS
    );

    wp_safe_redirect( $order->get_edit_order_url() );
    exit;
}

/**
 * Build a user- and order-scoped transient key for a Dry Run result.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $user_id  WordPress user ID.
 * @return string
 */
function wctf_get_fazercards_dry_run_transient_key( $order_id, $user_id ) {
    return 'wctf_fazer_dry_run_' . absint( $user_id ) . '_' . absint( $order_id );
}

/**
 * Render persistent submission statuses stored on FazerCards order items.
 *
 * @param WC_Order $order WooCommerce order.
 */
function wctf_render_fazercards_submission_status_summary( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $items = array();

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            continue;
        }

        $prepared = wctf_prepare_fazercards_order_item_payload( $order, $item, $item_id );

        if ( empty( $prepared['success'] ) ) {
            continue;
        }

        $status = sanitize_key( (string) $item->get_meta( '_wctf_fazer_submission_status', true ) );

        if ( ! in_array( $status, array( 'submitting', 'submitted', 'failed' ), true ) ) {
            $status = 'not_submitted';
        }

        $readiness = wctf_get_fazercards_submission_readiness( $prepared );

        $items[] = array(
            'item_id'       => absint( $item_id ),
            'item_name'     => sanitize_text_field( $item->get_name() ),
            'status'        => $status,
            'ready'         => $readiness['ready'],
            'warnings'      => $readiness['warnings'],
            'remote_id'     => wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_remote_order_id', true ) ),
            'remote_status' => wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_remote_status', true ) ),
            'submitted_at'  => wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_submitted_at', true ) ),
            'last_error'    => wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_last_error', true ) ),
            'idempotency'   => wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_idempotency_key', true ) ),
            'last_response' => wctf_format_fazercards_last_response(
                $item->get_meta( '_wctf_fazer_last_response', true )
            ),
            'auto_submit'   => wctf_is_fazercards_order_item_auto_submit_enabled( $item ),
            'auto_failure_trigger' => sanitize_key( (string) $item->get_meta( '_wctf_fazer_auto_failure_trigger', true ) ),
            'auto_failure_alert_sent' => 'yes' === sanitize_key( (string) $item->get_meta( '_wctf_fazer_auto_failure_alert_sent', true ) ),
        );
    }

    if ( empty( $items ) ) {
        return;
    }

    echo '<hr>';
    echo '<h4>' . esc_html__( 'FazerCards Submission Status', 'wc-topup-fields' ) . '</h4>';

    foreach ( $items as $item_data ) {
        ?>
        <section class="wctf-fazercards-submission-item">
            <h4>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: order item ID, 2: product name. */
                        __( 'Order item #%1$d — %2$s', 'wc-topup-fields' ),
                        $item_data['item_id'],
                        $item_data['item_name']
                    )
                );
                ?>
            </h4>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Submission Status', 'wc-topup-fields' ); ?></th>
                        <td><strong><?php echo esc_html( wctf_get_fazercards_submission_status_label( $item_data['status'] ) ); ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Payload Readiness', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $item_data['ready'] ? __( 'Ready', 'wc-topup-fields' ) : __( 'Not Ready', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-submit enabled for this item', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $item_data['auto_submit'] ? __( 'Yes', 'wc-topup-fields' ) : __( 'No', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Remote Order ID', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $item_data['remote_id'] ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Remote Status', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $item_data['remote_status'] ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Submitted At', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $item_data['submitted_at'] ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Last Error', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $item_data['last_error'] ); ?></td>
                    </tr>
                    <?php if ( 'failed' === $item_data['status'] && '' !== $item_data['auto_failure_trigger'] ) : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Auto failure alert sent', 'wc-topup-fields' ); ?></th>
                            <td><?php echo esc_html( $item_data['auto_failure_alert_sent'] ? __( 'Yes', 'wc-topup-fields' ) : __( 'No', 'wc-topup-fields' ) ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( 'submitted' === $item_data['status'] ) : ?>
                <div class="notice notice-success inline">
                    <p><strong><?php esc_html_e( 'Submitted', 'wc-topup-fields' ); ?></strong></p>
                    <p><?php esc_html_e( 'Already submitted — duplicate submission blocked.', 'wc-topup-fields' ); ?></p>
                </div>
            <?php elseif ( 'submitting' === $item_data['status'] ) : ?>
                <div class="notice notice-info inline">
                    <p><strong><?php esc_html_e( 'Submitting', 'wc-topup-fields' ); ?></strong></p>
                    <p><?php esc_html_e( 'A FazerCards submission is currently in progress for this item.', 'wc-topup-fields' ); ?></p>
                </div>
            <?php elseif ( 'failed' === $item_data['status'] ) : ?>
                <div class="notice notice-error inline">
                    <p><strong><?php esc_html_e( 'Failed', 'wc-topup-fields' ); ?></strong></p>
                    <?php if ( '' !== $item_data['last_error'] ) : ?>
                        <p><?php echo esc_html( $item_data['last_error'] ); ?></p>
                    <?php endif; ?>
                    <p><?php esc_html_e( 'A ready retry will reuse the existing idempotency key.', 'wc-topup-fields' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $item_data['warnings'] ) ) : ?>
                <div class="notice notice-warning inline">
                    <p><strong><?php esc_html_e( 'Payload warnings:', 'wc-topup-fields' ); ?></strong></p>
                    <ul>
                        <?php foreach ( $item_data['warnings'] as $warning ) : ?>
                            <li><?php echo esc_html( $warning ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <details>
                <summary><?php esc_html_e( 'Technical submission details', 'wc-topup-fields' ); ?></summary>
                <p>
                    <strong><?php esc_html_e( 'Idempotency Key:', 'wc-topup-fields' ); ?></strong>
                    <code><?php echo esc_html( $item_data['idempotency'] ); ?></code>
                </p>
                <p><strong><?php esc_html_e( 'Last Response Summary:', 'wc-topup-fields' ); ?></strong></p>
                <pre><code><?php echo esc_html( $item_data['last_response'] ); ?></code></pre>
            </details>
        </section>
        <?php
    }
}

/**
 * Evaluate whether a prepared item payload is ready for manual submission.
 *
 * @param array $prepared Prepared payload result.
 * @return array
 */
function wctf_get_fazercards_submission_readiness( $prepared ) {
    $warnings = array();

    if ( isset( $prepared['warnings'] ) && is_array( $prepared['warnings'] ) ) {
        foreach ( $prepared['warnings'] as $warning ) {
            if ( is_scalar( $warning ) && '' !== sanitize_text_field( (string) $warning ) ) {
                $warnings[] = sanitize_text_field( (string) $warning );
            }
        }
    }

    $payload = isset( $prepared['payload'] ) && is_array( $prepared['payload'] )
        ? $prepared['payload']
        : array();

    if ( empty( $prepared['success'] ) ) {
        $warnings[] = __( 'The payload helper did not prepare this item successfully.', 'wc-topup-fields' );
    }

    if ( ! isset( $payload['quantity'] ) || 1 !== (int) $payload['quantity'] ) {
        $warnings[] = __( 'Quantity must be exactly 1 for real submission.', 'wc-topup-fields' );
    }

    if ( empty( $payload['category_id'] ) ) {
        $warnings[] = __( 'Category ID is required for real submission.', 'wc-topup-fields' );
    }

    if ( empty( $payload['offer_id'] ) ) {
        $warnings[] = __( 'Offer ID is required for real submission.', 'wc-topup-fields' );
    }

    if ( empty( $payload['customer_fields'] ) || ! is_array( $payload['customer_fields'] ) ) {
        $warnings[] = __( 'Customer fields are required for real submission.', 'wc-topup-fields' );
    }

    return array(
        'ready'    => empty( $warnings ),
        'warnings' => array_values( array_unique( $warnings ) ),
    );
}

/**
 * Format a stored response summary for safe admin display.
 *
 * @param mixed $response Stored response summary.
 * @return string
 */
function wctf_format_fazercards_last_response( $response ) {
    if ( ! is_scalar( $response ) ) {
        return '';
    }

    $response = sanitize_textarea_field( (string) $response );
    $decoded  = json_decode( $response, true );

    if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
        return $response;
    }

    $json = wp_json_encode(
        wctf_sanitize_fazercards_response_value( $decoded ),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    return false === $json ? '' : $json;
}

/**
 * Get a readable FazerCards submission status label.
 *
 * @param string $status Internal status.
 * @return string
 */
function wctf_get_fazercards_submission_status_label( $status ) {
    $labels = array(
        'not_applicable' => __( 'Not applicable', 'wc-topup-fields' ),
        'not_submitted'  => __( 'Not submitted', 'wc-topup-fields' ),
        'submitting'     => __( 'Submitting', 'wc-topup-fields' ),
        'submitted'      => __( 'Submitted', 'wc-topup-fields' ),
        'failed'         => __( 'Failed', 'wc-topup-fields' ),
        'mixed'          => __( 'Mixed', 'wc-topup-fields' ),
    );

    return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['not_submitted'];
}

/**
 * Handle a manually confirmed real FazerCards order item submission.
 */
function wctf_handle_fazercards_order_item_submission() {
    $order_id = isset( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
        : 0;
    $item_id = isset( $_POST['item_id'] )
        ? absint( wp_unslash( $_POST['item_id'] ) )
        : 0;

    check_admin_referer(
        'wctf_submit_fazercards_item_' . $order_id . '_' . $item_id,
        'nonce'
    );

    if ( 0 === $order_id || 0 === $item_id ) {
        wp_die(
            esc_html__( 'A valid order and order item are required.', 'wc-topup-fields' ),
            esc_html__( 'Invalid submission', 'wc-topup-fields' ),
            array( 'response' => 400 )
        );
    }

    if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
        wp_die(
            esc_html__( 'You are not allowed to edit this WooCommerce order.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        wp_die(
            esc_html__( 'The WooCommerce order could not be found.', 'wc-topup-fields' ),
            esc_html__( 'Order not found', 'wc-topup-fields' ),
            array( 'response' => 404 )
        );
    }

    $item = $order->get_item( $item_id );

    if ( ! $item instanceof WC_Order_Item_Product ) {
        wctf_finish_fazercards_submission_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'The selected order item does not belong to this order.', 'wc-topup-fields' ),
            )
        );
    }

    $confirmed = isset( $_POST['confirmed'] )
        ? sanitize_text_field( wp_unslash( $_POST['confirmed'] ) )
        : '';

    if ( '1' !== $confirmed ) {
        wctf_finish_fazercards_submission_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'Confirmation is required. No FazerCards order was submitted.', 'wc-topup-fields' ),
            )
        );
    }

    $result = wctf_submit_fazercards_order_item(
        $order,
        $item,
        $item_id,
        'manual',
        true
    );

    wctf_finish_fazercards_submission_action( $order, $result );
}

/**
 * Submit one validated FazerCards order item through the shared real-submit path.
 *
 * @param WC_Order              $order              WooCommerce order.
 * @param WC_Order_Item_Product $item               Order line item.
 * @param int                   $item_id            Order item ID.
 * @param string                $trigger            Submission trigger identifier.
 * @param bool                  $allow_failed_retry Whether a failed item may be retried.
 * @return array
 */
function wctf_submit_fazercards_order_item( $order, $item, $item_id, $trigger, $allow_failed_retry = false ) {
    $result = array(
        'success'   => false,
        'attempted' => false,
        'message'   => __( 'The FazerCards order was not submitted.', 'wc-topup-fields' ),
    );

    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        $result['message'] = __( 'A valid WooCommerce order item is required.', 'wc-topup-fields' );
        return $result;
    }

    $order_id          = absint( $order->get_id() );
    $item_id           = absint( $item_id );
    $trigger           = sanitize_key( $trigger );
    $submission_status = sanitize_key( (string) $item->get_meta( '_wctf_fazer_submission_status', true ) );

    if ( 'submitted' === $submission_status ) {
        $result['message'] = __( 'This order item was already submitted to FazerCards.', 'wc-topup-fields' );
        return $result;
    }

    if ( 'failed' === $submission_status && ! $allow_failed_retry ) {
        $result['message'] = __( 'Automatic retry is disabled for failed FazerCards submissions.', 'wc-topup-fields' );
        return $result;
    }

    $prepared  = wctf_prepare_fazercards_order_item_payload( $order, $item, $item_id );
    $readiness = wctf_get_fazercards_submission_readiness( $prepared );
    $payload   = isset( $prepared['payload'] ) && is_array( $prepared['payload'] )
        ? $prepared['payload']
        : array();

    if ( empty( $readiness['ready'] ) ) {
        $result['message'] = __( 'The payload is not ready. Run the Dry Run and resolve all warnings.', 'wc-topup-fields' );
        return $result;
    }

    $category_id     = isset( $payload['category_id'] ) && is_scalar( $payload['category_id'] )
        ? sanitize_text_field( (string) $payload['category_id'] )
        : '';
    $offer_id        = isset( $payload['offer_id'] ) && is_scalar( $payload['offer_id'] )
        ? sanitize_text_field( (string) $payload['offer_id'] )
        : '';
    $customer_fields = isset( $payload['customer_fields'] ) && is_array( $payload['customer_fields'] )
        ? $payload['customer_fields']
        : array();

    if ( '' === $category_id || '' === $offer_id || empty( $customer_fields ) ) {
        $result['message'] = __( 'Category ID, offer ID, and customer fields are required.', 'wc-topup-fields' );
        return $result;
    }

    $config = function_exists( 'wctf_config' ) ? wctf_config() : array();

    if ( empty( $config['api_url'] ) || empty( $config['api_key'] ) ) {
        $result['message'] = __( 'Save the FazerCards API URL and API key before submitting.', 'wc-topup-fields' );
        return $result;
    }

    $idempotency_key = wctf_get_or_create_fazercards_idempotency_key( $order, $item, $item_id );

    if ( '' === $idempotency_key ) {
        $result['message'] = __( 'A stable FazerCards idempotency key could not be created.', 'wc-topup-fields' );
        return $result;
    }

    $lock_token = wctf_acquire_fazercards_submission_lock( $order_id, $item_id );

    if ( '' === $lock_token ) {
        $result['message'] = __( 'A FazerCards submission is already in progress for this item.', 'wc-topup-fields' );
        return $result;
    }

    $item->update_meta_data( '_wctf_fazer_submission_status', 'submitting' );
    $item->save_meta_data();

    try {
        $provider            = new WCTF_FazerCards_Provider();
        $result['attempted'] = true;
        $response            = $provider->create_order(
            $category_id,
            $offer_id,
            $customer_fields,
            $idempotency_key
        );
        $response_summary    = wctf_build_fazercards_response_summary( $response );
        $response_body       = isset( $response['body'] ) && is_array( $response['body'] )
            ? $response['body']
            : array();
        $remote_order        = isset( $response_body['order'] ) && is_array( $response_body['order'] )
            ? $response_body['order']
            : array();
        $remote_order_id     = isset( $remote_order['id'] ) && is_scalar( $remote_order['id'] )
            ? sanitize_text_field( (string) $remote_order['id'] )
            : '';
        $remote_status       = isset( $remote_order['status'] ) && is_scalar( $remote_order['status'] )
            ? sanitize_text_field( (string) $remote_order['status'] )
            : '';

        $item->update_meta_data( '_wctf_fazer_last_response', $response_summary );

        if ( $provider->isSuccess( $response ) && '' !== $remote_order_id ) {
            $item->update_meta_data( '_wctf_fazer_submission_status', 'submitted' );
            $item->update_meta_data( '_wctf_fazer_remote_order_id', $remote_order_id );
            $item->update_meta_data( '_wctf_fazer_remote_status', $remote_status );
            $item->update_meta_data(
                '_wctf_fazer_submitted_at',
                wctf_normalize_fazercards_payload_value( current_time( 'mysql', true ) )
            );
            $item->delete_meta_data( '_wctf_fazer_last_error' );
            wctf_clear_fazercards_auto_failure_alert( $item );
            $item->save_meta_data();

            wctf_add_fazercards_submission_order_note(
                $order,
                $item_id,
                $trigger,
                true,
                $remote_order_id,
                $remote_status,
                ''
            );

            $result['success'] = true;
            $result['message'] = sprintf(
                /* translators: %s: FazerCards remote order ID. */
                __( 'FazerCards order submitted successfully: %s.', 'wc-topup-fields' ),
                $remote_order_id
            );

            return $result;
        }

        $error = sanitize_text_field( $provider->getError( $response ) );

        if ( $provider->isSuccess( $response ) && '' === $remote_order_id ) {
            $error = __( 'FazerCards returned success without a remote order ID.', 'wc-topup-fields' );
        } elseif ( '' === $error ) {
            $error = __( 'The FazerCards order submission failed.', 'wc-topup-fields' );
        }

        $item->update_meta_data( '_wctf_fazer_submission_status', 'failed' );
        $item->update_meta_data( '_wctf_fazer_last_error', sanitize_text_field( $error ) );
        $item->save_meta_data();

        wctf_add_fazercards_submission_order_note(
            $order,
            $item_id,
            $trigger,
            false,
            '',
            '',
            $error
        );

        wctf_maybe_send_fazercards_auto_failure_alert(
            $order,
            $item,
            $item_id,
            $category_id,
            $offer_id,
            $error,
            $trigger,
            $customer_fields
        );

        $result['message'] = sanitize_text_field( $error );
        return $result;
    } catch ( Throwable $throwable ) {
        $error = sanitize_text_field( $throwable->getMessage() );

        if ( '' === $error ) {
            $error = __( 'The FazerCards order submission failed unexpectedly.', 'wc-topup-fields' );
        }

        $item->update_meta_data( '_wctf_fazer_submission_status', 'failed' );
        $item->update_meta_data( '_wctf_fazer_last_error', $error );
        $item->update_meta_data(
            '_wctf_fazer_last_response',
            wctf_build_fazercards_response_summary( array() )
        );
        $item->save_meta_data();

        if ( $result['attempted'] ) {
            wctf_add_fazercards_submission_order_note(
                $order,
                $item_id,
                $trigger,
                false,
                '',
                '',
                $error
            );

            wctf_maybe_send_fazercards_auto_failure_alert(
                $order,
                $item,
                $item_id,
                $category_id,
                $offer_id,
                $error,
                $trigger,
                $customer_fields
            );
        }

        $result['message'] = $error;
        return $result;
    } finally {
        wctf_release_fazercards_submission_lock( $order_id, $item_id, $lock_token );
    }
}

/**
 * Automatically submit eligible FazerCards items when an order enters a paid status.
 *
 * @param int      $order_id         WooCommerce order ID.
 * @param WC_Order $order            WooCommerce order.
 * @param array    $status_transition Status transition data.
 */
function wctf_handle_fazercards_auto_submission( $order_id, $order = null, $status_transition = array() ) {
    unset( $status_transition );

    if ( 'yes' !== get_option( 'wctf_fazercards_auto_submit_enabled', 'no' ) ) {
        return;
    }

    if ( ! $order instanceof WC_Order ) {
        $order = wc_get_order( absint( $order_id ) );
    }

    if (
        ! $order instanceof WC_Order
        || ! $order->has_status( array( 'processing', 'completed' ) )
        || ! $order->get_date_paid()
    ) {
        return;
    }

    $config = function_exists( 'wctf_config' ) ? wctf_config() : array();

    if ( empty( $config['api_url'] ) || empty( $config['api_key'] ) ) {
        return;
    }

    $trigger = sanitize_key( current_filter() );

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if (
            ! $item instanceof WC_Order_Item_Product
            || ! wctf_has_fazercards_order_item_snapshot( $item )
            || ! wctf_is_fazercards_order_item_auto_submit_enabled( $item )
        ) {
            continue;
        }

        $status = sanitize_key( (string) $item->get_meta( '_wctf_fazer_submission_status', true ) );

        if ( in_array( $status, array( 'submitting', 'submitted', 'failed' ), true ) ) {
            continue;
        }

        wctf_submit_fazercards_order_item(
            $order,
            $item,
            $item_id,
            $trigger,
            false
        );
    }
}

/**
 * Check whether an order item contains the binding snapshot required for auto submission.
 *
 * @param WC_Order_Item_Product $item Order line item.
 * @return bool
 */
function wctf_has_fazercards_order_item_snapshot( $item ) {
    return $item instanceof WC_Order_Item_Product
        && $item->meta_exists( '_wctf_fazer_offer_id' )
        && $item->meta_exists( '_wctf_fazer_snapshot_created_at' );
}

/**
 * Determine whether automatic submission was enabled when an order item was created.
 *
 * Missing snapshots are deliberately treated as disabled for old orders.
 *
 * @param WC_Order_Item_Product $item Order line item.
 * @return bool
 */
function wctf_is_fazercards_order_item_auto_submit_enabled( $item ) {
    return $item instanceof WC_Order_Item_Product
        && 'yes' === sanitize_key(
            (string) $item->get_meta( '_wctf_fazer_auto_submit_enabled_snapshot', true )
        );
}

/**
 * Get or create the stable idempotency key for an order item.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    Order line item.
 * @param int                   $item_id Order item ID.
 * @return string
 */
function wctf_get_or_create_fazercards_idempotency_key( $order, $item, $item_id ) {
    $idempotency_key = wctf_normalize_fazercards_payload_value(
        $item->get_meta( '_wctf_fazer_idempotency_key', true )
    );

    if ( '' !== $idempotency_key ) {
        return $idempotency_key;
    }

    $site_hash       = substr( hash( 'sha256', untrailingslashit( home_url( '/' ) ) ), 0, 12 );
    $idempotency_key = 'wctf_' . $site_hash . '_' . absint( $order->get_id() ) . '_' . absint( $item_id );

    $item->update_meta_data( '_wctf_fazer_idempotency_key', $idempotency_key );
    $item->save_meta_data();

    return $idempotency_key;
}

/**
 * Acquire a five-minute atomic per-item submission lock.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  Order item ID.
 * @return string Lock token, or an empty string when held elsewhere.
 */
function wctf_acquire_fazercards_submission_lock( $order_id, $item_id ) {
    $lock_key = wctf_get_fazercards_submission_lock_key( $order_id, $item_id );
    $now      = time();
    $token    = wp_generate_uuid4();
    $lock     = array(
        'created' => $now,
        'token'   => $token,
    );

    if ( add_option( $lock_key, $lock, '', 'no' ) ) {
        return $token;
    }

    $existing         = get_option( $lock_key, array() );
    $existing_created = is_array( $existing ) && isset( $existing['created'] )
        ? absint( $existing['created'] )
        : 0;

    if ( 0 !== $existing_created && $existing_created > ( $now - ( 5 * MINUTE_IN_SECONDS ) ) ) {
        return '';
    }

    delete_option( $lock_key );

    /*
     * Do not acquire on the same request that clears a stale lock. This keeps
     * concurrent stale-lock cleanup from allowing two callers through.
     */
    return '';
}

/**
 * Release an owned per-item submission lock.
 *
 * @param int    $order_id WooCommerce order ID.
 * @param int    $item_id  Order item ID.
 * @param string $token    Lock ownership token.
 */
function wctf_release_fazercards_submission_lock( $order_id, $item_id, $token ) {
    $lock_key = wctf_get_fazercards_submission_lock_key( $order_id, $item_id );
    $existing = get_option( $lock_key, array() );

    if (
        is_array( $existing )
        && isset( $existing['token'] )
        && hash_equals( (string) $existing['token'], (string) $token )
    ) {
        delete_option( $lock_key );
    }
}

/**
 * Build the per-item submission lock option name.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  Order item ID.
 * @return string
 */
function wctf_get_fazercards_submission_lock_key( $order_id, $item_id ) {
    return 'wctf_fazer_submit_lock_' . absint( $order_id ) . '_' . absint( $item_id );
}

/**
 * Add a private order note after an actual FazerCards API attempt.
 *
 * @param WC_Order $order           WooCommerce order.
 * @param int      $item_id         Order item ID.
 * @param string   $trigger         Submission trigger.
 * @param bool     $success         Whether submission succeeded.
 * @param string   $remote_order_id Remote order ID.
 * @param string   $remote_status   Remote order status.
 * @param string   $error           Sanitized failure message.
 */
function wctf_add_fazercards_submission_order_note( $order, $item_id, $trigger, $success, $remote_order_id, $remote_status, $error ) {
    $trigger         = sanitize_key( $trigger );
    $remote_order_id = sanitize_text_field( $remote_order_id );
    $remote_status   = sanitize_text_field( $remote_status );
    $error           = sanitize_text_field( $error );
    $is_auto         = 'manual' !== $trigger;

    if ( $success ) {
        $note = $is_auto
            ? sprintf(
                /* translators: 1: item ID, 2: remote order ID, 3: remote status, 4: trigger. */
                __( "FazerCards auto submission succeeded for item #%1\$d.\nRemote order: %2\$s\nRemote status: %3\$s\nTrigger: %4\$s", 'wc-topup-fields' ),
                absint( $item_id ),
                $remote_order_id,
                $remote_status,
                $trigger
            )
            : sprintf(
                /* translators: 1: item ID, 2: remote order ID, 3: remote status. */
                __( "FazerCards submission succeeded for item #%1\$d.\nRemote order: %2\$s\nRemote status: %3\$s", 'wc-topup-fields' ),
                absint( $item_id ),
                $remote_order_id,
                $remote_status
            );
    } else {
        $note = $is_auto
            ? sprintf(
                /* translators: 1: item ID, 2: error, 3: trigger. */
                __( "FazerCards auto submission failed for item #%1\$d.\nError: %2\$s\nTrigger: %3\$s", 'wc-topup-fields' ),
                absint( $item_id ),
                $error,
                $trigger
            )
            : sprintf(
                /* translators: 1: item ID, 2: error. */
                __( "FazerCards submission failed for item #%1\$d.\nError: %2\$s", 'wc-topup-fields' ),
                absint( $item_id ),
                $error
            );
    }

    $order->add_order_note( $note, false, true );
}

/**
 * Determine whether a submission trigger is an approved automatic trigger.
 *
 * @param string $trigger Submission trigger.
 * @return bool
 */
function wctf_is_fazercards_automatic_submission_trigger( $trigger ) {
    return in_array(
        sanitize_key( $trigger ),
        array( 'woocommerce_order_status_processing', 'woocommerce_order_status_completed' ),
        true
    );
}

/**
 * Send one admin-only alert after an actual automatic FazerCards failure.
 *
 * @param WC_Order              $order       WooCommerce order.
 * @param WC_Order_Item_Product $item        Order line item.
 * @param int                   $item_id     Order item ID.
 * @param string                $category_id FazerCards category ID.
 * @param string                $offer_id    FazerCards offer ID.
 * @param string                $error       Sanitized failure message.
 * @param string                $trigger     Submission trigger.
 * @param array                 $customer_fields Sensitive values to redact from the error.
 */
function wctf_maybe_send_fazercards_auto_failure_alert( $order, $item, $item_id, $category_id, $offer_id, $error, $trigger, $customer_fields = array() ) {
    if (
        ! $order instanceof WC_Order
        || ! $item instanceof WC_Order_Item_Product
        || ! wctf_is_fazercards_automatic_submission_trigger( $trigger )
        || 'yes' === sanitize_key( (string) $item->get_meta( '_wctf_fazer_auto_failure_alert_sent', true ) )
    ) {
        return;
    }

    $trigger = sanitize_key( $trigger );
    $item->update_meta_data( '_wctf_fazer_auto_failure_trigger', $trigger );
    $item->update_meta_data( '_wctf_fazer_auto_failure_alert_sent', 'no' );
    $item->delete_meta_data( '_wctf_fazer_auto_failure_alerted_at' );
    $item->save_meta_data();

    $raw_recipient = get_option( 'admin_email', '' );
    $recipient     = is_scalar( $raw_recipient ) ? sanitize_email( (string) $raw_recipient ) : '';

    if ( ! is_email( $recipient ) ) {
        return;
    }

    $order_id      = absint( $order->get_id() );
    $item_id       = absint( $item_id );
    $product_name  = sanitize_text_field( $item->get_name() );
    $category_id   = sanitize_text_field( $category_id );
    $offer_id      = sanitize_text_field( $offer_id );
    $error         = sanitize_text_field( $error );

    if ( is_array( $customer_fields ) ) {
        foreach ( $customer_fields as $customer_value ) {
            if ( ! is_scalar( $customer_value ) ) {
                continue;
            }

            $customer_value = sanitize_text_field( (string) $customer_value );

            if ( '' !== $customer_value ) {
                $error = str_replace( $customer_value, '[redacted customer value]', $error );
            }
        }
    }

    foreach ( array( 'wctf_api_key', 'wctf_api_secret' ) as $credential_option ) {
        $credential = get_option( $credential_option, '' );

        if ( is_scalar( $credential ) && '' !== (string) $credential ) {
            $error = str_replace( (string) $credential, '[redacted credential]', $error );
        }
    }

    $attempted_at  = wctf_normalize_fazercards_payload_value( current_time( 'mysql', true ) );
    $order_edit_url = esc_url_raw( $order->get_edit_order_url() );
    $site_name     = sanitize_text_field( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
    $subject       = sprintf(
        /* translators: 1: site name, 2: order ID, 3: order item ID. */
        __( '[%1$s] FazerCards automatic submission failed — Order #%2$d, item #%3$d', 'wc-topup-fields' ),
        $site_name,
        $order_id,
        $item_id
    );
    $body          = implode(
        "\n",
        array(
            __( 'A FazerCards automatic submission failed.', 'wc-topup-fields' ),
            '',
            sprintf( __( 'WooCommerce order: #%d', 'wc-topup-fields' ), $order_id ),
            sprintf( __( 'Order admin: %s', 'wc-topup-fields' ), $order_edit_url ),
            sprintf( __( 'Order item ID: %d', 'wc-topup-fields' ), $item_id ),
            sprintf( __( 'Product: %s', 'wc-topup-fields' ), $product_name ),
            sprintf( __( 'FazerCards category ID: %s', 'wc-topup-fields' ), $category_id ),
            sprintf( __( 'FazerCards offer ID: %s', 'wc-topup-fields' ), $offer_id ),
            sprintf( __( 'Error: %s', 'wc-topup-fields' ), $error ),
            sprintf( __( 'Attempted at (UTC): %s', 'wc-topup-fields' ), $attempted_at ),
            sprintf( __( 'Trigger: %s', 'wc-topup-fields' ), $trigger ),
            '',
            __( 'Manual retry is available in the WooCommerce order admin.', 'wc-topup-fields' ),
        )
    );
    $mail_attempted = false;

    try {
        $mail_attempted = true;
        wp_mail(
            $recipient,
            $subject,
            $body,
            array( 'Content-Type: text/plain; charset=UTF-8' )
        );
    } catch ( Throwable $throwable ) {
        unset( $throwable );
    } finally {
        if ( $mail_attempted ) {
            $item->update_meta_data( '_wctf_fazer_auto_failure_alert_sent', 'yes' );
            $item->update_meta_data( '_wctf_fazer_auto_failure_alerted_at', $attempted_at );
            $item->update_meta_data( '_wctf_fazer_auto_failure_trigger', $trigger );
            $item->save_meta_data();
        }
    }
}

/**
 * Clear automatic failure alert metadata after any successful submission.
 *
 * @param WC_Order_Item_Product $item Order line item.
 */
function wctf_clear_fazercards_auto_failure_alert( $item ) {
    if ( ! $item instanceof WC_Order_Item_Product ) {
        return;
    }

    $item->delete_meta_data( '_wctf_fazer_auto_failure_alert_sent' );
    $item->delete_meta_data( '_wctf_fazer_auto_failure_alerted_at' );
    $item->delete_meta_data( '_wctf_fazer_auto_failure_trigger' );
}

/**
 * Build a credential-free response summary for order item metadata.
 *
 * @param mixed $response Provider response.
 * @return string
 */
function wctf_build_fazercards_response_summary( $response ) {
    $status = is_array( $response ) && isset( $response['status'] )
        ? absint( $response['status'] )
        : 0;
    $body   = is_array( $response ) && isset( $response['body'] ) && is_array( $response['body'] )
        ? wctf_sanitize_fazercards_response_value( $response['body'] )
        : array();
    $json   = wp_json_encode(
        array(
            'http_status' => $status,
            'body'        => $body,
        ),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    return false === $json ? '{}' : $json;
}

/**
 * Recursively sanitize a provider response value without retaining headers.
 *
 * @param mixed $value Response value.
 * @return mixed
 */
function wctf_sanitize_fazercards_response_value( $value ) {
    if ( is_array( $value ) ) {
        $sanitized = array();

        foreach ( $value as $key => $child_value ) {
            $safe_key               = is_string( $key ) ? sanitize_key( $key ) : absint( $key );
            $sanitized[ $safe_key ] = wctf_sanitize_fazercards_response_value( $child_value );
        }

        return $sanitized;
    }

    if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
        return $value;
    }

    return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
}

/**
 * Store a one-time submission result and return to the order edit screen.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param array    $result Submission result.
 */
function wctf_finish_fazercards_submission_action( $order, $result ) {
    set_transient(
        wctf_get_fazercards_submission_result_transient_key(
            $order->get_id(),
            get_current_user_id()
        ),
        $result,
        5 * MINUTE_IN_SECONDS
    );

    wp_safe_redirect( $order->get_edit_order_url() );
    exit;
}

/**
 * Build a user- and order-scoped transient key for submission feedback.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $user_id  WordPress user ID.
 * @return string
 */
function wctf_get_fazercards_submission_result_transient_key( $order_id, $user_id ) {
    return 'wctf_fazer_submit_' . absint( $user_id ) . '_' . absint( $order_id );
}

/**
 * Add the FazerCards status column to WooCommerce order lists.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function wctf_add_fazercards_order_list_column( $columns ) {
    $updated = array();
    $added   = false;

    foreach ( $columns as $column_key => $column_label ) {
        $updated[ $column_key ] = $column_label;

        if ( 'order_status' === $column_key ) {
            $updated['wctf_fazercards_status'] = __( 'FazerCards', 'wc-topup-fields' );
            $added                             = true;
        }
    }

    if ( ! $added ) {
        $updated['wctf_fazercards_status'] = __( 'FazerCards', 'wc-topup-fields' );
    }

    return $updated;
}

/**
 * Render the FazerCards status column on the classic order list.
 *
 * @param string $column_name Column key.
 * @param int    $post_id     Order post ID.
 */
function wctf_render_fazercards_order_list_column_classic( $column_name, $post_id ) {
    if ( 'wctf_fazercards_status' !== $column_name ) {
        return;
    }

    wctf_render_fazercards_order_list_status( wc_get_order( absint( $post_id ) ) );
}

/**
 * Render the FazerCards status column on the HPOS order list.
 *
 * @param string       $column_name Column key.
 * @param WC_Order|int $order       Order object or ID.
 */
function wctf_render_fazercards_order_list_column_hpos( $column_name, $order ) {
    if ( 'wctf_fazercards_status' !== $column_name ) {
        return;
    }

    if ( ! $order instanceof WC_Order ) {
        $order = wc_get_order( absint( $order ) );
    }

    wctf_render_fazercards_order_list_status( $order );
}

/**
 * Render a sanitized aggregate FazerCards status for one order.
 *
 * @param WC_Order|false $order WooCommerce order.
 */
function wctf_render_fazercards_order_list_status( $order ) {
    $status = wctf_get_fazercards_order_submission_state( $order );

    echo '<span class="wctf-fazercards-order-status wctf-fazercards-order-status-' . esc_attr( $status ) . '">';
    echo esc_html( wctf_get_fazercards_submission_status_label( $status ) );
    echo '</span>';
}

/**
 * Aggregate item-level FazerCards submission states for an order.
 *
 * @param WC_Order|false $order WooCommerce order.
 * @return string
 */
function wctf_get_fazercards_order_submission_state( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return 'not_applicable';
    }

    $statuses = array();

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            continue;
        }

        $prepared = wctf_prepare_fazercards_order_item_payload( $order, $item, $item_id );

        if ( empty( $prepared['success'] ) ) {
            continue;
        }

        $status = sanitize_key( (string) $item->get_meta( '_wctf_fazer_submission_status', true ) );

        if ( ! in_array( $status, array( 'submitting', 'submitted', 'failed' ), true ) ) {
            $status = 'not_submitted';
        }

        $statuses[] = $status;
    }

    if ( empty( $statuses ) ) {
        return 'not_applicable';
    }

    $statuses = array_values( array_unique( $statuses ) );

    return 1 === count( $statuses ) ? $statuses[0] : 'mixed';
}
