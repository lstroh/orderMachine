# Order Machine — Update Package 2 Sprint Progress

*Companion to [`Update-2-Sprint-Plan.md`](Update-2-Sprint-Plan.md). Plan stays the source of scope; this file records what shipped and how it was verified.*

Assumption: base plugin + Update Package 1 complete (`SOM_DB::DB_VERSION` was `1.5.0`). Specs in this folder: `01-Update-Overview.md` … `05-Update-Cursor-Prompt.md`.

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| U2-1 | Budgets schema + model | **Done** | Code complete vs plan; migrate on next admin load / activate |
| U2-2 | Funding + draw-down hooks | **Done** | Inline hooks in order-sync + PO receive; decisions locked in plan §4 |
| U2-3 | Budgets admin UI | **Done** | List/edit, badges, adjustments, R&D on budget + material |
| U2-4 | Order Board read UI | **Done** | Re-confirmed vs plan Create/Modify + done-when + §4 locks; DnD → U2-5 |
| U2-5 | Order Board gated DnD | Pending | Decisions locked in plan §4 U2-5; not built yet |

---

## Sprint U2-1 — Budgets schema + model

- **Status:** **Done** (confirmed complete vs `Update-2-Sprint-Plan.md` § Sprint U2-1 + §4–5 locked decisions)
- **Completed:** 2026-08-03
- **Verified on:** Static code review against plan (syntax check `php -l`). Live `dbDelta` / table presence not exercised in this session — runs via `SOM_DB::maybe_upgrade()` when `som_db_version` ≠ `1.6.0`.
- **Plugin version:** unchanged (`0.18.1`) — plan did not require a `SOM_VERSION` bump for U2-1
- **DB version:** `1.6.0`

### Plan requirements review (`Update-2-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Create `includes/class-som-budgets.php` | **Done** | CRUD, `is_active`, ledger-only balance, product/workflow helpers, one-per-material enforcement |
| Modify `includes/class-som-db.php` — four budget tables | **Done** | `budgets`, `budget_product_links`, `budget_workflow_links`, `budget_ledger` |
| Uniques / indexes / `is_active` | **Done** | Matches §5 locked schema |
| Bump `DB_VERSION` to `1.6.0` | **Done** | `SOM_DB::DB_VERSION` |
| Bootstrap require in `orderMachine.php` | **Done** | After `class-som-workflow-material-goals.php` |
| R&D linked write-off model helper (B) | **Done** | `SOM_Budgets::write_off_material()` — UI deferred to U2-3 |
| **Done when:** four tables after migrate | **Pass** (code) | DDL present; migrate on upgrade path |
| **Done when:** create material + manual budgets | **Pass** | `create()` enforces type/funding constraints |
| **Done when:** attach product + workflow links | **Pass** | `set_product_links` / `set_workflow_links`; DB allows cross-type |
| **Done when:** ledger updates `current_balance` | **Pass** | `insert_ledger()` only balance mutator |
| **Done when:** deactivate toggles `is_active` | **Pass** | `set_active()` / `update()` |
| Open items first | Settled | O2 + R&D (B) locked before build |

### Locked decisions applied (§4)

| Topic | Applied? | How |
|---|---|---|
| One material budget per material | Yes | `UNIQUE KEY material_id`; `create()` rejects duplicate via `get_for_material(..., false)` |
| Unique link pairs | Yes | `UNIQUE budget_product`, `UNIQUE budget_workflow` |
| Cross-type links allowed in DB | Yes | Link setters do not reject by budget `type` |
| `is_active` soft-deactivate | Yes | Column + `set_active()`; no hard-delete API |
| Balance only via ledger | Yes | Documented on class; `create()` starts at `0`; no direct balance update helpers |
| R&D write-off B | Yes | Stock `adjust_stock(-qty)` then budget `manual_adjustment` debit `qty × WA`; notes required; no-op budget if no active material budget |
| Schema `1.6.0` | Yes | |
| FK-style indexes | Yes | On link/ledger FKs + `type` / `is_active` |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | Budget DDL; `DB_VERSION` → `1.6.0` |
| `includes/class-som-budgets.php` | Domain model (new) |
| `orderMachine.php` | `require_once` for `SOM_Budgets` |
| `stikerts/wordpress v3/Update-2-Sprint-Plan.md` | Pre-build: locked review answers (uniques, `is_active`, R&D B, etc.) |
| `stikerts/wordpress v3/Update-2-Sprint-Progress.md` | This progress record |

