# Order Machine — Update Sprint Progress

*Companion to [`Update-Sprint-Plan.md`](Update-Sprint-Plan.md). Plan stays the source of scope; this file records what shipped and how it was verified.*

Assumption: base plugin Phases 1–11 complete (`SOM_VERSION` was `0.11.0`, `SOM_DB::DB_VERSION` was `1.3.0`). See also [`../wordpress/Sprint-Progress.md`](../wordpress/Sprint-Progress.md).

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| U1 | Shared schema upgrade | **Done** | Verified on wp-env 2026-07-31 |
| U2 | Suppliers + purchase orders | **Done** | Verified on wp-env 2026-07-31 |
| U3 | Landed cost / WA / goals | **Done** | Verified on wp-env 2026-07-31 |
| U4 | Purchasing admin UI | **Done** | Verified on wp-env 2026-08-01 |
| U5 | Batch engine | **Done** | Verified on wp-env 2026-08-01 |
| U6 | Batches UI | **Done** | Verified on wp-env 2026-08-01 |
| U7 | REST + Abilities + smoke | **Done** | Verified on wp-env 2026-08-01 |

---

## Sprint U1 — Shared schema upgrade

- **Status:** **Done** (confirmed complete vs `Update-Sprint-Plan.md` § Sprint U1)
- **Completed:** 2026-07-31
- **Verified on:** wp-env (dev site `http://localhost:8888`)
- **Plugin version:** `0.12.0` (later bumped to `0.13.0` in U2)
- **DB version:** `1.4.0`

### Plan requirements review (`Update-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Both features’ DDL in `class-som-db.php` | **Done** | 7 new tables + column alters |
| Migration pattern: dbDelta + `DB_VERSION` bump + ENUM ALTER | **Done** | `1.3.0` → `1.4.0`; explicit `waiting_batch` ALTER |
| Seed / ensure `batch_groups` (`thank_you_card`, `shipping_label`, size 4) | **Done** | `SOM_Batch_Groups::ensure_rows()` on activate + init |
| `allocated_other_cost` on PO items | **Done** | In CREATE string |
| Alter materials / material_stock_log / products / workflow_steps | **Done** | See schema list below |
| ENUM + `waiting_batch` on `order_step_progress` | **Done** | dbDelta CREATE + `upgrade_progress_status_enum()` |
| Thank-you step auto-convert | **Done** | Pulled into U1 from U6; `convert_thankyou_steps()` |
| Material `total_value_on_hand` backfill | **Done** | `current_stock × unit_cost` where value still 0 |
| Suppliers domain class | **Done** | `SOM_Suppliers` CRUD; admin UI deferred to U2 |
| `SOM_VERSION` → `0.12.0` | **Done** | |
| **Done when:** Fresh/upgraded installs create all tables/columns | **Pass** | Smoke + clean activate |
| **Done when:** `som_db_version` / plugin version bump | **Pass** | `1.4.0` / `0.12.0` |
| **Done when:** Two batch groups exist | **Pass** | script + manual_confirm, both size 4 |
| **Done when:** Thank-you steps converted | **Pass** | No leftover `run_thankyou_card_script` on steps |
| **Done when:** Material values backfilled | **Pass** | Smoke probe + upgrade path |
| **Done when:** Existing rows remain valid | **Pass** | Nullable/defaulted columns; no destructive wipe |
| Open items first | None | All settled before build |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Existing WIP | Kept and finished `class-som-db.php` / `class-som-batch-groups.php` / `class-som-suppliers.php` |
| Thank-you convert | Pulled into **U1** (was planned for U6) |
| `total_value_on_hand` | Backfill `current_stock × unit_cost` where value still `0` |
| Versions | Bump both `SOM_VERSION` → `0.12.0` and `DB_VERSION` → `1.4.0` |
| Suppliers class | Include CRUD domain class in U1; admin UI still U2 |
| `batch_groups` key column | DB column `group_key` (spec name `key`); PHP exposes `->key` via normalize — required for dbDelta UNIQUE |
| Progress file | This file |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | 7 new tables; column alters; ENUM ALTER; `group_key` migrate; value backfill; `DB_VERSION` `1.4.0` |
| `includes/class-som-batch-groups.php` | Ensure `thank_you_card` / `shipping_label`; thank-you step convert |
| `includes/class-som-suppliers.php` | Supplier CRUD (no admin UI yet) |
| `includes/seed/class-som-seed.php` | New materials set `total_value_on_hand` |
| `orderMachine.php` | Require new classes; ensure after seed, then convert; `0.12.0` |
| `tests/sprint-u1-smoke.php` | Schema / groups / convert / backfill / suppliers smoke |
| `stikerts/wordpress v2/Update-Sprint-Plan.md` | U1/U6 notes updated for convert timing + `group_key` |
| `stikerts/wordpress v2/Update-Sprint-Progress.md` | This progress record |

### Schema created / altered

New tables:

- `wp_som_suppliers`
- `wp_som_purchase_orders`
- `wp_som_purchase_order_items` (incl. `allocated_other_cost`)
- `wp_som_workflow_material_goals`
- `wp_som_batch_groups` — column `group_key` (spec name `key`)
- `wp_som_step_batches`
- `wp_som_step_batch_items`

Altered:

