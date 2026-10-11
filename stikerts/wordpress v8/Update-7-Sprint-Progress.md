# Update Package 7 — Sprint Progress

*Companion to [`Update-7-Sprint-Plan.md`](Update-7-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP7-S1 | Make step gate & confirmation UX (BUG-003) | Code complete | No schema bump; wp-env smoke pending if Docker unavailable |
| UP7-S2 | Package materials + Materials used | Code complete | Schema **1.17.0**; wp-env smoke pending if Docker unavailable |
| UP7-S3 | Order package suggestion + seed/docs | Not started | — |

---

## UP7-S1 — Make step gate & confirmation UX (BUG-003)

- **Status:** Code complete (PR)
- **Schema:** unchanged
- **Plugin:** unchanged (release bump optional later → ~0.33.0)

### Verified root cause

- **Create PDF Done** = zero-gate auto-complete when the step has no manual/confirm/timer/script/batch.
- `SOM_Item_Make::enter_item_step` and `SOM_Workflow_Engine::enter_step` correctly keep gated steps `in_progress` — no engine change required.
- Checklist UI was rendered above the step list (ownership unclear); Make panel still had UP6-S2 placeholder copy.

### Delivered

- Order detail Make + Pack/legacy workflow: nest confirmation checklist under the current step `<li>`; heading `Confirmation checklist — {step name}`
- Remove Make panel “Pack & Ship controls arrive in UP6-S2”
- Workflow editor: per-card + Save warn for zero-gate steps (pass-through allowed); new/blank step cards default **Requires manual confirm**
- USER-REFERENCE: zero-gate auto-complete note; checklist nested under current step
- BUG-003 → Fixed
- Smoke: `tests/sprint-up7-s1-smoke.php`

### Soft defaults applied

O7 warn-only; O8 verify-first (empty gates, not engine bug); checklist Save ≠ Mark done; no rewrite of already-Done rows; no schema bump; zero-gate behaviour kept.

### Verification

- PHP lint on changed files: pass (`order-detail.php`, `workflow-step-editor.php`, smoke)
- `node --check` on `admin/assets/js/admin.js`: pass
- wp-env smoke: **not run in cloud agent** (`docker` missing). Run locally:

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up7-s1-smoke.php
```

---

## UP7-S2 — Package materials + Materials used

- **Status:** Code complete (PR)
- **Schema:** `1.17.0` (`wp_som_shipping_package_materials`; stock reasons `package_reserve` / `package_unreserve` / `package_usage_extra`)
- **Plugin:** unchanged (release bump optional later → ~0.34.0)

### Soft defaults applied

- Materials on shipping package only (not Pack workflow)
- Reserve when `shipping_package_id` is set/changed (including suggest-on-bind when stock applies)
- Package change: reverse prior package reserves, then reserve new (O4)
- Materials used rows separate by Source Recipe / Package (O5)
- History import / cancelled / Internal Produce N skip package reserve
- Product Costing packaging line deferred (O6)
- No decrease below planned; Actual increase-only

### Delivered

- Schema: `shipping_package_materials` via `dbDelta`; `som_db_version` → `1.17.0`
- Shipping package edit: recipe-like material BOM UI; reject duplicate materials
- `SOM_Material_Stock::sync_package_reserve` on pack bind (when stock applies) + `SOM_Pack::set_shipping_package`
- Material budget funding for package reserves; reverse funding on package change
- Materials used panel: Source column; Package overuse via `package_usage_extra` + Extra material usage funding
- COGS: `package_reserve` + `package_unreserve` + extras included
- Smoke: `tests/sprint-up7-s2-smoke.php`
- USER-REFERENCE / FEATURES short notes

### Verification

- PHP lint on changed files: pass
- wp-env smoke: **not run in cloud agent** if Docker unavailable. Run locally:

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up7-s2-smoke.php
```

---

## UP7-S3

Not started (out of scope for this PR).