### Schema created

New tables (no existing-table alters):

1. **`wp_som_budgets`** — `name`, `type` (`material`\|`manual`), `material_id` (nullable, unique), `funding_method`, `funding_value`, `target_reserve_amount`, `current_balance`, `notes`, `is_active`, timestamps
2. **`wp_som_budget_product_links`** — `budget_id`, `product_id`, `UNIQUE (budget_id, product_id)`
3. **`wp_som_budget_workflow_links`** — `budget_id`, `workflow_template_id`, `UNIQUE (budget_id, workflow_template_id)`
4. **`wp_som_budget_ledger`** — `budget_id`, `order_id`, `purchase_order_item_id`, `change_amount`, `reason`, `notes`, `created_at`

### `SOM_Budgets` API surface (U2-1)

| Method | Role |
|---|---|
| `get` / `query` / `get_for_material` | Read |
| `create` / `update` / `set_active` | CRUD + soft deactivate (type/`material_id` immutable after create) |
| `insert_ledger` / `get_ledger` | Audit + balance update |
| `get_product_link_ids` / `set_product_links` | Manual product scope |
| `get_workflow_link_ids` / `set_workflow_links` | Material workflow scope |
| `applies_to_product` / `applies_to_workflow` | Empty links = global (for U2-2 funding) |
| `write_off_material` | Linked R&D stock + budget debit |
| `is_overspent` / `is_low_balance` / `reason_label` | Helpers for U2-3 UI |

Ledger reason constants: `sale_funding`, `purchase_spend`, `manual_adjustment`.

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| After migrate, four tables exist with locked constraints | **Pass** (DDL); confirm on site after upgrade |
| Can create material budget (`funding_method = material_cost`) | **Pass** |
| Can create manual budgets (percent/fixed methods + value) | **Pass** |
| Can attach product links and workflow links | **Pass** |
| Inserting a ledger row updates `budgets.current_balance` | **Pass** |
| Deactivate toggles `is_active` | **Pass** |
| R&D model helper included | **Pass** (`write_off_material`) |

### Explicitly out of scope for U2-1 (later sprints)

| Item | Sprint |
|---|---|
| `fund_on_create` / `drawdown_on_receive` + order-sync / PO hooks | U2-2 |
| Budgets admin list/edit UI, badges, manual adjustment forms | U2-3 |
| R&D write-off UI | U2-3 |
| Order Board | U2-4 / U2-5 |

### Suggested live smoke (operator)

1. Load WP admin so `maybe_upgrade` runs → option `som_db_version` = `1.6.0`.
2. Confirm tables `{$wpdb->prefix}som_budgets`, `…_budget_product_links`, `…_budget_workflow_links`, `…_budget_ledger`.
3. (Optional WP-CLI / temporary probe) create material + manual budget, set links, `insert_ledger`, assert balance; `write_off_material` with notes.

### Gaps / residual risk

| Risk | Severity | Notes |
|---|---|---|
| Stock adjust then ledger fail in `write_off_material` | Low | Same sequential pattern as existing stock code; no DB transaction wrapper |
| Live migrate not run in this session | Info | Standard `maybe_upgrade` path; verify on Local site once |

---

## Sprint U2-2 — Funding + draw-down hooks