- `materials` — `total_value_on_hand`, `preferred_supplier_id`
- `material_stock_log` — `purchase_order_item_id`, `unit_cost_at_time`, `value_change`
- `products` — `target_selling_price`
- `workflow_steps` — `batch_group_id`
- `order_step_progress.status` — adds `waiting_batch` (explicit ALTER)

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| Fresh/upgraded installs create all tables/columns | **Pass** |
| `som_db_version` bumps to `1.4.0` | **Pass** |
| Plugin version `0.12.0` | **Pass** |
| Two batch groups exist (size 4) | **Pass** — `thank_you_card` (script), `shipping_label` (manual_confirm) |
| Thank-you steps converted; no leftover per-order thank-you `script_config` | **Pass** |
| Material `total_value_on_hand` backfill | **Pass** |
| Suppliers class create/get | **Pass** |
| Existing rows remain valid | **Pass** |

**Plan scope:** All Sprint U1 file and done-when items are complete (including agreed expansions: thank-you convert, value backfill, suppliers class).

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin deactivate orderMachine
npx @wordpress/env run cli wp plugin activate orderMachine
# Success: Activated 1 of 1 plugins. (no DB error output)
npx @wordpress/env run cli wp plugin list --name=orderMachine
# orderMachine active 0.12.0
npx @wordpress/env run cli wp option get som_db_version
# 1.4.0
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u1-smoke.php
# PASS — Sprint U1 smoke
```

Re-confirmed 2026-07-31 after progress review: plugin `0.12.0`, DB `1.4.0`, full smoke **PASS**.

### Spec deltas applied in code

- `batch_groups.key` → DB column `group_key` (reserved-word / dbDelta UNIQUE issue). PHP still exposes `->key` via normalize.
- Thank-you step auto-convert runs in U1 (not deferred to U6).
- Material `total_value_on_hand` backfilled from `current_stock × unit_cost` where value was still 0.

### Open items / notes for later

- **U3:** Consumption must maintain `total_value_on_hand` / log value fields (qty-only path still current); landed cost + WA on receive.
- **U5:** Engine must honour `batch_group_id` / `waiting_batch` (schema only in U1).
- **U6:** Batches UI + step editor; convert already idempotent from U1.
- In-flight orders stuck on thank-you as `waiting_script` are not rewritten to `waiting_batch` here — engine work in U5.

---

## Sprint U2 — Suppliers + purchase orders

- **Status:** **Done** (confirmed complete vs `Update-Sprint-Plan.md` § Sprint U2)
- **Completed:** 2026-07-31
- **Verified on:** wp-env (dev site `http://localhost:8888`)
- **Plugin version:** `0.13.0`
- **DB version:** `1.4.0` (unchanged; no new DDL in U2)

### Plan requirements review (`Update-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Purchasing CRUD + receive status machine | **Done** | Partial receive, later receives, mark-received, cancel |
| Create `includes/class-som-purchase-orders.php` | **Done** | Domain class; suppliers class already from U1 |
| Modify `orderMachine.php` requires | **Done** | Requires PO class; `SOM_VERSION` → `0.13.0` |
| Modify `admin/class-som-admin-menu.php` | **Done** | Suppliers + Purchase Orders menus, render, POST handlers |
| Views: suppliers list/edit | **Done** | `suppliers-list.php`, `supplier-edit.php` |
| Views: PO list/edit/receive | **Done** | `purchase-orders-list.php`, `purchase-order-edit.php`, `purchase-order-receive.php` |
| Material edit `preferred_supplier_id` | **Done** | Agreed U2 scope add (was optional vs original U4) |
| `SOM_Materials::adjust_stock` + `purchase_order_item_id` | **Done** | Also `purchase_received` reason label |
| **Done when:** CRUD suppliers (no delete) | **Pass** | Create/update/list/get; no delete method |
| **Done when:** Create PO `ordered` | **Pass** | |
| **Done when:** Receive delta; short → `partially_received` | **Pass** | |
| **Done when:** All lines met → `received` | **Pass** | Incl. over-receive |
| **Done when:** Later receives | **Pass** | |
| **Done when:** Mark-received / cancel close | **Pass** | No stock reverse on cancel |
| **Done when:** Stock + `purchase_received` log; cost stubbed | **Pass** | `allocated_*` / `landed_unit_cost` / `value_change` left null for U3 |
| **Done when:** Preferred supplier on materials | **Pass** | |
| Open items first | Settled | P1 + U2 clarifying answers in plan Settled decisions |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Manual close | **Mark received** (accept shortfall) **or Cancel** |
| Later receives | Allowed; form enters **additional qty this shipment** |
| Fully `received` | Every line `quantity_received >= quantity_ordered` |
| `received_date` | Overwrite on every successful receive |
| Edit lock | Full edit while `ordered` with no receipts; lock lines/costs after first receive (notes still editable) |
| Cancel | No reverse of already-received stock |
| Edges | Over-receive, 0 delta (skip line), duplicate materials on one PO OK |
| `item_cost` | Total line cost (GBP) |
| Menu | **Suppliers** + **Purchase Orders** submenus under Order Machine |
| Preferred supplier | On material create/edit in **U2** |
| Supplier delete | None |
| Costing | Persist `item_cost` / shipping / other; leave WA/allocation/value for **U3** |
| Versions | Bump `SOM_VERSION` → `0.13.0` only (DB stays `1.4.0`) |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-purchase-orders.php` | PO CRUD + receive status machine |
| `includes/class-som-materials.php` | Preferred supplier; `adjust_stock` writes `purchase_order_item_id`; `purchase_received` label |
| `includes/class-som-suppliers.php` | `detail_url` accepts `new` |
| `admin/class-som-admin-menu.php` | Menus, renderers, save/receive/close handlers |
| `admin/views/suppliers-list.php` | Suppliers list + search |
| `admin/views/supplier-edit.php` | Supplier create/edit |
| `admin/views/purchase-orders-list.php` | PO list + status/supplier filters |
| `admin/views/purchase-order-edit.php` | PO create/edit + close actions |
| `admin/views/purchase-order-receive.php` | Delta receive form |
| `admin/views/material-edit.php` | Preferred supplier field |
| `admin/assets/js/admin.js` | PO line add/remove |
| `admin/assets/css/admin.css` | PO status badges / action layout |
| `orderMachine.php` | Require PO class; notices for new pages; `0.13.0` |
| `tests/sprint-u2-smoke.php` | Domain smoke (create → partial → later/over → close paths) |
| `tests/sprint-u1-smoke.php` | Version assert relaxed to `>= 0.12.0` |
| `stikerts/wordpress v2/Update-Sprint-Plan.md` | U2 settled decisions + scope notes |
| `stikerts/wordpress v2/Update-Sprint-Progress.md` | This section |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| CRUD suppliers (no delete) | **Pass** |
| Create PO `ordered` | **Pass** |
| Receive lines (delta); short → `partially_received` | **Pass** |
| All lines met → `received`; later receives | **Pass** |
| Mark-received / cancel close | **Pass** |
| Stock qty rises with `purchase_received` log rows | **Pass** — includes `purchase_order_item_id` |
| Cost fields stubbed until U3 | **Pass** — `value_change` / allocations / landed still null |
| Preferred supplier on materials | **Pass** |

**Plan scope:** All Sprint U2 file and done-when items are complete (including agreed expansions: preferred supplier on material edit; settled receive/close rules).

### Explicitly out of U2 (deferred)

- Landed-cost allocation, weighted average, `total_value_on_hand` on receive → **U3**
- Preview Impact, goals/alerts UI, Product Costing surfaces → **U3/U4**
- REST / Abilities for suppliers & POs → **U7**

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin list --name=orderMachine
# orderMachine active 0.13.0
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u2-smoke.php
# PASS — Sprint U2 smoke
```

