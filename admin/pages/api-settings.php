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

</div>