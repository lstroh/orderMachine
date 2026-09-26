# Update Package 5 — Data Model Changes

*Self-contained schema delta for Package 5. Baseline at planning: `som_db_version` **1.12.0** (plugin v0.27.0). Multipack conventions need no schema.*

---

## Migration notes (all features)

- Apply via existing `SOM_DB::create_tables()` / `dbDelta` + version bump pattern in `includes/class-som-db.php`.
- Bump `SOM_DB::DB_VERSION` (suggest **1.13.0** when the first schema sprint lands; further bumps **1.14.0**… if sprints split schema).
- Additive only: do not drop columns/tables.
- Keep GBP / decimal conventions already used elsewhere.
- Weight unit for v1: **grams** (integer or decimal) — UK shop; display may show g. Package dimensions: **millimetres** (outer). Document conversion if operators think in cm.

---

## A. Order Notes

### A1. New table `wp_som_order_notes`

Append-only **threaded log** of operator comments on an order.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `order_id` | BIGINT UNSIGNED NOT NULL | FK → orders.id |
| `user_id` | BIGINT UNSIGNED NOT NULL | WP user who created the note |
| `body` | TEXT NOT NULL | Plain text; trimmed non-empty |
| `created_at` | DATETIME NOT NULL | UTC/GMT consistent with other SOM tables |
| `updated_at` | DATETIME NULL | Only if edit-in-place allowed (see open items); else omit or keep = created_at |

**Indexes:** KEY `order_id` (+ `created_at` for ordering); optional KEY `user_id`.

**Rules (application):**

- Create requires `manage_options` (same as rest of SOM admin).
- List on order detail newest-last or newest-first — **open item** (recommend chronological ascending, newest at bottom).
- **Not** exposed on REST order payloads or MCP Abilities in v1.
- Soft-delete / hard-delete: **open item** (recommend no delete in v1, or delete own note only within short window).

---

## B. Shipping packages & planned postage

### B1. New table `wp_som_shipping_packages`

Reusable outer packaging (mailers, boxes).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `name` | VARCHAR(100) NOT NULL | Unique display name, e.g. “C5 bubble mailer” |
| `length_mm` | INT UNSIGNED NOT NULL | Outer length |
| `width_mm` | INT UNSIGNED NOT NULL | Outer width |
| `height_mm` | INT UNSIGNED NOT NULL | Outer height; 0 allowed for flat envelope |
| `tare_weight_grams` | DECIMAL(10,2) NOT NULL DEFAULT 0 | Empty package weight |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | |
| `is_default` | TINYINT(1) NOT NULL DEFAULT 0 | At most one default for new products — enforce in app |
| `created_at` / `updated_at` | DATETIME | |

**Indexes:** UNIQUE `name`; KEY `is_active`.

### B2. Alter `wp_som_products`

| Column | Type | Notes |
|---|---|---|
| `weight_grams` | DECIMAL(10,2) NULL | Goods weight (item / pack contents), not including package tare |
| `package_id` | BIGINT UNSIGNED NULL | FK → shipping_packages.id; default package when this SKU ships alone |
| `planned_shipping_gbp` | DECIMAL(10,2) NULL | Flat expected postage for this SKU (planning / costing) |

**Indexes:** KEY `package_id`.

**Rules:**

- Internal products may leave shipping fields null (production jobs usually don’t need postage); optional still allow package/weight for component logistics — **open item** (recommend optional / unused for Internal channel orders).
- Multipack SKUs are normal products: each pack size has its own weight / package / planned postage.

### B3. Alter `wp_som_orders`

| Column | Type | Notes |
|---|---|---|
| `planned_shipping_gbp` | DECIMAL(10,2) NULL | Expected postage for **this** order; editable on order detail |

**Seed on create (application logic):**

```
If order channel is internal → leave planned_shipping null (or 0) — open item
Else → sum of (line product.planned_shipping_gbp × qty) for matched lines
     → or primary product only — open item (recommend sum × qty)
```

Changing product defaults later does **not** rewrite existing orders’ planned shipping.

### B4. Actual postage (existing — no change)

`wp_som_shipments.postage_paid` remains the **actual** cost when the order is shipped. One shipment row per order today (`UNIQUE order_id`).

**Variance (UI/query only):** `planned_shipping_gbp` vs `postage_paid` when shipment exists.

### B5. Costing / analytics impact (no new tables)

| Surface | Behaviour |
|---|---|
| Product Costing | Include `planned_shipping_gbp` as a cost line alongside materials + platform fees when set |
| Order profit helpers | **Open item:** subtract actual postage when shipment exists; else planned; or leave order profit unchanged in v1 and only show variance on order detail |
| Analytics charts | **Open item:** whether to fold postage into profit series in this package (recommend order-detail variance first; analytics later) |

---

## C. Multipacks

**No schema.** Each pack size = separate `products` row (existing). Optional future: `base_product_id` + `units_per_pack` — **explicitly out of Package 5**.

---

## D. Explicitly out of schema scope

- Live carrier / eBay Logistics / Etsy calculated-shipping API integration
- Buyer-paid shipping amount from channel payloads
- Multi-parcel orders (still one shipment row)
- Bin-packing multiple SKUs into a chosen package at quote time
- Product “item dimensions” separate from package dimensions
- REST/MCP notes or shipping package CRUD

---

## Open items (data model)

1. Note edit/delete policy and whether `updated_at` is needed.
2. Order planned shipping seed: sum of lines × qty vs primary product only.
3. Internal / production orders: null planned shipping always?
4. Order profit / Analytics: include actual or planned postage in v1?
5. Weight/dimension column precision and display units (g + mm vs also show cm).
6. Soft `is_default` package uniqueness (partial unique index vs app-only).