Re-confirmed 2026-07-31 after plan review: plugin `0.13.0`, full U2 smoke **PASS**.

---

## Sprint U3 — Landed cost, weighted average, goals, preview

- **Status:** **Done** (confirmed complete vs `Update-Sprint-Plan.md` § Sprint U3)
- **Completed:** 2026-07-31
- **Verified on:** wp-env (dev site `http://localhost:8888`)
- **Plugin version:** `0.14.0`
- **DB version:** `1.4.0` (unchanged; no new DDL in U3)

### Plan requirements review (`Update-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Costing math + consumption value + goals data + preview service | **Done** | Domain only; UI deferred to U4 |
| Create `includes/class-som-material-costing.php` | **Done** | Allocate, WA project, preview, goal alerts |
| Create `includes/class-som-workflow-material-goals.php` | **Done** | Upsert/update/delete/list + `alert_level` |
| Create `tests/sprint-u3-smoke.php` | **Done** | |
| Modify `adjust_stock` for value fields | **Done** | `unit_cost_at_time`, `value_change`, `total_value_on_hand`; optional sync WA → `unit_cost` |
| Manual `unit_cost` override revalues | **Done** | `revalue_from_unit_cost` + log row |
| `class-som-material-stock.php` consumption path | **Done** | No file edit required — already calls `adjust_stock`, which now writes value fields |
| PO receive calls costing service | **Done** | `write_allocations_for_order` + landed on stock adjust |
| Product helpers for recipe cost / margin | **Done** | `SOM_Products::recipe_costing` + `target_selling_price` on update |
| Costing rules (settled U3 answers) | **Done** | See decisions table below |
| **Done when:** Receive runs 03 §2 worked examples | **Pass** | Vinyl landed £0.6923; WA £0.6577 / stock 80 / value £52.615 |
| **Done when:** Preview matches receive without DB writes | **Pass** | |
| **Done when:** Consumption keeps `total_value_on_hand` consistent | **Pass** | |
| **Done when:** Goals approaching/over | **Pass** | |
| **Done when:** Correcting adjustment path (no edit-received-PO rewrite) | **Pass** | Manual `unit_cost` revalue |
| **Done when:** U3 smoke PASS | **Pass** | |
| Open items first | Settled | P2, X2, X6 + U3 clarifying answers in plan |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Partial receive costing | Full PO shipping/other by `item_cost`; stable landed = `(item_cost + allocated_*) / quantity_ordered`; each shipment WA uses that unit |
| Stored `landed_unit_cost` | Same stable unit (not cumulative `/ quantity_received`) |
| `unit_cost_at_time` | Purchase = inbound landed; consumption/manual = current WA |
| Sync `unit_cost` on receive | Yes — write new WA into `materials.unit_cost` |
| Zero total line cost | No allocation + warning from allocate/preview |
| Zero-stock consumption | Fall back to `unit_cost`, else `0` |
| U3 vs U4 | Domain only in U3 (Preview button, goals UI, Product Costing, badges → U4) |
| Versions | `SOM_VERSION` → `0.14.0` (DB stays `1.4.0`) |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-material-costing.php` | Landed allocation, WA, preview, goal alerts, product impacts |
| `includes/class-som-workflow-material-goals.php` | Goals CRUD + `alert_level` |
| `includes/class-som-materials.php` | Value-aware `adjust_stock`; `revalue_from_unit_cost` |
| `includes/class-som-purchase-orders.php` | Receive writes allocations + costing stock adjust |
| `includes/class-som-products.php` | `recipe_costing`; `target_selling_price` on update |
| `includes/class-som-material-stock.php` | Unchanged — inherits value path via `adjust_stock` |
| `orderMachine.php` | Require new classes; `0.14.0` |
| `tests/sprint-u3-smoke.php` | Worked examples + consumption + goals + preview + revalue |
| `tests/sprint-u2-smoke.php` | Version assert relaxed; expect `value_change` populated |
| `stikerts/wordpress v2/Update-Sprint-Plan.md` | U3 settled decisions recorded before/during build |
| `stikerts/wordpress v2/Update-Sprint-Progress.md` | This section |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| Receive runs worked examples from 03 §2 | **Pass** |
| Preview matches receive without DB writes | **Pass** |
| Consumption keeps `total_value_on_hand` consistent | **Pass** |
| Goals fire approaching/over | **Pass** |
| Correcting adjustment path exists | **Pass** |
| U3 smoke PASS | **Pass** |

**Plan scope:** All Sprint U3 create/modify/done-when items are complete (including agreed U3 costing rules). No U3 open items remain.

### Explicitly out of U3 (deferred to U4)

- Preview Impact button on PO screens
- Workflow material goals UI
- Product Costing page / alert badges on Materials
- Dashboard widget (still out of update entirely per P4)

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin list --name=orderMachine
# orderMachine active 0.14.0
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u3-smoke.php
# PASS — Sprint U3 smoke
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u2-smoke.php
# PASS — Sprint U2 smoke (regression)
```

