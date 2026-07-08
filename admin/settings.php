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
add_action( 'wp_ajax_wctf_sync_fazercards_offers', 'wctf_sync_fazercards_offers' );
add_action( 'wp_ajax_wctf_browse_fazercards_offers', 'wctf_browse_fazercards_offers' );

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

    register_setting(
        'wctf_api_settings',
        'wctf_fazercards_auto_submit_enabled',
        array(
            'default'           => 'no',
            'sanitize_callback' => 'wctf_sanitize_fazercards_auto_submit_enabled',
        )
    );

    register_setting(
        'wctf_api_settings',
        'wctf_fazercards_failure_alert_recipients',
        array(
            'default'           => '',
            'sanitize_callback' => 'wctf_sanitize_fazercards_failure_alert_recipients',
        )
    );

}

/**
 * Normalize the automatic FazerCards submission setting.
 *
 * @param mixed $value Submitted setting value.
 * @return string
 */
function wctf_sanitize_fazercards_auto_submit_enabled( $value ) {
    return is_scalar( $value ) && 'yes' === sanitize_key( (string) $value ) ? 'yes' : 'no';
}

/**
 * Normalize comma-separated automatic failure alert recipients.
 *
 * @param mixed $value Submitted recipient list.
 * @return string
 */
function wctf_sanitize_fazercards_failure_alert_recipients( $value ) {
    if ( ! is_scalar( $value ) ) {
        return '';
    }

    $emails = array();
    $value  = str_replace( array( "\r", "\n" ), ',', (string) $value );

    foreach ( explode( ',', $value ) as $email ) {
        $email = strtolower( sanitize_email( trim( $email ) ) );

        if ( ! is_email( $email ) ) {
            continue;
        }

        $emails[ $email ] = $email;
    }

    return implode( ', ', array_values( $emails ) );
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
            'offerNonce'    => wp_create_nonce( 'wctf_sync_fazercards_offers' ),
            'browserNonce'  => wp_create_nonce( 'wctf_browse_fazercards_offers' ),
            'messages'      => array(
                'connected'         => __( 'Connected', 'wc-topup-fields' ),
                'failed'            => __( 'Connection failed', 'wc-topup-fields' ),
                'requestFailed'     => __( 'The connection test could not be completed.', 'wc-topup-fields' ),
                'syncing'           => __( 'Synchronizing categories...', 'wc-topup-fields' ),
                'synced'            => __( 'Categories synchronized', 'wc-topup-fields' ),
                'syncFailed'        => __( 'Category synchronization failed', 'wc-topup-fields' ),
                'syncRequestFailed' => __( 'The category synchronization could not be completed.', 'wc-topup-fields' ),
                'offerSyncing'      => __( 'Synchronizing offers...', 'wc-topup-fields' ),
                'offersSynced'      => __( 'Offers synchronized', 'wc-topup-fields' ),
                'offerSyncFailed'   => __( 'Offer synchronization failed', 'wc-topup-fields' ),
                'offerRequestFailed' => __( 'The offer synchronization could not be completed.', 'wc-topup-fields' ),
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

/**
 * Start or continue the batched FazerCards offer synchronization.
 */
function wctf_sync_fazercards_offers() {
    $nonce_is_valid = check_ajax_referer( 'wctf_sync_fazercards_offers', 'nonce', false );

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
                'message' => __( 'You are not allowed to synchronize offers.', 'wc-topup-fields' ),
            ),
            403
        );
    }

    $config = wctf_config();

    if ( empty( $config['api_url'] ) || empty( $config['api_key'] ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Save an API URL and API key before synchronizing offers.', 'wc-topup-fields' ),
            ),
            400
        );
    }

    $operation = isset( $_POST['operation'] )
        ? sanitize_key( wp_unslash( $_POST['operation'] ) )
        : '';

    if ( 'start' === $operation ) {
        wctf_start_fazercards_offer_sync();
    }

    if ( 'continue' === $operation ) {
        wctf_continue_fazercards_offer_sync();
    }

    wp_send_json_error(
        array(
            'message' => __( 'Invalid offer synchronization operation.', 'wc-topup-fields' ),
        ),
        400
    );
}

/**
 * Initialize an offer synchronization transient.
 */
