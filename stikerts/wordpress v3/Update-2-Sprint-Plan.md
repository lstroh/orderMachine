# Update Package 2 — Sprint Plan

*Planning only. No plugin implementation in this document’s delivery. Assumes the base plugin plus Update Package 1 (Raw Material Purchasing + Batch Processing) are already built and working.*

**Source specs:** `01-Update-Overview.md` … `05-Update-Cursor-Prompt.md` in this folder.  
**Code examined against:** live plugin (schema `SOM_DB::DB_VERSION = 1.5.0`).

---

## 1. Consolidated open items

`02-Update-Data-Model.md` has **no** Open items section. Items below are from `03-Update-Budgets.md` and `04-Update-Order-Board.md`.

| # | Source | Open item | What it blocks / affects |
|---|--------|-----------|---------------------------|
| O1 | Budgets §6.1 | **Ink tracking** — ink is not a discrete-unit material; auto `material_cost` funding needs an ink material + recipe rows, or use a manual `fixed_amount` budget instead | Ops / product setup; not schema for this package |
| O2 | Budgets §6.2 | **Per-workflow scoping** — material budgets global vs scoped per workflow (like Update Package 1 goal costs) | Schema + funding eligibility + admin UI |
| O3 | Budgets §6.3 | **Negative balances** — allow (recommended) vs block when overspent | Validation / UI messaging only |
| O4 | Budgets §6.4 | **`percent_of_profit` basis** — actual sold price vs target selling price | Funding calculation |
| O5 | Board §7.1 | **Column ordering** — lowest-step-order heuristic vs manual pin/reorder | Board UI + per-user persistence |
| O6 | Board §7.2 | **Completed orders** — active-only (recommended) vs peek at recently completed | Board query / columns / UX |
| O7 | Board §7.3 | **Narrow-screen behaviour** — horizontal scroll vs stacked/collapsed columns | CSS / layout only |

---

## 2. Clarifying questions (kept visible) and answers

### Q1 — Material budget scoping (O2)

Specs say material budgets are global (fund from any product using that material). Keep that for v1, or add per-workflow scoping now?

**Answer:** **Both.** Default is global; optional per-workflow-template scope via links.

### Q2 — Board column ordering (O5)

Ship with “lowest step-order seen” only, or build manual column pinning/reordering from the start?

**Answer:** **Both.** Auto-order by lowest `step_order` seen, **plus** manual reorder (persist per-user). Exact UX for reorder can be refined during Board sprints; plan for both modes.

### Recommendations (confirmed)

| Topic | Recommendation | Confirmed? |
|-------|----------------|------------|
| Ink (O1) | Out of scope for this package — ops note: add ink as a material in recipes, or use a manual `fixed_amount` budget | Yes |
| Negative balances (O3) | Allow (same signal as negative `materials.current_stock`) | Yes |
| `percent_of_profit` (O4) | Use actual `order_items.unit_price` when set, else `products.target_selling_price` | Yes |
| Completed orders (O6) | Active / incomplete only; link to full Orders list for history | Yes |
| Narrow screen (O7) | Horizontal scroll; no stacked mobile view in this package | Yes |

---

## 3. Spec vs code discrepancies

Treat the existing codebase as ground truth. None of these invalidate the features; they refine hook points and naming.

| Spec assumption | Reality in code |
|-----------------|-----------------|
| Budget funding / draw-down via WordPress actions | **No** `do_action` / `apply_filters` in plugin PHP — add **inline calls** |
| “Purchase receipt” handler beside WAC | Receipt = `SOM_Purchase_Orders::receive()` on `purchase_orders` / `purchase_order_items`. WAC is inside `SOM_Materials::adjust_stock()` when receive passes `value_change` + `sync_unit_cost => true` — not a separate step |
| `material_stock` / `purchase_receipt` tables | Stock on `materials` + audit `material_stock_log`. Purchases are `purchase_orders` / `purchase_order_items` |
| Order create + stock decrement | `SOM_Order_Sync::upsert_order` → `SOM_Material_Stock::decrement_on_create` when `$apply_stock` is true (history import uses `false`) |
| `products.target_selling_price` + sold price on items | **Exist:** `target_selling_price`; items have **`unit_price`** only (no `sold_price` / `line_total`) |
| Budget tables already present | **Absent** — planning only until U2-1 |
| Order Board / SortableJS | **Absent** — Orders list + detail only |
| `advance-step` for drag-and-drop | `POST /wp-json/som/v1/orders/{id}/advance-step`, body `{}`, **no target step**. Marks current step done via `SOM_Workflow_Engine::mark_done`. Board must validate next column client-side. Only works when progress status is `in_progress` |
| `02` Open items | Spec prompt said files 2–4 each have Open items; **02 has none** |