Re-confirmed 2026-07-31 after plan review: plugin `0.14.0`, full U3 smoke **PASS**.

---

## Sprint U4 — Purchasing admin UI (costing surfaces)

- **Status:** **Done** (confirmed complete vs `Update-Sprint-Plan.md` § Sprint U4 + 03 §6)
- **Completed:** 2026-08-01
- **Verified on:** wp-env (dev site `http://localhost:8888`)
- **Plugin version:** `0.15.0`
- **DB version:** `1.4.0` (unchanged; no new DDL in U4)

### Plan requirements review (`Update-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Materials enhanced (WA, value, lead time, PO history, badges) | **Done** | List: WA + value + badges; edit: summary, lead time, breakdown, dedicated PO history |
| Workflow material goals UI | **Done** | Template-level section on `workflow-step-editor.php` + `sync_for_workflow` on save |
| Product Costing list + edit | **Done** | List columns (target / material cost / margin / badges); edit Costing panel |
| Preview Impact button | **Done** | `wp_ajax_som_preview_po_impact` from PO create/edit form (unsaved OK) |
| Post-receive alert notice | **Done** | Flash warning after receive in `handle_purchase_orders_actions`; redirects to receive URL while still receivable (not a separate receive-view edit) |
| Unit cost override UX | **Done** | Read-only WA + value on hand; editable `unit_cost` override/revalue copy |
| Domain helpers (lead time, PO history) | **Done** | `SOM_Materials::average_lead_time_days`, `get_purchase_history` |
| `SOM_VERSION` → `0.15.0` | **Done** | |
| `tests/sprint-u4-smoke.php` | **Done** | Goals sync, preview, lead time, PO history, list badges, product alerts |
| **Done when:** All 03 §6 UI rows (ex dashboard widget) | **Pass** | See §6 checklist below |
| **Done when:** Preview shows WA + goal + product margin | **Pass** | |
| **Done when:** Alerts on Materials list + Product Costing + receive | **Pass** | |
| **Done when:** U4 smoke PASS | **Pass** | |
| Open items first | Settled | P4 (no widget); U4 Q22–30 settled in plan |

### 03 §6 UI rows checklist

| Page / section | Status | Notes |
|---|---|---|
| Suppliers | **Done (U2)** | Unchanged in U4 |
| Purchase Orders (list) | **Done (U2)** | Unchanged in U4 |
| Purchase Order (create/edit) + Preview Impact | **Done** | Preview Impact + results panel added |
| Purchase Order (receive) | **Done** | Receive flow from U2/U3; U4 adds post-receive alert flash |
| Materials (enhanced) | **Done** | WA, value on hand, avg lead time, preferred supplier (U2), purchase history, goal badges |
| Workflow material goals | **Done** | On workflow template editor |
| Product Costing | **Done** | Target price, live cost, profit/margin, goal alerts, listing prices side by side |
| Dashboard cost-alerts widget | **Out of scope** | P4 deferred |

### Settled U4 clarifications applied

