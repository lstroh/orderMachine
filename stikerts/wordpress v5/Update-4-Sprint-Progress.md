# Update Package 4 — Sprint Progress

*Companion to [`Update-4-Sprint-Plan.md`](Update-4-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP4-S1 | Step instructions | Done | Schema 1.11.0; plugin 0.24.0 |
| UP4-S2 | Order material overuse + R&D copy | Done | Plugin 0.25.0; no schema bump |
| UP4-S3 | Internal products core | Not started | |
| UP4-S4 | Internal products UX / guards | Not started | |

---

## UP4-S1 — Step instructions

- **Status:** Done
- **Completed:** 2026-09-26
- **Verified on:** Deferred to operator desktop (Local / wp-env). Smoke script: `tests/sprint-up4-s1-smoke.php`. Cloud agent had no Docker.

### Decisions applied

| Topic | Decision |
|---|---|
| Display | All steps on order detail; empty → hidden |
| Max length | 5000 chars |
| Orphans | Deleted when product workflow changes / steps removed |
| MCP/REST | Not in this sprint |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | `workflow_steps.instructions`; `product_step_instructions`; DB **1.11.0** |
| `includes/class-som-step-instructions.php` | Resolve + sync overrides |
| `includes/class-som-workflows.php` | Persist step defaults; clean overrides on step delete |
| `includes/class-som-products.php` | Orphan cleanup on workflow reassignment |
| `admin/views/workflow-step-editor.php` | Default instructions field |
| `admin/views/product-edit.php` | Per-step override UI |
| `admin/views/order-detail.php` | Read-only effective instructions |
| `admin/class-som-admin-menu.php` | Save overrides with product |
| `admin/assets/css/admin.css` | Instruction styles |
| `orderMachine.php` | Require + version **0.24.0** |
| `tests/sprint-up4-s1-smoke.php` | Smoke coverage |

### Done-when checklist

| Criterion | Result |
|---|---|
| Step default + product override save | Implemented (run smoke on Local/wp-env) |
| Order detail shows effective text; empty hidden | Implemented |
| Workflow reassignment / no template clears orphans | Implemented |
| No Board changes | Pass |

### How to verify

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up4-s1-smoke.php
```

Then in wp-admin: set a workflow step default → product override → open an order on that product and confirm Instructions appear on each step (blank steps show nothing).

---

## UP4-S2 — Order material overuse + R&D copy

- **Status:** Done
- **Completed:** 2026-09-26
- **Verified on:** Deferred to operator desktop (Local / wp-env). Smoke script: `tests/sprint-up4-s2-smoke.php`. Cloud agent had no Docker.

### Decisions applied

| Topic | Decision |
|---|---|
| Overuse direction | Increase-only; reject below planned or below current actual |
| Stock reason | `order_usage_extra` (label: Extra material usage) |
| Budget funding | Separate `fund_usage_extras` with ledger reason `extra_material_usage` (not `fund_on_create`) |
| Aggregation | Pooled planned/extra/actual per material across order lines |
| History / no reservation | Empty Materials used panel; apply is no-op / error |
| R&D copy | Material + budget detail help only; restock-pot wording |
| Schema | None (no bump) |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-material-stock.php` | `get_usage_by_material` + `apply_overuse` |
| `includes/class-som-budgets.php` | `REASON_EXTRA_MATERIAL_USAGE` + `fund_usage_extras` |
| `includes/class-som-analytics.php` | COGS includes `order_usage_extra` |
| `includes/class-som-materials.php` | Reason label; `adjust_stock` returns stock-log PK |
| `includes/class-som-orders.php` | Attach `materials_used` on order load |
| `admin/views/order-detail.php` | Materials used panel (edit Actual) |
| `admin/class-som-admin-menu.php` | Save handler → `apply_overuse` |
| `admin/views/material-edit.php` | R&D / Adjust stock copy |
| `admin/views/budget-edit.php` | R&D restock-pot copy |
| `orderMachine.php` | Version **0.25.0** |
| `tests/sprint-up4-s2-smoke.php` | Smoke: reject below/decrease, stock, COGS, ledger |

### Done-when checklist

| Criterion | Result |
|---|---|
| Raise actual → stock ↓, COGS ↑, Extra material usage ledger | Implemented (run smoke on Local/wp-env) |
| Cannot go below planned or reduce prior actual | Implemented |
| No reservation → empty panel / error | Implemented |
| R&D vs Adjust stock restock-pot wording | Implemented |
| No Board changes; no schema bump | Pass |

### How to verify

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up4-s2-smoke.php
```

Then in wp-admin: open an order that reserved materials → **Materials used** → raise Actual → confirm stock, order profit/COGS, and material budget ledger show **Extra material usage**. Confirm Actual cannot go below planned.
