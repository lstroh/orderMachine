# Update Package 4 — Sprint Plan

*Planning only — no plugin code in this pass. Specs: `01`–`07` in this folder. Baseline: plugin **v0.23.0**, schema **1.10.0**.*

**Sequencing:** Step Instructions → Order Material Overuse (+ R&D copy) → Internal Products core → Internal Products UX/guards. Matches `01-Update-Overview.md` recommended order; Internal Products split into two sprints because it touches create/complete hooks, Board/list, listings, and costing.

Settled product decisions in `01-Update-Overview.md` are **locked** (overuse increase-only / recipe-only / order detail; internal make-to-stock; instructions default+override; etc.). This plan does **not** reopen them.

Open items below need confirmation before (or at the start of) the sprint they block. Recommendations are stated explicitly — **not** silently applied until you confirm.

---

## 1. Consolidated open items

| # | Source | Item | Blocks | Recommendation / status |
|---|---|---|---|---|
| O1 | `03` §5.1 | Show instructions on all progress steps vs current only | UP4-S1 order detail | **Recommend: all steps** in the progress list |
| O2 | `03` §5.2 | Hard max length for instruction text | UP4-S1 save validation | **Recommend: 5000 chars**; soft UI note |
| O3 | `02`§5 / `03` §5.3 | Orphan overrides when product workflow changes | UP4-S1 product save | **Recommend: delete** overrides whose `workflow_step_id` is not in the new template |
| O4 | `03` §5.4 | MCP/REST expose instructions? | UP4-S1 | **Recommend: skip v1** (admin UI only) |
| O5 | `02`§1 / `04` §7.1 | Budget reason for overuse: `sale_funding` vs `usage_extra_funding` | UP4-S2 funding | **Recommend: `usage_extra_funding`** (clearer audit; `fund_on_create` must not be reused — see §3) |
| O6 | `04` §7.2 | Multi-product orders: pooled vs per-line materials | UP4-S2 UI | **Recommend: pooled** (matches today’s stock summary) |
| O7 | `04` §7.3 | Overuse when create-time funding was skipped (history import) | UP4-S2 | **Recommend: allow** stock+funding for extras if `new_order` lines exist; if no reservation, panel empty |
| O8 | `04` §7.4 | Unit cost for extras: current WA vs original `new_order` cost | UP4-S2 | **Recommend: current WA** at overuse time (`unit_cost_for_consumption`) |
| O9 | `04` §7.5 | Cancel reversal must include extras | Future D3/A3 (not this package) | **Recorded requirement only** — do not implement cancel reversal in Package 4 |
| O10 | `05` §5.1–2 | Final R&D wording / Budgets list glossary | UP4-S2 copy | **Recommend:** update material + budget detail help; **no** list glossary |
| O11 | `02`§2 / `06` §10.1 | Production discrimination: `internal` channel vs `order_kind` | UP4-S3 | **Recommend: synthetic channel `internal`** (mirror existing `external`); no `order_kind` column in v1 |
| O12 | `02`§7 / `06` §10.2 | Produce N = one order qty N vs N orders | UP4-S3 | **Recommend: one order, qty N** |
| O13 | `06` §10.3 | Low stock: prompt only vs auto-draft | UP4-S4 | **Recommend: affordance only** (Produce button / notice); no silent auto-create |
| O14 | `02`§3 / `06` §10.4 | Production start: fund input material budgets? | UP4-S3 | **Recommend: yes** — same `fund_on_create` after input `new_order` (restock pots for vinyl etc. stay honest) |
| O15 | `02`§6 / `06` §10.5 | Production output costing / WA | UP4-S3 complete hook | **Recommend:** `production_output` log + update material WA/`unit_cost`/`total_value_on_hand` via existing adjust/receive-style helpers where possible |
| O16 | `02`§4 / `06` §10.6 | `materials.source_product_id` column? | UP4-S3 schema | **Recommend: yes** — UNIQUE nullable inverse of `products.linked_material_id`, kept in sync in app code |
| O17 | `06` §10.7 | Deactivate internal product with open jobs / stock | UP4-S4 | **Recommend:** block deactivate while open production orders exist; allow deactivate with stock remaining (material stays) |
| O18 | `06` §10.8 | Production orders in Analytics sales/profit? | UP4-S4 | **Recommend: exclude** channel `internal` from sales/AOV/profit charts |
| O19 | `06` §10.9 | Max nesting depth | UP4-S4 recipe validation | **Recommend: depth 5** + cycle detection |
| O20 | Code | `fund_on_create` no-ops if **any** `sale_funding` exists for the order | UP4-S2 | **Must** add a separate funding helper for extras (cannot call `fund_on_create` again) |
| O21 | Code | `get_order_summary` returns raw log lines, not planned/actual aggregates | UP4-S2 | Add aggregation helper for Materials used UI |
| O22 | Code | Workflow complete has no action hook today (`advance_after_step` sets `is_complete`) | UP4-S3 | Add a small hook or direct call from complete path for `production_output` — prefer `do_action( 'som_order_completed', $order_id )` for cleanliness |

