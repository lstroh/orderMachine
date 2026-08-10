# Order Machine — Features & Testing Guide

*Review guide for everything shipped through base Sprints 1–11, Update Package 1 (U1–U7), Update Package 2 (U2-1–U2-5), **and** Update Package 3 (UP3-S1–S4) — plugin **v0.22.0**, schema **1.8.0**.*  
*Companions: [`Sprint-Plan.md`](Sprint-Plan.md), [`Sprint-Progress.md`](Sprint-Progress.md), [`../wordpress v2/Update-Sprint-Plan.md`](../wordpress%20v2/Update-Sprint-Plan.md), [`../wordpress v2/Update-Sprint-Progress.md`](../wordpress%20v2/Update-Sprint-Progress.md), [`../wordpress v3/Update-2-Sprint-Plan.md`](../wordpress%20v3/Update-2-Sprint-Plan.md), [`../wordpress v3/Update-2-Sprint-Progress.md`](../wordpress%20v3/Update-2-Sprint-Progress.md), [`../wordpress v4/Update-3-Sprint-Plan.md`](../wordpress%20v4/Update-3-Sprint-Plan.md), [`../wordpress v4/Update-3-Sprint-Progress.md`](../wordpress%20v4/Update-3-Sprint-Progress.md).*

---

## At a glance

Order Machine is a WordPress plugin that pulls orders from eBay/Etsy (or fixture data), matches them to your product catalogue, tracks production through a workflow (including batch gates and an Orders Board), reserves materials when new orders arrive, tracks raw-material purchasing with landed-cost / weighted-average costing, maintains material / manual budgets funded from sales and drawn down on PO receive, estimates and syncs platform selling fees, and charts sales / fee-aware profit / stock on an Analytics dashboard.

| Area | Status |
|---|---|
| Database schema (25 `wp_som_*` tables) | Done |
| Channel settings + OAuth / dummy credentials | Done |
| Order sync (incremental + history import) | Done |
| Orders list + detail UI | Done |
| Orders Board (Kanban + gated DnD) | Done (U2-4 / U2-5) |
| Products, materials, recipes | Done |
| Workflow templates + step editor | Done |
| Workflow engine (manual + timer + script + batch) | Done |
| Material auto-decrement on new orders | Done (cancel reversal deferred) |
| Script / n8n / local actions execution | Done (Sprint 9) |
| Listings push | Done (Sprint 10) |
| REST API + MCP Abilities | Done (Sprint 11; enriched in U7 + UP3-S3) |
| Suppliers + purchase orders (receive / close) | Done (Package 1 U2) |
| Landed cost, WA, goals, product costing UI | Done (Package 1 U3–U4; fee-aware in UP3-S3) |
| Batch processing engine + Batches admin | Done (Package 1 U5–U6) |
| Purchasing / batch REST + Abilities | Done (Package 1 U7) |
| Budgets (schema, funding/draw-down, admin UI) | Done (U2-1–U2-3; fee-aware profit in UP3-S3) |
| Channel fee estimates + actual fee sync | Done (UP3-S1–S2) |
| Recurring platform expenses UI | Done (UP3-S2) |
| Analytics Dashboard (Chart.js) | Done (UP3-S4) |

**Recommended review path:** use **wp-env** for a clean, repeatable fixture walkthrough, then spot-check the same screens on your **Local** `ordermachine` site if you use that day-to-day.

---

## 1. How to get a test environment

### Option A — wp-env (recommended for this review)

Requires Docker Desktop + Node/`npx`. From the plugin root:

```bash
npx @wordpress/env start
```

| | |
|---|---|
| Admin | http://localhost:8888/wp-admin |
| Login | `admin` / `password` |
| Dummy mode | On via `.wp-env.json` (`SOM_USE_DUMMY_CREDENTIALS=true`) |

Dummy mode auto-seeds:

- Encrypted fake eBay + Etsy credentials
- Sample product **BIN-SET-4PK** with vinyl + laminate recipe
- Listing matches so some fixture lines resolve to that product
- Workflow template **Bin Sticker Production** (8 steps) assigned to the product
- Batch groups **thank_you_card** (script) and **shipping_label** (manual_confirm), both size 4
- Thank-you steps auto-converted to `batch_group_id` (per-order thank-you `script_config` cleared)

More commands: [`WP-ENV.md`](../../WP-ENV.md). Clean reset: `npx @wordpress/env destroy` then `start` again.

### Option B — Local site (`ordermachine`)

1. Open the Local site → wp-admin → **Plugins** → activate **Order Machine**.
2. Without dummy mode you need real eBay/Etsy developer app keys on **Settings**, then Connect + Sync.
3. To mirror the fixture path on Local, add to `wp-config.php` (above “That’s all, stop editing!”):

```php
define( 'SOM_USE_DUMMY_CREDENTIALS', true );
define( 'SOM_ENCRYPTION_KEY', 'your-local-dev-key-here' ); // optional but recommended
```

Deactivating the plugin does **not** delete data. Uninstall also keeps `som_*` tables/options by design.

---

## 2. Admin map

Top-level menu: **Order Machine** (capability: `manage_options`).

| Screen | URL slug | Purpose |
|---|---|---|
| **Orders** | `som-orders` | List, filters, badges; open a row for detail |
| Order detail | `som-orders&order_id=N` | Buyer, personalisation, address, items, workflow, stock, batch link |
| **Orders Board** | `som-orders-board` | Kanban of open orders by current step; pins, filters, gated drag-and-drop |
| **Products** | `som-products` | Catalogue; edit SKU, workflow, recipe, Product Costing |
| **Materials** | `som-materials` | Stock, WA / value on hand, preferred supplier, goal badges, PO history, R&D write-off |
| **Budgets** | `som-budgets` | Material + manual budgets; balances, ledger, adjustments, R&D write-off |
| **Suppliers** | `som-suppliers` | Supplier CRUD (no delete) |
| **Purchase Orders** | `som-purchase-orders` | Create/edit POs, Preview Impact, receive, mark-received / cancel |
| **Batches** | `som-batches` | Open batches list; release / mark done / retry; edit batch groups |
| Batch deep-link | `som-batches&batch_id=N` | Scrolls/expands that batch |
| **Workflows** | `som-workflows` | Templates + step editor (gates, batch group, material cost goals) |
| **Listings** | `som-listings` | Cached marketplace listings; refresh + push price/qty/description |
| **Analytics** | `som-analytics` | Sales / profit / stock / orders-by-channel / AOV charts (Chart.js) |
| **Channel Fee Estimates** | `som-channel-fee-estimates` | Per-channel estimated fee components (tiers, optional ads) |
| **Recurring Platform Expenses** | `som-recurring-platform-expenses` | Non-order-linked fees (e.g. Etsy listing fees) |
| **Settings** | `som-settings` | Channels, intervals, Sync now, Import history, fee sync, MCP toggle, API key |

---

## 3. Features — what they do and how to use them

### 3.1 Settings & channels

**Where:** Order Machine → Settings

**What you can do:**

