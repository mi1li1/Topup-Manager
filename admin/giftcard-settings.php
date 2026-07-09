<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_enqueue_scripts', 'wctf_enqueue_fazercards_giftcard_settings_assets' );
add_action( 'wp_ajax_wctf_sync_fazercards_giftcard_categories', 'wctf_sync_fazercards_giftcard_categories' );
add_action( 'wp_ajax_wctf_sync_fazercards_giftcard_cards', 'wctf_sync_fazercards_giftcard_cards' );
add_action( 'wp_ajax_wctf_browse_fazercards_giftcards', 'wctf_browse_fazercards_giftcards' );

/**
 * Load the isolated Gift Card catalog script on the API settings page.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function wctf_enqueue_fazercards_giftcard_settings_assets( $hook_suffix ) {
    if ( 'topup-manager_page_wctf-api' !== $hook_suffix ) {
        return;
    }

    $script_path    = WCTF_PATH . 'admin/js/giftcard-settings.js';
    $script_version = file_exists( $script_path ) ? filemtime( $script_path ) : false;

    if ( false === $script_version ) {
        $script_version = wctf_plugin_version();
    }

    wp_enqueue_script(
        'wctf-giftcard-settings',
        plugins_url( 'js/giftcard-settings.js', __FILE__ ),
        array( 'jquery' ),
        $script_version,
        true
    );

    wp_localize_script(
        'wctf-giftcard-settings',
        'wctfGiftCardSettings',
        array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'categoryNonce' => wp_create_nonce( 'wctf_sync_fazercards_giftcard_categories' ),
            'cardNonce'     => wp_create_nonce( 'wctf_sync_fazercards_giftcard_cards' ),
            'browserNonce'  => wp_create_nonce( 'wctf_browse_fazercards_giftcards' ),
            'messages'      => array(
                'categorySyncing'       => __( 'Synchronizing Gift Card categories...', 'wc-topup-fields' ),
                'categoriesSynced'      => __( 'Gift Card categories synchronized', 'wc-topup-fields' ),
                'categorySyncFailed'    => __( 'Gift Card category synchronization failed', 'wc-topup-fields' ),
                'categoryRequestFailed' => __( 'The Gift Card category synchronization could not be completed.', 'wc-topup-fields' ),
                'cardSyncing'           => __( 'Synchronizing Gift Cards...', 'wc-topup-fields' ),
                'cardsSynced'           => __( 'Gift Cards synchronized', 'wc-topup-fields' ),
                'cardSyncFailed'        => __( 'Gift Card synchronization failed', 'wc-topup-fields' ),
                'cardRequestFailed'     => __( 'The Gift Card synchronization could not be completed.', 'wc-topup-fields' ),
                'browserFailed'         => __( 'Unable to load locally cached Gift Cards.', 'wc-topup-fields' ),
            ),
        )
    );
}

/**
 * Verify common Gift Card catalog AJAX requirements.
 *
 * @param string $nonce_action Nonce action.
 * @param string $capability_message Capability error message.
 */
function wctf_verify_fazercards_giftcard_ajax_request( $nonce_action, $capability_message ) {
    if ( false === check_ajax_referer( $nonce_action, 'nonce', false ) ) {
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
                'message' => $capability_message,
            ),
            403
        );
    }
}

/**
 * Verify that catalog API credentials are configured.
 */
function wctf_verify_fazercards_giftcard_api_config() {
    $config = wctf_config();

    if ( empty( $config['api_url'] ) || empty( $config['api_key'] ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Save an API URL and API key before synchronizing Gift Cards.', 'wc-topup-fields' ),
            ),
            400
        );
    }
}

/**
 * Synchronize all Gift Card category pages into a separate local option.
 */
