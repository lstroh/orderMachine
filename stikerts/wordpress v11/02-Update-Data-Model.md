# Update Package 10 — Data Model Changes

*Workflow timing. Baseline: **v0.32.2**.*

---

## A. Planned duration (template)

### `workflow_steps.planned_seconds`

| Column | Type | Notes |
|---|---|---|
| `planned_seconds` | INT UNSIGNED NULL | Target wall-clock for this step; null = no plan |

Editor: friendly minutes/hours (same UX pattern as timer), stored as seconds.

**Workflow planned total** = sum of step `planned_seconds` for that template (computed; no column required). Optional cache later.

---

## B. Actual duration (runtime)

Progress already has:

- `started_at`, `completed_at` on `order_item_step_progress` and `order_step_progress`

**Actual step seconds** (wall clock):

```
completed_at - started_at   (when both set)
```

Incomplete steps: null actual (or live elapsed = now − started_at for display only — **default: show live elapsed on current step, don’t persist until complete**).

### Optional denormalised columns (soft default: **yes** for simpler Analytics)

| Column | On | Notes |
|---|---|---|
| `actual_seconds` | both progress tables | Set on step complete; wall-clock integer |

If omitted, Analytics computes from timestamps (heavier queries).

---

## C. Workflow-level actuals

No new tables required for v1:

| Total | Definition |
|---|---|
| Make line | Sum of that line’s completed step actuals **or** first `started_at` → last `completed_at` on the line — **default: first start → last complete** (true wall-clock for the make run) |
| Pack | First pack step `started_at` → last pack step `completed_at` (or order complete time) |

**Open:** sum-of-steps vs span-of-steps when steps don’t overlap (they’re sequential → should match). Prefer **span** for “entire workflow time”.

---

## D. Open items (data)

1. Persist `actual_seconds` vs derive only — **default: persist on complete**.  
2. Make span vs sum — **default: span** (first start → last complete).  
3. Planned pack/make shown when some steps lack planned_seconds — **default: sum of non-null only + “partial plan” hint**.  
