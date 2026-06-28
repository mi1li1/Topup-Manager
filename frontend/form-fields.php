<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 商品页显示充值输入框
 */
add_action(
    'woocommerce_before_add_to_cart_button',
    'wctf_show_topup_fields'
);

function wctf_show_topup_fields()
{
    global $product;

    if (!$product) {
        return;
    }

    $product_id = $product->get_id();

    // 获取商品类型
    $type = get_post_meta(
        $product_id,
        '_topup_type',
        true
    );

    // 只有游戏充值和账号充值显示输入框
    if (!in_array($type, ['game', 'account'])) {
        return;
    }

    // 获取字段配置
    $fields = get_post_meta(
        $product_id,
        '_topup_fields',
        true
    );

    if (empty($fields)) {
        return;
    }

    $fields = array_map(
        'trim',
        explode(',', $fields)
    );

    echo '<div class="wctf-fields">';

    foreach ($fields as $field) {

        echo '<p class="form-row form-row-wide">';

        switch ($field) {

            case 'player_id':
                $label = '玩家ID';
                $type_input = 'text';
                break;

            case 'server':
                $label = '服务器';
                $type_input = 'text';
                break;

            case 'zone_id':
                $label = '区服ID';
                $type_input = 'text';
                break;

            case 'email':
                $label = '邮箱';
                $type_input = 'email';
                break;

            case 'nickname':
                $label = '角色名';
                $type_input = 'text';
                break;

            default:
                $label = ucfirst($field);
                $type_input = 'text';
        }

        echo '<label>';
        echo esc_html($label);
        echo ' <span class="required">*</span>';
        echo '</label>';

        echo '<input
                type="' . esc_attr($type_input) . '"
                name="' . esc_attr($field) . '"
                required
                class="input-text"
              >';

        echo '</p>';
    }

    echo '</div>';
}