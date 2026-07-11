<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'add_meta_boxes', 'wctf_register_fazercards_giftcard_purchase_meta_box' );
add_action(
    'admin_post_wctf_fazercards_giftcard_purchase_item',
    'wctf_handle_fazercards_giftcard_purchase_item'
);
add_action(
    'admin_post_wctf_fazercards_giftcard_refresh_order',
    'wctf_handle_fazercards_giftcard_refresh_order'
);
add_action(
    'admin_post_wctf_fazercards_giftcard_start_auto_refresh_retry',
    'wctf_handle_fazercards_giftcard_start_auto_refresh_retry'
);
add_action(
    'wctf_fazercards_giftcard_auto_refresh_retry',
    'wctf_run_fazercards_giftcard_auto_refresh_retry',
    10,
    2
);
add_action(
    'admin_post_wctf_fazercards_giftcard_fast_settle',
    'wctf_handle_fazercards_giftcard_fast_settle'
);
add_action(
    'admin_post_nopriv_wctf_fazercards_giftcard_fast_settle',
    'wctf_handle_fazercards_giftcard_fast_settle'
);

/**
 * Register the manual REAL Gift Card purchase meta box for classic and HPOS.
 *
 * @return void
 */
function wctf_register_fazercards_giftcard_purchase_meta_box() {
    $screens = array( 'shop_order' );

    if ( function_exists( 'wc_get_page_screen_id' ) ) {
        $screens[] = wc_get_page_screen_id( 'shop-order' );
    }

    foreach ( array_unique( $screens ) as $screen ) {
        add_meta_box(
            'wctf-fazercards-giftcard-manual-purchase',
            __( 'FazerCards Gift Card Manual Purchase', 'wc-topup-fields' ),
            'wctf_render_fazercards_giftcard_purchase_meta_box',
            $screen,
            'normal',
            'default'
        );
    }
}

/**
 * Render the admin-only, per-item Gift Card manual purchase controls.
 *
 * This view never decrypts or displays the stored secret payload.
 *
 * @param WP_Post|WC_Order $post_or_order_object Order screen object.
 * @return void
 */