- Save eBay / Etsy app client ID + secret (secrets stored encrypted; leave blank to keep existing)
- See OAuth callback URLs for each channel
- **Connect** / **Disconnect** eBay and Etsy (live OAuth on Local with real apps; wp-env uses dummy tokens)
- Set **n8n base URL** (used when a step’s `script_config` type is `n8n`)
- Configure intervals: order poll, engine tick, token refresh, **fee poll** (default 30 minutes, min 5)
- Toggle **MCP / Abilities** registration and manage the **REST API key**
- **Sync now** — incremental pull (fixtures when credentials are dummy)
- **Import history** — 30 or 90 days (history creates orders but skips workflow assignment and stock reservation)
- **Platform fee sync** — last-run / cursor status, **Sync fees now**, reconnect notice when live eBay token lacks Finances scope
- **Remove seed data** / **Restore seed data** — clear or recreate the demo catalogue + dummy tokens (Settings → Seed data). Restore requires `SOM_USE_DUMMY_CREDENTIALS`. Does not delete your own products, suppliers, or POs.

**Background jobs (WP-Cron):**

| Hook | Role |
|---|---|
| `som_sync_orders` | Periodic incremental sync |
| `som_sync_platform_fees` | Pull eBay Finances / Etsy Ledger fee lines (separate from order sync) |
| `som_engine_tick` | Unlock timer steps; attempt due batch script retries |
| `som_refresh_tokens` | Refresh real OAuth tokens (skips dummy) |
| `som_batch_attempt` | Batch-unit script retry / backoff |

---

### 3.2 Order sync

**Where:** Settings → Sync now / Import history (or wait for cron)

**Behaviour:**

- De-duplicates on `channel_id` + `external_order_id` (re-sync updates; does not create duplicates)
- Stores full channel payload in `raw_payload`
- Matches line items via `wp_som_listings` (`external_listing_id` ↔ `product_id`)
- Unmatched lines keep `product_id = NULL` and are flagged in the UI
- Best-effort personalisation text extraction into `personalisation_text`
- On **new** incremental creates (not history import, not cancelled): assigns workflow + reserves materials + funds matching budgets (`SOM_Budgets::fund_on_create`)

**Fixture set (dummy mode):** ~6 orders across eBay/Etsy — mix of matched, unmatched, and cancelled.

| Fixture listing IDs (seeded matches) | |
|---|---|
| eBay | `110000000001` and SKU `BIN-SET-4PK` |
| Etsy | `220000000001` |

---

### 3.3 Orders list & detail

**Where:** Order Machine → Orders

**List:**

- Filters: status, channel, date range, search
- Badges / flags: open, complete, cancelled, unmatched items, no workflow

**Detail (open an order):**

- Buyer and totals
- Personalisation (front-and-centre when present)
- Shipping address
- Line items (matched product or unmatched warning)
- Workflow progress: current step, timers, scripts, **waiting_batch** badge + link to Batches
- **Mark done** when allowed (hidden/disabled while status is `waiting_batch` — advance is batch-level only)
- Material stock impact for this order (when reserved)
- **Platform fees** panel — itemized actual synced fee lines when present (after fee sync)
- Raw channel payload in a collapsed `<details>` block

**Workflow rules on the order:**

- One workflow per order, from the **primary product** = first line item with a non-null `product_id`
- If nothing matches → no progress rows; UI shows no-workflow / unmatched flags

---

### 3.4 Products

**Where:** Order Machine → Products → Add / edit

**What you can do:**

- Create / edit name, SKU, active flag
- Assign a **workflow template**
- Edit the **material recipe** (material + quantity per unit)
- Set **target selling price** and review the **Product Costing** panel (recipe material cost, **platform fees £ + %** per channel estimate vs actual, fee-aware profit / margin, goal alerts, listing prices side by side)
- See linked listings (links through to **Listings** admin)

**Products list** also shows costing columns: target price, material cost, **fee-aware margin** with Est. fees / Actual fees badge, goal-alert badges.

Deactivate rather than hard-delete (soft inactive).

---

### 3.5 Materials & stock

**Where:** Order Machine → Materials

**What you can do:**

- CRUD materials (name, unit, stock, low-stock threshold, active)
- Set **preferred supplier**
- See **weighted average (WA)** and **total value on hand** (read-only)
- Edit **unit cost** as an explicit override — revalues `total_value_on_hand = current_stock × unit_cost` and writes a stock-log row
- **Manual stock adjust** (positive or negative delta) → `material_stock_log` reason `manual_adjustment` (also maintains value fields). Does **not** debit a material budget — use R&D write-off for linked stock + budget debit
- **R&D / non-sale write-off** — separate action: decrements stock and, if an active material budget exists, debits it by `qty ×` WA unit cost (`manual_adjustment` ledger); notes required
- Goal-alert **badges** on the list; full per-workflow breakdown on edit
- Average **lead time** (overall, from past PO `received_date − order_date`)
- Dedicated **purchase history** table on edit (date, supplier, qty, landed unit cost, link to PO)
- Recent stock-log entries on the edit screen
- Low-stock highlighting on the list

**Auto-decrement (Sprint 8 + U3 value path):**

- When a **new** matched order is created by incremental sync, stock decreases per recipe × quantity
- Log reason: `new_order`; also updates `total_value_on_hand` / `unit_cost_at_time` / `value_change` using current WA (falls back to `unit_cost`, else `0`, when WA undefined)
- Stock may go negative (by design — signals shortage)
- Skipped for: history import, cancelled orders, unmatched-only orders
- Idempotent per order (won’t double-reserve on re-sync)
- Same create path also funds budgets when stock is applied (see §3.13)

**Not implemented yet:** stock reversal when an order is later cancelled (`order_cancelled`). Cancel status is stored/detected for display, but reversal is deferred until channel cancel fields are confirmed (open items D3 / A3).

---

### 3.6 Workflow templates & step editor

**Where:** Order Machine → Workflows

**Templates:** create / edit / deactivate; name + description.

**Steps (editor):**

- Add / remove / reorder
- Toggle **requires manual confirm**
- Set **timer** (seconds via friendly min/hr/day UI)
- Configure **script_config** (form fields + raw JSON fallback for `local` / `api` / `n8n`)
- Assign a **batch group** (`thank_you_card` or `shipping_label`) — batch-only steps for v1 (combo with manual/timer/script shows a warning; save is still rejected by validation)

**Material cost goals (template-level section on the editor):**

- Per-material target / approaching thresholds for this workflow
- Saved with the template (`sync_for_workflow`); alerts surface on Materials + Product Costing after PO receives change WA

**Seeded template — Bin Sticker Production:**

| # | Step | Gates |
|---|---|---|
| 1 | Print | Manual confirm |
| 2 | Dry | Timer 15 minutes |
| 3 | Laminate | Manual confirm |
| 4 | Cut | Manual confirm |
| 5 | Pack | Manual confirm |
| 6 | Ship | Manual confirm |
| 7 | Thank-you | Batch group `thank_you_card` (auto-converted on activate; script runs once for the whole batch) |
| 8 | Review reminder | Timer 7 days + manual confirm |

Opt-in: assign **shipping_label** to a Ship (or other) step via the editor — no bulk convert of existing Ship steps.