### Confirmed hook points

```
Order create (funding):
  SOM_Order_Sync::upsert_order
    → SOM_Workflow_Engine::assign_on_create
    → if $apply_stock:
         SOM_Material_Stock::decrement_on_create( $order_id )
         SOM_Budgets::fund_on_create( $order_id )   // add here

PO receive (draw-down):
  SOM_Purchase_Orders::receive( $id, $deltas )
    → per line after successful adjust_stock(...):
         SOM_Budgets::drawdown_on_receive( ... )   // add here
  Do NOT hook mark_received() (shortfall close — no stock / no WAC)

Board DnD:
  SortableJS drop → POST .../orders/{id}/advance-step  body "{}"
  Success: { ok, order_id, current_step_id, current_step_name, is_complete }
```

---

## 4. Locked product decisions

| Topic | Decision |
|-------|----------|
| Material budget scope | **Both:** empty `budget_workflow_links` = global; non-empty = fund only when the order’s assigned workflow template is linked |
| Manual budget scope | Unchanged from spec: empty `budget_product_links` = all products; else product-scoped |
| Board column order | Auto by lowest `step_order` seen across templates for that step name; **plus** per-user manual reorder (user meta, e.g. `som_board_column_order`). New columns not in saved order append via auto heuristic |
| Ink | Ops-only; not implemented in this package |
| Negative balances | Allowed; surface overspent / low-balance badges |
| Effective sold price | `order_items.unit_price` if set (**including 0**), else `products.target_selling_price` |
| Board population | Incomplete orders only (`is_complete = 0`, not cancelled as appropriate to existing list filters) |
| Narrow screen | Horizontal scroll |
| One material budget per material | **Yes** — `UNIQUE` on `budgets.material_id` where used; model rejects a second `type=material` row for the same material |
| Link-row uniqueness | **Yes** — `UNIQUE (budget_id, product_id)` and `UNIQUE (budget_id, workflow_template_id)` |
| Cross-type links in DB | **Allowed** — product links on material budgets / workflow links on manual budgets are not DB-rejected; UI (U2-3) only offers the intended combinations |
| Budget lifecycle | **`is_active`** soft-deactivate (materials-style); no hard-delete of budgets with history for v1 |
| Balance mutation | **`current_balance` only via ledger helper**; create starts at `0`; no direct balance edits |
| R&D / non-sale write-off | **B — Linked:** one action decrements stock and debits the material budget by `qty × WA unit_cost` (`manual_adjustment`); notes for reason. Standalone budget adjustments remain available |
| Schema version | Bump `SOM_DB::DB_VERSION` / `som_db_version` to **`1.6.0`** |
| Indexes | Add FK-style keys on budget tables (`budget_id`, `material_id`, `order_id`, `purchase_order_item_id`, etc.) |

### U2-2 funding / draw-down (from U2-2 review)

| Topic | Decision |
|-------|----------|
| Funding idempotency | Skip entire `fund_on_create` if any `sale_funding` ledger row already exists for that `order_id` |
| Material funding cost | From `material_stock_log` where `reason = new_order`: `\|change_qty\| × unit_cost_at_time` (after stock decrement) |
| Inactive budgets | Skip funding and draw-down when `is_active = 0` |
| Cancelled / stock no-op | `fund_on_create` no-ops when order is cancelled; ledger idempotency covers re-entry after a prior fund |
| Workflow scope (multi-line) | Order-level: primary product’s workflow gates **all** material funding on the order |
| `percent_of_profit` when loss | Allow negative `sale_funding` (do not clamp profit to 0) |
| `unit_price = 0` | Treated as set (effective sold price 0); only `NULL` falls back to `target_selling_price` |
| Ledger grain | Manual: one `sale_funding` row per order item per matching budget. Material: one row per stock-log material line (aggregated consumption, matches stock) |
| Draw-down ledger failure | Log and continue (do not abort remaining PO receive lines) |
| Manual + workflow links | Ignore workflow links when funding manual budgets (product scope only) |

