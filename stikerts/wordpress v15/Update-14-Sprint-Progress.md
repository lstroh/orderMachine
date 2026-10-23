# Update Package 14 — Sprint Progress

*Companion to [`Update-14-Sprint-Plan.md`](Update-14-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP14-S1 | Materials CSV | Done | wp-env PASS 2026-10-23 |
| UP14-S2 | Workflow CSV | Done | wp-env PASS 2026-10-23 |
| UP14-S3 | Product CSV | Done | wp-env PASS 2026-10-23 (assertion fix, then re-run) |
| UP14-S4 | Budget checkbox on Add material | Done | wp-env PASS 2026-10-23 |
| UP14-S5 | Collapsed Shipment panel | Done | wp-env PASS 2026-10-23 |
| UP14-S6 | Trays above Make, including Internal | Done | wp-env PASS 2026-10-23 |
| UP14-S7 | Instruction bullets and editor preview | Done | wp-env PASS 2026-10-23. BUG-006 closed |
| UP14-S8 | Output per run | Done | wp-env PASS 2026-10-23 (assertion fix, then re-run). Schema 1.26.0 |
| UP14-S9 | Pack workflow override | Done | wp-env PASS 2026-10-23. Schema 1.26.0 |
| UP14-S10 | Mark done stays in place | Done | wp-env PASS 2026-10-23 |
| UP14-S11 | Finished pack leaves the Pack board | Done | wp-env PASS 2026-10-23 |
| UP14-S12 | Release the last tray on purpose | Done | wp-env PASS 2026-10-23 |

**Package 14:** planning still open for further operator items. UP14-S1 through UP14-S12 are on main. All twelve smokes passed wp-env on 2026-10-23. Schema is **1.26.0**. Plugin version stays **0.34.0**. Suggested releases **0.35.0**–**0.46.0** are not tagged.

---

## Planning notes

- Operator asked for import/export of materials, workflows, and products, and for a budget option when creating a material.
- Settled 2026-10-19: create `Name (2)` when the name exists (never update); fail a record when a referenced name is missing or ambiguous; budget checkbox on Add material only, unchecked by default.
- Settled 2026-10-19: Shipment disclosure closed on every order; Trays panel above Make; Internal orders require a tray when the make template has **Uses trays** (Package 8 skipped those products).
- 2026-10-19: filed **BUG-006**. Instruction lists already render as `<ul>`; wp-admin hides bullets. Preview opens under the textarea (UP14-S7).
- Settled 2026-10-19: make workflow **Output per run**. Internal completion adds Produce N × that number (UP14-S8, schema 1.25.0).
- Settled 2026-10-21: sellable product **Pack workflow override**. Site default remains. Primary product decides (UP14-S9, schema 1.26.0). Overturns Package 6 “Pack is site-wide only.”
- Settled 2026-10-21, confirmed: Make and Post-ship Mark done stay in place, same as Pack (UP14-S10).
- Settled 2026-10-21: an order leaves the Pack board when every pack step is done, and stays on the Post-ship board (UP14-S11). Narrows the Package 6 “open until the order is complete” board rule.
- Settled 2026-10-21: **Release anyway** frees the last tray while the order still requires one. **Release selected** still keeps one. **Unblock step** does not release a tray (UP14-S12).
- `script_config` is omitted so Package 9’s removal stays in force.
- Local site **ordermachine** is this plugin folder. UI appears there when a sprint branch is checked out. wp-env smokes for UP14-S1 through UP14-S12 passed on 2026-10-23.
- Specs: [`02-Update-Data-Model.md`](02-Update-Data-Model.md), [`03-Update-Catalog-Import-Export.md`](03-Update-Catalog-Import-Export.md), [`05-Update-Material-Budget-On-Create.md`](05-Update-Material-Budget-On-Create.md), [`06-Update-Order-Detail-Shipment-And-Trays.md`](06-Update-Order-Detail-Shipment-And-Trays.md), [`07-Update-Instruction-List-Preview.md`](07-Update-Instruction-List-Preview.md), [`08-Update-Workflow-Output-Per-Run.md`](08-Update-Workflow-Output-Per-Run.md), [`09-Update-Pack-Workflow-Override.md`](09-Update-Pack-Workflow-Override.md), [`10-Update-Mark-Done-Scroll.md`](10-Update-Mark-Done-Scroll.md), [`11-Update-Pack-Board-After-Pack.md`](11-Update-Pack-Board-After-Pack.md), [`12-Update-Release-Last-Tray.md`](12-Update-Release-Last-Tray.md).

---

## UP14-S1 — Materials CSV

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** Materials list exports, downloads a template, previews, and imports. Create goes through `SOM_Materials::create`. Stock stays 0, no stock log, and `source_product_id` is not set. Export omits materials that have `source_product_id` set. A taken name becomes `Name (2)`. An unknown or ambiguous supplier fails that row only.
- **Helper:** `SOM_Catalog_Import` holds the name suffix and the preview token. Workflow import is UP14-S2. Product import is UP14-S3.
- **Smoke:** `tests/sprint-up14-s1-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s1-smoke.php`).

