# Plugin Update Package 4 — Overview

*Update set · Combined package covering four additive features: Step Instructions, Order Material Overuse, R&D / Budget operator copy, and Internal Products. Self-contained — assumes the base plugin and Update Packages 1–3 (purchasing, batches, budgets, board, platform fees, analytics) plus post-0.22 extras (shipments, confirmation checklists, live timers) are already built and working.*

---

## Assumption

Everything built so far is in place and working, in particular:

- Order sync, orders list/detail, Orders Board, workflow engine (manual / timer / script / batch / confirmation).
- Products with material recipes; materials with WA costing and stock log; material auto-decrement on new incremental orders (`reason = new_order`).
- Budgets funded from sales (`sale_funding`) and drawn down on PO receive / R&D write-off.
- Analytics order COGS currently derived from `material_stock_log` rows with `reason = new_order` only.
- Thank-you / shipping-label **batch groups** remain the mechanism for personalized thank-you cards on customer orders.

This package is a **pure additive update**. Do not rework unrelated behaviour.

Current plugin baseline at planning time: **v0.23.0**, schema **`som_db_version` 1.10.0**.

## What's in this update

1. **Step Instructions** (`03-Update-Step-Instructions.md`) — plain-text instructions per workflow step, with optional **per-product overrides** when products share a template. Shown read-only on order detail (including production jobs once Internal Products exist).
2. **Order Material Overuse** (`04-Update-Order-Material-Overuse.md`) — on order detail, increase (never decrease) how much of each **recipe** material was actually used; updates stock, order profit/COGS, and budget funding. Recipe catalogue unchanged. Editable anytime, including completed orders. Future cancel reversal must reverse **actual** usage (recipe + extras).
3. **R&D / Budget operator copy** (`05-Update-Rnd-Budget-Copy.md`) — no schema. Clarify in UI/docs that R&D write-off **debits** the material restock pot (sale funds → PO/R&D spends), vs plain Adjust stock which never touches budgets.
4. **Internal Products** (`06-Update-Internal-Products.md`) — products flagged internal: own recipe + workflow + linked output material; make-to-stock via production jobs on the **same** orders list/Board; low-stock and manual Produce N; nesting allowed; never listed on marketplaces; customer consumption funds the linked material budget as today. Personalized thank-you **batch step kept**. Per-order auto-make deferred.

## How the features interact

| Dependency | Note |
|---|---|
| Instructions ↔ Internal Products | Same resolve rules (product override → step default) on production orders; instructions can ship first and automatically apply later. |
| Overuse ↔ Internal Products | Overuse on a **customer** order can increase consumption of a component material (linked output of an internal product) the same as any recipe material. Overuse does **not** edit internal-product recipes. |
| Overuse ↔ Budgets / Analytics | Extends funding + COGS beyond `new_order`-only; must stay consistent with fee-aware profit helpers. |
| R&D copy | Independent; cheapest to ship alongside Overuse or Instructions. |
| Internal Products ↔ Batches | Do not replace `thank_you_card` batch; production jobs use normal workflow steps. |

**Recommended build order**

1. Step Instructions (smallest, isolated schema + UI)
2. Order Material Overuse (+ R&D copy in the same or adjacent sprint)
3. Internal Products (largest; benefits from instructions already displaying on order detail)

## Full schema change list

Detailed specs: `02-Update-Data-Model.md`. Summary:

| Change | Feature |
|---|---|
| `workflow_steps.instructions` TEXT NULL | Step Instructions |
| New table `product_step_instructions` | Step Instructions |
| New stock-log reason(s) + budget funding for extras (may be log-only; optional summary table) | Order Material Overuse |
| `products.is_internal`, `products.linked_material_id` (+ production order discrimination) | Internal Products |
| Materials may gain `source_product_id` (optional inverse of linked material) | Internal Products |

R&D copy: **no schema**.

## Settled product decisions (from planning chat)

Captured so implementers do not re-litigate:

**Overuse:** edit on order detail only; Board opens detail; recipe materials only; increase only; anytime including completed; stock + order profit + budgets; cancel later reverses actuals; no recipe write-back.

**Internal products:** make-to-stock v1; manual Produce N + low-stock trigger; nesting OK; never sellable; same orders list/Board; keep thank-you batch; fund material budget on customer consumption; per-order make = future.

**Instructions:** step default + product override; plain text; order detail only; read-only; applies to internal/production jobs too.

## Files in this package

1. `01-Update-Overview.md` — this file
2. `02-Update-Data-Model.md` — all schema changes
3. `03-Update-Step-Instructions.md` — feature spec
4. `04-Update-Order-Material-Overuse.md` — feature spec
5. `05-Update-Rnd-Budget-Copy.md` — docs/UI copy spec
6. `06-Update-Internal-Products.md` — feature spec
7. `07-Update-Cursor-Prompt.md` — kickoff prompt for Cursor (**planning → sprint plan**, then implement per sprint)