function wctf_start_fazercards_offer_sync() {
    $stored_categories = get_option( 'wctf_fazercards_categories', array() );

    if ( ! is_array( $stored_categories ) || empty( $stored_categories ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Synchronize categories before synchronizing offers.', 'wc-topup-fields' ),
            ),
            400
        );
    }

    $category_ids = array();

    foreach ( $stored_categories as $category ) {
        if ( ! is_array( $category ) || ! isset( $category['category_id'] ) || ! is_scalar( $category['category_id'] ) ) {
            continue;
        }

        $category_id = sanitize_text_field( (string) $category['category_id'] );

        if ( '' !== $category_id ) {
            $category_ids[ $category_id ] = true;
        }
    }

    if ( empty( $category_ids ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'No valid synchronized categories are available.', 'wc-topup-fields' ),
            ),
            400
        );
    }

    $stored_offers       = get_option( 'wctf_fazercards_offers', array() );
    $existing_offer_keys = array();

    if ( is_array( $stored_offers ) ) {
        foreach ( $stored_offers as $stored_offer ) {
            if (
                ! is_array( $stored_offer )
                || ! isset( $stored_offer['category_id'], $stored_offer['offer_id'] )
                || ! is_scalar( $stored_offer['category_id'] )
                || ! is_scalar( $stored_offer['offer_id'] )
            ) {
                continue;
            }

            $stored_offer_key = wctf_get_fazercards_offer_key(
                $stored_offer['category_id'],
                $stored_offer['offer_id']
            );

            if ( '' !== $stored_offer_key ) {
                $existing_offer_keys[ $stored_offer_key ] = true;
            }
        }
    }

    $sync_token = sanitize_key( str_replace( '-', '', wp_generate_uuid4() ) );
    $state      = array(
        'user_id'            => get_current_user_id(),
        'category_ids'       => array_keys( $category_ids ),
        'offset'             => 0,
        'offers'             => array(),
        'topup_fields'       => array(),
        'existing_offer_keys' => $existing_offer_keys,
        'created'            => 0,
        'updated'            => 0,
        'skipped'            => 0,
        'failed_categories'  => array(),
    );

    $stored = set_transient(
        wctf_get_offer_sync_transient_key( $sync_token ),
        $state,
        HOUR_IN_SECONDS
    );

    if ( ! $stored ) {
        wp_send_json_error(
            array(
                'message' => __( 'Unable to initialize offer synchronization.', 'wc-topup-fields' ),
            ),
            500
        );
    }

    $progress              = wctf_get_offer_sync_progress( $state, false );
    $progress['syncToken'] = $sync_token;

    wp_send_json_success( $progress );
}

/**
 * Process the next offer synchronization batch.
 */