### U2-3 Budgets admin UI (from U2-3 review)

| Topic | Decision |
|-------|----------|
| R&D write-off UI location | **Both** — material budget detail **and** material edit page |
| Plain Adjust stock | Keep existing material stock delta as-is (does not touch budgets); add a **separate** R&D write-off action; short note that Adjust stock does not debit the budget |
| Manual adjustment notes | **Required** (same as write-off) |
| Ledger depth | Recent **50** rows only (no full-history pagination in this sprint) |
| Ledger row links | `sale_funding` → order detail; `purchase_spend` → PO detail (resolve via `purchase_order_item_id`) |
| Menu placement | Budgets submenu **after Materials** |
| List default filter | **Active** only (materials-style; allow All / Inactive) |
| Material picker on create | **Hide** materials that already have a material budget |
| Product / workflow scope UX | Multi-checkbox lists; UI only offers intended combinations (workflow on material, products on manual) |
| Editable after create | As model already allows — name, notes, target reserve, `is_active`; manual funding method/value + product links; material workflow links. Type / `material_id` immutable. No further UI lock-down |
| Ink (O1) | Short help text on create — ink as material recipe **or** manual `fixed_amount` budget |

### U2-4 Order Board read UI (from U2-4 review)

| Topic | Decision |
|-------|----------|
| No current step | **Unassigned** column for incomplete orders with null `current_step_id` (needs mapping / needs workflow) |
| Cancelled orders | **Exclude** always (even if incomplete) |
| Product / workflow filter | **B — two dropdowns** (product and workflow template, independently) |
| Column reorder UX | **B — ←/→ buttons** on column headers; persist per-user |
| Pins + column order save | Instant AJAX to user meta; keys `som_board_pinned_orders`, `som_board_column_order` |
| Within-column card sort | **Oldest order first** (`order_date` ASC, then `id` ASC) |
| Volume | Load all incomplete open orders; **warn** ≥ **200**; hard **cap 500** (oldest kept when capped) |
| Progress badges | **In U2-4** — show `waiting_timer` / `waiting_script` / `waiting_batch` / `error` (and batch link when applicable); not deferred to U2-5 |
| Menu placement | **Orders Board** submenu immediately after **Orders** |
| Card links | Only **order ID**, **product name(s)**, and explicit **View** → order detail; card body itself not a single click target |
| Free-text search | Buyer, external order ID, **and** personalisation (extend beyond list query) |
| Completed orders (O6) | Active/incomplete only; link to Orders list for history |
| Narrow screen (O7) | Horizontal scroll |

### U2-5 Order Board gated DnD (from U2-5 review)

| Topic | Decision |
|-------|----------|
| Missing next-step column | **A — Prefill** empty columns for every reachable next step name among **draggable** (`in_progress` + can advance) cards on the board. Merge into column order via existing user-meta + auto heuristic. Avoids “nowhere to drop” when no order is in that step yet |
| Last step / complete | **A — Ephemeral Complete drop zone** always available when any draggable card is on its final step; successful drop removes the card (`is_complete`) |
| Next-step data on cards | **Yes** — server-rendered attrs (e.g. `data-next-step-name`, `data-can-advance`, `data-progress-status`, last-step flag); computed from workflow like detail “Mark done” |
| Locked cards | **Disable drag entirely** when not advanceable: `waiting_*` / `error` / `pending`, Unassigned, and any card where `can_mark_done` would fail |
| Post-drop status | **B — Extend** `advance-step` JSON with `progress_status`, batch summary when `waiting_batch`, plus `can_advance` / `next_step_name` / `is_last_step` so the board can update badges and keep chaining advances without reload |
| Within-column reorder | **Disabled** — cross-column advance only; keep oldest-first visual order |
| SortableJS CDN | **Pin `1.15.6`** via `wp_enqueue_script` from jsDelivr: `https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js` (dependency of `som-orders-board`) |
| Zero-gate / multi-skip | **Trust API response** — place/remove card from `current_step_name` / `is_complete`, not from the drop-target column name |
| REST on board | Localize `restUrl` + `restNonce` on `somBoard` (same pattern as `somAdmin` on order detail) |

### R&D / non-sale write-off (from U2-1 review Q5)

**Answer: B — Linked write-off.**

One operator action decrements material stock **and** posts a negative `manual_adjustment` on that material’s budget for `qty × WA unit_cost` (same cost basis as stock/WAC). Notes **required** (e.g. “R&D”).