---

### 3.7 Workflow engine

**Triggers:**

- New order create (incremental sync) → assign steps from primary product’s template
- Admin **Mark done** on the current step (when gates allow; not while `waiting_batch`)
- Cron `som_engine_tick` → unlock timer steps; attempt due batch retries
- Batch release / mark-done / script success → advance **all** batch members

**Gates:**

- Manual confirm: Mark done enabled only when that step is current and confirmed by you
- Timer: Mark done disabled until the countdown finishes (or tick unlocks it)
- Script (`local` / `api` / `n8n`): allowlisted runner + retries + REST callback (Sprint 9)
- Batch: entering a step with `batch_group_id` sets progress to `waiting_batch` and enqueues into a collecting batch (other gates ignored on that step in v1)

**Important for testing:** workflow + stock run on **new creates**. If fixtures were already imported **before** the product had a workflow/recipe, those old rows won’t retroactively get progress or stock. Fix: destroy/reseed wp-env, or sync after seed is in place (fresh env does this automatically).

---

### 3.8 Listings

**Where:** Order Machine → Listings

**What you can do:**

- Browse cached channel listings (refreshed from eBay/Etsy or fixtures)
- Link / unlink to catalogue products
- Push price, quantity, and/or description updates (including variations where supported)

---

### 3.9 Suppliers & purchase orders

**Where:** Order Machine → Suppliers / Purchase Orders

**Suppliers:**

- Create / edit name, contact details, notes
- **No delete** (by design)

**Purchase orders:**

- Create PO with supplier, order date, shipping cost, other cost, line items (material, qty ordered, `item_cost` = total line cost in GBP)
- Status machine: `ordered` → `partially_received` / `received` / cancelled / manually closed
- **Receive:** enter **delta this shipment** per line; short receipt → `partially_received`; all lines `quantity_received >= quantity_ordered` → `received` (over-receive OK; 0 delta skips a line)
- Later receives allowed while `ordered` / `partially_received`; `received_date` overwritten on every successful receive
- **Mark received** accepts shortfall; **Cancel** closes without reversing already-received stock
- Edit lock: full edit while `ordered` with no receipts; after first receive, lines/costs lock (notes still editable)
- Duplicate materials on one PO OK
- Stock rises with log reason `purchase_received` (+ `purchase_order_item_id`)
- After a successful stock adjust on a receive line, matching **material budgets** are drawn down (`purchase_spend` = `−(delta × landed_unit_cost)`). `Mark received` shortfall does **not** draw down

**Preview Impact (create/edit only):**

- Admin-ajax from current (possibly unsaved) form fields
- Shows projected WA, goal alerts, and product margin impact **without** writing the DB

---

### 3.10 Landed cost, weighted average & goals

**Costing rules (GBP only):**

- Full PO `shipping_cost` / `other_cost` allocated across lines by `item_cost` (stored as `allocated_shipping` / `allocated_other_cost`)
- Stable inbound unit: `(item_cost + allocated_*) / quantity_ordered` — used for WA updates and stored `landed_unit_cost` (not cumulative `/ quantity_received`)
- Each shipment: `value_change = delta ×` that unit; after receive, new WA is written into `materials.unit_cost`
- If total line `item_cost` is 0 → do **not** allocate shipping/other; surface a warning
- Purchase log `unit_cost_at_time` = inbound landed; consumption / manual = current WA
- Post-receive PO edits do **not** rewrite WA — corrections via separate stock/value adjustment (or unit-cost override revalue)

**Goals / alerts:**

- Defined per workflow template + material
- Levels approaching / over fire after receives change WA
- Surfaces: Materials list badges, material edit breakdown, Product Costing, post-receive success notice
- Dashboard cost-alerts widget is **out of scope** for this update

---

### 3.11 Batch processing

**Where:** Order Machine → Batches (`?batch_id=N` deep-links)

**Batch groups (seeded, editable name / `batch_size`; `group_key` + `action_type` fixed):**

| Group key | Action | Default size |
|---|---|---|
| `thank_you_card` | Script (multi-order thank-you CLI, 4-up PDF) | 4 |
| `shipping_label` | Manual confirm | 4 |

**State machine:** `collecting` → `ready` → (`processing` for scripts) → `done` / `error`

**Behaviour:**

- Orders with the same `batch_group_id` pool **across workflows**
- Auto-ready when size reached; manual **Release** on `collecting`
- Script groups: same request runs `ready` → `processing` → script once for all members, then advances all
- Manual-confirm groups: wait for **Mark done** on the batch
- On batch `error`, member progress flips to `error` (copied `last_error`); **Retry** resets budget and re-enters processing
- Cancelled orders stay in the collecting/ready batch (not removed); they are skipped on advance
- List defaults to open statuses only (`collecting` / `ready` / `processing` / `error`); optional include-done
- Expand a batch for members (name + order ref collapsed; address on expand)
- Edit group display name / batch size on the same page

---

### 3.12 REST API & MCP Abilities

**Auth:** API key or admin (`check_api_key_or_admin`) for mutating / sensitive routes. Channel credentials are never exposed.

**Core routes (Sprint 11):** `POST /som/v1/orders`, `POST /som/v1/orders/{id}/advance-step`, workflow callback.

**Update routes (U7):**

| Route | Methods |
|---|---|
| `/suppliers`, `/suppliers/{id}` | GET, POST, PUT (no DELETE) |
| `/purchase-orders`, `/purchase-orders/{id}` | GET, POST, PUT |
| `/purchase-orders/preview` | POST (unsaved body) |
| `/purchase-orders/{id}/receive` | POST |
| `/purchase-orders/{id}/mark-received` | POST |
| `/purchase-orders/{id}/cancel` | POST |
| `/workflow-material-goals`, `/workflow-material-goals/{id}` | GET, POST, PUT, DELETE |
| `/batches`, `/batches/{id}` | GET |
| `/batches/{id}/release`, `…/mark-done`, `…/retry` | POST |
| `/batch-groups`, `/batch-groups/{id}` | GET only |

**Abilities (read-only, MCP-toggle gated):** existing order/product/material reads plus `get-suppliers`, `get-purchase-orders`, `get-purchase-order`, `get-workflow-material-goals`, `get-batches`, `get-batch`, `get-batch-groups`. Enriched: materials (WA, value, preferred supplier, alert), products (target price, recipe cost, fee-aware margin/profit, `platform_fees`, `fee_source`).

Admin UI stays on form POST / admin-ajax; REST is parallel for automation / MCP / future UI.

---

### 3.13 Budgets

**Where:** Order Machine → Budgets (submenu immediately after Materials)

**Schema (Package 2 → `1.6.0`; still present under current `1.8.0`):**

| Table | Role / constraints |
|---|---|
| `wp_som_budgets` | Material or manual; `is_active`; **`UNIQUE` on `material_id`** (one material budget per material; MySQL allows multiple NULLs for manuals) |
| `wp_som_budget_product_links` | Manual product scope; **`UNIQUE (budget_id, product_id)`** |
| `wp_som_budget_workflow_links` | Material workflow scope; **`UNIQUE (budget_id, workflow_template_id)`** |
| `wp_som_budget_ledger` | Sole balance mutator; keys on `budget_id`, `order_id`, `purchase_order_item_id` |

