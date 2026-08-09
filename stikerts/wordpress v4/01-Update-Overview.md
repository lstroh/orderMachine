# Plugin Update Package 3 — Overview

*Update set 1 of 4 · Combined package covering two additive features: Platform Selling Fees and Analytics Dashboard. Self-contained — assumes the base plugin, Update Package 1 (Raw Material Purchasing + Batch Processing), and Update Package 2 (Budgets + Order Board) are all already built and working.*

---

## Assumption

Everything built so far is in place and working, in particular:

- Order sync (eBay Sell APIs, Etsy API v3), `orders`/`order_items` with real data flowing in.
- `products.target_selling_price`, weighted-average material costing, and the Product Costing view (Update Package 1).
- `budgets`/`budget_ledger` funded from sales (Update Package 2) — Platform Fees doesn't touch this, but is a natural future extension (a "platform fees" manual budget) once this update exists.

This package is a **pure additive update**.

## What's in this update

1. **Platform Selling Fees** (`03-Update-Platform-Fees.md`) — actual per-order fee data pulled from eBay's Finances API and Etsy's Payments/Ledger API (both support this — it's not manual entry), plus Etsy's non-order-linked listing fee tracked as a recurring expense, plus a manually-editable *estimated* fee rate per channel (pre-seeded from your existing `eBay-Marketing-Guide.md`/`Etsy-Marketing-Guide.md` figures) for pricing new products before real sales data exists — with a UI comparison between the estimate and what's actually realized once orders start syncing.
2. **Analytics Dashboard** (`04-Update-Analytics-Dashboard.md`) — charts for sales over time, profit over time, and material stock over time, plus a few standard companion charts (orders by channel, average order value), using Chart.js via CDN — no build step, consistent with the SortableJS approach already used for the Order Board.

Amazon is explicitly **not** covered here, per your answer — this stays eBay/Etsy-only until Amazon is actually built, at which point the same pattern extends via Amazon's own Financials API (SP-API), which works the same way.

## How the two interact

Analytics Dashboard's "profit over time" chart is more accurate once Platform Fees exists (real fee data improves the profit calculation), but it doesn't *require* it — profit can be computed from material cost alone if Platform Fees isn't built yet, and automatically improves in accuracy once it is. **Build Platform Fees first if you want the most accurate profit chart from day one**, but there's no hard blocker either way.

## Full schema change list

Detailed table specs are in `02-Update-Data-Model.md`. Summary:

**New tables (4):** `channel_fee_estimates`, `order_platform_fees`, `recurring_platform_expenses` (Platform Fees) — Analytics Dashboard needs **no new tables**, it's a pure read/aggregate UI layer over existing data.

**New cron job:** `som_sync_platform_fees` — separate from the existing order-sync cron, since fee/transaction data can lag behind order creation (eBay's own API docs note transactions may not appear immediately after payment).

## Files in this package

1. `01-Update-Overview.md` — this file
2. `02-Update-Data-Model.md` — all schema changes, self-contained
3. `03-Update-Platform-Fees.md` — full feature spec
4. `04-Update-Analytics-Dashboard.md` — full feature spec
5. `05-Update-Cursor-Prompt.md` — kickoff prompt for Cursor to plan and implement this update against the existing codebase
