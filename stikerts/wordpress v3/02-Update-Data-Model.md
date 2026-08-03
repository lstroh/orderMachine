# Update — Data Model Changes

*Update set 2 of 4 · Self-contained. Order Board (`04-Update-Order-Board.md`) requires no schema changes at all — everything below is for Budgets.*

---

## New tables for Budgets

### `wp_som_budgets`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `name` | VARCHAR(100) | e.g. "Vinyl Budget", "Equipment Replacement Fund" |
| `type` | ENUM | `material` or `manual` |
| `material_id` | INT FK → materials.id NULL | set only when `type = 'material'` (references the existing `materials` table from the base plugin) |
| `funding_method` | ENUM | `material_cost` (type=material only — automatic), `percent_of_price`, `percent_of_profit`, `fixed_amount` (type=manual only) |
| `funding_value` | DECIMAL(10,4) NULL | percentage (0–100) for the two `percent_of_*` methods, or a flat £ amount for `fixed_amount`; unused/null for `material_cost` since that's computed from actual consumption, not a fixed rate |
| `target_reserve_amount` | DECIMAL(10,2) NULL | optional — triggers a "low balance" flag if `current_balance` falls below it |
| `current_balance` | DECIMAL(12,4) DEFAULT 0 | running total — funded (positive) minus spent (negative) |
| `notes` | TEXT NULL | |
| `created_at` / `updated_at` | DATETIME | |

**Constraint:** `type = 'material'` requires `material_id` set and `funding_method = 'material_cost'`. `type = 'manual'` requires `material_id` null and `funding_method` one of the other three.

### `wp_som_budget_product_links`
Scopes a **manual** budget to specific products. No rows for a given budget = applies to all products.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `budget_id` | INT FK → budgets.id | |
| `product_id` | INT FK → products.id | references the existing `products` table |
| `created_at` | DATETIME | |

### `wp_som_budget_ledger`
Audit trail for every balance change — same pattern as the existing `material_stock_log` table.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `budget_id` | INT FK → budgets.id | |
| `order_id` | INT FK → orders.id NULL | set for `sale_funding` rows; references existing `orders` table |
| `purchase_order_item_id` | INT FK → purchase_order_items.id NULL | set for `purchase_spend` rows (material budgets only); references `purchase_order_items` from Update Package 1 |
| `change_amount` | DECIMAL(12,4) | positive = funded, negative = spent/withdrawn |
| `reason` | VARCHAR(50) | `sale_funding`, `purchase_spend`, `manual_adjustment` |
| `notes` | TEXT NULL | free text, mainly for `manual_adjustment` rows |
| `created_at` | DATETIME | |

## Migration notes

- New tables via `dbDelta()`, consistent with existing plugin convention.
- Bump `som_db_version` in `wp_options` and add the corresponding migration step.
- No changes to any existing table's schema — this update only adds new tables and new behavioural hooks into existing order-creation and purchase-receipt flows (see `03-Update-Budgets.md` §3–4).
