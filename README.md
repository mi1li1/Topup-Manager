# WC Topup Fields

Version: 1.2.0

WC Topup Fields connects WooCommerce to two independent FazerCards systems: Service Top-up and Gift Cards. Catalog caches, product bindings, order metadata, submission logic, and fulfillment data remain separated by product kind.

## FazerCards API Configuration

Open **Topup Manager > API Settings** in WordPress admin and configure the FazerCards API URL and API key. Save the settings before testing or synchronizing data.

Recommended setup order:

1. Test Connection.
2. Sync Service Top-up Categories and Offers.
3. Sync Gift Card Categories and Cards.
4. Select the product type and bind the WooCommerce product to the appropriate locally synchronized offer or card.

Product editors use local synchronized caches and do not make remote catalog requests.

## Service Top-up

The existing Service Top-up workflow supports:

- Category, offer, and customer-field schema synchronization.
- Local offer browsing and product binding.
- Required customer field collection during classic checkout.
- Immutable order-item snapshots and payload preview.
- Manual Dry Run and Manual REAL Submit.
- Controlled automatic submission for paid Processing or Completed orders.
- Global and product-level automatic submission controls.
- Stable idempotency keys, per-item locks, and duplicate-submission prevention.
- Admin failure alerts with configurable recipients.

Service Top-up automatic submission requires both the global setting and the product-level whitelist setting. Failed automatic submissions are not retried automatically; a safe manual retry remains available.

## Gift Cards

The Gift Card workflow is independent from Service Top-up and supports:

- Separate Gift Card category and card catalog synchronization.
- Separate local Gift Card browser and product binding.
- Product-type conditional binding controls.
- Immutable Gift Card order-item snapshots and Dry Run preview.
- Administrator-initiated Manual REAL Purchase with stable idempotency and duplicate-purchase protection.
- Authenticated encrypted storage for opaque Gift Card purchase responses.
- Admin-only Reveal and manual remote-order Refresh/Recovery.
- One-time post-purchase refresh, background retry queue, and Fast Background Settle.
- Authorized customer display on My Account View Order and Thank You / Order Received pages.
- Frontend status polling with private no-store/no-cache responses.
- Automatic customer email delivery after an item reaches `ready_to_deliver`.
- Admin SEND and RESEND controls with per-item delivery status and duplicate-send protection.

### Gift Card Operational Boundary

Gift Card purchase remains manual in v1.2.0 and must be initiated by an administrator through the **Manual REAL Purchase** control.

Customer delivery becomes automatic only after the order item reaches `ready_to_deliver`. Remote Refresh and Fast Settle use read-only order retrieval and never create another Gift Card purchase.

Build004 Release1 does not include:

- Automatic Gift Card purchase immediately after WooCommerce payment.
- Customer-triggered Gift Card purchase.
- Automatic Gift Card purchase retry.
- Gift Card-driven WooCommerce order-status changes.

## Gift Card Encryption Security

Gift Card secret storage requires the `WCTF_GIFTCARD_ENCRYPTION_KEY` constant in `wp-config.php`.

Use 32 cryptographically random bytes encoded with the `base64:` prefix. Keep the key outside the WordPress database and never commit it to Git. For example, generate the value through a trusted system cryptographic tool and place only the resulting secret in `wp-config.php`.

Important key-handling rules:

- Never store the encryption key in a plugin setting, database option, log, or repository.
- Back up the key securely and restrict access to it.
- Changing or losing the key prevents decryption of existing encrypted Gift Card payloads.
- Never place Gift Card codes, PINs, serial numbers, redeem URLs, or decrypted responses in logs, order notes, or normal metadata.

The plugin prefers Sodium Secretbox and uses AES-256-GCM as the authenticated-encryption fallback. Gift Card payloads must pass authenticated decryption and WooCommerce order/item context validation before Reveal, customer display, or email delivery.

## Customer Delivery Safety

- Registered customers must own the WooCommerce order.
- Guest access requires the full WooCommerce order key.
- Customer pages never receive the full opaque remote order object.
- Customer page display does not depend on email delivery status.
- Email delivery revalidates the encrypted payload immediately before sending.
- Card contents are held only in memory while rendering an authorized page or constructing the delivery email.
- Email delivery metadata contains only safe status information and the validated billing recipient.

## General Safety Notes

- The plugin does not change WooCommerce order status in either submission flow.
- Service Top-up and Gift Card data and execution paths remain isolated.
- No automatic Gift Card purchase occurs in v1.2.0.
- Remote Gift Card refresh operations do not spend balance or create a second purchase.
- Review API credentials, product bindings, encryption readiness, and mail transport before live use.

## Release

Build004 Release1 Stable — version 1.2.0, released 2026-07-12.
