<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册设置
 */

$offer_browser_categories       = get_option( 'wctf_fazercards_categories', array() );
$offer_browser_category_options = array();

if ( is_array( $offer_browser_categories ) ) {
    foreach ( $offer_browser_categories as $category ) {
        if (
            ! is_array( $category )
            || ! isset( $category['category_id'] )
            || ! is_scalar( $category['category_id'] )
        ) {
            continue;
        }

        $category_id   = sanitize_text_field( (string) $category['category_id'] );
        $category_name = '';

        if ( isset( $category['name'] ) && is_scalar( $category['name'] ) ) {
            $category_name = sanitize_text_field( (string) $category['name'] );
        }

        if ( '' === $category_id ) {
            continue;
        }

        $offer_browser_category_options[] = array(
            'id'   => $category_id,
            'name' => $category_name,
        );
    }
}

usort(
    $offer_browser_category_options,
    function ( $first, $second ) {
        $name_comparison = strcasecmp( $first['name'], $second['name'] );

        if ( 0 !== $name_comparison ) {
            return $name_comparison;
        }

        return strcasecmp( $first['id'], $second['id'] );
    }
);

$giftcard_browser_categories       = get_option( 'wctf_fazercards_giftcard_categories', array() );
$giftcard_browser_category_options = array();

if ( is_array( $giftcard_browser_categories ) ) {
    foreach ( $giftcard_browser_categories as $category ) {
        if (
            ! is_array( $category )
            || ! isset( $category['category_id'] )
            || ! is_scalar( $category['category_id'] )
        ) {
            continue;
        }

        $category_id   = sanitize_text_field( (string) $category['category_id'] );
        $category_name = isset( $category['name'] ) && is_scalar( $category['name'] )
            ? sanitize_text_field( (string) $category['name'] )
            : '';

        if ( '' === $category_id ) {
            continue;
        }

        $giftcard_browser_category_options[] = array(
            'id'   => $category_id,
            'name' => $category_name,
        );
    }
}

usort(
    $giftcard_browser_category_options,
    function ( $first, $second ) {
        $name_comparison = strcasecmp( $first['name'], $second['name'] );

        return 0 !== $name_comparison
            ? $name_comparison
            : strcasecmp( $first['id'], $second['id'] );
    }
);

$failure_alert_recipients = get_option( 'wctf_fazercards_failure_alert_recipients', '' );
$failure_alert_recipients = is_scalar( $failure_alert_recipients )
    ? (string) $failure_alert_recipients
    : '';

$giftcard_crypto_status = function_exists( 'wctf_fazercards_giftcard_crypto_status' )
    ? wctf_fazercards_giftcard_crypto_status()
    : array(
        'ready'          => false,
        'key_configured' => false,
        'key_valid'      => false,
        'algorithm'      => 'none',
        'message'        => __( 'Encrypted Gift Card secret storage is unavailable.', 'wc-topup-fields' ),
    );
$giftcard_crypto_algorithm_labels = array(
    'sodium-secretbox' => __( 'Sodium Secretbox', 'wc-topup-fields' ),
    'aes-256-gcm'      => __( 'AES-256-GCM', 'wc-topup-fields' ),
    'none'             => __( 'None', 'wc-topup-fields' ),
);
$giftcard_crypto_algorithm = isset( $giftcard_crypto_status['algorithm'] )
    && isset( $giftcard_crypto_algorithm_labels[ $giftcard_crypto_status['algorithm'] ] )
        ? $giftcard_crypto_algorithm_labels[ $giftcard_crypto_status['algorithm'] ]
        : $giftcard_crypto_algorithm_labels['none'];
$giftcard_crypto_notice_class = ! empty( $giftcard_crypto_status['ready'] )
    ? 'notice-success'
    : 'notice-warning';

?>

