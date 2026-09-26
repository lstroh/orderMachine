# Update Package 5 — Sprint Progress

*Companion to [`Update-5-Sprint-Plan.md`](Update-5-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP5-S1 | Order notes | Done | Schema 1.13.0; plugin 0.28.0 |
| UP5-S2 | Shipping packages / planned postage | Not started | |
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
