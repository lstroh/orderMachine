# Plugin Update Package 14 — Overview

*Update set · Catalog CSV, a material-budget checkbox, a collapsed Shipment panel, and tray rules for Internal jobs. Planning open for further operator items. Settled items below are from 2026-10-19. Self-contained.*

---

## Assumption

- Packages **7–13** are on `origin/main`. Plugin **0.34.0** (schema **1.24.0**). Package 13 is released: hand-entered eBay/Amazon orders, order file import, listing report import.
- Standing architecture: `.cursor/rules/order-machine-architecture.mdc` and docs under `stikerts/wordpress/` plus the v8–v14 update packages.
- UP14-S1 through UP14-S7 add **no tables and no columns**. UP14-S8 adds `workflow_templates.output_per_run` and bumps the schema to **1.25.0**. UP14-S9 adds `products.pack_workflow_template_id` and bumps the schema to **1.26.0**.
- Plain PHP admin. SemVer stays on **`0.x.y`**. Sprint work adds an Unreleased changelog line and does not bump `Version` / `SOM_VERSION`.

## Operator requests

1. **Import and export** for materials, workflows, and products.
2. **On Add material**, an option to create that material’s budget in the same save.
3. **Shipment** on the order starts collapsed, on every order.
4. **Trays** sit above Make. An Internal job requires a tray when its make workflow has **Uses trays**.
5. A make workflow can say how many units one Internal run adds to the linked material.
6. A sellable product can override the site Pack workflow, the same way it can override Post-ship.
7. Mark done on Make and Post-ship stays at the same place on the page, the way Pack already does.
8. An order leaves the Pack board once every pack step is done. It stays on the Post-ship board.
9. You can release the last tray on purpose, even when the order still requires one.

## Settled behaviour

| Topic | Decision |
|---|---|
| File | CSV on each list page (Materials, Workflows, Products). Same columns for export, template, and import. Comma or tab. UTF-8 BOM stripped. Preview, then commit. |
| What is in the file | Form fields plus the child rows that define the record. Column lists are in [`03-Update-Catalog-Import-Export.md`](03-Update-Catalog-Import-Export.md). |
| Name already in Order Machine | **Create another record.** Never update. The new name is `Name (2)`, then `Name (3)`, and so on. |
| Missing supplier, material, workflow, package, or batch group | That record fails and is listed. The importer does not create those related records. |
| Order of files | Materials, then workflows, then products, when a product file names materials and workflows. |
| Stock, stock log, purchase history, value on hand | Stay on the material screen. A new material starts at stock 0. |
| Unit cost | Stored on the new material. It does not write a stock log. |
| Made-in-house output materials | Omitted from the material file. An internal product import creates its output material through the normal product save. |
| Listings, product step notes, workflow material goals, budget links | Stay out of these files. |
| `script_config` | Not a column. Package 9 disables it and workflow clone does not copy it. Copy command is the step field that travels. |
| Post-ship import | Same rule as Save: a Package arrived step is required, and a failed workflow writes nothing. |
| Add-material budget | Checkbox, unchecked. Creates one active material budget (funding **Material cost (auto)**), named like the material, balance £0, no target reserve, every workflow in scope. |
| Budget save failure | The material stays. The screen shows the error. |
| Budgets from CSV | Not created by the material file. |
| Shipment panel | Collapsed on every order, every visit. Heading shows status and tracking number when a shipment exists. The form inside is unchanged. Spec [`06-Update-Order-Detail-Shipment-And-Trays.md`](06-Update-Order-Detail-Shipment-And-Trays.md). |
| Trays placement | The Trays panel moves to immediately above Make. |
| Internal trays | An Internal order requires at least one held tray before Mark done and board advance when its make template has **Uses trays**. This extends Package 8, which skipped internal products. A customer order still skips an internal component line. **Unblock** on the order still bypasses the tray gate. |
| Instruction lists | Bullets and numbers are visible in the instruction box. Preview opens directly under the textarea. BUG-006. Spec [`07-Update-Instruction-List-Preview.md`](07-Update-Instruction-List-Preview.md). |
| Output per run | Make workflow field, default **1**. Internal completion adds Produce N × that number to the linked material. Recipe stays at Produce N. Unit cost is input cost ÷ units added. Customer orders do not credit stock. Spec [`08-Update-Workflow-Output-Per-Run.md`](08-Update-Workflow-Output-Per-Run.md). |
| Pack override | Sellable product field. Blank uses Settings → Default Pack. The primary product decides when the order is bound. Package 6 had no per-product pack choice; this request adds one. Spec [`09-Update-Pack-Workflow-Override.md`](09-Update-Pack-Workflow-Override.md). |
| Mark done scroll | Make and Post-ship use the Pack in-place reload. Spec [`10-Update-Mark-Done-Scroll.md`](10-Update-Mark-Done-Scroll.md). |
| Pack board | An order leaves once every pack step is done. It stays on the Post-ship board. Spec [`11-Update-Pack-Board-After-Pack.md`](11-Update-Pack-Board-After-Pack.md). |
| Release last tray | **Release selected** still keeps one. **Release anyway** frees the last tray and writes an order note. Spec [`12-Update-Release-Last-Tray.md`](12-Update-Release-Last-Tray.md). |