function wctf_sync_fazercards_giftcard_categories() {
    wctf_verify_fazercards_giftcard_ajax_request(
        'wctf_sync_fazercards_giftcard_categories',
        __( 'You are not allowed to synchronize Gift Card categories.', 'wc-topup-fields' )
    );
    wctf_verify_fazercards_giftcard_api_config();

    $provider          = new WCTF_FazerCards_GiftCards_Provider();
    $stored_categories = get_option( 'wctf_fazercards_giftcard_categories', array() );
    $categories        = array();
    $seen_cursors      = array();
    $cursor            = '';
    $created           = 0;
    $updated           = 0;
    $skipped           = 0;

    if ( ! is_array( $stored_categories ) ) {
        $stored_categories = array();
    }

    do {
        $query = array(
            'limit' => 50,
        );

        if ( '' !== $cursor ) {
            $query['cursor'] = $cursor;
        }

        $response = $provider->categories( $query );

        if ( ! $provider->isSuccess( $response ) ) {
            $error = $provider->getError( $response );

            if ( '' === $error ) {
                $error = __( 'Unable to download Gift Card categories.', 'wc-topup-fields' );
            }

            wp_send_json_error( array( 'message' => sanitize_text_field( $error ) ), 502 );
        }

        $body = isset( $response['body'] ) && is_array( $response['body'] )
            ? $response['body']
            : array();

        if ( ! isset( $body['items'] ) || ! is_array( $body['items'] ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'FazerCards returned an invalid Gift Card category response.', 'wc-topup-fields' ),
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

            if ( ! isset( $categories[ $category_id ] ) ) {
                if ( isset( $stored_categories[ $category_id ] ) ) {
                    ++$updated;
                } else {
                    ++$created;
                }
            }

            $categories[ $category_id ] = array(
                'category_id' => $category_id,
                'name'        => wctf_normalize_fazercards_giftcard_catalog_value( $category, 'name' ),
                'currency'    => wctf_normalize_fazercards_giftcard_catalog_value( $category, 'currency' ),
                'region'      => wctf_normalize_fazercards_giftcard_catalog_value( $category, 'region' ),
            );
        }

        $meta     = isset( $body['meta'] ) && is_array( $body['meta'] ) ? $body['meta'] : array();
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
                    'message' => __( 'FazerCards returned invalid Gift Card category pagination data.', 'wc-topup-fields' ),
                ),
                502
            );
        }

        $seen_cursors[ $next_cursor ] = true;
        $cursor                       = $next_cursor;
    } while ( true );

    update_option( 'wctf_fazercards_giftcard_categories', $categories, false );

    if ( $categories !== get_option( 'wctf_fazercards_giftcard_categories', null ) ) {
        update_option( 'wctf_fazercards_giftcard_categories', $stored_categories, false );

        wp_send_json_error(
            array(
                'message' => __( 'Gift Card categories could not be stored locally. The existing snapshot was preserved.', 'wc-topup-fields' ),
            ),
            500
        );
    }

    wp_send_json_success(
        array(
            'total'   => count( $categories ),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        )
    );
}

/**
 * Start or continue the batched Gift Card catalog synchronization.
 */
function wctf_sync_fazercards_giftcard_cards() {
    wctf_verify_fazercards_giftcard_ajax_request(
        'wctf_sync_fazercards_giftcard_cards',
        __( 'You are not allowed to synchronize Gift Cards.', 'wc-topup-fields' )
    );
    wctf_verify_fazercards_giftcard_api_config();

    $operation = isset( $_POST['operation'] ) && is_scalar( $_POST['operation'] )
        ? sanitize_key( wp_unslash( $_POST['operation'] ) )
        : '';

    if ( 'start' === $operation ) {
        wctf_start_fazercards_giftcard_card_sync();
    }

    if ( 'continue' === $operation ) {
        wctf_continue_fazercards_giftcard_card_sync();
    }

    wp_send_json_error(
        array(
            'message' => __( 'Invalid Gift Card synchronization operation.', 'wc-topup-fields' ),
        ),
        400
    );
}

/**
 * Initialize the user-isolated Gift Card card synchronization state.
 */
function wctf_start_fazercards_giftcard_card_sync() {
    $stored_categories = get_option( 'wctf_fazercards_giftcard_categories', array() );

    if ( ! is_array( $stored_categories ) || empty( $stored_categories ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Synchronize Gift Card categories before synchronizing Gift Cards.', 'wc-topup-fields' ),
            ),
            400
        );
    }

    $category_ids = array();

    foreach ( $stored_categories as $category ) {
        if (
            ! is_array( $category )
            || ! isset( $category['category_id'] )
            || ! is_scalar( $category['category_id'] )
        ) {
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
                'message' => __( 'No valid synchronized Gift Card categories are available.', 'wc-topup-fields' ),
            ),
            400
        );
    }

    $stored_cards       = get_option( 'wctf_fazercards_giftcard_offers', array() );
    $existing_card_keys = array();

    if ( is_array( $stored_cards ) ) {
        foreach ( $stored_cards as $card ) {
            if ( ! is_array( $card ) ) {
                continue;
            }

            $card_key = wctf_get_fazercards_giftcard_card_key(
                isset( $card['category_id'] ) ? $card['category_id'] : '',
                isset( $card['card_id'] ) ? $card['card_id'] : ''
            );

            if ( '' !== $card_key ) {
                $existing_card_keys[ $card_key ] = true;
            }
        }
    }

    $sync_token = sanitize_key( str_replace( '-', '', wp_generate_uuid4() ) );
    $state      = array(
        'user_id'            => get_current_user_id(),
        'category_ids'       => array_keys( $category_ids ),
        'offset'             => 0,
        'cards'              => array(),
        'existing_card_keys' => $existing_card_keys,
        'created'            => 0,
        'updated'            => 0,
        'skipped'            => 0,
        'failed_categories'  => array(),
    );

    if (
        ! set_transient(
            wctf_get_fazercards_giftcard_sync_transient_key( $sync_token ),
            $state,
            HOUR_IN_SECONDS
        )
    ) {
        wp_send_json_error(
            array(
                'message' => __( 'Unable to initialize Gift Card synchronization.', 'wc-topup-fields' ),
            ),
            500
        );
    }

    $progress              = wctf_get_fazercards_giftcard_sync_progress( $state, false );
    $progress['syncToken'] = $sync_token;

    wp_send_json_success( $progress );
}