---

## 2. Clarifying questions (kept visible)

Answer these (or accept the recommendations) before / as each sprint starts. Do **not** re-ask settled decisions from `01-Update-Overview.md`.

### Instructions (before UP4-S1)

1. **Show instructions on all workflow steps on order detail, or only the current step?**  
   **Recommend:** all steps.

2. **Max instruction length 5000 chars OK?**  
   **Recommend:** yes.

3. **On workflow reassignment, delete orphan product overrides?**  
   **Recommend:** yes.

4. **Skip MCP/REST for instructions in v1?**  
   **Recommend:** yes.

### Overuse + R&D (before UP4-S2)

5. **New ledger reason `usage_extra_funding` vs reuse `sale_funding`?**  
   **Recommend:** `usage_extra_funding` (required separately from `fund_on_create` anyway — O20).

6. **Pooled materials on multi-product orders OK?**  
   **Recommend:** yes.

7. **Extras unit cost = current WA at save time OK?**  
   **Recommend:** yes.

8. **R&D copy: material + budget detail only (no Budgets list glossary)?**  
   **Recommend:** yes.

### Internal products (before UP4-S3 / S4)

9. **Discriminate production via new channel slug `internal` (like `external`), no `order_kind` column?**  
   **Recommend:** yes.

10. **Produce N = one order with quantity N?**  
    **Recommend:** yes.

11. **On production create, fund input material budgets via existing `fund_on_create`?**  
    **Recommend:** yes.

12. **Low stock = Produce affordance only (no auto-draft job)?**  
    **Recommend:** yes.

13. **Exclude `internal` channel from Analytics sales/profit/AOV?**  
    **Recommend:** yes.

14. **Nesting: cycle detection + max depth 5?**  
    **Recommend:** yes.

15. **Add `materials.source_product_id` as inverse pointer?**  
    **Recommend:** yes.

---

## 3. Spec ↔ codebase discrepancies

Treat **existing plugin code as ground truth**. None block planning; they shape implementation.

| Spec assumption | Actual code today | Plan impact |
|---|---|---|
| Re-fund budgets on overuse via same create path | [`SOM_Budgets::fund_on_create`](../../includes/class-som-budgets.php) returns early if `has_sale_funding_for_order` — **any** prior `sale_funding` blocks all further funding | **New** method e.g. `fund_usage_extras( $order_id, $stock_log_ids )` using reason `usage_extra_funding` (O5/O20) |
| Material COGS = all consumption | [`SOM_Analytics::order_material_cogs`](../../includes/class-som-analytics.php) filters `reason = new_order` only | Extend to include `order_usage_extra` |
| Order stock panel is editable / planned vs actual | [`SOM_Material_Stock::get_order_summary`](../../includes/class-som-material-stock.php) + order-detail view are **read-only** raw lines | New aggregate + POST handler in `handle_orders_actions` |
| Cancel reversal reverses logged qty | `maybe_reverse_on_cancel` is a **no-op placeholder**; comment already says reverse from logged qty | When D3/A3 lands, include `order_usage_extra` (O9) — out of Package 4 scope |
| Synthetic production channel | [`SOM_Channels::known()`](../../includes/class-som-channels.php) already has `ebay`, `etsy`, **`external`** (active, no OAuth) | Add `internal` the same way; filters already key off `c.slug` |
| `order_kind` column | **Does not exist**; no `is_internal` on products | Prefer channel slug over new order column (O11) |
| Complete → side effects | [`advance_after_step`](../../includes/class-som-workflow-engine.php) sets `is_complete = 1` with **no** `do_action` | Add completion hook or dedicated call for production output (O22) |
| Listing product picker | [`SOM_Listings::product_options()`](../../includes/class-som-listings.php) (via listing-edit) | Filter out `is_internal` in S4 |
| Schema upgrades | `DB_VERSION` **1.10.0**; `maybe_upgrade` runs `create_tables()` on mismatch + small repairs | Bump per sprint: **1.11.0** (S1), **1.12.0** (S2 if any DDL — log reasons need no DDL), **1.13.0** (S3 product/material columns) |
| Step save path | [`handle_workflows_actions`](../../admin/class-som-admin-menu.php) → `SOM_Workflows::save_steps` | Add `instructions` into step row payload |
| Product save path | `handle_products_actions` → product update + `save_recipe` | After save, persist `product_step_instructions` |
| R&D copy locations | [`admin/views/material-edit.php`](../../admin/views/material-edit.php), [`admin/views/budget-edit.php`](../../admin/views/budget-edit.php) | String updates only in S2 |
| Primary product | [`SOM_Workflow_Engine::primary_product_id`](../../includes/class-som-workflow-engine.php) — first item with non-null `product_id` | Use for instruction resolve (unchanged rule) |

