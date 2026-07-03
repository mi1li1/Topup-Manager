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

        if ( '' === $submission_status ) {
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

        $can_submit = $is_ready
            && $order_item instanceof WC_Order_Item_Product
            && 'submitted' !== $submission_status;
        $json      = wp_json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ( false === $json ) {
            $json       = '{}';
            $warnings[] = __( 'The payload result could not be encoded.', 'wc-topup-fields' );
            $is_ready   = false;
        }

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

    $statuses = array();

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            continue;
        }

        $status = sanitize_key( (string) $item->get_meta( '_wctf_fazer_submission_status', true ) );

        if ( '' === $status ) {
            continue;
        }

        $statuses[] = array(
            'item_id'       => absint( $item_id ),
            'item_name'     => sanitize_text_field( $item->get_name() ),
            'status'        => $status,
            'remote_id'     => wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_remote_order_id', true ) ),
            'remote_status' => wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_remote_status', true ) ),
            'last_error'    => wctf_normalize_fazercards_payload_value( $item->get_meta( '_wctf_fazer_last_error', true ) ),
        );
    }

    if ( empty( $statuses ) ) {
        return;
    }

    echo '<hr>';
    echo '<h4>' . esc_html__( 'FazerCards Submission Status', 'wc-topup-fields' ) . '</h4>';
    echo '<ul>';

    foreach ( $statuses as $status_data ) {
        echo '<li>';
        echo '<strong>';
        echo esc_html(
            sprintf(
                /* translators: 1: order item ID, 2: product name. */
                __( 'Order item #%1$d — %2$s', 'wc-topup-fields' ),
                $status_data['item_id'],
                $status_data['item_name']
            )
        );
        echo '</strong>: ';
        echo esc_html( $status_data['status'] );

        if ( '' !== $status_data['remote_id'] ) {
            echo ' — ';
            echo esc_html__( 'Remote order:', 'wc-topup-fields' ) . ' ';
            echo esc_html( $status_data['remote_id'] );
        }

        if ( '' !== $status_data['remote_status'] ) {
            echo ' (' . esc_html( $status_data['remote_status'] ) . ')';
        }

        if ( '' !== $status_data['last_error'] ) {
            echo '<br>';
            echo '<span class="notice notice-error inline">';
            echo esc_html( $status_data['last_error'] );
            echo '</span>';
        }

        echo '</li>';
    }

    echo '</ul>';
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

    $submission_status = sanitize_key( (string) $item->get_meta( '_wctf_fazer_submission_status', true ) );

    if ( 'submitted' === $submission_status ) {
        wctf_finish_fazercards_submission_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'This order item was already submitted to FazerCards.', 'wc-topup-fields' ),
            )
        );
    }

    $prepared = wctf_prepare_fazercards_order_item_payload( $order, $item, $item_id );
    $warnings = isset( $prepared['warnings'] ) && is_array( $prepared['warnings'] )
        ? array_filter( $prepared['warnings'] )
        : array();
    $payload  = isset( $prepared['payload'] ) && is_array( $prepared['payload'] )
        ? $prepared['payload']
        : array();

    if ( empty( $prepared['success'] ) || ! empty( $warnings ) ) {
        wctf_finish_fazercards_submission_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'The payload is not ready. Run the Dry Run and resolve all warnings.', 'wc-topup-fields' ),
            )
        );
    }

    if ( ! isset( $payload['quantity'] ) || 1 !== (int) $payload['quantity'] ) {
        wctf_finish_fazercards_submission_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'Only order items with quantity exactly 1 can be submitted.', 'wc-topup-fields' ),
            )
        );
    }

    $category_id    = isset( $payload['category_id'] ) && is_scalar( $payload['category_id'] )
        ? sanitize_text_field( (string) $payload['category_id'] )
        : '';
    $offer_id       = isset( $payload['offer_id'] ) && is_scalar( $payload['offer_id'] )
        ? sanitize_text_field( (string) $payload['offer_id'] )
        : '';
    $customer_fields = isset( $payload['customer_fields'] ) && is_array( $payload['customer_fields'] )
        ? $payload['customer_fields']
        : array();

    if ( '' === $category_id || '' === $offer_id || empty( $customer_fields ) ) {
        wctf_finish_fazercards_submission_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'Category ID, offer ID, and customer fields are required.', 'wc-topup-fields' ),
            )
        );
    }

    $config = function_exists( 'wctf_config' ) ? wctf_config() : array();

    if ( empty( $config['api_url'] ) || empty( $config['api_key'] ) ) {
        wctf_finish_fazercards_submission_action(
            $order,
            array(
                'success' => false,
                'message' => __( 'Save the FazerCards API URL and API key before submitting.', 'wc-topup-fields' ),
            )
        );
    }

    $idempotency_key = wctf_normalize_fazercards_payload_value(
        $item->get_meta( '_wctf_fazer_idempotency_key', true )
    );

    if ( '' === $idempotency_key ) {
        $site_hash       = substr( hash( 'sha256', untrailingslashit( home_url( '/' ) ) ), 0, 12 );
        $idempotency_key = 'wctf_' . $site_hash . '_' . $order_id . '_' . $item_id;

        $item->update_meta_data( '_wctf_fazer_idempotency_key', $idempotency_key );
        $item->save_meta_data();
    }

    $provider = new WCTF_FazerCards_Provider();
    $response = $provider->create_order(
        $category_id,
        $offer_id,
        $customer_fields,
        $idempotency_key
    );
    $response_summary = wctf_build_fazercards_response_summary( $response );
    $response_body    = isset( $response['body'] ) && is_array( $response['body'] )
        ? $response['body']
        : array();
    $remote_order     = isset( $response_body['order'] ) && is_array( $response_body['order'] )
        ? $response_body['order']
        : array();
    $remote_order_id  = isset( $remote_order['id'] ) && is_scalar( $remote_order['id'] )
        ? sanitize_text_field( (string) $remote_order['id'] )
        : '';
    $remote_status    = isset( $remote_order['status'] ) && is_scalar( $remote_order['status'] )
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
        $item->save_meta_data();

        wctf_finish_fazercards_submission_action(
            $order,
            array(
                'success' => true,
                'message' => sprintf(
                    /* translators: %s: FazerCards remote order ID. */
                    __( 'FazerCards order submitted successfully: %s.', 'wc-topup-fields' ),
                    $remote_order_id
                ),
            )
        );
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

    wctf_finish_fazercards_submission_action(
        $order,
        array(
            'success' => false,
            'message' => $error,
        )
    );
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