- **Status:** **Done** (re-confirmed 2026-08-03 vs `Update-2-Sprint-Plan.md` § Sprint U2-2, §4 U2-2 review table, §7 implementation notes)
- **Completed:** 2026-08-03
- **Verified on:** Code review of `class-som-budgets.php`, `class-som-order-sync.php`, `class-som-purchase-orders.php` against plan done-when + locked decisions; `php -l` clean. Live order sync / PO receive not exercised in-session.
- **Plugin version:** unchanged (`0.18.1`)
- **DB version:** unchanged (`1.6.0` — no schema delta in U2-2)

### Plan requirements review (`Update-2-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Create / extend `fund_on_create` | **Done** | `includes/class-som-budgets.php` |
| Create / extend `drawdown_on_receive` | **Done** | Same file |
| Modify `class-som-order-sync.php` | **Done** | After `decrement_on_create`, inside `if ( $apply_stock )` |
| Modify `class-som-purchase-orders.php` | **Done** | After successful `adjust_stock` in `receive()` loop only |
| Reuse stock WA path | **Done** | Material fund reads `material_stock_log` (`new_order`) `\|change_qty\| × unit_cost_at_time` |
| Reuse `recipe_costing` for profit | **Done** | `percent_of_profit` via `SOM_Products::recipe_costing` |
| **Done when:** `$apply_stock=true` → `sale_funding` for matching material + manual | **Pass** (code) | Product + workflow scope; inactive skipped |
| **Done when:** `$apply_stock=false` does not fund | **Pass** | Call never made on history import |
| **Done when:** PO receive → `purchase_spend` by landed delta | **Pass** | Negative; `purchase_order_item_id` set; opt-in if no budget |
| **Done when:** `mark_received` does not draw | **Pass** | No budget call in `mark_received()` |
| **Done when:** negative balances allowed | **Pass** | No floor in `insert_ledger` |
| Open items first | Settled | O3, O4 locked; U2-2 review answers in plan §4 |

### Locked U2-2 decisions applied (§4)

| Topic | Applied? | How |
|---|---|---|
| Funding idempotency | Yes | `has_sale_funding_for_order()` — any `sale_funding` for `order_id` skips entire fund |
| Material cost from stock log | Yes | `fund_material_from_stock_log()` |
| Inactive budgets skipped | Yes | `get_for_material(..., true)`; manual `query( is_active => 1 )` |
| Cancelled orders skip fund | Yes | `is_cancelled` early return |
| Workflow scope order-level | Yes | Primary product → `workflow_template_id` gates all material funding |
| Loss-making `percent_of_profit` | Yes | Negative `sale_funding` allowed (no clamp) |
| `unit_price = 0` is set | Yes | `effective_sold_price()`; only null/empty → `target_selling_price` |
| Ledger grain | Yes | Material: one row per stock-log line; manual: one row per order item × budget |
| Draw-down ledger failure | Yes | `error_log` + return error; `receive()` does not abort on it |
| Manual ignores workflow links | Yes | Product scope only |

### Behaviour summary

```
Order create ($apply_stock=true):
  assign_on_create
  → decrement_on_create
  → fund_on_create
       · skip if cancelled or existing sale_funding
       · material: stock log new_order lines → sale_funding (workflow scope)
       · manual: each order item × active manual budget (product scope) → sale_funding

PO receive (per line after adjust_stock success):
  → drawdown_on_receive( poi_id, delta, landed )
       · active material budget only → purchase_spend = −(delta × landed)
       · ledger error: log, continue remaining lines

mark_received: no budget hook
```

### Files delivered / modified

| File | Purpose |
|---|---|
| `includes/class-som-budgets.php` | `fund_on_create`, `drawdown_on_receive` + private helpers |
| `includes/class-som-order-sync.php` | Inline fund call after stock decrement |
| `includes/class-som-purchase-orders.php` | Inline draw-down after stock adjust in `receive()` |
| `stikerts/wordpress v3/Update-2-Sprint-Plan.md` | §4 U2-2 locked review answers; §7 idempotency note |
| `stikerts/wordpress v3/Update-2-Sprint-Progress.md` | This progress record |

### API surface added (U2-2)

