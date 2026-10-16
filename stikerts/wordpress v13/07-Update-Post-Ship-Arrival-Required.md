# Update — Post-ship template must have a Package arrived step (BUG-005)

*Package 12 · No schema. Driven by operator testing (Post-ship shows **Awaiting arrival** forever; no **Package arrived** step to confirm).*

---

## 1. What operators reported

- Built a custom Post-ship workflow. On order detail the Post-ship panel shows **Awaiting arrival** (and **Shipment missing**), but no step offers the **Package arrived** checklist.
- Without that step the order can **never** complete (Package 11: complete = shipment recorded **and** arrival confirmed; arrival is recorded only by Mark done on a step with confirmation kind `package_arrived`).

Logged as **BUG-005** in [`../wordpress/BUGS.md`](../wordpress/BUGS.md).

## 2. Today (Package 11 — locked rules kept)

| Topic | Today |
|---|---|
| Complete rule | `SOM_Post_Ship::confirm_arrival` (from Mark done on an arrival step) sets `arrival_confirmed_at`; `is_complete` when `SOM_Shipments::has_required` |
| Arrival step | `SOM_Post_Ship::is_arrival_step` = confirmation kind `package_arrived` |
| Template validation | `SOM_Workflows::save_steps` validates **each step** (gate combos) — no **template-level** rule |
| Save order | `handle_save_workflow` (`admin/class-som-admin-menu.php`) runs `SOM_Workflows::update` / `create` **before** `save_steps`. A step error redirects back after template meta (and a **new** template row) is already written; unsaved step edits are lost (form re-renders from DB) |
| Settings | Default Post-ship dropdown lists all post-ship templates (marks `(inactive)`) |

## 3. Settled decisions (operator, 2026-10-14)

| # | Topic | Decision |
|---|---|---|
| A1 | Rule | A **Post-ship** template must have **at least one** step with Confirmation checklist = **Package arrived** — **hard block** on save |
| A2 | Pack Ship equivalent | **No** — Pack keeps today’s name-based Ship detection; no new Pack rule |
| A3 | Existing invalid templates | Keep working for bound orders; **warning** on Workflows list + editor; saving them is blocked until the step is added |
| A4 | Settings default picker | **Warn only** — label `(no arrival step)` next to the name; still selectable |
| A5 | Stuck orders | Order detail **notice only** (no rebind tool) |
| A6 | Inactive templates | Rule applies **regardless** of active flag |

## 4. Behaviour

### Validation (server — authoritative)

Applies when the **resulting** kind is `post_ship`:

- create a Post-ship template;  
- edit a Post-ship template (incl. removing / changing the arrival step);  
- change kind of another template to Post-ship.

Error (soft default copy): **“A Post-ship workflow needs at least one step with Confirmation checklist = Package arrived. Nothing was saved.”**

**Atomic save:** validate the full submission (template fields + posted kind + all steps, using the same normalisation `save_steps` already does) **before** any write. On failure: no template update, **no new template row**, no step changes.

Soft default implementation: extract a side-effect-free `SOM_Workflows::validate_submission( $kind, array $steps )` (or `validate_steps`) that `handle_save_workflow` calls first; `save_steps` keeps calling it too (defence in depth, e.g. future REST/seed paths).

### Validation (browser — convenience)

Soft default: on Save, if kind = Post-ship and no step’s Confirmation checklist is Package arrived → block submit and show the same message inline (`notice-error` near the Save button). Prevents losing unsaved step edits on a server reject. Server check still runs.

### Warnings for existing templates (A3)

| Surface | Behaviour |
|---|---|
| Workflows list | Badge **No arrival step** on Post-ship rows that lack one |
| Workflow editor | Warning notice at top: orders using it can’t complete; add a Package arrived step to save |
| Settings → Default Post-ship | Option label suffix `(no arrival step)`; when the **selected** default lacks one, a `notice-warning` under the field (A4) |

### Order detail (A5)

On the Post-ship panel, when post-ship has started, arrival not confirmed, order not complete/cancelled, and the order’s **bound** post-ship progress has **no** `package_arrived` step:

> “This order’s Post-ship workflow has no Package arrived step, so it can’t complete. Fix the template for future orders; this order keeps its current steps.”

Shown alongside the existing **Awaiting arrival** badge. No rebind / repair action.

### Unchanged

- Pack Ship detection (name starts with “Ship” / shipping_label batch) — A2.  
- Complete rule (shipment + arrival).  
- Seed **Post-ship — Order Aftercare** already has “Confirm package arrived” — still saves.  
- Make / Pack templates unaffected.

## 5. Work items (detail)

1. `SOM_Workflows`: helper `template_has_arrival_step( $template_id )` (list/editor/settings/order notice) + side-effect-free submission validator.  
2. `handle_save_workflow`: call validator before `update` / `create`; on error flash + redirect without writing.  
3. Editor JS: pre-submit check for Post-ship kind (reads each step’s confirmation select + the kind select).  
4. Workflows list badge; editor top notice; Settings option suffix + notice.  
5. Order detail Post-ship notice.  
6. Smoke `tests/sprint-up12-s5-smoke.php`:  
   - new Post-ship without arrival → error, **no** template row created;  
   - existing Post-ship: remove arrival step → error, steps + name unchanged;  
   - kind make → post_ship without arrival → error, kind unchanged;  
   - with arrival → saves; inactive template follows same rule;  
   - `template_has_arrival_step` false for legacy invalid row (inserted directly) → list/settings helpers flag it;  
   - Pack template without Ship step still saves (A2).  
7. Docs: USER-REFERENCE (Workflows + Post-ship board), USER-WORKFLOWS troubleshooting (“Awaiting arrival forever”), FEATURES-AND-TESTING; BUGS → BUG-005 status.

## 6. Out of scope

- Pack “must have Ship step” rule / explicit Ship checkbox (A2)  
- Rebinding or repairing orders already on an invalid template  
- Blocking selection of invalid templates in Settings (A4 warn only)  
- Carrier-tracking auto-arrival  
- Preserving unsaved editor state for *other* step validation errors (existing behaviour; browser pre-check covers this rule only)  
