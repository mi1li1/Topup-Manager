## Build004 Release1 Stable — v1.2.0

Status: Stable and verified
Version: 1.2.0
Date: 2026-07-12

Completed:
- Task1 Gift Card API / architecture analysis
- Task2 Gift Card Catalog / Provider Foundation
- Task3 Gift Card Product Binding
- Task3B Product Type Conditional Binding UI
- Task4 Gift Card Order Snapshot + Dry Run
- Task5A OpenAPI response contract extraction
- Task5B Secure Storage Foundation
- Task5C Manual REAL Purchase
- Task5D Admin Secure Reveal
- Task5E Manual Remote Order Refresh / Recovery
- Task5F One-time post-purchase refresh
- Task5G Auto Refresh Retry Queue
- Task5G Fast Initial Retry Intervals
- Task5G Fast Background Settle Worker
- Task5H-A Customer Display and Polling
- Task5H-B Customer Email Delivery

Verified real-world results:
- Real Gift Card purchase succeeds.
- FazerCards balance is charged once.
- One remote order ID is created.
- Card data is retrieved without manual Refresh.
- Detected code count becomes 1.
- `fulfillment_status` becomes `ready_to_deliver`.
- Admin Reveal displays the card.
- The customer My Account order page displays the card.
- Automatic customer email delivery succeeds.
- Task5H-B was tested and synced to GitHub.

Stable release boundaries:
- v1.2.0 includes admin Manual REAL Gift Card purchase, encrypted storage, near-real-time remote-order synchronization, customer order-page display, and customer email delivery.
- Gift Card purchase remains administrator initiated in this release.
- Customer delivery becomes automatic only after an item reaches `ready_to_deliver`.
- WooCommerce order status is not changed by the Gift Card flow.
- Existing Service Top-up behavior remains unchanged.

Next planned work — explicitly excluded from v1.2.0:
- Automatic Gift Card purchase after paid WooCommerce orders.
- Product-level automatic Gift Card purchase control.
- Failure alerts and operational recovery for automatic purchase.
- Additional customer delivery presentation improvements if required.



## Build003 Release5 Final

Status: Stable and verified
Version: 1.1.0
Date: 2026-07-08

Completed:
- Automatic FazerCards submission for paid Processing/Completed orders
- Global automatic submission control
- Product-level automatic submission whitelist
- Order-item eligibility snapshots
- Per-item locking and idempotency protection
- Automatic failure admin email alerts
- Configurable failure alert recipients
- Manual REAL Submit compatibility
- Classic and HPOS admin visibility

Verified:
- Bigo Live real automatic top-up succeeded
- Mobile Legends Global real automatic top-up succeeded
- Duplicate submission protection works
- Failed automatic submissions do not retry automatically
- Manual retry remains available
- Failure alerts contain no credentials or customer top-up values
- WooCommerce order status remains unchanged
- FazerCards API payload remains unchanged

Cancelled:
- Release5 Task2 Auto Submit Readiness Audit

Skipped:
- Release5 Task5 intentionally skipped



## Build003 Release1

Status: ✅ Completed

Completed:
- Provider foundation
- Request integration
- API settings
- AJAX Test Connection
- Account verification
- Balance retrieval

Verified:
- Successfully connected to FazerCards API
- Account: agurakuqi50
- Balance API working



## Build003 Release2 Task1

Status: ✅ Completed

Completed:
- FazerCards category synchronization
- Cursor pagination support
- Local category cache
- Sync Categories admin button
- AJAX category sync
- Browser cache fix for admin JS

Verified:
- Successfully synchronized 305 categories



## Build003 Release2 Task2

Status: ✅ Completed

Completed:
- FazerCards offer synchronization
- Batched AJAX synchronization
- 10-category batch processing
- Transient-based intermediate sync state
- Atomic final offer storage
- Local offer cache using wctf_fazercards_offers

Verified:
- Processed Categories: 305
- Total Categories: 305
- Total Offers: 4027
- Created: 4027
- Updated: 0
- Skipped: 0
- Failed Categories: 0



## Build003 Release2 Task3

