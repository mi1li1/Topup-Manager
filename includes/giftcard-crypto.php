<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return the configured Gift Card encryption key as raw bytes.
 *
 * @return string|WP_Error
 */
function wctf_fazercards_giftcard_get_encryption_key() {
    if ( ! defined( 'WCTF_GIFTCARD_ENCRYPTION_KEY' ) ) {
        return new WP_Error(
            'wctf_giftcard_encryption_key_missing',
            __( 'The Gift Card encryption key is not configured.', 'wc-topup-fields' )
        );
    }

    $configured_key = constant( 'WCTF_GIFTCARD_ENCRYPTION_KEY' );

    if ( ! is_string( $configured_key ) || 0 !== strpos( $configured_key, 'base64:' ) ) {
        wctf_fazercards_giftcard_forget_sensitive_string( $configured_key );

        return new WP_Error(
            'wctf_giftcard_encryption_key_format_invalid',
            __( 'The Gift Card encryption key format is invalid.', 'wc-topup-fields' )
        );
    }

    $encoded_key = substr( $configured_key, 7 );

    if (
        '' === $encoded_key
        || 0 !== strlen( $encoded_key ) % 4
        || 1 !== preg_match( '/\A[A-Za-z0-9+\/]+={0,2}\z/D', $encoded_key )
    ) {
        wctf_fazercards_giftcard_forget_sensitive_string( $encoded_key );
        wctf_fazercards_giftcard_forget_sensitive_string( $configured_key );

        return new WP_Error(
            'wctf_giftcard_encryption_key_format_invalid',
            __( 'The Gift Card encryption key format is invalid.', 'wc-topup-fields' )
        );
    }

    $key = base64_decode( $encoded_key, true );

    if (
        false === $key
        || 32 !== strlen( $key )
        || base64_encode( $key ) !== $encoded_key
    ) {
        wctf_fazercards_giftcard_forget_sensitive_string( $key );
        wctf_fazercards_giftcard_forget_sensitive_string( $encoded_key );
        wctf_fazercards_giftcard_forget_sensitive_string( $configured_key );

        return new WP_Error(
            'wctf_giftcard_encryption_key_length_invalid',
            __( 'The Gift Card encryption key must contain exactly 32 random bytes.', 'wc-topup-fields' )
        );
    }

    wctf_fazercards_giftcard_forget_sensitive_string( $encoded_key );
    wctf_fazercards_giftcard_forget_sensitive_string( $configured_key );

    return $key;
}

/**
 * Return the non-secret identifier for the active encryption key.
 *
 * @return string|WP_Error
 */