/**
 * Process ten Gift Card categories and atomically finalize the card snapshot.
 */
function wctf_continue_fazercards_giftcard_card_sync() {
    $sync_token = isset( $_POST['syncToken'] ) && is_scalar( $_POST['syncToken'] )
        ? sanitize_key( wp_unslash( $_POST['syncToken'] ) )
        : '';

    if ( '' === $sync_token ) {
        wp_send_json_error( array( 'message' => __( 'Missing Gift Card synchronization token.', 'wc-topup-fields' ) ), 400 );
    }

    $transient_key = wctf_get_fazercards_giftcard_sync_transient_key( $sync_token );
    $state         = get_transient( $transient_key );

    if ( ! is_array( $state ) || ! isset( $state['user_id'] ) ) {
        wp_send_json_error( array( 'message' => __( 'Gift Card synchronization expired. Start again.', 'wc-topup-fields' ) ), 410 );
    }

    if ( get_current_user_id() !== (int) $state['user_id'] ) {
        wp_send_json_error( array( 'message' => __( 'This Gift Card synchronization belongs to another user.', 'wc-topup-fields' ) ), 403 );
    }

    if (
        ! isset( $state['category_ids'], $state['offset'], $state['cards'], $state['existing_card_keys'], $state['failed_categories'] )
        || ! is_array( $state['category_ids'] )
        || ! is_array( $state['cards'] )
        || ! is_array( $state['existing_card_keys'] )
        || ! is_array( $state['failed_categories'] )
    ) {
        delete_transient( $transient_key );
        wp_send_json_error( array( 'message' => __( 'Gift Card synchronization state is invalid. Start again.', 'wc-topup-fields' ) ), 500 );
    }

    $batch_size = 10;
    $offset     = absint( $state['offset'] );
    $batch      = array_slice( $state['category_ids'], $offset, $batch_size );
    $provider   = new WCTF_FazerCards_GiftCards_Provider();

    foreach ( $batch as $category_id ) {
        $category_id = sanitize_text_field( (string) $category_id );
        $response    = $provider->cards( $category_id );

        if ( ! $provider->isSuccess( $response ) ) {
            $error = $provider->getError( $response );

            $state['failed_categories'][ $category_id ] = '' !== $error
                ? sanitize_text_field( $error )
                : __( 'Unable to download Gift Cards.', 'wc-topup-fields' );
            continue;
        }

        $body = isset( $response['body'] ) && is_array( $response['body'] )
            ? $response['body']
            : array();

        if ( ! isset( $body['offers'] ) || ! is_array( $body['offers'] ) ) {
            $state['failed_categories'][ $category_id ] = __( 'Invalid Gift Card response.', 'wc-topup-fields' );
            continue;
        }

        $response_currency = wctf_normalize_fazercards_giftcard_catalog_value( $body, 'currency' );
        $response_region   = wctf_normalize_fazercards_giftcard_catalog_value( $body, 'region' );

        foreach ( $body['offers'] as $card ) {
            if (
                ! is_array( $card )
                || ! isset( $card['card_id'] )
                || ! is_scalar( $card['card_id'] )
            ) {
                ++$state['skipped'];
                continue;
            }

            $card_id  = sanitize_text_field( (string) $card['card_id'] );
            $card_key = wctf_get_fazercards_giftcard_card_key( $category_id, $card_id );

            if ( '' === $card_key ) {
                ++$state['skipped'];
                continue;
            }

            if ( ! isset( $state['cards'][ $card_key ] ) ) {
                if ( isset( $state['existing_card_keys'][ $card_key ] ) ) {
                    ++$state['updated'];
                } else {
                    ++$state['created'];
                }
            }

            $currency = wctf_normalize_fazercards_giftcard_catalog_value( $card, 'currency' );
            $region   = wctf_normalize_fazercards_giftcard_catalog_value( $card, 'region' );

            if ( '' === $currency ) {
                $currency = $response_currency;
            }

            if ( '' === $region ) {
                $region = $response_region;
            }

            // Explicit allowlist: never retain codes, PINs, serials, redeem URLs, or order data.
            $state['cards'][ $card_key ] = array(
                'category_id'        => $category_id,
                'card_id'            => $card_id,
                'name'               => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'name' ),
                'price_usd'          => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'price_usd' ),
                'currency'           => $currency,
                'region'             => $region,
                'stock'              => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'stock' ),
                'min_order_quantity' => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'min_order_quantity' ),
                'max_order_quantity' => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'max_order_quantity' ),
            );
        }
    }

    $state['offset'] = $offset + count( $batch );
    $complete        = $state['offset'] >= count( $state['category_ids'] );

    if ( ! $complete ) {
        if ( ! set_transient( $transient_key, $state, HOUR_IN_SECONDS ) ) {
            wp_send_json_error( array( 'message' => __( 'Unable to save Gift Card synchronization progress.', 'wc-topup-fields' ) ), 500 );
        }

        $progress              = wctf_get_fazercards_giftcard_sync_progress( $state, false );
        $progress['syncToken'] = $sync_token;
        wp_send_json_success( $progress );
    }

    if ( ! empty( $state['failed_categories'] ) ) {
        delete_transient( $transient_key );

        $progress = wctf_get_fazercards_giftcard_sync_progress( $state, true );
        $details  = array();

        foreach ( $state['failed_categories'] as $category_id => $error ) {
            $details[] = sprintf( '%1$s (%2$s)', $category_id, $error );
        }

        $progress['message'] = sprintf(
            /* translators: %s: comma-separated category IDs and errors. */
            __( 'Gift Cards could not be synchronized for these categories: %s. The existing Gift Card snapshot was preserved.', 'wc-topup-fields' ),
            implode( ', ', $details )
        );

        wp_send_json_error( $progress, 502 );
    }

    $previous_cards = get_option( 'wctf_fazercards_giftcard_offers', array() );
    update_option( 'wctf_fazercards_giftcard_offers', $state['cards'], false );

    if ( $state['cards'] !== get_option( 'wctf_fazercards_giftcard_offers', null ) ) {
        update_option( 'wctf_fazercards_giftcard_offers', $previous_cards, false );
        delete_transient( $transient_key );

        $progress            = wctf_get_fazercards_giftcard_sync_progress( $state, true );
        $progress['message'] = __( 'The synchronized Gift Cards could not be stored locally. The existing snapshot was restored.', 'wc-topup-fields' );
        wp_send_json_error( $progress, 500 );
    }

    delete_transient( $transient_key );
    wp_send_json_success( wctf_get_fazercards_giftcard_sync_progress( $state, true ) );
}

