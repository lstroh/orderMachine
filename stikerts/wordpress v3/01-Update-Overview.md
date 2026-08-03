# Plugin Update Package 2 — Overview

*Update set 1 of 4 · Combined package covering two additive features: Budgets and Order Board. Self-contained — assumes everything in the original 9-file plan plus Update Package 1 (Raw Material Purchasing + Batch Processing) is already built and working.*

---

## Assumption

The base plugin, plus Update Package 1 (`updates/` folder — Raw Material Purchasing and Batch Processing), are already built and working. In particular, this package assumes:

- Orders, products, materials, `product_materials` recipes, and `order_items` already exist and function.
- The workflow engine (manual-confirm/timer/script/batch gates) and its `/wp-json/som/v1/orders/{id}/advance-step` REST endpoint already exist and work.
- `materials.total_value_on_hand` / weighted-average costing, `purchase_orders` / `purchase_order_items`, and `products.target_selling_price` (all from Update Package 1) already exist and work — **Budgets reuses these directly.**

This package is a **pure additive update** — no existing functionality is removed or reworked.

## What's in this update

1. **Budgets** (`03-Update-Budgets.md`) — one budget per material, automatically funded from actual recipe cost per sale and drawn down on purchase receipt; plus manual budgets funded via a % of price, % of profit, or fixed amount per sale, optionally scoped to specific products. Low-balance and overspent alerts.
2. **Order Board** (`04-Update-Order-Board.md`) — a Kanban card view of orders, alternative to the existing Orders list table: dynamic columns (no fixed stage list, since different products can use different workflows), filters including a "pinned only" focus view, and gated drag-and-drop that calls the existing advance-step endpoint rather than duplicating logic.

## How the two interact

Independent — they don't depend on each other functionally. **Build order between them doesn't matter.**

- Budgets touches the database (new tables) and hooks into two existing trigger points (order creation, purchase receipt).
- Order Board adds **no new database tables at all** — it's a pure UI addition reading existing data and calling the existing advance-step endpoint, plus WordPress user meta for per-user pinned-card state.

Given Order Board has no schema dependency on Budgets (or vice versa), and no shared files beyond the general admin-menu registration, they can genuinely be built in parallel or in either order.

## Full schema change list

Detailed table specs are in `02-Update-Data-Model.md`. Summary:

**New tables (3):** `budgets`, `budget_product_links`, `budget_ledger` (Budgets only)

**Modified existing tables:** none. Order Board requires no schema changes.

**New hooks into existing logic (no schema change, but behavioural):** order-creation flow gains budget-funding logic (alongside its existing material-stock-decrement logic); purchase-receipt flow gains budget-drawdown logic (alongside its existing weighted-average-cost-update logic).

## Files in this package

1. `01-Update-Overview.md` — this file
2. `02-Update-Data-Model.md` — all schema changes, self-contained
3. `03-Update-Budgets.md` — full feature spec
4. `04-Update-Order-Board.md` — full feature spec
5. `05-Update-Cursor-Prompt.md` — kickoff prompt for Cursor to plan and implement this update against the existing codebase
