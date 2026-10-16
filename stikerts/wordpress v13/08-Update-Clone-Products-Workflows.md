# Update — Clone workflows and products

*Package 12 · No schema. Driven by operator request (start a new workflow / product from an existing one instead of from scratch).*

---

## 1. What operators asked for

A **Clone** option for **workflow templates** and **products**.

## 2. Today

No clone/duplicate action exists. New templates / products start empty (`SOM_Workflows::editor_url( 'new' )`, `SOM_Products::detail_url( 'new' )`).

Data attached to each:

| Entity | Own row | Related rows |
|---|---|---|
| Workflow template | `som_workflow_templates`: name, description, kind, uses_trays, is_active | `som_workflow_steps` (order, name, manual confirm, timer, planned, command template, batch group, confirmation kind, instructions, task list); `som_workflow_material_goals`; `som_budget_workflow_links` |
| Product | `som_products`: name, sku, workflow_template_id, post_ship_workflow_template_id, target price, weight, package_id, package_id_multi, planned shipping, is_internal, linked_material_id (**unique**), is_active | `som_product_materials` (recipe); `som_product_step_instructions` + `som_product_step_tasks` (keyed by **step id**); `som_budget_product_links`; `som_listings` (unique per channel listing) |

## 3. Settled decisions (operator, 2026-10-14)

| # | Topic | Decision |
|---|---|---|
| C1 | Entry points | **Clone** row action on Products + Workflows lists **and** a **Clone** button on each edit screen; after cloning, open the copy in its editor |
| C2 | Naming / status | Name **“X (copy)”**; product **SKU blank**; copy keeps the original’s **active / inactive** state |
| C3 | Product clone copies | All product fields + recipe + step instruction overrides + step task overrides + budget links. **Not:** listings, orders, history, stock |
| C4 | Internal product | Clone gets a **new** linked output material (stock 0) |
| C5 | Product → workflow | Clone uses the **same** workflow template(s); **no** “also clone workflow” option |
| C6 | Workflow clone copies | All steps (every setting) + material goals + budget links. **Not:** products/orders moved, site Pack / Post-ship default |
| C7 | BUG-005 interplay | Cloning a Post-ship template **without** a Package arrived step is **blocked** (same message as Save) |
| C8 | Sprints | Split: **UP12-S6** workflows, **UP12-S7** products |

## 4. Behaviour — workflow clone (UP12-S6)

| Topic | Rule |
|---|---|
| Action | POST with nonce + `manage_options` (not a GET link that mutates); list row action renders a tiny form/button or uses `admin-post.php` with nonce |
| Template row | Copy name (+ “ (copy)”, trimmed to the 100-char column), description, kind, uses_trays, is_active |
| Steps | Insert new rows in the same `step_order`, copying every column the editor saves (incl. `batch_group_id`, `confirmation_kind`, `command_template`, `instructions`, `task_list_json`, `timer_seconds`, `planned_seconds`, `requires_manual_confirm`). Legacy `script_config` is **not** copied (removed in Package 9) |
| Goals / budgets | Copy `workflow_material_goals` and `budget_workflow_links` rows to the new template id |
| Not touched | Products keep their template; orders keep bound progress; Settings defaults unchanged |
| Validation | Run the same template-level validation as Save — incl. BUG-005 Post-ship arrival rule (C7). Soft default: also run per-step combo checks so a legacy-invalid template can’t be cloned into a new invalid one |
| Atomic | Use a transaction if available (InnoDB) or delete the partial template on failure; never leave a half copy |
| After | Redirect to the copy’s editor with success notice “Cloned from X.” |

## 5. Behaviour — product clone (UP12-S7)

| Topic | Rule |
|---|---|
| Action | Same POST/nonce/cap pattern as S6 |
| Product row | Copy all columns except: `name` → “X (copy)” (trim to 100), `sku` → NULL, `linked_material_id` → see C4 |
| Same workflows | Keep `workflow_template_id` and `post_ship_workflow_template_id` (C5) |
| Recipe | Copy `product_materials` rows |
| Step overrides | Copy `product_step_instructions` and `product_step_tasks` rows as-is — **same** `workflow_step_id` values are valid because the clone uses the same template (C5) |
| Budgets | Copy `budget_product_links` |
| Internal (C4) | After insert, call the existing `SOM_Products::ensure_linked_material` path for the clone so it creates a fresh output material (name synced from “X (copy)”, stock 0). Never reuse the original’s material (unique key) |
| Not copied | `listings`, orders/items, stock logs, fees |
| Validation | Same rules as `SOM_Products::create` (e.g. internal requires workflow); fail → no partial product |
| After | Redirect to the copy’s edit screen with notice “Cloned from X. SKU left blank; listings not copied.” |

## 6. Work items (detail)

### UP12-S6

1. `SOM_Workflows::clone_template( $template_id )` → new id | WP_Error.  
2. Admin handler (nonce `som_clone_workflow`), list row action + editor button.  
3. Smoke `tests/sprint-up12-s6-smoke.php`: step count/order/fields match; goals + budget links copied; products unchanged; Post-ship without arrival → error and **no** new template row; Pack/Post-ship defaults untouched.  
4. Docs: USER-REFERENCE (Workflows), FEATURES-AND-TESTING.

### UP12-S7

1. `SOM_Products::clone_product( $product_id )` → new id | WP_Error.  
2. Admin handler (nonce `som_clone_product`), list row action + edit-screen button.  
3. Smoke `tests/sprint-up12-s7-smoke.php`: fields copied, SKU null, recipe + overrides + budget links copied, listings **not** copied, same workflow ids; internal clone gets a **new** linked material (stock 0) and original keeps its own.  
4. Docs: USER-REFERENCE (Products), FEATURES-AND-TESTING.

## 7. Out of scope

- Cloning listings, orders, stock  
- “Also clone the workflow” on product clone (C5)  
- Bulk clone  
- Cloning shipping packages, materials, budgets (not requested)  
