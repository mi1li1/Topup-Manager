<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'woocommerce_product_options_general_product_data', 'wctf_add_fazercards_giftcard_binding_fields' );
add_action( 'woocommerce_process_product_meta_simple', 'wctf_save_fazercards_giftcard_binding', 20 );
add_action( 'woocommerce_process_product_meta', 'wctf_disable_fazercards_giftcard_auto_purchase_for_unsupported_product', 30 );
add_action( 'admin_enqueue_scripts', 'wctf_enqueue_fazercards_giftcard_product_binding_assets' );
add_action( 'wp_ajax_wctf_search_fazercards_giftcards_for_product', 'wctf_search_fazercards_giftcards_for_product' );

/**
 * Add the FazerCards Gift Card binding interface to simple products.
 */
function wctf_add_fazercards_giftcard_binding_fields() {
    global $post;

    if ( ! $post instanceof WP_Post ) {
        return;
    }

    $category_id  = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_category_id', true ) );
    $card_id      = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_card_id', true ) );
    $card_key     = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_offer_key', true ) );
    $card_name    = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_offer_name', true ) );
    $price_usd    = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_price_usd', true ) );
    $currency     = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_currency', true ) );
    $region       = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_region', true ) );
    $min_quantity = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_min_quantity', true ) );
    $max_quantity = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_max_quantity', true ) );
    $auto_purchase = 'yes' === (string) get_post_meta( $post->ID, '_wctf_fazer_giftcard_auto_purchase_enabled', true )
        ? 'yes'
        : 'no';
    $stock        = '';
    $category_name = '';
    $has_valid_binding = false;
    $categories   = get_option( 'wctf_fazercards_giftcard_categories', array() );
    $cards        = get_option( 'wctf_fazercards_giftcard_offers', array() );

    if ( '' === $card_key ) {
        $card_key = wctf_get_fazercards_giftcard_product_card_key( $category_id, $card_id );
    }

    if (
        is_array( $categories )
        && isset( $categories[ $category_id ] )
        && is_array( $categories[ $category_id ] )
    ) {
        $category_name = wctf_get_fazercards_giftcard_product_value( $categories[ $category_id ], 'name' );
    }

    if ( is_array( $cards ) ) {
        $cached_card = wctf_find_fazercards_cached_giftcard_for_product( $cards, $card_key );

        if ( ! empty( $cached_card ) ) {
            $cached_category_id = wctf_get_fazercards_giftcard_product_value( $cached_card, 'category_id' );
            $cached_card_id     = wctf_get_fazercards_giftcard_product_value( $cached_card, 'card_id' );
            $cached_key         = wctf_get_fazercards_giftcard_product_card_key( $cached_category_id, $cached_card_id );
            $cached_name        = wctf_get_fazercards_giftcard_product_value( $cached_card, 'name' );
            $has_valid_binding  = '' !== $card_id
                && $category_id === $cached_category_id
                && $card_id === $cached_card_id
                && $card_key === $cached_key
                && '' !== $cached_name;
            $stock              = wctf_get_fazercards_giftcard_product_value( $cached_card, 'stock' );
        }
    }

    ?>
    <div class="options_group show_if_simple wctf-product-type-panel" id="wctf-fazercards-giftcard-binding">
        <?php wp_nonce_field( 'wctf_save_fazercards_giftcard_binding', 'wctf_fazercards_giftcard_binding_nonce' ); ?>

        <p class="form-field">
            <label for="wctf-fazercards-giftcard-search">
                <?php esc_html_e( 'FazerCards Gift Card Binding', 'wc-topup-fields' ); ?>
            </label>
            <input
                type="search"
                class="short"
                id="wctf-fazercards-giftcard-search"
                placeholder="<?php echo esc_attr__( 'Search local Gift Cards', 'wc-topup-fields' ); ?>"
            >
            <button type="button" class="button" id="wctf-fazercards-giftcard-search-button">
                <?php esc_html_e( 'Search', 'wc-topup-fields' ); ?>
            </button>
            <button type="button" class="button" id="wctf-fazercards-giftcard-clear-button">
                <?php esc_html_e( 'Clear Gift Card binding', 'wc-topup-fields' ); ?>
            </button>
            <span class="description">
                <?php esc_html_e( 'Search and select a Gift Card SKU from the local FazerCards Gift Card cache.', 'wc-topup-fields' ); ?>
            </span>
        </p>

        <input
            type="hidden"
            id="_wctf_fazer_giftcard_category_id"
            name="_wctf_fazer_giftcard_category_id"
            value="<?php echo esc_attr( $category_id ); ?>"
        >
        <input
            type="hidden"
            id="_wctf_fazer_giftcard_card_id"
            name="_wctf_fazer_giftcard_card_id"
            value="<?php echo esc_attr( $card_id ); ?>"
        >
        <input
            type="hidden"
            id="_wctf_fazer_giftcard_offer_key"
            name="_wctf_fazer_giftcard_offer_key"
            value="<?php echo esc_attr( $card_key ); ?>"
        >

        <div id="wctf-fazercards-giftcard-status" role="status" aria-live="polite"></div>
        <ul id="wctf-fazercards-giftcard-results" hidden></ul>

        <div id="wctf-fazercards-giftcard-selected">
            <h4><?php esc_html_e( 'Selected Gift Card', 'wc-topup-fields' ); ?></h4>
            <p id="wctf-fazercards-giftcard-no-selection"<?php echo '' !== $card_id ? ' hidden' : ''; ?>>
                <?php esc_html_e( 'No Gift Card selected.', 'wc-topup-fields' ); ?>
            </p>
            <dl id="wctf-fazercards-giftcard-selection-details"<?php echo '' === $card_id ? ' hidden' : ''; ?>>
                <dt><?php esc_html_e( 'Category ID', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-category-id"><?php echo esc_html( $category_id ); ?></dd>
                <dt><?php esc_html_e( 'Category Name', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-category-name"><?php echo esc_html( $category_name ); ?></dd>
                <dt><?php esc_html_e( 'Card ID', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-card-id"><?php echo esc_html( $card_id ); ?></dd>
                <dt><?php esc_html_e( 'Card Name', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-card-name"><?php echo esc_html( $card_name ); ?></dd>
                <dt><?php esc_html_e( 'Price USD', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-price-usd"><?php echo esc_html( $price_usd ); ?></dd>
                <dt><?php esc_html_e( 'Currency', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-currency"><?php echo esc_html( $currency ); ?></dd>
                <dt><?php esc_html_e( 'Region', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-region"><?php echo esc_html( $region ); ?></dd>
                <dt><?php esc_html_e( 'Stock', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-stock"><?php echo esc_html( $stock ); ?></dd>
                <dt><?php esc_html_e( 'Minimum Quantity', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-min-quantity"><?php echo esc_html( $min_quantity ); ?></dd>
                <dt><?php esc_html_e( 'Maximum Quantity', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazercards-giftcard-selected-max-quantity"><?php echo esc_html( $max_quantity ); ?></dd>
            </dl>
        </div>

        <p class="form-field" id="wctf-fazer-giftcard-auto-purchase-field">
            <label for="_wctf_fazer_giftcard_auto_purchase_enabled">
                <?php esc_html_e( 'Enable Automatic Gift Card Purchase', 'wc-topup-fields' ); ?>
            </label>
            <input
                type="hidden"
                name="_wctf_fazer_giftcard_auto_purchase_enabled"
                value="no"
            >
            <input
                type="checkbox"
                class="checkbox"
                id="_wctf_fazer_giftcard_auto_purchase_enabled"
                name="_wctf_fazer_giftcard_auto_purchase_enabled"
                value="yes"
                <?php checked( 'yes', $auto_purchase ); ?>
                <?php disabled( ! $has_valid_binding ); ?>
            >
            <span
                class="description"
                id="wctf-fazer-giftcard-auto-purchase-binding-required"
                <?php echo $has_valid_binding ? ' hidden' : ''; ?>
            >
                <?php esc_html_e( 'Select a valid FazerCards Gift Card before enabling automatic purchase.', 'wc-topup-fields' ); ?>
            </span>
            <span class="description">
                <strong><?php esc_html_e( 'Warning: this may spend real FazerCards balance and also requires the global Automatic Gift Card Purchase setting.', 'wc-topup-fields' ); ?></strong>
            </span>
        </p>

        <p class="form-field">
            <span class="description">
                <?php esc_html_e( 'Warning: saving a Gift Card binding makes this product a FazerCards Gift Card product, not a Service Top-up product.', 'wc-topup-fields' ); ?>
            </span>
        </p>
    </div>
    <?php
}

/**
 * Save a locally validated FazerCards Gift Card binding for a simple product.
 *
 * @param int $post_id Product post ID.
 */
function wctf_save_fazercards_giftcard_binding( $post_id ) {
    if (
        ! isset( $_POST['wctf_fazercards_giftcard_binding_nonce'] )
        || ! is_string( $_POST['wctf_fazercards_giftcard_binding_nonce'] )
    ) {
        return;
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['wctf_fazercards_giftcard_binding_nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'wctf_save_fazercards_giftcard_binding' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $product = wc_get_product( $post_id );

    if ( ! $product || ! $product->is_type( 'simple' ) ) {
        update_post_meta( $post_id, '_wctf_fazer_giftcard_auto_purchase_enabled', 'no' );
        return;
    }

    $topup_type = wctf_get_submitted_topup_type_for_fazercards_giftcard_binding();

    update_post_meta( $post_id, '_wctf_fazer_giftcard_auto_purchase_enabled', 'no' );

    if ( 'game' === $topup_type || 'account' === $topup_type ) {
        wctf_clear_fazercards_giftcard_product_binding( $post_id );
        return;
    }

    if ( 'giftcard' !== $topup_type ) {
        return;
    }

    $category_id = isset( $_POST['_wctf_fazer_giftcard_category_id'] ) && is_string( $_POST['_wctf_fazer_giftcard_category_id'] )
        ? sanitize_text_field( wp_unslash( $_POST['_wctf_fazer_giftcard_category_id'] ) )
        : '';
    $card_id = isset( $_POST['_wctf_fazer_giftcard_card_id'] ) && is_string( $_POST['_wctf_fazer_giftcard_card_id'] )
        ? sanitize_text_field( wp_unslash( $_POST['_wctf_fazer_giftcard_card_id'] ) )
        : '';
    $card_key = isset( $_POST['_wctf_fazer_giftcard_offer_key'] ) && is_string( $_POST['_wctf_fazer_giftcard_offer_key'] )
        ? sanitize_text_field( wp_unslash( $_POST['_wctf_fazer_giftcard_offer_key'] ) )
        : '';

    if ( '' === $category_id && '' === $card_id && '' === $card_key ) {
        wctf_clear_fazercards_giftcard_product_binding( $post_id );
        return;
    }

    $expected_key = wctf_get_fazercards_giftcard_product_card_key( $category_id, $card_id );

    if ( '' === $expected_key || $expected_key !== $card_key ) {
        return;
    }

    $cards = get_option( 'wctf_fazercards_giftcard_offers', array() );

    if ( ! is_array( $cards ) ) {
        return;
    }

    $card = wctf_find_fazercards_cached_giftcard_for_product( $cards, $card_key );

    if (
        empty( $card )
        || ! isset( $card['category_id'], $card['card_id'], $card['name'] )
        || ! is_scalar( $card['category_id'] )
        || ! is_scalar( $card['card_id'] )
        || ! is_scalar( $card['name'] )
    ) {
        return;
    }

    $cached_category_id = wctf_get_fazercards_giftcard_product_value( $card, 'category_id' );
    $cached_card_id     = wctf_get_fazercards_giftcard_product_value( $card, 'card_id' );
    $cached_card_key    = wctf_get_fazercards_giftcard_product_card_key( $cached_category_id, $cached_card_id );
    $card_name          = wctf_get_fazercards_giftcard_product_value( $card, 'name' );

    if (
        $category_id !== $cached_category_id
        || $card_id !== $cached_card_id
        || $card_key !== $cached_card_key
        || '' === $card_name
    ) {
        return;
    }

    update_post_meta( $post_id, '_wctf_fazer_product_kind', 'giftcard' );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_category_id', $cached_category_id );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_card_id', $cached_card_id );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_offer_key', $cached_card_key );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_offer_name', $card_name );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_price_usd', wctf_get_fazercards_giftcard_product_value( $card, 'price_usd' ) );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_currency', wctf_get_fazercards_giftcard_product_value( $card, 'currency' ) );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_region', wctf_get_fazercards_giftcard_product_value( $card, 'region' ) );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_min_quantity', wctf_get_fazercards_giftcard_product_value( $card, 'min_order_quantity' ) );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_max_quantity', wctf_get_fazercards_giftcard_product_value( $card, 'max_order_quantity' ) );
    update_post_meta(
        $post_id,
        '_wctf_fazer_giftcard_auto_purchase_enabled',
        isset( $_POST['_wctf_fazer_giftcard_auto_purchase_enabled'] )
            && is_scalar( $_POST['_wctf_fazer_giftcard_auto_purchase_enabled'] )
            && 'yes' === sanitize_text_field( wp_unslash( $_POST['_wctf_fazer_giftcard_auto_purchase_enabled'] ) )
                ? 'yes'
                : 'no'
    );

    wctf_clear_fazercards_topup_product_binding_for_giftcard( $post_id );
}

/**
 * Return the submitted custom top-up type for Gift Card binding save decisions.
 *
 * @return string
 */
function wctf_get_submitted_topup_type_for_fazercards_giftcard_binding() {
    if ( ! isset( $_POST['_topup_type'] ) || ! is_scalar( $_POST['_topup_type'] ) ) {
        return '';
    }

    $topup_type = sanitize_key( wp_unslash( $_POST['_topup_type'] ) );

    return in_array( $topup_type, array( 'giftcard', 'game', 'account' ), true )
        ? $topup_type
        : '';
}

/**
 * Clear Gift Card-only product meta.
 *
 * @param int $post_id Product post ID.
 */
function wctf_clear_fazercards_giftcard_product_binding( $post_id ) {
    delete_post_meta( $post_id, '_wctf_fazer_giftcard_category_id' );
    delete_post_meta( $post_id, '_wctf_fazer_giftcard_card_id' );
    delete_post_meta( $post_id, '_wctf_fazer_giftcard_offer_key' );
    delete_post_meta( $post_id, '_wctf_fazer_giftcard_offer_name' );
    delete_post_meta( $post_id, '_wctf_fazer_giftcard_price_usd' );
    delete_post_meta( $post_id, '_wctf_fazer_giftcard_currency' );
    delete_post_meta( $post_id, '_wctf_fazer_giftcard_region' );
    delete_post_meta( $post_id, '_wctf_fazer_giftcard_min_quantity' );
    delete_post_meta( $post_id, '_wctf_fazer_giftcard_max_quantity' );
    update_post_meta( $post_id, '_wctf_fazer_giftcard_auto_purchase_enabled', 'no' );

    if ( 'giftcard' === get_post_meta( $post_id, '_wctf_fazer_product_kind', true ) ) {
        delete_post_meta( $post_id, '_wctf_fazer_product_kind' );
    }
}

/**
 * Force Gift Card auto purchase off when the saved product becomes unsupported.
 *
 * @param int $post_id Product post ID.
 * @return void
 */
function wctf_disable_fazercards_giftcard_auto_purchase_for_unsupported_product( $post_id ) {
    if (
        ! isset( $_POST['wctf_fazercards_giftcard_binding_nonce'] )
        || ! is_scalar( $_POST['wctf_fazercards_giftcard_binding_nonce'] )
        || ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['wctf_fazercards_giftcard_binding_nonce'] ) ),
            'wctf_save_fazercards_giftcard_binding'
        )
        || ! current_user_can( 'edit_post', $post_id )
    ) {
        return;
    }

    $product    = wc_get_product( $post_id );
    $topup_type = wctf_get_submitted_topup_type_for_fazercards_giftcard_binding();

    if ( ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) || 'giftcard' !== $topup_type ) {
        update_post_meta( $post_id, '_wctf_fazer_giftcard_auto_purchase_enabled', 'no' );
    }
}

/**
 * Clear Service Top-up binding meta after an explicit Gift Card binding is saved.
 *
 * @param int $post_id Product post ID.
 */
function wctf_clear_fazercards_topup_product_binding_for_giftcard( $post_id ) {
    if ( function_exists( 'wctf_clear_fazercards_topup_product_binding' ) ) {
        wctf_clear_fazercards_topup_product_binding( $post_id, false );
        return;
    }

    delete_post_meta( $post_id, '_fazer_category_id' );
    delete_post_meta( $post_id, '_fazer_offer_id' );
    delete_post_meta( $post_id, '_fazer_offer_name' );
    delete_post_meta( $post_id, '_fazer_price_usd' );
    delete_post_meta( $post_id, '_wctf_fazer_offer_key' );
    delete_post_meta( $post_id, '_wctf_fazer_auto_submit_enabled' );
}

/**
 * Find a cached Gift Card by exact category/card composite key.
 *
 * @param array  $cards    Locally cached Gift Cards.
 * @param string $card_key Composite card key.
 * @return array
 */
function wctf_find_fazercards_cached_giftcard_for_product( $cards, $card_key ) {
    $key_parts = explode( '::', $card_key, 2 );

    if ( 2 !== count( $key_parts ) ) {
        return array();
    }

    $category_id = sanitize_text_field( $key_parts[0] );
    $card_id     = sanitize_text_field( $key_parts[1] );
    $card_key    = wctf_get_fazercards_giftcard_product_card_key( $category_id, $card_id );

    if ( '' === $card_key ) {
        return array();
    }

    $candidates = isset( $cards[ $card_key ] )
        ? array( $cards[ $card_key ] )
        : $cards;

    foreach ( $candidates as $card ) {
        if (
            ! is_array( $card )
            || ! isset( $card['category_id'], $card['card_id'] )
            || ! is_scalar( $card['category_id'] )
            || ! is_scalar( $card['card_id'] )
        ) {
            continue;
        }

        $cached_category_id = wctf_get_fazercards_giftcard_product_value( $card, 'category_id' );
        $cached_card_id     = wctf_get_fazercards_giftcard_product_value( $card, 'card_id' );

        if ( $category_id === $cached_category_id && $card_id === $cached_card_id ) {
            return $card;
        }
    }

    return array();
}

/**
 * Build the local Gift Card product card key.
 *
 * @param mixed $category_id Gift Card category ID.
 * @param mixed $card_id     Gift Card card ID.
 * @return string
 */
function wctf_get_fazercards_giftcard_product_card_key( $category_id, $card_id ) {
    if ( function_exists( 'wctf_get_fazercards_giftcard_card_key' ) ) {
        return wctf_get_fazercards_giftcard_card_key( $category_id, $card_id );
    }

    $category_id = is_scalar( $category_id ) ? sanitize_text_field( (string) $category_id ) : '';
    $card_id     = is_scalar( $card_id ) ? sanitize_text_field( (string) $card_id ) : '';

    if ( '' === $category_id || '' === $card_id ) {
        return '';
    }

    return $category_id . '::' . $card_id;
}

/**
 * Safely read a scalar Gift Card catalog value.
 *
 * @param array  $record Source record.
 * @param string $key    Source key.
 * @return string
 */
function wctf_get_fazercards_giftcard_product_value( $record, $key ) {
    if ( function_exists( 'wctf_normalize_fazercards_giftcard_catalog_value' ) ) {
        return wctf_normalize_fazercards_giftcard_catalog_value( $record, $key );
    }

    if ( ! is_array( $record ) || ! isset( $record[ $key ] ) || ! is_scalar( $record[ $key ] ) ) {
        return '';
    }

    return sanitize_text_field( (string) $record[ $key ] );
}

/**
 * Load the local Gift Card binding script on WooCommerce product edit screens.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function wctf_enqueue_fazercards_giftcard_product_binding_assets( $hook_suffix ) {
    if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    $screen = get_current_screen();

    if ( ! $screen || 'product' !== $screen->post_type ) {
        return;
    }

    $script_path    = __DIR__ . '/js/product-giftcard-binding.js';
    $script_version = file_exists( $script_path ) ? (string) filemtime( $script_path ) : wctf_plugin_version();

    wp_enqueue_script(
        'wctf-product-giftcard-binding',
        plugins_url( 'js/product-giftcard-binding.js', __FILE__ ),
        array(),
        $script_version,
        true
    );

    wp_localize_script(
        'wctf-product-giftcard-binding',
        'wctfProductGiftCardBinding',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wctf_search_fazercards_giftcards_for_product' ),
            'action'  => 'wctf_search_fazercards_giftcards_for_product',
            'messages' => array(
                'loading'       => __( 'Searching local Gift Cards...', 'wc-topup-fields' ),
                'empty'         => __( 'No matching Gift Cards found.', 'wc-topup-fields' ),
                'error'         => __( 'The local Gift Card search could not be completed.', 'wc-topup-fields' ),
                'selected'      => __( 'Gift Card selected. Save the product to apply the binding.', 'wc-topup-fields' ),
                'cleared'       => __( 'Gift Card binding cleared. Save the product to apply this change.', 'wc-topup-fields' ),
                'selectCard'    => __( 'Select Gift Card', 'wc-topup-fields' ),
                'missingConfig' => __( 'Gift Card search configuration is unavailable.', 'wc-topup-fields' ),
            ),
        )
    );
}

/**
 * Search locally cached Gift Cards for the product editor.
 */
function wctf_search_fazercards_giftcards_for_product() {
    if ( false === check_ajax_referer( 'wctf_search_fazercards_giftcards_for_product', 'nonce', false ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Security check failed. Refresh the page and try again.', 'wc-topup-fields' ),
            ),
            403
        );
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'You are not allowed to search Gift Cards.', 'wc-topup-fields' ),
            ),
            403
        );
    }

    $search = isset( $_POST['search'] ) && is_scalar( $_POST['search'] )
        ? sanitize_text_field( wp_unslash( $_POST['search'] ) )
        : '';
    $category_filter = isset( $_POST['category_id'] ) && is_scalar( $_POST['category_id'] )
        ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) )
        : '';
    $cards      = get_option( 'wctf_fazercards_giftcard_offers', array() );
    $categories = get_option( 'wctf_fazercards_giftcard_categories', array() );
    $matches    = array();

    if ( ! is_array( $cards ) ) {
        $cards = array();
    }

    if ( ! is_array( $categories ) ) {
        $categories = array();
    }

    foreach ( $cards as $card ) {
        if ( ! is_array( $card ) ) {
            continue;
        }

        $category_id = wctf_get_fazercards_giftcard_product_value( $card, 'category_id' );
        $card_id     = wctf_get_fazercards_giftcard_product_value( $card, 'card_id' );
        $card_key    = wctf_get_fazercards_giftcard_product_card_key( $category_id, $card_id );

        if ( '' === $card_key || ( '' !== $category_filter && $category_filter !== $category_id ) ) {
            continue;
        }

        $category_name = '';

        if ( isset( $categories[ $category_id ] ) && is_array( $categories[ $category_id ] ) ) {
            $category_name = wctf_get_fazercards_giftcard_product_value( $categories[ $category_id ], 'name' );
        }

        $item = array(
            'card_key'           => $card_key,
            'category_id'        => $category_id,
            'category_name'      => $category_name,
            'card_id'            => $card_id,
            'name'               => wctf_get_fazercards_giftcard_product_value( $card, 'name' ),
            'price_usd'          => wctf_get_fazercards_giftcard_product_value( $card, 'price_usd' ),
            'currency'           => wctf_get_fazercards_giftcard_product_value( $card, 'currency' ),
            'region'             => wctf_get_fazercards_giftcard_product_value( $card, 'region' ),
            'stock'              => wctf_get_fazercards_giftcard_product_value( $card, 'stock' ),
            'min_order_quantity' => wctf_get_fazercards_giftcard_product_value( $card, 'min_order_quantity' ),
            'max_order_quantity' => wctf_get_fazercards_giftcard_product_value( $card, 'max_order_quantity' ),
        );

        if ( '' !== $search ) {
            $haystacks = array(
                $item['card_id'],
                $item['name'],
                $item['category_id'],
                $item['category_name'],
            );
            $matched   = false;

            foreach ( $haystacks as $haystack ) {
                if ( false !== stripos( (string) $haystack, $search ) ) {
                    $matched = true;
                    break;
                }
            }

            if ( ! $matched ) {
                continue;
            }
        }

        $matches[] = $item;

        if ( 20 <= count( $matches ) ) {
            break;
        }
    }

    wp_send_json_success(
        array(
            'items' => $matches,
        )
    );
}
