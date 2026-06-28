<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 获取全部配置
 */
function wctf_config()
{
    return [

        'api_url' => rtrim(
            wctf_get_option(
                'wctf_api_url',
                'https://api.fzr.cards'
            ),
            '/'
        ),

        'api_key' => wctf_get_option(
            'wctf_api_key'
        ),

        'api_secret' => wctf_get_option(
            'wctf_api_secret'
        ),

        'debug' => wctf_get_option(
            'wctf_debug',
            false
        ),

    ];
}