function wctf_render_fazercards_giftcard_purchase_meta_box( $post_or_order_object ) {
    $order = wctf_get_fazercards_giftcard_order_from_screen( $post_or_order_object );

    if ( ! $order instanceof WC_Order ) {
        echo '<p>' . esc_html__( 'The WooCommerce order could not be loaded.', 'wc-topup-fields' ) . '</p>';
        return;
    }

    $order_id = absint( $order->get_id() );

    if (
        ! current_user_can( 'manage_woocommerce' )
        || ! current_user_can( 'edit_shop_order', $order_id )
    ) {
        echo '<p>' . esc_html__( 'You are not allowed to purchase Gift Cards for this order.', 'wc-topup-fields' ) . '</p>';
        return;
    }

    $notice = get_transient(
        wctf_get_fazercards_giftcard_purchase_result_transient_key(
            $order_id,
            get_current_user_id()
        )
    );

    delete_transient(
        wctf_get_fazercards_giftcard_purchase_result_transient_key(
            $order_id,
            get_current_user_id()
        )
    );

    if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
        $notice_type = isset( $notice['result_type'] )
            ? sanitize_key( (string) $notice['result_type'] )
            : 'error';
        $notice_class = 'success' === $notice_type
            ? 'notice-success'
            : ( 'warning' === $notice_type ? 'notice-warning' : 'notice-error' );

        echo '<div class="notice ' . esc_attr( $notice_class ) . ' inline" role="status"><p>';
        echo esc_html( wctf_limit_fazercards_giftcard_purchase_string( $notice['message'], 500 ) );
        echo '</p></div>';
    }

    if ( function_exists( 'wctf_get_fazercards_giftcard_reveal_result_transient_key' ) ) {
        $reveal_notice_key = wctf_get_fazercards_giftcard_reveal_result_transient_key(
            $order_id,
            get_current_user_id()
        );
        $reveal_notice     = get_transient( $reveal_notice_key );

        delete_transient( $reveal_notice_key );

        if ( is_array( $reveal_notice ) && ! empty( $reveal_notice['message'] ) ) {
            $reveal_notice_type = isset( $reveal_notice['type'] )
                ? sanitize_key( (string) $reveal_notice['type'] )
                : 'error';
            $reveal_class       = 'success' === $reveal_notice_type
                ? 'notice-success'
                : ( 'warning' === $reveal_notice_type ? 'notice-warning' : 'notice-error' );

            echo '<div class="notice ' . esc_attr( $reveal_class ) . ' inline" role="status"><p>';
            echo esc_html( wctf_limit_fazercards_giftcard_purchase_string( $reveal_notice['message'], 500 ) );
            echo '</p></div>';
        }
    }

    $refresh_notice = get_transient(
        wctf_get_fazercards_giftcard_refresh_result_transient_key(
            $order_id,
            get_current_user_id()
        )
    );

    delete_transient(
        wctf_get_fazercards_giftcard_refresh_result_transient_key(
            $order_id,
            get_current_user_id()
        )
    );

    if ( is_array( $refresh_notice ) && ! empty( $refresh_notice['message'] ) ) {
        $refresh_notice_type = isset( $refresh_notice['type'] )
            ? sanitize_key( (string) $refresh_notice['type'] )
            : 'error';
        $refresh_class       = 'success' === $refresh_notice_type
            ? 'notice-success'
            : ( 'warning' === $refresh_notice_type ? 'notice-warning' : 'notice-error' );

        echo '<div class="notice ' . esc_attr( $refresh_class ) . ' inline" role="status"><p>';
        echo esc_html( wctf_limit_fazercards_giftcard_purchase_string( $refresh_notice['message'], 500 ) );
        echo '</p></div>';
    }

    echo '<p><strong>';
    echo esc_html__( 'This tool creates real FazerCards Gift Card purchases and may spend real balance.', 'wc-topup-fields' );
    echo '</strong></p>';
    echo '<p>';
    echo esc_html__( 'Purchases are manual and per order item. No Gift Card codes are shown or delivered in this release.', 'wc-topup-fields' );
    echo '</p>';

    $found_giftcard_item = false;

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if (
            ! $item instanceof WC_Order_Item_Product
            || 'giftcard' !== sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) )
        ) {
            continue;
        }

        $found_giftcard_item = true;
        $readiness           = wctf_get_fazercards_giftcard_purchase_readiness(
            $order,
            $item,
            $item_id
        );
        $snapshot            = isset( $readiness['snapshot'] ) && is_array( $readiness['snapshot'] )
            ? $readiness['snapshot']
            : array();
        $status              = isset( $readiness['status'] )
            ? $readiness['status']
            : 'not_purchased';
        $reasons             = isset( $readiness['reasons'] ) && is_array( $readiness['reasons'] )
            ? array_values( array_unique( array_filter( $readiness['reasons'], 'is_scalar' ) ) )
            : array();
        $warnings            = isset( $readiness['warnings'] ) && is_array( $readiness['warnings'] )
            ? array_values( array_unique( array_filter( $readiness['warnings'], 'is_scalar' ) ) )
            : array();
        $remote_order_id     = wctf_limit_fazercards_giftcard_purchase_string(
            $item->get_meta( '_wctf_fazer_giftcard_remote_order_id', true ),
            191
        );
        $remote_status       = wctf_limit_fazercards_giftcard_purchase_string(
            $item->get_meta( '_wctf_fazer_giftcard_remote_status', true ),
            100
        );
        $last_error          = wctf_sanitize_fazercards_giftcard_purchase_error(
            $item->get_meta( '_wctf_fazer_giftcard_last_error', true ),
            500
        );
        $has_secret          = function_exists( 'wctf_fazercards_giftcard_has_secret_payload' )
            && wctf_fazercards_giftcard_has_secret_payload( $item );
        $crypto_status       = function_exists( 'wctf_fazercards_giftcard_crypto_status' )
            ? wctf_fazercards_giftcard_crypto_status()
            : array();
        $crypto_ready        = ! empty( $crypto_status['ready'] );
        $stored_codes_count  = $item->get_meta( '_wctf_fazer_giftcard_codes_count', true );
        $codes_count_value   = $item->meta_exists( '_wctf_fazer_giftcard_codes_count' )
            && is_scalar( $stored_codes_count )
            && 1 === preg_match( '/\A[0-9]+\z/D', (string) $stored_codes_count )
                ? absint( $stored_codes_count )
                : null;
        $codes_count         = null === $codes_count_value
            ? __( 'Unknown', 'wc-topup-fields' )
            : (string) $codes_count_value;
        $product_name       = sanitize_text_field( $item->get_name() );
        $quantity           = isset( $readiness['quantity'] ) && null !== $readiness['quantity']
            ? $readiness['quantity']
            : $item->get_quantity();
        $reveal_count_raw   = $item->get_meta( '_wctf_fazer_giftcard_codes_reveal_count', true );
        $reveal_count       = is_scalar( $reveal_count_raw ) && 1 === preg_match( '/\A[0-9]+\z/D', (string) $reveal_count_raw )
            ? (string) absint( $reveal_count_raw )
            : '0';
        $last_revealed_at   = wctf_limit_fazercards_giftcard_purchase_string(
            $item->get_meta( '_wctf_fazer_giftcard_codes_revealed_at', true ),
            32
        );
        $last_revealed_by   = $item->get_meta( '_wctf_fazer_giftcard_last_revealed_by_user_id', true );
        $last_revealed_by   = is_scalar( $last_revealed_by ) && 1 === preg_match( '/\A[0-9]+\z/D', (string) $last_revealed_by )
            ? (string) absint( $last_revealed_by )
            : '';
        $can_reveal_secret  = $has_secret
            && $crypto_ready
            && current_user_can( 'manage_woocommerce' )
            && current_user_can( 'edit_shop_order', $order_id )
            && function_exists( 'wctf_fazercards_giftcard_get_secret_payload_wrapper' );
        $remote_order_id_valid = 1 === preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id );
        $can_refresh_order     = '' !== $remote_order_id
            && $remote_order_id_valid
            && $crypto_ready
            && current_user_can( 'manage_woocommerce' )
            && current_user_can( 'edit_shop_order', $order_id )
            && function_exists( 'wctf_fazercards_giftcard_store_secret_payload' )
            && ! wctf_is_fazercards_giftcard_purchase_lock_active( $order_id, $item_id );
        $status_may_need_refresh = $has_secret
            && ( null === $codes_count_value || 0 === $codes_count_value )
            && in_array( sanitize_key( $remote_status ), array( 'created', 'pending' ), true );
        $fulfillment_status      = wctf_normalize_fazercards_giftcard_fulfillment_status(
            $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true )
        );
        $ready_to_deliver        = wctf_is_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id );
        $auto_refresh_attempts   = absint( $item->get_meta( '_wctf_fazer_giftcard_auto_refresh_attempts', true ) );
        $auto_refresh_max        = wctf_get_fazercards_giftcard_auto_refresh_max_attempts( $item );
        $next_refresh_at         = wctf_limit_fazercards_giftcard_purchase_string(
            $item->get_meta( '_wctf_fazer_giftcard_next_refresh_at', true ),
            32
        );
        $last_refresh_at         = wctf_limit_fazercards_giftcard_purchase_string(
            $item->get_meta( '_wctf_fazer_giftcard_last_refresh_at', true ),
            32
        );
        $last_refresh_error      = wctf_sanitize_fazercards_giftcard_purchase_error(
            $item->get_meta( '_wctf_fazer_giftcard_last_refresh_error', true ),
            500
        );
        $queue_backend          = wctf_get_fazercards_giftcard_auto_refresh_queue_backend();
        $has_future_retry       = wctf_has_scheduled_fazercards_giftcard_auto_refresh_retry(
            $order_id,
            $item_id
        );
        $queue_event_status     = wctf_get_fazercards_giftcard_auto_refresh_event_status(
            $fulfillment_status,
            $has_future_retry,
            $auto_refresh_attempts
        );
        $fast_settle_status     = wctf_normalize_fazercards_giftcard_fast_settle_status(
            $item->get_meta( '_wctf_fazer_giftcard_fast_settle_status', true )
        );
        $fast_settle_attempts   = absint(
            $item->get_meta( '_wctf_fazer_giftcard_fast_settle_attempts', true )
        );
        $fast_settle_started_at = wctf_limit_fazercards_giftcard_purchase_string(
            $item->get_meta( '_wctf_fazer_giftcard_fast_settle_started_at', true ),
            32
        );
        $fast_settle_completed_at = wctf_limit_fazercards_giftcard_purchase_string(
            $item->get_meta( '_wctf_fazer_giftcard_fast_settle_completed_at', true ),
            32
        );
        $fast_settle_last_error = wctf_sanitize_fazercards_giftcard_purchase_error(
            $item->get_meta( '_wctf_fazer_giftcard_fast_settle_last_error', true ),
            500
        );
        $can_start_auto_refresh  = '' !== $remote_order_id
            && $remote_order_id_valid
            && ( null === $codes_count_value || 0 === $codes_count_value )
            && $crypto_ready
            && current_user_can( 'manage_woocommerce' )
            && current_user_can( 'edit_shop_order', $order_id )
            && $auto_refresh_attempts < $auto_refresh_max
            && ! $ready_to_deliver;

        ?>
        <section class="wctf-fazercards-giftcard-purchase-item">
            <h4>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: order item ID, 2: product name. */
                        __( 'Order item #%1$d — %2$s', 'wc-topup-fields' ),
                        absint( $item_id ),
                        $product_name
                    )
                );
                ?>
            </h4>

            <table class="widefat striped" style="max-width: 850px;">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Item ID', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( absint( $item_id ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Product name', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $product_name ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Quantity', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( is_scalar( $quantity ) ? (string) $quantity : '' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Category ID', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( isset( $snapshot['category_id'] ) ? $snapshot['category_id'] : '' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Card ID', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( isset( $snapshot['card_id'] ) ? $snapshot['card_id'] : '' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Gift Card name', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( ! empty( $snapshot['offer_name'] ) ? $snapshot['offer_name'] : __( 'Not available', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Snapshot price USD', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( ! empty( $snapshot['price_usd'] ) ? $snapshot['price_usd'] : __( 'Not available', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Currency', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( ! empty( $snapshot['currency'] ) ? $snapshot['currency'] : __( 'Not available', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Region', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( ! empty( $snapshot['region'] ) ? $snapshot['region'] : __( 'Not available', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Purchase status', 'wc-topup-fields' ); ?></th>
                        <td><strong><?php echo esc_html( wctf_get_fazercards_giftcard_purchase_status_label( $status ) ); ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Encryption ready', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $crypto_ready ? __( 'Yes', 'wc-topup-fields' ) : __( 'No', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Remote order ID', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $remote_order_id ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Remote status', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $remote_status ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Encrypted secret stored', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $has_secret ? __( 'Yes', 'wc-topup-fields' ) : __( 'No', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Detected code count', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $codes_count ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Fulfillment status', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( wctf_get_fazercards_giftcard_fulfillment_status_label( $fulfillment_status ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Ready to deliver', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $ready_to_deliver ? __( 'Yes', 'wc-topup-fields' ) : __( 'No', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Queue backend', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $queue_backend ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Queue event status', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $queue_event_status ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto refresh attempts', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( absint( $auto_refresh_attempts ) . ' / ' . absint( $auto_refresh_max ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Next refresh at', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( '' !== $next_refresh_at ? $next_refresh_at : __( 'Not scheduled', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Last refresh at', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( '' !== $last_refresh_at ? $last_refresh_at : __( 'Not refreshed by queue', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Last safe refresh error', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $last_refresh_error ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Fast settle status', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( wctf_get_fazercards_giftcard_fast_settle_status_label( $fast_settle_status ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Fast settle attempts', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( absint( $fast_settle_attempts ) . ' / 5' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Fast settle started at', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( '' !== $fast_settle_started_at ? $fast_settle_started_at : __( 'Not started', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Fast settle completed at', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( '' !== $fast_settle_completed_at ? $fast_settle_completed_at : __( 'Not completed', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Fast settle last safe error', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $fast_settle_last_error ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Queue fallback status', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $queue_event_status ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Reveal count', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $reveal_count ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Last revealed at', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( '' !== $last_revealed_at ? $last_revealed_at : __( 'Not revealed', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Last revealed by user ID', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( '' !== $last_revealed_by ? $last_revealed_by : __( 'Not revealed', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Last safe error', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $last_error ); ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ( 'queued' === $fulfillment_status && ! $has_future_retry ) : ?>
                <p class="notice notice-warning inline">
                    <?php
                    esc_html_e(
                        'Auto refresh retry is marked queued but no future retry action is currently scheduled. Use Resume Auto Refresh Retry.',
                        'wc-topup-fields'
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $reasons ) ) : ?>
                <div class="notice notice-warning inline" role="alert">
                    <p><strong><?php esc_html_e( 'Purchase is not ready:', 'wc-topup-fields' ); ?></strong></p>
                    <ul>
                        <?php foreach ( $reasons as $reason ) : ?>
                            <li><?php echo esc_html( (string) $reason ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $warnings ) ) : ?>
                <div class="notice notice-info inline" role="status">
                    <p><strong><?php esc_html_e( 'Non-blocking notes:', 'wc-topup-fields' ); ?></strong></p>
                    <ul>
                        <?php foreach ( $warnings as $warning ) : ?>
                            <li><?php echo esc_html( (string) $warning ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ( $has_secret ) : ?>
                <div class="notice notice-info inline" role="status">
                    <p><?php esc_html_e( 'Encrypted payload exists. Reveal can inspect the currently stored response.', 'wc-topup-fields' ); ?></p>
                    <?php if ( $status_may_need_refresh ) : ?>
                        <p><?php esc_html_e( 'The stored response may not contain final card codes yet. A future remote refresh/recovery step may be required.', 'wc-topup-fields' ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $readiness['ready'] ) ) : ?>
                <?php
                $purchase_item_id   = absint( $item_id );
                $confirmation_id    = 'wctf-giftcard-purchase-confirmation-' . $purchase_item_id;
                $confirmed_id       = 'wctf-giftcard-purchase-confirmed-' . $purchase_item_id;
                $purchase_nonce     = wp_create_nonce(
                    'wctf_purchase_fazercards_giftcard_' . $order_id . '_' . $purchase_item_id
                );
                ?>

                <div class="wctf-fazercards-giftcard-purchase-controls">
                    <p><strong><?php esc_html_e( 'This will spend real FazerCards balance.', 'wc-topup-fields' ); ?></strong></p>

                    <p>
                        <label for="<?php echo esc_attr( $confirmed_id ); ?>">
                            <input
                                type="checkbox"
                                id="<?php echo esc_attr( $confirmed_id ); ?>"
                                value="1"
                            >
                            <?php esc_html_e( 'I understand this will spend real FazerCards balance.', 'wc-topup-fields' ); ?>
                        </label>
                    </p>

                    <p>
                        <label for="<?php echo esc_attr( $confirmation_id ); ?>">
                            <?php esc_html_e( 'Type PURCHASE to confirm:', 'wc-topup-fields' ); ?>
                            <input
                                type="text"
                                id="<?php echo esc_attr( $confirmation_id ); ?>"
                                value=""
                                autocomplete="off"
                                pattern="PURCHASE"
                            >
                        </label>
                    </p>

                    <p>
                        <button
                            type="button"
                            class="button button-primary wctf-fazercards-giftcard-purchase-button"
                            data-action-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                            data-order-id="<?php echo esc_attr( $order_id ); ?>"
                            data-item-id="<?php echo esc_attr( $purchase_item_id ); ?>"
                            data-nonce="<?php echo esc_attr( $purchase_nonce ); ?>"
                            data-confirmed-id="<?php echo esc_attr( $confirmed_id ); ?>"
                            data-confirmation-id="<?php echo esc_attr( $confirmation_id ); ?>"
                        >
                            <?php esc_html_e( 'Purchase Gift Card (REAL)', 'wc-topup-fields' ); ?>
                        </button>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( $can_reveal_secret ) : ?>
                <?php
                $reveal_item_id        = absint( $item_id );
                $reveal_confirmation_id = 'wctf-giftcard-reveal-confirmation-' . $reveal_item_id;
                $reveal_confirmed_id    = 'wctf-giftcard-reveal-confirmed-' . $reveal_item_id;
                $reveal_nonce           = wp_create_nonce(
                    'wctf_reveal_fazercards_giftcard_' . $order_id . '_' . $reveal_item_id
                );
                ?>

                <div class="wctf-fazercards-giftcard-reveal-controls">
                    <p><strong><?php esc_html_e( 'This reveals cash-equivalent Gift Card secrets.', 'wc-topup-fields' ); ?></strong></p>

                    <p>
                        <label for="<?php echo esc_attr( $reveal_confirmed_id ); ?>">
                            <input
                                type="checkbox"
                                id="<?php echo esc_attr( $reveal_confirmed_id ); ?>"
                                value="1"
                            >
                            <?php esc_html_e( 'I understand this reveals cash-equivalent Gift Card secrets.', 'wc-topup-fields' ); ?>
                        </label>
                    </p>

                    <p>
                        <label for="<?php echo esc_attr( $reveal_confirmation_id ); ?>">
                            <?php esc_html_e( 'Type REVEAL to confirm:', 'wc-topup-fields' ); ?>
                            <input
                                type="text"
                                id="<?php echo esc_attr( $reveal_confirmation_id ); ?>"
                                value=""
                                autocomplete="off"
                                pattern="REVEAL"
                            >
                        </label>
                    </p>

                    <p>
                        <button
                            type="button"
                            class="button wctf-fazercards-giftcard-reveal-button"
                            data-action-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                            data-order-id="<?php echo esc_attr( $order_id ); ?>"
                            data-item-id="<?php echo esc_attr( $reveal_item_id ); ?>"
                            data-nonce="<?php echo esc_attr( $reveal_nonce ); ?>"
                            data-confirmed-id="<?php echo esc_attr( $reveal_confirmed_id ); ?>"
                            data-confirmation-id="<?php echo esc_attr( $reveal_confirmation_id ); ?>"
                        >
                            <?php esc_html_e( 'Reveal Gift Card Secret', 'wc-topup-fields' ); ?>
                        </button>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( $can_refresh_order ) : ?>
                <?php
                $refresh_item_id        = absint( $item_id );
                $refresh_confirmation_id = 'wctf-giftcard-refresh-confirmation-' . $refresh_item_id;
                $refresh_confirmed_id    = 'wctf-giftcard-refresh-confirmed-' . $refresh_item_id;
                $refresh_nonce           = wp_create_nonce(
                    'wctf_refresh_fazercards_giftcard_' . $order_id . '_' . $refresh_item_id
                );
                ?>

                <div class="wctf-fazercards-giftcard-refresh-controls">
                    <p><strong><?php esc_html_e( 'Refresh Gift Card Remote Order', 'wc-topup-fields' ); ?></strong></p>
                    <p><?php esc_html_e( 'This does not create another purchase. It fetches the latest remote order and replaces the encrypted stored payload.', 'wc-topup-fields' ); ?></p>

                    <p>
                        <label for="<?php echo esc_attr( $refresh_confirmed_id ); ?>">
                            <input
                                type="checkbox"
                                id="<?php echo esc_attr( $refresh_confirmed_id ); ?>"
                                value="1"
                            >
                            <?php esc_html_e( 'I understand this refreshes the stored Gift Card order from FazerCards without creating a new purchase.', 'wc-topup-fields' ); ?>
                        </label>
                    </p>

                    <p>
                        <label for="<?php echo esc_attr( $refresh_confirmation_id ); ?>">
                            <?php esc_html_e( 'Type REFRESH to confirm:', 'wc-topup-fields' ); ?>
                            <input
                                type="text"
                                id="<?php echo esc_attr( $refresh_confirmation_id ); ?>"
                                value=""
                                autocomplete="off"
                                pattern="REFRESH"
                            >
                        </label>
                    </p>

                    <p>
                        <button
                            type="button"
                            class="button wctf-fazercards-giftcard-refresh-button"
                            data-action-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                            data-order-id="<?php echo esc_attr( $order_id ); ?>"
                            data-item-id="<?php echo esc_attr( $refresh_item_id ); ?>"
                            data-nonce="<?php echo esc_attr( $refresh_nonce ); ?>"
                            data-confirmed-id="<?php echo esc_attr( $refresh_confirmed_id ); ?>"
                            data-confirmation-id="<?php echo esc_attr( $refresh_confirmation_id ); ?>"
                        >
                            <?php esc_html_e( 'Refresh Gift Card Remote Order', 'wc-topup-fields' ); ?>
                        </button>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( $can_start_auto_refresh ) : ?>
                <?php
                $queue_item_id = absint( $item_id );
                $queue_nonce   = wp_create_nonce(
                    'wctf_start_fazercards_giftcard_auto_refresh_' . $order_id . '_' . $queue_item_id
                );
                $queue_label   = in_array( $fulfillment_status, array( 'queued', 'refreshing', 'needs_admin_review', 'stopped' ), true )
                    ? __( 'Resume Auto Refresh Retry', 'wc-topup-fields' )
                    : __( 'Start Auto Refresh Retry', 'wc-topup-fields' );
                ?>

                <div class="wctf-fazercards-giftcard-auto-refresh-controls">
                    <p><strong><?php esc_html_e( 'Gift Card Auto Refresh Retry', 'wc-topup-fields' ); ?></strong></p>
                    <p><?php esc_html_e( 'This schedules safe read-only remote refresh attempts. It does not purchase again, reveal codes, or deliver to the customer.', 'wc-topup-fields' ); ?></p>
                    <p>
                        <button
                            type="button"
                            class="button wctf-fazercards-giftcard-auto-refresh-button"
                            data-action-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                            data-order-id="<?php echo esc_attr( $order_id ); ?>"
                            data-item-id="<?php echo esc_attr( $queue_item_id ); ?>"
                            data-nonce="<?php echo esc_attr( $queue_nonce ); ?>"
                        >
                            <?php echo esc_html( $queue_label ); ?>
                        </button>
                    </p>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    if ( ! $found_giftcard_item ) {
        echo '<div class="notice notice-info inline"><p>';
        echo esc_html__( 'No snapshotted FazerCards Gift Card order items were found.', 'wc-topup-fields' );
        echo '</p></div>';
    }

    ?>
    <script>
    ( function() {
        if ( window.wctfFazerCardsGiftCardPurchaseSubmitBound ) {
            return;
        }

        window.wctfFazerCardsGiftCardPurchaseSubmitBound = true;

        function addField( form, name, value ) {
            var field = document.createElement( 'input' );
            field.type = 'hidden';
            field.name = name;
            field.value = value;
            form.appendChild( field );
        }

        document.addEventListener( 'click', function( event ) {
            var button = event.target.closest
                ? event.target.closest( '.wctf-fazercards-giftcard-purchase-button' )
                : null;

            if ( ! button ) {
                return;
            }

            var confirmed = document.getElementById( button.getAttribute( 'data-confirmed-id' ) );
            var confirmation = document.getElementById( button.getAttribute( 'data-confirmation-id' ) );

            event.preventDefault();

            if ( ! confirmed || ! confirmed.checked ) {
                if ( confirmed && confirmed.reportValidity ) {
                    confirmed.required = true;
                    confirmed.reportValidity();
                }
                return;
            }

            if ( ! confirmation || 'PURCHASE' !== confirmation.value ) {
                if ( confirmation && confirmation.setCustomValidity && confirmation.reportValidity ) {
                    confirmation.setCustomValidity( '<?php echo esc_js( __( 'Type PURCHASE exactly to confirm.', 'wc-topup-fields' ) ); ?>' );
                    confirmation.reportValidity();
                    confirmation.addEventListener( 'input', function clearConfirmationError() {
                        confirmation.setCustomValidity( '' );
                        confirmation.removeEventListener( 'input', clearConfirmationError );
                    } );
                }
                return;
            }

            var form = document.createElement( 'form' );
            form.method = 'post';
            form.action = button.getAttribute( 'data-action-url' );

            addField( form, 'action', 'wctf_fazercards_giftcard_purchase_item' );
            addField( form, 'order_id', button.getAttribute( 'data-order-id' ) || '' );
            addField( form, 'item_id', button.getAttribute( 'data-item-id' ) || '' );
            addField( form, 'nonce', button.getAttribute( 'data-nonce' ) || '' );
            addField( form, 'purchase_confirmed', '1' );
            addField( form, 'purchase_confirmation_text', confirmation.value );

            document.body.appendChild( form );
            form.submit();
        } );

        document.addEventListener( 'click', function( event ) {
            var button = event.target.closest
                ? event.target.closest( '.wctf-fazercards-giftcard-reveal-button' )
                : null;

            if ( ! button ) {
                return;
            }

            var confirmed = document.getElementById( button.getAttribute( 'data-confirmed-id' ) );
            var confirmation = document.getElementById( button.getAttribute( 'data-confirmation-id' ) );

            event.preventDefault();

            if ( ! confirmed || ! confirmed.checked ) {
                if ( confirmed && confirmed.reportValidity ) {
                    confirmed.required = true;
                    confirmed.reportValidity();
                }
                return;
            }

            if ( ! confirmation || 'REVEAL' !== confirmation.value ) {
                if ( confirmation && confirmation.setCustomValidity && confirmation.reportValidity ) {
                    confirmation.setCustomValidity( '<?php echo esc_js( __( 'Type REVEAL exactly to confirm.', 'wc-topup-fields' ) ); ?>' );
                    confirmation.reportValidity();
                    confirmation.addEventListener( 'input', function clearRevealError() {
                        confirmation.setCustomValidity( '' );
                        confirmation.removeEventListener( 'input', clearRevealError );
                    } );
                }
                return;
            }

            var form = document.createElement( 'form' );
            form.method = 'post';
            form.action = button.getAttribute( 'data-action-url' );

            addField( form, 'action', 'wctf_fazercards_giftcard_reveal_item' );
            addField( form, 'order_id', button.getAttribute( 'data-order-id' ) || '' );
            addField( form, 'item_id', button.getAttribute( 'data-item-id' ) || '' );
            addField( form, 'nonce', button.getAttribute( 'data-nonce' ) || '' );
            addField( form, 'reveal_confirmed', '1' );
            addField( form, 'reveal_confirmation_text', confirmation.value );

            document.body.appendChild( form );
            form.submit();
        } );

        document.addEventListener( 'click', function( event ) {
            var button = event.target.closest
                ? event.target.closest( '.wctf-fazercards-giftcard-refresh-button' )
                : null;

            if ( ! button ) {
                return;
            }

            var confirmed = document.getElementById( button.getAttribute( 'data-confirmed-id' ) );
            var confirmation = document.getElementById( button.getAttribute( 'data-confirmation-id' ) );

            event.preventDefault();

            if ( ! confirmed || ! confirmed.checked ) {
                if ( confirmed && confirmed.reportValidity ) {
                    confirmed.required = true;
                    confirmed.reportValidity();
                }
                return;
            }

            if ( ! confirmation || 'REFRESH' !== confirmation.value ) {
                if ( confirmation && confirmation.setCustomValidity && confirmation.reportValidity ) {
                    confirmation.setCustomValidity( '<?php echo esc_js( __( 'Type REFRESH exactly to confirm.', 'wc-topup-fields' ) ); ?>' );
                    confirmation.reportValidity();
                    confirmation.addEventListener( 'input', function clearRefreshError() {
                        confirmation.setCustomValidity( '' );
                        confirmation.removeEventListener( 'input', clearRefreshError );
                    } );
                }
                return;
            }

            var form = document.createElement( 'form' );
            form.method = 'post';
            form.action = button.getAttribute( 'data-action-url' );

            addField( form, 'action', 'wctf_fazercards_giftcard_refresh_order' );
            addField( form, 'order_id', button.getAttribute( 'data-order-id' ) || '' );
            addField( form, 'item_id', button.getAttribute( 'data-item-id' ) || '' );
            addField( form, 'nonce', button.getAttribute( 'data-nonce' ) || '' );
            addField( form, 'refresh_confirmed', '1' );
            addField( form, 'refresh_confirmation_text', confirmation.value );

            document.body.appendChild( form );
            form.submit();
        } );

        document.addEventListener( 'click', function( event ) {
            var button = event.target.closest
                ? event.target.closest( '.wctf-fazercards-giftcard-auto-refresh-button' )
                : null;

            if ( ! button ) {
                return;
            }

            event.preventDefault();

            var form = document.createElement( 'form' );
            form.method = 'post';
            form.action = button.getAttribute( 'data-action-url' );

            addField( form, 'action', 'wctf_fazercards_giftcard_start_auto_refresh_retry' );
            addField( form, 'order_id', button.getAttribute( 'data-order-id' ) || '' );
            addField( form, 'item_id', button.getAttribute( 'data-item-id' ) || '' );
            addField( form, 'nonce', button.getAttribute( 'data-nonce' ) || '' );

            document.body.appendChild( form );
            form.submit();
        } );
    }() );
    </script>
    <?php
}

/**
 * Resolve a WooCommerce order from classic or HPOS meta box context.
 *
 * @param mixed $post_or_order_object Order screen object.
 * @return WC_Order|false
 */
function wctf_get_fazercards_giftcard_order_from_screen( $post_or_order_object ) {
    if ( $post_or_order_object instanceof WC_Order ) {
        return $post_or_order_object;
    }

    if ( $post_or_order_object instanceof WP_Post ) {
        return wc_get_order( $post_or_order_object->ID );
    }

    return false;
}

/**
 * Build server-side purchase readiness for one Gift Card order item.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order line item.
 * @param int                   $item_id    WooCommerce order item ID.
 * @param bool                  $ignore_lock Whether to ignore a lock owned by the current request.
 * @return array
 */
function wctf_get_fazercards_giftcard_purchase_readiness( $order, $item, $item_id, $ignore_lock = false ) {
    $result = array(
        'ready'            => false,
        'reasons'          => array(),
        'warnings'         => array(),
        'snapshot'         => array(),
        'payload'          => array(),
        'quantity'         => null,
        'status'           => 'not_purchased',
        'encryption_ready' => false,
        'has_secret'       => false,
    );

    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        $result['reasons'][] = __( 'A valid WooCommerce order item is required.', 'wc-topup-fields' );
        return $result;
    }

    $order_id = absint( $order->get_id() );
    $item_id  = absint( $item_id );
    $owned    = $order->get_item( $item_id );

    if ( ! $owned instanceof WC_Order_Item_Product || absint( $owned->get_id() ) !== absint( $item->get_id() ) ) {
        $result['reasons'][] = __( 'The order item does not belong to this order.', 'wc-topup-fields' );
        return $result;
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        $result['reasons'][] = __( 'WooCommerce management permission is required.', 'wc-topup-fields' );
    }

    if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
        $result['reasons'][] = __( 'Permission to edit this order is required.', 'wc-topup-fields' );
    }

    $required_helpers = array(
        'wctf_fazercards_giftcard_crypto_status',
        'wctf_fazercards_giftcard_store_secret_payload',
        'wctf_fazercards_giftcard_has_secret_payload',
        'wctf_get_fazercards_giftcard_order_item_snapshot',
    );

    foreach ( $required_helpers as $helper ) {
        if ( ! function_exists( $helper ) ) {
            $result['reasons'][] = __( 'Required Gift Card encrypted-storage helpers are unavailable.', 'wc-topup-fields' );
            break;
        }
    }

    if ( function_exists( 'wctf_fazercards_giftcard_crypto_status' ) ) {
        $crypto_status               = wctf_fazercards_giftcard_crypto_status();
        $result['encryption_ready'] = ! empty( $crypto_status['ready'] );

        if ( empty( $crypto_status['ready'] ) ) {
            $result['reasons'][] = __( 'Encrypted Gift Card secret storage is not ready.', 'wc-topup-fields' );
        }
    }

    try {
        $config = function_exists( 'wctf_config' ) ? wctf_config() : array();
    } catch ( Throwable $config_throwable ) {
        unset( $config_throwable );
        $config = array();
    }

    if (
        ! isset( $config['api_url'], $config['api_key'] )
        || ! is_scalar( $config['api_url'] )
        || ! is_scalar( $config['api_key'] )
        || '' === trim( (string) $config['api_url'] )
        || '' === trim( (string) $config['api_key'] )
    ) {
        $result['reasons'][] = __( 'The FazerCards API URL and API key must be configured.', 'wc-topup-fields' );
    }

    if ( 'giftcard' !== sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) ) ) {
        $result['reasons'][] = __( 'The order item is not a snapshotted Gift Card item.', 'wc-topup-fields' );
        return $result;
    }

    $snapshot = function_exists( 'wctf_get_fazercards_giftcard_order_item_snapshot' )
        ? wctf_get_fazercards_giftcard_order_item_snapshot( $item )
        : array();

    $result['snapshot'] = is_array( $snapshot ) ? $snapshot : array();

    if ( empty( $snapshot ) ) {
        $result['reasons'][] = __( 'The Gift Card order-item snapshot is missing.', 'wc-topup-fields' );
    }

    if ( empty( $snapshot['snapshot_created_at'] ) ) {
        $result['reasons'][] = __( 'The Gift Card snapshot creation timestamp is missing.', 'wc-topup-fields' );
    }

    $display_only_snapshot_fields = array(
        'offer_name' => __( 'Gift Card name is not available in the snapshot.', 'wc-topup-fields' ),
        'price_usd'  => __( 'Gift Card price is not available in the snapshot.', 'wc-topup-fields' ),
        'currency'   => __( 'Gift Card currency is not available in the snapshot.', 'wc-topup-fields' ),
        'region'     => __( 'Gift Card region is not available in the snapshot.', 'wc-topup-fields' ),
    );

    foreach ( $display_only_snapshot_fields as $field_key => $warning ) {
        if (
            ! isset( $snapshot[ $field_key ] )
            || ! is_scalar( $snapshot[ $field_key ] )
            || '' === sanitize_text_field( (string) $snapshot[ $field_key ] )
        ) {
            $result['warnings'][] = $warning;
        }
    }

    $category_id = isset( $snapshot['category_id'] ) && is_scalar( $snapshot['category_id'] )
        ? sanitize_text_field( (string) $snapshot['category_id'] )
        : '';
    $card_id = isset( $snapshot['card_id'] ) && is_scalar( $snapshot['card_id'] )
        ? sanitize_text_field( (string) $snapshot['card_id'] )
        : '';

    if ( '' === $category_id ) {
        $result['reasons'][] = __( 'The Gift Card category ID is missing.', 'wc-topup-fields' );
    }

    if ( '' === $card_id ) {
        $result['reasons'][] = __( 'The Gift Card card ID is missing.', 'wc-topup-fields' );
    }

    $quantity = wctf_normalize_fazercards_giftcard_purchase_quantity( $item->get_quantity() );

    if ( null === $quantity || 1 > $quantity || 100 < $quantity ) {
        $result['reasons'][] = __( 'Gift Card quantity must be a strict integer from 1 to 100.', 'wc-topup-fields' );
    } else {
        $result['quantity'] = $quantity;
    }

    $min_quantity = isset( $snapshot['min_order_quantity'] ) && is_scalar( $snapshot['min_order_quantity'] )
        ? trim( (string) $snapshot['min_order_quantity'] )
        : '';
    $max_quantity = isset( $snapshot['max_order_quantity'] ) && is_scalar( $snapshot['max_order_quantity'] )
        ? trim( (string) $snapshot['max_order_quantity'] )
        : '';
    $min_numeric  = '' !== $min_quantity && is_numeric( $min_quantity )
        ? (float) $min_quantity
        : null;
    $max_numeric  = '' !== $max_quantity && is_numeric( $max_quantity )
        ? (float) $max_quantity
        : null;

    if ( '' !== $min_quantity && ( null === $min_numeric || ! is_finite( $min_numeric ) || 0 > $min_numeric ) ) {
        $result['reasons'][] = __( 'The Gift Card minimum quantity is invalid and blocks purchase.', 'wc-topup-fields' );
    }

    if ( '' !== $max_quantity && ( null === $max_numeric || ! is_finite( $max_numeric ) || 0 > $max_numeric ) ) {
        $result['reasons'][] = __( 'The Gift Card maximum quantity is invalid and blocks purchase.', 'wc-topup-fields' );
    }

    if ( null !== $quantity && null !== $min_numeric && $quantity < $min_numeric ) {
        $result['reasons'][] = __( 'Gift Card quantity is below the snapshotted minimum.', 'wc-topup-fields' );
    }

    if ( null !== $quantity && null !== $max_numeric && $quantity > $max_numeric ) {
        $result['reasons'][] = __( 'Gift Card quantity is above the snapshotted maximum.', 'wc-topup-fields' );
    }

    if ( null !== $min_numeric && null !== $max_numeric && $min_numeric > $max_numeric ) {
        $result['reasons'][] = __( 'The snapshotted Gift Card quantity limits are inconsistent.', 'wc-topup-fields' );
    }

    $result['payload'] = array(
        'category_id' => $category_id,
        'card_id'     => $card_id,
        'quantity'    => null === $quantity ? 0 : $quantity,
    );
    $result['status'] = wctf_normalize_fazercards_giftcard_purchase_status(
        $item->get_meta( '_wctf_fazer_giftcard_purchase_status', true )
    );

    if ( function_exists( 'wctf_fazercards_giftcard_has_secret_payload' ) ) {
        $result['has_secret'] = wctf_fazercards_giftcard_has_secret_payload( $item );
    }

    if ( $result['has_secret'] ) {
        $result['reasons'][] = __( 'An encrypted Gift Card payload already exists; another purchase is blocked.', 'wc-topup-fields' );
    }

    if ( ! in_array( $result['status'], array( 'not_purchased', 'failed' ), true ) ) {
        $result['reasons'][] = __( 'The current Gift Card purchase status does not permit another purchase.', 'wc-topup-fields' );
    }

    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string(
        $item->get_meta( '_wctf_fazer_giftcard_remote_order_id', true ),
        191
    );

    if ( '' !== $remote_order_id ) {
        $result['reasons'][] = __( 'A remote order ID already exists; recovery is required instead of another purchase.', 'wc-topup-fields' );
    }

    if (
        ! $ignore_lock
        && wctf_is_fazercards_giftcard_purchase_lock_active( $order_id, $item_id )
    ) {
        $result['reasons'][] = __( 'A Gift Card purchase lock is currently active for this item.', 'wc-topup-fields' );
    }

    $expected_key = wctf_build_fazercards_giftcard_idempotency_key(
        $order,
        $item_id,
        $result['payload']
    );
    $stored_key   = wctf_limit_fazercards_giftcard_purchase_string(
        $item->get_meta( '_wctf_fazer_giftcard_idempotency_key', true ),
        255
    );

    if ( '' === $expected_key ) {
        $result['reasons'][] = __( 'A stable Gift Card idempotency key could not be prepared.', 'wc-topup-fields' );
    } elseif ( '' !== $stored_key && ! hash_equals( $expected_key, $stored_key ) ) {
        $result['reasons'][] = __( 'The stored idempotency key does not match the snapshotted purchase payload.', 'wc-topup-fields' );
    }

    if ( 'failed' === $result['status'] && '' === $stored_key ) {
        $result['reasons'][] = __( 'The failed purchase has no stored idempotency key and cannot be retried safely.', 'wc-topup-fields' );
    }

    $result['reasons'] = array_values( array_unique( $result['reasons'] ) );
    $result['warnings'] = array_values( array_unique( $result['warnings'] ) );
    $result['ready']   = empty( $result['reasons'] );

    return $result;
}

/**
 * Normalize a WooCommerce quantity without truncating fractional values.
 *
 * @param mixed $quantity Raw WooCommerce quantity.
 * @return int|null
 */
function wctf_normalize_fazercards_giftcard_purchase_quantity( $quantity ) {
    if ( is_int( $quantity ) ) {
        return $quantity;
    }

    if ( is_float( $quantity ) ) {
        return is_finite( $quantity ) && floor( $quantity ) === $quantity
            ? (int) $quantity
            : null;
    }

    if (
        is_string( $quantity )
        && 1 === preg_match( '/\A(?:0|[1-9][0-9]*)\z/D', $quantity )
    ) {
        return (int) $quantity;
    }

    return null;
}

/**
 * Normalize a Gift Card purchase status, failing unknown values closed.
 *
 * @param mixed $status Raw status.
 * @return string
 */
function wctf_normalize_fazercards_giftcard_purchase_status( $status ) {
    $status = is_scalar( $status ) ? sanitize_key( (string) $status ) : '';

    if ( '' === $status ) {
        return 'not_purchased';
    }

    $allowed = array(
        'not_purchased',
        'purchasing',
        'pending',
        'pending_review',
        'purchased',
        'failed',
        'failed_uncertain',
        'storage_failed',
    );

    return in_array( $status, $allowed, true ) ? $status : 'pending_review';
}

/**
 * Return a readable Gift Card purchase status label.
 *
 * @param mixed $status Raw status.
 * @return string
 */
function wctf_get_fazercards_giftcard_purchase_status_label( $status ) {
    $labels = array(
        'not_purchased'   => __( 'Not purchased', 'wc-topup-fields' ),
        'purchasing'      => __( 'Purchasing', 'wc-topup-fields' ),
        'pending'         => __( 'Pending', 'wc-topup-fields' ),
        'pending_review'  => __( 'Pending review', 'wc-topup-fields' ),
        'purchased'       => __( 'Purchased (stored, not delivered)', 'wc-topup-fields' ),
        'failed'          => __( 'Failed', 'wc-topup-fields' ),
        'failed_uncertain' => __( 'Failed — outcome uncertain', 'wc-topup-fields' ),
        'storage_failed'  => __( 'Encrypted storage failed', 'wc-topup-fields' ),
    );
    $status = wctf_normalize_fazercards_giftcard_purchase_status( $status );

    return $labels[ $status ];
}

/**
 * Handle one explicitly confirmed REAL Gift Card purchase.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_purchase_item() {
    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
        : 0;
    $item_id = isset( $_POST['item_id'] ) && is_scalar( $_POST['item_id'] )
        ? absint( wp_unslash( $_POST['item_id'] ) )
        : 0;

    if ( 1 > $order_id || 1 > $item_id ) {
        wp_die(
            esc_html__( 'A valid order and order item are required.', 'wc-topup-fields' ),
            esc_html__( 'Invalid Gift Card purchase', 'wc-topup-fields' ),
            array( 'response' => 400 )
        );
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die(
            esc_html__( 'WooCommerce management permission is required.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $order = wc_get_order( $order_id );

    if ( ! $order instanceof WC_Order ) {
        wp_die(
            esc_html__( 'The WooCommerce order could not be found.', 'wc-topup-fields' ),
            esc_html__( 'Order not found', 'wc-topup-fields' ),
            array( 'response' => 404 )
        );
    }

    if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
        wp_die(
            esc_html__( 'You are not allowed to edit this WooCommerce order.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
        : '';

    if (
        '' === $nonce
        || ! wp_verify_nonce(
            $nonce,
            'wctf_purchase_fazercards_giftcard_' . $order_id . '_' . $item_id
        )
    ) {
        wctf_finish_fazercards_giftcard_purchase_action(
            $order,
            array(
                'result_type' => 'error',
                'item_id'     => $item_id,
                'status'      => 'not_purchased',
                'message'     => __( 'The Gift Card purchase request reached the server, but the security nonce was invalid. No Gift Card was purchased.', 'wc-topup-fields' ),
            )
        );
    }

    $item = $order->get_item( $item_id );

    if ( ! $item instanceof WC_Order_Item_Product ) {
        wctf_finish_fazercards_giftcard_purchase_action(
            $order,
            array(
                'result_type' => 'error',
                'item_id'     => $item_id,
                'status'      => 'not_purchased',
                'message'     => __( 'The selected order item does not belong to this order.', 'wc-topup-fields' ),
            )
        );
    }

    $confirmed = isset( $_POST['purchase_confirmed'] ) && is_string( $_POST['purchase_confirmed'] )
        ? sanitize_text_field( wp_unslash( $_POST['purchase_confirmed'] ) )
        : '';
    $confirmation_text = isset( $_POST['purchase_confirmation_text'] ) && is_string( $_POST['purchase_confirmation_text'] )
        ? wp_unslash( $_POST['purchase_confirmation_text'] )
        : '';

    if ( '1' !== $confirmed || 'PURCHASE' !== $confirmation_text ) {
        wctf_finish_fazercards_giftcard_purchase_action(
            $order,
            array(
                'result_type' => 'error',
                'item_id'     => $item_id,
                'status'      => wctf_normalize_fazercards_giftcard_purchase_status(
                    $item->get_meta( '_wctf_fazer_giftcard_purchase_status', true )
                ),
                'message'     => __( 'Both the checkbox and exact PURCHASE confirmation are required. No Gift Card was purchased.', 'wc-topup-fields' ),
            )
        );
    }

    $readiness = wctf_get_fazercards_giftcard_purchase_readiness( $order, $item, $item_id );

    if ( empty( $readiness['ready'] ) ) {
        $message = ! empty( $readiness['reasons'][0] )
            ? sanitize_text_field( (string) $readiness['reasons'][0] )
            : __( 'This Gift Card order item is not ready for a real purchase.', 'wc-topup-fields' );

        wctf_finish_fazercards_giftcard_purchase_action(
            $order,
            array(
                'result_type' => 'error',
                'item_id'     => $item_id,
                'status'      => isset( $readiness['status'] ) ? $readiness['status'] : 'not_purchased',
                'message'     => $message,
            )
        );
    }

    $lock_token = wctf_acquire_fazercards_giftcard_purchase_lock( $order_id, $item_id );

    if ( is_wp_error( $lock_token ) ) {
        wctf_finish_fazercards_giftcard_purchase_action(
            $order,
            array(
                'result_type' => 'warning',
                'item_id'     => $item_id,
                'status'      => isset( $readiness['status'] ) ? $readiness['status'] : 'not_purchased',
                'message'     => $lock_token->get_error_message(),
            )
        );
    }

    $attempted              = false;
    $remote_success_received = false;
    $fast_settle_pending    = false;
    $result                 = array(
        'result_type' => 'error',
        'item_id'     => $item_id,
        'status'      => 'not_purchased',
        'message'     => __( 'The Gift Card purchase was not completed.', 'wc-topup-fields' ),
    );

    try {
        $item                  = new WC_Order_Item_Product( $item_id );
        $post_lock_readiness   = wctf_get_fazercards_giftcard_purchase_readiness(
            $order,
            $item,
            $item_id,
            true
        );
        $state_changed = isset( $post_lock_readiness['status'], $readiness['status'] )
            && $post_lock_readiness['status'] !== $readiness['status'];
        $payload_changed = ! isset( $post_lock_readiness['payload'], $readiness['payload'] )
            || ! is_array( $post_lock_readiness['payload'] )
            || ! is_array( $readiness['payload'] )
            || $post_lock_readiness['payload'] !== $readiness['payload'];

        if ( empty( $post_lock_readiness['ready'] ) || $state_changed || $payload_changed ) {
            $result = array(
                'result_type' => 'warning',
                'item_id'     => $item_id,
                'status'      => isset( $post_lock_readiness['status'] )
                    ? $post_lock_readiness['status']
                    : 'not_purchased',
                'message'     => $state_changed || $payload_changed
                    ? __( 'The Gift Card item changed before the lock was acquired. No remote purchase was attempted.', 'wc-topup-fields' )
                    : ( ! empty( $post_lock_readiness['reasons'][0] )
                        ? sanitize_text_field( (string) $post_lock_readiness['reasons'][0] )
                        : __( 'The Gift Card item is no longer ready. No remote purchase was attempted.', 'wc-topup-fields' ) ),
            );
        } else {
            $payload     = $post_lock_readiness['payload'];
            $idempotency = wctf_persist_fazercards_giftcard_idempotency_key(
                $order,
                $item,
                $item_id,
                $payload
            );

            if ( is_wp_error( $idempotency ) ) {
                $result['message'] = $idempotency->get_error_message();
            } else {
                $item            = $idempotency['item'];
                $idempotency_key = $idempotency['key'];
                $item            = wctf_save_fazercards_giftcard_purchase_state(
                    $item,
                    'purchasing',
                    '',
                    '',
                    '',
                    false
                );

                if ( is_wp_error( $item ) ) {
                    $result['message'] = $item->get_error_message();
                } else {
                    wctf_add_fazercards_giftcard_purchase_note(
                        $order,
                        'started',
                        $item_id,
                        '',
                        '',
                        ''
                    );

                if ( ! class_exists( 'WCTF_FazerCards_GiftCards_Provider' ) ) {
                    throw new RuntimeException( 'The Gift Card provider is unavailable.' );
                }

                $provider  = new WCTF_FazerCards_GiftCards_Provider();
                $attempted = true;
                $response  = $provider->create_order(
                    $payload['category_id'],
                    $payload['card_id'],
                    $payload['quantity'],
                    $idempotency_key
                );

                if ( is_array( $response ) ) {
                    $response['raw']     = '';
                    $response['headers'] = array();
                }

                $parsed = wctf_parse_fazercards_giftcard_purchase_response( $response, $provider );
                unset( $provider, $response );

                if ( empty( $parsed['success'] ) ) {
                    $failure_status = ! empty( $parsed['uncertain'] )
                        ? 'failed_uncertain'
                        : 'failed';
                    $safe_error     = wctf_sanitize_fazercards_giftcard_purchase_error(
                        isset( $parsed['message'] ) ? $parsed['message'] : '',
                        500
                    );

                    $item = wctf_save_fazercards_giftcard_purchase_state(
                        $item,
                        $failure_status,
                        '',
                        '',
                        $safe_error,
                        false
                    );

                    wctf_add_fazercards_giftcard_purchase_note(
                        $order,
                        'failed_uncertain' === $failure_status ? 'uncertain' : 'failed',
                        $item_id,
                        '',
                        '',
                        $safe_error
                    );

                    $result = array(
                        'result_type' => 'failed_uncertain' === $failure_status ? 'warning' : 'error',
                        'item_id'     => $item_id,
                        'status'      => $failure_status,
                        'message'     => $safe_error,
                    );
                } else {
                    $remote_success_received = true;
                    $remote_order = $parsed['order'];
                    $safe_order   = wctf_get_fazercards_giftcard_safe_order_result( $remote_order );

                    try {
                        $stored = wctf_fazercards_giftcard_store_secret_payload(
                            $item,
                            $remote_order,
                            $parsed['http_status']
                        );
                    } catch ( Throwable $storage_throwable ) {
                        unset( $storage_throwable );
                        $stored = new WP_Error(
                            'wctf_giftcard_secret_storage_failed',
                            __( 'The encrypted Gift Card payload could not be stored safely.', 'wc-topup-fields' )
                        );
                    }

                    unset( $remote_order );

                    if ( is_wp_error( $stored ) ) {
                        $storage_error = __( 'The remote purchase may have succeeded, but encrypted storage failed. Do not purchase again; recovery is required.', 'wc-topup-fields' );
                        $saved_item    = wctf_save_fazercards_giftcard_purchase_state(
                            $item,
                            'storage_failed',
                            $safe_order['remote_order_id'],
                            $safe_order['remote_status'],
                            $storage_error,
                            false
                        );

                        if ( ! is_wp_error( $saved_item ) ) {
                            $item = $saved_item;
                        }

                        wctf_add_fazercards_giftcard_purchase_note(
                            $order,
                            'storage_failed',
                            $item_id,
                            $safe_order['remote_order_id'],
                            $safe_order['remote_status'],
                            $storage_error
                        );

                        $result = array(
                            'result_type' => 'error',
                            'item_id'     => $item_id,
                            'status'      => 'storage_failed',
                            'message'     => $storage_error,
                        );
                    } else {
                        $final_status = wctf_map_fazercards_giftcard_purchase_status( $safe_order );
                        $saved_item   = wctf_save_fazercards_giftcard_purchase_state(
                            $item,
                            $final_status,
                            $safe_order['remote_order_id'],
                            $safe_order['remote_status'],
                            '',
                            true
                        );

                        if ( is_wp_error( $saved_item ) ) {
                            $final_status = 'pending_review';
                            $status_error = __( 'The encrypted Gift Card payload was stored, but the purchase status requires manual review.', 'wc-topup-fields' );
                            $item->update_meta_data( '_wctf_fazer_giftcard_purchase_status', $final_status );
                            $item->update_meta_data( '_wctf_fazer_giftcard_last_error', $status_error );

                            if ( '' !== $safe_order['remote_order_id'] ) {
                                $item->update_meta_data(
                                    '_wctf_fazer_giftcard_remote_order_id',
                                    $safe_order['remote_order_id']
                                );
                            }

                            if ( '' !== $safe_order['remote_status'] ) {
                                $item->update_meta_data(
                                    '_wctf_fazer_giftcard_remote_status',
                                    $safe_order['remote_status']
                                );
                            }

                            $item->update_meta_data(
                                '_wctf_fazer_giftcard_purchased_at',
                                sanitize_text_field( current_time( 'mysql', true ) )
                            );
                            $item->save_meta_data();
                            $result_type = 'warning';
                            $message     = $status_error;
                        } else {
                            $item        = $saved_item;
                            $result_type = 'purchased' === $final_status ? 'success' : 'warning';
                            $message     = wctf_get_fazercards_giftcard_purchase_result_message(
                                $final_status,
                                $safe_order['remote_order_id']
                            );
                        }

                        wctf_add_fazercards_giftcard_purchase_note(
                            $order,
                            $final_status,
                            $item_id,
                            $safe_order['remote_order_id'],
                            $safe_order['remote_status'],
                            isset( $status_error ) ? $status_error : ''
                        );

                        $result = array(
                            'result_type' => $result_type,
                            'item_id'     => $item_id,
                            'status'      => $final_status,
                            'message'     => $message,
                        );

                        if ( wctf_should_fazercards_giftcard_auto_refresh_after_purchase( $safe_order ) ) {
                            $auto_refresh_result = wctf_fazercards_giftcard_refresh_remote_order_item(
                                $order,
                                $item,
                                $item_id,
                                $safe_order['remote_order_id'],
                                'auto_after_purchase'
                            );

                            if (
                                is_array( $auto_refresh_result )
                                && ! empty( $auto_refresh_result['status'] )
                                && in_array(
                                    isset( $auto_refresh_result['type'] ) ? $auto_refresh_result['type'] : '',
                                    array( 'success', 'warning' ),
                                    true
                                )
                            ) {
                                $final_status = wctf_normalize_fazercards_giftcard_purchase_status(
                                    $auto_refresh_result['status']
                                );
                                $result       = array(
                                    'result_type' => 'purchased' === $final_status ? 'success' : 'warning',
                                    'item_id'     => $item_id,
                                    'status'      => $final_status,
                                    'message'     => isset( $auto_refresh_result['message'] )
                                        ? $auto_refresh_result['message']
                                        : $message,
                                );
                            }

                            unset( $auto_refresh_result );
                        }

                        $item = new WC_Order_Item_Product( $item_id );

                        if ( wctf_is_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
                            wctf_maybe_mark_fazercards_giftcard_ready_to_deliver(
                                $order,
                                $item,
                                $item_id
                            );
                        } else {
                            $fast_settle_pending = true;
                        }
                    }

                    unset( $safe_order, $stored );
                }

                    unset( $parsed, $response );
                }
            }
        }
    } catch ( Throwable $throwable ) {
        unset( $throwable );

        $has_secret = function_exists( 'wctf_fazercards_giftcard_has_secret_payload' )
            && $item instanceof WC_Order_Item_Product
            && wctf_fazercards_giftcard_has_secret_payload( $item );
        if ( $has_secret ) {
            $failure_status = 'pending_review';
            $safe_error     = __( 'An encrypted Gift Card payload exists, but the purchase result requires manual review.', 'wc-topup-fields' );
        } elseif ( $remote_success_received ) {
            $failure_status = 'storage_failed';
            $safe_error     = __( 'The remote purchase may have succeeded, but encrypted storage failed. Do not purchase again; recovery is required.', 'wc-topup-fields' );
        } elseif ( $attempted ) {
            $failure_status = 'failed_uncertain';
            $safe_error     = __( 'The remote Gift Card purchase outcome is uncertain. Do not purchase again; recovery is required.', 'wc-topup-fields' );
        } else {
            $failure_status = 'failed';
            $safe_error     = __( 'The Gift Card purchase could not be started safely.', 'wc-topup-fields' );
        }

        $catch_remote_order_id = isset( $safe_order['remote_order_id'] )
            ? wctf_limit_fazercards_giftcard_purchase_string( $safe_order['remote_order_id'], 191 )
            : '';
        $catch_remote_status = isset( $safe_order['remote_status'] )
            ? wctf_limit_fazercards_giftcard_purchase_string( $safe_order['remote_status'], 100 )
            : '';

        if ( $item instanceof WC_Order_Item_Product ) {
            $saved_item = wctf_save_fazercards_giftcard_purchase_state(
                $item,
                $failure_status,
                $catch_remote_order_id,
                $catch_remote_status,
                $safe_error,
                false
            );

            if ( ! is_wp_error( $saved_item ) ) {
                $item = $saved_item;
            }
        }

        wctf_add_fazercards_giftcard_purchase_note(
            $order,
            $has_secret
                ? 'pending_review'
                : ( $remote_success_received ? 'storage_failed' : ( $attempted ? 'uncertain' : 'failed' ) ),
            $item_id,
            $catch_remote_order_id,
            $catch_remote_status,
            $safe_error
        );

        $result = array(
            'result_type' => in_array( $failure_status, array( 'failed', 'storage_failed' ), true )
                ? 'error'
                : 'warning',
            'item_id'     => $item_id,
            'status'      => $failure_status,
            'message'     => $safe_error,
        );
    } finally {
        unset( $response, $parsed );
        wctf_release_fazercards_giftcard_purchase_lock(
            $order_id,
            $item_id,
            $lock_token
        );
    }

    if ( $fast_settle_pending ) {
        $item = new WC_Order_Item_Product( $item_id );
        wctf_maybe_dispatch_fazercards_giftcard_fast_settle(
            $order,
            $item,
            $item_id,
            'manual_purchase'
        );
    }

    wctf_finish_fazercards_giftcard_purchase_action( $order, $result );
}

/**
 * Handle one explicitly confirmed Gift Card remote order refresh.
 *
 * This action fetches an existing remote order and replaces only the encrypted
 * stored payload. It never creates a new Gift Card purchase.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_refresh_order() {
    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
        : 0;
    $item_id  = isset( $_POST['item_id'] ) && is_scalar( $_POST['item_id'] )
        ? absint( wp_unslash( $_POST['item_id'] ) )
        : 0;

    if ( 1 > $order_id || 1 > $item_id ) {
        wp_die(
            esc_html__( 'A valid order and order item are required.', 'wc-topup-fields' ),
            esc_html__( 'Invalid Gift Card refresh', 'wc-topup-fields' ),
            array( 'response' => 400 )
        );
    }

    $request_method = isset( $_SERVER['REQUEST_METHOD'] ) && is_scalar( $_SERVER['REQUEST_METHOD'] )
        ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
        : '';

    if ( 'POST' !== $request_method ) {
        wp_die(
            esc_html__( 'Gift Card remote refresh requires a POST request.', 'wc-topup-fields' ),
            esc_html__( 'Invalid Gift Card refresh', 'wc-topup-fields' ),
            array( 'response' => 405 )
        );
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die(
            esc_html__( 'WooCommerce management permission is required.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $order = wc_get_order( $order_id );

    if ( ! $order instanceof WC_Order ) {
        wp_die(
            esc_html__( 'The WooCommerce order could not be found.', 'wc-topup-fields' ),
            esc_html__( 'Order not found', 'wc-topup-fields' ),
            array( 'response' => 404 )
        );
    }

    if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
        wp_die(
            esc_html__( 'You are not allowed to edit this WooCommerce order.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
        : '';

    if (
        '' === $nonce
        || ! wp_verify_nonce(
            $nonce,
            'wctf_refresh_fazercards_giftcard_' . $order_id . '_' . $item_id
        )
    ) {
        wctf_finish_fazercards_giftcard_refresh_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'The Gift Card refresh request reached the server, but the security nonce was invalid. No remote order was refreshed.', 'wc-topup-fields' ),
            )
        );
    }

    $item = $order->get_item( $item_id );

    if ( ! $item instanceof WC_Order_Item_Product ) {
        wctf_finish_fazercards_giftcard_refresh_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'The selected order item does not belong to this order.', 'wc-topup-fields' ),
            )
        );
    }

    $confirmed = isset( $_POST['refresh_confirmed'] ) && is_string( $_POST['refresh_confirmed'] )
        ? sanitize_text_field( wp_unslash( $_POST['refresh_confirmed'] ) )
        : '';
    $confirmation_text = isset( $_POST['refresh_confirmation_text'] ) && is_string( $_POST['refresh_confirmation_text'] )
        ? wp_unslash( $_POST['refresh_confirmation_text'] )
        : '';

    if ( '1' !== $confirmed || 'REFRESH' !== $confirmation_text ) {
        wctf_finish_fazercards_giftcard_refresh_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'Both the checkbox and exact REFRESH confirmation are required. No remote order was refreshed.', 'wc-topup-fields' ),
            )
        );
    }

    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string(
        $item->get_meta( '_wctf_fazer_giftcard_remote_order_id', true ),
        191
    );

    if ( 1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id ) ) {
        wctf_finish_fazercards_giftcard_refresh_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'A valid stored FazerCards remote order ID is required before refresh.', 'wc-topup-fields' ),
            )
        );
    }

    $result = wctf_fazercards_giftcard_refresh_remote_order_item(
        $order,
        $item,
        $item_id,
        $remote_order_id,
        'manual'
    );

    wctf_maybe_update_fazercards_giftcard_fulfillment_after_refresh(
        $order,
        $item_id,
        false
    );

    wctf_finish_fazercards_giftcard_refresh_action( $order, $result );
}

/**
 * Schedule or resume Gift Card auto-refresh retry from the admin order screen.
 *
 * This action only schedules a read-only future refresh. It never calls the
 * remote API directly.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_start_auto_refresh_retry() {
    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
        : 0;
    $item_id  = isset( $_POST['item_id'] ) && is_scalar( $_POST['item_id'] )
        ? absint( wp_unslash( $_POST['item_id'] ) )
        : 0;

    if ( 1 > $order_id || 1 > $item_id ) {
        wp_die(
            esc_html__( 'A valid order and order item are required.', 'wc-topup-fields' ),
            esc_html__( 'Invalid Gift Card auto refresh', 'wc-topup-fields' ),
            array( 'response' => 400 )
        );
    }

    $request_method = isset( $_SERVER['REQUEST_METHOD'] ) && is_scalar( $_SERVER['REQUEST_METHOD'] )
        ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
        : '';

    if ( 'POST' !== $request_method ) {
        wp_die(
            esc_html__( 'Gift Card auto refresh scheduling requires a POST request.', 'wc-topup-fields' ),
            esc_html__( 'Invalid Gift Card auto refresh', 'wc-topup-fields' ),
            array( 'response' => 405 )
        );
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die(
            esc_html__( 'WooCommerce management permission is required.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $order = wc_get_order( $order_id );

    if ( ! $order instanceof WC_Order ) {
        wp_die(
            esc_html__( 'The WooCommerce order could not be found.', 'wc-topup-fields' ),
            esc_html__( 'Order not found', 'wc-topup-fields' ),
            array( 'response' => 404 )
        );
    }

    if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
        wp_die(
            esc_html__( 'You are not allowed to edit this WooCommerce order.', 'wc-topup-fields' ),
            esc_html__( 'Access denied', 'wc-topup-fields' ),
            array( 'response' => 403 )
        );
    }

    $nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
        : '';

    if (
        '' === $nonce
        || ! wp_verify_nonce(
            $nonce,
            'wctf_start_fazercards_giftcard_auto_refresh_' . $order_id . '_' . $item_id
        )
    ) {
        wctf_finish_fazercards_giftcard_refresh_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'The Gift Card auto refresh request reached the server, but the security nonce was invalid. No retry was scheduled.', 'wc-topup-fields' ),
            )
        );
    }

    $item = $order->get_item( $item_id );

    if ( ! $item instanceof WC_Order_Item_Product ) {
        wctf_finish_fazercards_giftcard_refresh_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'The selected order item does not belong to this order.', 'wc-topup-fields' ),
            )
        );
    }

    $scheduled = wctf_schedule_fazercards_giftcard_auto_refresh_retry(
        $order,
        $item,
        $item_id,
        60,
        false
    );

    if ( is_wp_error( $scheduled ) ) {
        wctf_finish_fazercards_giftcard_refresh_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => $scheduled->get_error_message(),
            )
        );
    }

    wctf_finish_fazercards_giftcard_refresh_action(
        $order,
        array(
            'type'    => 'success',
            'item_id' => $item_id,
            'message' => __( 'Gift Card auto-refresh retry was scheduled. No remote API call was made by this button.', 'wc-topup-fields' ),
        )
    );
}

/**
 * Refresh one existing remote Gift Card order and replace encrypted payload.
 *
 * This helper never creates a new purchase and never exposes the opaque remote
 * order outside encrypted storage.
 *
 * @param WC_Order              $order           WooCommerce order.
 * @param WC_Order_Item_Product $item            WooCommerce order line item.
 * @param int                   $item_id         WooCommerce order item ID.
 * @param string                $remote_order_id Existing FazerCards remote order ID.
 * @param string                $context         Refresh context.
 * @return array Safe result fields.
 */
function wctf_fazercards_giftcard_refresh_remote_order_item( $order, $item, $item_id, $remote_order_id, $context = 'manual' ) {
    $item_id         = absint( $item_id );
    $order_id        = $order instanceof WC_Order ? absint( $order->get_id() ) : 0;
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );
    $context         = sanitize_key( $context );
    $is_auto_context       = 'auto_after_purchase' === $context;
    $is_background_context = in_array( $context, array( 'auto_retry', 'fast_settle' ), true );

    $result = array(
        'type'            => 'error',
        'item_id'         => $item_id,
        'status'          => '',
        'message'         => __( 'The Gift Card remote order could not be refreshed.', 'wc-topup-fields' ),
        'remote_order_id' => $remote_order_id,
        'remote_status'   => '',
        'codes_count'     => null,
        'attempted'       => false,
    );

    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        $result['message'] = __( 'A valid WooCommerce order item is required before refresh.', 'wc-topup-fields' );
        return $result;
    }

    if ( 1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id ) ) {
        $result['message'] = __( 'A valid stored FazerCards remote order ID is required before refresh.', 'wc-topup-fields' );
        return $result;
    }

    $owned = $order->get_item( $item_id );

    if ( ! $owned instanceof WC_Order_Item_Product || absint( $owned->get_id() ) !== absint( $item->get_id() ) ) {
        $result['message'] = __( 'The selected order item does not belong to this order.', 'wc-topup-fields' );
        return $result;
    }

    $crypto_status = function_exists( 'wctf_fazercards_giftcard_crypto_status' )
        ? wctf_fazercards_giftcard_crypto_status()
        : array();

    if (
        empty( $crypto_status['ready'] )
        || ! function_exists( 'wctf_fazercards_giftcard_store_secret_payload' )
    ) {
        $result['message'] = __( 'Gift Card encryption is not ready, so the remote response cannot be stored safely.', 'wc-topup-fields' );
        return $result;
    }

    if (
        ! $is_auto_context
        && wctf_is_fazercards_giftcard_purchase_lock_active( $order_id, $item_id )
    ) {
        $result['type']    = 'warning';
        $result['message'] = __( 'This Gift Card item is currently being purchased. Wait for that action to finish before refreshing the remote order.', 'wc-topup-fields' );
        return $result;
    }

    $lock_token = wctf_acquire_fazercards_giftcard_refresh_lock( $order_id, $item_id );

    if ( is_wp_error( $lock_token ) ) {
        $result['type']    = 'warning';
        $result['message'] = $lock_token->get_error_message();
        return $result;
    }

    try {
        $item = new WC_Order_Item_Product( $item_id );

        if ( ! class_exists( 'WCTF_FazerCards_GiftCards_Provider' ) ) {
            throw new RuntimeException( 'The Gift Card provider is unavailable.' );
        }

        $provider = new WCTF_FazerCards_GiftCards_Provider();
        $result['attempted'] = true;
        $response = $provider->get_order( $remote_order_id );

        if ( is_array( $response ) ) {
            $response['raw']     = '';
            $response['headers'] = array();
        }

        $parsed = wctf_parse_fazercards_giftcard_refresh_response( $response, $provider );
        unset( $provider, $response );

        if ( empty( $parsed['success'] ) ) {
            $safe_error = wctf_sanitize_fazercards_giftcard_purchase_error(
                isset( $parsed['message'] ) ? $parsed['message'] : '',
                500
            );
            wctf_save_fazercards_giftcard_refresh_error( $item, $safe_error );

            $result['type']    = 'error';
            $result['message'] = '' !== $safe_error
                ? $safe_error
                : __( 'FazerCards did not return a refreshable Gift Card order.', 'wc-topup-fields' );

            if ( $is_auto_context ) {
                wctf_add_fazercards_giftcard_auto_refresh_note(
                    $order,
                    $item_id,
                    $remote_order_id,
                    'failed'
                );
            }
        } else {
            $remote_order = $parsed['order'];
            $safe_order   = wctf_get_fazercards_giftcard_safe_order_result( $remote_order );

            if (
                empty( $safe_order['remote_order_id'] )
                || 1 !== preg_match( '/\Aord-[0-9]+\z/D', $safe_order['remote_order_id'] )
            ) {
                $safe_order['remote_order_id'] = $remote_order_id;
            }

            try {
                $stored = wctf_fazercards_giftcard_store_secret_payload(
                    $item,
                    $remote_order,
                    isset( $parsed['http_status'] ) ? absint( $parsed['http_status'] ) : 0
                );
            } catch ( Throwable $storage_throwable ) {
                unset( $storage_throwable );
                $stored = new WP_Error(
                    'wctf_giftcard_refresh_secret_storage_failed',
                    __( 'The refreshed Gift Card payload could not be stored safely.', 'wc-topup-fields' )
                );
            }

            unset( $remote_order );

            if ( is_wp_error( $stored ) ) {
                $safe_error = __( 'FazerCards returned the remote order, but encrypted storage failed. The existing encrypted payload was preserved.', 'wc-topup-fields' );
                wctf_save_fazercards_giftcard_refresh_error( $item, $safe_error );

                $result['type']    = 'error';
                $result['message'] = $safe_error;

                if ( $is_auto_context ) {
                    wctf_add_fazercards_giftcard_auto_refresh_note(
                        $order,
                        $item_id,
                        $safe_order['remote_order_id'],
                        'failed'
                    );
                }
            } else {
                $final_status = wctf_map_fazercards_giftcard_purchase_status( $safe_order );
                $saved_item   = wctf_save_fazercards_giftcard_refresh_success_state(
                    $item,
                    $final_status,
                    $safe_order['remote_order_id'],
                    $safe_order['remote_status']
                );

                if ( is_wp_error( $saved_item ) ) {
                    $final_status = 'pending_review';
                    $safe_error   = __( 'The refreshed Gift Card payload was stored, but the local status could not be saved cleanly.', 'wc-topup-fields' );
                    wctf_save_fazercards_giftcard_refresh_error( $item, $safe_error );
                    $result_type = 'warning';
                    $message     = $safe_error;
                } else {
                    $item        = $saved_item;
                    $result_type = 'purchased' === $final_status ? 'success' : 'warning';
                    $message     = wctf_get_fazercards_giftcard_refresh_result_message(
                        $final_status,
                        $safe_order['remote_order_id']
                    );
                }

                if ( $is_auto_context ) {
                    wctf_add_fazercards_giftcard_auto_refresh_note(
                        $order,
                        $item_id,
                        $safe_order['remote_order_id'],
                        'purchased' === $final_status ? 'refreshed' : 'still pending'
                    );
                } elseif ( ! $is_background_context ) {
                    wctf_add_fazercards_giftcard_refresh_note(
                        $order,
                        $item_id,
                        $safe_order['remote_order_id'],
                        $safe_order['remote_status'],
                        $final_status
                    );
                }

                $result = array(
                    'type'            => $result_type,
                    'item_id'         => $item_id,
                    'status'          => $final_status,
                    'message'         => $message,
                    'remote_order_id' => $safe_order['remote_order_id'],
                    'remote_status'   => $safe_order['remote_status'],
                    'codes_count'     => isset( $safe_order['codes_count'] ) ? $safe_order['codes_count'] : null,
                    'attempted'       => true,
                );
            }

            unset( $safe_order, $stored );
        }

        unset( $parsed );
    } catch ( Throwable $throwable ) {
        unset( $throwable );

        $safe_error = __( 'The Gift Card remote order refresh could not be completed safely. The existing encrypted payload was preserved.', 'wc-topup-fields' );
        wctf_save_fazercards_giftcard_refresh_error( $item, $safe_error );

        $result['type']    = 'error';
        $result['message'] = $safe_error;

        if ( $is_auto_context ) {
            wctf_add_fazercards_giftcard_auto_refresh_note(
                $order,
                $item_id,
                $remote_order_id,
                'failed'
            );
        }
    } finally {
        unset( $response, $parsed );
        wctf_release_fazercards_giftcard_refresh_lock(
            $order_id,
            $item_id,
            $lock_token
        );
    }

    return $result;
}

/**
 * Strictly parse a Gift Card remote order refresh response.
 *
 * @param mixed $response Provider response.
 * @param mixed $provider Gift Card provider.
 * @return array
 */
function wctf_parse_fazercards_giftcard_refresh_response( $response, $provider ) {
    $http_status = is_array( $response ) && isset( $response['status'] )
        ? absint( $response['status'] )
        : 0;
    $body        = is_array( $response ) && isset( $response['body'] ) && is_array( $response['body'] )
        ? $response['body']
        : array();

    if (
        isset( $body['ok'] )
        && true === $body['ok']
        && isset( $body['order'] )
        && wctf_fazercards_giftcard_is_associative_array( $body['order'] )
    ) {
        return array(
            'success'     => true,
            'http_status' => $http_status,
            'message'     => '',
            'order'       => $body['order'],
        );
    }

    $safe_error = '';

    if ( is_object( $provider ) && method_exists( $provider, 'getError' ) ) {
        $safe_error = wctf_sanitize_fazercards_giftcard_purchase_error(
            $provider->getError( $response ),
            500
        );
    }

    if ( '' === $safe_error ) {
        $safe_error = __( 'FazerCards did not return a refreshable Gift Card order.', 'wc-topup-fields' );
    }

    return array(
        'success'     => false,
        'http_status' => $http_status,
        'message'     => $safe_error,
    );
}

/**
 * Strictly parse a provider response without retaining arbitrary failure data.
 *
 * A successful result contains the opaque order object only for immediate
 * encrypted persistence by the caller.
 *
 * @param mixed $response Provider response.
 * @param mixed $provider Gift Card provider.
 * @return array
 */
function wctf_parse_fazercards_giftcard_purchase_response( $response, $provider ) {
    $http_status = is_array( $response ) && isset( $response['status'] )
        ? absint( $response['status'] )
        : 0;
    $body = is_array( $response ) && isset( $response['body'] ) && is_array( $response['body'] )
        ? $response['body']
        : array();

    if (
        isset( $body['ok'] )
        && true === $body['ok']
        && isset( $body['order'] )
        && wctf_fazercards_giftcard_is_associative_array( $body['order'] )
    ) {
        return array(
            'success'     => true,
            'uncertain'   => false,
            'http_status' => $http_status,
            'message'     => '',
            'order'       => $body['order'],
        );
    }

    $safe_error = '';

    if ( is_object( $provider ) && method_exists( $provider, 'getError' ) ) {
        $safe_error = wctf_sanitize_fazercards_giftcard_purchase_error(
            $provider->getError( $response ),
            500
        );
    }

    $is_server_or_transport_failure = 0 === $http_status
        || 408 === $http_status
        || 500 <= $http_status;
    $is_explicit_rejection          = ! $is_server_or_transport_failure
        && (
            ( isset( $body['ok'] ) && false === $body['ok'] )
            || ( 400 <= $http_status && 500 > $http_status && '' !== $safe_error )
        );

    if ( $is_explicit_rejection ) {
        if ( '' === $safe_error ) {
            $safe_error = __( 'FazerCards rejected the Gift Card purchase.', 'wc-topup-fields' );
        }

        return array(
            'success'     => false,
            'uncertain'   => false,
            'http_status' => $http_status,
            'message'     => $safe_error,
        );
    }

    return array(
        'success'     => false,
        'uncertain'   => true,
        'http_status' => $http_status,
        'message'     => __( 'The remote Gift Card purchase outcome is uncertain. Do not purchase again; recovery is required.', 'wc-topup-fields' ),
    );
}

/**
 * Extract only explicitly permitted top-level order summary fields.
 *
 * @param array $remote_order Opaque FazerCards order object.
 * @return array
 */
function wctf_get_fazercards_giftcard_safe_order_result( $remote_order ) {
    $remote_order_id = '';
    $remote_status   = '';

    foreach ( array( 'id', 'order_id' ) as $candidate_key ) {
        if ( isset( $remote_order[ $candidate_key ] ) && is_scalar( $remote_order[ $candidate_key ] ) ) {
            $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string(
                $remote_order[ $candidate_key ],
                191
            );

            if ( '' !== $remote_order_id ) {
                break;
            }
        }
    }

    if ( isset( $remote_order['status'] ) && is_scalar( $remote_order['status'] ) ) {
        $remote_status = wctf_limit_fazercards_giftcard_purchase_string(
            $remote_order['status'],
            100
        );
    }

    return array(
        'remote_order_id' => $remote_order_id,
        'remote_status'   => $remote_status,
        'codes_count'     => function_exists( 'wctf_fazercards_giftcard_detect_codes_count' )
            ? wctf_fazercards_giftcard_detect_codes_count( $remote_order )
            : null,
    );
}

/**
 * Map a safely stored opaque order to a conservative local status.
 *
 * @param array $safe_order Safe top-level summary.
 * @return string
 */
function wctf_map_fazercards_giftcard_purchase_status( $safe_order ) {
    $codes_count   = isset( $safe_order['codes_count'] ) ? $safe_order['codes_count'] : null;
    $remote_status = isset( $safe_order['remote_status'] )
        ? sanitize_key( (string) $safe_order['remote_status'] )
        : '';

    if ( null !== $codes_count && 0 < absint( $codes_count ) ) {
        return 'purchased';
    }

    if ( 'completed' === $remote_status ) {
        return 'purchased';
    }

    if ( in_array( $remote_status, array( 'pending', 'processing', 'created' ), true ) ) {
        return 'pending';
    }

    return 'pending_review';
}

/**
 * Decide whether one successful purchase response should be refreshed once.
 *
 * @param array $safe_order Safe top-level remote order summary.
 * @return bool
 */
function wctf_should_fazercards_giftcard_auto_refresh_after_purchase( $safe_order ) {
    if ( ! is_array( $safe_order ) ) {
        return false;
    }

    $remote_order_id = isset( $safe_order['remote_order_id'] )
        ? wctf_limit_fazercards_giftcard_purchase_string( $safe_order['remote_order_id'], 191 )
        : '';

    if ( 1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id ) ) {
        return false;
    }

    $codes_count   = array_key_exists( 'codes_count', $safe_order )
        ? $safe_order['codes_count']
        : null;
    $remote_status = isset( $safe_order['remote_status'] )
        ? sanitize_key( (string) $safe_order['remote_status'] )
        : '';

    if ( null === $codes_count || 0 === absint( $codes_count ) ) {
        return true;
    }

    return in_array( $remote_status, array( 'created', 'pending', 'processing' ), true );
}

/**
 * Normalize a Gift Card fast-settle status.
 *
 * @param mixed $status Raw status.
 * @return string
 */
function wctf_normalize_fazercards_giftcard_fast_settle_status( $status ) {
    $status  = is_scalar( $status ) ? sanitize_key( (string) $status ) : '';
    $allowed = array( 'not_started', 'dispatched', 'running', 'ready', 'fallback_queued', 'failed' );

    return in_array( $status, $allowed, true ) ? $status : 'not_started';
}

/**
 * Return a readable Gift Card fast-settle status label.
 *
 * @param mixed $status Raw status.
 * @return string
 */
function wctf_get_fazercards_giftcard_fast_settle_status_label( $status ) {
    $labels = array(
        'not_started'     => __( 'Not started', 'wc-topup-fields' ),
        'dispatched'      => __( 'Dispatched', 'wc-topup-fields' ),
        'running'         => __( 'Running', 'wc-topup-fields' ),
        'ready'           => __( 'Ready', 'wc-topup-fields' ),
        'fallback_queued' => __( 'Fallback queued', 'wc-topup-fields' ),
        'failed'          => __( 'Failed', 'wc-topup-fields' ),
    );
    $status = wctf_normalize_fazercards_giftcard_fast_settle_status( $status );

    return $labels[ $status ];
}

/**
 * Save only non-sensitive Gift Card fast-settle diagnostics.
 *
 * @param WC_Order_Item_Product $item     WooCommerce order item.
 * @param string                $status   Fast-settle status.
 * @param int|null              $attempts Attempt count, or null to preserve.
 * @param string                $error    Safe short error.
 * @return WC_Order_Item_Product|WP_Error
 */
function wctf_save_fazercards_giftcard_fast_settle_state( $item, $status, $attempts = null, $error = '' ) {
    if ( ! $item instanceof WC_Order_Item_Product || 1 > absint( $item->get_id() ) ) {
        return new WP_Error(
            'wctf_giftcard_fast_settle_state_invalid',
            __( 'Gift Card fast-settle state could not be saved.', 'wc-topup-fields' )
        );
    }

    $item_id = absint( $item->get_id() );
    $status  = wctf_normalize_fazercards_giftcard_fast_settle_status( $status );
    $error   = wctf_sanitize_fazercards_giftcard_purchase_error( $error, 500 );

    try {
        $item->update_meta_data( '_wctf_fazer_giftcard_fast_settle_status', $status );

        if ( null !== $attempts ) {
            $item->update_meta_data( '_wctf_fazer_giftcard_fast_settle_attempts', min( 5, absint( $attempts ) ) );
        }

        if ( 'dispatched' === $status ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_fast_settle_started_at' );
            $item->delete_meta_data( '_wctf_fazer_giftcard_fast_settle_completed_at' );
        } elseif (
            'running' === $status
            && '' === (string) $item->get_meta( '_wctf_fazer_giftcard_fast_settle_started_at', true )
        ) {
            $item->update_meta_data(
                '_wctf_fazer_giftcard_fast_settle_started_at',
                sanitize_text_field( current_time( 'mysql', true ) )
            );
        } elseif ( in_array( $status, array( 'ready', 'fallback_queued', 'failed' ), true ) ) {
            $item->update_meta_data(
                '_wctf_fazer_giftcard_fast_settle_completed_at',
                sanitize_text_field( current_time( 'mysql', true ) )
            );
        }

        if ( '' === $error ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_fast_settle_last_error' );
        } else {
            $item->update_meta_data( '_wctf_fazer_giftcard_fast_settle_last_error', $error );
        }

        $item->save_meta_data();
        return new WC_Order_Item_Product( $item_id );
    } catch ( Throwable $throwable ) {
        unset( $throwable );
        return new WP_Error(
            'wctf_giftcard_fast_settle_state_failed',
            __( 'Gift Card fast-settle state could not be persisted safely.', 'wc-topup-fields' )
        );
    }
}

/**
 * Return the one-time fast-settle token transient key.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_fast_settle_token_key( $order_id, $item_id ) {
    return 'wctf_gc_fast_token_' . absint( $order_id ) . '_' . absint( $item_id );
}

/**
 * Build the fast-settle HMAC signature.
 *
 * @param int    $order_id        WooCommerce order ID.
 * @param int    $item_id         WooCommerce order item ID.
 * @param string $remote_order_id Stored remote order ID.
 * @param int    $issued_at       Unix timestamp.
 * @param string $token           One-time random token.
 * @return string
 */
function wctf_build_fazercards_giftcard_fast_settle_signature( $order_id, $item_id, $remote_order_id, $issued_at, $token ) {
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );
    $token           = is_scalar( $token ) ? sanitize_text_field( (string) $token ) : '';
    $key             = wp_salt( 'auth' );

    if (
        1 > absint( $order_id )
        || 1 > absint( $item_id )
        || 1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id )
        || 1 > absint( $issued_at )
        || 1 !== preg_match( '/\A[A-Za-z0-9]{32,64}\z/D', $token )
        || ! is_string( $key )
        || '' === $key
    ) {
        return '';
    }

    $message = implode(
        '|',
        array( 'v1', absint( $order_id ), absint( $item_id ), $remote_order_id, absint( $issued_at ), $token )
    );

    return hash_hmac( 'sha256', $message, $key );
}

/**
 * Return the independent Gift Card fast-settle lock option key.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_fast_settle_lock_key( $order_id, $item_id ) {
    return 'wctf_fazer_giftcard_fast_settle_lock_' . absint( $order_id ) . '_' . absint( $item_id );
}

/**
 * Determine whether a non-stale fast-settle lock exists.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return bool
 */
function wctf_is_fazercards_giftcard_fast_settle_lock_active( $order_id, $item_id ) {
    $lock = get_option( wctf_get_fazercards_giftcard_fast_settle_lock_key( $order_id, $item_id ), false );

    return is_array( $lock ) && ! empty( $lock['expires'] ) && absint( $lock['expires'] ) > time();
}

/**
 * Acquire an atomic one-minute fast-settle lock.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string|WP_Error
 */
function wctf_acquire_fazercards_giftcard_fast_settle_lock( $order_id, $item_id ) {
    $lock_key = wctf_get_fazercards_giftcard_fast_settle_lock_key( $order_id, $item_id );
    $now      = time();
    $token    = wp_generate_uuid4();
    $lock     = array(
        'created' => $now,
        'expires' => $now + MINUTE_IN_SECONDS,
        'token'   => $token,
    );

    if ( add_option( $lock_key, $lock, '', 'no' ) ) {
        return $token;
    }

    $existing = get_option( $lock_key, array() );
    $expires  = is_array( $existing ) && isset( $existing['expires'] ) ? absint( $existing['expires'] ) : 0;

    if ( 0 !== $expires && $expires <= $now ) {
        delete_option( $lock_key );
    }

    return new WP_Error(
        'wctf_giftcard_fast_settle_lock_held',
        __( 'A Gift Card fast-settle worker is already active or its stale lock was just cleared.', 'wc-topup-fields' )
    );
}

/**
 * Release only the fast-settle lock owned by this worker.
 *
 * @param int    $order_id WooCommerce order ID.
 * @param int    $item_id  WooCommerce order item ID.
 * @param string $token    Lock owner token.
 * @return void
 */
function wctf_release_fazercards_giftcard_fast_settle_lock( $order_id, $item_id, $token ) {
    if ( ! is_string( $token ) || '' === $token ) {
        return;
    }

    $lock_key = wctf_get_fazercards_giftcard_fast_settle_lock_key( $order_id, $item_id );
    $existing = get_option( $lock_key, array() );

    if (
        is_array( $existing )
        && isset( $existing['token'] )
        && is_string( $existing['token'] )
        && hash_equals( $existing['token'], $token )
    ) {
        delete_option( $lock_key );
    }
}

/**
 * Schedule fallback and dispatch one signed fast-settle request if eligible.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order item.
 * @param int                   $item_id WooCommerce order item ID.
 * @param string                $reason  Safe internal dispatch reason.
 * @return true|WP_Error
 */
function wctf_maybe_dispatch_fazercards_giftcard_fast_settle( $order, $item, $item_id, $reason = '' ) {
    unset( $reason );

    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_context_invalid', __( 'A valid Gift Card order item is required before fast settle.', 'wc-topup-fields' ) );
    }

    $order_id = absint( $order->get_id() );
    $item_id  = absint( $item_id );
    $owned    = $order->get_item( $item_id );

    if ( ! $owned instanceof WC_Order_Item_Product || absint( $owned->get_id() ) !== absint( $item->get_id() ) ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_item_invalid', __( 'The Gift Card order item could not be validated for fast settle.', 'wc-topup-fields' ) );
    }

    if ( 'giftcard' !== sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) ) ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_not_giftcard', __( 'Only Gift Card order items can use fast settle.', 'wc-topup-fields' ) );
    }

    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string(
        $item->get_meta( '_wctf_fazer_giftcard_remote_order_id', true ),
        191
    );

    if ( 1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id ) ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_remote_id_invalid', __( 'A valid stored remote order ID is required for fast settle.', 'wc-topup-fields' ) );
    }

    $fulfillment_status = wctf_normalize_fazercards_giftcard_fulfillment_status(
        $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true )
    );

    if ( 'stopped' === $fulfillment_status ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_stopped', __( 'Gift Card fulfillment is stopped, so fast settle was not dispatched.', 'wc-topup-fields' ) );
    }

    if ( wctf_is_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
        wctf_maybe_mark_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id );
        wctf_save_fazercards_giftcard_fast_settle_state( $item, 'ready', null, '' );
        return true;
    }

    $fast_status = wctf_normalize_fazercards_giftcard_fast_settle_status(
        $item->get_meta( '_wctf_fazer_giftcard_fast_settle_status', true )
    );

    if ( in_array( $fast_status, array( 'dispatched', 'running', 'ready' ), true ) ) {
        return true;
    }

    $has_secret      = function_exists( 'wctf_fazercards_giftcard_has_secret_payload' )
        && wctf_fazercards_giftcard_has_secret_payload( $item );
    $purchase_status = wctf_normalize_fazercards_giftcard_purchase_status(
        $item->get_meta( '_wctf_fazer_giftcard_purchase_status', true )
    );

    if ( ! $has_secret && ! in_array( $purchase_status, array( 'pending', 'purchased' ), true ) ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_purchase_state_invalid', __( 'No encrypted payload or eligible remote purchase state exists for fast settle.', 'wc-topup-fields' ) );
    }

    $stored_codes_count = $item->get_meta( '_wctf_fazer_giftcard_codes_count', true );
    $codes_count        = $item->meta_exists( '_wctf_fazer_giftcard_codes_count' )
        && is_scalar( $stored_codes_count )
        && 1 === preg_match( '/\A[0-9]+\z/D', (string) $stored_codes_count )
            ? absint( $stored_codes_count )
            : null;

    if ( null !== $codes_count && 0 < $codes_count ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_codes_unverified', __( 'Gift Card codes appear to exist, but the encrypted payload is not ready for delivery.', 'wc-topup-fields' ) );
    }

    $crypto_status = function_exists( 'wctf_fazercards_giftcard_crypto_status' )
        ? wctf_fazercards_giftcard_crypto_status()
        : array();

    if (
        empty( $crypto_status['ready'] )
        || ! function_exists( 'wctf_fazercards_giftcard_store_secret_payload' )
        || ! function_exists( 'wctf_fazercards_giftcard_get_secret_payload_wrapper' )
    ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_crypto_not_ready', __( 'Gift Card encryption is not ready for fast settle.', 'wc-topup-fields' ) );
    }

    if ( wctf_is_fazercards_giftcard_fast_settle_lock_active( $order_id, $item_id ) ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_active', __( 'A Gift Card fast-settle worker is already active.', 'wc-topup-fields' ) );
    }

    $fallback = wctf_schedule_fazercards_giftcard_auto_refresh_retry( $order, $item, $item_id, 30, false );

    if ( is_wp_error( $fallback ) ) {
        wctf_save_fazercards_giftcard_fast_settle_state( $item, 'failed', 0, $fallback->get_error_message() );
        return $fallback;
    }

    $dispatched = wctf_dispatch_fazercards_giftcard_fast_settle( $order_id, $item_id, $remote_order_id );

    if ( is_wp_error( $dispatched ) ) {
        wctf_save_fazercards_giftcard_fast_settle_state(
            new WC_Order_Item_Product( $item_id ),
            'fallback_queued',
            0,
            $dispatched->get_error_message()
        );
    }

    return $dispatched;
}

