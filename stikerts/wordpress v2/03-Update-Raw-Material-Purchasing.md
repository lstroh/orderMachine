# Update — Raw Material Purchasing

*Update set 3 of 4 · Schema referenced here is defined in `02-Update-Data-Model.md` Part A/C. Self-contained — no need to reference the original planning files.*

---

## 1. What this adds

The **supply side** of the existing material stock model. The existing plugin already decrements `materials.current_stock` and writes a `material_stock_log` row the moment a new order is synced in, based on each product's fixed material recipe. This update doesn't touch that consumption logic — it feeds the other end of the same pool: purchases increase stock, and this defines how the *cost* side of that stock is tracked, since cost needs to inform pricing decisions.

No changes to the core workflow engine are needed — materials are consumed at order-creation time, not at a specific workflow step, so purchasing doesn't hook into workflow steps directly. The connection point is the shared `materials` table.

## 2. Costing approach — landed cost + weighted average

- **Landed cost:** a material's true unit cost is price plus its fair share of shipping. For a purchase order containing multiple materials, shipping is split **by value** (each line's cost as a proportion of the order's total line cost).
- **Weighted average cost:** rather than tracking which specific batch/supplier's units get used on any given order, each material carries one blended cost per unit, recalculated every time new stock is received — the standard approach for small businesses buying the same material repeatedly at varying prices from possibly-different suppliers.

### Worked example — landed cost + shipping allocation

One purchase order containing:
- 50 sheets vinyl @ £0.60/sheet = £30.00
- 20 sheets laminate @ £0.45/sheet = £9.00
- Total line cost: £39.00; shipping: £6.00

Shipping allocated by value:
- Vinyl's share: (£30.00 / £39.00) × £6.00 = £4.615
- Laminate's share: (£9.00 / £39.00) × £6.00 = £1.385

Landed unit cost:
- Vinyl: (£30.00 + £4.615) / 50 = **£0.6923/sheet**
- Laminate: (£9.00 + £1.385) / 20 = **£0.5193/sheet**

### Worked example — rolling weighted average on receipt

If vinyl currently has `current_stock = 30`, `total_value_on_hand = £18.00` (weighted average £0.60/sheet), and the purchase above (50 sheets @ landed £0.6923) is received:

- New `total_value_on_hand` = £18.00 + (50 × £0.6923) = **£52.615**
- New `current_stock` = 30 + 50 = **80**
- New weighted average = £52.615 / 80 = **£0.6577/sheet**

This is a **moving average cost** method: `materials.total_value_on_hand` and `materials.current_stock` are tracked directly, weighted average is always `total_value_on_hand / current_stock`.

**On consumption** (an order decrements stock): the value removed is `change_qty × current weighted average at that moment`, not the original purchase price — keeps `total_value_on_hand` consistent, and is why `material_stock_log.unit_cost_at_time` is recorded on every row, including consumption rows.

**Edge case — negative stock:** `current_stock` can already go negative (oversold) in the existing plugin. When at/below zero and a purchase is received, the formula above still works arithmetically from a negative starting point — no special-casing needed, but worth a sanity-check in testing.

## 3. Purchase order flow

1. **Create PO:** pick supplier, add line items (material, quantity, cost), enter shipping/other cost. Status `ordered`.
2. **Preview Impact (optional, before saving/committing — see §5d):** run the landed-cost + weighted-average calculation without persisting, to check impact before actually ordering.
3. **Receive PO:** enter actual quantity received per line (defaults to quantity ordered, editable for short shipments). Triggers:
   - Landed-cost calculation per line (§2).
   - Weighted-average update on each affected material.
   - A `purchase_received` row in `material_stock_log`, with `purchase_order_item_id`, `unit_cost_at_time`, and `value_change` populated.
   - Goal-cost alert check (§5c) for every affected material.
4. PO status → `received` (or `partially_received` if quantities came up short — see §7 open item 1).

## 4. Lead time tracking

`received_date − order_date` per PO gives lead time in days. Surfaced per-material (via its purchase order items):

- **Average lead time per material/supplier** — simple average across past POs, shown on the material detail page.
- **Reorder point formula (optional, don't build unless there's enough purchase history to make it meaningful):** `Reorder Point = (Average Daily Usage × Lead Time) + Safety Stock`. The existing simple `low_stock_threshold` field remains the primary v1 mechanism; this is a future enhancement, not required for this update.

