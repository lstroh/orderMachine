# Plugin Update Package 5 — Overview

*Update set · Combined package covering three additive features: Order Notes (threaded), Shipping packages / planned postage, and Multipack catalogue conventions. Self-contained — assumes the base plugin and Update Packages 1–4 (including internal products, material overuse, step instructions, shipments, platform fees, analytics) are already built and working.*

---

## Assumption

Everything built so far is in place and working, in particular:

- Orders list/detail, Orders Board, workflow engine, step instructions, material overuse.
- Products with recipes; internal products + Produce N; listings exclude internals.
- **Shipments** already store **actual** outbound postage (`som_shipments.postage_paid`) per order when shipping is recorded.
- Product Costing today = recipe material cost + platform fees vs target selling price (**no** shipping in that panel yet).
- No order-level free-text notes today.

This package is a **pure additive update**. Do not rework unrelated behaviour.

Current plugin baseline at planning time: **v0.27.0**, schema **`som_db_version` 1.12.0**.

## What's in this update

1. **Order Notes** (`03-Update-Order-Notes.md`) — admin-only **threaded log** on order detail (timestamp + user + entry). Not on Board/list snippets; not exposed via REST/MCP or channel sync.
2. **Shipping packages & planned postage** (`04-Update-Shipping-Packages.md`) — reusable **package catalogue** (box/mailer dims + tare); product **goods weight** + link to default package; **planned (flat) shipping cost** on product and editable on order; **actual** remains shipment `postage_paid`. No eBay/Etsy/carrier live rate APIs in v1. Product Costing gains planned shipping as a cost line.
3. **Multipacks** (`05-Update-Multipacks.md`) — **no special multipack engine** in v1: each pack size is a **separate sellable SKU** with its own recipe, weight, package, and planned postage. Document conventions only (optional seed example).

## How the features interact

| Dependency | Note |
|---|---|
| Notes ↔ Shipping | Independent; both surface on order detail. |
| Shipping ↔ Product Costing | Planned shipping (product default) feeds Product Costing; order planned vs shipment actual for ops variance. |
| Shipping ↔ Multipacks | Each pack SKU has its own weight / package / planned postage (4-pack ≠ 1-pack defaults). |
| Shipping ↔ Shipments | Planned ≠ actual; do not overwrite `postage_paid` from product defaults. |
| Notes ↔ REST/MCP | Admin UI only in v1. |

**Recommended build order**

1. Order Notes (smallest; isolated table + order detail UI)
2. Package catalogue + product weight / package link + planned postage (product + order) + Product Costing line
3. Multipack conventions / docs / optional seed (thin; can ship with shipping sprint)

## Full schema change list

Detailed specs: `02-Update-Data-Model.md`. Summary:

| Change | Feature |
|---|---|
| New table `order_notes` | Order Notes |
| New table `shipping_packages` | Shipping |
| `products.weight_grams`, `products.package_id`, `products.planned_shipping_gbp` (names TBD in data model) | Shipping |
| `orders.planned_shipping_gbp` (editable; seeded from products on create) | Shipping |
| Multipacks | **No schema** (separate products) |

## Settled product decisions (from planning chat)

Captured so implementers do not re-litigate:

**Order notes:** threaded log (timestamp + user + body); **order detail only**; **admin only** (not REST/MCP / channel).

**Shipping rates:** **flat / expected** amounts you set; no live eBay Logistics / Etsy calculator API integration in this package. Set **per product** (default) and **per order** (override). Actual postage = existing shipment record.

**Physical shipping data:** **goods weight** + **package catalogue link** (package has outer dims + tare). Not separate “product size” vs “packed size” in v1 unless opened later.

**Multipacks:** **separate SKUs** per pack size (not base SKU × multiplier engine).

**Package scope:** notes + shipping + multipack conventions in **one** Package 5 (same design set; sprint plan may still split delivery).

## Files in this package

1. `01-Update-Overview.md` — this file
2. `02-Update-Data-Model.md` — all schema changes
3. `03-Update-Order-Notes.md` — feature spec
4. `04-Update-Shipping-Packages.md` — feature spec
5. `05-Update-Multipacks.md` — conventions (no engine)
6. `06-Update-Cursor-Prompt.md` — kickoff prompt (**planning → sprint plan**, then implement per sprint)
