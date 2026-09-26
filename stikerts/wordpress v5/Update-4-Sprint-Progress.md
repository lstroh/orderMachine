# Update Package 4 — Sprint Progress

*Companion to [`Update-4-Sprint-Plan.md`](Update-4-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP4-S1 | Step instructions | Done | Schema 1.11.0; plugin 0.24.0 |
| UP4-S2 | Order material overuse + R&D copy | Not started | |
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
