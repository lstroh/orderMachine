# Plugin Update Package 10 — Overview

*Update set · Planned + actual wall-clock timing for workflow steps and whole make/pack workflows. Self-contained.*

---

## Assumption

- Make progress is per line (`order_item_step_progress`); pack is order-level (`order_step_progress`).
- Progress rows already have `started_at` / `completed_at` (often unused in UI).
- Analytics dashboard exists (Package 3).
- Baseline: **v0.32.2** (+ Packages 7–9 when started).

## Settled product decisions (operator)

| Topic | Decision |
|---|---|
| What to store | **Actual** + **planned** |
| Duration basis | **Wall clock** (enter → complete, including timer waits) |
| Totals | Per **make line** + separate **pack** total (and step-level) |
| Surfaces | **Order detail** and **Analytics** |

## What's in this update

1. **Planned duration** on each workflow step (template editor).  
2. **Actual duration** derived/stored from progress timestamps.  
3. **Rollups** — make-line total, pack total; compare actual vs planned.  
4. **Order detail** — show per-step and workflow totals.  
5. **Analytics** — timing charts/tables (averages, vs plan).  

## Recommended build order

1. **UP10-S1** — Planned fields + actual calc + order detail display  
2. **UP10-S2** — Analytics timing views + docs  

## Out of scope

- Active-only time (excluding timers)  
- Payroll / operator time tracking accounts  
- Editing historical actuals by hand  

## Files

1. `01-Update-Overview.md` (this file)  
2. `02-Update-Data-Model.md`  
3. `03-Update-Workflow-Timing.md`  
4. `Update-10-Sprint-Plan.md`  
5. `04-Update-Cursor-Prompt.md`  