<div class="wrap">

    <h1>FazerCards API 设置</h1>

    <form method="post" action="options.php">

        <?php
        settings_fields('wctf_api_settings');
        ?>

        <table class="form-table">

            <tr>
                <th scope="row">API Base URL</th>
                <td>
                    <input
                        type="text"
                        name="wctf_api_url"
                        value="<?php echo esc_attr(get_option('wctf_api_url', 'https://api.fzr.cards')); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">API Key</th>
                <td>
                    <input
                        type="text"
                        name="wctf_api_key"
                        value="<?php echo esc_attr(get_option('wctf_api_key')); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">API Secret</th>
                <td>
                    <input
                        type="password"
                        name="wctf_api_secret"
                        value="<?php echo esc_attr(get_option('wctf_api_secret')); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e( 'Automatic FazerCards Submission', 'wc-topup-fields' ); ?>
                </th>
                <td>
                    <label for="wctf-fazercards-auto-submit-enabled">
                        <input
                            type="hidden"
                            name="wctf_fazercards_auto_submit_enabled"
                            value="no"
                        >
                        <input
                            type="checkbox"
                            id="wctf-fazercards-auto-submit-enabled"
                            name="wctf_fazercards_auto_submit_enabled"
                            value="yes"
                            <?php checked( 'yes', get_option( 'wctf_fazercards_auto_submit_enabled', 'no' ) ); ?>
                        >
                        <?php
                        esc_html_e(
                            'Enable real automatic FazerCards order submission when paid WooCommerce orders enter Processing or Completed.',
                            'wc-topup-fields'
                        );
                        ?>
                    </label>
                    <p class="description">
                        <strong>
                            <?php
                            esc_html_e(
                                'Warning: enabling this setting can create real FazerCards orders and use your FazerCards balance.',
                                'wc-topup-fields'
                            );
                            ?>
                        </strong>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wctf-fazercards-failure-alert-recipients">
                        <?php esc_html_e( 'FazerCards Failure Alert Recipients', 'wc-topup-fields' ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        id="wctf-fazercards-failure-alert-recipients"
                        name="wctf_fazercards_failure_alert_recipients"
                        value="<?php echo esc_attr( $failure_alert_recipients ); ?>"
                        class="regular-text"
                        placeholder="<?php echo esc_attr__( 'admin@example.com, support@example.com, orders@example.com', 'wc-topup-fields' ); ?>"
                    >
                    <p class="description">
                        <?php esc_html_e( 'Enter one or more admin email addresses separated by commas. If empty, the WordPress admin email will be used.', 'wc-topup-fields' ); ?>
                    </p>
                </td>
            </tr>

        </table>

        <?php submit_button('保存设置'); ?>

    </form>

    <hr>

    <h2><?php esc_html_e( 'Test Connection', 'wc-topup-fields' ); ?></h2>

    <p>
        <?php esc_html_e( 'Save the API settings above before running the connection test.', 'wc-topup-fields' ); ?>
    </p>

    <p>
        <button type="button" class="button button-secondary" id="wctf-test-connection">
            <?php esc_html_e( 'Test Connection', 'wc-topup-fields' ); ?>
        </button>
        <span class="spinner" id="wctf-test-connection-spinner" aria-hidden="true"></span>
    </p>

    <table
        class="widefat striped"
        id="wctf-connection-results"
        style="max-width: 700px;"
        aria-live="polite"
        hidden
    >
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e( 'Connection Status', 'wc-topup-fields' ); ?></th>
                <td id="wctf-connection-status"></td>
            </tr>
            <tr id="wctf-account-row" hidden>
                <th scope="row"><?php esc_html_e( 'Account Name', 'wc-topup-fields' ); ?></th>
                <td id="wctf-account-name"></td>
            </tr>
            <tr id="wctf-balance-row" hidden>
                <th scope="row"><?php esc_html_e( 'Balance', 'wc-topup-fields' ); ?></th>
                <td id="wctf-balance"></td>
            </tr>
            <tr id="wctf-error-row" hidden>
                <th scope="row"><?php esc_html_e( 'Error Message', 'wc-topup-fields' ); ?></th>
                <td id="wctf-connection-error"></td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2><?php esc_html_e( 'Category Synchronization', 'wc-topup-fields' ); ?></h2>

    <p>
        <?php esc_html_e( 'Download the latest FazerCards categories and store them locally.', 'wc-topup-fields' ); ?>
    </p>

    <p>
        <button type="button" class="button button-secondary" id="wctf-sync-categories">
            <?php esc_html_e( 'Sync Categories', 'wc-topup-fields' ); ?>
        </button>
        <span class="spinner" id="wctf-sync-categories-spinner" aria-hidden="true"></span>
    </p>

    <table
        class="widefat striped"
        id="wctf-category-sync-results"
        style="max-width: 700px;"
        aria-live="polite"
        hidden
    >
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e( 'Synchronization Status', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-sync-status"></td>
            </tr>
            <tr id="wctf-category-total-row" hidden>
                <th scope="row"><?php esc_html_e( 'Total Categories', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-total"></td>
            </tr>
            <tr id="wctf-category-created-row" hidden>
                <th scope="row"><?php esc_html_e( 'Created', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-created"></td>
            </tr>
            <tr id="wctf-category-updated-row" hidden>
                <th scope="row"><?php esc_html_e( 'Updated', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-updated"></td>
            </tr>
            <tr id="wctf-category-skipped-row" hidden>
                <th scope="row"><?php esc_html_e( 'Skipped', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-skipped"></td>
            </tr>
            <tr id="wctf-category-error-row" hidden>
                <th scope="row"><?php esc_html_e( 'Error Message', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-sync-error"></td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2><?php esc_html_e( 'Offer Synchronization', 'wc-topup-fields' ); ?></h2>

    <p>
        <?php esc_html_e( 'Download offers for all locally synchronized FazerCards categories.', 'wc-topup-fields' ); ?>
    </p>

    <p>
        <button type="button" class="button button-secondary" id="wctf-sync-offers">
            <?php esc_html_e( 'Sync Offers', 'wc-topup-fields' ); ?>
        </button>
        <span class="spinner" id="wctf-sync-offers-spinner" aria-hidden="true"></span>
    </p>

    <table
        class="widefat striped"
        id="wctf-offer-sync-results"
        style="max-width: 700px;"
        aria-live="polite"
        hidden
    >
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e( 'Synchronization Status', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-sync-status"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Processed Categories', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-processed-categories">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Total Categories', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-total-categories">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Total Offers', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-total">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Created', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-created">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Updated', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-updated">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Skipped', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-skipped">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Failed Categories', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-failed-categories">0</td>
            </tr>
            <tr id="wctf-offer-error-row" hidden>
                <th scope="row"><?php esc_html_e( 'Error Message', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-sync-error"></td>
            </tr>
        </tbody>
    </table>

    <hr>

    <section id="wctf-offer-browser" aria-labelledby="wctf-offer-browser-heading">
        <h2 id="wctf-offer-browser-heading">
            <?php esc_html_e( 'Offer Browser', 'wc-topup-fields' ); ?>
        </h2>

        <p>
            <?php esc_html_e( 'Search and browse locally cached FazerCards offers.', 'wc-topup-fields' ); ?>
        </p>

        <div class="tablenav top">
            <label for="wctf-offer-browser-search">
                <?php esc_html_e( 'Search offer, category, ID or price', 'wc-topup-fields' ); ?>
            </label>
            <input
                type="search"
                id="wctf-offer-browser-search"
                class="regular-text"
                placeholder="<?php echo esc_attr__( 'Search offer, category, ID or price', 'wc-topup-fields' ); ?>"
            >

            <label for="wctf-offer-browser-category">
                <?php esc_html_e( 'Category', 'wc-topup-fields' ); ?>
            </label>
            <select id="wctf-offer-browser-category">
                <option value=""><?php esc_html_e( 'All Categories', 'wc-topup-fields' ); ?></option>
                <?php foreach ( $offer_browser_category_options as $category_option ) : ?>
                    <option value="<?php echo esc_attr( $category_option['id'] ); ?>">
                        <?php
                        echo esc_html(
                            '' !== $category_option['name']
                                ? $category_option['name'] . ' (' . $category_option['id'] . ')'
                                : $category_option['id']
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="button" class="button button-primary" id="wctf-offer-browser-submit">
                <?php esc_html_e( 'Search', 'wc-topup-fields' ); ?>
            </button>
            <button type="button" class="button" id="wctf-offer-browser-reset">
                <?php esc_html_e( 'Reset', 'wc-topup-fields' ); ?>
            </button>
        </div>

        <div
            class="notice notice-error inline"
            id="wctf-offer-browser-error"
            role="alert"
            hidden
        >
            <p id="wctf-offer-browser-error-message"></p>
        </div>

        <div class="notice notice-info inline" id="wctf-offer-browser-empty" hidden>
            <p><?php esc_html_e( 'No matching offers found.', 'wc-topup-fields' ); ?></p>
        </div>

        <table class="widefat fixed striped" id="wctf-offer-browser-results">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Offer ID', 'wc-topup-fields' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Category ID', 'wc-topup-fields' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Category Name', 'wc-topup-fields' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Offer Name', 'wc-topup-fields' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Price USD', 'wc-topup-fields' ); ?></th>
                </tr>
            </thead>
            <tbody id="wctf-offer-browser-rows"></tbody>
        </table>

        <div class="tablenav bottom" id="wctf-offer-browser-pagination">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <span id="wctf-offer-browser-total-results">0</span>
                    <?php esc_html_e( 'total results', 'wc-topup-fields' ); ?>
                </span>

                <button
                    type="button"
                    class="button"
                    id="wctf-offer-browser-previous"
                    disabled
                >
                    <?php esc_html_e( 'Previous', 'wc-topup-fields' ); ?>
                </button>

                <span class="paging-input">
                    <?php esc_html_e( 'Current page', 'wc-topup-fields' ); ?>
                    <span id="wctf-offer-browser-current-page">1</span>
                    /
                    <?php esc_html_e( 'Total pages', 'wc-topup-fields' ); ?>
                    <span id="wctf-offer-browser-total-pages">1</span>
                </span>

                <button
                    type="button"
                    class="button"
                    id="wctf-offer-browser-next"
                    disabled
                >
                    <?php esc_html_e( 'Next', 'wc-topup-fields' ); ?>
                </button>
            </div>
        </div>
    </section>

    <hr>

    <section id="wctf-giftcard-catalog" aria-labelledby="wctf-giftcard-catalog-heading">
        <h2 id="wctf-giftcard-catalog-heading">
            <?php esc_html_e( 'FazerCards Gift Cards', 'wc-topup-fields' ); ?>
        </h2>

        <p>
            <?php esc_html_e( 'Synchronize and browse the read-only FazerCards Gift Card catalog. This section does not purchase Gift Cards.', 'wc-topup-fields' ); ?>
        </p>

        <h3><?php esc_html_e( 'Gift Card Encrypted Storage Readiness', 'wc-topup-fields' ); ?></h3>

        <div
            class="notice <?php echo esc_attr( $giftcard_crypto_notice_class ); ?> inline"
            role="status"
        >
            <table class="widefat striped" style="max-width: 700px; margin: 12px 0;">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Encryption ready', 'wc-topup-fields' ); ?></th>
                        <td>
                            <?php echo ! empty( $giftcard_crypto_status['ready'] ) ? esc_html__( 'Yes', 'wc-topup-fields' ) : esc_html__( 'No', 'wc-topup-fields' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Encryption key configured', 'wc-topup-fields' ); ?></th>
                        <td>
                            <?php echo ! empty( $giftcard_crypto_status['key_configured'] ) ? esc_html__( 'Yes', 'wc-topup-fields' ) : esc_html__( 'No', 'wc-topup-fields' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Encryption key valid', 'wc-topup-fields' ); ?></th>
                        <td>
                            <?php echo ! empty( $giftcard_crypto_status['key_valid'] ) ? esc_html__( 'Yes', 'wc-topup-fields' ) : esc_html__( 'No', 'wc-topup-fields' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Available algorithm', 'wc-topup-fields' ); ?></th>
                        <td><?php echo esc_html( $giftcard_crypto_algorithm ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Readiness message', 'wc-topup-fields' ); ?></th>
                        <td>
                            <?php
                            echo esc_html(
                                isset( $giftcard_crypto_status['message'] )
                                    ? $giftcard_crypto_status['message']
                                    : __( 'Encrypted Gift Card secret storage is unavailable.', 'wc-topup-fields' )
                            );
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if ( empty( $giftcard_crypto_status['ready'] ) ) : ?>
                <p>
                    <strong>
                        <?php esc_html_e( 'Gift Card purchases remain disabled until encrypted storage is ready.', 'wc-topup-fields' ); ?>
                    </strong>
                </p>
            <?php endif; ?>
        </div>

        <h3><?php esc_html_e( 'Gift Card Category Synchronization', 'wc-topup-fields' ); ?></h3>

        <p>
            <button type="button" class="button button-secondary" id="wctf-giftcard-sync-categories">
                <?php esc_html_e( 'Sync Gift Card Categories', 'wc-topup-fields' ); ?>
            </button>
            <span class="spinner" id="wctf-giftcard-sync-categories-spinner" aria-hidden="true"></span>
        </p>

        <table
            class="widefat striped"
            id="wctf-giftcard-category-sync-results"
            style="max-width: 700px;"
            aria-live="polite"
            hidden
        >
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Synchronization Status', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-category-sync-status"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Total Categories', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-category-total">0</td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Created', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-category-created">0</td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Updated', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-category-updated">0</td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Skipped', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-category-skipped">0</td>
                </tr>
                <tr id="wctf-giftcard-category-error-row" hidden>
                    <th scope="row"><?php esc_html_e( 'Error Message', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-category-error"></td>
                </tr>
            </tbody>
        </table>

        <h3><?php esc_html_e( 'Gift Card Synchronization', 'wc-topup-fields' ); ?></h3>

        <p>
            <?php esc_html_e( 'Synchronize cards/SKUs from all locally cached Gift Card categories.', 'wc-topup-fields' ); ?>
        </p>

        <p>
            <button type="button" class="button button-secondary" id="wctf-giftcard-sync-cards">
                <?php esc_html_e( 'Sync Gift Cards', 'wc-topup-fields' ); ?>
            </button>
            <span class="spinner" id="wctf-giftcard-sync-cards-spinner" aria-hidden="true"></span>
        </p>

        <table
            class="widefat striped"
            id="wctf-giftcard-card-sync-results"
            style="max-width: 700px;"
            aria-live="polite"
            hidden
        >
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Synchronization Status', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-card-sync-status"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Processed Categories', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-card-processed-categories">0</td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Total Categories', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-card-total-categories">0</td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Total Cards', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-card-total">0</td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Created', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-card-created">0</td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Updated', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-card-updated">0</td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Skipped', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-card-skipped">0</td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Failed Categories', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-card-failed-categories">0</td>
                </tr>
                <tr id="wctf-giftcard-card-error-row" hidden>
                    <th scope="row"><?php esc_html_e( 'Error Message', 'wc-topup-fields' ); ?></th>
                    <td id="wctf-giftcard-card-error"></td>
                </tr>
            </tbody>
        </table>

        <h3><?php esc_html_e( 'Gift Card Browser', 'wc-topup-fields' ); ?></h3>

        <div id="wctf-giftcard-browser">
            <div class="tablenav top">
                <label for="wctf-giftcard-browser-search">
                    <?php esc_html_e( 'Search Gift Cards', 'wc-topup-fields' ); ?>
                </label>
                <input
                    type="search"
                    id="wctf-giftcard-browser-search"
                    class="regular-text"
                    placeholder="<?php echo esc_attr__( 'Search card, category, ID, price, currency or region', 'wc-topup-fields' ); ?>"
                >

                <label for="wctf-giftcard-browser-category">
                    <?php esc_html_e( 'Category', 'wc-topup-fields' ); ?>
                </label>
                <select id="wctf-giftcard-browser-category">
                    <option value=""><?php esc_html_e( 'All Gift Card Categories', 'wc-topup-fields' ); ?></option>
                    <?php foreach ( $giftcard_browser_category_options as $category_option ) : ?>
                        <option value="<?php echo esc_attr( $category_option['id'] ); ?>">
                            <?php
                            echo esc_html(
                                '' !== $category_option['name']
                                    ? $category_option['name'] . ' (' . $category_option['id'] . ')'
                                    : $category_option['id']
                            );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" class="button button-primary" id="wctf-giftcard-browser-submit">
                    <?php esc_html_e( 'Search', 'wc-topup-fields' ); ?>
                </button>
                <button type="button" class="button" id="wctf-giftcard-browser-reset">
                    <?php esc_html_e( 'Reset', 'wc-topup-fields' ); ?>
                </button>
            </div>

            <div class="notice notice-error inline" id="wctf-giftcard-browser-error" role="alert" hidden>
                <p id="wctf-giftcard-browser-error-message"></p>
            </div>

            <div class="notice notice-info inline" id="wctf-giftcard-browser-empty" hidden>
                <p><?php esc_html_e( 'No matching Gift Cards found.', 'wc-topup-fields' ); ?></p>
            </div>

            <table class="widefat fixed striped" id="wctf-giftcard-browser-results">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Category ID', 'wc-topup-fields' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Category Name', 'wc-topup-fields' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Card ID', 'wc-topup-fields' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Card Name', 'wc-topup-fields' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Price USD', 'wc-topup-fields' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Currency', 'wc-topup-fields' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Region', 'wc-topup-fields' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Stock', 'wc-topup-fields' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Min', 'wc-topup-fields' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Max', 'wc-topup-fields' ); ?></th>
                    </tr>
                </thead>
                <tbody id="wctf-giftcard-browser-rows"></tbody>
            </table>

            <div class="tablenav bottom" id="wctf-giftcard-browser-pagination">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <span id="wctf-giftcard-browser-total-results">0</span>
                        <?php esc_html_e( 'total results', 'wc-topup-fields' ); ?>
                    </span>
                    <button type="button" class="button" id="wctf-giftcard-browser-previous" disabled>
                        <?php esc_html_e( 'Previous', 'wc-topup-fields' ); ?>
                    </button>
                    <span class="paging-input">
                        <?php esc_html_e( 'Current page', 'wc-topup-fields' ); ?>
                        <span id="wctf-giftcard-browser-current-page">1</span>
                        /
                        <?php esc_html_e( 'Total pages', 'wc-topup-fields' ); ?>
                        <span id="wctf-giftcard-browser-total-pages">1</span>
                    </span>
                    <button type="button" class="button" id="wctf-giftcard-browser-next" disabled>
                        <?php esc_html_e( 'Next', 'wc-topup-fields' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </section>

</div>