function wctf_fazercards_giftcard_get_key_id() {
    if ( ! defined( 'WCTF_GIFTCARD_ENCRYPTION_KEY_ID' ) ) {
        return 'primary';
    }

    $key_id = constant( 'WCTF_GIFTCARD_ENCRYPTION_KEY_ID' );

    if (
        ! is_string( $key_id )
        || 1 !== preg_match( '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $key_id )
    ) {
        return new WP_Error(
            'wctf_giftcard_encryption_key_id_invalid',
            __( 'The Gift Card encryption key identifier is invalid.', 'wc-topup-fields' )
        );
    }

    return $key_id;
}

/**
 * Report safe encrypted-storage readiness information.
 *
 * @return array
 */
function wctf_fazercards_giftcard_crypto_status() {
    $key_configured = defined( 'WCTF_GIFTCARD_ENCRYPTION_KEY' );
    $key             = wctf_fazercards_giftcard_get_encryption_key();
    $key_valid       = ! is_wp_error( $key );
    $key_id          = wctf_fazercards_giftcard_get_key_id();
    $algorithm       = wctf_fazercards_giftcard_get_available_algorithm();
    $ready           = $key_valid && ! is_wp_error( $key_id ) && 'none' !== $algorithm;
    $reason_code     = 'ready';
    $message         = __( 'Encrypted Gift Card secret storage is ready.', 'wc-topup-fields' );

    if ( ! $key_valid ) {
        $reason_code = sanitize_key( $key->get_error_code() );
        $message     = sanitize_text_field( $key->get_error_message() );
    } elseif ( is_wp_error( $key_id ) ) {
        $reason_code = sanitize_key( $key_id->get_error_code() );
        $message     = sanitize_text_field( $key_id->get_error_message() );
    } elseif ( 'none' === $algorithm ) {
        $reason_code = 'wctf_giftcard_encryption_backend_unavailable';
        $message     = __( 'No supported authenticated encryption backend is available.', 'wc-topup-fields' );
    }

    if ( is_string( $key ) ) {
        wctf_fazercards_giftcard_forget_sensitive_string( $key );
    }

    return array(
        'ready'          => $ready,
        'key_configured' => $key_configured,
        'key_valid'      => $key_valid,
        'algorithm'      => $algorithm,
        'reason_code'    => $reason_code,
        'message'        => $message,
    );
}

/**
 * Select the strongest supported authenticated encryption backend.
 *
 * @return string
 */
function wctf_fazercards_giftcard_get_available_algorithm() {
    if (
        function_exists( 'sodium_crypto_secretbox' )
        && function_exists( 'sodium_crypto_secretbox_open' )
        && defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' )
        && defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' )
        && defined( 'SODIUM_CRYPTO_SECRETBOX_MACBYTES' )
        && 32 === SODIUM_CRYPTO_SECRETBOX_KEYBYTES
    ) {
        return 'sodium-secretbox';
    }

    if (
        function_exists( 'openssl_encrypt' )
        && function_exists( 'openssl_decrypt' )
        && function_exists( 'openssl_cipher_iv_length' )
        && function_exists( 'openssl_get_cipher_methods' )
    ) {
        $cipher_methods = array_map( 'strtolower', openssl_get_cipher_methods() );

        if (
            in_array( 'aes-256-gcm', $cipher_methods, true )
            && 0 < (int) openssl_cipher_iv_length( 'aes-256-gcm' )
        ) {
            return 'aes-256-gcm';
        }
    }

    return 'none';
}

/**
 * Encrypt an opaque FazerCards Gift Card order object.
 *
 * @param array $order_payload Opaque FazerCards order object.
 * @param int   $order_id      WooCommerce order ID.
 * @param int   $item_id       WooCommerce order item ID.
 * @return string|WP_Error Self-describing JSON envelope or an error.
 */
function wctf_fazercards_giftcard_encrypt_secret_payload( $order_payload, $order_id, $item_id ) {
    if ( ! wctf_fazercards_giftcard_is_associative_array( $order_payload ) ) {
        return new WP_Error(
            'wctf_giftcard_secret_payload_invalid',
            __( 'A non-empty Gift Card order payload is required.', 'wc-topup-fields' )
        );
    }

    $context = wctf_fazercards_giftcard_secret_context( $order_id, $item_id );

    if ( '' === $context ) {
        return new WP_Error(
            'wctf_giftcard_secret_context_invalid',
            __( 'A valid WooCommerce order and order item are required.', 'wc-topup-fields' )
        );
    }

    $status = wctf_fazercards_giftcard_crypto_status();

    if ( empty( $status['ready'] ) ) {
        return new WP_Error(
            'wctf_giftcard_crypto_not_ready',
            __( 'Encrypted Gift Card secret storage is not ready.', 'wc-topup-fields' )
        );
    }

    $key    = wctf_fazercards_giftcard_get_encryption_key();
    $key_id = wctf_fazercards_giftcard_get_key_id();

    if ( is_wp_error( $key ) || is_wp_error( $key_id ) ) {
        if ( is_string( $key ) ) {
            wctf_fazercards_giftcard_forget_sensitive_string( $key );
        }

        return new WP_Error(
            'wctf_giftcard_crypto_not_ready',
            __( 'Encrypted Gift Card secret storage is not ready.', 'wc-topup-fields' )
        );
    }

    $wrapper = array(
        'schema'                    => 'wctf-giftcard-secret-v1',
        'context'                   => $context,
        'woocommerce_order_id'      => absint( $order_id ),
        'woocommerce_order_item_id' => absint( $item_id ),
        'captured_at_utc'           => sanitize_text_field( current_time( 'mysql', true ) ),
        'order'                     => $order_payload,
    );
    $plaintext = wp_json_encode(
        $wrapper,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    );

    unset( $wrapper );

    if ( false === $plaintext || '' === $plaintext ) {
        wctf_fazercards_giftcard_forget_sensitive_string( $key );

        return new WP_Error(
            'wctf_giftcard_secret_payload_encoding_failed',
            __( 'The Gift Card secret payload could not be encoded safely.', 'wc-topup-fields' )
        );
    }

    try {
        if ( 'sodium-secretbox' === $status['algorithm'] ) {
            $nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
            $envelope   = array(
                'version'    => 1,
                'algorithm'  => 'sodium-secretbox',
                'key_id'     => $key_id,
                'nonce'      => base64_encode( $nonce ),
                'ciphertext' => base64_encode( $ciphertext ),
            );

            wctf_fazercards_giftcard_forget_sensitive_string( $nonce );
            wctf_fazercards_giftcard_forget_sensitive_string( $ciphertext );
        } elseif ( 'aes-256-gcm' === $status['algorithm'] ) {
            $iv_length  = (int) openssl_cipher_iv_length( 'aes-256-gcm' );
            $iv         = random_bytes( $iv_length );
            $tag        = '';
            $ciphertext = openssl_encrypt(
                $plaintext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $context,
                16
            );

            if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
                throw new RuntimeException( 'Authenticated encryption failed.' );
            }

            $envelope = array(
                'version'    => 1,
                'algorithm'  => 'aes-256-gcm',
                'key_id'     => $key_id,
                'iv'         => base64_encode( $iv ),
                'tag'        => base64_encode( $tag ),
                'ciphertext' => base64_encode( $ciphertext ),
            );

            wctf_fazercards_giftcard_forget_sensitive_string( $iv );
            wctf_fazercards_giftcard_forget_sensitive_string( $tag );
            wctf_fazercards_giftcard_forget_sensitive_string( $ciphertext );
        } else {
            throw new RuntimeException( 'No authenticated encryption backend is available.' );
        }

        $encoded_envelope = wp_json_encode( $envelope, JSON_UNESCAPED_SLASHES );
    } catch ( Throwable $throwable ) {
        unset( $throwable );
        $encoded_envelope = false;
    }

    wctf_fazercards_giftcard_forget_sensitive_string( $plaintext );
    wctf_fazercards_giftcard_forget_sensitive_string( $key );

    if ( false === $encoded_envelope || '' === $encoded_envelope ) {
        return new WP_Error(
            'wctf_giftcard_secret_encryption_failed',
            __( 'The Gift Card secret payload could not be encrypted.', 'wc-topup-fields' )
        );
    }

    return $encoded_envelope;
}

