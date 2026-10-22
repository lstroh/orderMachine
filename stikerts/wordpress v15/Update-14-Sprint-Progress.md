# Update Package 14 — Sprint Progress

*Companion to [`Update-14-Sprint-Plan.md`](Update-14-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP14-S1 | Materials CSV | Implemented | On main. Smoke written. Pending wp-env |
| UP14-S2 | Workflow CSV | Implemented | On main. Smoke written. Pending wp-env |
| UP14-S3 | Product CSV | Implemented | On main. Smoke written. Pending wp-env |
| UP14-S4 | Budget checkbox on Add material | Implemented | Smoke written. Pending wp-env |
| UP14-S5 | Collapsed Shipment panel | Not started | Independent of the files |
| UP14-S6 | Trays above Make, including Internal | Not started | Extends Package 8. Unblock on the order stays |
| UP14-S7 | Instruction bullets and editor preview | Not started | BUG-006. No allowlist change |
| UP14-S8 | Output per run | Not started | Schema 1.25.0. After S2 for the CSV column |
| UP14-S9 | Pack workflow override | Not started | Schema 1.26.0. Overturns Package 6 site-wide Pack |
| UP14-S10 | Mark done stays in place | Not started | Make and Post-ship. Pack already does this |
| UP14-S11 | Finished pack leaves the Pack board | Not started | Stays on the Post-ship board |
| UP14-S12 | Release the last tray on purpose | Not started | **Release anyway**. **Release selected** still keeps one |

**Package 14:** planning still open for further operator items. UP14-S1, UP14-S2, and UP14-S3 are on main. UP14-S4 is implemented on its branch and is not merged. Schema stays **1.24.0** until UP14-S8 (**1.25.0**) and UP14-S9 (**1.26.0**). Suggested releases **0.35.0**–**0.46.0** are not tagged.

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
- Local site **ordermachine** is this plugin folder. UI appears there when a sprint branch is checked out. wp-env smokes wait until the operator runs the final package prompt.
- Specs: [`02-Update-Data-Model.md`](02-Update-Data-Model.md), [`03-Update-Catalog-Import-Export.md`](03-Update-Catalog-Import-Export.md), [`05-Update-Material-Budget-On-Create.md`](05-Update-Material-Budget-On-Create.md), [`06-Update-Order-Detail-Shipment-And-Trays.md`](06-Update-Order-Detail-Shipment-And-Trays.md), [`07-Update-Instruction-List-Preview.md`](07-Update-Instruction-List-Preview.md), [`08-Update-Workflow-Output-Per-Run.md`](08-Update-Workflow-Output-Per-Run.md), [`09-Update-Pack-Workflow-Override.md`](09-Update-Pack-Workflow-Override.md), [`10-Update-Mark-Done-Scroll.md`](10-Update-Mark-Done-Scroll.md), [`11-Update-Pack-Board-After-Pack.md`](11-Update-Pack-Board-After-Pack.md), [`12-Update-Release-Last-Tray.md`](12-Update-Release-Last-Tray.md).

---

## UP14-S1 — Materials CSV

- **Status:** implemented. On main.
- **Behaviour:** Materials list exports, downloads a template, previews, and imports. Create goes through `SOM_Materials::create`. Stock stays 0, no stock log, and `source_product_id` is not set. Export omits materials that have `source_product_id` set. A taken name becomes `Name (2)`. An unknown or ambiguous supplier fails that row only.
- **Helper:** `SOM_Catalog_Import` holds the name suffix and the preview token. Workflow import is UP14-S2. Product import is UP14-S3.
- **Smoke:** `tests/sprint-up14-s1-smoke.php` — written. Pending wp-env. Do not run it until the package wp-env prompt. `php -l` only in the implementation chat.

## UP14-S2 — Workflow CSV

- **Status:** implemented. On main.
- **Behaviour:** Workflows list exports, downloads a template, previews, and imports. One row per step; rows with the same name are one template, sorted by `step_order`. Save goes through `SOM_Workflows::validate_submission` and `save_steps`. A taken name becomes `Name (2)`. A Post-ship file with no Package arrived step writes no template. An unknown or ambiguous batch group key fails that workflow and does not create a group. Header cells that disagree fail that workflow. A task label containing `|` fails that workflow. `script_config` is not a column and is stored empty. `output_per_run` is not a column. If step insert fails after the template row exists, the partial template is deleted.
- **Smoke:** `tests/sprint-up14-s2-smoke.php` — written. Pending wp-env. Do not run it until the package wp-env prompt. `php -l` only in the implementation chat.

## UP14-S3 — Product CSV

- **Status:** implemented. On main.
- **Behaviour:** Products list exports, downloads a template, previews, and imports. One row per recipe line; rows with the same name are one product. A product with no recipe is one row with the material and quantity empty. Create goes through `SOM_Products::create`, then `save_recipe`. A taken name becomes `Name (2)` and keeps the file SKU. When another product already has that SKU, the product is still created and the result lists a warning. Listings are not created. An internal product gets a new output material (stock 0). `linked_material_id` is not pointed at an existing material. An unknown or ambiguous material, make workflow, Post-ship workflow, or package fails that product and does not create the missing record. Header cells that disagree fail that product. If recipe save fails after the product exists, the new product and its new output material are deleted. `pack_workflow_name` is not a column.
- **Smoke:** `tests/sprint-up14-s3-smoke.php` — written. Pending wp-env. Do not run it until the package wp-env prompt. `php -l` only in the implementation chat.

## UP14-S4 — Budget checkbox on Add material

- **Status:** implemented. Not merged.
- **Behaviour:** Add material has an unchecked checkbox **Create a material budget**. Edit material does not. When the box is clear, the material save is unchanged. When it is checked, after the material row exists, `SOM_Budgets::create` adds one active material budget named like the material, funding `material_cost`, funding value empty, target reserve empty, balance £0, notes empty, and no workflow links (every workflow). One budget per material stays in force. If the budget insert fails, the material stays and the material screen shows the error. The materials CSV does not create budgets.
- **Smoke:** `tests/sprint-up14-s4-smoke.php` — written. Pending wp-env. Do not run it until the package wp-env prompt. `php -l` only in the implementation chat.

## UP14-S5 — Collapsed Shipment panel

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s5-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S6 — Trays above Make, including Internal

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s6-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S7 — Instruction bullets and editor preview

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s7-smoke.php` — not written. Pending wp-env after implementation.
- **Bug:** BUG-006 stays Open until the wp-env pass.

## UP14-S8 — Output per run

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s8-smoke.php` — not written. Pending wp-env after implementation.
- **Schema:** 1.25.0 in this sprint only.

## UP14-S9 — Pack workflow override

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s9-smoke.php` — not written. Pending wp-env after implementation.
- **Schema:** 1.26.0 in this sprint only.

## UP14-S10 — Mark done stays in place

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s10-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S11 — Finished pack leaves the Pack board

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s11-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S12 — Release the last tray on purpose

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s12-smoke.php` — not written. Pending wp-env after implementation.