/**
 * Dispatch one signed, non-blocking fast-settle self-request.
 *
 * @param int    $order_id        WooCommerce order ID.
 * @param int    $item_id         WooCommerce order item ID.
 * @param string $remote_order_id Stored remote order ID.
 * @return true|WP_Error
 */
function wctf_dispatch_fazercards_giftcard_fast_settle( $order_id, $item_id, $remote_order_id ) {
    $order_id        = absint( $order_id );
    $item_id         = absint( $item_id );
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );
    $issued_at       = time();
    $token           = wp_generate_password( 48, false, false );
    $signature       = wctf_build_fazercards_giftcard_fast_settle_signature(
        $order_id,
        $item_id,
        $remote_order_id,
        $issued_at,
        $token
    );

    if ( '' === $signature ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_signature_failed', __( 'The Gift Card fast-settle request could not be signed safely.', 'wc-topup-fields' ) );
    }

    $token_key = wctf_get_fazercards_giftcard_fast_settle_token_key( $order_id, $item_id );
    $stored    = set_transient(
        $token_key,
        array(
            'token_hash'           => hash( 'sha256', $token ),
            'issued_at'            => $issued_at,
            'remote_order_id_hash' => hash( 'sha256', $remote_order_id ),
        ),
        5 * MINUTE_IN_SECONDS
    );

    if ( ! $stored ) {
        return new WP_Error( 'wctf_giftcard_fast_settle_token_failed', __( 'The one-time Gift Card fast-settle token could not be stored.', 'wc-topup-fields' ) );
    }

    $state = wctf_save_fazercards_giftcard_fast_settle_state( new WC_Order_Item_Product( $item_id ), 'dispatched', 0, '' );

    if ( is_wp_error( $state ) ) {
        delete_transient( $token_key );
        return $state;
    }

    $response = wp_remote_post(
        admin_url( 'admin-post.php' ),
        array(
            'method'      => 'POST',
            'timeout'     => 1,
            'redirection' => 0,
            'blocking'    => false,
            'sslverify'   => true,
            'body'        => array(
                'action'    => 'wctf_fazercards_giftcard_fast_settle',
                'order_id'  => $order_id,
                'item_id'   => $item_id,
                'issued_at' => $issued_at,
                'token'     => $token,
                'signature' => $signature,
            ),
        )
    );

    unset( $token, $signature );

    if ( is_wp_error( $response ) ) {
        delete_transient( $token_key );
        return new WP_Error( 'wctf_giftcard_fast_settle_dispatch_failed', __( 'The background fast-settle request could not be dispatched. The scheduled fallback remains active.', 'wc-topup-fields' ) );
    }

    unset( $response );
    return true;
}

