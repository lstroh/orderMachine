# Update Package 5 — Sprint Progress

*Companion to [`Update-5-Sprint-Plan.md`](Update-5-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP5-S1 | Order notes | Done | Schema 1.13.0; plugin 0.28.0 |
| UP5-S2 | Shipping packages / planned postage | Done | Schema 1.14.0; plugin 0.29.0 |
| UP5-S3 | Multipack conventions / docs | Not started | |

---

## UP5-S1 — Order notes

- **Status:** Done
- **Completed:** 2026-09-26
- **Verified on:** Deferred to operator desktop (Local / wp-env). Smoke script: `tests/sprint-up5-s1-smoke.php`. Cloud agent had no Docker.

### Decisions applied

| Topic | Decision |
|---|---|
| Shape | Append-only threaded log |
| Display | Oldest first; form at bottom |
| Max length | 5000 chars |
| Surfaces | Order detail only |
| REST/MCP | Not exposed |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | `order_notes` table; DB **1.13.0** |
| `includes/class-som-order-notes.php` | List + add |
| `admin/views/order-detail.php` | Notes panel |
| `admin/class-som-admin-menu.php` | Add-note handler |
| `admin/assets/css/admin.css` | Note styles |
| `orderMachine.php` | Require + version **0.28.0** |
| `tests/sprint-up5-s1-smoke.php` | Smoke |

### Done-when checklist

| Criterion | Result |
|---|---|
| Add notes; show author + time + body | Implemented (run smoke on Local/wp-env) |
| Empty / over-length rejected | Implemented |
| REST/MCP exclude notes | Implemented |
| Append-only | Pass |

### How to verify

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up5-s1-smoke.php
```

Then in wp-admin: open an order → **Notes** → add a note → confirm it appears with your name and timestamp.

---

## UP5-S2 — Shipping packages & planned postage

- **Status:** Done
- **Completed:** 2026-09-26
- **Verified on:** Deferred to operator desktop (Local / wp-env). Smoke script: `tests/sprint-up5-s2-smoke.php`. Cloud agent had no Docker.

### Decisions applied

| Topic | Decision |
|---|---|
| Seed | Sum of `product.planned_shipping_gbp × qty` for matched lines with planned set |
| Re-sync | Create-only seed; never overwrite on update |
| Internal products | Shipping fields cleared / hidden |
| Internal orders | Planned shipping left null |
| Costing | Planned shipping in Product Costing; `order_profit` / Analytics unchanged |
| Package delete | Deactivate preferred; hard-delete blocked if products reference |
| Default package | One `is_default` enforced in app |
| Units | Store g + mm |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | `shipping_packages`; product + order columns; DB **1.14.0** |
| `includes/class-som-shipping-packages.php` | CRUD, default flag, list active |
| `includes/class-som-products.php` | Persist shipping fields; costing line |
| `includes/class-som-orders.php` | `set_planned_shipping`; attach shipment on get |
| `includes/class-som-order-sync.php` | Seed planned on create |
| `admin/views/shipping-packages-list.php` / `shipping-package-edit.php` | Catalogue UI |
| `admin/views/product-edit.php` | Weight, package, planned £ |
| `admin/views/order-detail.php` | Planned vs actual + variance |
| `admin/class-som-admin-menu.php` | Menu + handlers + allowlists |
| `orderMachine.php` | Require + version **0.29.0** |
| `tests/sprint-up5-s2-smoke.php` | Smoke |

### Done-when checklist

| Criterion | Result |
|---|---|
| Manage packages; assign on sellable products | Implemented |
| New non-internal order seeds sum×qty; editable; re-sync safe | Implemented |
| Internal product/order shipping null | Implemented |
| Product Costing planned shipping line | Implemented |
| Order detail planned vs actual variance | Implemented |
| Analytics / order_profit unchanged | Pass (no code change) |

### How to verify

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up5-s2-smoke.php
```

Then in wp-admin: **Shipping packages** → add a package → product edit set weight/package/planned £ → create test order → confirm planned = sum×qty → edit planned → save shipment postage → check variance.