/**
 * Return a safe scalar catalog field from an allowlisted source key.
 *
 * @param array  $record Source record.
 * @param string $key    Source key.
 * @return string
 */
function wctf_normalize_fazercards_giftcard_catalog_value( $record, $key ) {
    if ( ! is_array( $record ) || ! isset( $record[ $key ] ) || ! is_scalar( $record[ $key ] ) ) {
        return '';
    }

    return sanitize_text_field( (string) $record[ $key ] );
}

/**
 * Build a composite local Gift Card card key.
 *
 * @param mixed $category_id Gift Card category ID.
 * @param mixed $card_id     Gift Card card ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_card_key( $category_id, $card_id ) {
    $category_id = is_scalar( $category_id ) ? sanitize_text_field( (string) $category_id ) : '';
    $card_id     = is_scalar( $card_id ) ? sanitize_text_field( (string) $card_id ) : '';

    if ( '' === $category_id || '' === $card_id ) {
        return '';
    }

    return $category_id . '::' . $card_id;
}

/**
 * Build the Gift Card synchronization transient key.
 *
 * @param string $sync_token Synchronization token.
 * @return string
 */
function wctf_get_fazercards_giftcard_sync_transient_key( $sync_token ) {
    return 'wctf_giftcard_sync_' . sanitize_key( $sync_token );
}

/**
 * Build a public Gift Card synchronization progress payload.
 *
 * @param array $state    Synchronization state.
 * @param bool  $complete Whether synchronization is complete.
 * @return array
 */