function wctf_continue_fazercards_offer_sync() {
    $sync_token = isset( $_POST['syncToken'] )
        ? sanitize_key( wp_unslash( $_POST['syncToken'] ) )
        : '';

    if ( '' === $sync_token ) {
        wp_send_json_error(
            array(
                'message' => __( 'Missing offer synchronization token.', 'wc-topup-fields' ),
            ),
            400
        );
    }

    $transient_key = wctf_get_offer_sync_transient_key( $sync_token );
    $state         = get_transient( $transient_key );

    if ( ! is_array( $state ) || ! isset( $state['user_id'] ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Offer synchronization expired. Start again.', 'wc-topup-fields' ),
            ),
            410
        );
    }

    if ( get_current_user_id() !== (int) $state['user_id'] ) {
        wp_send_json_error(
            array(
                'message' => __( 'This offer synchronization belongs to another user.', 'wc-topup-fields' ),
            ),
            403
        );
    }

    if (
        ! isset( $state['category_ids'], $state['offset'], $state['offers'], $state['topup_fields'], $state['existing_offer_keys'] )
        || ! is_array( $state['category_ids'] )
        || ! is_array( $state['offers'] )
        || ! is_array( $state['topup_fields'] )
        || ! is_array( $state['existing_offer_keys'] )
    ) {
        delete_transient( $transient_key );

        wp_send_json_error(
            array(
                'message' => __( 'Offer synchronization state is invalid. Start again.', 'wc-topup-fields' ),
            ),
            500
        );
    }

    $batch_size = 10;
    $offset     = absint( $state['offset'] );
    $batch      = array_slice( $state['category_ids'], $offset, $batch_size );
    $provider   = new WCTF_FazerCards_Provider();

    foreach ( $batch as $category_id ) {
        $category_id = sanitize_text_field( (string) $category_id );
        $response    = $provider->offers( $category_id );

        if ( ! $provider->isSuccess( $response ) ) {
            $error = sanitize_text_field( $provider->getError( $response ) );

            if ( '' === $error ) {
                $error = __( 'Unable to download offers.', 'wc-topup-fields' );
            }

            $state['failed_categories'][ $category_id ] = $error;
            continue;
        }

        $body = isset( $response['body'] ) && is_array( $response['body'] )
            ? $response['body']
            : array();

        if ( ! isset( $body['offers'] ) || ! is_array( $body['offers'] ) ) {
            $state['failed_categories'][ $category_id ] = __( 'Invalid offers response.', 'wc-topup-fields' );
            continue;
        }

        $state['topup_fields'][ $category_id ] = wctf_normalize_fazercards_topup_field_schema(
            isset( $body['fields'] ) && is_array( $body['fields'] )
                ? $body['fields']
                : array()
        );

        foreach ( $body['offers'] as $offer ) {
            if (
                ! is_array( $offer )
                || ! isset( $offer['offer_id'], $offer['name'], $offer['price_usd'] )
                || ! is_scalar( $offer['offer_id'] )
                || ! is_scalar( $offer['name'] )
                || ! is_scalar( $offer['price_usd'] )
            ) {
                ++$state['skipped'];
                continue;
            }

            $offer_id  = sanitize_text_field( (string) $offer['offer_id'] );
            $name      = sanitize_text_field( (string) $offer['name'] );
            $price_usd = sanitize_text_field( (string) $offer['price_usd'] );

            if ( '' === $offer_id || '' === $name || '' === $price_usd ) {
                ++$state['skipped'];
                continue;
            }

            $offer_key = wctf_get_fazercards_offer_key( $category_id, $offer_id );

            if ( '' === $offer_key ) {
                ++$state['skipped'];
                continue;
            }

            if ( ! isset( $state['offers'][ $offer_key ] ) ) {
                if ( isset( $state['existing_offer_keys'][ $offer_key ] ) ) {
                    ++$state['updated'];
                } else {
                    ++$state['created'];
                }
            }

            $state['offers'][ $offer_key ] = array(
                'offer_id'    => $offer_id,
                'category_id' => $category_id,
                'name'        => $name,
                'price_usd'   => $price_usd,
            );
        }
    }

    $state['offset'] = $offset + count( $batch );
    $complete        = $state['offset'] >= count( $state['category_ids'] );

    if ( ! $complete ) {
        $stored = set_transient( $transient_key, $state, HOUR_IN_SECONDS );

        if ( ! $stored ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Unable to save offer synchronization progress.', 'wc-topup-fields' ),
                ),
                500
            );
        }

        $progress              = wctf_get_offer_sync_progress( $state, false );
        $progress['syncToken'] = $sync_token;

        wp_send_json_success( $progress );
    }

    if ( ! empty( $state['failed_categories'] ) ) {
        delete_transient( $transient_key );

        $progress                = wctf_get_offer_sync_progress( $state, true );
        $failed_category_details = array();

        foreach ( $state['failed_categories'] as $category_id => $error ) {
            $failed_category_details[] = sprintf(
                '%1$s (%2$s)',
                $category_id,
                $error
            );
        }

        $progress['message'] = sprintf(
            /* translators: %s: comma-separated category IDs and errors. */
            __( 'Offers could not be synchronized for these categories: %s. The existing offer snapshot was preserved.', 'wc-topup-fields' ),
            implode( ', ', $failed_category_details )
        );

        wp_send_json_error( $progress, 502 );
    }

    $previous_offers       = get_option( 'wctf_fazercards_offers', array() );
    $previous_topup_fields = get_option( 'wctf_fazercards_topup_fields', array() );

    update_option( 'wctf_fazercards_topup_fields', $state['topup_fields'], false );

    $saved_topup_fields = get_option( 'wctf_fazercards_topup_fields', null );

    if ( $state['topup_fields'] !== $saved_topup_fields ) {
        update_option( 'wctf_fazercards_topup_fields', $previous_topup_fields, false );
        delete_transient( $transient_key );

        $progress            = wctf_get_offer_sync_progress( $state, true );
        $progress['message'] = __( 'The synchronized top-up field schema could not be stored locally. Existing snapshots were preserved.', 'wc-topup-fields' );

        wp_send_json_error( $progress, 500 );
    }

    update_option( 'wctf_fazercards_offers', $state['offers'], false );

    $saved_offers = get_option( 'wctf_fazercards_offers', null );

    if ( $state['offers'] !== $saved_offers ) {
        update_option( 'wctf_fazercards_offers', $previous_offers, false );
        update_option( 'wctf_fazercards_topup_fields', $previous_topup_fields, false );
        delete_transient( $transient_key );

        $progress            = wctf_get_offer_sync_progress( $state, true );
        $progress['message'] = __( 'The synchronized offers could not be stored locally. Existing snapshots were restored.', 'wc-topup-fields' );

        wp_send_json_error( $progress, 500 );
    }

    delete_transient( $transient_key );

    wp_send_json_success( wctf_get_offer_sync_progress( $state, true ) );
}

