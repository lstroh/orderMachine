# Update — Order Material Overuse

*Package 4 · Schema / log reasons in `02-Update-Data-Model.md` §B. Self-contained.*

---

## 1. What this adds

When production reality uses **more** material than the product recipe reserved, the operator records that on the **order**. The product recipe is **not** changed.

Effects of saving a higher actual:

1. **Stock** decreases by the extra quantity (WA / value fields via existing `adjust_stock` path).
2. **Order profit / Analytics COGS** include the extra cost.
3. **Material budgets** receive additional funding for the extra cost (restock pot stays honest).

## 2. Settled rules

| Rule | Decision |
|---|---|
| Where to edit | **Order detail** only |
| Board | Open order detail (no inline editor on cards) |
| Which materials | Only materials already reserved for this order from the **product recipe** (no add foreign materials) |
| Direction | **Increase only** — cannot set actual below planned |
| When | **Anytime**, including completed / shipped |
| Recipe catalogue | Unchanged |
| Cancel (future D3/A3) | Reverse **actual** total (`new_order` + extras), not recipe alone |

## 3. Behaviour

### Baseline (unchanged)

On incremental create (not history import, not cancelled): recipe × qty → `material_stock_log` with `reason = new_order` + existing budget `fund_on_create`.

### Order detail — “Materials used” panel

Replace or extend the current read-only Material stock panel:

| Column | Content |
|---|---|
| Material | Name (+ unit) |
| Planned | From `new_order` abs qty |
| Actual | Planned + sum(`order_usage_extra`) — editable number ≥ planned |
| Extra | Actual − Planned (display) |

- Save (nonce + `manage_options`): for each line where actual > current stored actual, write delta as `order_usage_extra` stock log + fund matching material budget for that delta cost.
- Reject actual &lt; planned with a clear error.
- Materials with no `new_order` line for this order do not appear (unmatched / no reservation).
- Repeatable: further increases allowed later; still never below planned.

### Profit / Analytics

- `order_material_cogs` (and peers) sum `new_order` **and** `order_usage_extra`.
- Product Costing (catalogue) stays **recipe-based** — overuse is order-level reality, not a BOM change.

### Budgets

- Extra funding only for materials that have an **active** material budget, using the same workflow-scope gates as create-time funding where applicable.
- If create-time funding was skipped (history import, cancelled), overuse on that order: **recommend allow stock adjust + funding for extras anyway** when operator explicitly records usage — **open item**.

### History import orders

Orders that never got `new_order` lines have nothing to overuse against. Panel shows empty / “No materials reserved”. No fabricated planned qty from live recipe unless we later add “reserve now” (out of scope).

## 4. UI requirements

| Page | Purpose |
|---|---|
| Order detail | Editable Materials used panel; flash success/error |
| (Optional) Material stock log | Show `order_usage_extra` with human label “Order overuse” and link to order |

No Board changes beyond existing navigation to detail.

## 5. Interaction with Internal Products

If a recipe line is a component material (output of an internal product), overuse increases consumption of **that material stock** the same way — it does not create a production job. Restocking components remains Produce N / low-stock (feature 06).

## 6. Out of scope

- Using less than planned
- Adding non-recipe materials on the order
- Writing extras back into `product_materials`
- Automatic waste % 
- Per-line-item (order_item) split of overuse — **order-level** totals are enough for v1 (multi-product orders: materials are those reserved for the whole order; **open item** if mixed products need clearer attribution)

## 7. Open items

1. Budget ledger reason: reuse `sale_funding` vs `usage_extra_funding`.
2. Multi-product orders: single pooled materials list vs per-line attribution (recommend pooled, matching today’s stock summary).
3. Overuse when original funding was skipped (history import).
4. Whether unit cost for extras uses current WA at overuse time (recommend **yes**, same as consumption helper) vs original `new_order` unit_cost_at_time.
5. Confirm cancel-reversal work (when scheduled) must include `order_usage_extra` — record as requirement; do not implement cancel reversal in this package unless explicitly expanded.
