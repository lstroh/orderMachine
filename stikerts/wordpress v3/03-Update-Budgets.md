# Update — Budgets

*Update set 3 of 4 · Schema referenced here is defined in `02-Update-Data-Model.md`. Self-contained — no need to reference the original planning files.*

---

## 1. What this adds

A set of "pots" that automatically get funded from every sale, so you can see whether you're actually setting aside enough money to keep restocking each material/category. Two kinds:

- **Material budgets** — one per material (e.g. "Vinyl," "Laminate," "Thank-You Cards"), funded automatically based on the actual cost of that material consumed in each sale, drawn down automatically when you buy more of it.
- **Manual budgets** — anything else you want to ring-fence that isn't tied to a specific tracked material (e.g. an "Equipment Replacement Fund"), funded per sale using a rule you choose: a % of the sale price, a % of profit, or a fixed amount per unit sold.

## 2. Funding logic — hook into existing order-creation flow

The existing plugin already decrements material stock the moment a new order syncs in, based on each product's material recipe. This update adds budget-funding logic at that same trigger point, for each `order_item` in the new order:

1. **Effective sold price** = the order item's actual unit price if the channel provided it, else the product's `target_selling_price` (from Update Package 1).
2. **Material budgets:** for each material in the product's recipe that has a budget, fund it by `quantity_consumed × weighted_avg_unit_cost` (the same cost figure the existing plugin already records against material consumption) → ledger row, `reason = 'sale_funding'`.
3. **Manual budgets:** for each manual budget whose product scope (via `budget_product_links`, or unscoped = all products) includes this order item's product:
   - `percent_of_price`: `change_amount = effective_sold_price × quantity × (funding_value / 100)`
   - `percent_of_profit`: `change_amount = (effective_sold_price − material_cost_of_product) × quantity × (funding_value / 100)`, where `material_cost_of_product` is the live recipe cost (same calculation as the existing Product Costing view from Update Package 1)
   - `fixed_amount`: `change_amount = funding_value × quantity`
   → ledger row, `reason = 'sale_funding'`.
4. `budgets.current_balance` updated for every affected budget.

**Implementation note:** this should be added as a new step in the existing order-creation handler, alongside (not replacing) the existing material-stock-decrement logic — the two run from the same trigger but are independent pieces of logic.

## 3. Draw-down logic — hook into existing purchase-receipt flow

- **Material budgets:** when a purchase order item for the linked material is received (existing Update Package 1 flow), draw down the budget by that purchase line's landed cost → ledger row, `reason = 'purchase_spend'`, negative `change_amount`, linked via `purchase_order_item_id`. If no budget exists for that material, no effect — opt-in.
- **Manual budgets:** no automatic draw-down trigger. Withdrawals are recorded via a manual ledger entry (`reason = 'manual_adjustment'`) through the UI — e.g. logging that £200 was spent from the Equipment Fund on a new laminator.

**Implementation note:** add this as a new step in the existing purchase-receipt handler, alongside the existing weighted-average-cost-update logic.

## 4. Alerts

- **Low balance:** `current_balance` falls below `target_reserve_amount` (if set) → warning.
- **Overspent:** `current_balance` goes negative → alert. Not blocked — a negative balance is a real signal (spending ahead of what sales have funded so far), consistent with how the existing plugin already allows `materials.current_stock` to go negative.

## 5. UI requirements

| Page | Purpose |
|---|---|
| **Budgets (list)** | All budgets (material + manual), current balance, target reserve, low-balance/overspent badges |
| **Budget detail** | Full ledger history (which orders funded it, which purchases/adjustments drew it down), edit target reserve, edit product scope (manual budgets only) |
| **Create Budget** | Choose type; if material, pick from existing materials; if manual, choose funding method + value + product scope (all products or specific ones) |
| **Manual adjustment** | Record a withdrawal/deposit not tied to a sale or purchase — available on any budget, primarily for manual budgets |

## 6. Open items to resolve before/during build

1. **Ink tracking:** ink isn't currently a discrete-unit material like vinyl sheets. To fund an ink budget automatically via `material_cost`, ink would need its own material row with an estimated cost-per-print, added to relevant product recipes. A manual budget with a `fixed_amount` per unit sold is a simpler alternative if you'd rather not track it that precisely.
2. **Per-workflow scoping:** material budgets are global (fund from any product using that material) rather than scoped per-workflow like the goal costs from Update Package 1. Confirm that's right, or flag if per-workflow flexibility is wanted here too.
3. **Negative balances:** recommending they're allowed (§4) rather than blocked — confirm that's acceptable.
4. **`percent_of_profit` basis:** uses the *actual* sold price when available rather than the *target* price, since it's funding based on what was actually taken in — confirm that's the intended basis.
