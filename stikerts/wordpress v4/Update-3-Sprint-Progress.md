# Order Machine — Update Package 3 Sprint Progress

*Companion to [`Update-3-Sprint-Plan.md`](Update-3-Sprint-Plan.md). Plan stays the source of scope; this file records what shipped and how it was verified.*

Assumption: base plugin + Update Package 1 (materials/costing) + Update Package 2 (budgets/board) in place (`SOM_DB::DB_VERSION` was `1.6.0`, `SOM_VERSION` was `0.18.1`). Specs in this folder: `01`–`05`.

**Sequencing (from plan):** Platform Selling Fees first, then Analytics Dashboard.

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| 1 | Fee schema + Channel Fee Estimates UI | **Done** | Verified on wp-env 2026-08-09 |
| 2 | Platform fee sync + order/recurring UI | **Done** | Verified on wp-env 2026-08-10 |
| 3 | Product Costing + budgets fee-aware | **Done** | Verified on wp-env 2026-08-10 |
| 4 | Analytics Dashboard | **Done** | Verified on wp-env 2026-08-10 |

---

## Sprint 1 — Fee schema + Channel Fee Estimates UI

- **Status:** **Done** (confirmed complete vs `Update-3-Sprint-Plan.md` § Sprint 1 + §1 O1/O3/O11 + clarifying answers locked in chat)
- **Completed:** 2026-08-09
- **Verified on:** wp-env (`http://localhost:8888`) via `tests/sprint-up3-s1-smoke.php`; also re-ran existing smoke suite
- **Plugin version:** `0.19.0`
- **DB version:** `1.7.0`

