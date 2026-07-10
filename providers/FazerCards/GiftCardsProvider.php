<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WCTF_FazerCards_GiftCards_Provider' ) ) {

    /**
     * FazerCards Gift Cards provider.
     */
    class WCTF_FazerCards_GiftCards_Provider {

        /**
         * API path prefix.
         *
         * @var string
         */
        protected $base;

        /**
         * Most recent normalized response.
         *
         * @var array
         */
        protected $last_response = array();

        /**
         * Create the provider.
         *
         * @param string $base API path prefix.
         */
        public function __construct( $base = '/api/v2' ) {
            $base       = trim( (string) $base, '/' );
            $this->base = '' === $base ? '' : '/' . $base;
        }

        /**
         * Retrieve Gift Card categories.
         *
         * @param array $query Category query parameters.
         * @return array
         */
        public function categories( $query = array() ) {
            return $this->get( '/giftcards', $query );
        }

        /**
         * Retrieve Gift Card cards/SKUs for a category.
         *
         * @param string $category_id FazerCards Gift Card category ID.
         * @return array
         */
        public function cards( $category_id ) {
            $category_id = is_scalar( $category_id )
                ? sanitize_text_field( (string) $category_id )
                : '';

            return $this->get(
                '/giftcards/cards',
                array(
                    'category_id' => $category_id,
                )
            );
        }

        /**
         * Create one real FazerCards Gift Card order.
         *
         * @param string $category_id    FazerCards Gift Card category ID.
         * @param string $card_id        FazerCards Gift Card/SKU ID.
         * @param int    $quantity       Strict integer quantity from 1 to 100.
         * @param string $idempotency_key Stable per-order-item idempotency key.
         * @return array
         */
        public function create_order( $category_id, $card_id, $quantity, $idempotency_key ) {
            $category_id = is_scalar( $category_id )
                ? sanitize_text_field( (string) $category_id )
                : '';
            $card_id = is_scalar( $card_id )
                ? sanitize_text_field( (string) $card_id )
                : '';
            $normalized_quantity = $this->normalize_quantity( $quantity );
            $raw_idempotency_key = is_scalar( $idempotency_key )
                ? (string) $idempotency_key
                : '';
            $has_header_breaks = false !== strpos( $raw_idempotency_key, "\r" )
                || false !== strpos( $raw_idempotency_key, "\n" );
            $idempotency_key = sanitize_text_field( $raw_idempotency_key );

            if ( '' === $category_id ) {
                return $this->error_response( 'Gift Card category ID is required.' );
            }

            if ( '' === $card_id ) {
                return $this->error_response( 'Gift Card card ID is required.' );
            }

            if ( null === $normalized_quantity ) {
                return $this->error_response( 'Gift Card quantity must be a strict integer from 1 to 100.' );
            }

            if (
                '' === $idempotency_key
                || $has_header_breaks
                || 255 < strlen( $idempotency_key )
            ) {
                return $this->error_response( 'A valid idempotency key is required.' );
            }

            $endpoint = $this->base . '/giftcards/order';
            $response = WCTF_Request::post(
                $endpoint,
                array(
                    'category_id' => $category_id,
                    'card_id'     => $card_id,
                    'quantity'    => $normalized_quantity,
                ),
                array(
                    'Idempotency-Key' => $idempotency_key,
                )
            );

            if ( ! is_array( $response ) ) {
                return $this->error_response( 'The Gift Card order response was invalid.' );
            }

            $body = isset( $response['body'] ) && is_array( $response['body'] )
                ? $response['body']
                : array();

            foreach ( array( 'error', 'message' ) as $error_key ) {
                if ( isset( $body[ $error_key ] ) && is_scalar( $body[ $error_key ] ) ) {
                    $body[ $error_key ] = $this->sanitize_error_message( $body[ $error_key ] );
                }
            }

            $response = array(
                'success' => ! empty( $response['success'] ),
                'status'  => isset( $response['status'] ) ? absint( $response['status'] ) : 0,
                'message' => isset( $response['message'] ) && is_scalar( $response['message'] )
                    ? $this->sanitize_error_message( $response['message'] )
                    : '',
                'body'    => $body,
                'headers' => array(),
                'raw'     => '',
            );

            $this->last_response = $response;

            return $response;
        }

        /**
         * Retrieve one existing FazerCards order by public remote order ID.
         *
         * @param string $remote_order_id FazerCards public order ID.
         * @return array
         */
        public function get_order( $remote_order_id ) {
            $remote_order_id = is_scalar( $remote_order_id )
                ? sanitize_text_field( (string) $remote_order_id )
                : '';

            if ( 1 !== preg_match( '/\Aord-[0-9]+\z/D', $remote_order_id ) ) {
                return $this->error_response( 'A valid FazerCards remote order ID is required.' );
            }

            $response = $this->get( '/orders/' . rawurlencode( $remote_order_id ) );

            if ( ! is_array( $response ) ) {
                return $this->error_response( 'The Gift Card remote order response was invalid.' );
            }

            $body = isset( $response['body'] ) && is_array( $response['body'] )
                ? $response['body']
                : array();

            foreach ( array( 'error', 'message' ) as $error_key ) {
                if ( isset( $body[ $error_key ] ) && is_scalar( $body[ $error_key ] ) ) {
                    $body[ $error_key ] = $this->sanitize_error_message( $body[ $error_key ] );
                }
            }

            $response = array(
                'success' => ! empty( $response['success'] ),
                'status'  => isset( $response['status'] ) ? absint( $response['status'] ) : 0,
                'message' => isset( $response['message'] ) && is_scalar( $response['message'] )
                    ? $this->sanitize_error_message( $response['message'] )
                    : '',
                'body'    => $body,
                'headers' => array(),
                'raw'     => '',
            );

            $this->last_response = $response;

            return $response;
        }

        /**
         * Perform a read-only request.
         *
         * @param string $endpoint API endpoint.
         * @param array  $query    Query parameters.
         * @param array  $headers  Additional request headers.
         * @return array
         */
        public function get( $endpoint, $query = array(), $headers = array() ) {
            $endpoint            = $this->base . '/' . ltrim( (string) $endpoint, '/' );
            $response            = WCTF_Request::get( $endpoint, $query, $headers );
            $this->last_response = is_array( $response ) ? $response : array();

            return $response;
        }

        /**
         * Determine whether a response is successful.
         *
         * @param mixed $response Normalized request response.
         * @return bool
         */
        public function isSuccess( $response = null ) {
            $response = is_array( $response ) ? $response : $this->last_response;

            if ( empty( $response['success'] ) ) {
                return false;
            }

            if (
                isset( $response['body']['ok'] )
                && false === (bool) $response['body']['ok']
            ) {
                return false;
            }

            return true;
        }

        /**
         * Return a safe error message from a response.
         *
         * @param mixed $response Normalized request response.
         * @return string
         */
        public function getError( $response = null ) {
            $response = is_array( $response ) ? $response : $this->last_response;

            if (
                isset( $response['body']['error'] )
                && is_scalar( $response['body']['error'] )
            ) {
                return $this->sanitize_error_message( $response['body']['error'] );
            }

            if (
                isset( $response['body']['message'] )
                && is_scalar( $response['body']['message'] )
            ) {
                return $this->sanitize_error_message( $response['body']['message'] );
            }

            if ( isset( $response['message'] ) && is_scalar( $response['message'] ) ) {
                return $this->sanitize_error_message( $response['message'] );
            }

            return '';
        }

        /**
         * Normalize a strict Gift Card quantity without truncating decimals.
         *
         * @param mixed $quantity Raw quantity.
         * @return int|null
         */
        protected function normalize_quantity( $quantity ) {
            if ( is_int( $quantity ) ) {
                $normalized = $quantity;
            } elseif (
                is_string( $quantity )
                && 1 === preg_match( '/\A(?:0|[1-9][0-9]*)\z/D', $quantity )
            ) {
                $normalized = (int) $quantity;
            } else {
                return null;
            }

            return 1 <= $normalized && 100 >= $normalized
                ? $normalized
                : null;
        }

        /**
         * Build a credential-free local validation error response.
         *
         * @param string $message Safe error message.
         * @return array
         */
        protected function error_response( $message ) {
            $message  = $this->sanitize_error_message( $message );
            $response = array(
                'success' => false,
                'status'  => 0,
                'message' => $message,
                'body'    => array(
                    'ok'    => false,
                    'error' => $message,
                ),
                'headers' => array(),
                'raw'     => '',
            );

            $this->last_response = $response;

            return $response;
        }

        /**
         * Sanitize and redact configured credentials from a safe error string.
         *
         * @param mixed $message Raw error message.
         * @return string
         */
        protected function sanitize_error_message( $message ) {
            $message = is_scalar( $message )
                ? sanitize_text_field( (string) $message )
                : '';
            try {
                $config = function_exists( 'wctf_config' ) ? wctf_config() : array();
            } catch ( Throwable $throwable ) {
                unset( $throwable );
                $config = array();
            }

            foreach ( array( 'api_key', 'api_secret' ) as $credential_key ) {
                if (
                    isset( $config[ $credential_key ] )
                    && is_scalar( $config[ $credential_key ] )
                    && '' !== (string) $config[ $credential_key ]
                ) {
                    $message = str_replace(
                        (string) $config[ $credential_key ],
                        '[redacted credential]',
                        $message
                    );
                }
            }

            return $message;
        }
    }
}