| Method | Role |
|---|---|
| `fund_on_create( $order_id )` | Sale funding; always returns `true` (ledger errors logged) |
| `drawdown_on_receive( $poi_id, $qty, $landed )` | Purchase spend; logs and returns `WP_Error` on ledger failure (caller continues) |

Private helpers: `has_sale_funding_for_order`, `order_workflow_template_id`, `fund_material_from_stock_log`, `fund_manual_from_order_items`, `effective_sold_price`, `manual_funding_amount`.

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| Sync/create with `$apply_stock=true` writes `sale_funding` for matching budgets | **Pass** (code) |
| History import (`$apply_stock=false`) does not fund | **Pass** |
| PO receive draws material budgets by landed line total | **Pass** |
| `mark_received` shortfall does not draw down | **Pass** |
| Negative balances allowed | **Pass** |

### Explicitly out of scope for U2-2 (later sprints)

| Item | Sprint |
|---|---|
| Budgets admin list/edit UI, badges, manual adjustment forms | U2-3 |
| R&D write-off UI | U2-3 |
| Order Board | U2-4 / U2-5 |

### Suggested live smoke (operator)

1. Create an active material budget (+ optional workflow scope) and a manual budget (product scope / global).
2. Incremental sync (or create) an order with stock applied → ledger `sale_funding` rows; balances increase.
3. Re-sync same order → no duplicate `sale_funding`.
4. History import path → no funding.
5. Receive a PO line for the material → `purchase_spend` negative by `delta × landed`; shortfall `mark_received` → no extra draw-down.
6. Deactivated budget → no fund / no draw-down.

### Gaps / residual risk

| Risk | Severity | Notes |
|---|---|---|
| Stock succeeded, funding partially written then failed | Low | Order-level idempotency skips remainder on retry; fix via manual adjustment or ledger repair |
| Draw-down logged failure after stock | Low | Stock kept; budget undrawn until manual adjustment |
| Live sync/receive not run in this session | Info | Verify with smoke list above |

---

## Sprint U2-3 — Budgets admin UI

- **Status:** **Done** (re-confirmed 2026-08-03 vs `Update-2-Sprint-Plan.md` § Sprint U2-3 done-when, Create/Modify file list, and §4 U2-3 review table)
- **Completed:** 2026-08-03
- **Verified on:** Second-pass code review of views, admin menu handlers, material-edit R&D UI, CSS badges, and `SOM_Budgets` URL helpers against plan; `php -l` clean on all touched PHP. Live admin click-through not exercised in-session.
- **Plugin version:** unchanged (`0.18.1`)
- **DB version:** unchanged (`1.6.0` — no schema delta in U2-3)

### Plan requirements review (`Update-2-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Create `admin/views/budgets-list.php` | **Done** | Active default; type filter; balance + target; low/overspent badges |
| Create `admin/views/budget-edit.php` | **Done** | Create/edit/detail + ledger + manual adjustment + material R&D write-off |
| Modify `admin/class-som-admin-menu.php` | **Done** | Budgets submenu after Materials; `render_budgets`; `handle_budgets_actions`; material write-off in `handle_materials_actions` |
| Modify `admin/views/material-edit.php` | **Done** | R&D write-off form; Adjust stock note that it does not debit budget |
| Modify `admin/assets/css/admin.css` | **Done** | `som-badge-low-balance`, `som-badge-overspent`, checkbox list, adjust/write-off form layout |
| Modify `includes/class-som-budgets.php` | **Done** | `list_url` / `detail_url`; type/funding labels; `materials_available_for_budget` |
| Extra (ledger PO resolve) | **Done** | `SOM_Purchase_Orders::get_item()` for purchase_spend → PO link |
| **Done when:** list active default + balances + badges | **Pass** | |
| **Done when:** create/edit material (no existing budget + workflow checkboxes) | **Pass** | |
| **Done when:** create/edit manual (funding + product checkboxes) | **Pass** | |
| **Done when:** ink help on create | **Pass** | |
| **Done when:** ledger recent 50, order/PO links | **Pass** | |
| **Done when:** manual adjustment with required notes | **Pass** | Handler + HTML `required` |
| **Done when:** R&D write-off on budget detail **and** material edit | **Pass** | Notes required; calls `write_off_material` |
| Open items first | Settled | O1 + R&D (B); §4 U2-3 table |