/**
 * Build the public progress payload for offer synchronization.
 *
 * @param array $state    Synchronization state.
 * @param bool  $complete Whether synchronization has finished.
 * @return array
 */
function wctf_get_offer_sync_progress( $state, $complete ) {
    $failed_categories = isset( $state['failed_categories'] ) && is_array( $state['failed_categories'] )
        ? $state['failed_categories']
        : array();

    return array(
        'complete'            => (bool) $complete,
        'processedCategories' => min( absint( $state['offset'] ), count( $state['category_ids'] ) ),
        'totalCategories'     => count( $state['category_ids'] ),
        'totalOffers'         => count( $state['offers'] ),
        'created'             => absint( $state['created'] ),
        'updated'             => absint( $state['updated'] ),
        'skipped'             => absint( $state['skipped'] ),
        'failedCategories'    => count( $failed_categories ),
        'failedCategoryIds'   => array_keys( $failed_categories ),
    );
}

/**
 * Build a namespaced offer synchronization transient key.
 *
 * @param string $sync_token Synchronization token.
 * @return string
 */
function wctf_get_offer_sync_transient_key( $sync_token ) {
    return 'wctf_offer_sync_' . $sync_token;
}

/**
 * Build the composite local cache key for a FazerCards offer.
 *
 * @param mixed $category_id FazerCards category ID.
 * @param mixed $offer_id    FazerCards offer ID.
 * @return string
 */
function wctf_get_fazercards_offer_key( $category_id, $offer_id ) {
    $category_id = is_scalar( $category_id )
        ? sanitize_text_field( (string) $category_id )
        : '';
    $offer_id = is_scalar( $offer_id )
        ? sanitize_text_field( (string) $offer_id )
        : '';

    if ( '' === $category_id || '' === $offer_id ) {
        return '';
    }

    return $category_id . '::' . $offer_id;
}

/**
 * Normalize a category-level FazerCards top-up field schema.
 *
 * @param mixed $fields Raw fields returned by FazerCards.
 * @return array
 */
function wctf_normalize_fazercards_topup_field_schema( $fields ) {
    if ( ! is_array( $fields ) ) {
        return array();
    }

    $schema = array();

    foreach ( $fields as $field ) {
        if ( ! is_array( $field ) || ! isset( $field['key'] ) || ! is_scalar( $field['key'] ) ) {
            continue;
        }

        $field_key = sanitize_key( (string) $field['key'] );

        if ( '' === $field_key ) {
            continue;
        }

        $label = isset( $field['label'] ) && is_scalar( $field['label'] )
            ? sanitize_text_field( (string) $field['label'] )
            : $field_key;
        $type = isset( $field['type'] ) && is_scalar( $field['type'] )
            ? sanitize_key( (string) $field['type'] )
            : 'text';

        if ( '' === $type ) {
            $type = 'text';
        }

        $required = true;

        if ( array_key_exists( 'required', $field ) ) {
            if ( is_bool( $field['required'] ) ) {
                $required = $field['required'];
            } elseif ( is_scalar( $field['required'] ) ) {
                $required = in_array(
                    strtolower( sanitize_text_field( (string) $field['required'] ) ),
                    array( '1', 'true', 'yes', 'on' ),
                    true
                );
            }
        }

        $schema[ $field_key ] = array(
            'key'      => $field_key,
            'label'    => $label,
            'type'     => $type,
            'required' => $required,
            'options'  => wctf_normalize_fazercards_topup_field_options(
                isset( $field['options'] ) ? $field['options'] : array()
            ),
        );
    }

    return $schema;
}

/**
 * Normalize optional values from a FazerCards top-up field schema.
 *
 * @param mixed $options Raw options.
 * @return array
 */
