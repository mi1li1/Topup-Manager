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