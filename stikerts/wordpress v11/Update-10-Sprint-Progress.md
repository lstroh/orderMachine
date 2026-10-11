# Update Package 10 — Sprint Progress

*Companion to [`Update-10-Sprint-Plan.md`](Update-10-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP10-S1 | Planned + actual on order detail | Done | Merged (#27) |
| UP10-S2 | Analytics + docs | Code complete | This PR |

**Package 10 sprints: complete.**

---

## UP10-S1 — Planned + actual on order detail

- **Status:** Done (merged to main)
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

### Verification

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up10-s1-smoke.php
```

---

## UP10-S2 — Analytics + docs

- **Status:** Code complete (this PR)
- **Plugin:** unchanged (release bump optional later → ~0.44.0)

### Delivered

- Analytics **Workflow timing** section: Make / Pack tabs + template filter; shared date range with the rest of Analytics
- Step averages table: avg actual vs avg planned per step; samples; over-plan highlight when actual > planned × 1.2
- One Chart.js bar chart: avg planned vs avg actual by step (minutes)
- Workflow totals table (make-line or pack): span actual (first start → last complete) vs template planned sum; averages + detail rows
- Uses persisted `actual_seconds` / `planned_seconds` from S1
- Docs: USER-GUIDE / USER-REFERENCE / FEATURES — planned vs timer vs actual; where to see times
- Smoke: `tests/sprint-up10-s2-smoke.php`

### Soft defaults applied

- O4: table + one chart (not table-only)
- Wall clock includes timer waits (same as S1)
- No active-only time; no manual edit of actuals; no SLA notifications
- Package 11 not started

### Verification

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up10-s2-smoke.php
```

---

## Package 10 checklist

### UP10-S1
- [x] planned_seconds + editor
- [x] actual_seconds on complete
- [x] Order detail step + make/pack totals
- [x] Smoke

### UP10-S2
- [x] Analytics timing
- [x] Docs + smoke
