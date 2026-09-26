# Update — Shipping Packages & Planned Postage

*Package 5 · Schema in `02-Update-Data-Model.md` §B. Self-contained.*

---

## 1. What this adds

Operator-facing shipping **planning** data, separate from **actual** postage already stored on shipments:

1. **Package catalogue** — named mailers/boxes with outer dimensions + tare weight.
2. **Product shipping defaults** — goods weight, default package, flat **planned** shipping £.
3. **Order planned shipping** — editable expected postage for this order (seeded from products on create).
4. **Product Costing** — include planned shipping in the cost stack vs target price.

**Actual** postage remains `som_shipments.postage_paid` when you record the shipment (unchanged flow).

## 2. Settled rules

| Topic | Decision |
|---|---|
| Rate source | **Flat / expected** amounts you set — no live eBay Logistics / Etsy calculator API |
| Physical data | **Goods weight** + **package catalogue link** (package owns outer dims + tare) |
| Planned vs actual | Product default → order planned (editable); actual = shipment |
| Buyer-paid shipping | Out of scope (channel “what buyer paid for shipping”) |
| Multi-item packing algorithm | Out of scope (no bin-packing) |

## 3. Domain model

```
shipping_packages (catalogue)
  └── products.package_id → default outer package for this SKU alone
  └── products.weight_grams → goods weight
  └── products.planned_shipping_gbp → flat expected postage

orders.planned_shipping_gbp ← seeded on create from matched product lines; editable

som_shipments.postage_paid → actual (existing)
```

**Shipped weight (informational, optional helper):**  
`goods weight(s) + package.tare` — useful later for labels; not required to drive flat planned £ in v1.

## 4. Behaviour

### Package catalogue admin

- New admin screen or Settings subsection: list / add / edit packages (name, L×W×H mm, tare g, active, default).
- Deactivate rather than hard-delete if products still reference the package (or block delete — **open item**).
- Exactly one `is_default` preferred for new products.

### Product edit

| Field | Notes |
|---|---|
| Goods weight (g) | Optional; required only if you want costing/helpers to use it |
| Default package | Select from active packages |
| Planned shipping (£) | Flat expected postage for selling this SKU |

Internal products: fields optional / hidden — **open item** (recommend hide or leave unused for Internal channel).

### Order create (channel sync / external / test order)

- After items are matched, set `orders.planned_shipping_gbp` from product defaults (see data-model open item: **sum × qty** recommended).
- Internal production orders: leave null / 0.
- Re-sync must **not** overwrite an operator-edited planned amount — **open item** (recommend: set only when null on first create; never on update).

### Order detail

- Show **Planned shipping** (editable + save).
- Show **Actual postage** from shipment when present.
- Optional variance line: actual − planned.

### Product Costing panel

When `planned_shipping_gbp` is set:

```
material_cost
+ platform_fees
+ planned_shipping
= total_cost_for_margin
profit / margin vs target_selling_price
```

If planned shipping is null, behaviour matches today (materials + fees only).

### Shipments

No change to create/edit shipment UX beyond optionally displaying planned vs actual. Do **not** auto-fill `postage_paid` from planned (operators enter what they paid).

## 5. UI requirements

| Page | Purpose |
|---|---|
| Packages admin | CRUD catalogue |
| Product edit | Weight, package, planned shipping |
| Order detail | Planned shipping edit; actual + variance |
| Product Costing | Planned shipping cost line |
| Orders list / Board | Optional planned/actual columns — **out of scope v1** |

## 6. Out of scope

- Calling eBay Sell Logistics / Etsy shipping calculator APIs
- Carrier account integrations (Shippo, EasyPost, etc.)
- Auto-selecting package for multi-SKU carts
- Buyer shipping charged vs seller cost reconciliation reports (beyond simple order variance)
- Changing Click & Drop / tracking push behaviour

## 7. Open items

1. Seed rule for multi-line orders (sum × qty vs primary only vs max line).
2. Whether re-sync/import may fill planned shipping when still null.
3. Include postage in order-level profit helpers / Analytics in this package or defer.
4. Hide shipping fields on internal products?
5. Package delete vs deactivate when referenced.
6. Domestic vs international planned amounts (single flat field vs two fields) — **recommend single flat for v1**.
