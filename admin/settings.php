<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册插件设置
 */
add_action('admin_init', 'wctf_register_settings');
add_action( 'admin_enqueue_scripts', 'wctf_enqueue_api_settings_assets' );
add_action( 'wp_ajax_wctf_test_fazercards_connection', 'wctf_test_fazercards_connection' );

function wctf_register_settings()
{

    /*
     * FazerCards API
     */

    register_setting(
        'wctf_api_settings',
        'wctf_api_url',
        array(
            'sanitize_callback' => 'esc_url_raw',
        )
    );

    register_setting(
        'wctf_api_settings',
        'wctf_api_key',
        array(
            'sanitize_callback' => 'sanitize_text_field',
        )
    );

    register_setting(
        'wctf_api_settings',
        'wctf_api_secret',
        array(
            'sanitize_callback' => 'sanitize_text_field',
        )
    );

}

/**
 * Load assets for the FazerCards API settings page.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function wctf_enqueue_api_settings_assets( $hook_suffix ) {
    if ( 'topup-manager_page_wctf-api' !== $hook_suffix ) {
        return;
    }

    wp_enqueue_script(
        'wctf-api-settings',
        plugins_url( 'js/api-settings.js', __FILE__ ),
        array( 'jquery' ),
        wctf_plugin_version(),
        true
    );

    wp_localize_script(
        'wctf-api-settings',
        'wctfApiSettings',
        array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wctf_test_fazercards_connection' ),
            'messages' => array(
                'connected'     => __( 'Connected', 'wc-topup-fields' ),
                'failed'        => __( 'Connection failed', 'wc-topup-fields' ),
                'requestFailed' => __( 'The connection test could not be completed.', 'wc-topup-fields' ),
            ),
        )
    );
}

/**
 * Test the configured FazerCards connection.
 */
function wctf_test_fazercards_connection() {
    if ( false === check_ajax_referer( 'wctf_test_fazercards_connection', 'nonce', false ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Security check failed. Refresh the page and try again.', 'wc-topup-fields' ),
            ),
            403
        );
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'You are not allowed to test this connection.', 'wc-topup-fields' ),
            ),
            403
        );
    }

    $config = wctf_config();

    if ( empty( $config['api_url'] ) || empty( $config['api_key'] ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Save an API URL and API key before testing the connection.', 'wc-topup-fields' ),
            ),
            400
        );
    }

    $provider   = new WCTF_FazerCards_Provider();
    $connection = $provider->connect();

    if ( ! $provider->isSuccess( $connection ) ) {
        $error = sanitize_text_field( $provider->getError( $connection ) );

        if ( '' === $error ) {
            $error = __( 'Unable to connect to FazerCards.', 'wc-topup-fields' );
        }

        wp_send_json_error(
            array(
                'message' => $error,
            ),
            502
        );
    }

    $account      = array();
    $account_name = '';

    if ( isset( $connection['body'] ) && is_array( $connection['body'] ) ) {
        $account = $connection['body'];
    }

    foreach ( array( 'name', 'login', 'email' ) as $account_field ) {
        if ( isset( $account[ $account_field ] ) && is_scalar( $account[ $account_field ] ) ) {
            $account_name = sanitize_text_field( (string) $account[ $account_field ] );
            break;
        }
    }

    $balance_response = $provider->balance();
    $balance_body     = array();
    $balance          = '';
    $currency         = '';

    if (
        $provider->isSuccess( $balance_response )
        && isset( $balance_response['body'] )
        && is_array( $balance_response['body'] )
    ) {
        $balance_body = $balance_response['body'];
    }

    if ( isset( $balance_body['balance'] ) && is_scalar( $balance_body['balance'] ) ) {
        $balance = sanitize_text_field( (string) $balance_body['balance'] );
    }

    if ( isset( $balance_body['currency'] ) && is_scalar( $balance_body['currency'] ) ) {
        $currency = sanitize_text_field( (string) $balance_body['currency'] );
    }

    wp_send_json_success(
        array(
            'accountName' => $account_name,
            'balance'     => $balance,
            'currency'    => $currency,
        )
    );
}
