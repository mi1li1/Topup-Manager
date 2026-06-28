<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册后台菜单
 */
add_action('admin_menu', 'wctf_admin_menu');

function wctf_admin_menu()
{
    // 主菜单
    add_menu_page(
        'Topup Manager',
        'Topup Manager',
        'manage_woocommerce',
        'wctf-dashboard',
        'wctf_dashboard_page',
        'dashicons-games',
        56
    );

    // Dashboard
    add_submenu_page(
        'wctf-dashboard',
        'Dashboard',
        'Dashboard',
        'manage_woocommerce',
        'wctf-dashboard',
        'wctf_dashboard_page'
    );

    // API 设置
    add_submenu_page(
        'wctf-dashboard',
        'API 设置',
        'API 设置',
        'manage_woocommerce',
        'wctf-api',
        'wctf_api_page'
    );

    // 商品同步
    add_submenu_page(
        'wctf-dashboard',
        '商品同步',
        '商品同步',
        'manage_woocommerce',
        'wctf-sync',
        'wctf_sync_page'
    );

    // 日志
    add_submenu_page(
        'wctf-dashboard',
        '日志',
        '日志',
        'manage_woocommerce',
        'wctf-logs',
        'wctf_logs_page'
    );

    // 工具
    add_submenu_page(
        'wctf-dashboard',
        '工具',
        '工具',
        'manage_woocommerce',
        'wctf-tools',
        'wctf_tools_page'
    );
}

/**
 * 安全加载页面
 */
function wctf_load_admin_page($file)
{
    $path = WCTF_PATH . 'admin/pages/' . $file;

    if (file_exists($path)) {
        require_once $path;
    } else {
        echo '<div class="notice notice-error"><p>';
        echo '页面不存在：' . esc_html($file);
        echo '</p></div>';
    }
}

/**
 * Dashboard
 */
function wctf_dashboard_page()
{
    wctf_load_admin_page('dashboard.php');
}

/**
 * API 设置
 */
function wctf_api_page()
{
    wctf_load_admin_page('api-settings.php');
}

/**
 * 商品同步
 */
function wctf_sync_page()
{
    wctf_load_admin_page('sync.php');
}

/**
 * 日志
 */
function wctf_logs_page()
{
    wctf_load_admin_page('logs.php');
}

/**
 * 工具
 */
function wctf_tools_page()
{
    wctf_load_admin_page('tools.php');
}