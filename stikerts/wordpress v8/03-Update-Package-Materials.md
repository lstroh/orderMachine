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

**Intent:** package follows the order, not the Pack workflow.

| Order shape | Expected package |
|---|---|
| One sold pack unit (e.g. qty **1** of a 4-pack SKU) | Small / single package |
| Two or more pack units (qty **≥ 2**, or multiple pack lines) | Larger / multi package |

**Soft default (overturn before S3):**

- Keep product `package_id` as the **single-pack** suggestion (qty 1).  
- Add product `package_id_multi` (or equivalent) as the suggestion when that line’s qty ≥ 2, or when **total sellable pack qty** on the order ≥ 2.  
- On create / pack bind: pre-select suggestion into `orders.shipping_package_id` (already used before Ship).  
- Operator can still change package on Pack panel; materials reverse/re-reserve on change.

**Open:** exact multi rule = per-line qty vs sum of all sellable qty (recommend **sum of sellable line qtys**).

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

1. Multi-pack suggestion: `package_id_multi` on product vs site-level rules.  
2. Multi rule: sum of sellable qtys vs primary line only.  
3. Reserve timing if package pre-selected on create vs only when operator confirms on Pack. **Default:** reserve when `shipping_package_id` is saved (including auto-suggest on create if set).  
4. Product Costing: include default package materials? **Default:** defer or optional S3.  
5. Same material on recipe and package: separate Materials used rows by Source.  
