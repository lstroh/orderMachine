# Update Package 7 — Sprint Progress

*Companion to [`Update-7-Sprint-Plan.md`](Update-7-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP7-S1 | Make step gate & confirmation UX (BUG-003) | Code complete | No schema bump; wp-env smoke pending if Docker unavailable |
| UP7-S2 | Package materials + Materials used | Not started | — |
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

## UP7-S2 / UP7-S3

Not started (out of scope for this PR).
