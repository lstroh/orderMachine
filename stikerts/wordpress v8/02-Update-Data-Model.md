# Update Package 7 — Data Model Changes

*Baseline: plugin **v0.32.2**. No materials on workflow templates.*

---

## A. Shipping package materials (package BOM)

### `wp_som_shipping_package_materials` (new)

Same pattern as `wp_som_product_materials`, but owned by a shipping package.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `shipping_package_id` | BIGINT UNSIGNED NOT NULL | FK → `shipping_packages` |
| `material_id` | BIGINT UNSIGNED NOT NULL | FK → `materials` |
| `quantity_per_package` | DECIMAL(10,2) NOT NULL | Qty when **this** package is used on an order |
| `created_at` / `updated_at` | DATETIME | |

**Constraint:** `UNIQUE (shipping_package_id, material_id)`.

**Examples**

| Package | Material | Qty |
|---|---|---|
| Small (1× 4-pack) | Thank-you cardstock | 1 |
| Small (1× 4-pack) | C5 envelope | 1 |
| Large (2+ packs) | Thank-you cardstock | 1 |
| Large (2+ packs) | Large letter box | 1 |

---

## B. Not in this package

| Idea | Status |
|---|---|
| `workflow_pack_materials` | **Dropped** — workflows stay steps-only |
| Extra columns on `workflow_templates` for materials | **No** |

---

## C. Stock log reasons

| Reason | Meaning |
|---|---|
| `new_order` | Existing — product recipe reserve |
| `package_reserve` | Package BOM reserve for this order’s selected package |
| `order_usage_extra` | Existing — Materials used extras (recipe **or** package lines) |

Cancel reversal (future): reverse `new_order` + `package_reserve` + extras.

---

## D. Order package selection (application + existing column)

Orders already have `shipping_package_id` (selected package before Ship).

**No new table required** for “1 pack → package A, 2+ → package B” if we use:

- Product `package_id` = default for **qty 1** (already exists), and  
- New optional product field **or** site/package rules for multi-qty (see feature doc open items).

Candidate column (only if product-level multi package is chosen):

| Column | Type | Notes |
|---|---|---|
| `products.package_id_multi` | BIGINT UNSIGNED NULL | Suggested package when sellable line qty ≥ 2 (or order total packs ≥ 2) |

Prefer deciding the rule in `03` before adding columns.

---

## E. Materials used derivation

No denormalised usage table in v1. Derive:

| Source | Planned |
|---|---|
| Product recipe | abs(`new_order`) |
| Shipping package | abs(`package_reserve`) |

Actual = planned + sum(`order_usage_extra`) for that material (or separate rows by source — see feature doc).

---

## F. Open items (data)

1. `package_id_multi` on product vs order-total heuristic vs manual-only — **see `03`**.  
2. Schema bump target: **1.17.0** at S2 kickoff (confirm live `som_db_version`).  
3. Package change: reverse prior `package_reserve` then reserve new — **yes**.  
