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

</div>
