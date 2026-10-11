# Update Package 7 — Sprint Progress

*Companion to [`Update-7-Sprint-Plan.md`](Update-7-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP7-S1 | Make step gate & confirmation UX (BUG-003) | Code complete | Merged (#17); wp-env smoke pending if Docker unavailable |
| UP7-S2 | Package materials + Materials used | Code complete | Merged (#18); schema 1.17.0 |
| UP7-S3 | Order package suggestion + seed/docs | Code complete | Schema 1.18.0; Package 7 sprints complete |

---

## UP7-S1 — Make step gate & confirmation UX (BUG-003)

- **Status:** Code complete (merged #17)
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

- PHP lint on changed files: pass
- `node --check` on `admin/assets/js/admin.js`: pass
- wp-env smoke: run locally when Docker available

---

## UP7-S2 — Package materials + Materials used

- **Status:** Code complete (merged #18)
- **Schema:** 1.17.0 (`shipping_package_materials`, reason `package_reserve`)
- **Plugin:** unchanged (release bump optional later → ~0.34.0)

### Delivered

- Package material recipe UI + table
- Reserve/reverse on package select/change
- Materials used Source Recipe / Package
- COGS includes package reserve + extras
- Smoke: `tests/sprint-up7-s2-smoke.php`

### Soft defaults applied

O3 reserve when `shipping_package_id` set/changed; O4 reverse prior then reserve new; O5 separate Source rows.

---

## UP7-S3 — Order package suggestion (1 vs 2+ / mixed) + multi-line test orders + seed/docs

- **Status:** Code complete (this PR)
- **Schema:** 1.18.0 (`products.package_id_multi`)
- **Plugin:** unchanged (release bump optional later → ~0.35.0)

### Delivered

- Product fields: `package_id` = qty 1; `package_id_multi` = suggestion when order total sellable qty ≥ 2
- `SOM_Pack::suggest_package` / `suggest_package_id` / `has_mixed_package_conflict` — sum sellable qtys; agreement vs site default + Pack flag
- Create test order: up to 5 addable lines (product + qty + optional price/personalisation) via `create_from_external`
- Seed: Sample C5 envelope (single) + Sample large mailer (multi) with thank-you/envelope materials; sample product wired
- Docs: USER-GUIDE / USER-REFERENCE / FEATURES
- Smoke: `tests/sprint-up7-s3-smoke.php`
- O6 Product Costing packaging line: deferred (not in this sprint)

### Soft defaults applied

O1 product-level `package_id_multi`; O2 sum of sellable qtys; O9 seed yes; O10 conflict → site default + confirm flag; O11 multi-line test order; O6 deferred.

### Verification

- PHP lint on changed files
- `node --check` on `admin/assets/js/admin.js`
- wp-env: UP7-S3 smoke **RESULT: OK**; UP7-S2 smoke updated for create-time suggested reserve (count before switch + 2) then **RESULT: OK**

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up7-s3-smoke.php
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up7-s2-smoke.php
```

---

## Package 7 complete

All three sprints (S1–S3) are code-complete. Follow-on packages (Trays/Unblock, Copy command, Workflow timing, Post-ship) are out of scope — see Sprint Plan §8.
