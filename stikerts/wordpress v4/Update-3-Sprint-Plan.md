# Update Package 3 — Sprint Plan

*Planning only. Assumes base plugin + Update Package 1 (materials/costing) + Update Package 2 (budgets/board) are already in place. Specs: `01`–`05` in this folder.*

**Sequencing:** Platform Selling Fees first, then Analytics Dashboard (profit chart accurate from day one). Analytics does not hard-block on Fees, but Fees first is the chosen path.

---

## 1. Consolidated open items

Every open item from `03` / `04`, plus code-surfaced items. Settled answers are locked for implementation. Items that still need a concrete formula during a sprint are marked *pin during build*.

| # | Source | Item | Blocks | Status / decision |
|---|---|---|---|---|
| O1 | `03` §7.1 | eBay per-order fee estimate tiering (£0.30 under £10 / £0.40 at or above) | Sprint 1 schema + seed + estimate calculator | **Settled:** tiered estimates. Add `order_value_min` / `order_value_max` on `channel_fee_estimates`. Seed two eBay `per_order_fee` rows. |
| O2 | `03` §7.2 | How to turn fee components into a comparable “effective rate” | Sprint 3 Costing comparison UI | **Settled:** **no blending into one opaque effective-rate product.** Show **total fees in £** and as **% of representative price / revenue**. Exact representative price (target vs listing vs sold) pinned in Sprint 3 against a real sample. |
| O3 | `03` §7.3 | Optional components (Promoted Listings, Offsite Ads) in/out of estimate | Sprint 1 seed + Sprint 3 estimate totals | **Settled:** **include by default** (conservative). |
| O4 | `03` §7.4 | Currency / FX when API returns non-GBP | Sprint 2 sync storage | **Settled:** store amounts **as returned**; treat as **GBP**; **no FX conversion**. |
| O5 | `04` §5.1 | Companion charts (orders by channel, AOV) | Sprint 4 chart set | **Settled:** **both** included. |
| O6 | `04` §5.2 | Live aggregation vs summary tables | Sprint 4 (non-blocking) | **Settled for v1:** live queries only. Revisit if slow later. |
| O7 | `04` §5.3 | Profit chart if actual fees not yet synced | Sprint 3–4 profit path | **Settled:** use **estimated fees** until real synced fees exist for that order/product — not material-only. |
| O8 | Code | eBay/Etsy OAuth scopes lack Finances / Ledger | Sprint 2 live sync | **Accepted:** expand scopes + settings **reconnect** messaging. Existing tokens will not authorize fee APIs until reconnect. |
| O9 | Code | Should budgets `percent_of_profit` stay material-only? | Sprint 3 | **Settled:** **no** — use same **estimate → actual** fee rule as Costing / Analytics. Do not silently change other funding methods. |
| O10 | Code | `material_stock_log` has deltas only (no running balance column) | Sprint 4 stock chart | **Settled:** reconstruct by walking **backward from `materials.current_stock`**. |
| O11 | Doc | `01` says “New tables (4)” but lists 3; `02` has no Open items section | Planning accuracy | **Treat as doc typos.** Implement the **3** tables in `02`, plus tier columns on `channel_fee_estimates`. |

---

## 2. Clarifying questions (kept visible)

Answers from planning chat — retained even after resolution.

1. **eBay per-order estimate — flat £0.30 or tiered bands?**  
   **A:** Tiered — under £10 → £0.30; at/above £10 → £0.40.

2. **Optional ads (Promoted Listings / Offsite Ads) in blended estimate?**  
   **A:** Include by default.

3. **Currency when payloads may be USD?**  
   **A:** Assume/store GBP as returned; no FX at sync time.

4. **Companion charts — both, core only, or one?**  
   **A:** Both (Orders by channel + Average order value).

5. **Profit chart without actual fees yet — material-only OK?**  
   **A:** No — use **estimation** until real cost syncs.

6. **Budgets `percent_of_profit` — leave material-only?**  
   **A:** No — use estimations or real cost (same rule as Costing).