Status: ✅ Completed

Completed:
- Offer Browser backend AJAX
- Offer Browser admin UI
- Offer Browser JavaScript
- Local offer browsing from wctf_fazercards_offers
- Category filter from wctf_fazercards_categories
- AJAX pagination with 50 offers per page
- Global search by Offer ID, Offer Name, Category ID, Category Name, and Price USD

Verified:
- Total Offers: 4027
- Offer Browser loads successfully
- Local cache browsing works
- Category filter works
- Global search works case-insensitively



## Build003 Release2 Task4

Status: ✅ Completed

Completed:
- WooCommerce simple product Offer Binding
- Local Offer search/select UI
- Saved FazerCards offer mapping meta
- Product binding nonce and permission validation
- Server-side validation from local offer cache

Verified:
- Simple product can bind a local FazerCards Offer
- Binding persists after saving product
- Tested Offer ID: 60_uc
- Tested Category: PUBG Mobile (Reserve)



## Build003 Release3 Task1

Status: ✅ Completed

Completed:
- Checkout field collection for FazerCards-bound simple products
- Product-level field configuration using _topup_fields
- Cart item scoped customer input fields
- Checkout validation for required fields
- Sanitized customer input storage
- WooCommerce order item meta storage
- Elementor/WoodMart checkout hook compatibility

Verified:
- Checkout displays player_id and server fields
- Missing required fields block checkout
- Filled fields allow checkout
- Submitted fields are visible in WooCommerce admin order item details



## Build003 Release3 Task2

Status: ✅ Completed

Completed:
- Local FazerCards order payload preparation
- WooCommerce admin order item payload preview
- Product binding data included in payload
- Customer submitted checkout fields included in payload
- Custom product type compatibility for game products

Verified:
- Payload preview displays in WooCommerce admin order details
- Tested Offer ID: 60_uc
- Tested Category ID: pubg_mobile_reserve
- Tested customer fields: player_id, server
- No remote FazerCards API request is made
- WooCommerce order status is unchanged



## Build003 Release3 Task3

Status: ✅ Completed

Completed:
- FazerCards product binding snapshot stored on WooCommerce order items
- Snapshot meta stored during order creation
- Payload preview now prefers order item snapshot data
- Old orders without snapshot data still fall back to product meta
- Supports simple and game product types

Verified:
- New orders store FazerCards snapshot data
- Snapshot includes category ID, offer ID, offer name, price USD, product ID, and created time
- Payload preview remains correct
- No remote FazerCards API request is made
- WooCommerce order status is unchanged




## Build003 Release3 Task4

Status: ✅ Completed

Completed:
- WooCommerce admin FazerCards Dry Run meta box
- Manual local dry-run action
- Payload readiness validation
- Ready / Not Ready result display
- Dry-run payload preview
- Transient-based one-time dry-run result display

Verified:
- Dry Run button appears in WooCommerce admin order
- Dry Run result displays successfully
- Order item status shows Ready
- Payload includes offer ID, category ID, quantity, and customer fields
- No remote FazerCards API request is made
- WooCommerce order status is unchanged



## Build003 Release4 Blocker TaskA

Status: ✅ Completed

Completed:
- Synced FazerCards top-up field schema from offers endpoint
- Stored category-level field schema in wctf_fazercards_topup_fields
- Product binding auto-fills _topup_fields from synced schema
- Schema-based field key validation
- Manual field guessing no longer required

Verified:
- Bigo Live auto-fills field key as bigo_id
- Manually changed fields are restored to schema-defined required fields after product save
- Multiple products tested successfully
- No remote FazerCards order API request is made



## Build003 Release4 Task1

Status: ✅ Completed

Completed:
- Manual FazerCards order submission from WooCommerce admin
- Provider::create_order() integration
- Real FazerCards order creation
- Idempotency key protection
- Per-order-item submission status storage
- Remote order ID and status display
- Duplicate submission prevention after success
- Real submission confirmation checkbox

