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
| U5 | Batch engine | Pending | |
| U6 | Batches UI | Pending | Thank-you convert already done in U1 |
| U7 | REST + Abilities + smoke | Pending | |

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

## Next

Execute **Sprint U5** (batch gate in workflow engine).
