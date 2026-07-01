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
add_action( 'wp_ajax_wctf_sync_fazercards_categories', 'wctf_sync_fazercards_categories' );

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

    $script_path    = WCTF_PATH . 'admin/js/api-settings.js';
    $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : false;

    if ( false === $script_version ) {
        $script_version = wctf_plugin_version();
    }

    wp_enqueue_script(
        'wctf-api-settings',
        plugins_url( 'js/api-settings.js', __FILE__ ),
        array( 'jquery' ),
        $script_version,
        true
    );

    wp_localize_script(
        'wctf-api-settings',
        'wctfApiSettings',
        array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'wctf_test_fazercards_connection' ),
            'categoryNonce' => wp_create_nonce( 'wctf_sync_fazercards_categories' ),
            'messages'      => array(
                'connected'         => __( 'Connected', 'wc-topup-fields' ),
                'failed'            => __( 'Connection failed', 'wc-topup-fields' ),
                'requestFailed'     => __( 'The connection test could not be completed.', 'wc-topup-fields' ),
                'syncing'           => __( 'Synchronizing categories...', 'wc-topup-fields' ),
                'synced'            => __( 'Categories synchronized', 'wc-topup-fields' ),
                'syncFailed'        => __( 'Category synchronization failed', 'wc-topup-fields' ),
                'syncRequestFailed' => __( 'The category synchronization could not be completed.', 'wc-topup-fields' ),
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

/**
 * Synchronize FazerCards categories into a local WordPress option.
 */
function wctf_sync_fazercards_categories() {
    $nonce_is_valid = check_ajax_referer( 'wctf_sync_fazercards_categories', 'nonce', false );

    if ( false === $nonce_is_valid ) {
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
                'message' => __( 'You are not allowed to synchronize categories.', 'wc-topup-fields' ),
            ),
            403
        );
    }

    $config = wctf_config();

    if ( empty( $config['api_url'] ) || empty( $config['api_key'] ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Save an API URL and API key before synchronizing categories.', 'wc-topup-fields' ),
            ),
            400
        );
    }

    $provider            = new WCTF_FazerCards_Provider();
    $stored_categories   = get_option( 'wctf_fazercards_categories', array() );
    $synced_categories   = array();
    $seen_cursors        = array();
    $cursor              = '';
    $created             = 0;
    $updated             = 0;
    $skipped             = 0;

    if ( ! is_array( $stored_categories ) ) {
        $stored_categories = array();
    }

    do {
        $query = array(
            'limit' => 100,
        );

        if ( '' !== $cursor ) {
            $query['cursor'] = $cursor;
        }

        $response = $provider->categories( $query );

        if ( ! $provider->isSuccess( $response ) ) {
            $error = sanitize_text_field( $provider->getError( $response ) );

            if ( '' === $error ) {
                $error = __( 'Unable to download FazerCards categories.', 'wc-topup-fields' );
            }

            wp_send_json_error(
                array(
                    'message' => $error,
                ),
                502
            );
        }

        $body = array();

        if ( isset( $response['body'] ) && is_array( $response['body'] ) ) {
            $body = $response['body'];
        }

        if ( ! isset( $body['items'] ) || ! is_array( $body['items'] ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'FazerCards returned an invalid category response.', 'wc-topup-fields' ),
                ),
                502
            );
        }

        foreach ( $body['items'] as $category ) {
            if (
                ! is_array( $category )
                || ! isset( $category['category_id'] )
                || ! is_scalar( $category['category_id'] )
            ) {
                ++$skipped;
                continue;
            }

            $category_id = sanitize_text_field( (string) $category['category_id'] );

            if ( '' === $category_id ) {
                ++$skipped;
                continue;
            }

            if ( ! isset( $synced_categories[ $category_id ] ) ) {
                if ( isset( $stored_categories[ $category_id ] ) ) {
                    ++$updated;
                } else {
                    ++$created;
                }
            }

            $category_name = '';
            $category_note = '';

            if ( isset( $category['name'] ) && is_scalar( $category['name'] ) ) {
                $category_name = sanitize_text_field( (string) $category['name'] );
            }

            if ( isset( $category['note'] ) && is_scalar( $category['note'] ) ) {
                $category_note = sanitize_textarea_field( (string) $category['note'] );
            }

            $synced_categories[ $category_id ] = array(
                'category_id' => $category_id,
                'name'        => $category_name,
                'note'        => $category_note,
            );
        }

        $meta = array();

        if ( isset( $body['meta'] ) && is_array( $body['meta'] ) ) {
            $meta = $body['meta'];
        }

        $has_more = ! empty( $meta['has_more'] );

        if ( ! $has_more ) {
            break;
        }

        $next_cursor = isset( $meta['next_cursor'] ) && is_scalar( $meta['next_cursor'] )
            ? sanitize_text_field( (string) $meta['next_cursor'] )
            : '';

        if ( '' === $next_cursor || isset( $seen_cursors[ $next_cursor ] ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'FazerCards returned invalid category pagination data.', 'wc-topup-fields' ),
                ),
                502
            );
        }

        $seen_cursors[ $next_cursor ] = true;
        $cursor                       = $next_cursor;
    } while ( true );

    update_option( 'wctf_fazercards_categories', $synced_categories, false );

    $saved_categories = get_option( 'wctf_fazercards_categories', null );

    if ( $synced_categories !== $saved_categories ) {
        wp_send_json_error(
            array(
                'message' => __( 'The synchronized categories could not be stored locally.', 'wc-topup-fields' ),
            ),
            500
        );
    }

    wp_send_json_success(
        array(
            'total'   => count( $synced_categories ),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        )
    );
}
