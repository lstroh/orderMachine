# Update — Multipacks (Separate SKUs)

*Package 5 · No schema. Conventions only. Self-contained.*

---

## 1. What this is

**Not** a new multipack engine. Confirms how to model pack sizes in Order Machine today and after shipping fields land.

A **multipack** = N identical units sold as one listing/SKU (e.g. bin sticker **4-pack**).  
A **bundle/kit** of different products is out of scope.

## 2. Settled approach

| Topic | Decision |
|---|---|
| Catalogue shape | **Separate product SKUs** per pack size (1-pack, 4-pack, 10-pack, …) |
| Stock / materials | Each SKU has its **own recipe** (qty per unit already scales materials) |
| Shipping | Each SKU has its own goods weight, default package, planned postage |
| Base × multiplier link | **Deferred** (`base_product_id` / `units_per_pack` not in Package 5) |

## 3. Operator conventions

Recommended naming:

- Name: `Bin Sticker Set — 4-pack` / SKU: `BIN-SET-4PK` (existing seed style).
- Recipe: materials for **one sold unit** of that pack (already how recipes work).
- Heavier packs → higher `weight_grams` and often a larger `package_id` / higher `planned_shipping_gbp`.

Do **not** create a phantom “loose unit” SKU unless you actually sell singles.

## 4. Optional seed / docs

| Deliverable | Purpose |
|---|---|
| USER-GUIDE / USER-REFERENCE short note | “Pack sizes = separate products” |
| FEATURES-AND-TESTING note | Same |
| Optional second seed SKU | Only if useful for wp-env demos — **open item** (recommend skip unless trivial) |

## 5. Out of scope

- Inventory explosion from pack → base units
- Channel listing “variations” as the multipack mechanism inside SOM
- Auto-creating N singleton orders for a pack
- Google Merchant `multipack` feed attribute export

## 6. Open items

1. Add a second seeded pack SKU in dummy catalogue? (recommend no)
2. Future package: link pack SKU → base unit for shared costing — park only
