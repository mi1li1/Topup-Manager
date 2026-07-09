<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WCTF_FazerCards_GiftCards_Provider' ) ) {

    /**
     * Read-only FazerCards Gift Cards catalog provider.
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
                return sanitize_text_field( (string) $response['body']['error'] );
            }

            if ( isset( $response['message'] ) && is_scalar( $response['message'] ) ) {
                return sanitize_text_field( (string) $response['message'] );
            }

            return '';
        }
    }
}
