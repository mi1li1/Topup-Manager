<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册插件设置
 */
add_action('admin_init', 'wctf_register_settings');

function wctf_register_settings()
{

    /*
     * FazerCards API
     */

    register_setting(
        'wctf_api_settings',
        'wctf_api_url'
    );

    register_setting(
        'wctf_api_settings',
        'wctf_api_key'
    );

    register_setting(
        'wctf_api_settings',
        'wctf_api_secret'
    );

}