| Topic | Decision | Implemented |
|---|---|---|
| Product Costing home | Both list + edit | Yes |
| Goals UI | Template-level on workflow editor | Yes |
| Preview Impact | Create/edit only; admin-ajax | Yes |
| Lead time | Overall average | Yes |
| Purchase history | Dedicated PO-history table | Yes |
| Alert badges | List badges; edit breakdown | Yes |
| Unit cost UI | WA/value read-only; override control | Yes |
| Post-receive alerts | Receive-screen success notice | Yes (flash + stay on receive when open) |
| U4 smoke | Yes | Yes |
| P5 UI copy | Document workflow reassignment follows goals | Yes — product edit + goals section descriptions |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Product Costing | List columns **and** edit panel |
| Goals UI | Template-level on workflow editor |
| Preview | PO create/edit only; admin-ajax from form |
| Lead time | Overall average days (`DATEDIFF(received_date, order_date)`) |
| Purchase history | Dedicated table (date, PO link, supplier, qty, landed unit cost) |
| Alert badges | List only; full per-workflow breakdown on material edit |
| Unit cost UI | WA + value read-only; `unit_cost` = override/revalue |
| Receive alerts | Success + warning notice; redirect to receive if still open, else PO detail |
| `purchase-order-receive.php` | No view markup change — notice via admin flash after POST |
| Versions | `SOM_VERSION` → `0.15.0` (DB stays `1.4.0`) |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-materials.php` | Lead time, PO history, WA/alerts on get/query |
| `includes/class-som-material-costing.php` | `worst_alert_level`, `alert_label`; material name on alerts |
| `includes/class-som-workflow-material-goals.php` | `sync_for_workflow` |
| `includes/class-som-products.php` | `target_selling_price` on create |
| `admin/class-som-admin-menu.php` | Goals save, product target, receive alerts, preview ajax, script localize |
| `admin/views/materials-list.php` | WA, value, goal badges |
| `admin/views/material-edit.php` | Costing summary, alerts breakdown, PO history, override copy |
| `admin/views/products-list.php` | Target / material cost / margin / badges |
| `admin/views/product-edit.php` | Target field + Product Costing panel + P5 copy |
| `admin/views/workflow-step-editor.php` | Material cost goals section |
| `admin/views/purchase-order-edit.php` | Preview Impact button + results panel |
| `admin/assets/js/admin.js` | Goals rows + Preview Impact ajax |
| `admin/assets/css/admin.css` | Goal badge / preview / costing styles |
| `orderMachine.php` | `0.15.0` |
| `tests/sprint-u4-smoke.php` | Domain/UI-helper smoke |
| `stikerts/wordpress v2/Update-Sprint-Plan.md` | U4 settled decisions (before build) |
| `stikerts/wordpress v2/Update-Sprint-Progress.md` | This section |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| All UI rows from 03 §6 (except deferred dashboard widget) | **Pass** |
| Preview Impact shows WA + goal + product margin impact | **Pass** |
| Alerts on Materials list + Product Costing + receive notice | **Pass** |
| U4 smoke PASS | **Pass** |

**Plan scope:** All Sprint U4 modify/done-when items and settled U4 UI rules are complete. Preferred supplier was already on material edit from U2.

### Explicitly out of U4

- Dashboard cost-alerts widget (P4)
- Batch engine / batches UI → **U5/U6**
- REST / Abilities → **U7**

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin list --name=orderMachine
# orderMachine active 0.15.0
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u4-smoke.php
# PASS — Sprint U4 smoke
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u3-smoke.php
# PASS — Sprint U3 smoke (regression)
```

Re-confirmed 2026-08-01 after plan review: plugin `0.15.0`, full U4 smoke **PASS**, all plan/§6 done-when items covered.

---

## Sprint U5 — Batch gate in workflow engine

- **Status:** **Done** (confirmed complete vs `Update-Sprint-Plan.md` § Sprint U5 + settled U5 Q31–40)
- **Completed:** 2026-08-01
- **Verified on:** wp-env (dev site `http://localhost:8888`)
- **Plugin version:** `0.16.0`
- **DB version:** `1.5.0` (`retry_count` / `retry_after` on `step_batches`)

### Plan requirements review (`Update-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Batch Processing state machine (04 §2) | **Done** | `collecting` → `ready` → (`processing`) → `done` / `error` |
| Batch-only step rule | **Done** | `save_steps` rejects batch + manual/timer/script; `enter_step` takes batch first |
| Create `includes/class-som-batches.php` | **Done** | enqueue, release, mark_done, run script, domain retry, cron attempt |
| Create `tests/sprint-u5-smoke.php` | **Done** | |
| Modify `class-som-db.php` (`retry_*`, `DB_VERSION` → `1.5.0`) | **Done** | |
| Modify `enter_step` → `waiting_batch` + enqueue | **Done** | |
| Advance all members via each item’s `workflow_step_id` | **Done** | `SOM_Workflow_Engine::complete_batch_member` |
| On batch `error`, flip members to `error` | **Done** | |
| Batch thank-you path (multi-order JSON → existing CLI) | **Done** | `SOM_Local_Actions::run_for_orders` |
| Step save validation (batch-only) | **Done** | Also preserves `batch_group_id` when form omits it |
| Script retry/backoff at batch unit | **Done** | `retry_count` / `retry_after` + `som_batch_attempt` cron |
| `SOM_VERSION` → `0.16.0` | **Done** | |
| **Done when:** Orders pool cross-workflow; auto-ready at size 4 | **Pass** | Pooling by `batch_group_id` (not template) |
| **Done when:** Manual release | **Pass** | |
| **Done when:** Script group runs once for all members and advances all | **Pass** | Same-request on size/manual release |
| **Done when:** manual_confirm waits for mark-done | **Pass** | |
| **Done when:** Failure → whole batch + members `error`; domain retry | **Pass** | |
| **Done when:** U5 smoke PASS | **Pass** | |
| Open items first | Settled | B1–B3, X3 + U5 clarifying answers in plan |