Cross-type link rows are **allowed in the DB**; the admin UI only offers the intended combinations (workflow on material, products on manual).

**Types:**

| Type | Funding | Scope |
|---|---|---|
| **Material** | Always `material_cost` — funds from consumption cost on new orders (`\|change_qty\| × unit_cost_at_time` from `new_order` stock-log lines) | Empty workflow links = **global**; one or more workflow-template checkboxes = fund only when the order’s primary-product workflow is in the set. **One material budget per material**. Scope is **order-level**: the primary product’s workflow gates **all** material funding on that order |
| **Manual** | `percent_of_price`, `percent_of_profit`, or `fixed_amount` per unit | Empty product links = all products; else product-scoped. **Workflow links ignored** for funding |

**Behaviour:**

- Sale funding runs after stock decrement on incremental create (`$apply_stock=true`); skipped for history import, cancelled orders, inactive budgets, and when any `sale_funding` ledger row already exists for that order (idempotent)
- Effective sold price for % methods: `order_items.unit_price` if set (**including 0** — treated as sold price 0), else `products.target_selling_price` (only `NULL`/empty falls back)
- **`percent_of_profit`** uses the same **estimate → actual** platform-fee rule as Costing / Analytics (`SOM_Platform_Fees::line_profit`): revenue − materials − fees. May fund a **negative** amount on loss (not clamped to 0). Other funding methods (`percent_of_price`, `fixed_amount`, material `material_cost`) are unchanged
- **Ledger grain:** material → one `sale_funding` row per `new_order` stock-log material line; manual → one `sale_funding` row per order item × matching budget
- PO **receive** draws active material budgets by landed delta (`purchase_spend` = `−(delta × landed_unit_cost)`); draw-down is by PO-line `material_id` (workflow scope applies to **funding only**). If a ledger write fails after stock succeeded, the error is **logged and receive continues** on remaining lines (stock is kept; fix budget via manual adjustment if needed)
- `Mark received` shortfall close does **not** draw down
- `current_balance` mutates **only** via ledger rows; create starts at `0`; negative balances allowed (same signal style as negative stock)
- Soft-deactivate via `is_active` (no hard-delete of budgets with history in v1)
- After create, **type** and **`material_id`** are immutable; name, notes, target reserve, `is_active`, manual funding method/value + product links, and material workflow links remain editable

**Admin UI:**

- List defaults to **active**; filters status / type / search; **low balance** (balance &lt; target reserve) and **overspent** (balance &lt; 0) badges
- Create material: picker hides materials that already have a budget; optional workflow checkboxes; short ink tip (ops: track ink via recipe material or a manual `fixed_amount` budget)
- Create/edit manual: funding method/value + product checkboxes
- Detail: recent **50** ledger rows (`sale_funding` → order detail; `purchase_spend` → PO detail); **manual adjustment** with required notes; material budgets also offer **R&D write-off**
- Same R&D write-off on material edit (beside Adjust stock)

**Ink:** out of scope as a special type — use a recipe material or a manual fixed budget.

---

### 3.14 Orders Board

**Where:** Order Machine → Orders Board (submenu immediately after Orders)

**Population:** Incomplete, non-cancelled orders only. Completed history stays on the Orders list (**View history** link). Horizontal scroll on narrow screens (no stacked mobile layout).

**Columns & cards:**

- Columns = distinct current step names among loaded orders, plus **Unassigned** when any open order has no `current_step_id`
- Empty columns are also **prefilled** for reachable next-step names of advanceable cards (so there is somewhere to drop)
- Column order: per-user meta (`som_board_column_order`) merged with auto lowest-`step_order` heuristic; **←/→** on headers; new names append via heuristic
- Cards (oldest `order_date` first): channel badge, buyer, personalisation preview, step, time in step, progress badges (`waiting_timer` / `waiting_script` / `waiting_batch` / `error` / etc.), batch link when waiting on a batch, pin ★
- Links only on **order ID**, **product name(s)**, and **View** — card body is not one big click target

**Filters:** channel, product, workflow template (two independent dropdowns), free-text (buyer / external order ID / personalisation), client **Pinned only**

**Volume:** warn at ≥ **200** matching open orders; hard **cap 500** (oldest kept when capped)

**Gated drag-and-drop (SortableJS 1.15.6 CDN):**

- Only cards that could Mark done (`in_progress` + gates clear) are draggable; waiting / error / pending / Unassigned are locked
- Valid drop = next-step column, or ephemeral **Complete** zone when the card is on its last step
- Within-column reorder disabled; drop POSTs `POST /som/v1/orders/{id}/advance-step` with `{}`
- Success places/removes the card from the API response (`current_step_name` / `is_complete`), not blindly from the drop-target name; badges update from extended `progress_status` (+ batch summary when applicable)
- Invalid drop or API/network error → snap-back (+ alert on failure)
- Complete zone is not persisted in column-order meta

---

### 3.15 Platform selling fees

**Where:** Channel Fee Estimates · Recurring Platform Expenses · Settings (fee sync) · order detail · Product Costing / Budgets / Analytics (consumers)

**Three tables (schema 1.7.0+, idempotency keys in 1.8.0):**

| Table | Role |
|---|---|
| `wp_som_channel_fee_estimates` | Manually editable estimated components per channel (percent or fixed; optional `order_value_min` / `order_value_max` tiers; `is_enabled`) |
| `wp_som_order_platform_fees` | Actual per-order fee lines from API sync (`external_entry_id` unique per channel) |
| `wp_som_recurring_platform_expenses` | Non-order-linked fees (mainly Etsy listing fees) |

**Channel Fee Estimates UI:**

- Seeded eBay / Etsy defaults on activate/upgrade (idempotent — never overwrites user edits)
- eBay: final value %, tiered per-order fee (£0.30 under £10 / £0.40 at or above — half-open min inclusive / max exclusive), regulatory %, Promoted Listings % **enabled by default**
- Etsy: listing fee £, transaction %, payment processing % + fixed £, regulatory %, VAT-on-fees 20% (of other estimated fee £), Offsite Ads % **enabled by default**
- Full CRUD + enable/disable per component

**Fee sync (`SOM_Platform_Fee_Sync`):**

- Separate cron `som_sync_platform_fees` + Settings **Sync fees now**; own cursor (`som_fee_sync_cursor`) — does **not** reuse `channels.last_synced_at`
- eBay Finances (`sell.finances` scope) + Etsy Ledger (existing `transactions_r` — **no** new Etsy scope)
- Receipt-linked → `order_platform_fees`; unmatched listing-style → `recurring_platform_expenses`; payouts/refunds/labels/taxes ignored
- Amounts stored **as returned**, treated as GBP (**no FX**)
- Dummy mode uses fixtures under `tests/fixtures/ebay-platform-fees.json` / `etsy-platform-fees.json`
- Live eBay tokens issued before Finances scope → Settings **reconnect** warning (`needs_finances_reconnect`)

**Shared profit / fee math (`SOM_Platform_Fees`):**