/**
 * Handle the signed server-to-server fast-settle endpoint.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_fast_settle() {
    $request_method = isset( $_SERVER['REQUEST_METHOD'] ) && is_scalar( $_SERVER['REQUEST_METHOD'] )
        ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
        : '';

    if ( 'POST' !== $request_method ) {
        wctf_finish_fazercards_giftcard_fast_settle_request( 405 );
    }

    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
        : 0;
    $item_id = isset( $_POST['item_id'] ) && is_scalar( $_POST['item_id'] )
        ? absint( wp_unslash( $_POST['item_id'] ) )
        : 0;
    $issued_at = isset( $_POST['issued_at'] ) && is_scalar( $_POST['issued_at'] )
        ? absint( wp_unslash( $_POST['issued_at'] ) )
        : 0;
    $token = isset( $_POST['token'] ) && is_scalar( $_POST['token'] )
        ? sanitize_text_field( wp_unslash( $_POST['token'] ) )
        : '';
    $signature = isset( $_POST['signature'] ) && is_scalar( $_POST['signature'] )
        ? sanitize_text_field( wp_unslash( $_POST['signature'] ) )
        : '';
    $now = time();

    if (
        1 > $order_id
        || 1 > $item_id
        || 1 > $issued_at
        || $issued_at > $now + 30
        || $issued_at < $now - ( 5 * MINUTE_IN_SECONDS )
        || 1 !== preg_match( '/\A[A-Za-z0-9]{32,64}\z/D', $token )
        || 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $signature )
    ) {
        wctf_finish_fazercards_giftcard_fast_settle_request( 403 );
    }

    $order = wc_get_order( $order_id );
    $item  = $order instanceof WC_Order ? $order->get_item( $item_id ) : false;

    if (
        ! $order instanceof WC_Order
        || ! $item instanceof WC_Order_Item_Product
        || 'giftcard' !== sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) )
    ) {
        wctf_finish_fazercards_giftcard_fast_settle_request( 403 );
    }

    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string(
        $item->get_meta( '_wctf_fazer_giftcard_remote_order_id', true ),
        191
    );
    $expected_signature = wctf_build_fazercards_giftcard_fast_settle_signature(
        $order_id,
        $item_id,
        $remote_order_id,
        $issued_at,
        $token
    );
    $token_key = wctf_get_fazercards_giftcard_fast_settle_token_key( $order_id, $item_id );
    $stored    = get_transient( $token_key );

    if (
        '' === $expected_signature
        || ! hash_equals( $expected_signature, $signature )
        || ! is_array( $stored )
        || empty( $stored['token_hash'] )
        || empty( $stored['remote_order_id_hash'] )
        || absint( isset( $stored['issued_at'] ) ? $stored['issued_at'] : 0 ) !== $issued_at
        || ! hash_equals( (string) $stored['token_hash'], hash( 'sha256', $token ) )
        || ! hash_equals( (string) $stored['remote_order_id_hash'], hash( 'sha256', $remote_order_id ) )
    ) {
        wctf_finish_fazercards_giftcard_fast_settle_request( 403 );
    }

    unset( $token, $signature, $expected_signature );

    $crypto_status = function_exists( 'wctf_fazercards_giftcard_crypto_status' )
        ? wctf_fazercards_giftcard_crypto_status()
        : array();
    $fulfillment_status = wctf_normalize_fazercards_giftcard_fulfillment_status(
        $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true )
    );
    $has_secret = function_exists( 'wctf_fazercards_giftcard_has_secret_payload' )
        && wctf_fazercards_giftcard_has_secret_payload( $item );
    $purchase_status = wctf_normalize_fazercards_giftcard_purchase_status(
        $item->get_meta( '_wctf_fazer_giftcard_purchase_status', true )
    );

    if (
        1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id )
        || empty( $crypto_status['ready'] )
        || ! function_exists( 'wctf_fazercards_giftcard_store_secret_payload' )
        || ( ! $has_secret && ! in_array( $purchase_status, array( 'pending', 'purchased' ), true ) )
        || wctf_is_fazercards_giftcard_purchase_lock_active( $order_id, $item_id )
        || 'stopped' === $fulfillment_status
    ) {
        wctf_finish_fazercards_giftcard_fast_settle_request( 403 );
    }

    if ( wctf_is_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
        delete_transient( $token_key );
        wctf_maybe_mark_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id );
        wctf_save_fazercards_giftcard_fast_settle_state( $item, 'ready', null, '' );
        wctf_finish_fazercards_giftcard_fast_settle_request( 204 );
    }

    $lock_token = wctf_acquire_fazercards_giftcard_fast_settle_lock( $order_id, $item_id );

    if ( is_wp_error( $lock_token ) ) {
        wctf_finish_fazercards_giftcard_fast_settle_request( 409 );
    }

    delete_transient( $token_key );
    ignore_user_abort( true );

    try {
        wctf_run_fazercards_giftcard_fast_settle( $order, $item, $item_id, $remote_order_id );
    } catch ( Throwable $throwable ) {
        unset( $throwable );
        wctf_save_fazercards_giftcard_fast_settle_state(
            new WC_Order_Item_Product( $item_id ),
            'fallback_queued',
            null,
            __( 'The background fast-settle worker stopped safely. The scheduled fallback remains active.', 'wc-topup-fields' )
        );
    } finally {
        wctf_release_fazercards_giftcard_fast_settle_lock( $order_id, $item_id, $lock_token );
    }

    wctf_finish_fazercards_giftcard_fast_settle_request( 204 );
}

/**
 * Run the bounded background fast-settle GET sequence.
 *
 * @param WC_Order              $order           WooCommerce order.
 * @param WC_Order_Item_Product $item            WooCommerce order item.
 * @param int                   $item_id         WooCommerce order item ID.
 * @param string                $remote_order_id Stored remote order ID.
 * @return void
 */
