# Update Package 6 — Data Model Changes

*Self-contained schema delta for Package 6. Baseline at planning: `som_db_version` **1.14.0** (plugin v0.29.1).*

---

## Migration notes (all features)

- Apply via existing `SOM_DB::create_tables()` / `dbDelta` + version bump in `includes/class-som-db.php`.
- Bump `SOM_DB::DB_VERSION` (suggest **1.15.0** when the first schema sprint lands; further bumps **1.16.0**… if sprints split schema).
- Additive only: do not drop columns/tables in v1 (legacy order-level progress rows may remain for history).
- GBP / grams / mm conventions unchanged from Package 5.

---

## A. Per-line make progress

Today `wp_som_order_step_progress` is **order-level** (one workflow from primary product). Package 6 needs **per-line** make progress for sellable items.

### A1. New table `wp_som_order_item_step_progress` (recommended)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `order_id` | BIGINT UNSIGNED NOT NULL | Denormalised for queries |
| `order_item_id` | BIGINT UNSIGNED NOT NULL | FK → order_items.id |
| `workflow_step_id` | BIGINT UNSIGNED NOT NULL | Step on the **product make** template |
| `status` | ENUM (same as order_step_progress) | Include `waiting_timer`, `waiting_script`, `waiting_batch`, `error`, `done`, etc. |
| `timer_ends_at` | DATETIME NULL | |
| `retry_count` | INT NOT NULL DEFAULT 0 | |
| `last_error` | TEXT NULL | |
| `confirmation_state` | TEXT NULL | JSON if a make step uses confirmations |
| `started_at` / `completed_at` | DATETIME NULL | |
| `created_at` / `updated_at` | DATETIME | |

**Indexes:** UNIQUE (`order_item_id`, `workflow_step_id`); KEY `order_id`; KEY `status`; KEY `workflow_step_id`.

### A2. Order item make status (optional column vs derived)

**Recommendation:** derive **make-complete** in application code:

- Internal product line (`products.is_internal = 1`) → always make-complete / pack-ready.  
- Sellable line → make-complete when every assigned make step is `done` (or item has no workflow → treat as unmatched / not packable for Ship gate — **open item**).

Optional convenience column on `order_items`:

| Column | Type | Notes |
|---|---|---|
| `make_status` | VARCHAR(20) NULL | e.g. `pending`, `in_progress`, `ready`, `n/a` (internal) — cache only if queries need it |

### A3. Legacy order-level progress

Keep `order_step_progress` + `orders.current_step_id` for:

- **Pack workflow** progress (order-level), and/or  
- Historical orders created before Package 6.

**Open item:** whether pack reuses `order_step_progress` exclusively (recommended) while make uses only `order_item_step_progress`.

---

## B. Order pack & ship binding

### B1. Alter `wp_som_orders`

| Column | Type | Notes |
|---|---|---|
| `pack_workflow_template_id` | BIGINT UNSIGNED NULL | Bound Pack template (usually site default at create) |
| `shipping_package_id` | BIGINT UNSIGNED NULL | **Selected** package at pack (required before Ship); not the same as product default |
| `pack_hold_reason` | TEXT NULL | Non-empty ⇒ hold active; clears when hold released |
| `pack_held_at` | DATETIME NULL | |
| `pack_held_by` | BIGINT UNSIGNED NULL | WP user |
| `packed_by_user_id` | BIGINT UNSIGNED NULL | Auto-stamp when pack checklist saved complete |
| `packed_at` | DATETIME NULL | Auto-stamp |

**Indexes:** KEY `pack_workflow_template_id`; KEY `shipping_package_id`.

### B2. Site option for default Pack template

| Option | Notes |
|---|---|
| `som_pack_workflow_template_id` | Default Pack workflow template PK used on new non-internal orders |

Internal / production orders: leave `pack_workflow_template_id` null; never assign pack progress.

### B3. Pack step progress

**Recommendation:** reuse `wp_som_order_step_progress` for steps belonging to `pack_workflow_template_id`, and set `orders.current_step_id` to the **current pack step** once the order is in the pack lane (or always for pack-bound orders).

Make board must **not** use `orders.current_step_id` as the sole make cursor — it reads per-item progress instead.

### B4. Pack checklist state

Reuse confirmation machinery where possible:

- Pack checklist kind (new or extend `packing_items`) stores ticks for **each order line in the pack** + **thank-you included**.
- Persist in `confirmation_state` on the pack confirm step (existing pattern) **or** a dedicated JSON column on orders — **open item** (prefer existing confirmation_state on the pack step).

Auto-stamp `packed_by_user_id` / `packed_at` when checklist first becomes complete (or on each save when complete — **open item**, recommend stamp on first transition to complete; update `packed_at` on later edits optional).

---

## C. Shipment pack weight

### C1. Alter `wp_som_shipments`

| Column | Type | Notes |
|---|---|---|
| `pack_weight_grams` | DECIMAL(10,2) NULL | Optional actual packed weight (goods + tare as weighed); independent of `postage_paid` |

Do **not** auto-fill postage from weight.

---

## D. Workflow template role (application / optional column)

### D1. Optional `workflow_templates.kind`

| Column | Type | Notes |
|---|---|---|
| `kind` | ENUM(`make`,`pack`) NOT NULL DEFAULT `make` | Distinguishes product make templates from the Pack template |

**Open item:** encode kind in column vs convention (name/option only). Recommend column for UI filters and validation (products may only assign `make` templates; site pack option may only point at `kind=pack`).

---

## E. Explicitly out of schema scope

- Multi-shipment / multi-carton tables  
- Insert SKU inventory  
- Barcode scan events  
- Customer tracking tokens  
- Dropping `thank_you_card` batch group row (may remain in DB unused)

---

## Open items (data model)

1. Unmatched sellable lines (null `product_id`): block Ship always?  
2. Pack progress storage: reuse `order_step_progress` vs new pack-only table.  
3. `workflow_templates.kind` column vs convention-only.  
4. Whether to cache `order_items.make_status`.  
5. Packed-at: stamp once vs refresh on every checklist save.  
6. Migration of in-flight open orders that still have old monolithic progress (recommend: leave old rows; new assigns use new model; optional repair tool — **open item**).