Verified:
- Bigo Live 5 Diamonds real order submitted successfully
- Field schema auto-filled as bigo_id
- Remote order ID: ord-106211
- Remote status: created
- Real Bigo Live account received recharge successfully
- Submit button disappears after successful submission
- No automatic submission is enabled
- WooCommerce order status is unchanged



## Build003 Release4 Task2

Status: ✅ Completed

Completed:
- Hardened FazerCards submission result display
- Added private admin order notes for successful and failed submissions
- Improved submission meta box display
- Added remote order ID, remote status, submitted time, error and technical details display
- Added duplicate submission blocked message
- Added WooCommerce order list FazerCards status column
- Added classic and HPOS order list support

Verified:
- Submitted orders show remote order ID and status
- Submit button remains hidden after successful submission
- Private admin order note is created
- WooCommerce order list shows FazerCards status
- WooCommerce order status is unchanged



## Build003 Release4 Task2

Status: ✅ Completed and verified

Completed:
- Hardened FazerCards submission result display
- Added private admin order notes for successful and failed submissions
- Improved per-item submission status display
- Added remote order ID, remote status, submitted time, last error and technical details
- Added duplicate submission blocked message
- Added WooCommerce order list FazerCards status column
- Added classic and HPOS order list support

Verified:
- Successful real submission created private admin order note
- Remote order ID: ord-111829
- Remote status: created
- Real Bigo Live account received recharge successfully
- Submitted item hides Submit to FazerCards (REAL)
- WooCommerce order list shows FazerCards submission status
- WooCommerce order status remains unchanged



## Build003 Release5 Task1

Status: ✅ Completed and verified

Completed:
- Added global automatic FazerCards submission setting
- Default auto submission setting is disabled
- Added automatic submission on WooCommerce processing/completed status
- Added paid-date validation before auto submission
- Added per-order-item atomic lock
- Added submitting status
- Reused stable idempotency key
- Shared manual and automatic submission flow
- Automatic failed submissions do not retry automatically
- Manual retry remains available
- Private admin order notes for automatic success/failure

Verified:
- Default disabled state does not auto-submit
- Enabled state auto-submits after payment completion
- Real Bigo Live account received automatic recharge successfully
- Automatic submission does not duplicate after status changes
- Remote order ID and remote status are stored
- WooCommerce order status remains unchanged
- Manual Submit to FazerCards remains compatible



## Build003 Release5 Task3

Status: ✅ Completed and verified

Completed:
- Added product-level auto-submit control
- Added product meta _wctf_fazer_auto_submit_enabled
- Added order-item snapshot _wctf_fazer_auto_submit_enabled_snapshot
- Product-level auto-submit defaults to no
- Automatic submission now requires both global and product-level enablement
- Old orders without snapshot are treated as disabled
- Manual REAL submission remains available even when product-level auto-submit is disabled
- Order meta box shows auto-submit eligibility per item

Verified:
- Product checkbox saves yes/no correctly
- Global yes + product no does not auto-submit
- Global yes + product yes auto-submits successfully
- Manual submission remains available when product-level auto-submit is disabled
- Mobile Legends (Global) real top-up completed successfully
- WooCommerce order status remains unchanged



## Build003 Release5 Task4

Status: ✅ Completed and verified

Completed:
- Added admin email alerts for automatic FazerCards submission failures
- Alerts are sent only after a real automatic API failure
- Alerts are sent for processing/completed auto-submit triggers only
- Added duplicate alert prevention
- Added auto failure alert meta
- Added alert status display in the WooCommerce order meta box
- Later successful submission clears alert meta
- Manual submission failures do not trigger automatic failure alerts

Meta added:
- _wctf_fazer_auto_failure_alert_sent
- _wctf_fazer_auto_failure_alerted_at
- _wctf_fazer_auto_failure_trigger

Verified:
- Automatic failure test succeeded
- Admin email received failure alert
- Failed order status and error were stored correctly
- WooCommerce order status remains unchanged
- No API credentials or customer top-up field values are included in the alert email
- Manual retry remains available



## Build003 Release5 Task4B

Status: ✅ Completed and verified

