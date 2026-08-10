# Order Machine — User Reference

*Screen-by-screen reference for operators.*  
*Hub: [`USER-GUIDE.md`](USER-GUIDE.md) · Procedures: [`USER-WORKFLOWS.md`](USER-WORKFLOWS.md)*

Tone: what each screen is for, main actions, important rules, and what you will not find. For QA depth see [`FEATURES-AND-TESTING.md`](FEATURES-AND-TESTING.md).

---

## Contents

1. [Settings & channels](#1-settings--channels)
2. [Order sync behaviour](#2-order-sync-behaviour)
3. [Orders list & detail](#3-orders-list--detail)
4. [Orders Board](#4-orders-board)
5. [Products](#5-products)
6. [Materials & stock](#6-materials--stock)
7. [Workflows](#7-workflows)
8. [Workflow engine (how progress moves)](#8-workflow-engine-how-progress-moves)
9. [Listings](#9-listings)
10. [Suppliers & purchase orders](#10-suppliers--purchase-orders)
11. [Landed cost, WA & goals](#11-landed-cost-wa--goals)
12. [Batches](#12-batches)
13. [Budgets](#13-budgets)
14. [Platform selling fees](#14-platform-selling-fees)
15. [Analytics](#15-analytics)
16. [Automation (REST & MCP)](#16-automation-rest--mcp)
17. [What is out of scope for now](#17-what-is-out-of-scope-for-now)

---

## 1. Settings & channels

**Where:** Order Machine → Settings

**What it is for:** Connect marketplaces, pull orders and fees, tune background intervals, manage API/MCP access, and (in dummy mode) seed data tools.

**Main actions:**

- Save eBay / Etsy app client ID + secret (secrets encrypted; leave blank to keep existing)
- Copy OAuth callback URLs into developer apps (**live**)
- **Connect** / **Disconnect** each channel
- Set **n8n base URL** (for workflow steps that call n8n)
- Intervals: order poll, engine tick, token refresh, fee poll (default 30 min, min 5)
- Toggle **MCP / Abilities**; manage **REST API key**
- **Sync now** — incremental orders (fixtures when dummy)
- **Import history** — 30 or 90 days backfill
- **Platform fee sync** — status, **Sync fees now**, eBay Finances reconnect notice when needed
- **Remove seed data** / **Restore seed data** (restore requires dummy mode)

**Important rules:**

- **Dummy:** connected tokens are fake; Sync uses fixtures.  
- **Live:** real OAuth; changing `SOM_ENCRYPTION_KEY` after saving credentials breaks decrypt — reconnect.  
- Cron still runs in the background; manual Sync buttons are for immediate pulls.

**You will not find:** Per-order editing of channel credentials; exposed raw secrets in the UI after save.

---

## 2. Order sync behaviour

**Where:** Settings → Sync now / Import history (or cron)

**What it is for:** Bring channel orders into Order Machine without duplicates.

**Main behaviour:**

- De-duplicates on channel + external order ID (re-sync updates; does not create duplicates)
- Matches line items via Listings (`external_listing_id` ↔ product)
- Unmatched lines keep no product and are flagged
- Best-effort personalisation text extraction
- On **new incremental creates** (not history, not cancelled): assign workflow, reserve materials, fund matching budgets

**Important rules:**

| Mode | Workflow | Stock reserve | Budget funding |
|---|---|---|---|
| Sync now (new create) | Yes (if primary product has template) | Yes (if recipe matches) | Yes (if budgets match) |
| Import history | No | No | No |
| Cancelled create | No fresh reservation | No | No |

**Dummy fixtures:** roughly half a dozen orders across eBay/Etsy — matched, unmatched, and cancelled mix. Seeded listing IDs commonly include eBay `110000000001` / SKU `BIN-SET-4PK` and Etsy `220000000001`.

---

## 3. Orders list & detail

**Where:** Order Machine → Orders · detail via `order_id`

**What it is for:** Find orders, inspect packing data, advance non-batch steps, see fees and stock impact.

**List — main actions:**

- Filters: status, channel, date range, search
- Badges: open, complete, cancelled, unmatched items, no workflow

**Detail — main sections:**

- Buyer and totals
- Personalisation (prominent when present)
- Shipping address
- Line items (matched product or unmatched warning)
- Workflow progress: current step, timers, scripts, **waiting_batch** badge + Batches link
- **Mark done** when gates allow (hidden while waiting on a batch)
- Material stock impact when reserved
- **Platform fees** panel when synced fee lines exist
- Raw channel payload in a collapsed block

**Important rules:**

- One workflow per order from the **primary product** = first line with a matched product.
- If nothing matches → no progress rows; flags show unmatched / no workflow.
- Batch advance is batch-level only while `waiting_batch`.

---

## 4. Orders Board

**Where:** Order Machine → Orders Board

**What it is for:** Day-to-day production queue as a Kanban of open orders.

**Main actions:**

- View columns by current step name (+ **Unassigned**)
- Reorder columns ←/→ (per-user preference)
- Pin cards; filter Pinned only
- Filter channel / product / workflow / free-text
- Drag advanceable cards to the next step or **Complete** zone
- Open order / product via dedicated links only

**Important rules:**

- Incomplete, non-cancelled only; completed history stays on Orders.
- Only cards that could Mark done are draggable; waiting / error / Unassigned locked.
- Valid drop = next-step column (or Complete on last step); wrong drop snaps back.
- Warn around 200 open matching orders; hard cap 500 (oldest kept).
- Horizontal scroll on narrow screens (no stacked mobile layout).

**You will not find:** Completed orders on the Board; free drag to arbitrary past steps.

---

## 5. Products

**Where:** Order Machine → Products

**What it is for:** Catalogue SKUs that drive matching, recipes, workflows, and costing.

**Main actions:**

- Create / edit name, SKU, active flag (deactivate rather than hard-delete)
- Assign workflow template
- Edit material recipe (material + qty per unit)
- Set target selling price; review **Product Costing** (recipe cost, platform fees £/% estimate vs actual, fee-aware profit/margin, goal alerts, listing prices)
- Follow links to related Listings

**List columns:** target price, material cost, fee-aware margin with Est./Actual fees badge, goal alerts.

**Important rules:**

- Primary product on an order is the first matched line — that product’s workflow applies to the whole order.
- Representative price for fee %: target for estimates; listing price when a channel listing is linked.

---

## 6. Materials & stock

**Where:** Order Machine → Materials

**What it is for:** Track raw materials, costs, stock movements, and purchase history.

**Main actions:**

- CRUD materials (name, unit, stock, low-stock threshold, active)
- Preferred supplier
- Read WA and total value on hand
- **Unit cost override** — revalues value on hand; logs the change
- **Manual stock adjust** — delta; does **not** debit budgets
- **R&D / non-sale write-off** — stock down + budget debit when an active material budget exists (notes required)
- Goal-alert badges; per-workflow breakdown on edit
- Lead time from past POs; purchase history table; recent stock log

**Auto-decrement on new orders:**

- Incremental create of a matched order reduces stock by recipe × qty
- Stock may go **negative** (shortage signal)
- Skipped for history import, cancelled, unmatched-only
- Idempotent per order (re-sync does not double-reserve)

**You will not find yet:** Automatic stock reversal when an order is cancelled later.

---

## 7. Workflows

**Where:** Order Machine → Workflows

**What it is for:** Define production templates and step gates.

**Templates:** create / edit / deactivate; name + description.

**Step editor — main actions:**

- Add / remove / reorder steps
- Requires manual confirm
- Timer (friendly min/hr/day → seconds)
- Script config (local / api / n8n — form + JSON fallback)
- Assign **batch group** (batch-only step in v1; combo with other gates is rejected)
- Template-level **material cost goals**

**Seeded example — Bin Sticker Production:** Print (manual) → Dry (timer) → Laminate → Cut → Pack → Ship → Thank-you (batch `thank_you_card`) → Review (timer + manual). Shipping label batch is opt-in via editor.

---

## 8. Workflow engine (how progress moves)

**What it is for:** Understanding why Mark done / Board drag works or does not.

**Triggers:**

- New order create (incremental) → assign steps from primary product’s template
- Admin Mark done / Board advance when gates allow
- Cron engine tick → unlock timers; attempt due batch script retries
- Batch release / mark-done / script success → advance all batch members

**Gates:**

| Gate | Behaviour |
|---|---|
| Manual | Mark done / drag only when current and allowed |
| Timer | Blocked until countdown ends (or tick unlocks) |
| Script | Allowlisted runner + retries + callback |
| Batch | Entering sets `waiting_batch` and enqueues; other gates ignored on that step in v1 |

**Important:** Workflow + stock run on **new creates**. Orders imported before a product had a workflow/recipe do not get progress or stock retroactively.

---

## 9. Listings

**Where:** Order Machine → Listings

**What it is for:** Cache marketplace listings, link them to products, push updates.

**Main actions:**

- Browse / refresh listings (live API or fixtures)
- Link / unlink to catalogue products
- Push price, quantity, and/or description (variations where supported)

**Important rules:** Linking fixes matching for **future** syncs; already-stored unmatched lines may need separate handling.

---

## 10. Suppliers & purchase orders

**Where:** Order Machine → Suppliers · Purchase Orders

### Suppliers

- Create / edit name, contact, notes
- **No delete** (by design)

### Purchase orders

**Main actions:**

- Create with supplier, dates, shipping/other costs, lines (material, qty, item cost GBP)
- **Preview Impact** before/while editing (no DB stock write)
- **Receive** deltas per line (partial / full / over-receive)
- **Mark received** (shortfall close) / **Cancel** (no reverse of already-received stock)
- Edit notes after lock; lines/costs lock after first receive

**Status ideas:** ordered → partially_received / received / cancelled / manually closed.

**Important rules:**

- Stock rises on receive; active material budgets draw down by landed delta on receive.
- Mark received shortfall does **not** draw down for missing qty.
- Duplicate materials on one PO are allowed.

---

## 11. Landed cost, WA & goals

**What it is for:** Understand cost numbers after purchasing.

**Rules (GBP):**

- Shipping/other allocated across lines by item cost
- Inbound unit cost from ordered qty (not cumulative received qty)
- After receive, new WA written into the material’s unit cost
- If total line item cost is 0 → no shipping/other allocation; warning shown
- Post-receive PO edits do **not** rewrite past WA — correct via adjustment / unit-cost override

**Goals:** Per workflow template + material; approaching / over after WA changes. Surfaces on Materials, Product Costing, post-receive notices. No separate dashboard cost-alerts widget yet.

---

## 12. Batches

**Where:** Order Machine → Batches

**What it is for:** Multi-order steps (thank-you script, shipping-label confirm).

**Seeded groups (editable name/size; key + action type fixed):**

| Group key | Action | Default size |
|---|---|---|
| `thank_you_card` | Script (e.g. 4-up thank-you) | 4 |
| `shipping_label` | Manual confirm | 4 |

**States:** collecting → ready → (processing for scripts) → done / error

**Main actions:** Release, Mark done (manual groups), Retry (error), expand members, edit group display name/size.

**Important rules:**

- Same batch group pools **across** workflows
- Cancelled members may stay listed but are skipped on advance
- Order Mark done is hidden while `waiting_batch`

---

## 13. Budgets

**Where:** Order Machine → Budgets

**What it is for:** Pots of money funded from sales and spent on purchases / R&D.

### Types

| Type | Funding | Scope |
|---|---|---|
| **Material** | Consumption cost on new orders | Optional workflow list (empty = all); **one budget per material** |
| **Manual** | % of price, % of profit (fee-aware), or fixed per unit | Optional product list (empty = all) |

**Main actions:**

- Create / edit (type and linked material immutable after create)
- Soft-deactivate
- View ledger (sale_funding, purchase_spend, adjustments)
- Manual adjustment (notes required)
- R&D write-off (also on Materials)

**Important rules:**

- Funding runs with stock on incremental create; skipped for history / cancelled / inactive / already funded
- Sold price 0 on a line is treated as 0 (does not fall back to target); empty price can fall back to target
- `% of profit` may fund a **negative** amount on loss
- PO receive draws material budgets; Mark received shortfall does not
- Negative balances allowed
- Plain Adjust stock never touches budgets

---

## 14. Platform selling fees

**Where:** Channel Fee Estimates · Recurring Platform Expenses · Settings (fee sync) · order detail · Product Costing / Budgets / Analytics consumers

### Channel Fee Estimates

- Seeded eBay/Etsy defaults (do not overwrite your edits)
- Components: percents, fixed amounts, optional order-value tiers, enable/disable
- eBay examples: final value %, tiered per-order fee, regulatory %, Promoted Listings % (on by default)
- Etsy examples: listing fee, transaction %, payment processing, VAT-on-fees, Offsite Ads % (on by default)

### Fee sync

- Separate from order sync (own button + cron + cursor)
- Receipt-linked → per-order platform fees; unmatched listing-style → recurring expenses
- Amounts treated as GBP; no FX
- **Live eBay:** Finances scope required — reconnect if warned
- **Dummy:** fee fixtures

### Shared math

- Per order: **actual** fee totals if rows exist, else **estimate**
- Costing shows £ and % (no single opaque “effective rate” product)
- Budgets `% of profit` and Analytics profit reuse the same helpers
- Recurring expenses are **out** of profit charts / costing order profit

---

## 15. Analytics

**Where:** Order Machine → Analytics

**What it is for:** Live charts for ops review (no pre-computed summary tables in v1).

| Chart | Notes |
|---|---|
| Sales over time | Revenue from line `unit_price` × qty only (no target fallback) |
| Profit over time | Revenue − material COGS from new_order stock log − platform fees (estimate→actual). Recurring expenses excluded |
| Material stock over time | Multi-select materials + Apply; reconstructed from current stock + log |
| Orders by channel | Counts in range |
| Average order value | Revenue ÷ priced-order count |

**Filters (shared):** date range, granularity, channel, material multi-select. Site timezone → UTC query window.

**Exclusions:** cancelled + best-effort refunded. Chart.js loaded from CDN (no build step).

---

## 16. Automation (REST & MCP)

**Where:** Settings (API key, MCP toggle) · details in [`MCP.md`](../../MCP.md)

**What operators need to know:**

- REST under `som/v1` supports automation (orders, purchasing, batches, etc.) with API key or admin auth.
- Channel credentials are **never** exposed via REST or MCP.
- MCP Abilities are **read-only** and only register when the MCP toggle is on.
- Day-to-day admin UI stays on form POST / admin-ajax; REST is parallel for automation / AI tools.

This reference is not a full API manual — use developer docs and MCP.md when wiring integrations.

---

## 17. What is out of scope for now

So you do not hunt for missing features:

| Deferred | Operator note |
|---|---|
| Cancel → stock reversal | Cancelled status shows; stock not put back automatically yet |
| Dashboard cost-alerts widget | Use Materials + Product Costing badges |
| Multi-currency / FX | GBP only |
| Rewrite received POs’ WA | Adjust stock / unit cost instead |
| Batch + timer/script/manual on one step | Batch-only steps in v1 |
| Completed orders on Board | Use Orders list |
| Amazon fee sync | eBay/Etsy only |
| Recurring expenses in profit charts | Order fees only |
| Dedicated ink type | Recipe material or manual fixed budget |
| Stacked mobile Board | Horizontal scroll only |

---

*End of operator reference. Procedures live in [`USER-WORKFLOWS.md`](USER-WORKFLOWS.md).*
