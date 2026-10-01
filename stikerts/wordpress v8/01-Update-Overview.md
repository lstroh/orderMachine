# Plugin Update Package 7 — Overview

*Update set · Shipping-package materials (thank-you, mailers) + Make step gate / confirmation UX. Self-contained — assumes Update Packages 1–6 are built and working.*

---

## Assumption

- Per-line **make** + order-level **Pack** workflow (Package 6).
- **Product recipes** reserve on create; order **Materials used** is increase-only overuse.
- **Shipping packages** today = dims / tare / planned postage + product default suggestion — **no** stocked materials.
- Pack **workflow** = steps/gates only (checklists, Ship, etc.).
- Multipack SKUs are separate products (e.g. one sold unit = one 4-pack).

Baseline: plugin **v0.32.2**.

## Settled product decisions (locked)

| Topic | Decision |
|---|---|
| Where packaging materials live | On the **shipping package** (same idea as product → recipe) |
| Pack / make workflows | **Steps only** — no material BOM on workflows |
| Package choice | Based on the **order** (e.g. **1×** 4-pack → package A; **2+** packs → package B) |
| Actual usage | Order detail **Materials used** — planned vs actual, like product recipe materials |
| Thank-you cardstock | Belongs on the **package** materials list (qty per that package), not on the product recipe |

## What's in this update

1. **Shipping package materials** (`03-Update-Package-Materials.md`) — recipe-like rows on each package; reserve when the order’s package is set; Materials used on the order.
2. **Order packaging selection** — suggest/select package from order contents (1 pack vs 2+); materials follow the selected package.
3. **Make step gate & confirmation UX** (`04-Update-Make-Step-Gate-UX.md`) — BUG-003.
4. **Data model** (`02-Update-Data-Model.md`).

## How this mirrors today

| Today (make) | Package 7 (pack/ship) |
|---|---|
| Product → material recipe | Shipping package → material recipe |
| Workflow = production steps | Pack workflow = pack/ship steps only |
| Order reserves recipe × qty | Order reserves **package materials** (fixed per selected package) |
| Materials used on order | Same panel / same increase-only Actual |

## Recommended build order

1. **UP7-S1** — BUG-003 / confirmation UX  
2. **UP7-S2** — Package materials + reserve + Materials used  
3. **UP7-S3** — Order-based package suggestion (1 vs 2+) + seed/docs  

## Out of scope

- Materials on Pack workflow templates  
- Putting thank-you on product recipes as the solution  
- Decrease below planned  
- Multi-carton / split shipments  

## Files

1. `01-Update-Overview.md` (this file)  
2. `02-Update-Data-Model.md`  
3. `03-Update-Package-Materials.md`  
4. `04-Update-Make-Step-Gate-UX.md`  
5. `Update-7-Sprint-Plan.md`  
6. `05-Update-Cursor-Prompt.md`  