## Local QA site

The Local site **ordermachine** runs this plugin folder. There is no separate deploy.

- **0.34.0 cannot do this yet.** Materials, workflows, and products have no CSV. Add material does not create a budget. A budget can still be added by hand: **Budgets → Add budget → Type: Material → pick the material.**
- After a sprint branch is checked out in this folder, reload the Local site’s wp-admin and the new screen is there. Start the site in Local first.
- Implementation chats still do not run `npx @wordpress/env`. Automated smokes wait for the package’s final wp-env prompt. The operator can click through the Local site as soon as the branch is in this folder.

## What's in this update

1. Materials export, template, preview, and import (UP14-S1).
2. Workflows export, template, preview, and import, including steps (UP14-S2).
3. Products export, template, preview, and import, including recipes (UP14-S3).
4. Add material checkbox that creates the material budget (UP14-S4).
5. Shipment panel starts collapsed (UP14-S5).
6. Trays panel above Make, and Internal jobs honour **Uses trays** (UP14-S6).
7. Instruction bullets show on the order, and Preview opens under the instruction box (UP14-S7, BUG-006).
8. Make workflow **Output per run** multiplies the Internal material credit (UP14-S8).
9. Pack workflow override on a sellable product (UP14-S9).
10. Mark done on Make and Post-ship stays in place (UP14-S10).
11. Finished pack leaves the Pack board (UP14-S11).
12. Release the last tray on purpose (UP14-S12).

Further operator items can still be appended before implementation starts.

## Recommended build order

1. **UP14-S1** — Materials file, and the shared name-suffix + preview/commit helper.
2. **UP14-S2** — Workflow file. Reuses the S1 helper.
3. **UP14-S3** — Product file. Reuses the S1 helper. Names materials and workflows that already exist.
4. **UP14-S4** — Budget checkbox. Independent of the files.
5. **UP14-S5** — Collapsed Shipment panel. Independent of the files.
6. **UP14-S6** — Tray placement and Internal tray requirement. Independent of S5.
7. **UP14-S7** — Instruction list markers and preview placement. Independent.
8. **UP14-S8** — Output per run. After S2, so the workflow CSV can gain the column. Schema **1.25.0**.
9. **UP14-S9** — Pack override on a sellable product. After S3 and S8. Schema **1.26.0**.
10. **UP14-S10** — Mark done stays in place on Make and Post-ship. Independent.
11. **UP14-S11** — Finished pack leaves the Pack board. Independent.
12. **UP14-S12** — Release the last tray on purpose. Independent.

## Release

Suggest **0.35.0** after S1, **0.36.0** after S2, **0.37.0** after S3, **0.38.0** after S4, **0.39.0** after S5, **0.40.0** after S6, **0.41.0** after S7, **0.42.0** after S8, **0.43.0** after S9, **0.44.0** after S10, **0.45.0** after S11, **0.46.0** after S12. Do not tag until the operator asks for a release.