- Fee source per order: **actual abs totals if synced fee rows exist, else channel estimate**
- Costing shows per-channel **estimated £ + %** and **actual £ + %** (with n= orders); variance highlight when ≥ **2** pp off
- Representative price: **target** for estimates; **listing price** when a channel listing is linked
- No single opaque “effective rate” entity — totals in £ and % only
- Budgets `percent_of_profit` and Analytics profit reuse the same helpers (order-level profit for charts)

---

### 3.16 Analytics Dashboard

**Where:** Order Machine → Analytics

**Charts (Chart.js 4.4.8 via jsDelivr CDN — no build step):**

| Chart | Type | Notes |
|---|---|---|
| Sales over time | Line | Revenue from `order_items.unit_price` × qty only (no target fallback); null/empty price lines dropped |
| Profit over time | Line | Order-level once: revenue − stock-log COGS (`new_order`) − platform fees (estimate→actual). Recurring expenses **out** of profit |
| Material stock over time | Line | Multi-select materials; walk **backward** from `materials.current_stock` via `change_qty`. Empty until materials selected + Apply |
| Orders by channel | Bar | Order counts for the selected range |
| Average order value | Line | Revenue ÷ priced-order count per bucket |

**Filters (shared GET form across charts):** date range (7 / 30 / 90 / this year / custom), granularity (daily / weekly / monthly), channel (order-backed charts), material multi-select (stock). Site timezone bounds → UTC SQL window.

**Exclusions:** cancelled + best-effort refunded (`raw_payload`). Live aggregation only — no summary tables in v1.

---

## 4. What is intentionally out of scope (for now)

Use this so review time isn’t spent hunting missing screens:

| Deferred | Notes |
|---|---|
| Cancel → material stock reversal | After D3/A3 (base open item) |
| Dashboard cost-alerts widget | Update P4 — Materials + Product Costing only for now |
| Reorder-point formula | Future (03 §4) |
| Multi-currency | GBP only |
| Retroactive rewrite of received POs | Corrections via separate adjustment |
| Combining batch with timer/script/manual on same step | Batch-only steps in v1 |
| Rewriting thank-you PDF layout | Already 4-up; PHP wires batch into existing CLI |
| Write Abilities / admin migrate to REST | Read-only Abilities; admin stays form POST |
| Bulk `sync_for_workflow` REST endpoint | Editor uses domain sync |
| Budget / Board REST + Abilities | Admin UI + existing `advance-step` only (Package 2) |
| Dedicated ink material type | Ops: recipe material or manual `fixed_amount` budget |
| Stacked / collapsed board on mobile | Horizontal scroll only |
| Completed orders on the Board | Active/incomplete only; use Orders list |
| Amazon / SP-API Financials | Package 3 stays eBay/Etsy only |
| Pre-computed analytics summary tables | Live queries only for v1 (O6) |
| FX conversion on fee sync | Store as returned; treat as GBP |
| Blended opaque “effective fee rate” product | Show £ + % of representative price only |
| Recurring platform expenses in profit charts | Same as Costing/budgets — order fees only |

---

## 5. Testing — logical order

Work top-to-bottom. Each section builds on the previous. Checkboxes are for your pass/fail notes.

### Prep

- [ ] Docker running; from plugin root: `npx @wordpress/env start`
- [ ] Log in at http://localhost:8888/wp-admin (`admin` / `password`)
- [ ] Confirm **Order Machine** is active under Plugins
- [ ] Optional clean slate: `npx @wordpress/env destroy` → `start`

---

### Test 1 — Foundation & menu

1. Open **Order Machine** in the left admin menu.
2. Confirm submenus: Orders, Orders Board, Products, Materials, Budgets, Suppliers, Purchase Orders, Batches, Workflows, Listings, Analytics, Channel Fee Estimates, Recurring Platform Expenses, Settings.

- [ ] Menu present and all screens load without PHP errors

**Optional CLI check:**

```bash
npx @wordpress/env run cli wp option get som_db_version
npx @wordpress/env run cli wp db query "SHOW TABLES LIKE 'wp_som_%';"
npx @wordpress/env run cli wp plugin list --name=orderMachine
```

Expect DB version `1.8.0`, plugin `0.22.0`, and **25** `wp_som_*` tables (includes budgets + `som_channel_fee_estimates`, `som_order_platform_fees`, `som_recurring_platform_expenses`).

---

### Test 2 — Seeded catalogue (before first sync)

1. **Products** → open **Bin Sticker Set — 100x140mm 4-pack (sample)** (`BIN-SET-4PK`).
2. Confirm workflow template **Bin Sticker Production** is assigned; note costing panel fields.
3. Confirm recipe rows: vinyl + laminate (~1 each).
4. **Materials** → both sheets show stock (seed starts at 25), WA / value on hand, threshold 5.
5. **Workflows** → open **Bin Sticker Production** → 8 steps as in §3.6; Thank-you has batch group (not per-order thank-you script).
6. **Batches** → confirm two batch groups exist (thank_you_card / shipping_label, size 4).

- [ ] Product, recipe, materials, workflow, batch groups all present without manual create

---

### Test 3 — Settings & Sync now

1. **Settings** → confirm eBay and Etsy show as connected (dummy).
2. Note material stock numbers (vinyl/laminate) for later comparison.
3. Click **Sync now**.
4. Confirm a success/summary notice (created / updated counts).
5. Click **Sync now** again → expect **created 0**, updates only (de-dup).

- [ ] First sync creates fixture orders
- [ ] Second sync does not duplicate

---

### Test 4 — Orders list

1. **Orders** → you should see ~6 fixture orders (eBay + Etsy).
2. Try filters: channel, status, dates, search (buyer name / order id).
3. Spot badges: unmatched, cancelled, open, etc.

- [ ] List populates
- [ ] Filters change the result set sensibly
- [ ] Cancelled / unmatched visibly distinct

---

### Test 5 — Order detail (matched vs unmatched)

1. Open a **matched** open order (line tied to `BIN-SET-4PK`).
2. Check personalisation (if present), shipping address, line items with product link.
3. Confirm a **workflow progress** section exists (steps, current step, Mark done).
4. Confirm a **material stock** panel / log mention if reservation ran.
5. Expand **raw payload** and skim structure.
6. Open an **unmatched** order → line(s) without product; no (or incomplete) workflow as designed.
7. Open a **cancelled** fixture → cancelled state clear; no fresh stock reservation expected.

- [ ] Matched order: address + items + workflow readable
- [ ] Unmatched flagged; not blocking the rest of the UI
- [ ] Cancelled identifiable

---

### Test 6 — Workflow: Mark done + timer

On a matched open order with progress:

1. Current step should be **Print** (manual).
2. Click **Mark done** → advances to **Dry** (15-minute timer).
3. Confirm Mark done is **disabled** (or blocked) while the timer is running.
4. Either:
   - Wait ~15 minutes and refresh / wait for engine tick, **or**
   - Force unlock for review (WP-CLI), e.g. set `timer_ends_at` in the past then run the tick:

```bash
npx @wordpress/env run cli wp eval 'do_action("som_engine_tick");'
```

