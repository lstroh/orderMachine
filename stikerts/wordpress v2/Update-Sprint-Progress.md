# Order Machine — Update Sprint Progress

*Companion to [`Update-Sprint-Plan.md`](Update-Sprint-Plan.md). Plan stays the source of scope; this file records what shipped and how it was verified.*

Assumption: base plugin Phases 1–11 complete (`SOM_VERSION` was `0.11.0`, `SOM_DB::DB_VERSION` was `1.3.0`). See also [`../wordpress/Sprint-Progress.md`](../wordpress/Sprint-Progress.md).

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| U1 | Shared schema upgrade | **Done** | Verified on wp-env 2026-07-31 |
| U2 | Suppliers + purchase orders | Pending | `SOM_Suppliers` CRUD shipped in U1; PO domain + admin UI remain |
| U3 | Landed cost / WA / goals | Pending | |
| U4 | Purchasing admin UI | Pending | |
| U5 | Batch engine | Pending | |
| U6 | Batches UI | Pending | Thank-you convert already done in U1 |
| U7 | REST + Abilities + smoke | Pending | |

---

## Sprint U1 — Shared schema upgrade

- **Status:** **Done** (confirmed complete vs `Update-Sprint-Plan.md` § Sprint U1)
- **Completed:** 2026-07-31
- **Verified on:** wp-env (dev site `http://localhost:8888`)
- **Plugin version:** `0.12.0`
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

- **U2:** PO domain (`class-som-purchase-orders.php`) + suppliers/PO admin UI (`SOM_Suppliers` already present).
- **U3:** Consumption must maintain `total_value_on_hand` / log value fields (qty-only path still current).
- **U5:** Engine must honour `batch_group_id` / `waiting_batch` (schema only in U1).
- **U6:** Batches UI + step editor; convert already idempotent from U1.
- In-flight orders stuck on thank-you as `waiting_script` are not rewritten to `waiting_batch` here — engine work in U5.

---

## Next

Execute **Sprint U2** (purchase orders domain + suppliers/PO admin UI, receive with stock qty; full costing in U3).
