<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action(
    'woocommerce_checkout_create_order_line_item',
    'wctf_save_order_item_meta',
    10,
    4
);

function wctf_save_order_item_meta(
    $item,
    $cart_item_key,
    $values,
    $order
) {

    $labels = [
        'player_id' => '玩家ID',
        'server'    => '服务器',
        'zone_id'   => '区服ID',
        'email'     => '邮箱',
        'nickname'  => '角色名',
    ];

    foreach ($labels as $key => $label) {

        if (!empty($values[$key])) {

            $item->add_meta_data(
                $label,
                $values[$key],
                true
            );

        }
    }
}