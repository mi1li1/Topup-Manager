<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 获取插件配置
 */
function wctf_get_option($key, $default = '')
{
    return get_option($key, $default);
}

/**
 * 更新插件配置
 */
function wctf_update_option($key, $value)
{
    return update_option($key, $value);
}

/**
 * 删除插件配置
 */
function wctf_delete_option($key)
{
    return delete_option($key);
}

/**
 * 获取插件版本
 */
function wctf_plugin_version()
{
    return '1.2.0';
}

/**
 * 是否开启 Debug
 */
function wctf_is_debug()
{
    return (bool) wctf_get_option('wctf_debug', false);
}