---

## 4. Architecture notes (implementation guidance)

### 4.1 Step instructions

- Column `workflow_steps.instructions` TEXT NULL.
- Table `wp_som_product_step_instructions` UNIQUE `(product_id, workflow_step_id)`.
- Helper e.g. `SOM_Step_Instructions::effective( $product_id, $step_id )`.
- Display with newline preservation; empty → hide.

### 4.2 Order material overuse

```text
Planned(M) = sum abs(change_qty) where reason=new_order
Extra(M)   = sum abs(change_qty) where reason=order_usage_extra
Actual(M)  = Planned + Extra
On save: if Actual_new > Actual_old → adjust_stock(−delta, reason=order_usage_extra)
         → fund_usage_extras for that log line (reason=usage_extra_funding)
Reject Actual_new < Planned
```

- Do **not** call `fund_on_create` for extras.
- Extend Analytics COGS reasons list.
- Label new reasons in `SOM_Materials::reason_label`.

### 4.3 Internal products / production

```text
SOM_Channels::known() += internal => Internal
Produce N → order on internal channel, one line product P qty N
         → decrement_on_create (inputs) + assign_on_create + fund_on_create (if O14=yes)
Complete → if channel=internal && product.is_internal
         → adjust_stock(+N on linked_material, reason=production_output, unit cost from inputs/N)
```

- Cycle detection when saving recipes that include component materials.
- Listings / Analytics exclude internals as decided.

### 4.4 R&D copy

- No schema. Replace help strings with restock-pot wording from `05-Update-Rnd-Budget-Copy.md`; sync FEATURES/USER docs lightly.

---

## 5. Sprint breakdown

### UP4-S1 — Step instructions

- **Covers:** `03-Update-Step-Instructions.md` + `02` §A  
- **Open items first:** O1–O4 (confirm or accept recommendations)

**Files (expected):**

| File | Change |
|---|---|
| `includes/class-som-db.php` | `instructions` column; `product_step_instructions` table; bump **1.11.0** |
| `includes/class-som-step-instructions.php` | **New** — CRUD overrides + `effective()` |
| `includes/class-som-workflows.php` | Persist step `instructions` in `save_steps` / get_steps |
| `includes/class-som-products.php` | On workflow change, orphan cleanup (O3); load helpers for edit UI |
| `admin/views/workflow-step-editor.php` | Default instructions textarea per step |
| `admin/views/product-edit.php` | Override section for current template steps |
| `admin/views/order-detail.php` | Read-only effective instructions in progress list |
| `admin/class-som-admin-menu.php` | Wire save of overrides in `handle_products_actions` / workflows |
| `orderMachine.php` | Require new class |
| `tests/sprint-up4-s1-smoke.php` | **New** — schema + resolve override→default |

**Done when:**

- Can set step default + product override; order detail shows effective text (all steps if O1=all).
- Changing product workflow cleans orphans (if O3=delete).
- wp-env smoke passes; no Board changes.

---

### UP4-S2 — Order material overuse + R&D copy

- **Covers:** `04-Update-Order-Material-Overuse.md`, `05-Update-Rnd-Budget-Copy.md`, `02` §B  
- **Open items first:** O5–O10, O20–O21  
- **DDL:** none required if log reasons are free-form varchar (they are). Optional schema bump only if you add a helper table (not recommended).

**Files (expected):**

| File | Change |
|---|---|
| `includes/class-som-material-stock.php` | Aggregate planned/actual; apply overuse deltas (`order_usage_extra`) |
| `includes/class-som-budgets.php` | `fund_usage_extras` (+ reason constant); do not reuse `fund_on_create` |
| `includes/class-som-analytics.php` | COGS includes `order_usage_extra` |
| `includes/class-som-materials.php` | `reason_label` for `order_usage_extra` |
| `admin/views/order-detail.php` | Materials used panel (edit actual ≥ planned) |
| `admin/class-som-admin-menu.php` | `handle_orders_actions` save overuse |
| `admin/views/material-edit.php` | R&D / Adjust stock copy |
| `admin/views/budget-edit.php` | R&D copy |
| `stikerts/wordpress/FEATURES-AND-TESTING.md` | §3.5 / §3.13 / new overuse section notes |
| `stikerts/wordpress/USER-GUIDE.md` and/or `USER-REFERENCE.md` | Short R&D + overuse notes |
| `tests/sprint-up4-s2-smoke.php` | **New** — overuse stock + COGS + budget ledger |