## UP14-S2 — Workflow CSV

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** Workflows list exports, downloads a template, previews, and imports. One row per step; rows with the same name are one template, sorted by `step_order`. Save goes through `SOM_Workflows::validate_submission` and `save_steps`. A taken name becomes `Name (2)`. A Post-ship file with no Package arrived step writes no template. An unknown or ambiguous batch group key fails that workflow and does not create a group. Header cells that disagree fail that workflow. A task label containing `|` fails that workflow. `script_config` is not a column and is stored empty. `output_per_run` is an optional column from UP14-S8 (blank means 1; Pack and Post-ship store 1). If step insert fails after the template row exists, the partial template is deleted.
- **Smoke:** `tests/sprint-up14-s2-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s2-smoke.php`).

## UP14-S3 — Product CSV

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** Products list exports, downloads a template, previews, and imports. One row per recipe line; rows with the same name are one product. A product with no recipe is one row with the material and quantity empty. Create goes through `SOM_Products::create`, then `save_recipe`. A taken name becomes `Name (2)` and keeps the file SKU. When another product already has that SKU, the product is still created and the result lists a warning. Listings are not created. An internal product gets a new output material (stock 0). `linked_material_id` is not pointed at an existing material. An unknown or ambiguous material, make workflow, Post-ship workflow, or package fails that product and does not create the missing record. Header cells that disagree fail that product. If recipe save fails after the product exists, the new product and its new output material are deleted. `pack_workflow_name` is an optional column from UP14-S9 (blank means no override; a filled name must match exactly one Pack template; an internal product ignores the cell).
- **Smoke:** `tests/sprint-up14-s3-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s3-smoke.php`). Failed once on `column_list`, `no_pack_workflow_column`, `template_header_only`, and `export_header`. The column list omitted optional `pack_workflow_name` (UP14-S9; blank means no override). The smoke now includes that column. Re-run PASS.

## UP14-S4 — Budget checkbox on Add material

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** Add material has an unchecked checkbox **Create a material budget**. Edit material does not. When the box is clear, the material save is unchanged. When it is checked, after the material row exists, `SOM_Budgets::create` adds one active material budget named like the material, funding `material_cost`, funding value empty, target reserve empty, balance £0, notes empty, and no workflow links (every workflow). One budget per material stays in force. If the budget insert fails, the material stays and the material screen shows the error. The materials CSV does not create budgets.
- **Smoke:** `tests/sprint-up14-s4-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s4-smoke.php`).

