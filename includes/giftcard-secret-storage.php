<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Store an opaque FazerCards Gift Card order object as authenticated ciphertext.
 *
 * @param WC_Order_Item_Product $item          WooCommerce order line item.
 * @param array                 $order_payload Opaque FazerCards order object.
 * @param int                   $http_status   Remote HTTP status for the safe summary.
 * @return true|WP_Error
 */
function wctf_fazercards_giftcard_store_secret_payload( $item, $order_payload, $http_status = 0 ) {
    $context_ids = wctf_fazercards_giftcard_get_secret_storage_context_ids( $item );

    if ( is_wp_error( $context_ids ) ) {
        return $context_ids;
    }

    if ( ! wctf_fazercards_giftcard_is_associative_array( $order_payload ) ) {
        return new WP_Error(
            'wctf_giftcard_secret_payload_invalid',
            __( 'A non-empty Gift Card order payload is required.', 'wc-topup-fields' )
        );
    }

    $envelope = wctf_fazercards_giftcard_encrypt_secret_payload(
        $order_payload,
        $context_ids['order_id'],
        $context_ids['item_id']
    );

    if ( is_wp_error( $envelope ) ) {
        return $envelope;
    }

    $envelope_data = json_decode( $envelope, true );

    if (
        JSON_ERROR_NONE !== json_last_error()
        || ! is_array( $envelope_data )
        || ! isset( $envelope_data['version'], $envelope_data['algorithm'] )
    ) {
        return new WP_Error(
            'wctf_giftcard_secret_envelope_invalid',
            __( 'The encrypted Gift Card payload envelope is invalid.', 'wc-topup-fields' )
        );
    }

    $captured_at = sanitize_text_field( current_time( 'mysql', true ) );
    $codes_count = wctf_fazercards_giftcard_detect_codes_count( $order_payload );
    $summary     = wctf_fazercards_giftcard_build_safe_response_summary(
        $order_payload,
        $http_status,
        true,
        $captured_at
    );
    $meta_keys   = wctf_fazercards_giftcard_secret_storage_meta_keys();
    $previous    = wctf_fazercards_giftcard_capture_secret_storage_meta( $item, $meta_keys );

    try {
        $item->update_meta_data( '_wctf_fazer_giftcard_secret_payload_encrypted', $envelope );
        $item->update_meta_data(
            '_wctf_fazer_giftcard_secret_payload_version',
            absint( $envelope_data['version'] )
        );
        $item->update_meta_data(
            '_wctf_fazer_giftcard_secret_payload_algorithm',
            sanitize_key( (string) $envelope_data['algorithm'] )
        );
        $item->update_meta_data( '_wctf_fazer_giftcard_last_response_summary', $summary );

        if ( null === $codes_count ) {
            $item->delete_meta_data( '_wctf_fazer_giftcard_codes_count' );
        } else {
            $item->update_meta_data( '_wctf_fazer_giftcard_codes_count', absint( $codes_count ) );
        }

        $item->save_meta_data();

        $stored_item = new WC_Order_Item_Product( $context_ids['item_id'] );
        $stored      = $stored_item->get_meta( '_wctf_fazer_giftcard_secret_payload_encrypted', true );

        if ( ! is_string( $stored ) || ! hash_equals( $envelope, $stored ) ) {
            throw new RuntimeException( 'Encrypted Gift Card payload persistence verification failed.' );
        }

        $verified = wctf_fazercards_giftcard_decrypt_secret_payload(
            $stored,
            $context_ids['order_id'],
            $context_ids['item_id']
        );

        if ( is_wp_error( $verified ) ) {
            throw new RuntimeException( 'Encrypted Gift Card payload authentication verification failed.' );
        }

        unset( $verified );
    } catch ( Throwable $throwable ) {
        unset( $throwable );
        wctf_fazercards_giftcard_restore_secret_storage_meta( $item, $previous );

        return new WP_Error(
            'wctf_giftcard_secret_storage_failed',
            __( 'The encrypted Gift Card payload could not be stored safely.', 'wc-topup-fields' )
        );
    }

    return true;
}

