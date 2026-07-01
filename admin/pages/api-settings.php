<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册设置
 */

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

</div>