## UP14-S5 — Collapsed Shipment panel

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** On every order detail, including Internal, Shipment is a disclosure that starts closed. Nothing stores open or closed. With no shipment row the heading is **Shipment**. When a shipment is saved, the heading also shows the status label and the tracking number when one is stored. The form inside is unchanged: carrier, service, ship date, postage, pack weight, tracking, Click & Drop reference, proof, save, and push tracking. Saving and pushing stay allowed on an Internal order. Planned shipping and Shipping address stay as they are. Pack & Ship stays hidden when the channel is Internal. Make, Pack & Ship, and Post-ship stay expanded.
- **Smoke:** `tests/sprint-up14-s5-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s5-smoke.php`).

## UP14-S6 — Trays above Make, including Internal

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** The existing Trays panel (`#som-trays`) sits immediately above Make. Held trays, free-tray checkboxes, assign, and release are the same controls. The short warning inside Make stays, with the link to `#som-trays`, when a tray is still required and none is held. `SOM_Trays::order_requires_trays()` is also true when the order channel is Internal and that product’s make template has **Uses trays**. A customer order still skips an internal component line; that line does not, by itself, require a tray. A sellable line whose make template has **Uses trays** still requires a tray. A bound Pack template with **Uses trays** still requires a tray. `trays_gate_ok()` is still the only Mark done and board-advance gate. The last-tray release rule uses the same predicate, so an open Internal order that requires a tray cannot release its last held tray. Unblock on the order detail still lets that one Mark done through without a tray. There is no Unblock on the boards and no trays overview page. Post-ship **Uses trays** stays forced off. Auto-release when the order completes is unchanged. **Release anyway** is UP14-S12 and is not in this sprint.
- **Smoke:** `tests/sprint-up14-s6-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s6-smoke.php`).

## UP14-S7 — Instruction bullets and editor preview

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** `.som-step-instructions-body ul` uses disc markers and `.som-step-instructions-body ol` uses decimal numbers, with left inset so the marker sits inside the step card. Order detail (Make, Pack, Post-ship) and the editor preview share that class. Preview stays a toolbar button. It starts closed. Clicking Preview opens the rendered box directly under the textarea, above the help line and the cheatsheet. Hide preview closes it. The Markdown allowlist and toolbar buttons are unchanged.
- **Smoke:** `tests/sprint-up14-s7-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s7-smoke.php`).
- **Bug:** BUG-006 closed 2026-10-23 after this wp-env pass.

## UP14-S8 — Output per run

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** `workflow_templates.output_per_run` is an int unsigned, not null, default 1. Schema **1.25.0**. Existing rows become 1. A Make workflow shows **Output per run** beside Uses trays (whole number 1–10000; blank saves as 1). Pack and Post-ship do not show the field, and saving those kinds stores 1. `SOM_Production::credit_output_for_order()` stays Internal-only, one `production_output` row, and idempotent. Units are the line quantity times the make template’s yield, read when the job finishes. A missing or invalid yield counts as 1. Unit cost is input COGS divided by those units. Produce 3 with output per run 12 credits 36. A yield of 1 still credits the Produce N quantity. Recipe reservation on create stays the Produce N quantity. A customer order does not credit stock. Clone copies the number. The workflow CSV has an optional `output_per_run` column: blank means 1, a Pack or Post-ship row stores 1 even when the cell is filled, and export writes the stored number.
- **Smoke:** `tests/sprint-up14-s8-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s8-smoke.php`). Failed once on `schema_1_25_0`. `SOM_DB::DB_VERSION` is 1.26.0 after UP14-S9. The assertion now expects 1.26.0. Re-run PASS.
- **Schema:** 1.25.0 in this sprint only. Live schema is 1.26.0 after UP14-S9.