7. **Stock over time — walk backward from `current_stock`?**  
   **A:** Yes.

8. **Blending components into one effective % — defer formula / use target as representative?**  
   **A:** No blending as the primary comparison. Show **total in pounds and in percentage**.

---

## 3. Spec ↔ codebase discrepancies

Treat **existing plugin code as ground truth** where it conflicts with the specs. None of these stop planning; they shape implementation.

| Spec assumption | Actual code today | Plan impact |
|---|---|---|
| Separate `som_sync_platform_fees` cron | [`includes/class-som-cron.php`](../../includes/class-som-cron.php) already has three separate jobs (`som_refresh_tokens`, `som_sync_orders`, `som_engine_tick`) | Add a fourth job the same way; must update `init`, `add_schedules`, `schedule_events`, `clear_events`, `reschedule_events`. |
| Fee sync reuses channel auth pattern | [`SOM_Channel_Ebay`](../../includes/class-som-channel-ebay.php) / [`SOM_Channel_Etsy`](../../includes/class-som-channel-etsy.php): OAuth + `wp_remote_*`; dummy fixtures for orders | Add Finances / Ledger methods on the same classes; respect `SOM_Channels::is_dummy()`; expand `scopes()`. |
| Shared profit for Costing + Analytics | One hub: `SOM_Products::recipe_costing()` — **material-only** (`target − materials`). Budgets recompute `sold − materials` locally in `percent_of_profit`. | Extend Costing via `recipe_costing` (or sibling). Add a **shared order-level** fee-aware profit aggregator for Analytics + budgets. |
| Stock chart from log “running balance” | `wp_som_material_stock_log` columns: `id`, `material_id`, `order_id`, `change_qty`, `reason`, `purchase_order_item_id`, `unit_cost_at_time`, `value_change`, `created_at`. Live stock on `materials.current_stock`. No `balance_after`. | Build reconstruction helper (backward from current). |
| Fee tables / Analytics page exist | Not in PHP; `SOM_DB::DB_VERSION` is `1.6.0`; no fee tables, no Chart.js page | Pure additive: tables + admin pages + cron. |
| OAuth already allows fee APIs | eBay scopes: fulfillment + inventory only. Etsy: `transactions_r`, `shops_r`, `listings_r`, `listings_w` — no payment/ledger | Scope expand + reconnect required for live sync. |
| `01` “4 tables” / `02` Open items | Overview typo; `02` has no Open items section | Plan against 3 tables + tier columns. |
| Chart.js via CDN like SortableJS | Order Board loads SortableJS from jsDelivr in [`admin/class-som-admin-menu.php`](../../admin/class-som-admin-menu.php) | Same CDN pattern for Chart.js on Analytics only. |

---

## 4. Architecture notes (implementation guidance)

### 4.1 New tables (Platform Fees)

From `02-Update-Data-Model.md`, plus tier columns:

1. **`wp_som_channel_fee_estimates`** — estimated fee components per channel.  
   Spec columns + **`order_value_min` / `order_value_max` DECIMAL(10,2) NULL** (null/null = always applies).  
   Seed ebay/etsy defaults from `02`; optional ads **included**; two eBay `per_order_fee` rows for tiers.

2. **`wp_som_order_platform_fees`** — actual per-order fee lines from API sync.

3. **`wp_som_recurring_platform_expenses`** — non-order-linked (mainly Etsy listing fees).

Analytics: **no new tables**.

### 4.2 Fee sync

- Cron hook: `som_sync_platform_fees` (suggest 30–60 min interval setting).
- Orchestrator class (e.g. `SOM_Platform_Fee_Sync`) — **not** merged into `SOM_Order_Sync`.
- Own sync cursor / options — **do not** reuse `channels.last_synced_at`.
- Idempotent writes (safe re-run, no duplicate fee rows).
- Etsy ledger: receipt-linked → `order_platform_fees`; otherwise (listing fees) → `recurring_platform_expenses`.

### 4.3 Shared profit logic

