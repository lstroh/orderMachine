# Update Package 7 — Data Model Changes

*Self-contained schema delta for Package 7. Baseline at planning: plugin **v0.32.2** (Package 6 complete).*

---

## A. Pack workflow materials (order-level BOM)

### `wp_som_workflow_pack_materials` (new)

Materials required **once per order pack** for a Pack template (not multiplied by line qty).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `workflow_template_id` | BIGINT UNSIGNED NOT NULL | FK → `workflow_templates` (kind=`pack`) |
| `material_id` | BIGINT UNSIGNED NOT NULL | FK → `materials` |
| `quantity_per_pack` | DECIMAL(10,2) NOT NULL | Fixed qty when this pack workflow runs |
| `created_at` / `updated_at` | DATETIME | |

**Constraint:** `UNIQUE (workflow_template_id, material_id)`.

**Example:** Pack template “Order Pack & Ship” → thank-you cardstock `1.0`, seal sticker `1.0`.

Make templates (`kind=make`) must **not** use this table (reject on save).

---

## B. Shipping package materials (optional)

### `wp_som_shipping_package_materials` (new)

Materials consumed when a specific outer package is **selected** on the order.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `shipping_package_id` | BIGINT UNSIGNED NOT NULL | FK → `shipping_packages` |
| `material_id` | BIGINT UNSIGNED NOT NULL | FK → `materials` |
| `quantity_per_package` | DECIMAL(10,2) NOT NULL | Usually `1` (one mailer/box) |
| `created_at` / `updated_at` | DATETIME | |

**Constraint:** `UNIQUE (shipping_package_id, material_id)`.

**Example:** Package “C5 Board Backed Envelope” → material “C5 Envelope” qty `1`.

---

## C. Stock log reasons

Extend `material_stock_log.reason` (or document string reasons already free-form) with:

| Reason | Meaning |
|---|---|
| `pack_reserve` | Pack-template materials reserved for this order |
| `package_reserve` | Shipping-package materials reserved when package selected |
| `order_usage_extra` | Existing — extras from Materials used (recipe **or** pack/package lines) |

**Open:** whether pack/package reserves use distinct reasons (recommended) or reuse `new_order` with a `source` column. Prefer **distinct reasons** so Materials used can attribute Planned by source without a denormalised usage table.

Cancel reversal (future): must reverse `new_order` + `pack_reserve` + `package_reserve` + extras (same rule as Package 4 overuse).

---

## D. No denormalised usage table (v1)

Same recommendation as Package 4: derive planned/actual from stock log:

| Source | Planned | Extra |
|---|---|---|
| Product recipe | abs(`new_order`) | `order_usage_extra` for that material |
| Pack template | abs(`pack_reserve`) | same extras bucket (or split — open) |
| Shipping package | abs(`package_reserve`) | same |

If UI needs a **Source** column, join/log metadata is enough; add `wp_som_order_material_usage` only if dual-source pain appears.

---

## E. Product Costing (optional follow-on)

No schema required for v1 if costing stays recipe-only. Optional later: include default pack materials + default package materials as a “packaging” cost line on Product Costing (application math only).

---

## F. Open items (data)

1. Package change after reserve: reverse old `package_reserve` and write new? **Recommend yes.**  
2. Pack template change mid-flight: leave existing reserves (like recipe); no rewrite.  
3. Same material on recipe + pack + package: one Materials used row with combined planned, or separate rows by source? **Recommend separate rows by source** for clarity.  
4. Schema version bump target: **1.17.0** (confirm against live `som_db_version` at kickoff).  
