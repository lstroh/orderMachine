# Update Package 11 — Sprint Progress

*Companion to [`Update-11-Sprint-Plan.md`](Update-11-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP11-S1 | Kind, bind/start, complete rules | Done on `main` | #29 |
| UP11-S2 | Board, UI, seed, docs | Code complete | This PR |

**Package 11 complete.** Packages **7–11** series complete (Trays/Unblock → Copy command → Workflow timing → Post-ship).

---

## UP11-S1 — Kind, bind/start, complete rules

- **Status:** Done on `main` (#29)
- **Schema:** 1.24.0 (`workflow_templates.kind` + `post_ship`; order post-ship bind/arrival columns; `products.post_ship_workflow_template_id`)
- **Plugin:** unchanged (release bump optional later → ~0.45.0)

### Delivered

- Schema + `SOM_DB::upgrade_workflow_template_kind_post_ship()` / `upgrade_post_ship_columns()`
- Template kind **Post-ship**; confirmation kind **Package arrived**
- `SOM_Post_Ship`: site default + auto-start (default on); resolve product override → site default; bind/start; `confirm_arrival`
- Pack finish for customer orders **no longer** sets `is_complete`; auto-starts post-ship when setting on
- Arrival confirm (+ shipment) sets `is_complete`; later post-ship steps may still run (O1)
- Reuses `order_step_progress` + `current_step_id` after pack done (O3); advance scoped by template
- Settings: default Post-ship template + auto-start toggle
- Product: optional Post-ship override
- Order detail: Start post-ship control + minimal post-ship progress panel
- Internal Produce N: skipped
- Smoke: `tests/sprint-up11-s1-smoke.php`

### Soft defaults applied

- O1: `is_complete` at arrival; review may continue
- O2: auto-start default on
- O3: reuse `current_step_id` after pack done
- O4: mixed products → primary product’s post_ship template
- No trays / package materials on post-ship

### Verification

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up11-s1-smoke.php
```

---

## UP11-S2 — Board, UI, seed, docs

- **Status:** Code complete (this PR)
- **Plugin:** unchanged (release bump optional later → ~0.46.0)

### Delivered

- **Post-ship Board** (`som-post-ship-board`): menu + Kanban by post-ship step; scope Active / Unstarted / Complete; **filter by template** (O5); Start/Open cards; reuses S1 engine (no trays)
- Order detail Post-ship panel polished to Make/Pack quality (board link, badges, copy-command, tasks, confirmation, timer countdown, unblock, timing footer)
- Seed **Post-ship — Order Aftercare** (buyer update + copy-command → confirm shipping details → package arrived → Review reminder); site default when unset
- **Strip Review reminder** from Pack seed (migrate when no open progress on that step); docs note for existing sites
- Docs: USER-GUIDE / USER-REFERENCE / FEATURES — lifecycle, complete = shipment + arrival, board, auto/manual start, review on post-ship
- Smoke: `tests/sprint-up11-s2-smoke.php`
- Board DnD / REST meta allow post-ship advance after `is_complete` (O1 review)

### Soft defaults applied

- O5: board filter by template yes
- O1 from S1 stays
- No trays / package materials / marketplace send APIs on post-ship
- Plain PHP admin

### Verification

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up11-s2-smoke.php
```

---

## Package 11 checklist

### UP11-S1
- [x] kind post_ship + bind/auto/manual start
- [x] Complete rule = shipment + arrival
- [x] Product override + site default
- [x] Smoke

### UP11-S2
- [x] Post-ship board + order panel polish
- [x] Seed / migrate review reminder
- [x] Docs + smoke

---

## Series note

Packages **7–11** (from the Update-7 follow-on track through Post-ship) are code-complete. SemVer release bumps (~0.45 / ~0.46) remain optional operator cuts via the release skill.