```text
Product Costing (pricing)  →  recipe_costing (+ fees)     →  £ + % fee totals, profit
Order-level profit         →  sold − material COGS − fees →  Analytics + budgets % of profit
Fee source                 →  actual if synced else estimate
```

- **%** = `total_fees ÷ representative_price_or_revenue` (display only — not a separate blended-rate entity).
- Do not silently rework unrelated budget funding methods.

### 4.4 Material stock over time

- Query `material_stock_log` for selected material(s) in date range.
- Walk **backward** from `materials.current_stock` using `change_qty` to produce balance-at-date series.

---

## 5. Sprint breakdown

### Sprint 1 — Fee schema + Channel Fee Estimates UI

**Covers:** Data model + manually editable estimates (including eBay tiers and optional ads on by default).

**Create:**
- `admin/views/channel-fee-estimates.php` (or equivalent settings sub-view)
- Possibly `includes/class-som-channel-fee-estimates.php` (CRUD + seed helpers)

**Modify:**
- [`includes/class-som-db.php`](../../includes/class-som-db.php) — `dbDelta` for 3 tables + tier columns; bump `som_db_version`
- [`orderMachine.php`](../../orderMachine.php) — require new class if added
- [`admin/class-som-admin-menu.php`](../../admin/class-som-admin-menu.php) — menu/page + save handlers + capability checks
- Possibly [`admin/views/settings.php`](../../admin/views/settings.php) if estimates live under Settings rather than a top-level page

**Done when:**
- Migration creates the three tables; version bumps cleanly on existing installs.
- eBay/Etsy estimate rows are seeded (tiered `per_order_fee`; optional ads included).
- Admin can view/edit estimate components (including min/max order value for tiers).
- No fee sync / Costing changes required yet.

**Open items needed first:** O1, O3 (settled). O11 (doc only).

---

### Sprint 2 — Platform fee sync + order/recurring UI

**Covers:** eBay Finances + Etsy Ledger/Payments sync; order detail fee breakdown; recurring expenses list.

**Create:**
- `includes/class-som-platform-fee-sync.php` (orchestrator)
- Fee fixtures under `tests/fixtures/` for dummy mode (as needed)
- `admin/views/recurring-platform-expenses.php`

**Modify:**
- [`includes/class-som-channel-ebay.php`](../../includes/class-som-channel-ebay.php) — Finances API methods; expand `scopes()` (e.g. sell.finances)
- [`includes/class-som-channel-etsy.php`](../../includes/class-som-channel-etsy.php) — Ledger / payment account methods; expand scopes
- [`includes/class-som-cron.php`](../../includes/class-som-cron.php) — `HOOK_SYNC_PLATFORM_FEES`, schedule, clear, reschedule, handler
- [`includes/class-som-settings.php`](../../includes/class-som-settings.php) — fee poll interval if configurable
- [`admin/class-som-admin-menu.php`](../../admin/class-som-admin-menu.php) — recurring expenses page; reconnect messaging on settings
- [`admin/views/order-detail.php`](../../admin/views/order-detail.php) — itemized actual fees when present
- [`admin/views/settings.php`](../../admin/views/settings.php) — scope reconnect notice; optional “Sync fees now”
- [`orderMachine.php`](../../orderMachine.php) — require + activation scheduling already via `SOM_Cron`

**Done when:**
- `som_sync_platform_fees` runs separately from `som_sync_orders`.
- Matched fees land in `order_platform_fees` without duplicates on re-run.
- Unmatched Etsy listing-style entries land in `recurring_platform_expenses`.
- Order detail shows fee lines when synced.
- Recurring expenses list is filterable by listing (as specified).
- Dummy mode does not break on missing live credentials.
- Settings clearly tell the user to **reconnect** eBay/Etsy after scope change.

**Open items needed first:** O4, O8 (settled / accepted).

---

### Sprint 3 — Product Costing + budgets fee-aware

**Covers:** Estimate vs actual as £ + %; profit includes fees; budgets `percent_of_profit` shares the rule.

