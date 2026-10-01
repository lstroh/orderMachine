# Plugin Update Package 7 — Overview

*Update set · Pack / order-level materials (thank-you, packaging) + Make step gate / confirmation UX fixes from operator testing. Self-contained — assumes the base plugin and Update Packages 1–6 (including make/pack split, shipping packages, material overuse, budgets) are already built and working.*

---

## Assumption

Everything built so far is in place and working, in particular:

- Per-line **make** progress + order-level **Pack** workflow (Package 6).
- Product **recipes** reserve materials on incremental create (`new_order`); order detail **Materials used** is increase-only overuse.
- **Shipping packages** catalogue = dims / tare / planned postage only — **not** stocked materials.
- Pack checklist includes **thank-you included** (once per order pack) but does **not** consume thank-you cardstock.
- Workflow steps with **no gates** (no manual confirm, no confirmation checklist, no timer, no script, no batch) **auto-complete** on enter (“zero-gate”).

Current plugin baseline at planning time: **v0.32.2**, schema as of Package 6 complete.

## Why this package

1. **Thank-you / packaging is one-per-pack, not per product unit.** Putting “1 thank-you card” on a product recipe burns N cards when qty = N. Operators need **order/pack-level** material plans (and the same **Materials used** actuals path).
2. **Operator confusion / bug:** Make step “Create PDF” shows **Done** before work starts while a confirmation checklist appears for a later step; stale Make-panel copy still mentions UP6-S2.

## What's in this update

1. **Pack / order-level materials** (`03-Update-Pack-Order-Materials.md`) — define materials needed **once per pack** (and optionally per selected shipping package); reserve stock; show planned vs actual on order detail; overuse same rules as recipe materials.
2. **Make step gate & confirmation UX** (`04-Update-Make-Step-Gate-UX.md`) — fix / clarify zero-gate auto-complete; nest checklist under the current step; remove stale UP6-S2 copy; workflow-editor warnings for ungated steps.
3. **Data model** (`02-Update-Data-Model.md`) — schema for pack/package material recipes + stock-log reasons if needed.

## How the features interact

| Dependency | Note |
|---|---|
| Product recipe | Unchanged for **make** BOM (vinyl × qty, etc.). |
| Pack materials | Fixed qty **per order pack** (not × line qty). Thank-you cardstock lives here. |
| Shipping package materials | Optional add-on when a package is selected (e.g. 1× C5 mailer). |
| Materials used | One panel: recipe lines + pack/package lines; increase-only Actual. |
| Budgets / COGS | Pack reserves and extras should fund / cost like recipe usage (details in feature doc). |
| Pack checklist | “Thank-you included” stays a **process** tick; stock is separate. |
| Internal Produce N | No pack materials (no Pack path). |

## Recommended build order

1. Bug / UX (small, unblocks trust in Make UI) — UP7-S1  
2. Pack materials schema + reserve + Materials used — UP7-S2  
3. Shipping-package materials + seed/docs/costing polish — UP7-S3  

## Out of scope (this package)

- Using **less** than planned (still increase-only)  
- Writing pack usage back into product recipes  
- Multi-carton / split packs  
- Auto-generating thank-you PDF as a stock gate  
- Changing make/pack board column models  

## Files in this package

1. `01-Update-Overview.md` (this file)  
2. `02-Update-Data-Model.md`  
3. `03-Update-Pack-Order-Materials.md`  
4. `04-Update-Make-Step-Gate-UX.md`  
5. `Update-7-Sprint-Plan.md`  
6. `05-Update-Cursor-Prompt.md`  
