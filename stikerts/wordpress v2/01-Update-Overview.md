# Plugin Update Package — Overview

*Update set 1 of 4 · Combined package covering two additive features: Raw Material Purchasing and Batch Processing.*

---

## Assumption

The base plugin exists and Phases 1–11 of `05-Implementation-Roadmap.md` are already built and working: order sync (eBay/Etsy), the workflow engine (manual-confirm/timer/script gates), materials with simple stock tracking, product recipes, listings, and the internal REST API. This package is a **pure additive update** on top of that — no existing functionality is removed or reworked, only extended.

## What's in this update

Two features, requested separately but bundled here as one implementation package since you're tackling them together:

1. **Raw Material Purchasing** (`03-Update-Raw-Material-Purchasing.md`) — supplier purchase logging, landed cost, weighted-average material costing, target-selling-price tracking with per-workflow cost-ceiling alerts, and a pre-purchase "preview impact" calculator.
2. **Batch Processing** (`04-Update-Batch-Processing.md`) — a fourth workflow gate type letting orders pool together (default batch of 4, pooled across all workflows) so thank-you card printing and shipping-label grouping happen once per batch instead of once per order.

## How the two interact

They touch different parts of the schema and don't depend on each other functionally, but both extend the **workflow engine** and both extend **materials-related data**:

- Batch Processing adds a `batch_group_id` to `workflow_steps` (a new way a step can gate an order's progress) — independent of anything Raw Material Purchasing does.
- Raw Material Purchasing adds cost/value tracking to `materials` and a new `workflow_material_goals` table that references `workflow_templates` — independent of the batching mechanism.
- The only place they're both "in the room together" is the `materials` table itself and the general shape of `workflow_steps`/`workflow_templates` — but neither feature needs the other to function. **Build order between them doesn't matter** — do either first, or interleave them, without risk of conflict. (Within each feature, follow the internal build order suggested in that feature's own file.)

## Full schema change list (all changes, both features)

Detailed table specs are in `02-Update-Data-Model.md`. Summary:

**New tables (7):** `suppliers`, `purchase_orders`, `purchase_order_items`, `workflow_material_goals` (Raw Material Purchasing) · `batch_groups`, `step_batches`, `step_batch_items` (Batch Processing)

**Modified existing tables (5):** `materials`, `material_stock_log`, `products` (Raw Material Purchasing) · `workflow_steps`, `order_step_progress` (Batch Processing)

## Files in this package

1. `01-Update-Overview.md` — this file
2. `02-Update-Data-Model.md` — all schema changes, new and modified tables, self-contained
3. `03-Update-Raw-Material-Purchasing.md` — full feature spec
4. `04-Update-Batch-Processing.md` — full feature spec
5. `05-Update-Cursor-Prompt.md` — kickoff prompt for Cursor to plan and implement this update against the existing codebase
