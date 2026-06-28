<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 添加 FazerCards 商品字段
 */
add_action(
    'woocommerce_product_options_general_product_data',
    'wctf_add_fazer_fields'
);

function wctf_add_fazer_fields()
{
    echo '<div class="options_group">';

    woocommerce_wp_text_input(array(
        'id'          => '_fazer_category_id',
        'label'       => 'Fazer Category ID',
        'placeholder' => '例如：cat_pubgm_1',
        'desc_tip'    => true,
        'description' => 'FazerCards 分类 ID',
    ));

    woocommerce_wp_text_input(array(
        'id'          => '_fazer_offer_id',
        'label'       => 'Fazer Offer ID',
        'placeholder' => '例如：offer_60uc',
        'desc_tip'    => true,
        'description' => 'FazerCards 套餐 ID',
    ));

    echo '</div>';
}

/**
 * 保存 FazerCards 字段
 */
add_action(
    'woocommerce_process_product_meta',
    'wctf_save_fazer_fields'
);

function wctf_save_fazer_fields($post_id)
{
    if (isset($_POST['_fazer_category_id'])) {

        update_post_meta(
            $post_id,
            '_fazer_category_id',
            sanitize_text_field($_POST['_fazer_category_id'])
        );
    }

    if (isset($_POST['_fazer_offer_id'])) {

        update_post_meta(
            $post_id,
            '_fazer_offer_id',
            sanitize_text_field($_POST['_fazer_offer_id'])
        );
    }
}