/**
 * Retrieve and authenticated-decrypt an order item's Gift Card secret payload.
 *
 * Callers that expose decrypted data must enforce strict administrator
 * authorization and auditing. This task adds no such display path.
 *
 * @param WC_Order_Item_Product $item WooCommerce order line item.
 * @return array|WP_Error
 */
function wctf_fazercards_giftcard_get_secret_payload( $item ) {
    $context_ids = wctf_fazercards_giftcard_get_secret_storage_context_ids( $item );

    if ( is_wp_error( $context_ids ) ) {
        return $context_ids;
    }

    $envelope = $item->get_meta( '_wctf_fazer_giftcard_secret_payload_encrypted', true );

    if ( ! is_string( $envelope ) || '' === $envelope ) {
        return new WP_Error(
            'wctf_giftcard_secret_payload_missing',
            __( 'No encrypted Gift Card payload is stored for this order item.', 'wc-topup-fields' )
        );
    }

    return wctf_fazercards_giftcard_decrypt_secret_payload(
        $envelope,
        $context_ids['order_id'],
        $context_ids['item_id']
    );
}

/**
 * Delete only the centralized Gift Card secret-storage metadata.
 *
 * @param WC_Order_Item_Product $item WooCommerce order line item.
 * @return true|WP_Error
 */
function wctf_fazercards_giftcard_delete_secret_payload( $item ) {
    $context_ids = wctf_fazercards_giftcard_get_secret_storage_context_ids( $item );

    if ( is_wp_error( $context_ids ) ) {
        return $context_ids;
    }

    unset( $context_ids );

    try {
        foreach ( wctf_fazercards_giftcard_secret_storage_meta_keys() as $meta_key ) {
            $item->delete_meta_data( $meta_key );
        }

        $item->save_meta_data();
    } catch ( Throwable $throwable ) {
        unset( $throwable );

        return new WP_Error(
            'wctf_giftcard_secret_delete_failed',
            __( 'The encrypted Gift Card payload could not be removed safely.', 'wc-topup-fields' )
        );
    }

    return true;
}

/**
 * Determine whether an order item contains a non-empty encrypted envelope.
 *
 * @param WC_Order_Item_Product $item WooCommerce order line item.
 * @return bool
 */
function wctf_fazercards_giftcard_has_secret_payload( $item ) {
    if ( ! $item instanceof WC_Order_Item_Product ) {
        return false;
    }

    $envelope = $item->get_meta( '_wctf_fazer_giftcard_secret_payload_encrypted', true );

    return is_string( $envelope ) && '' !== $envelope;
}

/**
 * Build a strict, credential-free summary of an opaque Gift Card order object.
 *
 * @param array  $order_payload         Opaque FazerCards order object.
 * @param int    $http_status           Remote HTTP status.
 * @param bool   $has_encrypted_payload Whether authenticated ciphertext was stored.
 * @param string $captured_at_utc       UTC capture time.
 * @return string JSON summary containing only explicitly allowed fields.
 */