5. After unlock, Mark done on Dry → Laminate, then walk a couple more manual steps if you like.
6. Optional: complete through Ship; Thank-you should enter **`waiting_batch`** (Mark done hidden) and appear under **Batches** — not a per-order script pass-through.

- [ ] Manual advance works
- [ ] Timer blocks early completion
- [ ] Engine tick / elapsed timer unlocks the step
- [ ] Thank-you lands in a batch (not auto-completed alone)

---

### Test 7 — Material auto-decrement

1. **Before** another new order: note vinyl + laminate `current_stock` on Materials.
2. If all fixtures already exist, create a **new** situation by destroying wp-env and syncing once, **or** temporarily lower stock and re-import on a fresh DB so you can watch the delta.
3. After a fresh incremental create of a matched order, stock should drop by recipe × qty (seed recipe: 1 vinyl + 1 laminate per unit).
4. Open the material edit screen → recent log shows `new_order` (negative quantity) and value fields populated.
5. Re-sync the same orders → stock must **not** drop again for those order IDs.

- [ ] Stock decreased on first create
- [ ] Log reason `new_order` (+ value_change consistent)
- [ ] Re-sync is idempotent (no double decrement)

---

### Test 8 — Manual stock adjust, unit-cost override & R&D write-off

1. Materials → edit vinyl → enter a delta (e.g. `+5` or `-2`) → save.
2. Confirm `current_stock` updates and log shows `manual_adjustment`.
3. Change **unit cost** override → confirm WA display stays distinct, value on hand revalues, and a log row records the value change.
4. After a material budget exists (§16): use **R&D write-off** with notes → stock ↓ and budget debit; confirm Adjust stock note that it does not debit the budget.

- [ ] Manual adjust + log entry work
- [ ] Unit-cost override revalues value on hand
- [ ] R&D write-off links stock + budget when a budget exists

---

### Test 9 — Product & material CRUD

1. Create a new product (unique SKU), leave workflow empty, save.
2. Attach one material to its recipe; assign a workflow; set a target selling price; save.
3. Confirm Product Costing panel / list columns show material cost / margin (and after §18 fee sync: estimated fees £/% + Est. fees badge).
4. Deactivate the product; confirm it disappears from “active” expectations / is marked inactive.
5. Create a material; set preferred supplier; edit threshold; deactivate.

- [ ] Create / edit / deactivate paths work without errors
- [ ] Costing fields persist and display

---

### Test 10 — Workflow editor (gates + goals + batch)

1. Workflows → create a small template with 2–3 steps.
2. Reorder steps; set one timer (e.g. 1 minute); set one manual; add a `script_config` JSON blob; save.
3. Assign a **batch group** to one step; confirm combo warning if you also tick other gates; save should reject invalid combo.
4. Add **material cost goals** in the template-level section; save; reopen and confirm they persist.
5. Assign that template to a test product.
6. (Optional) On a fresh sync path, confirm a new matched order picks up this template.

- [ ] Step CRUD / reorder / timer / script_config persist
- [ ] Batch group assign works; invalid combo blocked
- [ ] Goals save round-trip
- [ ] Assignment on product sticks

---

### Test 11 — Import history vs Sync now

1. On a clean DB (or after noting behaviour): run **Import history** (30 days).
2. Confirm orders appear, but **new** history rows should **not** get workflow assignment / stock reservation the way incremental Sync now does.
3. Prefer **Sync now** for day-to-day “new order” behaviour during review.

- [ ] History import does not pretend to be a live new-order pipeline

---

### Test 12 — Suppliers & purchase orders

1. **Suppliers** → create a supplier; edit it; confirm there is no delete action.
2. **Purchase Orders** → create a PO with that supplier, two material lines, shipping + other cost, status `ordered`.
3. On create/edit, click **Preview Impact** → WA / goals / product margin preview appears without changing stock.
4. **Receive** a short delta on one line only → PO becomes `partially_received`; stock + `purchase_received` log; WA / value update.
5. Receive remaining qty (or over-receive) → PO `received`.
6. Create another PO, receive partially, then try **Mark received** (shortfall) and on a third PO try **Cancel** (no stock reverse of prior receives).
7. After first receive on a PO, confirm line/cost fields lock; notes still editable.
8. Confirm Materials list badges / material edit breakdown / post-receive notice when goals fire.
9. If a material budget exists: confirm balance / `purchase_spend` ledger after receive (§16 / Budgets).

- [ ] Supplier CRUD (no delete)
- [ ] PO create → partial → full receive
- [ ] Preview Impact (no DB write)
- [ ] Mark received / cancel close behave as designed
- [ ] Costing + alerts visible after receive
- [ ] Material budget draw-down on receive when budget exists

---

### Test 13 — Batches UI + batch advance

1. Advance several matched orders until Thank-you (`waiting_batch`), **or** assign `shipping_label` to a step and park orders there.
2. **Batches** → open list shows collecting/ready batches; expand a row for members + addresses.
3. For a `collecting` thank-you batch under size 4: either wait for size or click **Release** → script should run once and members advance.
4. For a `shipping_label` batch: **Release** then **Mark done** → members advance.
5. From order detail while `waiting_batch`: confirm Mark done is hidden and the batch link opens `som-batches&batch_id=N`.
6. Edit a batch group’s display name / size on the Batches page; confirm `group_key` stays fixed.
7. (Optional) Force a script failure path and confirm **Retry** on an `error` batch.

- [ ] Collecting / release / mark-done / advance work
- [ ] Order detail links to batch; Mark done hidden while waiting
- [ ] Group name/size editable

---

### Test 14 — REST + Abilities smoke (optional CLI)

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u7-smoke.php
```

Expect `PASS — Sprint U7 smoke` (schema presence, PO receive WA path, batch collect→release→advance via `rest_do_request`).

Earlier update smokes (optional regression):

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u4-smoke.php
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u5-smoke.php
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u6-smoke.php
```

- [ ] U7 smoke PASS
- [ ] Optional U4–U6 smokes PASS

---

### Test 15 — Smoke on Local (optional)

If you use the Local `ordermachine` site:

- [ ] Plugin activates; menus load (incl. Analytics, Channel Fee Estimates, Recurring Platform Expenses, Orders Board, Budgets, …)
- [ ] After first admin load with Package 3 code, `som_db_version` is `1.8.0` and fee tables exist
- [ ] With dummy constant: Sync now + Sync fees now behave like wp-env
- [ ] Without dummy: Settings accepts app keys; Connect shows correct callback URLs; live eBay may show Finances reconnect after scope expand

---

### Test 16 — Budgets