Completed:
- Added configurable recipients for FazerCards automatic failure alert emails
- Added option wctf_fazercards_failure_alert_recipients
- Supports multiple comma-separated admin emails
- Invalid emails are removed during sanitization
- Duplicate emails are removed
- Empty or invalid configuration falls back to WordPress admin_email
- Failure alert behavior remains automatic-failure-only
- Manual failures do not trigger automatic failure emails

Verified:
- Configured recipient emails receive automatic failure alerts
- Invalid and duplicate email handling works
- WordPress admin_email fallback remains available
- FazerCards API payload remains unchanged
- WooCommerce order status remains unchanged



## Build004 Release1 Task2

Status: ✅ Completed and verified

Completed:
- Added FazerCards Gift Card catalog provider
- Added Gift Card category synchronization
- Added Gift Card cards/SKU synchronization
- Added separate Gift Card cache options
- Added Gift Card Browser in API Settings
- Added search, category filter and pagination for Gift Card Browser
- Used category_id::card_id composite key to prevent cross-category collisions
- Kept Gift Card catalog flow separate from existing Service Top-up flow

New files:
- providers/FazerCards/GiftCardsProvider.php
- admin/giftcard-settings.php
- admin/js/giftcard-settings.js

New cache options:
- wctf_fazercards_giftcard_categories
- wctf_fazercards_giftcard_offers

New AJAX actions:
- wctf_sync_fazercards_giftcard_categories
- wctf_sync_fazercards_giftcard_cards
- wctf_browse_fazercards_giftcards

Verified:
- Gift Card section appears in API Settings
- Sync Gift Card Categories works
- Sync Gift Cards works
- Gift Card Browser search/filter/pagination works
- Gift Card category_id and card_id display correctly
- Existing Top-up sync, offer browser and automatic submission flow remain unaffected

Safety:
- No Gift Card purchase endpoint implemented
- No /giftcards/order call added
- No Gift Card code/card/PIN storage
- No customer delivery
- No WooCommerce order status changes
- Existing Top-up Provider and order flow unchanged



## Build004 Release1 Task3

Status: ✅ Completed and verified

Completed:
- Added WooCommerce simple product Gift Card binding
- Added separate FazerCards Gift Card Binding product admin section
- Added local-cache-only Gift Card SKU search
- Added Gift Card SKU select and clear binding behavior
- Added Gift Card product meta storage
- Enforced mutual exclusivity between Gift Card and Service Top-up bindings
- Gift Card binding saves values from local cache only

New files:
- admin/giftcard-product-fields.php
- admin/js/product-giftcard-binding.js

Modified files:
- wc-topup-fields.php

New product meta:
- _wctf_fazer_product_kind
- _wctf_fazer_giftcard_category_id
- _wctf_fazer_giftcard_card_id
- _wctf_fazer_giftcard_offer_key
- _wctf_fazer_giftcard_offer_name
- _wctf_fazer_giftcard_price_usd
- _wctf_fazer_giftcard_currency
- _wctf_fazer_giftcard_region
- _wctf_fazer_giftcard_min_quantity
- _wctf_fazer_giftcard_max_quantity

Verified:
- Gift Card Binding section appears for simple products
- Gift Card SKU search works from local cache
- Gift Card binding saves and persists after product update
- Gift Card binding can be cleared
- Gift Card and Service Top-up bindings are mutually exclusive
- Existing Top-up product binding and automatic submission remain unaffected

Safety:
- No Gift Card purchase endpoint implemented
- No /giftcards/order call added
- No Gift Card code/PIN/serial/redeem URL storage
- No customer delivery
- No WooCommerce order status changes
- Existing Top-up automatic submission flow unchanged



## Build004 Release1 Task3B

Status: ✅ Completed and verified

Completed:
- Added product type based binding panel visibility
- Added centralized product type visibility controller
- New/simple products hide all binding panels until a product type is selected
- Gift Card products show only FazerCards Gift Card Binding
- Game top-up products show only FazerCards Offer / Top-up Binding
- Account top-up products show only account placeholder
- Added server-side save safety based on _topup_type
- Enforced mutual exclusivity between Gift Card and Service Top-up bindings