| Layer | Where it lands |
|-------|----------------|
| Model helper | `SOM_Budgets::write_off_material` — stock then ledger; no-op budget side if no active material budget |
| UI | U2-3 — **both** material budget detail and material edit (beside Adjust stock) |
| Standalone budget adjustment | Still available; notes required |

---

## 5. Schema delta beyond `02-Update-Data-Model.md`

Specs define three new tables. Locked scoping adds a **fourth**, plus columns/constraints from U2-1 review:

### Spec tables (updated for locked decisions)

1. **`wp_som_budgets`**
   - As in `02`, plus **`is_active` TINYINT(1) NOT NULL DEFAULT 1**
   - Types: `bigint(20) unsigned` PKs/FKs to match existing `SOM_DB` style
   - **`UNIQUE KEY material_id (material_id)`** — enforces one material budget per material (MySQL allows multiple NULLs for manual budgets)
   - Keys on `material_id`, `type`, `is_active` as useful
2. **`wp_som_budget_product_links`** — as in `02`, plus **`UNIQUE KEY budget_product (budget_id, product_id)`**, KEY on `product_id`
3. **`wp_som_budget_ledger`** — as in `02`, plus KEYS on `budget_id`, `order_id`, `purchase_order_item_id`

### Added for “both” material / workflow scope

4. **`wp_som_budget_workflow_links`**
   - `id` bigint PK AI
   - `budget_id` bigint FK → budgets.id
   - `workflow_template_id` bigint FK → workflow_templates.id
   - `created_at` DATETIME
   - **`UNIQUE KEY budget_workflow (budget_id, workflow_template_id)`**, KEY on `workflow_template_id`
   - **Semantics:** for a **material** budget, no rows = global (any order consuming that material funds it); one or more rows = fund only when the order’s workflow template (from primary-product assignment) is in the set.

No modifications to existing tables. Bump to **`1.6.0`** via `dbDelta` as usual.

---

## 6. Sprint breakdown

Budgets and Order Board are independent (per `01-Update-Overview.md`). Sequence below builds Budgets first (schema + hooks), then Board. They may be interleaved or reordered later without shared blockers beyond admin-menu registration.

---

### Sprint U2-1 — Budgets schema + model

| | |
|--|--|
| **Feature** | Budgets — tables + domain helpers |
| **Create** | `includes/class-som-budgets.php` (CRUD + deactivate via `is_active`; ledger write is sole balance mutator; product/workflow scope helpers; enforce one material budget per material) |
| **Modify** | `includes/class-som-db.php` (dbDelta for all four budget tables + uniques/indexes/`is_active`; bump `DB_VERSION` to `1.6.0`); require/bootstrap in `orderMachine.php` |
| **Done when** | After migrate, four tables exist with locked constraints; can create material (`funding_method = material_cost`) and manual budgets; can attach product links and workflow links (DB allows cross-type); inserting a ledger row updates `budgets.current_balance`; deactivate toggles `is_active` |
| **Open items first** | O2 locked. R&D linked write-off (B) locked — include model helper here if practical; UI in U2-3 |

---

### Sprint U2-2 — Funding + draw-down hooks

| | |
|--|--|
| **Feature** | Budgets — behavioural hooks |
| **Create / extend** | `SOM_Budgets::fund_on_create`, `SOM_Budgets::drawdown_on_receive` in `includes/class-som-budgets.php` |
| **Modify** | `includes/class-som-order-sync.php` (call fund after `decrement_on_create`, same `$apply_stock` gate); `includes/class-som-purchase-orders.php` (call draw-down after successful `adjust_stock` in `receive()` loop) |
| **Reuse** | Material consumption / WA unit cost consistent with stock path; `SOM_Products::recipe_costing` for `percent_of_profit` material cost of product |
| **Done when** | Sync/create with `$apply_stock=true` writes `sale_funding` ledger rows and balances for matching material + manual budgets (respecting product + workflow scope); history import (`$apply_stock=false`) does not fund; PO receive draws material budgets by landed line total (`purchase_spend`, negative, `purchase_order_item_id` set); `mark_received` shortfall does not draw down; negative balances allowed |
| **Open items first** | O3, O4 locked |

---

### Sprint U2-3 — Budgets admin UI

