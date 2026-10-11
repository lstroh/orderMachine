# Update Package 10 — Sprint Progress

*Companion to [`Update-10-Sprint-Plan.md`](Update-10-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP10-S1 | Planned + actual on order detail | Code complete | This PR |
| UP10-S2 | Analytics + docs | Not started | Charts/docs deferred |

---

## UP10-S1 — Planned + actual on order detail

- **Status:** Code complete (this PR)
- **Schema:** 1.23.0 (`workflow_steps.planned_seconds`; `actual_seconds` on `order_item_step_progress` + `order_step_progress`)
- **Plugin:** unchanged (release bump optional later → ~0.43.0)

### Delivered

- Schema + `SOM_DB::upgrade_workflow_timing_columns()`
- Workflow editor: **Planned duration** (minutes/hours/days → `planned_seconds`), separate from timer gate; template planned total + partial-plan hint (live JS update)
- Engine: `started_at` on enter; `completed_at` + `actual_seconds` on complete (wall clock = completed − started, includes timer waits); zero-gate auto-done stores `actual_seconds = 0`
- Order detail: per-step Planned · Actual (optional ± vs plan); in-progress live elapsed (O3, display only); make-line + pack footers with planned sum + **span** actual (O2: first start → last complete)
- Cancelled / incomplete: keep completed steps’ actuals; no invented workflow totals
- Legacy rows without timestamps → “—”
- Smoke: `tests/sprint-up10-s1-smoke.php`

### Soft defaults applied

- O1: persist `actual_seconds` on complete
- O2: workflow actual = span first→last (not sum-of-steps)
- O3: live elapsed on current step (JS tick; not persisted until complete)
- Wall clock includes timer waits
- No manual edit of actuals; no SLA alerts
- No Analytics work (S2)

### Explicitly deferred

- UP10-S2: Analytics timing charts/tables + USER-GUIDE / REFERENCE docs
- Active-only time (excluding timers)
- Package 11 (post-ship)

### Verification

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up10-s1-smoke.php
```

---

## UP10-S2 — Analytics + docs

- **Status:** Not started
- **Scope:** Timing section on Analytics; date/template filters; docs for planned vs timer vs actual; `tests/sprint-up10-s2-smoke.php`
