## 1.1.0 — 2026-07-08

### Added
- Automatic FazerCards submission after paid orders enter Processing or Completed
- Global automatic submission setting
- Product-level automatic submission whitelist
- Order-item auto-submit eligibility snapshot
- Admin email alerts for automatic submission failures
- Configurable comma-separated failure alert recipients
- WordPress admin email fallback

### Safety
- Stable per-order-item idempotency keys
- Atomic submission locks
- Duplicate successful submissions are blocked
- Failed automatic submissions are not retried automatically
- Manual REAL Submit remains available
- Customer top-up values and credentials are excluded from alert emails
- WooCommerce order status is never changed by the submission flow

### Verified
- Bigo Live real automatic top-up succeeded
- Mobile Legends Global real automatic top-up succeeded

### Scope
- Task2 Auto Submit Readiness Audit was cancelled and is not included
- Task5 was intentionally skipped



## Build003 Release1

### Added
- FazerCards Provider foundation
- Admin API Settings page
- AJAX Test Connection
- Account information display
- Balance display



## Build003 Release2 Task1

### Added
- FazerCards category synchronization
- Sync Categories admin button
- AJAX category synchronization
- Local category storage using wctf_fazercards_categories

### Fixed
- Admin JavaScript cache issue by using filemtime() for script versioning



## Build003 Release2 Task2

### Added
- FazerCards offer synchronization
- Sync Offers admin button
- Batched AJAX offer synchronization
- Local offer storage using wctf_fazercards_offers
- Progress display for processed categories, total offers, created, updated, skipped, and failed categories



## Build003 Release2 Task3

### Added
- Admin Offer Browser
- AJAX offer browsing from local cache
- Search by Offer ID, Offer Name, Category ID, Category Name, and Price USD
- Category filter
- Paginated offer table
- Safe DOM rendering for offer data



## Build003 Release2 Task4

### Added
- WooCommerce simple product FazerCards Offer Binding
- Local Offer search/select UI on product edit page
- Product meta mapping for category ID, offer ID, offer name, and price USD
- Safe server-side validation from local offer cache



## Build003 Release3 Task1

### Added
- Checkout customer field collection for FazerCards-bound products
- Required field validation at checkout
- Order item meta storage for submitted customer fields
- Elementor/WoodMart checkout hook compatibility

### Changed
- Checkout field rendering hook changed to support Elementor/WoodMart WooCommerce Hook layout



## Build003 Release3 Task2

### Added
- FazerCards order payload preview in WooCommerce admin orders
- Local payload preparation from product binding and order item meta
- Customer submitted fields included in payload
- Support for game product type payload preview



## Build003 Release3 Task3

### Added
- FazerCards order item snapshot meta
- Snapshot-based payload preview
- Fallback support for old orders without snapshots



## Build003 Release3 Task4

### Added
- FazerCards Dry Run meta box in WooCommerce admin orders
- Manual dry-run action for local payload validation
- Ready / Not Ready result display
- Local payload preview for dry-run results



## Build003 Release4 Blocker TaskA

### Added
- FazerCards top-up field schema synchronization
- Category-level required field storage
- Product binding auto-fill for top-up fields
- Schema-authoritative field key handling



## Build003 Release4 Task1

### Added
- Manual Submit to FazerCards action in WooCommerce admin orders
- Real FazerCards top-up order creation
- Idempotency-key based duplicate protection
- Submission status and remote response storage
- Remote order ID and status display
- Real submission confirmation checkbox

### Verified
- Bigo Live real top-up order completed successfully



## Build003 Release4 Task2

### Added
- Private admin order notes for FazerCards submission results
- Improved FazerCards submission result display
- WooCommerce order list FazerCards status column
- Submitted / Failed / Not submitted / Mixed / Not applicable status display

### Improved
- Duplicate submission visibility
- Failed submission retry visibility
- Remote response and idempotency key technical details



## Build003 Release4 Task2

### Added
- Private admin order notes for FazerCards submission results
- Improved FazerCards submission result display
- WooCommerce order list FazerCards status column
- Submitted / Failed / Not submitted / Mixed / Not applicable status display

### Verified
- Real Bigo Live submission created a private admin order note
- Real Bigo Live recharge arrived successfully



## Build003 Release5 Task1

### Added
- Optional automatic FazerCards submission after paid WooCommerce orders enter Processing or Completed
- Global auto-submit enable/disable setting
- Per-item submitting status and atomic lock
- Shared manual/automatic submission handling
- Automatic submission admin notes

### Verified
- Automatic Bigo Live real top-up completed successfully
- Duplicate prevention works after order status changes



## Build003 Release5 Task3

### Added
- Product-level automatic FazerCards submission control
- Per-order-item auto-submit eligibility snapshot
- Admin order display for auto-submit eligibility

### Improved
- Automatic submission now only runs for whitelisted products
- Safer rollout for untested FazerCards products

### Verified
- Mobile Legends (Global) real automatic top-up completed successfully



## Build003 Release5 Task4

### Added
- Admin email alert for automatic FazerCards submission failures
- Duplicate prevention for failure alert emails
- Auto failure alert meta tracking
- Auto failure alert status display in the order meta box

### Improved
- Admin visibility when automatic submission fails
- Safer operations for paid orders that fail to submit to FazerCards

### Verified
- Automatic submission failure sends an admin email alert
- WooCommerce order status is not changed by the failure alert system
- Sensitive data is not included in failure alert emails



## Build003 Release5 Task4B

### Added
- Configurable recipients for FazerCards automatic submission failure alerts
- Multiple comma-separated recipient support
- Fallback to WordPress admin email when no valid recipients are configured

### Improved
- More flexible admin notification routing for failed automatic submissions
- Email sanitization and duplicate recipient removal



## Build004 Release1 Task2

### Added
- FazerCards Gift Card catalog provider
- Gift Card category synchronization
- Gift Card cards/SKU synchronization
- Separate Gift Card cache options
- Gift Card Browser in API Settings
- Search, category filter and pagination for Gift Card Browser

### Safety
- Gift Card catalog is separated from Service Top-up flow
- Gift Card purchase endpoint is not implemented
- No Gift Card codes, PINs or redeem URLs are stored
- No customer delivery is implemented
- Existing Top-up automatic submission flow is unchanged

### Verified
- Gift Card Categories sync completed successfully
- Gift Cards sync completed successfully
- Gift Card Browser works correctly
- Existing Service Top-up features remain unaffected



## Build004 Release1 Task3

### Added
- WooCommerce simple product binding for FazerCards Gift Cards
- Gift Card SKU search from local synced cache
- Gift Card product meta storage
- Gift Card binding clear action
- Mutual exclusivity between Gift Card and Service Top-up bindings

### Safety
- Gift Card binding is product-admin only
- Gift Card purchase endpoint is not implemented
- No Gift Card codes, PINs, serials or redeem URLs are stored
- Existing Service Top-up automatic submission flow is unchanged

### Verified
- Gift Card SKU binding works correctly
- Saved Gift Card binding persists after product update
- Clearing Gift Card binding works correctly
- Existing Service Top-up products remain unaffected