### Plan requirements review (`Update-3-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Create `admin/views/channel-fee-estimates.php` | **Done** | Dedicated list + create/edit view (not Settings sub-section) |
| Create `includes/class-som-channel-fee-estimates.php` | **Done** | CRUD + idempotent seed + tier match helpers |
| Modify `includes/class-som-db.php` — 3 tables + tier columns; bump version | **Done** | `channel_fee_estimates` (+ min/max/`is_enabled`), `order_platform_fees`, `recurring_platform_expenses`; `1.6.0` → `1.7.0` |
| Modify `orderMachine.php` — require new class | **Done** | Require + `ensure_defaults()` on activate/init; `SOM_VERSION` → `0.19.0` |
| Modify `admin/class-som-admin-menu.php` — menu + handlers + caps | **Done** | Submenu **Channel Fee Estimates**; save/delete handlers; `manage_options` |
| Settings sub-view (optional) | **N/A** | Chose dedicated submenu (locked answer #1) |
| **Done when:** Migration creates three tables; version bumps | **Pass** | Smoke + `som_db_version` = `1.7.0` on wp-env |
| **Done when:** eBay/Etsy rows seeded (tiered `per_order_fee`; optional ads on) | **Pass** | Seed + smoke asserts |
| **Done when:** Admin can view/edit components (incl. min/max) | **Pass** | Full CRUD UI |
| **Done when:** No fee sync / Costing yet | **Pass** | Explicitly out of scope |
| Open items first | Settled | O1, O3; O11 doc-only |

### Locked decisions applied (planning chat + plan §1–§2)

| Topic | Decision | Applied? |
|---|---|---|
| O1 eBay per-order tiers | Under £10 → £0.30; ≥ £10 → £0.40 via `order_value_min`/`max` | Yes |
| Tier band semantics | Half-open: min inclusive, max exclusive; seed `(NULL, 10)` + `(10, NULL)` | Yes |
| O3 optional ads | Include by default (Promoted Listings / Offsite Ads `is_enabled = 1`) | Yes |
| UI placement | Dedicated submenu **Channel Fee Estimates** | Yes |
| Etsy payment processing | Two rows: `payment_processing` (4%) + `payment_processing_fixed` (£0.20) | Yes |
| Optional components toggle | `is_enabled` per row | Yes |
| CRUD scope | Full add / edit / delete | Yes |
| Seed behaviour | Idempotent — insert missing only; never overwrite user edits | Yes |
| `vat_on_fees` | Seed as percent 20 with note; application deferred to Sprint 3 | Yes |
| O11 “4 tables” typo | Implement **3** tables from `02` + tier columns | Yes |
| PK/FK types | `bigint(20) unsigned` to match existing `SOM_DB` | Yes |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | Fee DDL; `DB_VERSION` → `1.7.0`; call `ensure_defaults()` after migrate |
| `includes/class-som-channel-fee-estimates.php` | Domain CRUD, seed, tier match, URL helpers (new) |
| `admin/views/channel-fee-estimates.php` | List by channel + create/edit form (new) |
| `admin/class-som-admin-menu.php` | Submenu, render, save/delete handlers, CSS enqueue page |
| `orderMachine.php` | Require class; ensure on activate/init; notices page allowlist; `0.19.0` |
| `tests/sprint-up3-s1-smoke.php` | Schema / seed / tiers / CRUD / idempotency smoke (new) |
| `tests/sprint-u5-smoke.php` | Relax DB assert to `>= 1.5.0` (suite compatibility) |
| `tests/sprint-u6-smoke.php` | Same |
| `tests/sprint-u7-smoke.php` | Same |
| `tests/sprint11-smoke.php` | Prefer seed product with workflow (pre-existing flake fix) |
| `stikerts/wordpress v4/Update-3-Sprint-Progress.md` | This progress record |

### Schema created

New tables (no existing-table alters beyond additive CREATE):

1. **`wp_som_channel_fee_estimates`** — `channel_id`, `fee_component`, `rate_type` (`percent`\|`fixed`), `rate_value`, `order_value_min` / `order_value_max` (NULL = open end; both NULL = always), `is_enabled`, `notes`, timestamps
2. **`wp_som_order_platform_fees`** — actual per-order fee lines (unused until Sprint 2)
3. **`wp_som_recurring_platform_expenses`** — non-order-linked fees (unused until Sprint 2)

### Seed defaults

**eBay:** `final_value_fee` 12.8%; `per_order_fee` £0.30 / £0.40 (tiers); `regulatory_fee` 0.4%; `promoted_listings` 3% enabled.

**Etsy:** `listing_fee` £0.16; `transaction_fee` 6.5%; `payment_processing` 4%; `payment_processing_fixed` £0.20; `regulatory_fee` 0.32%; `vat_on_fees` 20% (note: on fee totals); `offsite_ads` 15% enabled.

### `SOM_Channel_Fee_Estimates` API surface (Sprint 1)

| Method | Role |
|---|---|
| `get` / `list_all` / `list_grouped_by_channel` | Read |
| `create` / `update` / `delete` | Full CRUD |
| `ensure_defaults` / `find_matching` | Idempotent seed |
| `matches_order_value` | Half-open tier check (for later estimate math) |
| `list_url` / `detail_url` / `delete_url` | Admin URLs |
| `format_rate` / `format_tier` | Display helpers |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| Migration creates the three tables; version bumps cleanly | **Pass** (wp-env `som_db_version` = `1.7.0`) |
| eBay/Etsy estimate rows seeded (tiered `per_order_fee`; optional ads included) | **Pass** |
| Admin can view/edit estimate components (including min/max) | **Pass** (full CRUD + `is_enabled`) |
| No fee sync / Costing changes required yet | **Pass** |

### Explicitly out of scope for Sprint 1 (later sprints)

| Item | Sprint |
|---|---|
| `som_sync_platform_fees` cron + Finances/Ledger API | 2 |
| Order detail actual fee breakdown | 2 |
| Recurring platform expenses UI | 2 |
| OAuth scope expand + reconnect messaging | 2 |
| Product Costing estimate vs actual £/% | 3 |
| Budgets `percent_of_profit` fee-aware | 3 |
| Analytics Dashboard / Chart.js | 4 |

### Verification (wp-env, 2026-08-09)

```bash
npx @wordpress/env start
npx @wordpress/env run cli wp plugin activate orderMachine
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s1-smoke.php
```

**Sprint 1 smoke:** `PASS — Update Package 3 Sprint 1 smoke` (schema, seed counts, tier edges at £9.99/£10, dual Etsy processing, optional ads enabled, idempotent seed, CRUD, inverted-tier rejection, seed preserves edits).

**Existing suite also run:** u1–u7, sprint9 (+ callback), sprint10, sprint11 (after product-selection fix), bugfix-001-002, seed-remove-restore — all exit 0 / PASS.

### Suggested live check (Local / operator)

1. Load WP admin so `maybe_upgrade` runs → option `som_db_version` = `1.7.0`.
2. Open **Order Machine → Channel Fee Estimates**.
3. Confirm eBay/Etsy seeded rows; edit a rate / toggle Enabled; add and delete a custom component.
4. Confirm Settings / Costing / order detail unchanged (no Sprint 2–3 UI yet).

### Gaps / residual risk

| Item | Notes |
|---|---|
| `dbDelta` “table already exists” noise | Seen on wp-env when re-running upgrade path for some tables (including pre-existing budgets); tables present and version set — monitor on Local upgrade |
| Estimate → £/% application | Intentionally Sprint 3; `vat_on_fees` is stored only |
| Fee sync tables empty | Expected until Sprint 2 (now filled by Sprint 2) |

---

## Sprint 2 — Platform fee sync + order/recurring UI

- **Status:** **Done** — confirmed complete vs `Update-3-Sprint-Plan.md` §5 Sprint 2 Create/Modify/**Done when**, plus settled O4/O8 and Sprint 2 Q&A locks
- **Completed:** 2026-08-10
- **Verified on:** wp-env via `tests/sprint-up3-s2-smoke.php` (`PASS — Update Package 3 Sprint 2 smoke`)
- **Plugin version:** `0.20.0`
- **DB version:** `1.8.0` (from `1.7.0`; additive `external_entry_id` + unique keys)

### Plan Create / Modify checklist

| Plan item | Status | Notes |
|---|---|---|
| Create `includes/class-som-platform-fee-sync.php` | **Done** | Separate from `SOM_Order_Sync`; own cursor `som_fee_sync_cursor` + status `som_fee_sync_status` |
| Fee fixtures under `tests/fixtures/` | **Done** | `ebay-platform-fees.json`, `etsy-platform-fees.json` |
| Create `admin/views/recurring-platform-expenses.php` | **Done** | Dedicated submenu **Recurring Platform Expenses** |
| Modify `class-som-channel-ebay.php` — Finances + scopes | **Done** | `fetch_platform_fees()`; added `sell.finances`; `needs_finances_reconnect()` |
| Modify `class-som-channel-etsy.php` — Ledger / payments | **Done** | Ledger list + payment→receipt map; **no new scope** (existing `transactions_r` covers ledger) |
| Modify `class-som-cron.php` — `HOOK_SYNC_PLATFORM_FEES` | **Done** | init / schedules / schedule / clear / reschedule / `sync_platform_fees()` |
| Modify `class-som-settings.php` — fee poll interval | **Done** | `fee_poll_interval_minutes` default **30** (min 5) |
| Modify admin menu — recurring page + reconnect | **Done** | Submenu + Settings reconnect notice (eBay only) |
| Modify `order-detail.php` — itemized fees | **Done** | Platform fees panel when synced |
| Modify `settings.php` — reconnect + Sync fees now | **Done** | Fee sync section + **Sync fees now** button |
| Modify `orderMachine.php` — require + version | **Done** | Require sync class; `SOM_VERSION` `0.20.0` |
| Schema support for idempotency (locked Q1) | **Done** | Both fee tables: `external_entry_id` + `UNIQUE KEY channel_entry` |

### Plan “Done when” checklist

| Criterion (plan wording) | Result | Evidence |
|---|---|---|
| `som_sync_platform_fees` runs separately from `som_sync_orders` | **Pass** | Fourth cron hook; own orchestrator/cursor |
| Matched fees in `order_platform_fees` without duplicates on re-run | **Pass** | Smoke: 6 inserted → re-run 0 insert / 6 skipped |
| Unmatched Etsy listing-style → `recurring_platform_expenses` | **Pass** | Fixture listing fee → recurring; smoke `recurring_gte_1` |
| Order detail shows fee lines when synced | **Pass** | `SOM_Orders::get` attaches `platform_fees`; detail panel |
| Recurring expenses list filterable by listing | **Pass** | Channel + listing filters on recurring view |
| Dummy mode does not break on missing live credentials | **Pass** | Dummy fixtures path; smoke on wp-env dummy channels |
| Settings tell user to **reconnect** after scope change | **Pass*** | *eBay only* (locked: Etsy needs no new scope) |

\* Plan text said “eBay/Etsy”; locked answer was force reconnect on **eBay only** because Etsy ledger already uses `transactions_r`.

### Settled open items (plan §1) for this sprint

| Item | Decision | Applied? |
|---|---|---|
| O4 Currency / FX | Store as returned; treat as GBP; no FX | Yes |
| O8 OAuth scopes | Expand + reconnect messaging | Yes — eBay `sell.finances` + reconnect UI; Etsy unchanged |

### Locked decisions applied (Sprint 2 planning chat)

| Topic | Decision | Applied? |
|---|---|---|
| Idempotency | `external_entry_id` + unique index | Yes |
| Fees before order exists | Skip / retry later (unmatched) | Yes |
| Non-fee ledger/finance lines | Ignore (payouts, refunds, labels, taxes) | Yes |
| Amount sign | As returned (often negative) | Yes |
| Scopes / reconnect | eBay `sell.finances` only; reconnect eBay only | Yes |
| Sync fees now | Include | Yes |
| Recurring UI | Dedicated submenu | Yes |
| Dummy fixtures | Yes — fees on fixture orders | Yes |
| First-run lookback | **7 days** | Yes |

### What shipped (behaviour)

1. **Sync:** Cron + Settings **Sync fees now** pull eBay Finances / Etsy Ledger (or fixtures in dummy), classify order vs recurring vs ignore, match orders by `channel_id` + `external_order_id`, write idempotently.
2. **UI:** Order detail **Platform fees** panel; **Recurring Platform Expenses** list with channel/listing filters; Settings fee poll interval + last-run/cursor status + eBay reconnect warning when `finances_scope` missing on a live token.
3. **Schema:** `1.7.0` → `1.8.0` for unique external entry IDs on both actual-fee tables.

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | `external_entry_id` + unique keys; `DB_VERSION` → `1.8.0` |
| `includes/class-som-platform-fee-sync.php` | Orchestrator + list helpers (new) |
| `includes/class-som-channel-ebay.php` | Finances fetch, `sell.finances`, reconnect helper |
| `includes/class-som-channel-etsy.php` | Ledger fetch + fee/ignore/recurring classification |
| `includes/class-som-cron.php` | `som_sync_platform_fees` job |
| `includes/class-som-settings.php` | `fee_poll_interval_minutes` |
| `includes/class-som-orders.php` | Attach `platform_fees` on detail get |
| `includes/seed/class-som-seed.php` | Dummy creds include `finances_scope` |
| `admin/class-som-admin-menu.php` | Recurring submenu; Sync fees now handler |
| `admin/views/settings.php` | Fee sync UI + eBay reconnect notice |
| `admin/views/order-detail.php` | Platform fees panel |
| `admin/views/recurring-platform-expenses.php` | List + filters (new) |
| `tests/fixtures/ebay-platform-fees.json` | Dummy Finances payload |
| `tests/fixtures/etsy-platform-fees.json` | Dummy ledger entries |
| `tests/sprint-up3-s2-smoke.php` | Sprint 2 smoke (new) |
| `orderMachine.php` | Require sync class; `0.20.0`; notices allowlist |
| `stikerts/wordpress v4/Update-3-Sprint-Progress.md` | This progress record |

### Verification (wp-env, 2026-08-10)

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s2-smoke.php
```

**Result:** `PASS — Update Package 3 Sprint 2 smoke`  
Smoke covered: schema columns/unique indexes, cron registration, fee poll setting, fixture insert (6) / unmatched (2) / ignored (2), idempotent re-run, eBay+Etsy order fee rows, recurring row, order detail `platform_fees` attachment, eBay finances scope present.

### Suggested live check (Local / operator)

1. Load WP admin → `som_db_version` = `1.8.0`.
2. Settings → Platform fee sync section, **Sync fees now**, fee poll interval.
3. Live eBay connected before this sprint → reconnect warning for Finances scope.
4. After Sync fees now (dummy): fixture order **Platform fees** panel; **Recurring Platform Expenses**.

### Gaps / residual risk (not blocking Sprint 2)

| Item | Notes |
|---|---|
| Live Finances/Ledger field mapping | Confirm against real payloads if shapes differ from fixtures/normalizers (plan kickoff note) |
| Existing live eBay tokens | Must reconnect once; token refresh does not grant `sell.finances` |
| Etsy amount units | Live divides ledger `amount` by 100; fixtures use major units in normalized entries |
| Estimate → Costing £/% / budgets | Sprint 3 |

### Explicitly out of scope for Sprint 2 (later sprints)

| Item | Sprint |
|---|---|
| Product Costing estimate vs actual £/% | 3 |
| Budgets `percent_of_profit` fee-aware | 3 |
| Analytics Dashboard / Chart.js | 4 |

---

## Sprint 3 — Product Costing + budgets fee-aware

- **Status:** **Done** (confirmed complete vs `Update-3-Sprint-Plan.md` §5 Sprint 3 Create/Modify/**Done when**, plus settled O2/O7/O9 and Sprint 3 Q&A locks)
- **Completed:** 2026-08-10
- **Verified on:** wp-env via `tests/sprint-up3-s3-smoke.php` (`PASS — Update Package 3 Sprint 3 smoke`)
- **Plugin version:** `0.21.0`
- **DB version:** `1.8.0` (unchanged — no schema migration this sprint)

### Plan requirements review (`Update-3-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Create shared helpers e.g. `includes/class-som-platform-fees.php` | **Done** | Estimate application, actual totals, £/% math, product + order/line profit |
| Modify `includes/class-som-products.php` — extend `recipe_costing()` | **Done** | Fee-aware `profit`/`margin_percent`; keeps `material_only_profit`; adds `fee_channels` / `fee_source` / `platform_fees` |
| Modify `admin/views/product-edit.php` — Costing panel | **Done** | Per-channel estimated £+%, actual £+% with n=, variance highlight, source badges |
| Modify `admin/views/products-list.php` | **Done** | Fee-aware margin + Est./Actual badge (locked: update list, not material-only) |
| Modify `includes/class-som-budgets.php` — `percent_of_profit` | **Done** | Shared `SOM_Platform_Fees::line_profit` (estimate→actual); other funding methods untouched |
| Possibly MCP/abilities product costing fields | **Done** | `get_products` adds `platform_fees` + `fee_source`; profit/margin fee-aware |
| Modify `orderMachine.php` | **Done** | Require `class-som-platform-fees.php`; `SOM_VERSION` → `0.21.0` |
| **Done when:** Costing per-channel estimate vs actual £+% (n=) + variance | **Pass** | Product edit table; ≥2 pp highlight class |
| **Done when:** Profit = price − material − fees (estimate until actuals) | **Pass** | One fee-aware profit; UI labels estimate vs actual |
| **Done when:** Budgets `percent_of_profit` same fee rule | **Pass** | Per-order actual if synced, else estimate |
| **Done when:** No blended effective-rate object | **Pass** | Totals in £ and % only |
| Open items first | Settled | O2 (rep price pinned), O7, O9 |

### Settled open items (plan §1) for this sprint

| Item | Decision | Applied? |
|---|---|---|
| O2 Representative price / no opaque effective rate | Show £ + % of rep price; pin target vs listing | Yes — target for estimates; listing price when linked |
| O7 Profit without actual fees | Use estimated fees, not material-only | Yes — Costing + budgets + shared helpers |
| O9 Budgets `percent_of_profit` | Fee-aware same as Costing; do not change other methods | Yes |

### Locked decisions applied (Sprint 3 planning chat)

| Topic | Decision | Applied? |
|---|---|---|
| Representative price | **A** target for estimates; **B** live listing when channel has a linked listing | Yes |
| Profit display | One fee-aware profit (estimate→actual); UI must state source clearly | Yes |
| Actual fee attribution (Costing) | **B** — full order fee total when product appears on the order | Yes |
| Estimate→actual granularity | Per order: synced fee rows if present, else channel estimate | Yes |
| `vat_on_fees` | 20% of other estimated fee £ totals (`is_enabled` respected) | Yes |
| Etsy `listing_fee` in estimate | Include in per-sale estimate | Yes |
| Variance threshold | Absolute gap ≥ **2** percentage points | Yes |
| Products list + MCP | Update fee-aware profit/margin (not Costing-only) | Yes |
| Fee amount sign | Use **abs(amount)** of fee lines | Yes |

### What shipped (behaviour)

1. **`SOM_Platform_Fees`:** estimate stack (tiers, optional ads, listing fee, VAT-on-fees); order actual abs totals; product/channel actual aggregates (rule B); preferred-channel summary for list/MCP; duplicate component guard after tier match.
2. **Costing UI:** summary platform fees + source badge; per-channel table (rep price source, estimate £/%, actual £/% with n=, variance pp, fee-aware profit).
3. **Budgets:** `percent_of_profit` = `(revenue − materials − fees) × %` via `line_profit` / `fees_for_order`.
4. **List + MCP:** fee-aware margin/profit; MCP exposes `platform_fees` and `fee_source`.

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-platform-fees.php` | Shared fee math (new) |
| `includes/class-som-products.php` | Fee-aware `recipe_costing` |
| `includes/class-som-budgets.php` | Fee-aware `percent_of_profit` |
| `includes/class-som-abilities.php` | MCP fee fields |
| `admin/views/product-edit.php` | Costing fee panel |
| `admin/views/products-list.php` | Margin + fee source badge |
| `admin/assets/css/admin.css` | Fee badges + variance row |
| `orderMachine.php` | Require helpers; `0.21.0` |
| `tests/sprint-up3-s3-smoke.php` | Sprint 3 smoke (new) |
| `stikerts/wordpress v4/Update-3-Sprint-Progress.md` | This progress record |

### `SOM_Platform_Fees` API surface (Sprint 3)

| Method | Role |
|---|---|
| `estimate_total` | Apply enabled estimate components (+ VAT on fee totals) |
| `order_actual_fee_total` / `fees_for_order` | Actual abs sum or estimate fallback |
| `line_profit` | Order-line fee-aware profit (budgets) |
| `product_channel_actuals` | Rule B aggregates + n= / avg fee per unit |
| `product_fee_costing` / `prefer_channel_row` | Per-channel Costing rows + preferred summary |
| `fee_source_label` | UI labels for estimate / actual / none |

### Verification (wp-env, 2026-08-10)

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s3-smoke.php
```

**Result:** `PASS — Update Package 3 Sprint 3 smoke`  
Covered: eBay/Etsy estimate maths (tiers, VAT, listing fee), `recipe_costing` fee fields, actual path after fixture sync + product on fee order, line profit actual vs estimate, listing as representative price.

### Suggested live check (Local / operator)

1. Open a product with target price → Costing shows estimated fees per channel and fee-aware profit labeled **Estimated fees**.
2. After fee sync on orders linked to that product → channel switches to **Actual fees** with n= and variance if ≥2 pp off.
3. Link a listing → rep. price shows **(listing)** for that channel.
4. Confirm a `percent_of_profit` manual budget funds less after fees than material-only profit would.
5. Products list margin badge shows Est. fees / Actual fees; MCP product payload includes `platform_fees` / `fee_source`.

### Gaps / residual risk (not blocking Sprint 3)

| Item | Notes |
|---|---|
| Duplicate estimate seed rows | Env had duplicate eBay `final_value_fee`; estimate math dedupes by component after tier match |
| Multi-item orders + rule B | Full order fees attributed to each product that appears — can overstate on multi-product orders (accepted lock) |
| Analytics reuse | Sprint 4 should call `SOM_Platform_Fees` / order profit helpers — do not duplicate |

### Explicitly out of scope for Sprint 3 (later)

| Item | Sprint |
|---|---|
| Analytics Dashboard / Chart.js | 4 |

---

## Sprint 4 — Analytics Dashboard

- **Status:** **Done** — confirmed complete vs `Update-3-Sprint-Plan.md` §5 Sprint 4 Create/Modify/Charts/Filters/**Done when**, settled O5/O6/O10 (+ O7 from Sprint 3), and Sprint 4 Q&A locks
- **Completed:** 2026-08-10
- **Verified on:** wp-env via `tests/sprint-up3-s4-smoke.php` (`PASS — Update Package 3 Sprint 4 smoke`)
- **Plugin version:** `0.22.0`
- **DB version:** `1.8.0` (unchanged — no schema migration)

### Plan Create / Modify checklist

| Plan item | Status | Notes |
|---|---|---|
| Create `admin/views/analytics.php` | **Done** | Single page; shared GET filters; JSON embed `#som-analytics-data` |
| Create `admin/assets/js/analytics.js` | **Done** | Chart.js wiring for all five charts |
| Query/helper methods (sales, profit, stock, channel, AOV) | **Done** | `includes/class-som-analytics.php` |
| Modify `admin/class-som-admin-menu.php` — register + enqueue | **Done** | Submenu **Analytics** (`som-analytics`); Chart.js 4.4.8 jsDelivr + `analytics.js` only on that page |
| Shared profit aggregator from Sprint 3 (reuse) | **Done** | `SOM_Platform_Fees::fees_for_order`; order-level `SOM_Analytics::order_profit` (not sum of `line_profit`) |
| Modify `orderMachine.php` | **Done** | Require analytics class; `SOM_VERSION` → `0.22.0` |

### Charts (plan §5)

| Chart | Type | Status | Notes |
|---|---|---|---|
| Sales over time | Line | **Done** | Revenue from `order_items.unit_price` × qty only |
| Profit over time | Line | **Done** | Order-level: revenue − stock-log COGS − fees once (estimate→actual) |
| Material stock over time | Line | **Done** | Multi-select materials; backward from `current_stock` |
| Orders by channel | Bar | **Done** | Order counts for the selected range |
| Average order value | Line | **Done** | Revenue ÷ priced-order count per bucket |

\*Plan text said sales fallback to `target_selling_price`; Sprint 4 lock superseded that — **sold price (`unit_price`) only**; drop lines with null/empty price from sales/profit/AOV.

### Filters (plan §5)

| Filter | Status | Notes |
|---|---|---|
| Date range 7 / 30 / 90 / this year / custom | **Done** | Site timezone bounds → UTC SQL window |
| Granularity daily / weekly / monthly | **Done** | Sales, profit, AOV, stock series |
| Channel where relevant | **Done** | Applies to order-backed charts |
| Material selector for stock | **Done** | Multi-select; chart empty until selection + Apply |

### Plan “Done when” checklist

| Criterion (plan wording) | Result | Evidence |
|---|---|---|
| One admin Analytics page; shared filter state across charts | **Pass** | One GET form drives all series |
| All five charts render with live aggregation | **Pass** | No summary tables; `dashboard_payload` |
| Profit uses estimate→actual fees | **Pass** | `fees_for_order` |
| Stock series matches walking backward from current stock | **Pass** | Smoke 100 → 90 → 70 |
| No build step; Chart.js via CDN like SortableJS | **Pass** | jsDelivr Chart.js 4.4.8 |

### Settled open items (plan §1) for this sprint

| Item | Decision | Applied? |
|---|---|---|
| O5 Companion charts | Both orders-by-channel + AOV | Yes |
| O6 Live vs summary tables | Live queries only | Yes |
| O10 Stock reconstruction | Walk backward from `current_stock` | Yes |
| O7 Profit without actual fees | Estimate until actual | Yes (Sprint 3 path reused) |

### Locked decisions applied (Sprint 4 planning chat)

| Topic | Decision | Applied? |
|---|---|---|
| Profit aggregation | **Order-level** once (industry practice for contribution charts; avoids double-counting fees) | Yes |
| Exclude statuses | Cancelled + refunded | Yes — `cancelled_sql` + best-effort `refunded_sql` on payload |
| Revenue | `unit_price` (sold) only — no target fallback | Yes |
| Null sold price | Drop that line from sales / profit / AOV | Yes |
| Orders by channel | Order counts; one bar per channel for the range | Yes |
| Stock default | Empty until user picks material(s) | Yes |
| Recurring platform expenses | Out of profit chart (same as Costing/budgets) | Yes |
| Data load | Embed JSON + form GET reload | Yes |
| Material COGS | Stock log `reason = new_order` × `unit_cost_at_time` | Yes |

### What shipped (behaviour)

1. **Analytics admin page** — shared filters (range, custom dates, granularity, channel, materials) reload via GET; summary totals for sales / profit / priced-order count.
2. **Five Chart.js charts** — sales, profit, AOV (line); orders by channel (bar); material stock (line when materials selected).
3. **`SOM_Analytics`** — live queries; exclude cancelled/refunded; eligible sold lines only; order-level fee-aware profit; stock reconstruction from `current_stock`.

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-analytics.php` | Aggregations (new) |
| `admin/views/analytics.php` | Dashboard UI (new) |
| `admin/assets/js/analytics.js` | Chart.js wiring (new) |
| `admin/assets/css/admin.css` | Analytics layout |
| `admin/class-som-admin-menu.php` | Submenu + page-gated CDN enqueue + `render_analytics` |
| `orderMachine.php` | Require class; `0.22.0` |
| `tests/sprint-up3-s4-smoke.php` | Sprint 4 smoke (new) |
| `stikerts/wordpress v4/Update-3-Sprint-Progress.md` | This progress record |

### `SOM_Analytics` API surface (Sprint 4)

| Method | Role |
|---|---|
| `parse_filters` / `resolve_date_bounds` | Shared GET filters |
| `excluded_orders_sql` / `refunded_sql` | Cancelled + refunded exclusion |
| `bucket_key` / `bucket_labels` / `bucket_end_utc` | Granularity series |
| `load_orders_with_items` | Live order + items query |
| `eligible_lines` / `order_material_cogs` / `order_profit` | Order-level fee-aware profit |
| `aggregate_sales_profit_aov` / `aggregate_orders_by_channel` / `aggregate_stock_series` | Chart series |
| `dashboard_payload` | Full embed for the view |

### Verification (wp-env, 2026-08-10)

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s4-smoke.php
```

**Result:** `PASS — Update Package 3 Sprint 4 smoke`  
Covered: custom bounds/labels, stock reconstruction, cancelled exclusion, null `unit_price` drop, order-level fees + material COGS from log, sales totals (£42 across 3 priced orders), channel bars, empty stock until selected, payload keys + view/js presence.

### Suggested live check (Local / operator)

1. Open **Order Machine → Analytics**.
2. Apply Last 30 days / daily — sales, profit, AOV, orders-by-channel render.
3. Select a material → stock chart appears after Apply.
4. Confirm cancelled orders do not inflate sales; lines without sold price are omitted.

### Gaps / residual risk (not blocking Sprint 4)

| Item | Notes |
|---|---|
| Refunded detection | Best-effort on `raw_payload` (no order-level refund column) |
| Multi-item COGS vs dropped lines | Material COGS still from full-order `new_order` stock log |
| Pre-existing orders in range | Charts include all eligible orders in the selected window |

### Explicitly out of scope (package complete)

| Item | Notes |
|---|---|
| Pre-computed analytics summary tables | Plan §7 / O6 — live only for v1 |
| Amazon / SP-API Financials | Plan §7 |
| Further Update Package 3 sprints | None — Sprint 4 was the last |

---

## Update Package 3 — complete

All four sprints done and smoke-verified. Plan source of truth remains [`Update-3-Sprint-Plan.md`](Update-3-Sprint-Plan.md).