1. **Budgets** → create a **material** budget for vinyl (or laminate); optionally scope to **Bin Sticker Production**; set a target reserve.
2. Create a **manual** budget (`percent_of_price` or `fixed_amount`); optionally scope to `BIN-SET-4PK`. Also create (or reuse) a `percent_of_profit` budget once fees exist (§18–§19) and confirm funding is lower than material-only profit would imply.
3. Confirm list shows balances; force a low/overspent badge if useful (manual adjustment negative, or low reserve). Confirm type / linked material cannot be changed after create.
4. On a clean incremental create of a matched order (or after reset + Sync): budget detail ledger shows `sale_funding`; balance increased. Material budgets: one ledger row per consumed material stock-log line; manual: one row per matching order item. Re-sync → no duplicate funding.
5. (Optional edge) Manual `%` budget on a line with `unit_price = 0` → funds from sold price 0 (does **not** fall back to target). Loss-making `percent_of_profit` may post a **negative** `sale_funding` (not clamped).
6. Receive a PO line for that material → ledger `purchase_spend` (negative) linked to the PO; `Mark received` shortfall alone does not add another draw-down. (If a ledger write ever failed after stock, remaining receive lines should still complete — stock kept.)
7. Manual adjustment with notes required; R&D write-off from budget detail **and** material edit → stock ↓ + budget debit (or stock-only if no active budget).
8. Confirm plain **Adjust stock** still does not touch the budget.
9. Deactivate a budget → further sync/receive skips it.

- [ ] Material + manual create / scope / badges; type/`material_id` fixed after create
- [ ] Sale funding on create; correct ledger grain; idempotent re-sync; skipped on history import
- [ ] `unit_price = 0` treated as set; loss may fund negative `percent_of_profit`
- [ ] `percent_of_profit` uses estimate→actual fees (after §18–§19)
- [ ] PO receive draw-down; mark-received does not draw; receive continues if a draw-down ledger write fails
- [ ] Manual adjustment + R&D on both surfaces; Adjust stock skips budget

---

### Test 17 — Orders Board

1. **Orders Board** (under Orders). Confirm open incomplete orders as cards in step columns (+ Unassigned if any need workflow/mapping).
2. Reorder columns with ←/→; refresh → order persists. Pin a card; toggle **Pinned only**; unpin.
3. Filter by channel, product, workflow, and personalisation search.
4. Confirm progress badges / batch link when waiting; only order ID / product / View are links.
5. Confirm cancelled/completed absent; use **View history** / Orders list for completed.
6. Drag an **In progress** card toward a wrong column → snap-back. Drag to the correct next-step column (including an empty prefilled column) → advances; badges update.
7. Waiting / error / Unassigned cards do not drag.
8. Final-step advanceable card → **Complete** zone → card removed (`is_complete`).
9. (Optional) Volume notices with many open orders (≥200 warn; &gt;500 hard cap).

- [ ] Columns / Unassigned / filters / pins / column reorder
- [ ] Gated DnD + Complete zone; locked cards stay put
- [ ] History link; horizontal scroll works on a narrow window

---

### Test 18 — Channel Fee Estimates

1. **Channel Fee Estimates** → confirm eBay and Etsy seeded rows without manual create.
2. Spot eBay tiered `per_order_fee` (£0.30 under £10 / £0.40 ≥ £10) and enabled Promoted Listings; Etsy dual payment-processing rows + enabled Offsite Ads.
3. Edit a rate / toggle Enabled; add a custom component; delete a custom one.
4. Confirm seed re-run does not overwrite your edit (reload admin / re-activate if needed).

- [ ] Seeded rows present for both channels
- [ ] Tiers / optional ads defaults correct
- [ ] CRUD + enable/disable work; seed preserves edits

---

### Test 19 — Platform fee sync + recurring + order fees

1. **Settings** → Platform fee sync section: fee poll interval, last-run/cursor status, **Sync fees now**.
2. In dummy mode: Sync fees now → success / insert summary.
3. Open a fixture order that received fees → **Platform fees** panel lists lines.
4. **Recurring Platform Expenses** → at least one listing-style row (dummy); filter by channel / listing.
5. Sync fees now again → no duplicate fee rows for the same external entry IDs.
6. (Live Local only) Connected eBay without Finances scope → reconnect warning; Etsy should not require a new scope for ledger.

- [ ] Sync fees now works in dummy; cron hook separate from order sync
- [ ] Order detail shows fees when synced
- [ ] Recurring list + filters work
- [ ] Re-sync is idempotent
- [ ] eBay reconnect messaging when applicable

---

### Test 20 — Fee-aware Costing

1. Open **BIN-SET-4PK** (or another product with target price) → Costing shows per-channel **estimated** fees £ + % and fee-aware profit labeled estimate.
2. After §19 fee sync on orders linked to that product → channel row switches toward **Actual fees** with n= and variance highlight if ≥2 pp off.
3. Link a listing → representative price shows **(listing)** for that channel.
4. Products list margin badge shows Est. fees / Actual fees.

- [ ] Estimate £/% + fee-aware profit visible
- [ ] Actual path after sync (n=, variance)
- [ ] List badge reflects fee source

---

### Test 21 — Analytics Dashboard

1. **Analytics** → apply Last 30 days / daily.
2. Confirm sales, profit, AOV (lines) and orders-by-channel (bar) render; summary totals present.
3. Select one or more materials → Apply → stock chart series appears (empty until selection).
4. Change granularity / channel / custom dates → charts reload via GET.
5. Confirm cancelled orders do not inflate sales; lines without sold `unit_price` are omitted from sales/profit/AOV.

- [ ] Five charts + shared filters
- [ ] Stock empty until materials selected
- [ ] Profit uses estimate→actual fees; no Chart.js build step (CDN)

---

