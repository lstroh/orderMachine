# Update Package 11 — Sprint Progress

*Companion to [`Update-11-Sprint-Plan.md`](Update-11-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP11-S1 | Kind, bind/start, complete rules | Code complete | This PR |
| UP11-S2 | Board, UI, seed, docs | Not started | |

---

## UP11-S1 — Kind, bind/start, complete rules

- **Status:** Code complete (this PR)
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
- No Post-ship board / seed aftercare / full docs (S2)

### Verification

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up11-s1-smoke.php
```

---

## Package 11 checklist

### UP11-S1
- [x] kind post_ship + bind/auto/manual start
- [x] Complete rule = shipment + arrival
- [x] Product override + site default
- [x] Smoke

### UP11-S2
- [ ] Post-ship board + order panel polish
- [ ] Seed / migrate review reminder
- [ ] Docs + smoke
