# Data Model — Order Management Plugin

*Detailed design, part 1 of 4 · Builds on `Order-Management-Requirements.md` (v1.0) · Target: native WordPress plugin, custom MySQL tables via `$wpdb` (not custom post types — this data is relational and query-heavy, a poor fit for the postmeta model).*

All tables prefixed `wp_som_` (Sticker Order Manager) to avoid collisions with WordPress core tables. Adjust prefix to match your site's actual `$wpdb->prefix` at install time.

---

## 1. Entity overview

```
channels ──┬── listings ──── products ──── product_materials ──── materials
           │                    │
           └── orders ──────────┤
                 │               └── workflow_templates ── workflow_steps
                 ├── order_items
                 └── order_step_progress ── workflow_steps
                        │
                 material_stock_log
```

## 2. Tables

### `wp_som_channels`
One row per connected sales channel.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `slug` | VARCHAR(20) | `ebay`, `etsy`, `amazon` (unused until v2) |
| `display_name` | VARCHAR(50) | |
| `is_active` | TINYINT(1) | toggles polling on/off without deleting config |
| `credentials` | TEXT | encrypted JSON — OAuth tokens, refresh tokens, expiry (see API-Integration.md for what goes here) |
| `last_synced_at` | DATETIME NULL | last successful order pull |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_materials`
Raw stock — vinyl sheets, laminate sheets, envelopes, etc.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `name` | VARCHAR(100) | e.g. "A4 Glossy White Vinyl" |
| `unit` | VARCHAR(20) | e.g. "sheet", "pack" |
| `current_stock` | DECIMAL(10,2) | can go negative — that's a real signal (oversold), not an error state to block on |
| `low_stock_threshold` | DECIMAL(10,2) NULL | optional warning trigger |
| `unit_cost` | DECIMAL(10,4) NULL | optional, feeds cost reporting later — not required for v1 function |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_products`
Your internal product catalogue (bin stickers, name labels, decals...) — distinct from marketplace listings, since one product can map to listings on both eBay and Etsy.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `name` | VARCHAR(100) | e.g. "Bin Sticker Set — 100x140mm 4-pack" |
| `sku` | VARCHAR(50) NULL | your own internal reference, optional |
| `workflow_template_id` | INT FK → workflow_templates.id | which workflow this product's orders follow — many products can point at the same template |
| `is_active` | TINYINT(1) | |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_product_materials`
The fixed material recipe per product (per requirements §6.10 — per-product, not per-variant, editable over time).

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `product_id` | INT FK → products.id | |
| `material_id` | INT FK → materials.id | |
| `quantity_per_unit` | DECIMAL(10,2) | e.g. 1.0 sheet vinyl, 1.0 sheet laminate, 0.25 sheet cardstock (backing card) |

A product typically has 2+ rows here (vinyl + laminate, at minimum).

### `wp_som_listings`
The marketplace-facing side — one row per (product × channel) that's actually listed.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `product_id` | INT FK → products.id | |
| `channel_id` | INT FK → channels.id | |
| `external_listing_id` | VARCHAR(100) | eBay item ID / Etsy listing ID |
| `price` | DECIMAL(10,2) | last known — cached from platform, editable and pushed back |
| `quantity_available` | INT | same — cached + pushable |
| `last_synced_at` | DATETIME NULL | |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_workflow_templates`
Reusable named workflows (e.g. "Bin Sticker Production", "Name Label Production").

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `name` | VARCHAR(100) | |
| `description` | TEXT NULL | |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_workflow_steps`
Ordered steps belonging to a template.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `workflow_template_id` | INT FK → workflow_templates.id | |
| `step_order` | INT | 1, 2, 3... determines sequence |
| `name` | VARCHAR(100) | e.g. "Print", "Laminate — ink dry", "Cut", "Pack", "Ship (Click & Drop)", "Send thank-you card", "Review reminder" |
| `requires_manual_confirm` | TINYINT(1) | shows a "done" checkbox |
| `timer_seconds` | INT NULL | if set, step hard-gates for this long before it can be marked done — see Workflow-Engine.md |
| `script_config` | TEXT NULL | JSON — null if no script/API action; otherwise `{ "type": "local" \| "api" \| "n8n", ...type-specific fields }`, see Workflow-Engine.md for schema |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_orders`
One row per order pulled from a channel.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `channel_id` | INT FK → channels.id | |
| `external_order_id` | VARCHAR(100) | eBay/Etsy order ID — used for de-duplication on sync (§6.7 "no duplicate orders") |
| `order_date` | DATETIME | |
| `buyer_name` | VARCHAR(150) | |
| `shipping_address` | TEXT | store as JSON blob (line1/2, city, postcode, country) — simplest for a single-user internal tool, avoids a rigid schema fighting varied address formats across channels |
| `current_step_id` | INT FK → workflow_steps.id NULL | null once workflow complete, or if order predates a workflow assignment |
| `is_complete` | TINYINT(1) | true once final step done |
| `raw_payload` | TEXT NULL | original API response JSON, kept for debugging/re-sync — cheap insurance |
| `created_at` / `updated_at` | DATETIME | |

