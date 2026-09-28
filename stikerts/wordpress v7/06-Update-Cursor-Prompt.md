# Cursor Kickoff Prompt — Plugin Update 6

*Paste this into Cursor as the first prompt after the Package 6 design docs exist. It assumes the base plugin and Update Packages 1–5 already exist and work. Plugin baseline: v0.29.1 / schema 1.14.0.*

---

```
TASK: Read this update spec from the folder `stikerts/wordpress v7/` only,
examine the existing plugin codebase, and produce a sprint plan for
implementing make/pack separation. Do NOT write any implementation code
in this task — planning only.

## READ FIRST — in this order

1. 01-Update-Overview.md — scope, assumptions, settled decisions, build order.
2. 02-Update-Data-Model.md — per-line make progress, order pack fields, shipment weight.
3. 03-Update-Make-Workflows.md — per-line make + Make board.
4. 04-Update-Pack-Ship.md — Pack board, checklist, hold, package, print, ship gates.
5. 05-Update-Seed-Migration.md — seed rewrite + migrate notes.

Each of files 02–05 has its own "Open items" section — read those closely.
Settled decisions in 01-Update-Overview.md must not be reopened unless the
codebase makes them impossible.

## THEN — examine the existing codebase

Before planning anything, actually look at the current plugin code to confirm:

- `SOM_DB` schema / `DB_VERSION` and `order_step_progress` shape.
- `SOM_Workflow_Engine::assign_on_create` (primary product rule) and advance paths.
- Orders Board (`som-orders-board`) + order detail workflow UI.
- `SOM_Step_Confirmations` (`packing_items`, `shipping_address`).
- Batches / `thank_you_card` convert-on-activate + seed workflow steps in `SOM_Seed`.
- Shipments (Ship gate) + shipping packages / order planned shipping (Package 5).
- Order notes panel (surface on Pack).
- Internal channel / `SOM_Production` (must stay off Pack board).

If the real code differs meaningfully from what these specs assume, stop and
report the discrepancy before planning further.

## YOUR TASK

1. **Produce a numbered list of every "Open item"** from files 02–05,
   noting which ones block which part of the implementation.

2. **Ask me any clarifying questions** — about the open items, anything
   ambiguous, or anything the existing codebase reveals that these specs
   didn't anticipate. Do not guess silently on anything that changes the
   shape of the code. Where 01-Update-Overview already settled a decision,
   do not re-ask it.

3. **Break this into concrete sprints.** Prefer the recommended order in
   01-Update-Overview (Make line progress + Make board → Pack board/gates →
   Seed/migrate). Each sprint must list:
   - Goal / done-when
   - Files likely touched
   - Schema bump if any
   - Smoke test expectations

4. **Write the sprint plan** to
   `stikerts/wordpress v7/Update-6-Sprint-Plan.md`
   (create it). Do not implement plugin code in this task.

## CONSTRAINTS

- Plain PHP admin UI (no React build) unless I change that.
- Plugin SemVer stays on 0.x during alpha.
- Do not invent product behaviour that contradicts the design docs.
- Pause for my answers on open items before writing the final sprint plan
  if any open item would change schema or UX shape.
```