function wctf_fazercards_giftcard_build_safe_response_summary( $order_payload, $http_status = 0, $has_encrypted_payload = false, $captured_at_utc = '' ) {
    $remote_order_id = '';
    $remote_status   = '';

    if ( is_array( $order_payload ) ) {
        foreach ( array( 'id', 'order_id' ) as $candidate_key ) {
            if ( isset( $order_payload[ $candidate_key ] ) && is_scalar( $order_payload[ $candidate_key ] ) ) {
                $remote_order_id = wctf_fazercards_giftcard_limit_safe_summary_string(
                    $order_payload[ $candidate_key ],
                    191
                );

                if ( '' !== $remote_order_id ) {
                    break;
                }
            }
        }

        if ( isset( $order_payload['status'] ) && is_scalar( $order_payload['status'] ) ) {
            $remote_status = wctf_fazercards_giftcard_limit_safe_summary_string(
                $order_payload['status'],
                100
            );
        }
    }

    $summary = array(
        'http_status'           => absint( $http_status ),
        'remote_order_id'       => $remote_order_id,
        'remote_status'         => $remote_status,
        'has_encrypted_payload' => (bool) $has_encrypted_payload,
        'codes_count'           => wctf_fazercards_giftcard_detect_codes_count( $order_payload ),
        'captured_at_utc'       => wctf_fazercards_giftcard_limit_safe_summary_string( $captured_at_utc, 32 ),
    );
    $json    = wp_json_encode( $summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

    return false === $json ? '{}' : $json;
}

/**
 * Detect a Gift Card/code count without inspecting or returning array contents.
 *
 * @param mixed $order_payload Opaque FazerCards order object.
 * @return int|null
 */
function wctf_fazercards_giftcard_detect_codes_count( $order_payload ) {
    if ( ! is_array( $order_payload ) ) {
        return null;
    }

    if ( isset( $order_payload['cards'] ) && is_array( $order_payload['cards'] ) ) {
        return count( $order_payload['cards'] );
    }

    if ( isset( $order_payload['codes'] ) && is_array( $order_payload['codes'] ) ) {
        return count( $order_payload['codes'] );
    }

    return null;
}

/**
 * Return and validate the WooCommerce storage context for an order item.
 *
 * @param mixed $item Potential WooCommerce order line item.
 * @return array|WP_Error
 */
function wctf_fazercards_giftcard_get_secret_storage_context_ids( $item ) {
    if ( ! $item instanceof WC_Order_Item_Product ) {
        return new WP_Error(
            'wctf_giftcard_order_item_invalid',
            __( 'A valid WooCommerce order item is required.', 'wc-topup-fields' )
        );
    }

    $item_id  = absint( $item->get_id() );
    $order_id = absint( $item->get_order_id() );

    if ( 1 > $item_id || 1 > $order_id ) {
        return new WP_Error(
            'wctf_giftcard_order_item_context_invalid',
            __( 'The WooCommerce order item is not attached to a valid order.', 'wc-topup-fields' )
        );
    }

    return array(
        'order_id' => $order_id,
        'item_id'  => $item_id,
    );
}

/**
 * Return the private meta keys exclusively managed by the storage helper.
 *
 * @return array
 */
function wctf_fazercards_giftcard_secret_storage_meta_keys() {
    return array(
        '_wctf_fazer_giftcard_secret_payload_encrypted',
        '_wctf_fazer_giftcard_secret_payload_version',
        '_wctf_fazer_giftcard_secret_payload_algorithm',
        '_wctf_fazer_giftcard_codes_count',
        '_wctf_fazer_giftcard_last_response_summary',
    );
}

/**
 * Capture existing storage metadata so a failed replacement can be rolled back.
 *
 * @param WC_Order_Item_Product $item      WooCommerce order line item.
 * @param array                 $meta_keys Private storage meta keys.
 * @return array
 */
function wctf_fazercards_giftcard_capture_secret_storage_meta( $item, $meta_keys ) {
    $captured = array();

    foreach ( $meta_keys as $meta_key ) {
        $captured[ $meta_key ] = array(
            'exists' => $item->meta_exists( $meta_key ),
            'value'  => $item->get_meta( $meta_key, true ),
        );
    }

    return $captured;
}

/**
 * Restore previous safe metadata after a failed encrypted write.
 *
 * @param WC_Order_Item_Product $item     WooCommerce order line item.
 * @param array                 $previous Previously captured metadata.
 * @return void
 */
function wctf_fazercards_giftcard_restore_secret_storage_meta( $item, $previous ) {
    try {
        foreach ( wctf_fazercards_giftcard_secret_storage_meta_keys() as $meta_key ) {
            if ( ! empty( $previous[ $meta_key ]['exists'] ) ) {
                $item->update_meta_data( $meta_key, $previous[ $meta_key ]['value'] );
            } else {
                $item->delete_meta_data( $meta_key );
            }
        }

        $item->save_meta_data();
    } catch ( Throwable $throwable ) {
        unset( $throwable );
    }
}

/**
 * Sanitize and length-limit one scalar response-summary value.
 *
 * @param mixed $value      Raw scalar value.
 * @param int   $max_length Maximum character length.
 * @return string
 */
function wctf_fazercards_giftcard_limit_safe_summary_string( $value, $max_length ) {
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