### Settled U5 clarifications applied

| Topic | Decision | Implemented |
|---|---|---|
| In-flight thank-you | No migration | Yes |
| Retry storage | `retry_count` + `retry_after` on `step_batches`; DB `1.5.0` | Yes |
| Members on batch error | Flip to `error` (copy `last_error`) | Yes |
| Script auto-run | Same request (`ready` → `processing` → run) | Yes |
| Cancelled in batch | Leave in batch; skip advance on complete | Yes |
| Duplicate membership | No uniqueness | Yes |
| Domain retry | `SOM_Batches::retry` | Yes |
| Smoke / versions | `sprint-u5-smoke.php`; plugin `0.16.0`; DB `1.5.0` | Yes |
| Seed thank-you | Convert-on-activate (unchanged) | Yes |
| Shipping-label convert | Engine only; opt-in stays U6 | Yes |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Batch script types | `local` only for batch groups in v1 (api/n8n → failure message) |
| Empty group `script_config` | Treat as success (noop) then advance members |
| Cancelled members on complete | Stay in batch items; `complete_batch_member` no-ops advance |
| Preserve `batch_group_id` on step save | If form omits field, keep existing DB value (protects U1 thank-you convert until U6 editor) |
| Versions | `SOM_VERSION` → `0.16.0`; `DB_VERSION` → `1.5.0` |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | `step_batches.retry_count` / `retry_after`; `DB_VERSION` `1.5.0` |
| `includes/class-som-batches.php` | Batch domain state machine |
| `includes/class-som-workflow-engine.php` | Batch `enter_step`; `complete_batch_member`; tick due retries |
| `includes/class-som-local-actions.php` | Multi-order thank-you CLI (`run_for_orders`) |
| `includes/class-som-workflows.php` | Batch-only validation; preserve `batch_group_id`; gates label |
| `includes/class-som-cron.php` | Wire `som_batch_attempt` |
| `orderMachine.php` | Require `class-som-batches.php`; `0.16.0` |
| `tests/sprint-u5-smoke.php` | Manual/auto/script/retry/validation smoke |
| `tests/sprint-u1-smoke.php` | DB version assert relaxed to `>= 1.4.0` |
| `stikerts/wordpress v2/Update-Sprint-Plan.md` | U5 settled decisions (before build) |
| `stikerts/wordpress v2/Update-Sprint-Progress.md` | This section |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| Orders pool cross-workflow; auto-ready at size 4 | **Pass** |
| Manual release; script group runs once and advances all | **Pass** |
| manual_confirm waits for mark-done | **Pass** |
| Failure leaves whole batch + members in `error`; domain retry works | **Pass** |
| U5 smoke PASS | **Pass** |

**Plan scope:** All Sprint U5 create/modify/done-when items and settled U5 rules are complete. Batches admin UI / step-editor assignment remain **U6**.

### Explicitly out of U5

- Batches page / order-detail batch link / step editor `batch_group_id` UI → **U6**
- shipping_label opt-in on existing Ship steps → **U6**
- REST / Abilities for batches → **U7**

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin list --name=orderMachine
# orderMachine active 0.16.0
npx @wordpress/env run cli wp option get som_db_version
# 1.5.0
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u5-smoke.php
# PASS — Sprint U5 smoke
```

Re-confirmed 2026-08-01 after plan review: plugin `0.16.0`, DB `1.5.0`, full U5 smoke **PASS**, all plan done-when items covered.

---

## Sprint U6 — Batches admin UI + step editor

- **Status:** **Done** (confirmed complete vs `Update-Sprint-Plan.md` § Sprint U6 + settled U6 Q41–50)
- **Completed:** 2026-08-01
- **Verified on:** wp-env (dev site `http://localhost:8888`)
- **Plugin version:** `0.17.0`
- **DB version:** `1.5.0` (unchanged; no new DDL in U6)

### Plan requirements review (`Update-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Create `admin/views/batches.php` (single expandable list) | **Done** | No separate detail page; `?batch_id=N` deep-link |
| Create `tests/sprint-u6-smoke.php` | **Done** | |
| Modify `admin/class-som-admin-menu.php` | **Done** | Batches submenu + release/mark-done/retry/groups save |
| Modify `admin/views/order-detail.php` | **Done** | `waiting_batch` badge + count + link; Mark done hidden |
| Modify `admin/views/workflow-step-editor.php` | **Done** | Batch group dropdown + combo warning |
| Batch-groups edit UI (name / `batch_size`) | **Done** | On Batches page; key + action_type fixed |
| Modify `includes/class-som-batches.php` | **Done** | `query`, `find_for_order`, `get_items_with_orders`, URLs, labels |
| Modify `includes/class-som-batch-groups.php` | **Done** | `update()` |
| Modify `orderMachine.php` (`0.17.0`) | **Done** | Notices include `som-batches` |
| Admin CSS/JS for expand + deep-link | **Done** | Also address expand + step combo warning |
| UI: open only by default | **Done** | Optional Include done + status/group filters |
| UI: Release / Mark done / Retry | **Done** | collecting / ready+manual_confirm / error+script |
| UI: member rows expand for address | **Done** | Name + order ref collapsed |
| shipping_label via editor only | **Done** | No bulk convert |
| Thank-you convert (plan note) | **N/A (U1)** | Still idempotent on activate; not reworked in U6 |
| **Done when:** Batches UI matches 04 §4 + settled U6 rules | **Pass** | |
| **Done when:** Order detail links to batch | **Pass** | |
| **Done when:** Step editor can assign `batch_group_id` | **Pass** | shipping_label opt-in |
| **Done when:** U6 smoke PASS | **Pass** | |
| Open items first | Settled | Q11 convert in U1; U6 Q41–50 |