function wctf_run_fazercards_giftcard_fast_settle( $order, $item, $item_id, $remote_order_id ) {
    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        return;
    }

    $item_id         = absint( $item_id );
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );
    $targets         = array( 0, 3, 7, 12, 18 );
    $started         = microtime( true );
    $deadline        = $started + 21;
    $attempts        = 0;
    $state           = wctf_save_fazercards_giftcard_fast_settle_state( $item, 'running', 0, '' );

    if ( is_wp_error( $state ) ) {
        return;
    }

    foreach ( $targets as $target ) {
        $target_time = $started + absint( $target );
        $wait        = $target_time - microtime( true );

        if ( 0 < $wait ) {
            usleep( (int) min( 6000000, floor( $wait * 1000000 ) ) );
        }

        if ( microtime( true ) >= $deadline ) {
            break;
        }

        $item = new WC_Order_Item_Product( $item_id );

        if ( wctf_is_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
            wctf_maybe_mark_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id );
            wctf_save_fazercards_giftcard_fast_settle_state( $item, 'ready', $attempts, '' );
            return;
        }

        if ( 'stopped' === wctf_normalize_fazercards_giftcard_fulfillment_status( $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true ) ) ) {
            wctf_save_fazercards_giftcard_fast_settle_state( $item, 'failed', $attempts, __( 'Gift Card fulfillment was stopped during fast settle.', 'wc-topup-fields' ) );
            return;
        }

        add_filter( 'http_request_args', 'wctf_filter_fazercards_giftcard_fast_settle_http_args', 999, 2 );

        try {
            $result = wctf_fazercards_giftcard_refresh_remote_order_item(
                $order,
                $item,
                $item_id,
                $remote_order_id,
                'fast_settle'
            );
        } finally {
            remove_filter( 'http_request_args', 'wctf_filter_fazercards_giftcard_fast_settle_http_args', 999 );
        }

        if ( is_array( $result ) && ! empty( $result['attempted'] ) ) {
            $attempts++;
            wctf_save_fazercards_giftcard_fast_settle_state( new WC_Order_Item_Product( $item_id ), 'running', $attempts, '' );
        }

        $item = new WC_Order_Item_Product( $item_id );

        if ( wctf_is_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
            wctf_maybe_mark_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id );
            wctf_save_fazercards_giftcard_fast_settle_state( $item, 'ready', $attempts, '' );
            return;
        }

        if (
            ! is_array( $result )
            || empty( $result['attempted'] )
            || ( isset( $result['type'] ) && 'error' === $result['type'] )
        ) {
            $safe_error = is_array( $result ) && ! empty( $result['message'] )
                ? wctf_sanitize_fazercards_giftcard_purchase_error( $result['message'], 500 )
                : __( 'Gift Card fast settle could not complete a safe refresh attempt.', 'wc-topup-fields' );
            wctf_save_fazercards_giftcard_fast_settle_state( $item, 'fallback_queued', $attempts, $safe_error );
            return;
        }
    }

    wctf_save_fazercards_giftcard_fast_settle_state( new WC_Order_Item_Product( $item_id ), 'fallback_queued', $attempts, '' );
}

