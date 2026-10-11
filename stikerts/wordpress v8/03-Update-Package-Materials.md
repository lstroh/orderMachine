# Update — Shipping Package Materials

*Package 7 · Schema in `02-Update-Data-Model.md`. Self-contained.*

---

## 1. What this adds

Packaging materials are defined **on the shipping package**, the same way make materials are defined **on the product**.

1. Each shipping package has a **material recipe** (thank-you, envelope, box, seal sticker, …).  
2. The **order** gets a package (suggested from how many packs are on the order; operator can change).  
3. Stock reserves from that package’s recipe.  
4. Order **Materials used** shows planned vs actual (same behaviour as product recipe materials).

**Pack workflow stays steps only** — no material list on the workflow template.

## 2. Settled rules (operator)

| Topic | Decision |
|---|---|
| Where to define packing materials | **Per shipping package** |
| Workflows | **Steps only** |
| Package vs order shape | **1× pack** (e.g. one 4-sticker set) → one package; **2+ packs** → a different (usually larger) package |
| Actual usage | Order detail Materials used, like products |
| Thank-you | On the package BOM (typically qty 1 per package), not on the product recipe |

## 3. Mirror of today’s product model

| Product path | Package path |
|---|---|
| Products → edit → recipe rows | Shipping packages → edit → material rows |
| `quantity_per_unit` × line qty | `quantity_per_package` once for the selected package |
| Reserve on order create (`new_order`) | Reserve when package is set (`package_reserve`) |
| Materials used → raise Actual | Same panel / same increase-only rules |

## 4. Package selection on the order

**Intent:** package follows the **order** (one outbound ship-together), not the Pack workflow and not one package per line.

### Single-product orders

| Order shape | Expected package |
|---|---|
| One sold pack unit (e.g. qty **1** of a 4-pack SKU) | Product `package_id` (small / single) |
| Two or more of the **same** product (qty **≥ 2**) | Product `package_id_multi` (larger) |

### Mixed-product orders (different SKUs on one order)

Still **one** shipping package for the whole order (Package 6 ship-together).

| Step | Rule (soft default) |
|---|---|
| 1. Total sellable qty | Sum of quantities on matched non-internal lines |
| 2. Pick candidate per line | If **order total qty = 1**: that line’s `package_id`. If **total ≥ 2**: each line’s `package_id_multi` if set, else that line’s `package_id` |
| 3. Agreement | If all candidates are the **same** package → suggest it |
| 4. Conflict | Products disagree → suggest **site default** package and flag in Pack UI (“Mixed products — confirm package”) |
| 5. Override | Operator always chooses final `orders.shipping_package_id` on Pack panel |

**Make / Pack / materials still compose cleanly:**

| Concern | Mixed-order behaviour |
|---|---|
| Make | Each line runs **its product’s** make workflow |
| Pack steps | One site Pack workflow for the order |
| Make materials | Each line’s product recipe × that line’s qty |
| Shipping materials | **Once**, from the **selected** package BOM |
| Planned postage | Existing sum of product planned shipping × qty (unchanged) |

### Test orders (multi-line)

Create test order must support **several product lines** (product + qty + optional price/personalisation per row) so mixed and multi-qty cases are easy to exercise without channel sync. Same create path as today (`create_from_external`).

## 5. Behaviour

### Package admin

- On package edit: repeatable material rows (material + qty), same UX pattern as product recipe.  
- Reject duplicate materials on one package.

### Reserve

- When `shipping_package_id` is first set: write `package_reserve` for each package material; fund material budgets.  
- Idempotent per order + package.  
- On package change: reverse previous package reserves, reserve new.  
- Skip for history import / cancelled / Internal Produce N (no pack path).  
- If no package selected yet: no package materials reserved (Materials used shows recipe-only until package set).

### Materials used

| Column | Content |
|---|---|
| Source | Recipe / Package |
| Material | Name + unit |
| Planned | From `new_order` or `package_reserve` |
| Actual | ≥ planned; editable up |
| Extra | Actual − Planned |

Same save path as today’s overuse (`order_usage_extra` + Extra material usage funding).

### Pack checklist

“Thank-you included” remains a **process** tick. Stock comes from the package BOM.

## 6. UI requirements

| Page | Purpose |
|---|---|
| Shipping packages → edit | Material recipe editor |
| Products → edit | Default package + multi package suggestion (S3) |
| Order Pack panel | Selected package (required before Ship) — materials follow it |
| Order Materials used | Recipe + Package sources |
| Seed | Small + large sample packages with thank-you / envelope materials |

## 7. Out of scope

- Materials on Pack workflow templates  
- Auto PDF consuming stock  
- Decrease below planned  
- Multi-carton  

## 8. Open items

1. Multi-pack suggestion: `package_id_multi` on product vs site-level rules. **Default:** product fields.  
2. Multi rule: **sum of sellable qtys** (settled soft default).  
3. Mixed-product package conflict: site default + UI flag (settled soft default above).  
4. Reserve timing: when `shipping_package_id` is saved (including auto-suggest on create if set).  
5. Product Costing: include default package materials? **Default:** defer or optional S3.  
6. Same material on recipe and package: separate Materials used rows by Source.  
7. Test order UI: max lines / whether unit price required per row — **Default:** up to ~5 addable rows; price optional.  