/**
 * Authenticated-decrypt an opaque FazerCards Gift Card order object.
 *
 * @param string $encoded_envelope Stored JSON envelope.
 * @param int    $order_id         WooCommerce order ID.
 * @param int    $item_id          WooCommerce order item ID.
 * @return array|WP_Error
 */
function wctf_fazercards_giftcard_decrypt_secret_payload( $encoded_envelope, $order_id, $item_id ) {
    if ( ! is_string( $encoded_envelope ) || '' === $encoded_envelope ) {
        return new WP_Error(
            'wctf_giftcard_secret_envelope_missing',
            __( 'The encrypted Gift Card payload is missing.', 'wc-topup-fields' )
        );
    }

    $context = wctf_fazercards_giftcard_secret_context( $order_id, $item_id );

    if ( '' === $context ) {
        return new WP_Error(
            'wctf_giftcard_secret_context_invalid',
            __( 'A valid WooCommerce order and order item are required.', 'wc-topup-fields' )
        );
    }

    $envelope = json_decode( $encoded_envelope, true );

    if (
        JSON_ERROR_NONE !== json_last_error()
        || ! is_array( $envelope )
        || ! isset( $envelope['version'], $envelope['algorithm'], $envelope['key_id'] )
        || 1 !== $envelope['version']
        || ! in_array( $envelope['algorithm'], array( 'sodium-secretbox', 'aes-256-gcm' ), true )
        || ! is_string( $envelope['key_id'] )
        || 1 !== preg_match( '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $envelope['key_id'] )
    ) {
        return new WP_Error(
            'wctf_giftcard_secret_envelope_invalid',
            __( 'The encrypted Gift Card payload envelope is invalid.', 'wc-topup-fields' )
        );
    }

    $key    = wctf_fazercards_giftcard_get_encryption_key();
    $key_id = wctf_fazercards_giftcard_get_key_id();

    if (
        is_wp_error( $key )
        || is_wp_error( $key_id )
        || ! hash_equals( $key_id, $envelope['key_id'] )
    ) {
        if ( is_string( $key ) ) {
            wctf_fazercards_giftcard_forget_sensitive_string( $key );
        }

        return new WP_Error(
            'wctf_giftcard_secret_key_mismatch',
            __( 'The encrypted Gift Card payload cannot be opened with the active key.', 'wc-topup-fields' )
        );
    }

    $plaintext = false;

    try {
        if ( 'sodium-secretbox' === $envelope['algorithm'] ) {
            if (
                ! function_exists( 'sodium_crypto_secretbox_open' )
                || ! defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' )
                || ! defined( 'SODIUM_CRYPTO_SECRETBOX_MACBYTES' )
            ) {
                throw new RuntimeException( 'The required encryption backend is unavailable.' );
            }

            $nonce      = wctf_fazercards_giftcard_decode_envelope_field(
                $envelope,
                'nonce',
                SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
            );
            $ciphertext = wctf_fazercards_giftcard_decode_envelope_field( $envelope, 'ciphertext' );

            if (
                is_wp_error( $nonce )
                || is_wp_error( $ciphertext )
                || strlen( $ciphertext ) < SODIUM_CRYPTO_SECRETBOX_MACBYTES
            ) {
                throw new RuntimeException( 'The encrypted payload fields are invalid.' );
            }

            $plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

            wctf_fazercards_giftcard_forget_sensitive_string( $nonce );
            wctf_fazercards_giftcard_forget_sensitive_string( $ciphertext );
        } else {
            if (
                ! function_exists( 'openssl_decrypt' )
                || ! function_exists( 'openssl_cipher_iv_length' )
                || ! function_exists( 'openssl_get_cipher_methods' )
                || ! in_array( 'aes-256-gcm', array_map( 'strtolower', openssl_get_cipher_methods() ), true )
            ) {
                throw new RuntimeException( 'The required encryption backend is unavailable.' );
            }

            $iv_length  = (int) openssl_cipher_iv_length( 'aes-256-gcm' );

            if ( 1 > $iv_length ) {
                throw new RuntimeException( 'The encrypted payload IV length is invalid.' );
            }

            $iv         = wctf_fazercards_giftcard_decode_envelope_field( $envelope, 'iv', $iv_length );
            $tag        = wctf_fazercards_giftcard_decode_envelope_field( $envelope, 'tag', 16 );
            $ciphertext = wctf_fazercards_giftcard_decode_envelope_field( $envelope, 'ciphertext' );

            if ( is_wp_error( $iv ) || is_wp_error( $tag ) || is_wp_error( $ciphertext ) ) {
                throw new RuntimeException( 'The encrypted payload fields are invalid.' );
            }

            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $context
            );

            wctf_fazercards_giftcard_forget_sensitive_string( $iv );
            wctf_fazercards_giftcard_forget_sensitive_string( $tag );
            wctf_fazercards_giftcard_forget_sensitive_string( $ciphertext );
        }
    } catch ( Throwable $throwable ) {
        unset( $throwable );
        $plaintext = false;
    }

    wctf_fazercards_giftcard_forget_sensitive_string( $key );

    if ( false === $plaintext || '' === $plaintext ) {
        return new WP_Error(
            'wctf_giftcard_secret_authentication_failed',
            __( 'The encrypted Gift Card payload failed authentication.', 'wc-topup-fields' )
        );
    }

    $wrapper = json_decode( $plaintext, true );
    wctf_fazercards_giftcard_forget_sensitive_string( $plaintext );

    if (
        JSON_ERROR_NONE !== json_last_error()
        || ! is_array( $wrapper )
        || ! isset(
            $wrapper['schema'],
            $wrapper['context'],
            $wrapper['woocommerce_order_id'],
            $wrapper['woocommerce_order_item_id'],
            $wrapper['captured_at_utc'],
            $wrapper['order']
        )
        || 'wctf-giftcard-secret-v1' !== $wrapper['schema']
        || ! is_string( $wrapper['context'] )
        || ! is_scalar( $wrapper['woocommerce_order_id'] )
        || ! is_scalar( $wrapper['woocommerce_order_item_id'] )
        || ! is_string( $wrapper['captured_at_utc'] )
        || ! hash_equals( $context, $wrapper['context'] )
        || absint( $order_id ) !== absint( $wrapper['woocommerce_order_id'] )
        || absint( $item_id ) !== absint( $wrapper['woocommerce_order_item_id'] )
        || ! wctf_fazercards_giftcard_is_associative_array( $wrapper['order'] )
    ) {
        return new WP_Error(
            'wctf_giftcard_secret_context_mismatch',
            __( 'The encrypted Gift Card payload does not match this order item.', 'wc-topup-fields' )
        );
    }

    return $wrapper['order'];
}