/**
 * Limit the HTTP timeout only for the fast-settle Gift Card order GET.
 *
 * @param array  $args HTTP request arguments.
 * @param string $url  Request URL.
 * @return array
 */
function wctf_filter_fazercards_giftcard_fast_settle_http_args( $args, $url ) {
    if ( ! is_array( $args ) || ! is_string( $url ) || ! function_exists( 'wctf_config' ) ) {
        return $args;
    }

    $config    = wctf_config();
    $api_url   = is_array( $config ) && ! empty( $config['api_url'] ) ? (string) $config['api_url'] : '';
    $api_parts = wp_parse_url( $api_url );
    $url_parts = wp_parse_url( $url );
    $method    = isset( $args['method'] ) && is_scalar( $args['method'] )
        ? strtoupper( (string) $args['method'] )
        : 'GET';

    if (
        'GET' === $method
        && is_array( $api_parts )
        && is_array( $url_parts )
        && ! empty( $api_parts['host'] )
        && ! empty( $url_parts['host'] )
        && strtolower( (string) $api_parts['host'] ) === strtolower( (string) $url_parts['host'] )
        && ! empty( $url_parts['path'] )
        && 1 === preg_match( '#/api/v2/orders/ord-[0-9]+/?\z#D', (string) $url_parts['path'] )
    ) {
        $args['timeout'] = 2;
    }

    return $args;
}

/**
 * End the internal fast-settle request without exposing diagnostics.
 *
 * @param int $status HTTP response status.
 * @return void
 */
function wctf_finish_fazercards_giftcard_fast_settle_request( $status ) {
    status_header( absint( $status ) );
    nocache_headers();
    exit;
}

/**
 * Normalize Gift Card fulfillment status.
 *
 * @param mixed $status Raw status.
 * @return string
 */
function wctf_normalize_fazercards_giftcard_fulfillment_status( $status ) {
    $status = is_scalar( $status ) ? sanitize_key( (string) $status ) : '';

    if ( '' === $status ) {
        return 'not_started';
    }

    $allowed = array(
        'not_started',
        'queued',
        'refreshing',
        'ready_to_deliver',
        'needs_admin_review',
        'stopped',
    );

    return in_array( $status, $allowed, true ) ? $status : 'not_started';
}

/**
 * Return readable Gift Card fulfillment status label.
 *
 * @param mixed $status Raw status.
 * @return string
 */
function wctf_get_fazercards_giftcard_fulfillment_status_label( $status ) {
    $labels = array(
        'not_started'        => __( 'Not started', 'wc-topup-fields' ),
        'queued'             => __( 'Queued', 'wc-topup-fields' ),
        'refreshing'         => __( 'Refreshing', 'wc-topup-fields' ),
        'ready_to_deliver'   => __( 'Ready to deliver', 'wc-topup-fields' ),
        'needs_admin_review' => __( 'Needs admin review', 'wc-topup-fields' ),
        'stopped'            => __( 'Stopped', 'wc-topup-fields' ),
    );
    $status = wctf_normalize_fazercards_giftcard_fulfillment_status( $status );

    return $labels[ $status ];
}

/**
 * Return filterable Gift Card auto-refresh retry intervals in seconds.
 *
 * @return array
 */
function wctf_get_fazercards_giftcard_auto_refresh_intervals() {
    $intervals = array(
        10,
        20,
        30,
        1 * MINUTE_IN_SECONDS,
        2 * MINUTE_IN_SECONDS,
        5 * MINUTE_IN_SECONDS,
        10 * MINUTE_IN_SECONDS,
    );
    $filtered  = apply_filters( 'wctf_fazercards_giftcard_auto_refresh_intervals', $intervals );

    if ( ! is_array( $filtered ) || empty( $filtered ) ) {
        return $intervals;
    }

    $clean = array();

    foreach ( $filtered as $interval ) {
        $interval = absint( $interval );

        if ( 0 < $interval ) {
            $clean[] = $interval;
        }
    }

    return empty( $clean ) ? $intervals : array_values( $clean );
}

/**
 * Return filterable max Gift Card auto-refresh attempts.
 *
 * @param WC_Order_Item_Product|null $item WooCommerce order item.
 * @return int
 */
function wctf_get_fazercards_giftcard_auto_refresh_max_attempts( $item = null ) {
    $default  = 7;
    $filtered = apply_filters(
        'wctf_fazercards_giftcard_auto_refresh_max_attempts',
        $default,
        $item
    );
    $max      = absint( $filtered );

    return 0 < $max ? $max : $default;
}

/**
 * Get the next retry delay for a one-based attempt number.
 *
 * @param int $attempt_number One-based attempt number.
 * @return int
 */
function wctf_get_fazercards_giftcard_auto_refresh_delay( $attempt_number ) {
    $intervals = wctf_get_fazercards_giftcard_auto_refresh_intervals();
    $index     = max( 0, absint( $attempt_number ) - 1 );

    if ( isset( $intervals[ $index ] ) ) {
        return absint( $intervals[ $index ] );
    }

    return absint( end( $intervals ) );
}

/**
 * Determine whether encrypted Gift Card payload is ready for future delivery.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order item.
 * @param int                   $item_id WooCommerce order item ID.
 * @return bool
 */
function wctf_is_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) {
    if (
        ! $order instanceof WC_Order
        || ! $item instanceof WC_Order_Item_Product
        || ! function_exists( 'wctf_fazercards_giftcard_has_secret_payload' )
        || ! function_exists( 'wctf_fazercards_giftcard_get_secret_payload_wrapper' )
        || ! function_exists( 'wctf_fazercards_giftcard_detect_codes_count' )
    ) {
        return false;
    }

    if ( ! wctf_fazercards_giftcard_has_secret_payload( $item ) ) {
        return false;
    }

    $wrapper = wctf_fazercards_giftcard_get_secret_payload_wrapper( $item );

    if (
        is_wp_error( $wrapper )
        || ! is_array( $wrapper )
        || absint( isset( $wrapper['woocommerce_order_id'] ) ? $wrapper['woocommerce_order_id'] : 0 ) !== absint( $order->get_id() )
        || absint( isset( $wrapper['woocommerce_order_item_id'] ) ? $wrapper['woocommerce_order_item_id'] : 0 ) !== absint( $item_id )
        || empty( $wrapper['order'] )
        || ! is_array( $wrapper['order'] )
    ) {
        return false;
    }

    $codes_count = wctf_fazercards_giftcard_detect_codes_count( $wrapper['order'] );

    return null !== $codes_count && 0 < absint( $codes_count );
}

/**
 * Persist one fulfillment status update.
 *
 * @param WC_Order_Item_Product $item   WooCommerce order item.
 * @param string                $status Fulfillment status.
 * @param string                $error  Safe error.
 * @return WC_Order_Item_Product|WP_Error
 */
function wctf_save_fazercards_giftcard_fulfillment_state( $item, $status, $error = '' ) {
    if ( ! $item instanceof WC_Order_Item_Product || 1 > absint( $item->get_id() ) ) {
        return new WP_Error(
            'wctf_giftcard_fulfillment_state_invalid',
            __( 'The Gift Card fulfillment state could not be saved.', 'wc-topup-fields' )
        );
    }

    $item_id = absint( $item->get_id() );
    $status  = wctf_normalize_fazercards_giftcard_fulfillment_status( $status );
    $error   = wctf_sanitize_fazercards_giftcard_purchase_error( $error, 500 );

    try {
        $item->update_meta_data( '_wctf_fazer_giftcard_fulfillment_status', $status );

        if ( '' === $error ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_last_refresh_error' );
        } else {
            $item->update_meta_data( '_wctf_fazer_giftcard_last_refresh_error', $error );
        }

        if ( 'ready_to_deliver' === $status ) {
            $now = sanitize_text_field( current_time( 'mysql', true ) );
            $item->update_meta_data( '_wctf_fazer_giftcard_ready_to_deliver_at', $now );
            $item->update_meta_data( '_wctf_fazer_giftcard_queue_completed_at', $now );
            $item->delete_meta_data( '_wctf_fazer_giftcard_next_refresh_at' );
        } elseif ( 'needs_admin_review' === $status || 'stopped' === $status ) {
            $item->update_meta_data(
                '_wctf_fazer_giftcard_queue_completed_at',
                sanitize_text_field( current_time( 'mysql', true ) )
            );
            $item->delete_meta_data( '_wctf_fazer_giftcard_next_refresh_at' );
        }

        $item->save_meta_data();

        return new WC_Order_Item_Product( $item_id );
    } catch ( Throwable $throwable ) {
        unset( $throwable );

        return new WP_Error(
            'wctf_giftcard_fulfillment_state_failed',
            __( 'The Gift Card fulfillment state could not be persisted safely.', 'wc-topup-fields' )
        );
    }
}

/**
 * Save only a safe short auto-refresh error.
 *
 * @param WC_Order_Item_Product $item  WooCommerce order item.
 * @param string                $error Safe error.
 * @return void
 */
function wctf_save_fazercards_giftcard_auto_refresh_error( $item, $error ) {
    if ( ! $item instanceof WC_Order_Item_Product || 1 > absint( $item->get_id() ) ) {
        return;
    }

    $error = wctf_sanitize_fazercards_giftcard_purchase_error( $error, 500 );

    try {
        if ( '' === $error ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_last_refresh_error' );
        } else {
            $item->update_meta_data( '_wctf_fazer_giftcard_last_refresh_error', $error );
        }

        $item->save_meta_data();
    } catch ( Throwable $throwable ) {
        unset( $throwable );
    }
}

/**
 * Mark an item ready for future delivery if the encrypted payload qualifies.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order item.
 * @param int                   $item_id WooCommerce order item ID.
 * @return bool
 */
function wctf_maybe_mark_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) {
    if ( ! wctf_is_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
        return false;
    }

    $saved_item = wctf_save_fazercards_giftcard_fulfillment_state(
        $item,
        'ready_to_deliver',
        ''
    );

    return ! is_wp_error( $saved_item );
}

/**
 * Return the Gift Card retry queue backend label.
 *
 * @return string
 */
function wctf_get_fazercards_giftcard_auto_refresh_queue_backend() {
    return function_exists( 'as_schedule_single_action' )
        ? __( 'Action Scheduler', 'wc-topup-fields' )
        : __( 'WP-Cron fallback', 'wc-topup-fields' );
}

/**
 * Return a safe Gift Card retry event status label for admin diagnostics.
 *
 * @param string $fulfillment_status Current fulfillment status.
 * @param bool   $has_future_retry   Whether a pending retry event exists.
 * @param int    $attempts           Completed remote GET attempts.
 * @return string
 */
function wctf_get_fazercards_giftcard_auto_refresh_event_status( $fulfillment_status, $has_future_retry, $attempts ) {
    $fulfillment_status = wctf_normalize_fazercards_giftcard_fulfillment_status( $fulfillment_status );

    if ( 'needs_admin_review' === $fulfillment_status ) {
        return __( 'Needs admin review', 'wc-topup-fields' );
    }

    if ( $has_future_retry ) {
        return __( 'Future retry scheduled', 'wc-topup-fields' );
    }

    if ( 0 < absint( $attempts ) ) {
        return __( 'Last action completed', 'wc-topup-fields' );
    }

    return __( 'No future retry scheduled', 'wc-topup-fields' );
}

/**
 * Return whether a pending retry is already scheduled for an order item.
 *
 * A currently running Action Scheduler action is intentionally excluded so the
 * worker can enqueue the next future retry before the current action completes.
 *
 * @param int  $order_id              WooCommerce order ID.
 * @param int  $item_id               WooCommerce order item ID.
 * @param bool $ignore_current_action Whether to ignore older Action Scheduler APIs that cannot distinguish pending from running.
 * @return bool
 */
function wctf_has_scheduled_fazercards_giftcard_auto_refresh_retry( $order_id, $item_id, $ignore_current_action = false ) {
    $args = array(
        'order_id' => absint( $order_id ),
        'item_id'  => absint( $item_id ),
    );

    if ( function_exists( 'as_get_scheduled_actions' ) ) {
        try {
            $pending_actions = as_get_scheduled_actions(
                array(
                    'hook'     => 'wctf_fazercards_giftcard_auto_refresh_retry',
                    'args'     => $args,
                    'group'    => 'wctf-giftcards',
                    'status'   => 'pending',
                    'per_page' => 1,
                ),
                'ids'
            );

            return is_array( $pending_actions ) && ! empty( $pending_actions );
        } catch ( Throwable $throwable ) {
            unset( $throwable );
        }
    }

    if ( function_exists( 'as_next_scheduled_action' ) && ! $ignore_current_action ) {
        $next_action = as_next_scheduled_action(
            'wctf_fazercards_giftcard_auto_refresh_retry',
            $args,
            'wctf-giftcards'
        );

        return null !== $next_action && false !== $next_action;
    }

    return false !== wp_next_scheduled(
        'wctf_fazercards_giftcard_auto_refresh_retry',
        $args
    );
}

/**
 * Schedule one Gift Card auto-refresh retry without duplicating events.
 *
 * @param WC_Order              $order      WooCommerce order.
 * @param WC_Order_Item_Product $item       WooCommerce order item.
 * @param int                   $item_id    WooCommerce order item ID.
 * @param int                   $delay      Delay in seconds.
 * @param bool                  $reset      Whether to reset exhausted/stopped attempts.
 * @param bool                  $from_worker Whether scheduling is occurring inside the running retry worker.
 * @return true|WP_Error
 */