### Locked U2-3 decisions applied (§4)

| Topic | Applied? | How |
|---|---|---|
| R&D UI on both surfaces | Yes | Budget detail form + material edit form |
| Adjust stock unchanged + skip-budget note | Yes | Existing delta handler; description updated |
| Manual adjustment notes required | Yes | Empty notes rejected in handler |
| Ledger recent 50 | Yes | `get_ledger( $id, 50 )` |
| Ledger order / PO links | Yes | `SOM_Orders::detail_url`; PO via `get_item` → `detail_url` |
| Menu after Materials | Yes | Submenu registration order |
| List active default | Yes | `som_status` defaults to `active` |
| Hide materials with existing budget | Yes | `materials_available_for_budget()` |
| Multi-checkbox scopes; intended combinations | Yes | Workflow UI on material only; product UI on manual only |
| Editable as model allows | Yes | Type/`material_id` fixed after create |
| Ink help (O1) | Yes | Create-type description |

### Behaviour summary

```
Budgets list (som-budgets):
  · Default active; filters status + type + search
  · Badges: overspent (balance < 0), else low balance (< target reserve)

Budget create/edit:
  · Material: pick unused material; optional workflow checkboxes; ink tip on create
  · Manual: funding method/value; optional product checkboxes
  · Save → create/update + set_*_links for the intended scope only

Budget detail (existing):
  · Manual adjustment → insert_ledger(manual_adjustment); notes required
  · Material R&D write-off → write_off_material (stock + budget debit)
  · Ledger: newest 50; sale_funding→order; purchase_spend→PO

Material edit:
  · Adjust stock unchanged (no budget)
  · Separate R&D write-off → same write_off_material helper
```

### Files delivered / modified

| File | Purpose |
|---|---|
| `admin/views/budgets-list.php` | List (new) |
| `admin/views/budget-edit.php` | Create/edit/detail (new) |
| `admin/views/material-edit.php` | R&D write-off + Adjust stock note |
| `admin/class-som-admin-menu.php` | Submenu after Materials; render; budget + material write-off handlers |
| `admin/assets/css/admin.css` | Badges + form layout |
| `includes/class-som-budgets.php` | URL/label helpers; materials available for create |
| `includes/class-som-purchase-orders.php` | `get_item()` for ledger PO links |
| `stikerts/wordpress v3/Update-2-Sprint-Plan.md` | §4 U2-3 locks (pre-build) |
| `stikerts/wordpress v3/Update-2-Sprint-Progress.md` | This record |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| List (active default) shows balances + low/overspent badges | **Pass** |
| Create/edit material (pick material without existing budget + optional workflow checkboxes) | **Pass** |
| Create/edit manual (funding method/value + product checkboxes) | **Pass** |
| Ink help on create | **Pass** |
| Detail ledger (recent 50, order/PO links) | **Pass** |
| Manual adjustment with required notes | **Pass** |
| R&D write-off on budget detail **and** material edit with required notes | **Pass** |

### Explicitly out of scope for U2-3 (later sprints)

| Item | Sprint |
|---|---|
| Order Board read UI | U2-4 |
| Order Board gated DnD | U2-5 |

### Suggested live smoke (operator)

1. Open **Order Machine → Budgets** (directly under Materials). Create material budget (picker hides materials that already have one) + optional workflow checks; create manual with funding + product checks.
2. Confirm list badges when balance &lt; reserve / negative.
3. Record manual adjustment (notes required); confirm ledger row and balance change.
4. R&D write-off from budget detail and from material edit; confirm stock ↓ and budget debit (or stock-only if no active budget).
5. Confirm Adjust stock still works and does not touch budget.
6. After a funded order / received PO: ledger `sale_funding` → order detail; `purchase_spend` → PO detail.

