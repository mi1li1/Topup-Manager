<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WCTF_FazerCards_Provider')) {

class WCTF_FazerCards_Provider {

    protected $base;
    protected $last_response = array();

    public function __construct($base = '/api/v2') {
        $base = trim((string) $base, '/');
        $this->base = $base === '' ? '' : '/' . $base;
    }

    public function connect() {
        return $this->me();
    }

    public function me() {
        return $this->get('/me');
    }

    public function balance() {
        return $this->get('/balance');
    }

    /**
     * Retrieve FazerCards top-up categories.
     *
     * @param array $query Category query parameters.
     * @return array
     */
    public function categories( $query = array() ) {
        return $this->get( '/topups', $query );
    }

    /**
     * Retrieve FazerCards offers for a category.
     *
     * @param string $category_id FazerCards category ID.
     * @return array
     */
    public function offers( $category_id ) {
        return $this->get(
            '/topups/offers',
            array(
                'category_id' => $category_id,
            )
        );
    }

    /**
     * Create a FazerCards top-up order.
     *
     * @param string $category_id    FazerCards category ID.
     * @param string $offer_id       FazerCards offer ID.
     * @param array  $fields         Customer fields required by the offer.
     * @param string $idempotency_key Unique idempotency key for this order.
     * @return array
     */
    public function create_order( $category_id, $offer_id, $fields, $idempotency_key ) {
        $category_id = is_scalar( $category_id )
            ? sanitize_text_field( (string) $category_id )
            : '';
        $offer_id = is_scalar( $offer_id )
            ? sanitize_text_field( (string) $offer_id )
            : '';
        $idempotency_key = is_scalar( $idempotency_key )
            ? sanitize_text_field( (string) $idempotency_key )
            : '';

        if ( '' === $idempotency_key ) {
            $response = array(
                'success' => false,
                'status'  => 0,
                'message' => 'Idempotency key is required.',
                'body'    => array(
                    'ok'    => false,
                    'error' => 'Idempotency key is required.',
                ),
                'headers' => array(),
                'raw'     => '',
            );

            $this->last_response = $response;

            return $response;
        }

        $normalized_fields = array();

        if ( is_array( $fields ) ) {
            foreach ( $fields as $field_key => $field_value ) {
                if ( ! is_scalar( $field_value ) ) {
                    continue;
                }

                $field_key = sanitize_key( (string) $field_key );

                if ( '' === $field_key ) {
                    continue;
                }

                $normalized_fields[ $field_key ] = sanitize_text_field( (string) $field_value );
            }
        }

        return $this->post(
            '/topups/order',
            array(
                'category_id' => $category_id,
                'offer_id'    => $offer_id,
                'fields'      => $normalized_fields,
            ),
            array(
                'Idempotency-Key' => $idempotency_key,
            )
        );
    }

    protected function request($method, $endpoint, $data = array(), $headers = array()) {
        $endpoint = $this->base . '/' . ltrim($endpoint, '/');

        switch (strtoupper($method)) {
            case 'POST':
                $response = WCTF_Request::post($endpoint, $data, $headers);
                break;

            default:
                $response = WCTF_Request::get($endpoint, $data, $headers);
                break;
        }

        $this->last_response = is_array($response) ? $response : array();

        return $response;
    }

    public function get($endpoint, $query = array(), $headers = array()) {
        return $this->request('GET', $endpoint, $query, $headers);
    }

    public function post($endpoint, $body = array(), $headers = array()) {
        return $this->request('POST', $endpoint, $body, $headers);
    }

    public function isSuccess($response = null) {
        if ($response === null) {
            $response = $this->last_response;
        }

        if (!is_array($response) || empty($response['success'])) {
            return false;
        }

        if (isset($response['body']['ok']) && $response['body']['ok'] === false) {
            return false;
        }

        return true;
    }

    public function getError($response = null) {
        if ($response === null) {
            $response = $this->last_response;
        }

        if ($this->isSuccess($response)) {
            return '';
        }

        if (isset($response['body']['error']) && is_scalar($response['body']['error'])) {
            return (string) $response['body']['error'];
        }

        if (isset($response['body']['message']) && is_scalar($response['body']['message'])) {
            return (string) $response['body']['message'];
        }

        if (isset($response['message']) && is_scalar($response['message'])) {
            return (string) $response['message'];
        }

        return 'Unknown provider error';
    }
}

}
