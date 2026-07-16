<?php
/*
Plugin Name: WC Topup Fields
Plugin URI: https://yourdomain.com
Description: WooCommerce 游戏充值输入框插件
Version: 1.2.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit;
}

define('WCTF_PATH', plugin_dir_path(__FILE__));

$includes = [

    // Core
    'includes/helper.php',
    'includes/config.php',
    'includes/logger.php',
    'includes/request.php',
    'includes/giftcard-crypto.php',
    'includes/giftcard-secret-storage.php',
    'providers/FazerCards/Provider.php',
    'providers/FazerCards/GiftCardsProvider.php',

    // Admin
    'admin/menu.php',
    'admin/settings.php',
    'admin/giftcard-settings.php',
    'admin/product-fields.php',
    'admin/fazer-fields.php',
    'admin/giftcard-product-fields.php',

    // Frontend
    'frontend/form-fields.php',

    // WooCommerce
    'includes/cart.php',
    'includes/order.php',
    'includes/giftcard-order.php',
    'includes/giftcard-reveal.php',
    'includes/giftcard-delivery.php',
    'includes/thankyou-page.php',

];

foreach ($includes as $file) {

    $path = WCTF_PATH . $file;

    if (file_exists($path)) {
        require_once $path;
    }
}



