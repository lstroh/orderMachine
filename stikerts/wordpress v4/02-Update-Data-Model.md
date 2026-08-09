# Update — Data Model Changes

*Update set 2 of 4 · Self-contained. Analytics Dashboard (`04-Update-Analytics-Dashboard.md`) requires no schema changes — everything below is for Platform Selling Fees.*

---

## New tables for Platform Selling Fees

### `wp_som_channel_fee_estimates`
Manually-editable estimated fee components per channel, used for pricing before real data exists. Component-level (not one blended number) so it mirrors how the fees actually break down and stays accurate as individual components change.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `channel_id` | INT FK → channels.id | |
| `fee_component` | VARCHAR(50) | e.g. `final_value_fee`, `per_order_fee`, `regulatory_fee`, `promoted_listings`, `transaction_fee`, `payment_processing`, `listing_fee`, `offsite_ads`, `vat_on_fees` |
| `rate_type` | ENUM | `percent` or `fixed` |
| `rate_value` | DECIMAL(10,4) | percentage (0–100) or a flat £ amount, depending on `rate_type` |
| `notes` | TEXT NULL | |
| `created_at` / `updated_at` | DATETIME | |

**Seed data recommendation** (from your existing `eBay-Marketing-Guide.md` / `Etsy-Marketing-Guide.md`, checked Aug 2026 — re-verify before relying on these long-term, fee schedules move):

*eBay:*
| Component | Value |
|---|---|
| `final_value_fee` | 12.8% (category-dependent, 6.9–14.9% range) |
| `per_order_fee` | £0.30 (fixed, orders under £10) — note: this varies by order value, see open item 1 |
| `regulatory_fee` | 0.4% |
| `promoted_listings` | 3% (midpoint of the 2–4% recommended range, optional/toggleable) |

*Etsy:*
| Component | Value |
|---|---|
| `listing_fee` | £0.16 (fixed, per listing — also tracked separately as a recurring expense, see §3 below) |
| `transaction_fee` | 6.5% |
| `payment_processing` | 4% + £0.20 fixed |
| `regulatory_fee` | 0.32% |
| `vat_on_fees` | 20% (applies since the business isn't VAT-registered) |
| `offsite_ads` | 15% (optional/conditional — only applies if triggered, see the Etsy guide for the $10,000 trailing-revenue threshold) |

### `wp_som_order_platform_fees`
Actual per-order fee line items, pulled via API sync — this is the real, realized data.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `order_id` | INT FK → orders.id | |
| `channel_id` | INT FK → channels.id | |
| `fee_type` | VARCHAR(50) | matches the channel's own fee type naming where possible (e.g. eBay's `FINAL_VALUE_FEE`, `FINAL_VALUE_FEE_FIXED_PER_ORDER`; Etsy's transaction/processing fee labels from the ledger) |
| `amount` | DECIMAL(10,4) | |
| `currency` | CHAR(3) DEFAULT 'GBP' | |
| `raw_payload` | TEXT NULL | original API response fragment, kept for debugging |
| `synced_at` | DATETIME | |
| `created_at` | DATETIME | |

### `wp_som_recurring_platform_expenses`
Platform costs not tied to a specific order — primarily Etsy's per-listing fee, which is charged on initial listing, on 4-month renewal, and again per extra quantity sold on a multi-quantity listing, regardless of whether that specific event was a sale.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `channel_id` | INT FK → channels.id | |
| `listing_id` | INT FK → listings.id NULL | which listing this charge relates to, where known |
| `fee_type` | VARCHAR(50) DEFAULT 'listing_fee' | |
| `amount` | DECIMAL(10,4) | |
| `incurred_date` | DATE | |
| `notes` | TEXT NULL | |
| `created_at` | DATETIME | |

## Migration notes

- New tables via `dbDelta()`, consistent with existing plugin convention.
- Bump `som_db_version` and add the corresponding migration step.
- Seed `channel_fee_estimates` with the values in the table above for the `ebay` and `etsy` channel rows on migration — gives working defaults from day one rather than an empty config screen, while remaining fully editable.