function wctf_get_fazercards_giftcard_sync_progress( $state, $complete ) {
    $category_ids      = isset( $state['category_ids'] ) && is_array( $state['category_ids'] ) ? $state['category_ids'] : array();
    $cards             = isset( $state['cards'] ) && is_array( $state['cards'] ) ? $state['cards'] : array();
    $failed_categories = isset( $state['failed_categories'] ) && is_array( $state['failed_categories'] ) ? $state['failed_categories'] : array();

    return array(
        'complete'            => (bool) $complete,
        'processedCategories' => min( absint( $state['offset'] ), count( $category_ids ) ),
        'totalCategories'     => count( $category_ids ),
        'totalCards'          => count( $cards ),
        'created'             => absint( $state['created'] ),
        'updated'             => absint( $state['updated'] ),
        'skipped'             => absint( $state['skipped'] ),
        'failedCategories'    => count( $failed_categories ),
        'failedCategoryIds'   => array_keys( $failed_categories ),
    );
}

/**
 * Return one filtered page from the local Gift Card catalog snapshot.
 */
function wctf_browse_fazercards_giftcards() {
    wctf_verify_fazercards_giftcard_ajax_request(
        'wctf_browse_fazercards_giftcards',
        __( 'You are not allowed to browse Gift Cards.', 'wc-topup-fields' )
    );

    $page = isset( $_POST['page'] ) && is_scalar( $_POST['page'] )
        ? max( 1, absint( wp_unslash( $_POST['page'] ) ) )
        : 1;
    $search = isset( $_POST['search'] ) && is_scalar( $_POST['search'] )
        ? sanitize_text_field( wp_unslash( $_POST['search'] ) )
        : '';
    $category_filter = isset( $_POST['category_id'] ) && is_scalar( $_POST['category_id'] )
        ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) )
        : '';
    $per_page   = 50;
    $cards      = get_option( 'wctf_fazercards_giftcard_offers', array() );
    $categories = get_option( 'wctf_fazercards_giftcard_categories', array() );
    $matches    = array();

    if ( ! is_array( $cards ) ) {
        $cards = array();
    }

    if ( ! is_array( $categories ) ) {
        $categories = array();
    }

    foreach ( $cards as $card ) {
        if ( ! is_array( $card ) ) {
            continue;
        }

        $category_id = wctf_normalize_fazercards_giftcard_catalog_value( $card, 'category_id' );
        $card_id     = wctf_normalize_fazercards_giftcard_catalog_value( $card, 'card_id' );
        $card_key    = wctf_get_fazercards_giftcard_card_key( $category_id, $card_id );

        if ( '' === $card_key || ( '' !== $category_filter && $category_filter !== $category_id ) ) {
            continue;
        }

        $category_name = '';

        if ( isset( $categories[ $category_id ] ) && is_array( $categories[ $category_id ] ) ) {
            $category_name = wctf_normalize_fazercards_giftcard_catalog_value( $categories[ $category_id ], 'name' );
        }

        $item = array(
            'card_key'           => $card_key,
            'category_id'        => $category_id,
            'category_name'      => $category_name,
            'card_id'            => $card_id,
            'name'               => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'name' ),
            'price_usd'          => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'price_usd' ),
            'currency'           => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'currency' ),
            'region'             => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'region' ),
            'stock'              => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'stock' ),
            'min_order_quantity' => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'min_order_quantity' ),
            'max_order_quantity' => wctf_normalize_fazercards_giftcard_catalog_value( $card, 'max_order_quantity' ),
        );

        if ( '' !== $search ) {
            $haystacks = array_values( $item );
            $matched   = false;

            foreach ( $haystacks as $haystack ) {
                if ( false !== stripos( (string) $haystack, $search ) ) {
                    $matched = true;
                    break;
                }
            }

            if ( ! $matched ) {
                continue;
            }
        }

        $matches[] = $item;
    }

    usort(
        $matches,
        function ( $first, $second ) {
            $category_comparison = strnatcasecmp( $first['category_id'], $second['category_id'] );

            return 0 !== $category_comparison
                ? $category_comparison
                : strnatcasecmp( $first['card_id'], $second['card_id'] );
        }
    );

    $total       = count( $matches );
    $total_pages = max( 1, (int) ceil( $total / $per_page ) );
    $page        = min( $page, $total_pages );
    $offset      = ( $page - 1 ) * $per_page;

    wp_send_json_success(
        array(
            'items'       => array_slice( $matches, $offset, $per_page ),
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
        )
    );
}
