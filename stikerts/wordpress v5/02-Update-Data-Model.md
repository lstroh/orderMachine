# Update Package 4 — Data Model Changes

*Self-contained schema delta for Package 4. Baseline at planning: `som_db_version` **1.10.0** (plugin v0.23.0). Analytics Dashboard and R&D copy need no schema.*

---

## Migration notes (all features)

- Apply via existing `SOM_DB::create_tables()` / `dbDelta` + version bump pattern in `includes/class-som-db.php`.
- Bump `SOM_DB::DB_VERSION` (suggest **1.11.0** for instructions, then further bumps per sprint if preferred — or one bump **1.11.0** when the first schema sprint lands and fold later ALTERs into subsequent bumps **1.12.0** / **1.13.0**).
- Additive only: do not drop columns/tables.
- Keep GBP / decimal conventions already used elsewhere.

---

## A. Step Instructions

### A1. Alter `wp_som_workflow_steps`

| Column | Type | Notes |
|---|---|---|
| `instructions` | TEXT NULL | Plain-text default for this step. Empty/null = no default. |

### A2. New table `wp_som_product_step_instructions`

Per-product override for a workflow step. When present and non-empty, wins over the step default for that product.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `product_id` | BIGINT FK → products.id | |
| `workflow_step_id` | BIGINT FK → workflow_steps.id | Step must belong to the product’s current `workflow_template_id` in UI validation (orphans possible if template reassigned — see open items) |
| `instructions` | TEXT NOT NULL | Plain text; empty string should delete the override row rather than store blank |
| `created_at` / `updated_at` | DATETIME | |

**Indexes:** `UNIQUE (product_id, workflow_step_id)`; KEY on `workflow_step_id`.

**Resolve helper (logical):**

```
effective = override.instructions if override exists
         else step.instructions
         else null
```

Primary product for an order = existing rule (first `order_items` row with non-null `product_id`).

---

## B. Order Material Overuse

### B1. Prefer log-based actuals (no required new table)

Reuse `wp_som_material_stock_log`:

| Reason value | Meaning |
|---|---|
| `new_order` | Existing planned reservation on incremental create (unchanged) |
| `order_usage_extra` | **New** — additional consumption for an order (negative `change_qty`, same sign convention as `new_order`) |

Optional later: `order_cancelled` reversal already deferred (D3/A3) must reverse **sum** of `new_order` + `order_usage_extra` for that `order_id`.

**Actual qty for material M on order O:**

```
actual = sum( abs(change_qty) ) where order_id=O and material_id=M
         and reason IN ('new_order','order_usage_extra')
```

**Planned qty:** abs of `new_order` lines only (or recipe × line qty recomputed — log is source of truth once reserved).

**Rules encoded in application logic (not DB constraints):**

- Only materials that appear on the order’s reserved recipe set (materials with a `new_order` line for that order, or equivalently materials from matched products’ recipes that were reserved).
- New actual ≥ planned (increase only); UI/API reject decreases.
- Each save writes **delta-only** `order_usage_extra` rows (never rewrite `new_order`).
- Idempotent display: show planned + sum(extras) as current actual.

### B2. Budget ledger

No new budget tables. Extra funding uses existing `budget_ledger` with `reason = extra_material_usage` (UI label: **Extra material usage**). Amount = `extra_qty × unit_cost_at_time` for the material budget linked to that material, following the same material-budget scoping rules as create-time funding.

### B3. Optional summary table (not required for v1)

If UI/query pain appears, a denormalised `wp_som_order_material_usage` (`order_id`, `material_id`, `planned_qty`, `actual_qty`) may be added later. **v1 recommendation: derive from stock log** to avoid dual sources of truth.

### B4. Analytics / profit

Extend `SOM_Analytics::order_material_cogs` (and any twin helpers) to include `order_usage_extra` in the same abs(qty)×unit_cost sum. Fee-aware profit automatically improves.

---

## C. Internal Products

### C1. Alter `wp_som_products`