## 5. Target selling price, per-workflow material cost goals, and alerts

Pricing here is **competition-driven, not cost-derived**: you set the price you want to charge based on competitors, and this feature tells you when rising material costs threaten the profit that price implies.

### 5a. Target selling price (per product)

`products.target_selling_price` is set by you directly (see `02-Update-Data-Model.md` Part C). Resulting profit/margin is always **calculated, not stored**: `profit = target_selling_price − material_cost`, `margin % = profit / target_selling_price`, where `material_cost` is the live sum across the product's full recipe (`product_materials`), including packaging-level materials (thank-you card, envelope, backing card — anything in the recipe).

### 5b. Goal cost per material, per workflow

The same material can have a different cost ceiling depending on which workflow uses it (`workflow_material_goals`, see `02-Update-Data-Model.md` Part A) — e.g. the thank-you card material might have one goal in a "Bin Sticker Production" workflow and a different one in a "Premium Gift Set" workflow, even though it's the same material row. Not every material needs a goal set — opt-in per (workflow, material) pair; no row = no alert.

### 5c. Alert logic

Whenever a material's weighted-average cost changes (i.e. on every purchase receipt), for every `workflow_material_goals` row referencing that material:

- **current weighted-average ≥ goal_unit_cost** → **"Over goal"** alert (red).
- **current weighted-average ≥ goal_unit_cost × (warning_threshold_percent / 100)**, but below the goal itself → **"Approaching goal"** warning (amber).
- Otherwise → no flag.

Surfaces in two places:
- **Materials page:** badge on any material over/approaching goal in *any* workflow it's used in, with a breakdown of which workflow(s) triggered it.
- **Product Costing view:** for a product, cross-reference its workflow's goals against its recipe materials — flag there too, alongside the resulting actual profit/margin against `target_selling_price`.

### 5d. Pre-buying preview

A **"Preview Impact" button** on the Purchase Order create/edit screen, reusing the exact same calculation logic as actually receiving a PO (§2–3), without persisting anything:

1. Fill in the PO as normal (supplier, line items, quantities, costs, shipping).
2. Clicking "Preview Impact" runs the landed-cost and weighted-average calculations exactly as receiving would, in memory only — no DB writes, nothing saved unless the PO is also saved for real.
3. Shows, per material: what the new weighted-average *would* become, and whether this crosses "approaching"/"over" goal territory for every workflow with a goal set on that material.
4. Shows downstream impact: every product whose recipe includes an affected material, resulting new material cost, and resulting profit/margin against that product's `target_selling_price`.

**Build this as one shared calculation function/service**, called from both the real receive-flow and this preview action — not duplicated logic.

## 6. UI requirements (new admin pages/sections)

| Page | Purpose |
|---|---|
| **Suppliers** | List/add/edit suppliers |
| **Purchase Orders (list)** | All POs, filterable by status/supplier |
| **Purchase Order (create/edit)** | Line items, shipping/other cost, "Preview Impact" action (§5d) |
| **Purchase Order (receive)** | Enter actual quantity received per line, triggers costing update + alert check |
| **Materials (enhanced)** | Existing page gains: weighted-average cost, total value on hand, average lead time, preferred supplier, purchase history, goal-cost alert badges |
| **Workflow material goals** | Set/edit `goal_unit_cost` and `warning_threshold_percent` per (workflow, material) pair — natural home is alongside the existing workflow template/step editor |
| **Product Costing** | Per product: `target_selling_price`, live material cost, resulting profit/margin, goal-cost alerts, and actual live listing price(s) from `listings` side by side |

## 7. Open items to resolve before/during build

1. **Partial receipts:** if `quantity_received < quantity_ordered`, does the remaining balance stay open on the same PO (status `partially_received`), or need manual closing? Affects the receive-flow UI.
2. **Editing a received PO:** should fixing a mistake after receipt retroactively recalculate the weighted average, or go through a separate correcting adjustment (simpler, cleaner audit trail — recommended)?
3. **Multi-currency:** assumed not needed (UK suppliers, GBP only) — flag if any supplier bills differently.
4. **Alert visibility beyond Materials/Product Costing:** is that sufficient, or do you want a dashboard-level "cost alerts" summary too?
5. **Workflow reassignment:** if a product's workflow changes later, its alerts/goals follow the new workflow's `workflow_material_goals` automatically — falls out naturally from the data model, just flagging as expected behaviour.