**Create:**
- Shared helpers as needed, e.g. `includes/class-som-platform-fees.php` (estimate application, actual totals, £/% display math, order-level profit)

**Modify:**
- [`includes/class-som-products.php`](../../includes/class-som-products.php) — extend `recipe_costing()` or add sibling used by Costing UI
- [`admin/views/product-edit.php`](../../admin/views/product-edit.php) — Costing panel: fee totals £ + %, variance, order count for actuals
- [`admin/views/products-list.php`](../../admin/views/products-list.php) — only if list columns should reflect fee-aware margin (prefer minimal change; flag if list stays material-only)
- [`includes/class-som-budgets.php`](../../includes/class-som-budgets.php) — `percent_of_profit` uses shared fee-aware profit (estimate → actual)
- Possibly MCP/abilities product costing fields if they expose profit today

**Done when:**
- Costing shows per-channel **estimated total £ + %** and **actual total £ + %** (with n= orders), plus variance highlight when meaningfully off.
- Profit = representative/sold price − material cost − platform fees (estimate until actuals exist).
- Budget funding via `percent_of_profit` uses the same fee rule.
- No single “blended effective rate” object — only totals in £ and %.

**Open items needed first:** O2 (settled direction; pin representative price in this sprint), O7, O9.

---

### Sprint 4 — Analytics Dashboard

**Covers:** Single Analytics page with filters; core three charts + both companions; Chart.js CDN.

**Create:**
- `admin/views/analytics.php`
- `admin/assets/js/analytics.js` (Chart.js wiring)
- Query/helper methods (class or methods on existing includes) for sales, profit, stock series, orders-by-channel, AOV

**Modify:**
- [`admin/class-som-admin-menu.php`](../../admin/class-som-admin-menu.php) — register Analytics page; enqueue Chart.js CDN + `analytics.js` only on that page
- Shared profit aggregator from Sprint 3 (reuse — do not duplicate)

**Charts:**
1. Sales over time (line) — revenue by date; sold price where available else `target_selling_price`
2. Profit over time (line) — shared order-level profit (estimate → actual fees)
3. Material stock over time (line) — selectable material(s); backward from `current_stock`
4. Orders by channel (bar)
5. Average order value (line)

**Filters:** date range (7/30/90/this year/custom), granularity (daily/weekly/monthly), channel where relevant, material selector for stock chart.

**Done when:**
- One admin Analytics page; shared filter state across charts.
- All five charts render with live aggregation.
- Profit uses estimate→actual fees (Fees already built).
- Stock series matches walking backward from current stock.
- No build step; Chart.js via CDN like SortableJS.

**Open items needed first:** O5, O6, O10 (settled). O7 already satisfied by Sprint 3 path.

---

## 6. Suggested file touch map (summary)

| Area | Primary files |
|---|---|
| Schema | `includes/class-som-db.php` |
| Fee estimates CRUD | new class + `admin/views/channel-fee-estimates.php` + admin menu |
| Fee sync | new `class-som-platform-fee-sync.php`, `class-som-cron.php`, ebay/etsy channel classes, settings |
| Costing / profit | `class-som-products.php`, new fee helpers, `product-edit.php`, `class-som-budgets.php` |
| Order / recurring UI | `order-detail.php`, recurring expenses view |
| Analytics | `analytics.php`, `analytics.js`, admin menu enqueue |
| Bootstrap | `orderMachine.php` |

---

## 7. Out of scope (this update)

- Plugin implementation in the planning task that produced this file (done — planning doc only).
- Amazon / SP-API Financials.
- Pre-computed analytics summary tables (v1 live queries only).
- Reworking unrelated budget funding methods beyond `percent_of_profit`.
- Reworking existing order-sync behaviour except additive hooks/UI for reconnect and optional “sync fees now”.

---

## 8. Implementation kickoff note

When building, follow sprints **1 → 2 → 3 → 4** in order. Do not silently re-open settled decisions in §1–§2; if API reality forces a change (especially eBay Finances / Etsy Ledger field mapping), stop and confirm before inventing behaviour.