**Uniqueness constraint:** `UNIQUE (channel_id, external_order_id)` — this is what actually enforces "no duplicate orders" at the DB level, not just application logic.

### `wp_som_order_items`
Line items within an order — one order can contain multiple products/quantities.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `order_id` | INT FK → orders.id | |
| `product_id` | INT FK → products.id NULL | null if the item couldn't be matched to a known product (flag for manual review — see Open Items below) |
| `quantity` | INT | |
| `personalisation_text` | TEXT NULL | bin number / child's name / custom text — the buyer-entered detail that's easy to miss (§6.4) |
| `unit_price` | DECIMAL(10,2) NULL | |

### `wp_som_order_step_progress`
Tracks each order's progress through its assigned workflow, step by step — this is the actual state machine data.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `order_id` | INT FK → orders.id | |
| `workflow_step_id` | INT FK → workflow_steps.id | |
| `status` | ENUM | `pending`, `in_progress`, `waiting_timer`, `waiting_script`, `error`, `done` |
| `timer_ends_at` | DATETIME NULL | set when a timer step starts |
| `retry_count` | INT DEFAULT 0 | for script/API steps |
| `last_error` | TEXT NULL | |
| `started_at` / `completed_at` | DATETIME NULL | |

One row created per (order, step) pair when an order is assigned its workflow — see Workflow-Engine.md for the full state machine.

### `wp_som_material_stock_log`
Audit trail for every stock change — required to make "auto-decrement on new order" (§6.11) reversible/debuggable, and to support the cancellation/refund reversal flagged as an open item.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `material_id` | INT FK → materials.id | |
| `order_id` | INT FK → orders.id NULL | null for manual stock-take adjustments |
| `change_qty` | DECIMAL(10,2) | negative for consumption, positive for restock/reversal |
| `reason` | VARCHAR(50) | `new_order`, `manual_adjustment`, `order_cancelled`, `restock` |
| `created_at` | DATETIME | |

---

## 3. Key relationships, in plain terms

- A **product** has a fixed **material recipe** and one **workflow template**.
- A **workflow template** is reusable — many products can share one.
- An **order** arrives from a **channel**, contains one or more **order items**, each pointing at a **product**.
- The moment an order is created, its material recipe is looked up (via each item's product) and `material_stock_log` rows are written, decrementing `materials.current_stock` — this is the "new order reduces material count" rule (§6.11).
- The order is simultaneously assigned its workflow (via its product's `workflow_template_id`) and `order_step_progress` rows are created for every step in that template, all starting `pending`.
- A **listing** is the bridge between a product and how it appears on a specific channel — separate from orders entirely, used only for the inventory/price-push functionality (§6.12–13).

## 4. Open items to resolve before/during build

- **Multi-product orders with mixed workflows:** if an order contains two different products with two different workflow templates, does the order track two parallel step-progress sets, or does this need a "primary product per order" simplification for v1? Worth a quick decision before building `order_step_progress`.
- **Unmatched order items:** if a channel's line item can't be automatically matched to a `products` row (e.g. a new listing you haven't mapped yet), the item should still be saved with `product_id = NULL` and flagged in the UI, rather than silently dropped or blocking the whole order sync.
- **Cancellation/refund stock reversal:** flagged in the requirements doc as needing detailed design — likely a channel-triggered event (order status change to cancelled) writing a positive `material_stock_log` row, but the *trigger* for detecting a cancellation depends on what each channel's API actually returns (see API-Integration.md).
