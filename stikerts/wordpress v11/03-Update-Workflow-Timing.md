# Update — Workflow Timing (Planned + Actual)

*Package 10 · Schema in `02-Update-Data-Model.md`. Self-contained.*

---

## 1. What this adds

Track **how long each step should take** (plan) and **how long it took** (actual wall clock), plus totals for each make line and for pack. Show on **order detail** and **Analytics**.

## 2. Settled rules

| Topic | Decision |
|---|---|
| Actual + plan | Both |
| Clock | Wall clock (includes timer waits) |
| Totals | Per make line + pack workflow |
| UI | Order detail + Analytics |

## 3. Behaviour

### Workflow editor

- Per step: **Planned duration** (minutes/hours/days → `planned_seconds`), separate from timer gate.  
- Timer = hard gate; planned = target for reporting (can differ).  
- Show template planned total (sum of steps).

### Runtime

- On step enter: `started_at` (already).  
- On step complete: `completed_at` + `actual_seconds`.  
- Make-line workflow actual = first step start → last step complete.  
- Pack workflow actual = first pack step start → last pack step complete / order complete.

### Order detail

| Display | Content |
|---|---|
| Each step | Planned · Actual (and optional ± vs plan) |
| Make line footer | Make total planned · actual |
| Pack panel / progress | Pack total planned · actual |
| In-progress step | Live elapsed (optional), planned |

### Analytics

Soft-default v1:

- Filter by date range / template  
- Average actual vs average planned per step (for a make or pack template)  
- Distribution or table of order/line workflow totals  
- Highlight steps often over plan (e.g. actual > planned × 1.2)

Exact chart library: follow existing Analytics patterns (Chart.js).

## 4. Edge cases

| Case | Behaviour |
|---|---|
| Step not started | No actual |
| Cancelled order | Keep completed steps’ actuals; don’t invent totals |
| Legacy progress without timestamps | Show “—” |
| Timer early release | Still wall clock to Mark done |

## 5. Out of scope

- Excluding wait/timer from actual  
- Per-user time tracking  
- SLA alerts / email  

## 6. Open items

1. Live elapsed on current step in UI? **Default: yes.**  
2. Analytics depth (table only vs charts) — **default: table + one chart**.  
3. Compare make vs pack on same Analytics page? **Default: tabs or template filter.**  
