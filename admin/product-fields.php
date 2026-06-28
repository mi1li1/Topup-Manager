<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 添加商品设置字段
 */
add_action(
    'woocommerce_product_options_general_product_data',
    'wctf_add_product_fields'
);

function wctf_add_product_fields()
{
    echo '<div class="options_group">';

    // 商品类型
    woocommerce_wp_select(array(
        'id'          => '_topup_type',
        'label'       => '商品类型',
        'description' => '选择该商品的类型',
        'desc_tip'    => true,
        'options'     => array(
            ''         => '请选择',
            'giftcard' => '礼品卡',
            'game'      => '游戏充值',
            'account'   => '账号充值',
        ),
    ));

    // 充值字段
    woocommerce_wp_text_input(array(
        'id'          => '_topup_fields',
        'label'       => '充值字段',
        'description' => '多个字段用英文逗号分隔，例如：player_id,server',
        'desc_tip'    => true,
        'placeholder' => 'player_id,server',
    ));

    echo '</div>';
}



/**
 * 保存商品设置
 */
add_action(
    'woocommerce_process_product_meta',
    'wctf_save_product_fields'
);

function wctf_save_product_fields($post_id)
{
    // 商品类型
    if (isset($_POST['_topup_type'])) {

        update_post_meta(
            $post_id,
            '_topup_type',
            sanitize_text_field($_POST['_topup_type'])
        );
    }

    // 充值字段
    if (isset($_POST['_topup_fields'])) {

        update_post_meta(
            $post_id,
            '_topup_fields',
            sanitize_text_field($_POST['_topup_fields'])
        );
    }
}



add_action(
    'woocommerce_product_options_general_product_data',
    function () {

        global $post;

        echo '<div class="options_group">';

        echo '<p>';
        echo '当前类型：';
        echo esc_html(
            get_post_meta(
                $post->ID,
                '_topup_type',
                true
            )
        );
        echo '</p>';

        echo '<p>';
        echo '当前字段：';
        echo esc_html(
            get_post_meta(
                $post->ID,
                '_topup_fields',
                true
            )
        );
        echo '</p>';

        echo '</div>';
    }
);