function wctf_normalize_fazercards_topup_field_options( $options ) {
    if ( ! is_array( $options ) ) {
        return array();
    }

    $normalized = array();

    foreach ( $options as $option_key => $option ) {
        if ( is_scalar( $option ) ) {
            $option = sanitize_text_field( (string) $option );

            if ( is_string( $option_key ) ) {
                $option_key                = sanitize_text_field( $option_key );
                $normalized[ $option_key ] = $option;
            } else {
                $normalized[] = $option;
            }
            continue;
        }

        if ( ! is_array( $option ) ) {
            continue;
        }

        $normalized_option = array();

        foreach ( $option as $value_key => $value ) {
            if ( ! is_scalar( $value ) ) {
                continue;
            }

            $value_key = is_string( $value_key ) ? sanitize_key( $value_key ) : absint( $value_key );
            $normalized_option[ $value_key ] = sanitize_text_field( (string) $value );
        }

        if ( ! empty( $normalized_option ) ) {
            $normalized[] = $normalized_option;
        }
    }

    return $normalized;
}

/**
 * Return a filtered page of locally cached FazerCards offers.
 */
function wctf_browse_fazercards_offers() {
    $nonce_is_valid = check_ajax_referer( 'wctf_browse_fazercards_offers', 'nonce', false );

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
                'message' => __( 'You are not allowed to browse offers.', 'wc-topup-fields' ),
            ),
            403
        );
    }

    $page = isset( $_POST['page'] )
        ? max( 1, absint( wp_unslash( $_POST['page'] ) ) )
        : 1;
    $search = isset( $_POST['search'] )
        ? sanitize_text_field( wp_unslash( $_POST['search'] ) )
        : '';
    $category_filter = isset( $_POST['category_id'] )
        ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) )
        : '';
    $per_page   = 50;
    $offers     = get_option( 'wctf_fazercards_offers', array() );
    $categories = get_option( 'wctf_fazercards_categories', array() );
    $topup_fields = get_option( 'wctf_fazercards_topup_fields', array() );
    $matches    = array();

    if ( ! is_array( $offers ) ) {
        $offers = array();
    }

    if ( ! is_array( $categories ) ) {
        $categories = array();
    }

    if ( ! is_array( $topup_fields ) ) {
        $topup_fields = array();
    }

    foreach ( $offers as $offer ) {
        if ( ! is_array( $offer ) ) {
            continue;
        }

        $offer_id = isset( $offer['offer_id'] ) && is_scalar( $offer['offer_id'] )
            ? sanitize_text_field( (string) $offer['offer_id'] )
            : '';
        $category_id = isset( $offer['category_id'] ) && is_scalar( $offer['category_id'] )
            ? sanitize_text_field( (string) $offer['category_id'] )
            : '';
        $name = isset( $offer['name'] ) && is_scalar( $offer['name'] )
            ? sanitize_text_field( (string) $offer['name'] )
            : '';
        $price_usd = isset( $offer['price_usd'] ) && is_scalar( $offer['price_usd'] )
            ? sanitize_text_field( (string) $offer['price_usd'] )
            : '';

        $offer_key = wctf_get_fazercards_offer_key( $category_id, $offer_id );

        if ( '' === $offer_key ) {
            continue;
        }

        if ( '' !== $category_filter && $category_filter !== $category_id ) {
            continue;
        }

        $category_name = '';

        if (
            isset( $categories[ $category_id ]['name'] )
            && is_scalar( $categories[ $category_id ]['name'] )
        ) {
            $category_name = sanitize_text_field( (string) $categories[ $category_id ]['name'] );
        }

        if (
            '' !== $search
            && false === stripos( $offer_id, $search )
            && false === stripos( $name, $search )
            && false === stripos( $category_id, $search )
            && false === stripos( $category_name, $search )
            && false === stripos( $price_usd, $search )
        ) {
            continue;
        }

        $matches[] = array(
            'offer_key'     => $offer_key,
            'offer_id'      => $offer_id,
            'category_id'   => $category_id,
            'category_name' => $category_name,
            'name'          => $name,
            'price_usd'     => $price_usd,
            'fields_synced' => array_key_exists( $category_id, $topup_fields ),
            'fields'        => array_key_exists( $category_id, $topup_fields )
                ? wctf_normalize_fazercards_topup_field_schema( $topup_fields[ $category_id ] )
                : array(),
        );
    }

    usort(
        $matches,
        function ( $first, $second ) {
            return strnatcasecmp( $first['offer_id'], $second['offer_id'] );
        }
    );

    $total       = count( $matches );
    $total_pages = max( 1, (int) ceil( $total / $per_page ) );
    $page        = min( $page, $total_pages );
    $offset      = ( $page - 1 ) * $per_page;
    $items       = array_slice( $matches, $offset, $per_page );

    wp_send_json_success(
        array(
            'items'       => $items,
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
        )
    );
}
