# Update — Internal Products (Make-to-Stock Components)

*Package 4 · Schema in `02-Update-Data-Model.md` §C. Self-contained.*

---

## 1. What this adds

**Internal products** are makeable components: they have their own **recipe** and **workflow**, and they produce stock of a linked **material** that other products (sellable or internal) can consume in recipes.

Examples: company logo sticker, generic thank-you insert (non-personalized), packing card.

They are **never** sold on eBay/Etsy. Personalized thank-you cards that vary per order **keep** the existing `thank_you_card` batch step — this feature does not replace that.

**v1 mode:** make-to-stock (produce ahead). **Per-order auto-make** is explicitly deferred.

## 2. Settled rules

| Topic | Decision |
|---|---|
| Mode | Make-to-stock |
| Start a run | **Manual Produce N** and/or **low stock** on the output material |
| Nesting | Allowed (with cycle detection) |
| Marketplace | Never listed |
| Orders UI | **Same** orders list + Orders Board |
| Thank-you batch | Keep |
| Budgets on customer sale | Consuming the linked material **funds** that material budget (existing path) |
| Per-order make | Future |

## 3. Domain model

```
Internal product P
  ├── recipe → input materials (raw and/or other component materials)
  ├── workflow_template_id → production steps
  └── linked_material_id → output material M (stock increased on job complete)

Sellable product S
  └── recipe may include M (and raw materials)
```

Creating/editing an internal product should ensure material M exists (auto-create named after the product if needed) and stays 1:1 with P.

## 4. Production jobs

### Create

**Produce N** (from product edit, materials low-stock UI, or materials list action):

1. Create an order discriminated as production (synthetic internal channel and/or `order_kind = production` — see data-model open items).
2. One line: product P, quantity N.
3. Reserve **input** materials: recipe × N → `new_order` stock log (same helper as channel sync create).
4. Assign P’s workflow; appear on Orders list + Board like any open order.
5. Skip marketplace fee expectations; do not push tracking to eBay/Etsy.

### Complete

When the production order’s workflow completes:

1. Credit output material M by **+N**.
2. Stock-log reason `production_output`; unit cost from total input consumption cost / N (see open item on WA integration).
3. Order shows as complete on the list/Board (existing complete behaviour).

### Low stock

When M is at/below low-stock threshold:

- **v1 recommendation:** prominent **Produce** affordance (and optional admin notice), not fully silent auto-create — confirm open item (auto-draft vs prompt only).

## 5. Nesting & validation

- Saving a recipe that would make P consume M (its own output) directly or indirectly → reject with error.
- Internal products cannot be assigned marketplace listings (block on listing create/edit + hide from listing product picker).
- Internal products **can** appear in other products’ recipe material pickers **via their linked material** (materials list), not as “product-in-recipe”.
- Deactivating an internal product: do not delete M (history/stock); block deactivate if open production jobs exist — **open item**.

## 6. Funding & costing

| Event | Stock | Budget |
|---|---|---|
| Start production | Inputs ↓ (`new_order`) | **Open item:** fund input material budgets like a sale, or skip sale_funding for production |
| Complete production | Output M ↑ (`production_output`) | No customer funding |
| Customer order uses M | M ↓ (`new_order`) | Fund M’s material budget (existing) |
| Overuse of M on customer order | Extra ↓ | Extra funding (feature 04) |

Product Costing for sellable SKUs already sums recipe materials at WA — once M has WA/unit cost from production output, margins reflect component cost without a special case.

## 7. UI requirements

| Page | Purpose |
|---|---|
| Products list | Badge/filter **Internal** vs sellable |
| Product create/edit | Toggle Internal; when on: require workflow + manage linked material; hide target/listing-centric noise where helpful; **Produce N** button |
| Materials | Indicate “Made in-house” when linked; Produce action when low |
| Orders list / Board | Badge or channel label **Production**; filters include/exclude production |
| Order detail | Same workflow UI; show production qty / output material note; step instructions (feature 03) |
| Listings | Product picker excludes internal |

## 8. Seed / fixtures (wp-env)

Optional but valuable: one internal product (e.g. “Logo sticker”) with a tiny workflow, linked material, and a sellable recipe line consuming it — so Produce N → complete → customer sync path is testable.

## 9. Out of scope

- Per-order automatic component jobs
- Replacing thank-you batch / personalized cards
- Selling internal products on channels
- Separate Production admin app
- Multi-output products (one job → many materials)

## 10. Open items

1. Production discrimination: synthetic `internal` channel vs `orders.order_kind`.
2. Produce N = **one order qty N** (recommended) vs N singleton orders.
3. Low stock: prompt/affordance only vs auto-draft production order (qty = ?).
4. Production start: fund input material budgets or not?
5. Output costing / WA update details.
6. `materials.source_product_id` column vs derive from products.
7. Deactivate/delete rules when open jobs or stock remain.
8. Should production orders appear in Analytics revenue/profit charts? (**Recommend exclude** from sales/AOV; optional separate “production” metrics later.)
9. Max nesting depth for cycle check.