Product type rules:
- giftcard shows Gift Card Binding and clears Top-up binding
- game shows Top-up Binding and clears Gift Card binding
- account hides both binding panels, clears both binding sets and disables product-level auto-submit
- empty/unknown hides all binding panels and prevents new binding saves

New file:
- admin/js/product-type-visibility.js

Modified files:
- admin/fazer-fields.php
- admin/giftcard-product-fields.php
- admin/product-fields.php

Verified:
- New product shows only base fields before selecting product type
- Selecting Gift Card shows only Gift Card Binding
- Selecting Game Top-up shows only Top-up Binding
- Selecting Account Top-up shows only the account placeholder
- UI updates immediately when changing product type
- Saving and refreshing keeps the correct panel visible
- WooCommerce show_if_simple no longer re-shows both panels
- Existing Game Top-up products remain unaffected

Safety:
- No Gift Card purchase endpoint implemented
- No /giftcards/order call added
- No Gift Card code/PIN/serial/redeem URL storage
- No account top-up functionality implemented
- Existing Top-up automatic submission logic unchanged



## Build004 Release1 Task4

Status: ✅ Completed and verified

Completed:
- Added Gift Card order item snapshot
- Added separate admin-only Gift Card Dry Run preview
- Gift Card Dry Run uses order item snapshot only
- Gift Card Dry Run shows future purchase payload preview without submitting
- Added quantity validation warnings
- Gift Card order snapshot remains independent from later product binding changes

Modified files:
- includes/order.php

New order item meta:
- _wctf_fazer_item_kind
- _wctf_fazer_giftcard_category_id
- _wctf_fazer_giftcard_card_id
- _wctf_fazer_giftcard_offer_key
- _wctf_fazer_giftcard_offer_name
- _wctf_fazer_giftcard_price_usd
- _wctf_fazer_giftcard_currency
- _wctf_fazer_giftcard_region
- _wctf_fazer_giftcard_min_quantity
- _wctf_fazer_giftcard_max_quantity
- _wctf_fazer_giftcard_snapshot_created_at

Verified:
- Gift Card orders store snapshot meta on order items
- Admin order page shows FazerCards Gift Card Dry Run
- Dry Run payload displays category_id, card_id and quantity
- Dry Run has no submit button
- Old orders continue using order item snapshot after product binding changes
- Quantity warnings work
- Existing Service Top-up Dry Run, REAL Submit and automatic submission remain unaffected

Safety:
- No Gift Card purchase endpoint implemented
- No /giftcards/order call added
- No Gift Card code/PIN/serial/redeem URL storage
- No customer delivery
- No WooCommerce order status changes
- Existing Top-up automatic submission flow unchanged



## Build004 Release1 Task5B

Status: ✅ Completed and verified

Completed:
- Added secure Gift Card encryption foundation
- Added authenticated encrypted order-item secret storage helpers
- Added strict 32-byte Base64 encryption key validation
- Added Sodium Secretbox encryption with AES-256-GCM fallback
- Added order and order-item context binding
- Added self-describing encrypted envelope format
- Added safe Gift Card response summary helper
- Added non-recursive Gift Card code count detection
- Added Gift Card encrypted storage readiness display in API Settings
- Added fail-closed behavior when encryption is not ready

New files:
- includes/giftcard-crypto.php
- includes/giftcard-secret-storage.php

Modified files:
- wc-topup-fields.php
- admin/pages/api-settings.php

Verified:
- Missing encryption key reports Not Ready
- Invalid or missing key disables future Gift Card purchasing
- Valid 32-byte Base64 key reports Ready
- Server supports Sodium Secretbox
- Encryption key value is not displayed or stored in the database
- docs/openapi.json remains outside Git tracking
- Existing Gift Card and Service Top-up flows remain unchanged

Safety:
- No /giftcards/order call
- No Gift Card purchase button
- No real Gift Card code storage
- No reveal or customer delivery
- No plaintext secrets in meta, logs, notes or emails
- No WooCommerce order status changes
- Existing Service Top-up submission logic unchanged
