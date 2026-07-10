<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action(
    'admin_post_wctf_fazercards_giftcard_reveal_item',
    'wctf_handle_fazercards_giftcard_reveal_item'
);

/**
 * Handle one explicitly confirmed Gift Card secret reveal.
 *
 * @return void
 */
function wctf_handle_fazercards_giftcard_reveal_item() {
    if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
        wp_die(
            esc_html__( 'Gift Card secrets can only be revealed by a confirmed POST request.', 'wc-topup-fields' ),
            esc_html__( 'Invalid reveal request', 'wc-topup-fields' ),
            array( 'response' => 405 )
        );
    }

    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] )
        ? absint( wp_unslash( $_POST['order_id'] ) )
        : 0;
    $item_id  = isset( $_POST['item_id'] ) && is_scalar( $_POST['item_id'] )
        ? absint( wp_unslash( $_POST['item_id'] ) )
        : 0;

    if ( 1 > $order_id || 1 > $item_id ) {
        wp_die(
            esc_html__( 'A valid order and order item are required.', 'wc-topup-fields' ),
            esc_html__( 'Invalid reveal request', 'wc-topup-fields' ),
            array( 'response' => 400 )
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

    if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order_id ) ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'You are not allowed to reveal Gift Card secrets for this order.', 'wc-topup-fields' ),
            )
        );
    }

    $nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
        : '';

    if (
        '' === $nonce
        || ! wp_verify_nonce(
            $nonce,
            'wctf_reveal_fazercards_giftcard_' . $order_id . '_' . $item_id
        )
    ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'The Gift Card reveal request reached the server, but the security nonce was invalid.', 'wc-topup-fields' ),
            )
        );
    }

    $confirmed = isset( $_POST['reveal_confirmed'] ) && is_string( $_POST['reveal_confirmed'] )
        ? sanitize_text_field( wp_unslash( $_POST['reveal_confirmed'] ) )
        : '';
    $confirmation_text = isset( $_POST['reveal_confirmation_text'] ) && is_string( $_POST['reveal_confirmation_text'] )
        ? wp_unslash( $_POST['reveal_confirmation_text'] )
        : '';

    if ( '1' !== $confirmed || 'REVEAL' !== $confirmation_text ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'Both the checkbox and exact REVEAL confirmation are required. No Gift Card secret was revealed.', 'wc-topup-fields' ),
            )
        );
    }

    $item = $order->get_item( $item_id );

    if ( ! $item instanceof WC_Order_Item_Product ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'The selected order item does not belong to this order.', 'wc-topup-fields' ),
            )
        );
    }

    if ( ! function_exists( 'wctf_fazercards_giftcard_has_secret_payload' ) || ! wctf_fazercards_giftcard_has_secret_payload( $item ) ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'No encrypted Gift Card secret payload is stored for this order item.', 'wc-topup-fields' ),
            )
        );
    }

    if ( ! function_exists( 'wctf_fazercards_giftcard_crypto_status' ) ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'Gift Card encrypted-storage helpers are unavailable.', 'wc-topup-fields' ),
            )
        );
    }

    $crypto_status = wctf_fazercards_giftcard_crypto_status();

    if ( empty( $crypto_status['ready'] ) ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'Encrypted Gift Card secret storage is not ready.', 'wc-topup-fields' ),
            )
        );
    }

    if ( ! function_exists( 'wctf_fazercards_giftcard_get_secret_payload_wrapper' ) ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'Gift Card reveal helpers are unavailable.', 'wc-topup-fields' ),
            )
        );
    }

    $wrapper = wctf_fazercards_giftcard_get_secret_payload_wrapper( $item );

    if ( is_wp_error( $wrapper ) || ! wctf_is_fazercards_giftcard_reveal_wrapper_valid( $wrapper, $order_id, $item_id ) ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'The encrypted Gift Card payload could not be authenticated for this order item.', 'wc-topup-fields' ),
            )
        );
    }

    $item = wctf_record_fazercards_giftcard_secret_reveal( $order, $item, $item_id );

    if ( is_wp_error( $item ) ) {
        wctf_finish_fazercards_giftcard_reveal_action(
            $order,
            array(
                'type'    => 'error',
                'item_id' => $item_id,
                'message' => __( 'The Gift Card secret reveal could not be audited safely.', 'wc-topup-fields' ),
            )
        );
    }

    wctf_render_fazercards_giftcard_secret_reveal_page( $order, $item, $item_id, $wrapper );
}