**Done when:**

- Order with reservation: raise actual → stock ↓, COGS ↑, budget ledger row for extra; cannot go below planned; works on completed orders.
- History-import order with no reservation: panel empty / no-op.
- R&D vs Adjust stock wording matches restock-pot explanation.
- wp-env smoke passes.

---

### UP4-S3 — Internal products core (produce + complete)

- **Covers:** `06-Update-Internal-Products.md` domain core + `02` §C  
- **Open items first:** O11, O12, O14–O16, O22  

**Files (expected):**

| File | Change |
|---|---|
| `includes/class-som-db.php` | `products.is_internal`, `products.linked_material_id`; `materials.source_product_id`; bump **1.13.0** (or 1.12.0 if S2 had no bump) |
| `includes/class-som-channels.php` | `internal` in `known()` + ensure_rows |
| `includes/class-som-products.php` | Internal flag, linked material create/sync, Produce N API |
| `includes/class-som-production.php` | **New** — create production order; on complete credit output |
| `includes/class-som-workflow-engine.php` | Fire `som_order_completed` (or call production completer) when `is_complete` flips |
| `includes/class-som-material-stock.php` | Ensure production create can reuse `decrement_on_create` |
| `includes/class-som-materials.php` | `production_output` reason label; source_product helpers |
| `admin/views/product-edit.php` | Internal toggle, Produce N, linked material display |
| `admin/class-som-admin-menu.php` | Produce N action |
| `orderMachine.php` | Require new class |
| `tests/sprint-up4-s3-smoke.php` | **New** — Produce N → reserve inputs → complete → output stock |

**Done when:**

- Internal product with recipe + workflow + linked material can **Produce N**.
- Job appears on Orders list/Board (channel Internal).
- Completing workflow credits linked material by N with cost from inputs.
- Input materials decremented; input budgets funded if O14=yes.
- Thank-you batch untouched.
- wp-env smoke passes.

---

### UP4-S4 — Internal products UX, guards, analytics

- **Covers:** remaining `06` UI/validation + O13, O17–O19  
- **Depends on:** UP4-S3  

**Files (expected):**

| File | Change |
|---|---|
| `admin/views/products-list.php` | Internal badge + filter |
| `admin/views/orders-list.php` / board | Channel filter includes Internal; optional exclude-production default **open** — recommend show all, badge visible |
| `admin/views/materials-list.php` / material-edit | Made in-house + Produce affordance when low (O13) |
| `includes/class-som-listings.php` | `product_options` excludes internal; save rejects internal |
| `includes/class-som-products.php` | Recipe cycle detection + max depth (O19); deactivate rules (O17) |
| `includes/class-som-analytics.php` | Exclude `internal` channel from sales/profit/AOV (O18) |
| `includes/seed/class-som-seed.php` | Optional internal product + consumer recipe line |
| `tests/sprint-up4-s4-smoke.php` | **New** — nesting reject, listing exclude, analytics exclude |
| Docs | FEATURES / USER notes for internal products |

**Done when:**

- Cannot list internal product on marketplace mapping.
- Nested recipe cycle rejected; depth guarded.
- Low-stock material shows Produce path (no auto job).
- Analytics ignores production orders.
- Deactivate rules as confirmed.
- wp-env smoke passes.

---

## 6. Out of scope (this package)

- Cancel → stock reversal implementation (D3/A3) — requirement only that future work reverses actuals including extras  
- Per-order auto-make of components  
- Replacing thank-you batch / personalized cards  
- Board card instruction snippets  
- Instruction MCP/REST (unless you overturn O4)  
- Decreasing material usage below planned  
- Adding non-recipe materials on an order  
- Writing overuse back into product recipes  

---

## 7. Progress tracking

After each implemented sprint, record verification in **`Update-4-Sprint-Progress.md`** (create on first implementation sprint — not in this planning pass).

---

## 8. Explicit scope of this document

This file is the Package 4 **sprint plan** only. It does not implement features. Implementation starts when you explicitly ask to implement **UP4-S1** (or a later sprint), after confirming the open items that sprint needs.
