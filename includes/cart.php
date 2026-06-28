<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 保存充值字段到购物车
 */
add_filter(
    'woocommerce_add_cart_item_data',
    'wctf_save_cart_item_data',
    10,
    2
);

function wctf_save_cart_item_data(
    $cart_item_data,
    $product_id
) {

    $fields = get_post_meta(
        $product_id,
        '_topup_fields',
        true
    );

    if (empty($fields)) {
        return $cart_item_data;
    }

    $fields = array_map(
        'trim',
        explode(',', $fields)
    );

    foreach ($fields as $field) {

        if (!empty($_POST[$field])) {

            $cart_item_data[$field] =
                sanitize_text_field(
                    $_POST[$field]
                );
        }
    }

    return $cart_item_data;
}



/**
 * 购物车显示充值信息
 */
add_filter(
    'woocommerce_get_item_data',
    'wctf_display_cart_item_data',
    10,
    2
);

function wctf_display_cart_item_data(
    $item_data,
    $cart_item
) {

    $labels = [
        'player_id' => '玩家ID',
        'server'    => '服务器',
        'zone_id'   => '区服ID',
        'email'     => '邮箱',
        'nickname'  => '角色名',
    ];

    foreach ($labels as $key => $label) {

        if (!empty($cart_item[$key])) {

            $item_data[] = [
                'key'   => $label,
                'value' => $cart_item[$key]
            ];
        }
    }

    return $item_data;
}