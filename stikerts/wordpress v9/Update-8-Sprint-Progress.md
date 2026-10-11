# Update Package 8 — Sprint Progress

*Companion to [`Update-8-Sprint-Plan.md`](Update-8-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP8-S1 | Schema, site max, order-level assign/release | Code complete | Schema 1.19.0; this PR |
| UP8-S2 | Trays require/complete + Unblock step + boards | Not started | |
| UP8-S3 | Docs / polish | Not started | |

---

## UP8-S1 — Schema, site max, order-level assign/release

- **Status:** Code complete (this PR)
- **Schema:** 1.19.0 (`workflow_templates.uses_trays`, `wp_som_tray_assignments`, option `som_tray_max`)
- **Plugin:** unchanged (release bump optional later → ~0.36.0)

### Delivered

- Settings: **Max trays** (`som_tray_max`, default 10, allowed 1–20); lowering blocked while a higher tray is still held (O4/O5)
- Schema: `uses_trays` on workflow templates; `som_tray_assignments` (order-level held/released; no `order_item_id`)
- `SOM_Trays`: free/held queries; assign many free trays to `order_id`; reject in-use; manual release (retain released rows)
- Workflow template editor: **Uses trays** checkbox (make + pack)
- Order detail: order-level trays panel — list held; multi-select free trays to assign; manual release
- Smoke: `tests/sprint-up8-s1-smoke.php`

### Soft defaults applied

- Trays on the order only — no per-line trays, no make→pack transfer
- O4 max range 1–20; O5 block lowering max while high trays held
- Released rows retained for audit
- Plain PHP admin; SemVer stays 0.x.y

### Explicitly deferred to UP8-S2

- Block Mark done / advance until trays when required
- Auto-release on order complete
- Unblock step + audit columns
- Board tray badges

### Verification

- PHP lint on changed files
- wp-env smoke (when Docker available):

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up8-s1-smoke.php
```