### Gaps / residual risk

| Risk | Severity | Notes |
|---|---|---|
| Product scope list capped at 500 active | Low | Linked inactive products appended when editing |
| Live admin UI not clicked in-session | Info | Use smoke list above |

---

## Sprint U2-4 — Order Board read UI

- **Status:** **Done** (re-confirmed 2026-08-03 vs `Update-2-Sprint-Plan.md` § Sprint U2-4 Create/Modify/done-when + §4 U2-4 review table)
- **Completed:** 2026-08-03
- **Verified on:** Second-pass code review of board view, JS (pins/column ←→), admin menu submenu + enqueue + AJAX, and `SOM_Orders` board helpers against every plan criterion; `php -l` clean. Live admin click-through not exercised in-session.
- **Plugin version:** unchanged (`0.18.1`)
- **DB version:** unchanged (`1.6.0` — no schema delta; pins + column order in user meta only)

### Plan requirements review (`Update-2-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Create `admin/views/orders-board.php` | **Done** | Kanban columns/cards, filters form, volume notices, history link |
| Create `admin/assets/js/orders-board.js` | **Done** | Pin toggle AJAX, pinned-only filter, column ←/→ + save AJAX |
| Board styles in `admin.css` (or dedicated) | **Done** | Horizontal scroll board layout in `admin/assets/css/admin.css` |
| Modify `admin/class-som-admin-menu.php` — submenu after Orders | **Done** | `som-orders-board` immediately after Orders |
| Asset enqueue for board page | **Done** | `som-admin` CSS + `som-orders-board` JS + `somBoard` localize |
| AJAX handlers for pins + column order | **Done** | `ajax_board_toggle_pin`, `ajax_board_save_columns` |
| Extend order query helpers | **Done** | `query_board`, `board_columns`, pin/column meta, time/personalisation helpers |
| **Done when:** columns = step names + Unassigned | **Pass** | From incomplete non-cancelled only |
| **Done when:** column order = user meta + auto lowest-`step_order` | **Pass** | ←/→ buttons; unknowns append via heuristic |
| **Done when:** card fields + badges + batch + View | **Pass** | Linked order ID + product + View only |
| **Done when:** filters channel / product / workflow / free-text / pinned-only | **Pass** | Two dropdowns; search includes personalisation |
| **Done when:** pins AJAX user meta | **Pass** | `som_board_pinned_orders` |
| **Done when:** oldest-first; warn then hard cap | **Pass** | Warn ≥200; cap 500 |
| **Done when:** horizontal scroll; Orders list history link | **Pass** | |
| Open items first | Settled | O5, O6, O7 + §4 U2-4 table |

### Locked U2-4 decisions applied (§4)

| Topic | Applied? | How |
|---|---|---|
| Unassigned column | Yes | Null/`empty` current step → `BOARD_UNASSIGNED_KEY`; auto-sorted first |
| Exclude cancelled | Yes | `NOT cancelled_sql` in `query_board` |
| Two filter dropdowns | Yes | Independent product + workflow selects |
| Column ←/→ | Yes | Header buttons; persist via `som_board_save_columns` |
| Instant pin/column AJAX | Yes | Meta keys `som_board_pinned_orders`, `som_board_column_order` |
| Oldest order first | Yes | `ORDER BY order_date ASC, id ASC` |
| Warn 200 / cap 500 | Yes | `BOARD_WARN` / `BOARD_CAP`; notices in view |
| Progress badges in U2-4 | Yes | `som-badge-step-*` for timer/script/batch/error/etc. |
| Menu after Orders | Yes | Submenu registration order |
| Card links: ID, product, View | Yes | Card body not a single click target |
| Free-text incl. personalisation | Yes | Buyer + external ID + item personalisation LIKE |
| Completed only via Orders list (O6) | Yes | `is_complete = 0`; “View history” → complete list filter |
| Horizontal scroll (O7) | Yes | `.som-board-scroll { overflow-x: auto }` |

### Behaviour summary

