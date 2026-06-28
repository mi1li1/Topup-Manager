<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WCTF_FazerCards_Provider')) {

class WCTF_FazerCards_Provider {

    protected $base = '/api/v2';

    public function connect() {
        return $this->me();
    }

    public function me() {
        return WCTF_Request::get($this->base . '/me');
    }

    public function balance() {
        return WCTF_Request::get($this->base . '/balance');
    }

    protected function request($method, $endpoint, $body = array()) {
        switch (strtoupper($method)) {
            case 'POST':
                return WCTF_Request::post($endpoint, $body);
            case 'PUT':
                return WCTF_Request::put($endpoint, $body);
            case 'DELETE':
                return WCTF_Request::delete($endpoint, $body);
            default:
                return WCTF_Request::get($endpoint, $body);
        }
    }
}

}
