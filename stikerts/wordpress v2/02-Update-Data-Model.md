# Update — Data Model Changes

*Update set 2 of 4 · Self-contained: every schema change needed for both features, in one place. Table specs, not raw SQL — translate to `dbDelta()`-compatible `CREATE TABLE`/migration code as per the existing plugin's schema-versioning approach (`wp_options` → `som_db_version`).*

---

## Part A — New tables for Raw Material Purchasing

### `wp_som_suppliers`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `name` | VARCHAR(100) | |
| `website` | VARCHAR(255) NULL | |
| `contact_info` | TEXT NULL | email/phone, free text |
| `notes` | TEXT NULL | |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_purchase_orders`
One row per order/shipment placed with a supplier.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `supplier_id` | INT FK → suppliers.id | |
| `order_date` | DATE | |
| `received_date` | DATE NULL | null until marked received |
| `status` | ENUM | `ordered`, `received`, `partially_received`, `cancelled` |
| `shipping_cost` | DECIMAL(10,2) DEFAULT 0 | total shipping for the whole order, allocated across items by value |
| `other_cost` | DECIMAL(10,2) DEFAULT 0 NULL | optional — tax, handling, allocated the same way as shipping |
| `notes` | TEXT NULL | |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_purchase_order_items`
Line items within a purchase order.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `purchase_order_id` | INT FK → purchase_orders.id | |
| `material_id` | INT FK → materials.id | |
| `quantity_ordered` | DECIMAL(10,2) | |
| `quantity_received` | DECIMAL(10,2) NULL | may differ from ordered; null until received |
| `item_cost` | DECIMAL(10,2) | line cost before shipping allocation |
| `allocated_shipping_cost` | DECIMAL(10,4) | calculated — this line's value-proportional share of the PO's `shipping_cost` |
| `landed_unit_cost` | DECIMAL(10,4) | calculated, `(item_cost + allocated_shipping_cost) / quantity_received` — null until received |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_workflow_material_goals`
Per-(workflow, material) cost ceiling.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `workflow_template_id` | INT FK → workflow_templates.id | |
| `material_id` | INT FK → materials.id | |
| `goal_unit_cost` | DECIMAL(10,4) | the ceiling you're aiming to stay under |
| `warning_threshold_percent` | DECIMAL(5,2) DEFAULT 90 | "approaching goal" fires once weighted-average cost reaches this % of `goal_unit_cost`; "over goal" fires at 100%+ |
| `created_at` / `updated_at` | DATETIME | |

**Constraint:** `UNIQUE (workflow_template_id, material_id)` — one goal per material per workflow. Not every material needs a row here — opt-in, no row = no alert for that pairing.

---

## Part B — New tables for Batch Processing

### `wp_som_batch_groups`
Defines a batchable action type (e.g. thank-you card printing). Workflows opt into a group by key, which is what makes cross-workflow pooling work.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `key` | VARCHAR(50) UNIQUE | e.g. `thank_you_card`, `shipping_label` |
| `display_name` | VARCHAR(100) | |
| `batch_size` | INT DEFAULT 4 | target count before auto-release |
| `action_type` | ENUM | `script` (plugin auto-runs something on release) or `manual_confirm` (batch just becomes visible for you to action externally) |
| `script_config` | TEXT NULL | same JSON shape as `workflow_steps.script_config`, but operates on a list of orders — see `04-Update-Batch-Processing.md` §3 |
| `created_at` / `updated_at` | DATETIME | |

### `wp_som_step_batches`
One row per in-progress batch instance.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `batch_group_id` | INT FK → batch_groups.id | |
| `status` | ENUM | `collecting`, `ready`, `processing`, `done`, `error` |
| `released_manually` | TINYINT(1) DEFAULT 0 | |
| `released_at` | DATETIME NULL | |
| `completed_at` | DATETIME NULL | |
| `last_error` | TEXT NULL | |
| `created_at` / `updated_at` | DATETIME | |

Only one `collecting` batch should exist per `batch_group_id` at a time.

### `wp_som_step_batch_items`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `batch_id` | INT FK → step_batches.id | |
| `order_id` | INT FK → orders.id | |
| `workflow_step_id` | INT FK → workflow_steps.id | the specific step this order is sitting at — needed since pooled orders can come from different workflow templates |
| `added_at` | DATETIME | |

---

## Part C — Changes to existing tables

### `materials` — add columns
| New column | Type | Notes |
|---|---|---|
| `total_value_on_hand` | DECIMAL(12,4) DEFAULT 0 | running cost total of current stock; weighted-average cost = `total_value_on_hand / current_stock` (guard divide-by-zero), never stored redundantly |
| `preferred_supplier_id` | INT FK → suppliers.id NULL | for quick reorder, not a hard constraint |

**Existing `unit_cost` column:** superseded by `total_value_on_hand`-derived weighted average — leave in place for backward compatibility/reference, but new code should read the derived weighted-average value instead.

### `material_stock_log` — add columns
| New column | Type | Notes |
|---|---|---|
| `purchase_order_item_id` | INT FK → purchase_order_items.id NULL | set when `reason = 'purchase_received'` |
| `unit_cost_at_time` | DECIMAL(10,4) NULL | weighted-average cost in effect at the moment of this row — recorded on consumption rows too, not just purchases, so historical COGS is reconstructable |
| `value_change` | DECIMAL(12,4) NULL | `change_qty × unit_cost_at_time`; sum reconciles against `materials.total_value_on_hand` |

**Existing `reason` column:** extend allowed values to include `purchase_received` (alongside the existing `new_order`, `manual_adjustment`, `order_cancelled`, `restock`).

### `products` — add column
| New column | Type | Notes |
|---|---|---|
| `target_selling_price` | DECIMAL(10,2) NULL | set explicitly by you, informed by competitor research — the primary pricing input; resulting profit/margin is calculated and displayed, not derived the other way around |

### `workflow_steps` — add column
| New column | Type | Notes |
|---|---|---|
| `batch_group_id` | INT FK → batch_groups.id NULL | if set, orders reaching this step join a shared batch instead of proceeding through the normal per-order gates alone. Not mutually exclusive with `requires_manual_confirm`/`timer_seconds` — a step can gate an order individually *before* it joins the batch, though the common case is batching being the step's only gate. |

### `order_step_progress` — extend `status` enum
Add `waiting_batch` to the existing set (`pending`, `in_progress`, `waiting_timer`, `waiting_script`, `error`, `done`).

---

## Migration notes

- All new tables via `dbDelta()`, consistent with existing plugin convention.
- New columns on existing tables: `ALTER TABLE ... ADD COLUMN`, nullable/defaulted so existing rows remain valid without backfill.
- Bump `som_db_version` in `wp_options` and add the corresponding migration step to whatever incremental-migration mechanism the existing plugin uses.
- No data migration needed for existing `materials`/`products`/`orders` rows — new columns default to null/0 and simply won't have weighted-average or target-price data until you start using the new features.