### Test 22 — Package 3 smoke scripts (optional CLI)

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s1-smoke.php
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s2-smoke.php
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s3-smoke.php
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s4-smoke.php
```

- [ ] UP3 S1–S4 smokes PASS

Earlier package smokes (optional regression): U7 / U4–U6 as in Test 14.

---

## 6. Suggested review focus (human judgment)

Beyond “does it click,” please watch for:

1. **Orders detail layout** — is personalisation and address easy to find for packing?
2. **Unmatched / no-workflow flags** — clear enough that you wouldn’t ship the wrong thing?
3. **Workflow step naming / seed defaults** — sensible for real bin-sticker production?
4. **Timer UX** — Dry at 15 minutes / review at 7 days: right defaults?
5. **Stock going negative** — acceptable warning, or do you want hard blocks later?
6. **PO receive / Preview Impact** — clear enough for day-to-day purchasing?
7. **WA vs unit-cost override copy** — operators understand what they’re changing?
8. **Goal alerts** — useful on Materials / Product Costing, or noisy?
9. **Batches list** — expandable rows + deep-link good enough vs a separate detail page?
10. **Thank-you batching** — size 4 and cross-workflow pooling feel right?
11. **Budgets** — material vs manual funding / scopes understandable? Low/overspent badges useful? Does fee-aware `percent_of_profit` feel right?
12. **Orders Board** — column names / DnD gates / Complete zone feel natural for day-to-day production?
13. **Fee estimates** — seeded rates / tiers / optional ads defaults sensible? Costing estimate vs actual comparison useful?
14. **Analytics** — filters + five charts enough for weekly ops? Stock multi-select UX clear?

---

## 7. Quick troubleshooting

| Symptom | Likely cause |
|---|---|
| No seed product / workflow | `SOM_USE_DUMMY_CREDENTIALS` not true; or load any admin page once to trigger seed |
| Sync creates 0 forever | Already synced; check Orders list; or destroy wp-env for a clean DB |
| Matched order has no workflow | Order was created before template was assigned; use fresh sync after seed |
| Mark done disabled on Dry | Timer still running; wait or force `som_engine_tick` after adjusting `timer_ends_at` |
| Mark done missing on Thank-you | Expected while `waiting_batch` — use Batches page |
| Stock didn’t move | History import, cancelled order, unmatched-only, or already reserved |
| Budget didn’t fund on sync | History import (`$apply_stock=false`), cancelled, inactive budget, workflow/product scope miss, or already has `sale_funding` |
| Budget didn’t draw on receive | No active material budget for that material; or used Mark received (shortfall) instead of Receive |
| Stock rose on receive but budget unchanged / partial | Ledger write may have failed after stock — receive continues other lines; repair with manual adjustment; check PHP error log |
| `%` funding used target price when line shows £0 | Unexpected — `unit_price = 0` is treated as set; only NULL/empty should fall back to target |
| Adjust stock didn’t change budget | By design — use R&D write-off |
| Board card won’t drag | Not `in_progress` / gates blocked / Unassigned — waiting badges mean batch/timer/script |
| DnD missing entirely | SortableJS CDN blocked (offline admin); pins/filters still work |
| Card snapped back after drop | Wrong column (not next step) or `advance-step` API error |
| PO receive didn’t change WA | Zero total `item_cost` (no shipping/other allocation); or preview-only click |
| Can’t edit PO lines | Already received once — lines/costs lock by design |
| Batch stuck in `error` | Use Retry on Batches; check `last_error` / local CLI path |
| Thank-you still has per-order script | Re-activate plugin / run convert; seed relies on convert-on-activate |
| OAuth Connect fails on wp-env | Expected without tunnel + real apps — use Local for live OAuth |
| Ciphertext / decrypt weirdness | `SOM_ENCRYPTION_KEY` changed after credentials were saved — reconnect or re-seed |
| Abilities missing | MCP toggle off in Settings |
| Budget tables missing / DB still 1.5.0 | Load any Order Machine admin page so `maybe_upgrade` runs (expect **1.8.0** with Package 3) |
| Fee estimate seed missing | Load admin / activate so `ensure_defaults()` runs; DB ≥ `1.7.0` |
| Order detail has no Platform fees panel | Fees not synced yet — Settings → Sync fees now (dummy fixtures or live Finances/Ledger) |
| Sync fees now inserts 0 forever | Already synced those entries (idempotent); or live token missing Finances scope (eBay reconnect) |
| eBay reconnect warning | Token predates `sell.finances` — Disconnect/Connect; refresh alone does not grant scope |
| Costing still material-only looking | Check fee estimates seeded; profit is fee-aware but labeled Est./Actual — expand per-channel table |
| Analytics charts empty | No priced `unit_price` lines in range; cancelled/refunded excluded; or date filter too narrow |
| Analytics stock chart blank | Select material(s) and Apply — empty until selection by design |
| Chart.js missing / blank canvases | CDN blocked offline; same pattern as SortableJS on Board |
| Notices missing on Analytics | Fixed in post-sprint follow-up — `som-analytics` is on the notices allowlist |

---

## 8. Design doc pointers

| Doc | Use when |
|---|---|
| [`Order-Management-Requirements.md`](Order-Management-Requirements.md) | Why / scope |
| [`01-Data-Model.md`](01-Data-Model.md) | Base tables and relationships |
| [`02-API-Integration.md`](02-API-Integration.md) | eBay / Etsy behaviour |
| [`03-Workflow-Engine.md`](03-Workflow-Engine.md) | Base state machine rules |
| [`04-WordPress-Integration.md`](04-WordPress-Integration.md) | Plugin architecture |
| [`05-Implementation-Roadmap.md`](05-Implementation-Roadmap.md) | Phase order |
| [`07-MCP-Integration.md`](07-MCP-Integration.md) | Abilities / MCP |
| [`Sprint-Progress.md`](Sprint-Progress.md) | Base sprints verified |
| [`../wordpress v2/01-Update-Overview.md`](../wordpress%20v2/01-Update-Overview.md) | Purchasing + batching overview |
| [`../wordpress v2/02-Update-Data-Model.md`](../wordpress%20v2/02-Update-Data-Model.md) | Update schema delta |
| [`../wordpress v2/03-Update-Raw-Material-Purchasing.md`](../wordpress%20v2/03-Update-Raw-Material-Purchasing.md) | Costing / PO rules |
| [`../wordpress v2/04-Update-Batch-Processing.md`](../wordpress%20v2/04-Update-Batch-Processing.md) | Batch state machine / UI |
| [`../wordpress v2/Update-Sprint-Plan.md`](../wordpress%20v2/Update-Sprint-Plan.md) | Package 1 U1–U7 scope + settled decisions |
| [`../wordpress v2/Update-Sprint-Progress.md`](../wordpress%20v2/Update-Sprint-Progress.md) | Package 1 sprints verified |
| [`../wordpress v3/01-Update-Overview.md`](../wordpress%20v3/01-Update-Overview.md) | Budgets + Order Board overview |
| [`../wordpress v3/02-Update-Data-Model.md`](../wordpress%20v3/02-Update-Data-Model.md) | Budget tables |
| [`../wordpress v3/03-Update-Budgets.md`](../wordpress%20v3/03-Update-Budgets.md) | Funding / draw-down rules |
| [`../wordpress v3/04-Update-Order-Board.md`](../wordpress%20v3/04-Update-Order-Board.md) | Board UX / DnD |
| [`../wordpress v3/Update-2-Sprint-Plan.md`](../wordpress%20v3/Update-2-Sprint-Plan.md) | Package 2 U2-1–U2-5 scope + locked decisions |
| [`../wordpress v3/Update-2-Sprint-Progress.md`](../wordpress%20v3/Update-2-Sprint-Progress.md) | Package 2 sprints verified |
| [`../wordpress v4/01-Update-Overview.md`](../wordpress%20v4/01-Update-Overview.md) | Platform fees + Analytics overview |
| [`../wordpress v4/02-Update-Data-Model.md`](../wordpress%20v4/02-Update-Data-Model.md) | Fee tables + tier columns |
| [`../wordpress v4/03-Update-Platform-Fees.md`](../wordpress%20v4/03-Update-Platform-Fees.md) | Fee sync / Costing comparison |
| [`../wordpress v4/04-Update-Analytics-Dashboard.md`](../wordpress%20v4/04-Update-Analytics-Dashboard.md) | Charts / filters |
| [`../wordpress v4/Update-3-Sprint-Plan.md`](../wordpress%20v4/Update-3-Sprint-Plan.md) | Package 3 S1–S4 scope + settled decisions |
| [`../wordpress v4/Update-3-Sprint-Progress.md`](../wordpress%20v4/Update-3-Sprint-Progress.md) | Package 3 sprints verified |

---

*End of guide. Base Sprints 1–11, Update Package 1 (U1–U7), Update Package 2 (U2-1–U2-5), and Update Package 3 (UP3-S1–S4) are complete (plugin **0.22.0**, DB **1.8.0**).*