## UP14-S9 — Pack workflow override

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** `products.pack_workflow_template_id` is a nullable indexed bigint. Schema **1.26.0**. Existing products stay NULL. A sellable product shows **Pack workflow override**. Blank uses Settings → Default Pack workflow. The list is active Pack templates, plus the saved one if it is now inactive. Internal products do not show the field and leave it NULL. Clone copies the override. When pack is bound (new customer order, or Repair pack binding), the override on the primary product is used when that template is an active Pack template with steps. The primary product is the first order line that has a product. Otherwise the site default is used. An inactive override, the wrong kind, or a template with no steps falls through to the site default. Other lines do not change the choice. An order that already has a pack template keeps it. An Internal order stays unbound. The product CSV has an optional `pack_workflow_name` column: blank means no override, a filled name must match exactly one Pack template, and an internal product ignores the cell.
- **Smoke:** `tests/sprint-up14-s9-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s9-smoke.php`).
- **Schema:** 1.26.0 in this sprint only.

## UP14-S10 — Mark done stays in place

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** Make Mark done and Post-ship Mark done use the same in-place reload as Pack. The make form carries `data-som-advance-step` and that line’s `order_item_id`. The request includes `order_item_id` when the form has one, then the page reloads. Post-ship carries `data-som-advance-step` and no item id. Pack Mark done, including the Ship step, is unchanged. A failed advance shows the same alert and leaves the button usable. If the REST settings are missing, the form can still post. Unblock, checklists, trays, shipment, and notes stay full posts. No new route and no schema.
- **Smoke:** `tests/sprint-up14-s10-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s10-smoke.php`).

## UP14-S11 — Finished pack leaves the Pack board

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** `SOM_Pack::query_board` omits an order that has pack progress and no pack step still open. An order with a pack step still open stays, including Waiting for make and Held. An order with no pack progress stays, so a missing Pack template is still visible. The Post-ship board is unchanged. The order page still shows the finished Pack section. If Auto-start Post-ship is off, the order still leaves the Pack board and shows under Post-ship → Not started once a shipment is recorded. No schema. Make board membership is unchanged.
- **Smoke:** `tests/sprint-up14-s11-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s11-smoke.php`).

## UP14-S12 — Release the last tray on purpose

- **Status:** done. On main. Smoke run 2026-10-23 — PASS.
- **Behaviour:** On the order Trays panel, beside **Release selected**, **Release anyway** shows when the order requires trays and is not complete. You still check at least one tray. **Release selected** still returns `som_tray_keep_one` for the last held tray while the order requires trays and is open. **Release anyway** frees the trays you checked, including the last one. The tray row stays, with `release_reason` `manual_override`. An order note records who released it and that the tray requirement was bypassed. The note does not complete a step. After the last tray is freed, Mark done stays blocked until a tray is assigned or **Unblock step** is used. **Unblock step** does not release a tray. A tray freed this way can be assigned to another order, and this order can be given a tray again. No schema. No Version bump.
- **Smoke:** `tests/sprint-up14-s12-smoke.php` — **2026-10-23 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s12-smoke.php`).

---

## wp-env pass — 2026-10-23

Plugin 0.34.0, db 1.26.0. No Version bump.

Command from the plugin root:

`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/<file>`

UP14-S1 through UP14-S12: PASS.

UP14-S3 failed once on `column_list`, `no_pack_workflow_column`, `template_header_only`, and `export_header`. The local column list still omitted `pack_workflow_name`, which UP14-S9 added as an optional column (blank means no override). The smoke now includes that column and expects it on the template and the export header. Re-run PASS.

UP14-S8 failed once on `schema_1_25_0`. `SOM_DB::DB_VERSION` is 1.26.0 after UP14-S9. The assertion now expects 1.26.0. The output-per-run column checks are unchanged. Re-run PASS.

These older smokes were not run again, were not rewritten, and are not marked passed. They remain the failures recorded in the Package 12 wp-env pass ([`../wordpress v13/Update-12-Sprint-Progress.md`](../wordpress%20v13/Update-12-Sprint-Progress.md)): `bugfix-001-002`, `shipments`, `sprint11`, `sprint-u1`, `sprint-u5`, `sprint-u6`, `sprint-u7`, `sprint-up6-s3`, `sprint-up8-s2`, `sprint-up10-s1`, `step-confirmations`.