### Settled U6 clarifications applied

| Topic | Decision | Implemented |
|---|---|---|
| Page shape | Single expandable list; `?batch_id=N` | Yes |
| Default filter | Open only | Yes (+ optional Include done) |
| Retry UI | Yes on `error` script batches | Yes |
| Member address | Expand | Yes |
| Step editor combo | Warn only | Yes |
| shipping_label | Editor opt-in only | Yes |
| Edit groups | Name + size | Yes |
| Order link | `som-batches&batch_id=N` | Yes |
| Smoke / version | `0.17.0` + smoke | Yes |
| Hide Mark done while waiting_batch | Yes | Yes |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Groups editor home | Same Batches page (top section), not a separate submenu |
| Deep-link to done batch | If `batch_id` missing from open list, fetch and prepend so link still works |
| Script `processing` UI | Show `last_error` + retry_after when present (no separate action until `error`) |
| Versions | `SOM_VERSION` → `0.17.0` only (DB stays `1.5.0`) |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-batches.php` | `query`, `find_for_order`, `get_items_with_orders`, status labels, URLs |
| `includes/class-som-batch-groups.php` | `update` (display name / batch_size) |
| `admin/views/batches.php` | Groups editor + expandable batches list |
| `admin/class-som-admin-menu.php` | Batches menu, render, POST handlers |
| `admin/views/workflow-step-editor.php` | Batch group dropdown + warning |
| `admin/views/order-detail.php` | `waiting_batch` UI; hide Mark done |
| `admin/assets/js/admin.js` | Expand / address / deep-link; combo warning |
| `admin/assets/css/admin.css` | Batch badges + card styles |
| `orderMachine.php` | `0.17.0`; notices for `som-batches` |
| `tests/sprint-u6-smoke.php` | Domain/UI-helper smoke |
| `stikerts/wordpress v2/Update-Sprint-Plan.md` | U6 settled decisions |
| `stikerts/wordpress v2/Update-Sprint-Progress.md` | This section |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| Batches UI matches 04 §4 + settled U6 rules | **Pass** |
| Order detail links to batch; Mark done hidden while waiting_batch | **Pass** |
| Step editor can assign `batch_group_id` (shipping_label opt-in) | **Pass** |
| U6 smoke PASS | **Pass** |

**Plan scope:** All Sprint U6 create/modify/done-when items and settled U6 rules are complete. Thank-you convert remains U1 (idempotent). REST/Abilities remain **U7**.

### Explicitly out of U6

- REST / Abilities for batches → **U7**
- Thank-you step auto-convert (already U1)
- Dashboard widgets / purchasing UI (U1–U4)

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin list --name=orderMachine
# orderMachine active 0.17.0
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u6-smoke.php
# PASS — Sprint U6 smoke
```

Re-confirmed 2026-08-01 after plan review: plugin `0.17.0`, DB `1.5.0`, full U6 smoke **PASS**, all plan done-when items covered.

---

## Sprint U7 — REST + Abilities + smoke

- **Status:** **Done** (confirmed complete vs `Update-Sprint-Plan.md` § Sprint U7 + settled U7 Q51–55)
- **Completed:** 2026-08-01
- **Verified on:** wp-env (dev site `http://localhost:8888`)
- **Plugin version:** `0.18.0`
- **DB version:** `1.5.0` (unchanged; no new DDL in U7)

### Plan requirements review (`Update-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| `som/v1` suppliers CRUD (no delete) | **Done** | `GET` list/one, `POST` create, `PUT` update |
| PO CRUD + receive/preview/mark-received/cancel | **Done** | Preview = `POST /purchase-orders/preview` (unsaved body) |
| Workflow material goals individual CRUD | **Done** | List by workflow **or** material; `POST` upsert; `PUT`; `DELETE` — no bulk sync |
| Batches list/get/release/mark-done/retry | **Done** | Retry wired to `SOM_Batches::retry` |
| Batch groups read-only | **Done** | `GET` list/one only |
| Auth: API key or admin on all new routes | **Done** | `check_api_key_or_admin` |
| Read-only Abilities mirroring safe reads | **Done** | New abilities below; MCP toggle still gates registration |
| Enrich `get-materials` / `get-products` | **Done** | WA/value/supplier/alert; target/cost/profit/margin |
| Admin UI unchanged (form POST / ajax) | **Done** | No admin migrate to REST |
| Modify `class-som-rest-api.php` / `class-som-abilities.php` | **Done** | |
| `SOM_VERSION` → `0.18.0` | **Done** | |
| Create `tests/sprint-u7-smoke.php` via `rest_do_request` | **Done** | |
| **Done when:** Authenticated REST covers settled routes | **Pass** | |
| **Done when:** Abilities list/get only; no credentials | **Pass** | |
| **Done when:** Smoke PASS (schema + PO WA + batch advance) | **Pass** | |
| Open items first | Settled | X5 + U7 Q51–55 |