function wctf_schedule_fazercards_giftcard_auto_refresh_retry(
    $order,
    $item,
    $item_id,
    $delay = 60,
    $reset = false,
    $from_worker = false
) {
    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        return new WP_Error(
            'wctf_giftcard_auto_refresh_context_invalid',
            __( 'A valid Gift Card order item is required before scheduling auto refresh.', 'wc-topup-fields' )
        );
    }

    $order_id = absint( $order->get_id() );
    $item_id  = absint( $item_id );
    $owned    = $order->get_item( $item_id );

    if ( ! $owned instanceof WC_Order_Item_Product || absint( $owned->get_id() ) !== absint( $item->get_id() ) ) {
        return new WP_Error(
            'wctf_giftcard_auto_refresh_item_mismatch',
            __( 'The selected order item does not belong to this order.', 'wc-topup-fields' )
        );
    }

    if ( 'giftcard' !== sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) ) ) {
        return new WP_Error(
            'wctf_giftcard_auto_refresh_not_giftcard',
            __( 'Only Gift Card order items can enter the auto-refresh queue.', 'wc-topup-fields' )
        );
    }

    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string(
        $item->get_meta( '_wctf_fazer_giftcard_remote_order_id', true ),
        191
    );

    if ( 1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id ) ) {
        return new WP_Error(
            'wctf_giftcard_auto_refresh_remote_id_invalid',
            __( 'A valid stored FazerCards remote order ID is required before scheduling auto refresh.', 'wc-topup-fields' )
        );
    }

    $crypto_status = function_exists( 'wctf_fazercards_giftcard_crypto_status' )
        ? wctf_fazercards_giftcard_crypto_status()
        : array();

    if ( empty( $crypto_status['ready'] ) ) {
        return new WP_Error(
            'wctf_giftcard_auto_refresh_crypto_not_ready',
            __( 'Gift Card encryption is not ready, so auto refresh cannot be scheduled safely.', 'wc-topup-fields' )
        );
    }

    if ( wctf_is_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
        wctf_save_fazercards_giftcard_fulfillment_state( $item, 'ready_to_deliver', '' );
        return true;
    }

    $stored_codes_count = $item->get_meta( '_wctf_fazer_giftcard_codes_count', true );
    $codes_count_value  = $item->meta_exists( '_wctf_fazer_giftcard_codes_count' )
        && is_scalar( $stored_codes_count )
        && 1 === preg_match( '/\A[0-9]+\z/D', (string) $stored_codes_count )
            ? absint( $stored_codes_count )
            : null;

    if ( null !== $codes_count_value && 0 < $codes_count_value ) {
        return new WP_Error(
            'wctf_giftcard_auto_refresh_codes_present_but_unverified',
            __( 'Gift Card codes appear to exist, but encrypted payload verification did not pass. Admin review is required.', 'wc-topup-fields' )
        );
    }

    if ( wctf_has_scheduled_fazercards_giftcard_auto_refresh_retry( $order_id, $item_id, $from_worker ) ) {
        return true;
    }

    $max_attempts = wctf_get_fazercards_giftcard_auto_refresh_max_attempts( $item );
    $attempts     = absint( $item->get_meta( '_wctf_fazer_giftcard_auto_refresh_attempts', true ) );

    if ( $reset && $attempts >= $max_attempts ) {
        $attempts = 0;
        $item->update_meta_data( '_wctf_fazer_giftcard_auto_refresh_attempts', 0 );
    }

    if ( $attempts >= $max_attempts ) {
        wctf_save_fazercards_giftcard_fulfillment_state(
            $item,
            'needs_admin_review',
            __( 'Gift Card auto-refresh attempts are exhausted.', 'wc-topup-fields' )
        );

        return new WP_Error(
            'wctf_giftcard_auto_refresh_attempts_exhausted',
            __( 'Gift Card auto-refresh attempts are exhausted. Admin review is required.', 'wc-topup-fields' )
        );
    }

    $timestamp = time() + max( 1, absint( $delay ) );
    $args      = array(
        'order_id' => $order_id,
        'item_id'  => $item_id,
    );

    try {
        if ( function_exists( 'as_schedule_single_action' ) ) {
            $scheduled_action_id = as_schedule_single_action(
                $timestamp,
                'wctf_fazercards_giftcard_auto_refresh_retry',
                $args,
                'wctf-giftcards'
            );

            if ( 1 > absint( $scheduled_action_id ) ) {
                throw new RuntimeException( 'Action Scheduler did not create the Gift Card retry action.' );
            }
        } else {
            $scheduled = wp_schedule_single_event(
                $timestamp,
                'wctf_fazercards_giftcard_auto_refresh_retry',
                $args,
                true
            );

            if ( is_wp_error( $scheduled ) || true !== $scheduled ) {
                throw new RuntimeException( 'WP-Cron did not create the Gift Card retry event.' );
            }
        }

        $item->update_meta_data( '_wctf_fazer_giftcard_fulfillment_status', 'queued' );
        $item->update_meta_data( '_wctf_fazer_giftcard_auto_refresh_max_attempts', $max_attempts );
        $item->update_meta_data(
            '_wctf_fazer_giftcard_next_refresh_at',
            sanitize_text_field( gmdate( 'Y-m-d H:i:s', $timestamp ) )
        );
        $item->delete_meta_data( '_wctf_fazer_giftcard_queue_completed_at' );
        $item->save_meta_data();
    } catch ( Throwable $throwable ) {
        unset( $throwable );

        return new WP_Error(
            'wctf_giftcard_auto_refresh_schedule_failed',
            __( 'Gift Card auto-refresh retry could not be scheduled safely.', 'wc-topup-fields' )
        );
    }

    return true;
}

/**
 * Update fulfillment after manual purchase/refresh and optionally queue retry.
 *
 * @param WC_Order $order               WooCommerce order.
 * @param int      $item_id             WooCommerce order item ID.
 * @param bool     $schedule_if_pending Whether to schedule retry when not ready.
 * @return void
 */
function wctf_maybe_update_fazercards_giftcard_fulfillment_after_refresh( $order, $item_id, $schedule_if_pending ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $item = $order->get_item( absint( $item_id ) );

    if ( ! $item instanceof WC_Order_Item_Product ) {
        return;
    }

    if ( wctf_maybe_mark_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
        wctf_add_fazercards_giftcard_auto_refresh_queue_note(
            $order,
            'completed',
            $item_id,
            __( 'ready_to_deliver', 'wc-topup-fields' )
        );
        return;
    }

    if ( ! $schedule_if_pending ) {
        return;
    }

    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string(
        $item->get_meta( '_wctf_fazer_giftcard_remote_order_id', true ),
        191
    );

    if ( 1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id ) ) {
        return;
    }

    $scheduled = wctf_schedule_fazercards_giftcard_auto_refresh_retry(
        $order,
        $item,
        $item_id,
        wctf_get_fazercards_giftcard_auto_refresh_delay( 1 ),
        false
    );

    if ( ! is_wp_error( $scheduled ) ) {
        wctf_add_fazercards_giftcard_auto_refresh_queue_note(
            $order,
            'queued',
            $item_id,
            ''
        );
    }
}

/**
 * Scheduled Gift Card auto-refresh retry worker.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return void
 */
function wctf_run_fazercards_giftcard_auto_refresh_retry( $order_id, $item_id ) {
    $order_id = absint( $order_id );
    $item_id  = absint( $item_id );
    $order    = wc_get_order( $order_id );

    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $item = $order->get_item( $item_id );

    if ( ! $item instanceof WC_Order_Item_Product ) {
        return;
    }

    if ( 'giftcard' !== sanitize_key( (string) $item->get_meta( '_wctf_fazer_item_kind', true ) ) ) {
        return;
    }

    $fast_settle_status = wctf_normalize_fazercards_giftcard_fast_settle_status(
        $item->get_meta( '_wctf_fazer_giftcard_fast_settle_status', true )
    );

    if (
        in_array( $fast_settle_status, array( 'dispatched', 'running' ), true )
        && ! wctf_is_fazercards_giftcard_fast_settle_lock_active( $order_id, $item_id )
    ) {
        $item = wctf_save_fazercards_giftcard_fast_settle_state(
            $item,
            'fallback_queued',
            null,
            ''
        );

        if ( is_wp_error( $item ) ) {
            return;
        }
    }

    $fulfillment_status = wctf_normalize_fazercards_giftcard_fulfillment_status(
        $item->get_meta( '_wctf_fazer_giftcard_fulfillment_status', true )
    );

    if ( in_array( $fulfillment_status, array( 'ready_to_deliver', 'stopped' ), true ) ) {
        return;
    }

    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string(
        $item->get_meta( '_wctf_fazer_giftcard_remote_order_id', true ),
        191
    );

    if ( 1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id ) ) {
        wctf_save_fazercards_giftcard_fulfillment_state(
            $item,
            'needs_admin_review',
            __( 'Gift Card auto-refresh stopped because the remote order ID is missing or invalid.', 'wc-topup-fields' )
        );
        return;
    }

    $has_secret      = function_exists( 'wctf_fazercards_giftcard_has_secret_payload' )
        && wctf_fazercards_giftcard_has_secret_payload( $item );
    $purchase_status = wctf_normalize_fazercards_giftcard_purchase_status(
        $item->get_meta( '_wctf_fazer_giftcard_purchase_status', true )
    );

    if ( ! $has_secret && ! in_array( $purchase_status, array( 'pending', 'purchased', 'pending_review' ), true ) ) {
        wctf_save_fazercards_giftcard_fulfillment_state(
            $item,
            'needs_admin_review',
            __( 'Gift Card auto-refresh stopped because no encrypted payload or pending remote purchase state exists.', 'wc-topup-fields' )
        );
        return;
    }

    $crypto_status = function_exists( 'wctf_fazercards_giftcard_crypto_status' )
        ? wctf_fazercards_giftcard_crypto_status()
        : array();

    if ( empty( $crypto_status['ready'] ) ) {
        wctf_save_fazercards_giftcard_fulfillment_state(
            $item,
            'needs_admin_review',
            __( 'Gift Card encryption is not ready.', 'wc-topup-fields' )
        );
        wctf_add_fazercards_giftcard_auto_refresh_queue_note(
            $order,
            'review',
            $item_id,
            __( 'Encryption is not ready.', 'wc-topup-fields' )
        );
        return;
    }

    if ( wctf_maybe_mark_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
        wctf_add_fazercards_giftcard_auto_refresh_queue_note(
            $order,
            'completed',
            $item_id,
            __( 'ready_to_deliver', 'wc-topup-fields' )
        );
        return;
    }

    $max_attempts = wctf_get_fazercards_giftcard_auto_refresh_max_attempts( $item );
    $attempts     = absint( $item->get_meta( '_wctf_fazer_giftcard_auto_refresh_attempts', true ) );

    if ( $attempts >= $max_attempts ) {
        wctf_save_fazercards_giftcard_fulfillment_state(
            $item,
            'needs_admin_review',
            __( 'Gift Card auto-refresh attempts are exhausted.', 'wc-topup-fields' )
        );
        wctf_add_fazercards_giftcard_auto_refresh_queue_note(
            $order,
            'exhausted',
            $item_id,
            ''
        );
        return;
    }

    try {
        $item->update_meta_data( '_wctf_fazer_giftcard_fulfillment_status', 'refreshing' );
        $item->update_meta_data( '_wctf_fazer_giftcard_auto_refresh_max_attempts', $max_attempts );
        $item->delete_meta_data( '_wctf_fazer_giftcard_next_refresh_at' );
        $item->save_meta_data();
    } catch ( Throwable $throwable ) {
        unset( $throwable );
        return;
    }

    $result = wctf_fazercards_giftcard_refresh_remote_order_item(
        $order,
        $item,
        $item_id,
        $remote_order_id,
        'auto_retry'
    );
    $item   = new WC_Order_Item_Product( $item_id );

    if ( ! $item instanceof WC_Order_Item_Product ) {
        return;
    }

    if ( is_array( $result ) && ! empty( $result['attempted'] ) ) {
        $attempts++;

        try {
            $item->update_meta_data( '_wctf_fazer_giftcard_auto_refresh_attempts', $attempts );
            $item->update_meta_data(
                '_wctf_fazer_giftcard_last_refresh_at',
                sanitize_text_field( current_time( 'mysql', true ) )
            );
            $item->save_meta_data();
            $item = new WC_Order_Item_Product( $item_id );
        } catch ( Throwable $throwable ) {
            unset( $throwable );
            return;
        }
    }

    if ( wctf_maybe_mark_fazercards_giftcard_ready_to_deliver( $order, $item, $item_id ) ) {
        wctf_add_fazercards_giftcard_auto_refresh_queue_note(
            $order,
            'completed',
            $item_id,
            __( 'ready_to_deliver', 'wc-topup-fields' )
        );
        return;
    }

    if (
        is_array( $result )
        && ! empty( $result['message'] )
        && isset( $result['type'] )
        && 'error' === $result['type']
    ) {
        wctf_save_fazercards_giftcard_auto_refresh_error( $item, $result['message'] );
    } elseif ( is_array( $result ) && ! empty( $result['attempted'] ) ) {
        wctf_save_fazercards_giftcard_auto_refresh_error( $item, '' );
    }

    if ( $attempts >= $max_attempts ) {
        wctf_save_fazercards_giftcard_fulfillment_state(
            $item,
            'needs_admin_review',
            __( 'Gift Card auto-refresh attempts are exhausted.', 'wc-topup-fields' )
        );
        wctf_add_fazercards_giftcard_auto_refresh_queue_note(
            $order,
            'exhausted',
            $item_id,
            ''
        );
        return;
    }

    $scheduled = wctf_schedule_fazercards_giftcard_auto_refresh_retry(
        $order,
        $item,
        $item_id,
        wctf_get_fazercards_giftcard_auto_refresh_delay( $attempts + 1 ),
        false,
        true
    );

    if ( ! is_wp_error( $scheduled ) ) {
        try {
            $item->update_meta_data( '_wctf_fazer_giftcard_fulfillment_status', 'queued' );
            $item->save_meta_data();
        } catch ( Throwable $throwable ) {
            unset( $throwable );
        }
    } else {
        wctf_save_fazercards_giftcard_fulfillment_state(
            $item,
            'queued',
            $scheduled->get_error_message()
        );
    }
}

/**
 * Build the deterministic per-item Gift Card idempotency key.
 *
 * @param WC_Order $order   WooCommerce order.
 * @param int      $item_id WooCommerce order item ID.
 * @param array    $payload Strict snapshot-derived API payload.
 * @return string
 */
function wctf_build_fazercards_giftcard_idempotency_key( $order, $item_id, $payload ) {
    if (
        ! $order instanceof WC_Order
        || 1 > absint( $item_id )
        || ! is_array( $payload )
        || empty( $payload['category_id'] )
        || empty( $payload['card_id'] )
        || empty( $payload['quantity'] )
    ) {
        return '';
    }

    $fixed_payload = array(
        'category_id' => sanitize_text_field( (string) $payload['category_id'] ),
        'card_id'     => sanitize_text_field( (string) $payload['card_id'] ),
        'quantity'    => absint( $payload['quantity'] ),
    );
    $payload_json  = wp_json_encode( $fixed_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

    if ( false === $payload_json || '' === $payload_json ) {
        return '';
    }

    $normalized_home = strtolower( untrailingslashit( home_url( '/' ) ) );
    $site_hash       = substr( hash( 'sha256', $normalized_home ), 0, 12 );
    $payload_hash    = substr( hash( 'sha256', $payload_json ), 0, 24 );
    $key             = sprintf(
        'wctf_gc_%s_%d_%d_%s',
        $site_hash,
        absint( $order->get_id() ),
        absint( $item_id ),
        $payload_hash
    );

    return 255 >= strlen( $key ) ? $key : '';
}

/**
 * Store or verify the stable idempotency key before any remote call.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order line item.
 * @param int                   $item_id WooCommerce order item ID.
 * @param array                 $payload Strict snapshot-derived API payload.
 * @return array|WP_Error
 */
function wctf_persist_fazercards_giftcard_idempotency_key( $order, $item, $item_id, $payload ) {
    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        return new WP_Error(
            'wctf_giftcard_idempotency_context_invalid',
            __( 'A stable Gift Card idempotency key could not be saved.', 'wc-topup-fields' )
        );
    }

    $expected_key = wctf_build_fazercards_giftcard_idempotency_key( $order, $item_id, $payload );
    $stored_key   = wctf_limit_fazercards_giftcard_purchase_string(
        $item->get_meta( '_wctf_fazer_giftcard_idempotency_key', true ),
        255
    );

    if ( '' === $expected_key ) {
        return new WP_Error(
            'wctf_giftcard_idempotency_generation_failed',
            __( 'A stable Gift Card idempotency key could not be generated.', 'wc-topup-fields' )
        );
    }

    if ( '' !== $stored_key && ! hash_equals( $expected_key, $stored_key ) ) {
        return new WP_Error(
            'wctf_giftcard_idempotency_mismatch',
            __( 'The stored Gift Card idempotency key does not match the snapshotted payload.', 'wc-topup-fields' )
        );
    }

    try {
        if ( '' === $stored_key ) {
            $item->update_meta_data( '_wctf_fazer_giftcard_idempotency_key', $expected_key );
            $item->save_meta_data();
        }

        $stored_item = new WC_Order_Item_Product( absint( $item_id ) );
        $read_back   = wctf_limit_fazercards_giftcard_purchase_string(
            $stored_item->get_meta( '_wctf_fazer_giftcard_idempotency_key', true ),
            255
        );

        if ( '' === $read_back || ! hash_equals( $expected_key, $read_back ) ) {
            throw new RuntimeException( 'Gift Card idempotency key persistence verification failed.' );
        }
    } catch ( Throwable $throwable ) {
        unset( $throwable );

        return new WP_Error(
            'wctf_giftcard_idempotency_persistence_failed',
            __( 'The Gift Card idempotency key could not be persisted safely. No remote purchase was attempted.', 'wc-topup-fields' )
        );
    }

    return array(
        'item' => $stored_item,
        'key'  => $read_back,
    );
}

/**
 * Save and read back one safe Gift Card purchase state.
 *
 * @param WC_Order_Item_Product $item            WooCommerce order line item.
 * @param string                $status          Local purchase status.
 * @param string                $remote_order_id Safe remote order ID.
 * @param string                $remote_status   Safe remote status.
 * @param string                $error           Safe short error.
 * @param bool                  $set_timestamp   Whether to record the accepted purchase time.
 * @return WC_Order_Item_Product|WP_Error
 */
function wctf_save_fazercards_giftcard_purchase_state( $item, $status, $remote_order_id = '', $remote_status = '', $error = '', $set_timestamp = false ) {
    if ( ! $item instanceof WC_Order_Item_Product || 1 > absint( $item->get_id() ) ) {
        return new WP_Error(
            'wctf_giftcard_purchase_state_invalid',
            __( 'The Gift Card purchase state could not be saved.', 'wc-topup-fields' )
        );
    }

    $item_id         = absint( $item->get_id() );
    $status          = wctf_normalize_fazercards_giftcard_purchase_status( $status );
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );
    $remote_status   = wctf_limit_fazercards_giftcard_purchase_string( $remote_status, 100 );
    $error           = wctf_sanitize_fazercards_giftcard_purchase_error( $error, 500 );

    try {
        $item->update_meta_data( '_wctf_fazer_giftcard_purchase_status', $status );

        if ( '' === $remote_order_id ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_remote_order_id' );
        } else {
            $item->update_meta_data( '_wctf_fazer_giftcard_remote_order_id', $remote_order_id );
        }

        if ( '' === $remote_status ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_remote_status' );
        } else {
            $item->update_meta_data( '_wctf_fazer_giftcard_remote_status', $remote_status );
        }

        if ( '' === $error ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_last_error' );
        } else {
            $item->update_meta_data( '_wctf_fazer_giftcard_last_error', $error );
        }

        if ( $set_timestamp ) {
            $item->update_meta_data(
                '_wctf_fazer_giftcard_purchased_at',
                sanitize_text_field( current_time( 'mysql', true ) )
            );
        } elseif ( in_array( $status, array( 'not_purchased', 'purchasing', 'failed', 'failed_uncertain' ), true ) ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_purchased_at' );
        }

        $item->save_meta_data();

        $stored_item   = new WC_Order_Item_Product( $item_id );
        $stored_status = wctf_normalize_fazercards_giftcard_purchase_status(
            $stored_item->get_meta( '_wctf_fazer_giftcard_purchase_status', true )
        );

        if ( ! hash_equals( $status, $stored_status ) ) {
            throw new RuntimeException( 'Gift Card purchase status persistence verification failed.' );
        }
    } catch ( Throwable $throwable ) {
        unset( $throwable );

        return new WP_Error(
            'wctf_giftcard_purchase_state_persistence_failed',
            __( 'The Gift Card purchase state could not be persisted safely. No new remote call should be made.', 'wc-topup-fields' )
        );
    }

    return $stored_item;
}

/**
 * Return the separate Gift Card purchase lock option name.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_purchase_lock_key( $order_id, $item_id ) {
    return 'wctf_fazer_giftcard_purchase_lock_' . absint( $order_id ) . '_' . absint( $item_id );
}

/**
 * Determine whether a non-stale Gift Card purchase lock exists.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return bool
 */
function wctf_is_fazercards_giftcard_purchase_lock_active( $order_id, $item_id ) {
    $lock = get_option(
        wctf_get_fazercards_giftcard_purchase_lock_key( $order_id, $item_id ),
        false
    );

    if ( false === $lock ) {
        return false;
    }

    $expires = is_array( $lock ) && isset( $lock['expires'] )
        ? absint( $lock['expires'] )
        : 0;

    return 0 !== $expires && $expires > time();
}

/**
 * Acquire an atomic five-minute per-item Gift Card purchase lock.
 *
 * A stale lock is removed, but is never reacquired in the same request.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string|WP_Error Lock token or a safe error.
 */
function wctf_acquire_fazercards_giftcard_purchase_lock( $order_id, $item_id ) {
    $lock_key = wctf_get_fazercards_giftcard_purchase_lock_key( $order_id, $item_id );
    $now      = time();
    $token    = wp_generate_uuid4();
    $lock     = array(
        'created' => $now,
        'expires' => $now + ( 5 * MINUTE_IN_SECONDS ),
        'token'   => $token,
    );

    if ( add_option( $lock_key, $lock, '', 'no' ) ) {
        return $token;
    }

    $existing = get_option( $lock_key, array() );
    $expires  = is_array( $existing ) && isset( $existing['expires'] )
        ? absint( $existing['expires'] )
        : 0;

    if ( 0 !== $expires && $expires > $now ) {
        return new WP_Error(
            'wctf_giftcard_purchase_lock_held',
            __( 'Another administrator is already processing this Gift Card item.', 'wc-topup-fields' )
        );
    }

    delete_option( $lock_key );

    return new WP_Error(
        'wctf_giftcard_purchase_stale_lock_removed',
        __( 'A stale Gift Card purchase lock was cleared. Refresh the order and try again.', 'wc-topup-fields' )
    );
}