/**
 * Validate the decrypted Gift Card secret wrapper.
 *
 * @param mixed $wrapper  Decrypted wrapper.
 * @param int   $order_id WooCommerce order ID.
 * @param int   $item_id  WooCommerce order item ID.
 * @return bool
 */
function wctf_is_fazercards_giftcard_reveal_wrapper_valid( $wrapper, $order_id, $item_id ) {
    return is_array( $wrapper )
        && isset(
            $wrapper['schema'],
            $wrapper['woocommerce_order_id'],
            $wrapper['woocommerce_order_item_id'],
            $wrapper['order']
        )
        && 'wctf-giftcard-secret-v1' === $wrapper['schema']
        && absint( $order_id ) === absint( $wrapper['woocommerce_order_id'] )
        && absint( $item_id ) === absint( $wrapper['woocommerce_order_item_id'] )
        && is_array( $wrapper['order'] )
        && ! empty( $wrapper['order'] );
}

/**
 * Record safe reveal audit metadata and a private order note.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order item.
 * @param int                   $item_id WooCommerce order item ID.
 * @return WC_Order_Item_Product|WP_Error
 */
function wctf_record_fazercards_giftcard_secret_reveal( $order, $item, $item_id ) {
    if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
        return new WP_Error(
            'wctf_giftcard_reveal_context_invalid',
            __( 'A valid WooCommerce order item is required.', 'wc-topup-fields' )
        );
    }

    $stored_count = $item->get_meta( '_wctf_fazer_giftcard_codes_reveal_count', true );
    $count        = is_scalar( $stored_count ) && 1 === preg_match( '/\A[0-9]+\z/D', (string) $stored_count )
        ? absint( $stored_count )
        : 0;
    $user_id      = get_current_user_id();

    try {
        $item->update_meta_data( '_wctf_fazer_giftcard_codes_revealed_at', sanitize_text_field( current_time( 'mysql', true ) ) );
        $item->update_meta_data( '_wctf_fazer_giftcard_codes_reveal_count', $count + 1 );
        $item->update_meta_data( '_wctf_fazer_giftcard_last_revealed_by_user_id', absint( $user_id ) );
        $item->save_meta_data();

        $order->add_order_note(
            sprintf(
                /* translators: 1: order item ID, 2: user ID. */
                __( 'Gift Card secret payload revealed for item #%1$d by user #%2$d.', 'wc-topup-fields' ),
                absint( $item_id ),
                absint( $user_id )
            ),
            0,
            false
        );
    } catch ( Throwable $throwable ) {
        unset( $throwable );

        return new WP_Error(
            'wctf_giftcard_reveal_audit_failed',
            __( 'The Gift Card secret reveal audit could not be saved.', 'wc-topup-fields' )
        );
    }

    return $item;
}

/**
 * Render the standalone, no-store admin reveal page.
 *
 * @param WC_Order              $order   WooCommerce order.
 * @param WC_Order_Item_Product $item    WooCommerce order item.
 * @param int                   $item_id WooCommerce order item ID.
 * @param array                 $wrapper Decrypted, validated wrapper.
 * @return void
 */
