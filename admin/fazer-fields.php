<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'woocommerce_product_options_general_product_data', 'wctf_add_fazer_offer_binding_fields' );
add_action( 'woocommerce_process_product_meta_simple', 'wctf_save_fazer_offer_binding' );
add_action( 'admin_enqueue_scripts', 'wctf_enqueue_product_offer_binding_assets' );

/**
 * Add the FazerCards offer binding interface to simple products.
 */
function wctf_add_fazer_offer_binding_fields() {
    global $post;

    if ( ! $post instanceof WP_Post ) {
        return;
    }

    $offer_id      = sanitize_text_field( (string) get_post_meta( $post->ID, '_fazer_offer_id', true ) );
    $category_id   = sanitize_text_field( (string) get_post_meta( $post->ID, '_fazer_category_id', true ) );
    $offer_name    = sanitize_text_field( (string) get_post_meta( $post->ID, '_fazer_offer_name', true ) );
    $price_usd     = sanitize_text_field( (string) get_post_meta( $post->ID, '_fazer_price_usd', true ) );
    $offer_key     = sanitize_text_field( (string) get_post_meta( $post->ID, '_wctf_fazer_offer_key', true ) );
    $auto_submit   = 'yes' === sanitize_key( (string) get_post_meta( $post->ID, '_wctf_fazer_auto_submit_enabled', true ) )
        ? 'yes'
        : 'no';
    $category_name = '';
    $categories    = get_option( 'wctf_fazercards_categories', array() );
    $topup_fields  = get_option( 'wctf_fazercards_topup_fields', array() );
    $fields_synced = is_array( $topup_fields ) && array_key_exists( $category_id, $topup_fields );
    $field_schema  = $fields_synced
        ? wctf_normalize_fazercards_topup_field_schema( $topup_fields[ $category_id ] )
        : array();

    if ( '' === $offer_key ) {
        $offer_key = wctf_get_fazercards_offer_key( $category_id, $offer_id );
    }

    if (
        is_array( $categories )
        && isset( $categories[ $category_id ]['name'] )
        && is_scalar( $categories[ $category_id ]['name'] )
    ) {
        $category_name = sanitize_text_field( (string) $categories[ $category_id ]['name'] );
    }

    ?>
    <div class="options_group show_if_simple" id="wctf-fazer-offer-binding">
        <?php wp_nonce_field( 'wctf_save_product_offer_binding', 'wctf_product_offer_binding_nonce' ); ?>

        <p class="form-field">
            <label for="wctf-fazer-offer-search">
                <?php esc_html_e( 'FazerCards Offer', 'wc-topup-fields' ); ?>
            </label>
            <input
                type="search"
                class="short"
                id="wctf-fazer-offer-search"
                placeholder="<?php echo esc_attr__( 'Search local offers', 'wc-topup-fields' ); ?>"
            >
            <button type="button" class="button" id="wctf-fazer-offer-search-button">
                <?php esc_html_e( 'Search', 'wc-topup-fields' ); ?>
            </button>
            <button type="button" class="button" id="wctf-fazer-offer-clear-button">
                <?php esc_html_e( 'Clear binding', 'wc-topup-fields' ); ?>
            </button>
            <span class="description">
                <?php esc_html_e( 'Search and select an offer from the local FazerCards cache.', 'wc-topup-fields' ); ?>
            </span>
        </p>

        <input
            type="hidden"
            id="_fazer_offer_id"
            name="_fazer_offer_id"
            value="<?php echo esc_attr( $offer_id ); ?>"
        >
        <input
            type="hidden"
            id="_wctf_fazer_offer_key"
            name="_wctf_fazer_offer_key"
            value="<?php echo esc_attr( $offer_key ); ?>"
        >

        <div id="wctf-fazer-offer-status" role="status" aria-live="polite"></div>
        <ul id="wctf-fazer-offer-results" hidden></ul>

        <div id="wctf-fazer-selected-offer">
            <h4><?php esc_html_e( 'Selected Offer', 'wc-topup-fields' ); ?></h4>
            <p id="wctf-fazer-no-selection"<?php echo '' !== $offer_id ? ' hidden' : ''; ?>>
                <?php esc_html_e( 'No offer selected.', 'wc-topup-fields' ); ?>
            </p>
            <dl id="wctf-fazer-selection-details"<?php echo '' === $offer_id ? ' hidden' : ''; ?>>
                <dt><?php esc_html_e( 'Offer ID', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazer-selected-offer-id"><?php echo esc_html( $offer_id ); ?></dd>
                <dt><?php esc_html_e( 'Category ID', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazer-selected-category-id"><?php echo esc_html( $category_id ); ?></dd>
                <dt><?php esc_html_e( 'Category Name', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazer-selected-category-name"><?php echo esc_html( $category_name ); ?></dd>
                <dt><?php esc_html_e( 'Offer Name', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazer-selected-offer-name"><?php echo esc_html( $offer_name ); ?></dd>
                <dt><?php esc_html_e( 'Price USD', 'wc-topup-fields' ); ?></dt>
                <dd id="wctf-fazer-selected-price-usd"><?php echo esc_html( $price_usd ); ?></dd>
            </dl>
        </div>

        <p class="form-field">
            <label for="wctf-fazer-auto-submit-enabled">
                <?php esc_html_e( 'Automatic Submission', 'wc-topup-fields' ); ?>
            </label>
            <input
                type="hidden"
                name="_wctf_fazer_auto_submit_enabled"
                value="no"
            >
            <input
                type="checkbox"
                id="wctf-fazer-auto-submit-enabled"
                name="_wctf_fazer_auto_submit_enabled"
                value="yes"
                <?php checked( 'yes', $auto_submit ); ?>
            >
            <span>
                <?php esc_html_e( 'Enable automatic FazerCards submission for this product.', 'wc-topup-fields' ); ?>
            </span>
            <span class="description">
                <?php esc_html_e( 'This product will be automatically submitted to FazerCards after paid orders enter Processing or Completed, only when global auto-submit is also enabled.', 'wc-topup-fields' ); ?>
            </span>
        </p>

        <div id="wctf-fazer-selected-fields">
            <h4><?php esc_html_e( 'Required Customer Fields', 'wc-topup-fields' ); ?></h4>
            <p
                id="wctf-fazer-fields-message"
                <?php echo $fields_synced && ! empty( $field_schema ) ? 'hidden' : ''; ?>
            >
                <?php
                if ( ! $fields_synced ) {
                    esc_html_e( 'Field schema has not been synchronized for this category. Manual product fields will be preserved.', 'wc-topup-fields' );
                } elseif ( empty( $field_schema ) ) {
                    esc_html_e( 'This category does not require customer fields.', 'wc-topup-fields' );
                }
                ?>
            </p>
            <ul id="wctf-fazer-fields-list">
                <?php foreach ( $field_schema as $field ) : ?>
                    <li>
                        <?php
                        $options_json = wp_json_encode(
                            $field['options'],
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
                        echo esc_html(
                            sprintf(
                                /* translators: 1: field key, 2: label, 3: type, 4: required state. */
                                __( 'Key: %1$s | Label: %2$s | Type: %3$s | Required: %4$s', 'wc-topup-fields' ),
                                $field['key'],
                                $field['label'],
                                $field['type'],
                                $field['required'] ? __( 'Yes', 'wc-topup-fields' ) : __( 'No', 'wc-topup-fields' )
                            )
                        );

                        if ( ! empty( $field['options'] ) && false !== $options_json ) {
                            echo ' | ';
                            echo esc_html__( 'Options:', 'wc-topup-fields' ) . ' ';
                            echo esc_html( $options_json );
                        }
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Save a locally validated FazerCards offer binding for a simple product.
 *
 * @param int $post_id Product post ID.
 */
function wctf_save_fazer_offer_binding( $post_id ) {
    if (
        ! isset( $_POST['wctf_product_offer_binding_nonce'] )
        || ! is_string( $_POST['wctf_product_offer_binding_nonce'] )
    ) {
        return;
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['wctf_product_offer_binding_nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'wctf_save_product_offer_binding' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( ! isset( $_POST['_fazer_offer_id'] ) || ! is_string( $_POST['_fazer_offer_id'] ) ) {
        return;
    }

    $offer_id = sanitize_text_field( wp_unslash( $_POST['_fazer_offer_id'] ) );
    $auto_submit = (
        isset( $_POST['_wctf_fazer_auto_submit_enabled'] )
        && is_string( $_POST['_wctf_fazer_auto_submit_enabled'] )
        && 'yes' === sanitize_key( wp_unslash( $_POST['_wctf_fazer_auto_submit_enabled'] ) )
    ) ? 'yes' : 'no';

    if ( '' === $offer_id ) {
        delete_post_meta( $post_id, '_fazer_category_id' );
        delete_post_meta( $post_id, '_fazer_offer_id' );
        delete_post_meta( $post_id, '_fazer_offer_name' );
        delete_post_meta( $post_id, '_fazer_price_usd' );
        delete_post_meta( $post_id, '_wctf_fazer_offer_key' );
        update_post_meta( $post_id, '_wctf_fazer_auto_submit_enabled', 'no' );
        return;
    }

    if ( 'no' === $auto_submit ) {
        update_post_meta( $post_id, '_wctf_fazer_auto_submit_enabled', 'no' );
    }

    $offer_key = isset( $_POST['_wctf_fazer_offer_key'] ) && is_string( $_POST['_wctf_fazer_offer_key'] )
        ? sanitize_text_field( wp_unslash( $_POST['_wctf_fazer_offer_key'] ) )
        : '';

    if ( '' === $offer_key ) {
        $existing_category_id = sanitize_text_field(
            (string) get_post_meta( $post_id, '_fazer_category_id', true )
        );
        $offer_key = wctf_get_fazercards_offer_key( $existing_category_id, $offer_id );
    }

    $offers = get_option( 'wctf_fazercards_offers', array() );

    if ( ! is_array( $offers ) || '' === $offer_key ) {
        return;
    }

    $offer = wctf_find_fazercards_cached_offer( $offers, $offer_key );

    if (
        empty( $offer )
        || ! isset( $offer['offer_id'], $offer['category_id'], $offer['name'], $offer['price_usd'] )
        || ! is_scalar( $offer['offer_id'] )
        || ! is_scalar( $offer['category_id'] )
        || ! is_scalar( $offer['name'] )
        || ! is_scalar( $offer['price_usd'] )
    ) {
        return;
    }

    $cached_offer_id = sanitize_text_field( (string) $offer['offer_id'] );
    $category_id     = sanitize_text_field( (string) $offer['category_id'] );
    $offer_name      = sanitize_text_field( (string) $offer['name'] );
    $price_usd       = sanitize_text_field( (string) $offer['price_usd'] );
    $cached_offer_key = wctf_get_fazercards_offer_key( $category_id, $cached_offer_id );

    if (
        $offer_id !== $cached_offer_id
        || $offer_key !== $cached_offer_key
        || '' === $category_id
        || '' === $offer_name
        || '' === $price_usd
    ) {
        return;
    }

    update_post_meta( $post_id, '_fazer_category_id', $category_id );
    update_post_meta( $post_id, '_fazer_offer_id', $cached_offer_id );
    update_post_meta( $post_id, '_fazer_offer_name', $offer_name );
    update_post_meta( $post_id, '_fazer_price_usd', $price_usd );
    update_post_meta( $post_id, '_wctf_fazer_offer_key', $cached_offer_key );
    update_post_meta( $post_id, '_wctf_fazer_auto_submit_enabled', $auto_submit );

    $topup_fields = get_option( 'wctf_fazercards_topup_fields', array() );

    if ( is_array( $topup_fields ) && array_key_exists( $category_id, $topup_fields ) ) {
        $field_schema        = wctf_normalize_fazercards_topup_field_schema( $topup_fields[ $category_id ] );
        $required_field_keys = array();

        foreach ( $field_schema as $field_key => $field ) {
            if ( ! empty( $field['required'] ) ) {
                $required_field_keys[] = sanitize_key( (string) $field_key );
            }
        }

        update_post_meta( $post_id, '_topup_fields', implode( ',', $required_field_keys ) );
    }
}

/**
 * Find a cached offer by an exact category and offer composite key.
 *
 * @param array  $offers    Locally cached offers.
 * @param string $offer_key Composite offer key.
 * @return array
 */
function wctf_find_fazercards_cached_offer( $offers, $offer_key ) {
    $key_parts = explode( '::', $offer_key, 2 );

    if ( 2 !== count( $key_parts ) ) {
        return array();
    }

    $category_id = sanitize_text_field( $key_parts[0] );
    $offer_id    = sanitize_text_field( $key_parts[1] );
    $offer_key   = wctf_get_fazercards_offer_key( $category_id, $offer_id );

    if ( '' === $offer_key ) {
        return array();
    }

    $candidates = isset( $offers[ $offer_key ] )
        ? array( $offers[ $offer_key ] )
        : $offers;

    foreach ( $candidates as $offer ) {
        if (
            ! is_array( $offer )
            || ! isset( $offer['category_id'], $offer['offer_id'] )
            || ! is_scalar( $offer['category_id'] )
            || ! is_scalar( $offer['offer_id'] )
        ) {
            continue;
        }

        $cached_category_id = sanitize_text_field( (string) $offer['category_id'] );
        $cached_offer_id    = sanitize_text_field( (string) $offer['offer_id'] );

        if ( $category_id === $cached_category_id && $offer_id === $cached_offer_id ) {
            return $offer;
        }
    }

    return array();
}

/**
 * Load the local offer binding script on WooCommerce product edit screens.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function wctf_enqueue_product_offer_binding_assets( $hook_suffix ) {
    if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    $screen = get_current_screen();

    if ( ! $screen || 'product' !== $screen->post_type ) {
        return;
    }

    $script_path    = __DIR__ . '/js/product-offer-binding.js';
    $script_version = file_exists( $script_path ) ? (string) filemtime( $script_path ) : '1.0.0';

    wp_enqueue_script(
        'wctf-product-offer-binding',
        plugins_url( 'js/product-offer-binding.js', __FILE__ ),
        array(),
        $script_version,
        true
    );

    wp_localize_script(
        'wctf-product-offer-binding',
        'wctfProductOfferBinding',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wctf_browse_fazercards_offers' ),
            'action'  => 'wctf_browse_fazercards_offers',
            'messages' => array(
                'loading'       => __( 'Searching local offers...', 'wc-topup-fields' ),
                'empty'         => __( 'No matching offers found.', 'wc-topup-fields' ),
                'error'         => __( 'The local offer search could not be completed.', 'wc-topup-fields' ),
                'selected'      => __( 'Offer selected. Save the product to apply the binding.', 'wc-topup-fields' ),
                'cleared'       => __( 'Binding cleared. Save the product to apply this change.', 'wc-topup-fields' ),
                'selectOffer'   => __( 'Select offer', 'wc-topup-fields' ),
                'missingConfig' => __( 'Offer search configuration is unavailable.', 'wc-topup-fields' ),
            ),
        )
    );
}