/**
 * Release only a Gift Card purchase lock owned by the supplied token.
 *
 * @param int    $order_id WooCommerce order ID.
 * @param int    $item_id  WooCommerce order item ID.
 * @param string $token    Lock ownership token.
 * @return void
 */
function wctf_release_fazercards_giftcard_purchase_lock( $order_id, $item_id, $token ) {
    if ( ! is_string( $token ) || '' === $token ) {
        return;
    }

    $lock_key = wctf_get_fazercards_giftcard_purchase_lock_key( $order_id, $item_id );
    $existing = get_option( $lock_key, array() );

    if (
        is_array( $existing )
        && isset( $existing['token'] )
        && is_string( $existing['token'] )
        && hash_equals( $existing['token'], $token )
    ) {
        delete_option( $lock_key );
    }
}

/**
 * Return the separate Gift Card remote refresh lock option name.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_refresh_lock_key( $order_id, $item_id ) {
    return 'wctf_fazer_giftcard_refresh_lock_' . absint( $order_id ) . '_' . absint( $item_id );
}

/**
 * Acquire an atomic five-minute per-item Gift Card remote refresh lock.
 *
 * A stale lock is removed, but is never reacquired in the same request.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string|WP_Error Lock token or a safe error.
 */
function wctf_acquire_fazercards_giftcard_refresh_lock( $order_id, $item_id ) {
    $lock_key = wctf_get_fazercards_giftcard_refresh_lock_key( $order_id, $item_id );
    $now      = time();
    $token    = wp_generate_uuid4();
    $lock     = array(
        'created' => $now,
        'expires' => $now + ( 5 * MINUTE_IN_SECONDS ),
        'token'   => $token,
    );

    if ( add_option( $lock_key, $lock, '', 'no' ) ) {
        return $token;
    }

    $existing = get_option( $lock_key, array() );
    $expires  = is_array( $existing ) && isset( $existing['expires'] )
        ? absint( $existing['expires'] )
        : 0;

    if ( 0 !== $expires && $expires > $now ) {
        return new WP_Error(
            'wctf_giftcard_refresh_lock_held',
            __( 'Another administrator is already refreshing this Gift Card remote order.', 'wc-topup-fields' )
        );
    }

    delete_option( $lock_key );

    return new WP_Error(
        'wctf_giftcard_refresh_stale_lock_removed',
        __( 'A stale Gift Card refresh lock was cleared. Refresh the order and try again.', 'wc-topup-fields' )
    );
}

/**
 * Release only a Gift Card remote refresh lock owned by the supplied token.
 *
 * @param int    $order_id WooCommerce order ID.
 * @param int    $item_id  WooCommerce order item ID.
 * @param string $token    Lock ownership token.
 * @return void
 */
function wctf_release_fazercards_giftcard_refresh_lock( $order_id, $item_id, $token ) {
    if ( ! is_string( $token ) || '' === $token ) {
        return;
    }

    $lock_key = wctf_get_fazercards_giftcard_refresh_lock_key( $order_id, $item_id );
    $existing = get_option( $lock_key, array() );

    if (
        is_array( $existing )
        && isset( $existing['token'] )
        && is_string( $existing['token'] )
        && hash_equals( $existing['token'], $token )
    ) {
        delete_option( $lock_key );
    }
}

/**
 * Save safe Gift Card refresh success metadata.
 *
 * @param WC_Order_Item_Product $item            WooCommerce order line item.
 * @param string                $status          Local purchase status after refresh.
 * @param string                $remote_order_id Safe remote order ID.
 * @param string                $remote_status   Safe remote status.
 * @return WC_Order_Item_Product|WP_Error
 */
function wctf_save_fazercards_giftcard_refresh_success_state( $item, $status, $remote_order_id, $remote_status ) {
    if ( ! $item instanceof WC_Order_Item_Product || 1 > absint( $item->get_id() ) ) {
        return new WP_Error(
            'wctf_giftcard_refresh_state_invalid',
            __( 'The Gift Card refresh state could not be saved.', 'wc-topup-fields' )
        );
    }

    $item_id         = absint( $item->get_id() );
    $status          = wctf_normalize_fazercards_giftcard_purchase_status( $status );
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );
    $remote_status   = wctf_limit_fazercards_giftcard_purchase_string( $remote_status, 100 );

    try {
        $item->update_meta_data( '_wctf_fazer_giftcard_purchase_status', $status );
        $item->update_meta_data( '_wctf_fazer_giftcard_remote_order_id', $remote_order_id );

        if ( '' === $remote_status ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_remote_status' );
        } else {
            $item->update_meta_data( '_wctf_fazer_giftcard_remote_status', $remote_status );
        }

        $item->update_meta_data(
            '_wctf_fazer_giftcard_last_checked_at',
            sanitize_text_field( current_time( 'mysql', true ) )
        );
        $item->delete_meta_data( '_wctf_fazer_giftcard_last_error' );
        $item->save_meta_data();

        $stored_item   = new WC_Order_Item_Product( $item_id );
        $stored_status = wctf_normalize_fazercards_giftcard_purchase_status(
            $stored_item->get_meta( '_wctf_fazer_giftcard_purchase_status', true )
        );

        if ( ! hash_equals( $status, $stored_status ) ) {
            throw new RuntimeException( 'Gift Card refresh status persistence verification failed.' );
        }
    } catch ( Throwable $throwable ) {
        unset( $throwable );

        return new WP_Error(
            'wctf_giftcard_refresh_state_persistence_failed',
            __( 'The Gift Card refresh state could not be persisted safely.', 'wc-topup-fields' )
        );
    }

    return $stored_item;
}

/**
 * Save a safe short refresh error while preserving existing encrypted payload.
 *
 * @param WC_Order_Item_Product $item  WooCommerce order line item.
 * @param string                $error Safe short error.
 * @return void
 */
function wctf_save_fazercards_giftcard_refresh_error( $item, $error ) {
    if ( ! $item instanceof WC_Order_Item_Product || 1 > absint( $item->get_id() ) ) {
        return;
    }

    $error = wctf_sanitize_fazercards_giftcard_purchase_error( $error, 500 );

    try {
        if ( '' === $error ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_last_error' );
        } else {
            $item->update_meta_data( '_wctf_fazer_giftcard_last_error', $error );
        }

        $item->save_meta_data();
    } catch ( Throwable $throwable ) {
        unset( $throwable );
    }
}

/**
 * Add a private order note containing only safe Gift Card purchase fields.
 *
 * @param WC_Order $order           WooCommerce order.
 * @param string   $type            Note type.
 * @param int      $item_id         WooCommerce order item ID.
 * @param string   $remote_order_id Safe remote order ID.
 * @param string   $remote_status   Safe remote status.
 * @param string   $error           Safe short error.
 * @return void
 */
function wctf_add_fazercards_giftcard_purchase_note( $order, $type, $item_id, $remote_order_id = '', $remote_status = '', $error = '' ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $type            = sanitize_key( $type );
    $item_id         = absint( $item_id );
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );
    $remote_status   = wctf_limit_fazercards_giftcard_purchase_string( $remote_status, 100 );
    $error           = wctf_sanitize_fazercards_giftcard_purchase_error( $error, 500 );

    if ( 'started' === $type ) {
        $note = sprintf(
            /* translators: %d: WooCommerce order item ID. */
            __( 'FazerCards Gift Card purchase started for item #%d.', 'wc-topup-fields' ),
            $item_id
        );
    } elseif ( 'purchased' === $type ) {
        $note = sprintf(
            /* translators: 1: item ID, 2: remote order ID, 3: remote status. */
            __( "FazerCards Gift Card purchase completed for item #%1\$d.\nRemote order: %2\$s\nRemote status: %3\$s", 'wc-topup-fields' ),
            $item_id,
            $remote_order_id,
            $remote_status
        );
    } elseif ( in_array( $type, array( 'pending', 'pending_review' ), true ) ) {
        $note = sprintf(
            /* translators: 1: item ID, 2: remote order ID, 3: remote status. */
            __( "FazerCards Gift Card purchase requires follow-up for item #%1\$d.\nRemote order: %2\$s\nRemote status: %3\$s", 'wc-topup-fields' ),
            $item_id,
            $remote_order_id,
            $remote_status
        );
    } elseif ( 'storage_failed' === $type ) {
        $note = sprintf(
            /* translators: 1: item ID, 2: remote order ID, 3: remote status. */
            __( "FazerCards Gift Card encrypted storage failed after a remote response for item #%1\$d.\nRemote order: %2\$s\nRemote status: %3\$s\nThe remote purchase may have succeeded. Do not purchase again; recovery is required.", 'wc-topup-fields' ),
            $item_id,
            $remote_order_id,
            $remote_status
        );
    } elseif ( 'uncertain' === $type ) {
        $note = sprintf(
            /* translators: %d: WooCommerce order item ID. */
            __( 'FazerCards Gift Card purchase outcome is uncertain for item #%d. Do not purchase again; recovery is required.', 'wc-topup-fields' ),
            $item_id
        );
    } else {
        $note = sprintf(
            /* translators: 1: item ID, 2: safe short error. */
            __( "FazerCards Gift Card purchase failed for item #%1\$d.\nError: %2\$s", 'wc-topup-fields' ),
            $item_id,
            $error
        );
    }

    $order->add_order_note( $note, 0, false );
}

/**
 * Add a private order note containing only safe Gift Card refresh fields.
 *
 * @param WC_Order $order           WooCommerce order.
 * @param int      $item_id         WooCommerce order item ID.
 * @param string   $remote_order_id Safe remote order ID.
 * @param string   $remote_status   Safe remote status.
 * @param string   $status          Local purchase status after refresh.
 * @return void
 */
function wctf_add_fazercards_giftcard_refresh_note( $order, $item_id, $remote_order_id, $remote_status, $status ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $item_id         = absint( $item_id );
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );
    $remote_status   = wctf_limit_fazercards_giftcard_purchase_string( $remote_status, 100 );
    $status          = wctf_normalize_fazercards_giftcard_purchase_status( $status );
    $note            = sprintf(
        /* translators: 1: item ID, 2: remote order ID, 3: remote status, 4: local status. */
        __( "FazerCards Gift Card remote order refreshed for item #%1\$d.\nRemote order: %2\$s\nRemote status: %3\$s\nLocal status: %4\$s", 'wc-topup-fields' ),
        $item_id,
        $remote_order_id,
        $remote_status,
        $status
    );

    $order->add_order_note( $note, 0, false );
}

/**
 * Add a private order note for the single automatic post-purchase refresh.
 *
 * @param WC_Order $order           WooCommerce order.
 * @param int      $item_id         WooCommerce order item ID.
 * @param string   $remote_order_id Safe remote order ID.
 * @param string   $result          Safe refresh result label.
 * @return void
 */
function wctf_add_fazercards_giftcard_auto_refresh_note( $order, $item_id, $remote_order_id, $result ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $item_id         = absint( $item_id );
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );
    $result          = wctf_limit_fazercards_giftcard_purchase_string( $result, 80 );
    $note            = sprintf(
        /* translators: 1: item ID, 2: remote order ID, 3: refresh result. */
        __( "Automatic post-purchase Gift Card refresh attempted for item #%1\$d.\nRemote order: %2\$s\nResult: %3\$s", 'wc-topup-fields' ),
        $item_id,
        $remote_order_id,
        $result
    );

    $order->add_order_note( $note, 0, false );
}

/**
 * Add a private lifecycle note for the Gift Card auto-refresh queue.
 *
 * @param WC_Order $order   WooCommerce order.
 * @param string   $type    Lifecycle note type.
 * @param int      $item_id WooCommerce order item ID.
 * @param string   $detail  Safe detail.
 * @return void
 */
function wctf_add_fazercards_giftcard_auto_refresh_queue_note( $order, $type, $item_id, $detail = '' ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $type    = sanitize_key( $type );
    $item_id = absint( $item_id );
    $detail  = wctf_limit_fazercards_giftcard_purchase_string( $detail, 120 );

    if ( 'queued' === $type ) {
        $note = sprintf(
            /* translators: %d: WooCommerce order item ID. */
            __( 'Gift Card auto-refresh queued for item #%d.', 'wc-topup-fields' ),
            $item_id
        );
    } elseif ( 'completed' === $type ) {
        $note = sprintf(
            /* translators: 1: item ID, 2: safe result. */
            __( "Gift Card auto-refresh completed for item #%1\$d.\nResult: %2\$s", 'wc-topup-fields' ),
            $item_id,
            '' !== $detail ? $detail : 'ready_to_deliver'
        );
    } elseif ( 'exhausted' === $type ) {
        $note = sprintf(
            /* translators: %d: WooCommerce order item ID. */
            __( 'Gift Card auto-refresh exhausted for item #%d. Admin review required.', 'wc-topup-fields' ),
            $item_id
        );
    } elseif ( 'review' === $type ) {
        $note = sprintf(
            /* translators: 1: item ID, 2: safe detail. */
            __( "Gift Card auto-refresh requires admin review for item #%1\$d.\n%2\$s", 'wc-topup-fields' ),
            $item_id,
            $detail
        );
    } else {
        $note = sprintf(
            /* translators: 1: item ID, 2: safe detail. */
            __( "Gift Card auto-refresh still pending for item #%1\$d.\n%2\$s", 'wc-topup-fields' ),
            $item_id,
            $detail
        );
    }

    $order->add_order_note( $note, 0, false );
}

/**
 * Build a safe admin result message for a stored Gift Card order.
 *
 * @param string $status          Local purchase status.
 * @param string $remote_order_id Safe remote order ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_purchase_result_message( $status, $remote_order_id ) {
    $status          = wctf_normalize_fazercards_giftcard_purchase_status( $status );
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );

    if ( 'purchased' === $status ) {
        return '' !== $remote_order_id
            ? sprintf(
                /* translators: %s: safe remote order ID. */
                __( 'Gift Card purchase completed and stored securely. Remote order: %s.', 'wc-topup-fields' ),
                $remote_order_id
            )
            : __( 'Gift Card purchase completed and was stored securely.', 'wc-topup-fields' );
    }

    if ( 'pending' === $status ) {
        return __( 'The Gift Card order was stored securely and is pending. No automatic polling will occur.', 'wc-topup-fields' );
    }

    return __( 'The Gift Card order was stored securely but requires manual review.', 'wc-topup-fields' );
}

/**
 * Build a safe admin result message for a refreshed Gift Card order.
 *
 * @param string $status          Local purchase status.
 * @param string $remote_order_id Safe remote order ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_refresh_result_message( $status, $remote_order_id ) {
    $status          = wctf_normalize_fazercards_giftcard_purchase_status( $status );
    $remote_order_id = wctf_limit_fazercards_giftcard_purchase_string( $remote_order_id, 191 );

    if ( 'purchased' === $status ) {
        return '' !== $remote_order_id
            ? sprintf(
                /* translators: %s: safe remote order ID. */
                __( 'Gift Card remote order refreshed and stored securely. Codes may now be available through Reveal. Remote order: %s.', 'wc-topup-fields' ),
                $remote_order_id
            )
            : __( 'Gift Card remote order refreshed and stored securely. Codes may now be available through Reveal.', 'wc-topup-fields' );
    }

    if ( 'pending' === $status ) {
        return __( 'Gift Card remote order refreshed and stored securely, but it is still pending. Reveal can inspect the latest stored response.', 'wc-topup-fields' );
    }

    return __( 'Gift Card remote order refreshed and stored securely, but it requires manual review. Reveal can inspect the latest stored response.', 'wc-topup-fields' );
}

/**
 * Save a short, user- and order-scoped result then redirect to the order.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param array    $result Safe result fields.
 * @return void
 */
function wctf_finish_fazercards_giftcard_purchase_action( $order, $result ) {
    if ( ! $order instanceof WC_Order ) {
        wp_die(
            esc_html__( 'The WooCommerce order could not be loaded.', 'wc-topup-fields' ),
            esc_html__( 'Order not found', 'wc-topup-fields' ),
            array( 'response' => 404 )
        );
    }

    $result_type = isset( $result['result_type'] )
        ? sanitize_key( (string) $result['result_type'] )
        : 'error';

    if ( ! in_array( $result_type, array( 'success', 'warning', 'error' ), true ) ) {
        $result_type = 'error';
    }

    $safe_result = array(
        'result_type' => $result_type,
        'item_id'     => isset( $result['item_id'] ) ? absint( $result['item_id'] ) : 0,
        'status'      => wctf_normalize_fazercards_giftcard_purchase_status(
            isset( $result['status'] ) ? $result['status'] : ''
        ),
        'message'     => wctf_limit_fazercards_giftcard_purchase_string(
            isset( $result['message'] ) ? $result['message'] : '',
            500
        ),
    );

    set_transient(
        wctf_get_fazercards_giftcard_purchase_result_transient_key(
            $order->get_id(),
            get_current_user_id()
        ),
        $safe_result,
        5 * MINUTE_IN_SECONDS
    );

    wp_safe_redirect( $order->get_edit_order_url() );
    exit;
}

/**
 * Save a short, user- and order-scoped refresh result then redirect to the order.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param array    $result Safe result fields.
 * @return void
 */
function wctf_finish_fazercards_giftcard_refresh_action( $order, $result ) {
    if ( ! $order instanceof WC_Order ) {
        wp_die(
            esc_html__( 'The WooCommerce order could not be loaded.', 'wc-topup-fields' ),
            esc_html__( 'Order not found', 'wc-topup-fields' ),
            array( 'response' => 404 )
        );
    }

    $type = isset( $result['type'] )
        ? sanitize_key( (string) $result['type'] )
        : 'error';

    if ( ! in_array( $type, array( 'success', 'warning', 'error' ), true ) ) {
        $type = 'error';
    }

    $safe_result = array(
        'type'    => $type,
        'item_id' => isset( $result['item_id'] ) ? absint( $result['item_id'] ) : 0,
        'message' => wctf_limit_fazercards_giftcard_purchase_string(
            isset( $result['message'] ) ? $result['message'] : '',
            500
        ),
    );

    set_transient(
        wctf_get_fazercards_giftcard_refresh_result_transient_key(
            $order->get_id(),
            get_current_user_id()
        ),
        $safe_result,
        5 * MINUTE_IN_SECONDS
    );

    wp_safe_redirect( $order->get_edit_order_url() );
    exit;
}

/**
 * Return the user- and order-isolated Gift Card result transient key.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $user_id  WordPress user ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_purchase_result_transient_key( $order_id, $user_id ) {
    return 'wctf_fazer_gc_purchase_' . absint( $user_id ) . '_' . absint( $order_id );
}

/**
 * Return the user- and order-isolated Gift Card refresh result transient key.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $user_id  WordPress user ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_refresh_result_transient_key( $order_id, $user_id ) {
    return 'wctf_fazer_gc_refresh_' . absint( $user_id ) . '_' . absint( $order_id );
}

/**
 * Sanitize and length-limit one non-sensitive scalar for admin storage/output.
 *
 * @param mixed $value      Raw scalar.
 * @param int   $max_length Maximum characters.
 * @return string
 */
function wctf_limit_fazercards_giftcard_purchase_string( $value, $max_length ) {
    if ( ! is_scalar( $value ) ) {
        return '';
    }

    $value      = sanitize_text_field( (string) $value );
    $max_length = max( 1, absint( $max_length ) );

    if ( function_exists( 'mb_substr' ) ) {
        return mb_substr( $value, 0, $max_length, 'UTF-8' );
    }

    return substr( $value, 0, $max_length );
}

/**
 * Sanitize, length-limit and redact configured credentials from an error.
 *
 * @param mixed $value      Raw error value.
 * @param int   $max_length Maximum characters.
 * @return string
 */
function wctf_sanitize_fazercards_giftcard_purchase_error( $value, $max_length = 500 ) {
    $error  = wctf_limit_fazercards_giftcard_purchase_string( $value, $max_length );
    $config = function_exists( 'wctf_config' ) ? wctf_config() : array();

    foreach ( array( 'api_key', 'api_secret' ) as $credential_key ) {
        if (
            isset( $config[ $credential_key ] )
            && is_scalar( $config[ $credential_key ] )
            && '' !== (string) $config[ $credential_key ]
        ) {
            $error = str_replace(
                (string) $config[ $credential_key ],
                '[redacted credential]',
                $error
            );
        }
    }

    return wctf_limit_fazercards_giftcard_purchase_string( $error, $max_length );
}