/**
 * Build the stable authenticated context for an order item secret.
 *
 * @param int $order_id WooCommerce order ID.
 * @param int $item_id  WooCommerce order item ID.
 * @return string
 */
function wctf_fazercards_giftcard_secret_context( $order_id, $item_id ) {
    $order_id = absint( $order_id );
    $item_id  = absint( $item_id );

    if ( 1 > $order_id || 1 > $item_id ) {
        return '';
    }

    return sprintf( 'wctf-giftcard-secret-v1|order:%d|item:%d', $order_id, $item_id );
}

/**
 * Decode and validate a Base64 field from an encrypted envelope.
 *
 * @param array  $envelope       Parsed envelope.
 * @param string $field_key      Envelope field name.
 * @param int    $expected_bytes Expected decoded length, or zero for any non-empty length.
 * @return string|WP_Error
 */
function wctf_fazercards_giftcard_decode_envelope_field( $envelope, $field_key, $expected_bytes = 0 ) {
    if (
        ! is_array( $envelope )
        || ! isset( $envelope[ $field_key ] )
        || ! is_string( $envelope[ $field_key ] )
        || '' === $envelope[ $field_key ]
    ) {
        return new WP_Error( 'wctf_giftcard_secret_envelope_field_invalid' );
    }

    $decoded = base64_decode( $envelope[ $field_key ], true );

    if (
        false === $decoded
        || '' === $decoded
        || base64_encode( $decoded ) !== $envelope[ $field_key ]
        || ( 0 < $expected_bytes && $expected_bytes !== strlen( $decoded ) )
    ) {
        wctf_fazercards_giftcard_forget_sensitive_string( $decoded );
        return new WP_Error( 'wctf_giftcard_secret_envelope_field_invalid' );
    }

    return $decoded;
}

/**
 * Determine whether an array represents a non-empty JSON object.
 *
 * @param mixed $value Value to inspect.
 * @return bool
 */
function wctf_fazercards_giftcard_is_associative_array( $value ) {
    if ( ! is_array( $value ) || empty( $value ) ) {
        return false;
    }

    return array_keys( $value ) !== range( 0, count( $value ) - 1 );
}

/**
 * Best-effort clearing of a sensitive string from the current PHP variable.
 *
 * @param mixed $value Sensitive value passed by reference.
 * @return void
 */
function wctf_fazercards_giftcard_forget_sensitive_string( &$value ) {
    if ( ! is_string( $value ) ) {
        $value = null;
        return;
    }

    if ( '' !== $value && function_exists( 'sodium_memzero' ) ) {
        sodium_memzero( $value );
    } elseif ( '' !== $value ) {
        $value = str_repeat( "\0", strlen( $value ) );
    }

    $value = '';
}
