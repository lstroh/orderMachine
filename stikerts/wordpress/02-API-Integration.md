# API Integration — eBay & Etsy

*Detailed design, part 2 of 4 · Builds on `01-Data-Model.md` · Amazon deliberately excluded — see requirements doc §5/§7 for the deferred email→API workaround idea.*

---

## 1. eBay — Sell APIs

### Auth
- Register a developer account at developer.ebay.com (free, self-serve — no business review gate for this use case, since you're only ever authenticating your own seller account).
- Use **OAuth 2.0 Authorization Code flow** to get a **user access token** (short-lived, ~2 hrs) + **refresh token** (long-lived, ~18 months) tied to your own eBay seller login.
- Store both in `wp_som_channels.credentials` (encrypted JSON: `access_token`, `refresh_token`, `expires_at`).
- Plugin needs a background job to refresh the access token using the refresh token before it expires — don't wait for a 401 to trigger this, check proactively on each cron run.
- Sandbox environment exists for testing before going live — worth building against sandbox first given the OAuth flow has a few moving parts.

### Orders — Fulfillment API
- **Endpoint:** `GET /sell/fulfillment/v1/order` — supports filtering by date range and order status.
- No webhook/push support for orders in the standard Fulfillment API — **polling required**.
- **Suggested polling schedule:** every 10–15 minutes via WP Cron (`wp_schedule_event`), pulling orders modified since `channels.last_synced_at`.
- Field mapping (eBay response → `wp_som_orders` / `order_items`):

| eBay field | Internal field |
|---|---|
| `orderId` | `external_order_id` |
| `creationDate` | `order_date` |
| `buyer.username` (or shipping name if present) | `buyer_name` |
| `fulfillmentStartInstructions[0].shippingStep.shipTo` | `shipping_address` (as JSON) |
| `lineItems[].sku` or `lineItems[].legacyItemId` | matched against `wp_som_listings.external_listing_id` to resolve `product_id` |
| `lineItems[].quantity` | `order_items.quantity` |
| custom variation/personalisation fields (varies by listing setup) | `order_items.personalisation_text` |

**Cancellation detection:** check `orderFulfillmentStatus` / cancellation status fields on each poll — a status change to cancelled should trigger the stock-reversal log entry (see Data Model §4).

### Listings — Inventory API
- **Endpoint:** `GET/PUT /sell/inventory/v1/inventory_item/{sku}` for stock quantity, `POST /sell/inventory/v1/offer/{offerId}/publish` family of endpoints for price/description changes on an existing offer.
- eBay's Inventory API is SKU-based — this only works cleanly if your eBay listings already have SKUs set. If they don't yet, that's a prerequisite task before listing-push functionality can work (worth checking your current listings before building this part).

---

## 2. Etsy — API v3

### Auth
- Register a **Seller App** (not a commercial/public app) in Etsy's developer portal — approved quickly since it's scoped to your own shop only.
- **OAuth 2.0 with PKCE** — get an access token (1 hr) + refresh token (90 days).
- Same storage pattern as eBay: encrypted JSON in `channels.credentials`, proactive refresh via cron.

### Orders — Shop Receipts
- **Endpoint:** `GET /v3/application/shops/{shop_id}/receipts` — supports `min_created` / `max_created` filters for polling.
- **No webhook support at all in Etsy's API** — polling is the only option, confirmed in the requirements doc already. Same 10–15 min cron cadence as eBay is reasonable.
- Field mapping (Etsy receipt → internal):

| Etsy field | Internal field |
|---|---|
| `receipt_id` | `external_order_id` |
| `created_timestamp` | `order_date` |
| `name` (buyer shipping name) | `buyer_name` |
| `first_line`, `second_line`, `city`, `state`, `zip`, `country_iso` | `shipping_address` (as JSON) |
| `transactions[].listing_id` | matched against `wp_som_listings.external_listing_id` |
| `transactions[].quantity` | `order_items.quantity` |
| `transactions[].variations[].formatted_value` (personalisation is usually a variation or a "Personalization" field on the transaction) | `order_items.personalisation_text` |
| `status` (`open`, `unshipped`, `shipped`, `canceled`) | drives cancellation-reversal logic |

### Listings — Listing Inventory
- **Endpoint:** `GET/PUT /v3/application/listings/{listing_id}/inventory` for quantity, `PATCH /v3/application/shops/{shop_id}/listings/{listing_id}` for price/description/title.
- Etsy's inventory model is per-listing with optional per-variation quantities (e.g. by size/colour) — if any of your listings use variations, quantity updates need to target the right variation row within the inventory payload, not just a single top-level number. Worth checking whether your actual listings use Etsy variations before assuming a flat quantity model.

---

## 3. Cross-cutting concerns

- **Rate limits:** both eBay (5,000 calls/day default tier) and Etsy (10,000 calls/day, 10/sec burst) are generous enough for a single small shop polling every 10–15 min — not a practical constraint at this scale, no special handling needed beyond basic courtesy (don't poll more often than needed).
- **Credential storage:** use WordPress's `wp_salt()`-derived key (or a dedicated constant in `wp-config.php`) to encrypt tokens at rest in the `credentials` column rather than storing them in plain text in the DB — this is a single-user internal tool, but tokens are still live API access.
- **Idempotent sync:** every sync run should be safe to re-run without side effects — the `UNIQUE (channel_id, external_order_id)` constraint (Data Model §2) is the backstop; sync logic should `INSERT ... ON DUPLICATE KEY UPDATE` or check-then-insert rather than assuming a clean run every time.
- **Address format storage:** deliberately kept as a JSON blob rather than separate columns (see Data Model §2, orders table) — eBay and Etsy structure addresses slightly differently, and normalising into rigid columns adds complexity for no real benefit given this data is only ever read (copied into Click & Drop), never queried by address field.

## 4. Open items to resolve before/during build

- **Personalisation field location varies per listing setup on both platforms** — worth pulling a handful of real recent orders from each channel's existing dashboard and checking exactly where the personalisation text sits in the raw API response before finalising the mapping table above, rather than assuming.
- **SKU/listing-ID matching to internal products:** decide the matching rule now — e.g. maintain the `external_listing_id ↔ product_id` link manually in `wp_som_listings` (simplest, and probably necessary anyway since it's needed for the inventory-push feature too) rather than trying to auto-match by title/text.
