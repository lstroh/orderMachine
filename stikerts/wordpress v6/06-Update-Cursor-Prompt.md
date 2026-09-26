# Cursor Kickoff Prompt — Plugin Update 5

*Paste this into Cursor as the first prompt after the Package 5 design docs exist. It assumes the base plugin and Update Packages 1–4 already exist and work. Plugin baseline: v0.27.0 / schema 1.12.0.*

---

```
TASK: Read this update spec from the folder `stikerts/wordpress v6/` only,
examine the existing plugin codebase, and produce a sprint plan for
implementing three additive features. Do NOT write any implementation code
in this task — planning only.

## READ FIRST — in this order

1. 01-Update-Overview.md — what this update covers, assumptions, settled
   decisions, recommended build order, and how the features interact.
2. 02-Update-Data-Model.md — schema for order notes, shipping packages,
   product/order planned shipping; multipacks have none.
3. 03-Update-Order-Notes.md — admin-only threaded log on order detail.
4. 04-Update-Shipping-Packages.md — package catalogue, goods weight,
   flat planned postage on product + order; actual = existing shipment.
5. 05-Update-Multipacks.md — separate SKUs only; conventions/docs.

Each of files 02–05 has its own "Open items" section — read those closely.
Settled decisions in 01-Update-Overview.md must not be reopened unless the
codebase makes them impossible.

## THEN — examine the existing codebase

Before planning anything, actually look at the current plugin code to confirm:

- `SOM_DB` schema versioning / `dbDelta` patterns and current `DB_VERSION`.
- Order detail view + `SOM_Admin_Menu` order save handlers (notes + planned shipping).
- `SOM_Order_Sync` create path (where to seed `planned_shipping_gbp`).
- `som_shipments` / shipment admin UX (`postage_paid` = actual).
- `SOM_Products::recipe_costing` / Product Costing panel (add planned shipping line).
- Product edit form + admin menu product save.
- Whether any REST/MCP order serializers would accidentally expose notes
  (must remain admin-only).

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
   01-Update-Overview (Order Notes → Shipping packages/planned postage →
   Multipack docs/seed). Each sprint must list:
   - Goal / done-when
   - Files likely touched
   - Schema bump if any
   - Smoke test expectations

4. **Write the sprint plan** to
   `stikerts/wordpress v6/Update-5-Sprint-Plan.md`
   (create it). Do not implement plugin code in this task.

## CONSTRAINTS

- Plain PHP admin UI (no React build) unless I change that.
- Plugin SemVer stays on 0.x during alpha.
- Do not invent product behaviour that contradicts the design docs.
- Pause for my answers on open items before writing the final sprint plan
  if any open item would change schema or UX shape.
```
