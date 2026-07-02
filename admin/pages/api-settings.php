<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册设置
 */

$offer_browser_categories       = get_option( 'wctf_fazercards_categories', array() );
$offer_browser_category_options = array();

if ( is_array( $offer_browser_categories ) ) {
    foreach ( $offer_browser_categories as $category ) {
        if (
            ! is_array( $category )
            || ! isset( $category['category_id'] )
            || ! is_scalar( $category['category_id'] )
        ) {
            continue;
        }

        $category_id   = sanitize_text_field( (string) $category['category_id'] );
        $category_name = '';

        if ( isset( $category['name'] ) && is_scalar( $category['name'] ) ) {
            $category_name = sanitize_text_field( (string) $category['name'] );
        }

        if ( '' === $category_id ) {
            continue;
        }

        $offer_browser_category_options[] = array(
            'id'   => $category_id,
            'name' => $category_name,
        );
    }
}

usort(
    $offer_browser_category_options,
    function ( $first, $second ) {
        $name_comparison = strcasecmp( $first['name'], $second['name'] );

        if ( 0 !== $name_comparison ) {
            return $name_comparison;
        }

        return strcasecmp( $first['id'], $second['id'] );
    }
);

?>

<div class="wrap">

    <h1>FazerCards API 设置</h1>

    <form method="post" action="options.php">

        <?php
        settings_fields('wctf_api_settings');
        ?>

        <table class="form-table">

            <tr>
                <th scope="row">API Base URL</th>
                <td>
                    <input
                        type="text"
                        name="wctf_api_url"
                        value="<?php echo esc_attr(get_option('wctf_api_url', 'https://api.fzr.cards')); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">API Key</th>
                <td>
                    <input
                        type="text"
                        name="wctf_api_key"
                        value="<?php echo esc_attr(get_option('wctf_api_key')); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">API Secret</th>
                <td>
                    <input
                        type="password"
                        name="wctf_api_secret"
                        value="<?php echo esc_attr(get_option('wctf_api_secret')); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>

        </table>

        <?php submit_button('保存设置'); ?>

    </form>

    <hr>

    <h2><?php esc_html_e( 'Test Connection', 'wc-topup-fields' ); ?></h2>

    <p>
        <?php esc_html_e( 'Save the API settings above before running the connection test.', 'wc-topup-fields' ); ?>
    </p>

    <p>
        <button type="button" class="button button-secondary" id="wctf-test-connection">
            <?php esc_html_e( 'Test Connection', 'wc-topup-fields' ); ?>
        </button>
        <span class="spinner" id="wctf-test-connection-spinner" aria-hidden="true"></span>
    </p>

    <table
        class="widefat striped"
        id="wctf-connection-results"
        style="max-width: 700px;"
        aria-live="polite"
        hidden
    >
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e( 'Connection Status', 'wc-topup-fields' ); ?></th>
                <td id="wctf-connection-status"></td>
            </tr>
            <tr id="wctf-account-row" hidden>
                <th scope="row"><?php esc_html_e( 'Account Name', 'wc-topup-fields' ); ?></th>
                <td id="wctf-account-name"></td>
            </tr>
            <tr id="wctf-balance-row" hidden>
                <th scope="row"><?php esc_html_e( 'Balance', 'wc-topup-fields' ); ?></th>
                <td id="wctf-balance"></td>
            </tr>
            <tr id="wctf-error-row" hidden>
                <th scope="row"><?php esc_html_e( 'Error Message', 'wc-topup-fields' ); ?></th>
                <td id="wctf-connection-error"></td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2><?php esc_html_e( 'Category Synchronization', 'wc-topup-fields' ); ?></h2>

    <p>
        <?php esc_html_e( 'Download the latest FazerCards categories and store them locally.', 'wc-topup-fields' ); ?>
    </p>

    <p>
        <button type="button" class="button button-secondary" id="wctf-sync-categories">
            <?php esc_html_e( 'Sync Categories', 'wc-topup-fields' ); ?>
        </button>
        <span class="spinner" id="wctf-sync-categories-spinner" aria-hidden="true"></span>
    </p>

    <table
        class="widefat striped"
        id="wctf-category-sync-results"
        style="max-width: 700px;"
        aria-live="polite"
        hidden
    >
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e( 'Synchronization Status', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-sync-status"></td>
            </tr>
            <tr id="wctf-category-total-row" hidden>
                <th scope="row"><?php esc_html_e( 'Total Categories', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-total"></td>
            </tr>
            <tr id="wctf-category-created-row" hidden>
                <th scope="row"><?php esc_html_e( 'Created', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-created"></td>
            </tr>
            <tr id="wctf-category-updated-row" hidden>
                <th scope="row"><?php esc_html_e( 'Updated', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-updated"></td>
            </tr>
            <tr id="wctf-category-skipped-row" hidden>
                <th scope="row"><?php esc_html_e( 'Skipped', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-skipped"></td>
            </tr>
            <tr id="wctf-category-error-row" hidden>
                <th scope="row"><?php esc_html_e( 'Error Message', 'wc-topup-fields' ); ?></th>
                <td id="wctf-category-sync-error"></td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2><?php esc_html_e( 'Offer Synchronization', 'wc-topup-fields' ); ?></h2>

    <p>
        <?php esc_html_e( 'Download offers for all locally synchronized FazerCards categories.', 'wc-topup-fields' ); ?>
    </p>

    <p>
        <button type="button" class="button button-secondary" id="wctf-sync-offers">
            <?php esc_html_e( 'Sync Offers', 'wc-topup-fields' ); ?>
        </button>
        <span class="spinner" id="wctf-sync-offers-spinner" aria-hidden="true"></span>
    </p>

    <table
        class="widefat striped"
        id="wctf-offer-sync-results"
        style="max-width: 700px;"
        aria-live="polite"
        hidden
    >
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e( 'Synchronization Status', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-sync-status"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Processed Categories', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-processed-categories">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Total Categories', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-total-categories">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Total Offers', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-total">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Created', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-created">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Updated', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-updated">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Skipped', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-skipped">0</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Failed Categories', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-failed-categories">0</td>
            </tr>
            <tr id="wctf-offer-error-row" hidden>
                <th scope="row"><?php esc_html_e( 'Error Message', 'wc-topup-fields' ); ?></th>
                <td id="wctf-offer-sync-error"></td>
            </tr>
        </tbody>
    </table>

    <hr>

    <section id="wctf-offer-browser" aria-labelledby="wctf-offer-browser-heading">
        <h2 id="wctf-offer-browser-heading">
            <?php esc_html_e( 'Offer Browser', 'wc-topup-fields' ); ?>
        </h2>

        <p>
            <?php esc_html_e( 'Search and browse locally cached FazerCards offers.', 'wc-topup-fields' ); ?>
        </p>

        <div class="tablenav top">
            <label for="wctf-offer-browser-search">
                <?php esc_html_e( 'Search offer, category, ID or price', 'wc-topup-fields' ); ?>
            </label>
            <input
                type="search"
                id="wctf-offer-browser-search"
                class="regular-text"
                placeholder="<?php echo esc_attr__( 'Search offer, category, ID or price', 'wc-topup-fields' ); ?>"
            >

            <label for="wctf-offer-browser-category">
                <?php esc_html_e( 'Category', 'wc-topup-fields' ); ?>
            </label>
            <select id="wctf-offer-browser-category">
                <option value=""><?php esc_html_e( 'All Categories', 'wc-topup-fields' ); ?></option>
                <?php foreach ( $offer_browser_category_options as $category_option ) : ?>
                    <option value="<?php echo esc_attr( $category_option['id'] ); ?>">
                        <?php
                        echo esc_html(
                            '' !== $category_option['name']
                                ? $category_option['name'] . ' (' . $category_option['id'] . ')'
                                : $category_option['id']
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="button" class="button button-primary" id="wctf-offer-browser-submit">
                <?php esc_html_e( 'Search', 'wc-topup-fields' ); ?>
            </button>
            <button type="button" class="button" id="wctf-offer-browser-reset">
                <?php esc_html_e( 'Reset', 'wc-topup-fields' ); ?>
            </button>
        </div>

        <div
            class="notice notice-error inline"
            id="wctf-offer-browser-error"
            role="alert"
            hidden
        >
            <p id="wctf-offer-browser-error-message"></p>
        </div>

        <div class="notice notice-info inline" id="wctf-offer-browser-empty" hidden>
            <p><?php esc_html_e( 'No matching offers found.', 'wc-topup-fields' ); ?></p>
        </div>

        <table class="widefat fixed striped" id="wctf-offer-browser-results">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Offer ID', 'wc-topup-fields' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Category ID', 'wc-topup-fields' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Category Name', 'wc-topup-fields' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Offer Name', 'wc-topup-fields' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Price USD', 'wc-topup-fields' ); ?></th>
                </tr>
            </thead>
            <tbody id="wctf-offer-browser-rows"></tbody>
        </table>

        <div class="tablenav bottom" id="wctf-offer-browser-pagination">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <span id="wctf-offer-browser-total-results">0</span>
                    <?php esc_html_e( 'total results', 'wc-topup-fields' ); ?>
                </span>

                <button
                    type="button"
                    class="button"
                    id="wctf-offer-browser-previous"
                    disabled
                >
                    <?php esc_html_e( 'Previous', 'wc-topup-fields' ); ?>
                </button>

                <span class="paging-input">
                    <?php esc_html_e( 'Current page', 'wc-topup-fields' ); ?>
                    <span id="wctf-offer-browser-current-page">1</span>
                    /
                    <?php esc_html_e( 'Total pages', 'wc-topup-fields' ); ?>
                    <span id="wctf-offer-browser-total-pages">1</span>
                </span>

                <button
                    type="button"
                    class="button"
                    id="wctf-offer-browser-next"
                    disabled
                >
                    <?php esc_html_e( 'Next', 'wc-topup-fields' ); ?>
                </button>
            </div>
        </div>
    </section>

</div>