| Column | Type | Notes |
|---|---|---|
| `is_internal` | TINYINT(1) NOT NULL DEFAULT 0 | 1 = internal/component product |
| `linked_material_id` | BIGINT UNSIGNED NULL | Output material produced when a production job completes; required when `is_internal = 1` |

**Indexes:** KEY `linked_material_id`; recommend **UNIQUE** on `linked_material_id` where not null (one internal product owns one output material).

**Rules:**

- Internal products: no marketplace listings (`listings.product_id` must not point at them — enforce in UI + save).
- `linked_material_id` points at a normal `materials` row (may be auto-created when the internal product is created).
- Recipe on the internal product describes **inputs** (raw and/or other component materials). Output is only via `linked_material_id`, not a recipe line to self.

### C2. Optional alter `wp_som_materials`

| Column | Type | Notes |
|---|---|---|
| `source_product_id` | BIGINT UNSIGNED NULL | Inverse pointer to owning internal product; UNIQUE where not null |

Nice for admin (“this stock is made in-house”) and low-stock → Produce N. Can be maintained in app code whenever `linked_material_id` is set. **Open item:** column vs app-only inverse lookup.

### C3. Production orders — discriminate from channel orders

Channel orders today key on `channel_id` + `external_order_id`. Production jobs must appear on the **same** orders list/Board.

**Recommended approach (confirm in sprint planning):**

1. Seed or ensure a synthetic channel row, e.g. `slug = internal` / name “Internal”, **no OAuth credentials**.
2. Add `orders.order_kind` ENUM(`channel`,`production`) NOT NULL DEFAULT `channel` **or** infer production when `channel.slug = internal`.
3. Creating “Produce N” of internal product P:
   - Insert order on internal channel with generated `external_order_id` (e.g. `PROD-{product_id}-{timestamp}`).
   - One `order_items` row: `product_id = P`, `quantity = N`, no personalisation required.
   - Run stock reservation for **P’s recipe × N** (inputs), assign P’s workflow — same hooks as incremental create **except**: skip marketplace fee sync expectations; skip sale funding for **output** material; **do** apply normal material budget funding for **input** materials consumed (same as a sale consuming those inputs — **open item** whether production should fund input material budgets).
4. When production order becomes complete (`is_complete` / workflow finished): increase `linked_material_id` stock by **N**, with unit cost = total input consumption cost / N (WA update path analogous to a receive, or a dedicated `production_output` stock-log reason).

| New stock-log reason | Meaning |
|---|---|
| `production_output` | Positive `change_qty` onto linked material when production completes |

### C4. Nesting

- Component material M (from internal product A) may appear in product B’s recipe (sellable or internal).
- **Cycle detection** required when saving recipes or linking materials: A must not consume its own output material directly or through a chain.
- Depth: no hard DB limit; recommend practical max depth in validation (e.g. 5) — **open item**.

### C5. Low stock → production

Uses existing `materials.low_stock_threshold` / low-stock flag on the **linked output** material. Behaviour (notify-only vs auto-draft Produce N) is an open item; schema does not need a new table for v1 if drafts are ordinary production orders with a status/note.

---

## D. Explicitly out of schema scope

- Per-order auto-spawn of component jobs
- Replacing thank-you batch groups
- Multi-currency
- Cancel stock reversal implementation (still D3/A3) — only the **requirement** that it reverse actuals is recorded for when that work lands
- Board card instruction snippets
- Recipe write-back from overuse

---

## Open items (data model)

1. **Budget reason for overuse funding:** **Settled** — `extra_material_usage` (UI: Extra material usage).
2. **Production channel vs `order_kind` column** — pick one before Internal Products sprint.
3. **Should production consume-and-fund input material budgets** the same as customer sales?
4. **`materials.source_product_id`** column vs derive from `products.linked_material_id`.
5. **Orphan product_step_instructions** when product’s workflow template changes — delete overrides, or keep and ignore until steps remapped?
6. **Production output costing:** treat like PO receive into WA, or simpler set `unit_cost_at_time` on `production_output` without full WA machinery?
7. **Single order with qty N vs N orders of qty 1** for Produce N (recommend **one order, qty N**).