| | |
|--|--|
| **Feature** | Budgets — admin pages |
| **Create** | `admin/views/budgets-list.php`; `admin/views/budget-edit.php` (create/edit/detail + ledger + manual adjustment + R&D write-off for material budgets) |
| **Modify** | `admin/class-som-admin-menu.php` (Budgets submenu after Materials + form handlers; R&D write-off handler for materials too); `admin/views/material-edit.php` (R&D write-off form + note that Adjust stock skips budget); `admin/assets/css/admin.css` (list badges, light layout); `includes/class-som-budgets.php` (`list_url` / `detail_url` if missing) |
| **Done when** | List (active default) shows balances + low/overspent badges; create/edit material (pick material without existing budget + optional workflow checkboxes) and manual (funding method/value + product checkboxes); ink help on create; detail ledger (recent 50, order/PO links); manual adjustment with required notes; R&D write-off on budget detail **and** material edit with required notes |
| **Open items first** | O1 + R&D (B) locked — see §4 U2-3 table |

---

### Sprint U2-4 — Order Board read UI

| | |
|--|--|
| **Feature** | Order Board — Kanban without drag-and-drop |
| **Create** | `admin/views/orders-board.php`; `admin/assets/js/orders-board.js` (filters, pins, column ←/→ reorder, AJAX pin/order save); board styles in `admin/assets/css/admin.css` or dedicated CSS enqueued with the board |
| **Modify** | `admin/class-som-admin-menu.php` (Orders Board submenu immediately after Orders; asset enqueue; AJAX handlers for pins + column order); extend order query helpers as needed for board filters / personalisation search / progress + batch fields |
| **Done when** | Columns = distinct current step names among incomplete non-cancelled orders **plus Unassigned** when needed; column order = saved per-user order merged with auto lowest-`step_order` (←/→ buttons); cards show order ref/external ID (linked), channel badge, buyer, product (linked), personalisation preview, step name, time in step, progress status badges, batch indicator linking to Batches when applicable, View link; filters: channel, product, workflow template (two dropdowns), free-text (incl. personalisation), pinned-only; pins via AJAX user meta; oldest-first within columns; volume warn then hard cap; horizontal scroll; link to Orders list for history |
| **Open items first** | O5, O6, O7 locked; see §4 U2-4 table |

---

### Sprint U2-5 — Order Board gated drag-and-drop

| | |
|--|--|
| **Feature** | Order Board — SortableJS + existing advance-step |
| **Modify** | `admin/assets/js/orders-board.js` (+ enqueue SortableJS **1.15.6** CDN); `admin/views/orders-board.php` (card DnD attrs; prefill empty next-step columns; Complete drop zone); `admin/class-som-admin-menu.php` (enqueue Sortable + localize `restUrl`/`restNonce` on `somBoard`); `includes/class-som-orders.php` (next-step / can-advance helpers for board cards + empty next columns); `includes/class-som-rest-api.php` (`advance-step` adds `progress_status` + batch when applicable); light CSS in `admin/assets/css/admin.css` |
| **Done when** | Shared Sortable group across columns (within-column reorder off); only next-step column (or Complete zone for final step) is a valid drop; empty next-step columns prefilled from draggable cards; drop POSTs `advance-step` `{}`; snap-back on invalid/API error; non-advanceable cards not draggable; success places/removes via response `current_step_name` / `is_complete` and updates badges from extended `progress_status` |
| **Open items first** | None — see §4 U2-5 table |

---

## 7. Implementation notes (for later build — not this planning task)

- **Additive only** — do not rework stock, WAC, or workflow engine beyond the two inline budget hook calls, the Board UI caller of advance-step, and the small additive `advance-step` response fields (`progress_status` / batch) for U2-5.
- **Idempotency:** locked — skip `fund_on_create` if any `sale_funding` row exists for that `order_id` (see §4 U2-2 table).
- **Primary product / workflow for scope:** same rule as existing assignment — first `order_items` row with non-null `product_id` → product’s workflow template.
- **Draw-down amount:** landed cost for the received delta (`delta × landed_unit_cost`), material budgets only, opt-in (no budget for that material → no-op). Workflow scope applies to **funding**; draw-down is by `material_id` on the PO line (any linked material budget for that material).
- **Plain PHP admin** — no React/build step; SortableJS via CDN as specified.

---

## 8. Explicit scope of this document

This file is the **planning deliverable** for Update Package 2. Plugin code, migrations, and UI implementation are **out of scope** until a separate implementation task is started from these sprints.