### REST routes shipped (`som/v1`)

| Route | Methods | Domain |
|---|---|---|
| `/suppliers` | GET, POST | `SOM_Suppliers::query` / `create` |
| `/suppliers/{id}` | GET, PUT | `get` / `update` (no DELETE) |
| `/purchase-orders` | GET, POST | `SOM_Purchase_Orders::query` / `create` |
| `/purchase-orders/preview` | POST | `SOM_Material_Costing::preview_impact` |
| `/purchase-orders/{id}` | GET, PUT | `get` / `update` |
| `/purchase-orders/{id}/receive` | POST | `receive` (body: `deltas` map or `items[]`) |
| `/purchase-orders/{id}/mark-received` | POST | `mark_received` |
| `/purchase-orders/{id}/cancel` | POST | `cancel` |
| `/workflow-material-goals` | GET, POST | list by `workflow_template_id` or `material_id`; `upsert` |
| `/workflow-material-goals/{id}` | GET, PUT, DELETE | `get` / `update` / `delete` |
| `/batches` | GET | `SOM_Batches::query` (open-only default; `include_done` / `status` filters) |
| `/batches/{id}` | GET | detail + members |
| `/batches/{id}/release` | POST | `release` |
| `/batches/{id}/mark-done` | POST | `mark_done` |
| `/batches/{id}/retry` | POST | `retry` |
| `/batch-groups` | GET | `SOM_Batch_Groups::list_all` |
| `/batch-groups/{id}` | GET | `get` |

Pre-existing routes unchanged: `POST /orders`, `POST /orders/{id}/advance-step`, `POST /workflow-callback/{token}`.

### Abilities shipped (read-only, MCP toggle)

**New:**

| Ability | Returns |
|---|---|
| `order-machine/get-suppliers` | Supplier list |
| `order-machine/get-purchase-orders` | PO list (headers) |
| `order-machine/get-purchase-order` | One PO + lines |
| `order-machine/get-workflow-material-goals` | Goals by workflow or material |
| `order-machine/get-batches` | Batch list |
| `order-machine/get-batch` | One batch + members |
| `order-machine/get-batch-groups` | Batch group catalogue |

**Enriched existing:**

| Ability | Added fields |
|---|---|
| `order-machine/get-materials` | `unit_cost`, `weighted_average`, `total_value_on_hand`, `preferred_supplier_id`, `goal_alert_level` |
| `order-machine/get-products` | `target_selling_price`, `material_cost`, `profit`, `margin_percent` |

Still excluded: channel credentials (unchanged hard rule).

### Settled U7 clarifications applied

| Topic | Decision | Implemented |
|---|---|---|
| Auth (Q51) | API key or admin | Yes — all new routes |
| REST surface (Q52) | Confirmed table + retry + batch groups read + goals individual CRUD | Yes |
| Enrich Abilities (Q53) | Materials costing + products target/cost/margin | Yes |
| Version / smoke (Q54) | `0.18.0` + `rest_do_request` smoke | Yes |
| Admin migrate (Q55) | Leave form POST / admin-ajax | Yes |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Preview path | Standalone `POST /purchase-orders/preview` (no PO id; matches unsaved admin-ajax) |
| Receive body | Accept `deltas` map **or** `items[]` with `id` + `quantity` |
| Goals list filter | Require `workflow_template_id` **or** `material_id` (400 if neither) |
| Batch detail members | Order id, external ref, buyer name, `is_complete` (no credential fields) |
| Versions | `SOM_VERSION` → `0.18.0` only (DB stays `1.5.0`) |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-rest-api.php` | Full U7 route set + serializers (existing order routes kept) |
| `includes/class-som-abilities.php` | New read abilities + materials/products costing enrich |
| `orderMachine.php` | Plugin header + `SOM_VERSION` `0.18.0` |
| `tests/sprint-u7-smoke.php` | Schema + REST (`rest_do_request`) + abilities smoke |
| `stikerts/wordpress v2/Update-Sprint-Plan.md` | U7 settled decisions (Q51–55) recorded before/during build |
| `stikerts/wordpress v2/Update-Sprint-Progress.md` | This section |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| Authenticated REST covers settled route set | **Pass** |
| Abilities list/get only (no credential leakage) | **Pass** |
| Smoke: schema presence | **Pass** — 7 update tables |
| Smoke: one PO receive WA path | **Pass** — create → preview → receive; vinyl stock/WA |
| Smoke: one batch collect → release → advance | **Pass** — shipping_label manual_confirm via REST release + mark-done |
| Plugin `0.18.0` | **Pass** |

**Plan scope:** All Sprint U7 modify/done-when items and settled U7 rules are complete. Purchasing + batching update package (**U1–U7**) is complete.

### Explicitly out of U7

- Admin UI migration to REST (still form POST / `som_preview_po_impact` ajax)
- Write Abilities
- Bulk `sync_for_workflow` REST endpoint (editor continues to use domain sync)
- Dashboard cost-alerts widget (P4 — still out of update)

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin list --name=orderMachine
# orderMachine active 0.18.0
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u7-smoke.php
# PASS — Sprint U7 smoke
```

Re-confirmed 2026-08-01 after plan review: plugin `0.18.0`, DB `1.5.0`, full U7 smoke **PASS**, all plan done-when items covered.

---

## Next

Update package **U1–U7 complete**. No further update sprints in this plan.