```
Orders Board (som-orders-board):
  · Query incomplete + non-cancelled (oldest first, cap 500)
  · Columns = distinct current step names + Unassigned when needed
  · Column order = user meta then auto MIN(step_order) for new names
  · Cards: channel, buyer, personalisation preview, step, time-in-step,
    progress badges, batch link (waiting_batch), pin ★
  · Links: external order ID → detail; product → product detail; View → detail
  · Filters (GET): channel, product, workflow, free-text
  · Client: pinned-only toggle; pin AJAX; column ←/→ AJAX
  · Volume: info notice ≥200; warning + oldest-only when >500
```

### Files delivered / modified

| File | Purpose |
|---|---|
| `includes/class-som-orders.php` | `query_board`, columns, pins, time helpers, `board_url` |
| `admin/views/orders-board.php` | Board page (new) |
| `admin/assets/js/orders-board.js` | Pins + column reorder (new) |
| `admin/assets/css/admin.css` | Board layout / horizontal scroll |
| `admin/class-som-admin-menu.php` | Submenu after Orders; enqueue; AJAX |
| `stikerts/wordpress v3/Update-2-Sprint-Plan.md` | §4 U2-4 locks (pre-build) |
| `stikerts/wordpress v3/Update-2-Sprint-Progress.md` | This record |

### API / helpers added (U2-4)

| Method / constant | Role |
|---|---|
| `BOARD_WARN` / `BOARD_CAP` | 200 / 500 volume guards |
| `BOARD_PINNED_META` / `BOARD_COLUMN_META` | User meta keys |
| `BOARD_UNASSIGNED_KEY` | Column key for no current step |
| `query_board` | Board dataset |
| `board_columns` / `board_step_order_map` | Column list + auto order |
| `get/set/toggle_board_pin*` | Pin persistence |
| `get/set_board_column_order` | Column order persistence |
| `format_time_in_step` / `truncate_personalisation` / `progress_status_label` | Card display |
| `board_url` | Admin URL helper |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| Columns = distinct current step names among incomplete non-cancelled + Unassigned when needed | **Pass** |
| Column order = saved per-user order merged with auto lowest-`step_order` (←/→) | **Pass** |
| Cards: linked order ref, channel badge, buyer, linked product, personalisation, step, time in step, progress badges, batch→Batches, View | **Pass** |
| Filters: channel, product, workflow (two dropdowns), free-text (incl. personalisation), pinned-only | **Pass** |
| Pins via AJAX user meta | **Pass** |
| Oldest-first within columns; volume warn then hard cap | **Pass** |
| Horizontal scroll; link to Orders list for history | **Pass** |

### Explicitly out of scope for U2-4

| Item | Sprint |
|---|---|
| SortableJS gated drag-and-drop / `advance-step` | U2-5 |

### Suggested live smoke (operator)

1. Open **Order Machine → Orders Board** (directly under Orders).
2. Confirm columns for current steps + Unassigned; ←/→ reorder persists after refresh.
3. Pin a card; toggle **Pinned only**; unpin.
4. Filter by channel, product, workflow, and personalisation search.
5. Confirm progress badges and batch link when waiting on a batch.
6. Confirm order ID / product / View links; card body itself is not one big link.
7. Confirm cancelled/completed orders are absent; use Orders list / View history for history.
8. (Optional) With many open orders: info notice at ≥200; hard cap notice above 500.

### Gaps / residual risk

| Risk | Severity | Notes |
|---|---|---|
| N+1 `find_for_order` for waiting_batch cards | Low | Only when progress status is `waiting_batch` |
| Product/workflow filter dropdowns capped (500 / 200 active) | Low | Same pattern as other admin lists |
| Live admin UI not clicked in-session | Info | Use smoke list above |

---

## Verdict

**U2-4 is complete** relative to `Update-2-Sprint-Plan.md`: all Create/Modify files, every done-when criterion, and all §4 U2-4 locked decisions are implemented. Next: **U2-5 — Order Board gated drag-and-drop**.
