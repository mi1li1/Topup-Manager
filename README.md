# WC Topup Fields

Version: 1.1.0

WC Topup Fields connects WooCommerce products to locally synchronized FazerCards top-up offers. It collects the required customer fields at checkout and supports protected manual or automatic FazerCards order submission from WooCommerce orders.

## FazerCards API configuration

Open **Topup Manager > API Settings** in WordPress admin and configure the FazerCards API URL and API key. Save the settings before testing or synchronizing data.

Use this setup order:

1. Test Connection.
2. Sync Categories.
3. Sync Offers.
4. Edit a WooCommerce simple product and bind it to a locally synchronized FazerCards offer.

Offer synchronization also stores the FazerCards top-up field schema used by product binding and checkout field collection.

## Automatic submission

Automatic submission is protected by two independent controls:

- The global **Automatic FazerCards Submission** setting on the API Settings page.
- The product-level **Enable automatic FazerCards submission for this product** setting in the FazerCards offer binding area.

Both switches must be enabled. Product eligibility is copied to the WooCommerce order item when the order is created, so later product-setting changes do not alter existing orders.

An eligible order item may be submitted automatically when:

- The WooCommerce order enters **Processing** or **Completed**.
- The order has a paid date.
- The prepared FazerCards payload is ready.
- The order-item quantity is exactly `1`.
- The item has not already been submitted, is not currently submitting, and has not previously failed.

Failed automatic submissions are not retried automatically.

## Duplicate protection

Each eligible order item uses:

- A stable idempotency key.
- A per-item submission lock.
- Persistent submitted, submitting, or failed status metadata.

A successfully submitted item cannot be submitted again. Multiple items in one WooCommerce order are handled independently.

## Failure alerts

Automatic submission failures can send a plain-text email to one or more configured admin recipients. Configure **FazerCards Failure Alert Recipients** on the API Settings page using comma-separated email addresses.

If the recipient setting is empty or contains no valid addresses, the current WordPress admin email is used. Duplicate and invalid configured addresses are removed.

Failure alert emails do not contain API credentials, request headers, raw responses, or customer top-up values.

## Manual submission and retry

The WooCommerce order admin includes FazerCards payload preview, Dry Run, submission status, and manual REAL Submit controls.

Manual submission remains available when product-level automatic submission is disabled. A failed item may be retried manually using the same stable idempotency key. Manual submission requires explicit administrator confirmation.

## Safety notes

- The plugin does not change WooCommerce order status as part of FazerCards submission.
- The FazerCards API payload remains limited to `category_id`, `offer_id`, and `fields`.
- Automatic submission requires both global and product-level enablement.
- Automatic failures do not trigger automatic retries, refunds, or cancellations.
- Customer top-up values are not included in failure alert emails.
- Review API settings, product bindings, and required fields before enabling real automatic submission.

## Release5 scope

Cancelled:

- Task2 Auto Submit Readiness Audit is not included.

Skipped:

- Task5 Failure Recovery Helper was intentionally skipped.