function wctf_render_fazercards_giftcard_secret_reveal_page( $order, $item, $item_id, $wrapper ) {
    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
    header( 'Pragma: no-cache', true );
    header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT', true );
    header( 'X-Robots-Tag: noindex, nofollow', true );

    $order_payload = isset( $wrapper['order'] ) && is_array( $wrapper['order'] )
        ? $wrapper['order']
        : array();
    $remote        = wctf_get_fazercards_giftcard_reveal_remote_summary( $order_payload );
    $back_url      = $order instanceof WC_Order ? $order->get_edit_order_url() : admin_url( 'edit.php?post_type=shop_order' );
    $product_name  = $item instanceof WC_Order_Item_Product ? sanitize_text_field( $item->get_name() ) : '';
    $captured_at   = isset( $wrapper['captured_at_utc'] ) && is_scalar( $wrapper['captured_at_utc'] )
        ? sanitize_text_field( (string) $wrapper['captured_at_utc'] )
        : '';
    $sections      = wctf_get_fazercards_giftcard_reveal_sensitive_sections( $order_payload );

    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title><?php echo esc_html__( 'FazerCards Gift Card Secret Reveal', 'wc-topup-fields' ); ?></title>
        <?php wp_admin_css( 'forms' ); ?>
        <?php wp_admin_css( 'common' ); ?>
        <style>
            body { background: #f0f0f1; padding: 24px; }
            .wctf-reveal-wrap { max-width: 1100px; margin: 0 auto; background: #fff; border: 1px solid #c3c4c7; padding: 24px; }
            .wctf-reveal-warning { border-left: 4px solid #d63638; background: #fcf0f1; padding: 12px; margin: 16px 0; }
            .wctf-reveal-secret { background: #101517; color: #f6f7f7; padding: 12px; overflow: auto; white-space: pre-wrap; }
            .wctf-reveal-card { margin: 16px 0; }
        </style>
    </head>
    <body>
        <main class="wctf-reveal-wrap" role="main">
            <h1><?php echo esc_html__( 'FazerCards Gift Card Secret Reveal', 'wc-topup-fields' ); ?></h1>
            <p>
                <a class="button" href="<?php echo esc_url( $back_url ); ?>">
                    <?php esc_html_e( 'Back to order', 'wc-topup-fields' ); ?>
                </a>
            </p>

            <div class="wctf-reveal-warning" role="alert">
                <strong><?php esc_html_e( 'Sensitive Gift Card secrets are visible on this page only.', 'wc-topup-fields' ); ?></strong>
                <p><?php esc_html_e( 'Do not save, email, log, or paste these values outside the intended admin workflow.', 'wc-topup-fields' ); ?></p>
            </div>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'WooCommerce order ID', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( absint( $wrapper['woocommerce_order_id'] ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'WooCommerce order item ID', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( absint( $wrapper['woocommerce_order_item_id'] ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Product', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $product_name ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Captured at UTC', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( '' !== $captured_at ? $captured_at : __( 'Not available', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Remote order ID', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( '' !== $remote['remote_order_id'] ? $remote['remote_order_id'] : __( 'Not available', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Remote status', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( '' !== $remote['remote_status'] ? $remote['remote_status'] : __( 'Not available', 'wc-topup-fields' ) ); ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ( 'full_order' === $sections['mode'] ) : ?>
                <div class="notice notice-warning inline" role="alert">
                    <p><?php esc_html_e( 'No cards/codes array was found in the decrypted payload. The full opaque Gift Card order object is shown because the FazerCards response schema is not fully documented.', 'wc-topup-fields' ); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php echo esc_html( $sections['title'] ); ?></h2>
            <?php foreach ( $sections['items'] as $index => $secret_item ) : ?>
                <section class="wctf-reveal-card">
                    <?php if ( 'full_order' !== $sections['mode'] ) : ?>
                        <h3>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: 1: section title, 2: item number. */
                                    __( '%1$s #%2$d', 'wc-topup-fields' ),
                                    $sections['item_label'],
                                    absint( $index + 1 )
                                )
                            );
                            ?>
                        </h3>
                    <?php endif; ?>
                    <pre class="wctf-reveal-secret"><?php echo esc_html( wctf_fazercards_giftcard_reveal_json( $secret_item ) ); ?></pre>
                </section>
            <?php endforeach; ?>

            <p>
                <a class="button button-primary" href="<?php echo esc_url( $back_url ); ?>">
                    <?php esc_html_e( 'Back to order', 'wc-topup-fields' ); ?>
                </a>
            </p>
        </main>
    </body>
    </html>
    <?php

    exit;
}

/**
 * Return top-level card/code display sections without recursive searching.
 *
 * @param array $order_payload Opaque FazerCards order object.
 * @return array
 */
function wctf_get_fazercards_giftcard_reveal_sensitive_sections( $order_payload ) {
    if ( isset( $order_payload['cards'] ) && is_array( $order_payload['cards'] ) ) {
        return array(
            'mode'       => 'cards',
            'title'      => __( 'Gift Card cards', 'wc-topup-fields' ),
            'item_label' => __( 'Card', 'wc-topup-fields' ),
            'items'      => array_values( $order_payload['cards'] ),
        );
    }

    if ( isset( $order_payload['codes'] ) && is_array( $order_payload['codes'] ) ) {
        return array(
            'mode'       => 'codes',
            'title'      => __( 'Gift Card codes', 'wc-topup-fields' ),
            'item_label' => __( 'Code', 'wc-topup-fields' ),
            'items'      => array_values( $order_payload['codes'] ),
        );
    }

    return array(
        'mode'       => 'full_order',
        'title'      => __( 'Sensitive opaque Gift Card order object', 'wc-topup-fields' ),
        'item_label' => __( 'Order', 'wc-topup-fields' ),
        'items'      => array( $order_payload ),
    );
}

/**
 * Extract only safe top-level order identifiers for reveal page context.
 *
 * @param array $order_payload Opaque FazerCards order object.
 * @return array
 */
function wctf_get_fazercards_giftcard_reveal_remote_summary( $order_payload ) {
    $remote_order_id = '';
    $remote_status   = '';

    foreach ( array( 'id', 'order_id' ) as $candidate_key ) {
        if ( isset( $order_payload[ $candidate_key ] ) && is_scalar( $order_payload[ $candidate_key ] ) ) {
            $remote_order_id = wctf_limit_fazercards_giftcard_reveal_string( $order_payload[ $candidate_key ], 191 );

            if ( '' !== $remote_order_id ) {
                break;
            }
        }
    }

    if ( isset( $order_payload['status'] ) && is_scalar( $order_payload['status'] ) ) {
        $remote_status = wctf_limit_fazercards_giftcard_reveal_string( $order_payload['status'], 100 );
    }

    return array(
        'remote_order_id' => $remote_order_id,
        'remote_status'   => $remote_status,
    );
}

/**
 * JSON encode one revealed value for escaped admin display.
 *
 * @param mixed $value Revealed value.
 * @return string
 */
function wctf_fazercards_giftcard_reveal_json( $value ) {
    $json = wp_json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    );

    return false === $json ? '' : $json;
}

/**
 * Save a safe reveal failure notice and redirect back to the order.
 *
 * @param WC_Order $order  WooCommerce order.
 * @param array    $result Safe notice fields.
 * @return void
 */
function wctf_finish_fazercards_giftcard_reveal_action( $order, $result ) {
    if ( ! $order instanceof WC_Order ) {
        wp_die(
            esc_html__( 'The WooCommerce order could not be loaded.', 'wc-topup-fields' ),
            esc_html__( 'Order not found', 'wc-topup-fields' ),
            array( 'response' => 404 )
        );
    }

    $type = isset( $result['type'] ) ? sanitize_key( (string) $result['type'] ) : 'error';

    if ( ! in_array( $type, array( 'success', 'warning', 'error' ), true ) ) {
        $type = 'error';
    }

    set_transient(
        wctf_get_fazercards_giftcard_reveal_result_transient_key(
            $order->get_id(),
            get_current_user_id()
        ),
        array(
            'type'    => $type,
            'item_id' => isset( $result['item_id'] ) ? absint( $result['item_id'] ) : 0,
            'message' => wctf_limit_fazercards_giftcard_reveal_string(
                isset( $result['message'] ) ? $result['message'] : '',
                500
            ),
        ),
        5 * MINUTE_IN_SECONDS
    );

    wp_safe_redirect( $order->get_edit_order_url() );
    exit;
}

/**
 * Return the user- and order-isolated reveal failure transient key.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $user_id  WordPress user ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_reveal_result_transient_key( $order_id, $user_id ) {
    return 'wctf_fazer_gc_reveal_' . absint( $user_id ) . '_' . absint( $order_id );
}

/**
 * Sanitize and limit one safe scalar for notices and context.
 *
 * @param mixed $value      Raw value.
 * @param int   $max_length Maximum length.
 * @return string
 */
function wctf_limit_fazercards_giftcard_reveal_string( $value, $max_length ) {
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
