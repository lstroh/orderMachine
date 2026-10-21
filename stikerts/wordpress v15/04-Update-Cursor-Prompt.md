# Update Package 14 — Cursor kickoff prompt

*Use when the operator asks to start a sprint. Do not implement from planning alone.*

## Locked model

- CSV export, template, preview, and import for materials (S1), workflows (S2), and products (S3).
- Name collision creates `Name (2)`, then `Name (3)`. Never update an existing row.
- Missing or ambiguous references fail that record only. Do not create suppliers, packages, batch groups, materials, or workflows as a side effect.
- No stock movement on the catalog import. No listings. No `script_config`. S1–S7 do not change the schema.
- Post-ship import still requires a Package arrived step.
- S4: Add material checkbox, unchecked, creates one active material budget. Keep the material if the budget insert fails.
- S5: Shipment disclosure on every order, closed on each visit. Summary shows status and tracking number. Do not remove the form.
- S6: Move the Trays panel to immediately above Make. Internal orders require a tray when the make template has Uses trays. Customer orders still skip an internal component line. Do not remove Unblock on the order. Do not add Unblock on the boards.
- S7 (BUG-006): show disc bullets and decimal numbers on instruction lists. Move the preview box to directly under the textarea. Do not change the Markdown allowlist. Preview stays a button.
- S1–S7: no schema. DB stays **1.24.0** until S8.
- S8: `workflow_templates.output_per_run` (default 1). Schema **1.25.0**. Internal completion credits Produce N × that number. Recipe quantity unchanged. Customer orders do not credit stock. Add the column to the S2 workflow CSV.
- S9: `products.pack_workflow_template_id`. Schema **1.26.0**. Sellable product Pack override. Blank uses the site default. Primary product decides at bind time. Already-bound orders stay. Internal orders stay unbound. Add `pack_workflow_name` to the S3 product CSV. Do not add a pack picker on the order.
- S10: Make and Post-ship Mark done use the Pack in-place reload. Make sends `order_item_id`. Do not change Unblock or other forms. Pack Mark done stays as it is.
- S11: The Pack board drops an order whose pack steps are all done. Orders still in pack, and orders with no pack progress, stay. Do not change the Post-ship board or hide the Pack section on the order.
- S12: **Release anyway** on the order Trays panel frees checked trays including the last one. **Release selected** still keeps one. Do not make **Unblock step** release trays. After the last tray is freed, Mark done stays blocked until a tray is assigned or **Unblock step** is used.
- Plain PHP admin. SemVer `0.x.y` alpha. No Version bump.

## Rules

- Read `.cursor/rules/order-machine-architecture.mdc`, [`01-Update-Overview.md`](01-Update-Overview.md), [`Update-14-Sprint-Plan.md`](Update-14-Sprint-Plan.md), and the sprint spec (`03` for S1–S3, `05` for S4, `06` for S5–S6, `07` for S7, `02` and `08` for S8, `02` and `09` for S9, `10` for S10, `11` for S11, `12` for S12).
- One sprint per chat. Branch from latest `origin/main`. Do not continue a previous sprint branch.
- Do not start the next sprint.
- Do not run `npx @wordpress/env`.
- The Local site **ordermachine** uses this plugin folder. After the code is saved here, the operator can reload that site’s wp-admin and try the screen. Implementation still opens a PR and does not merge. Automated smokes wait for the package wp-env prompt.

## Sprints

| Sprint | Focus |
|---|---|
| UP14-S1 | Materials CSV + shared suffix / preview helper |
| UP14-S2 | Workflow CSV (after S1 is on main) |
| UP14-S3 | Product CSV (after S2 is on main) |
| UP14-S4 | Add material budget checkbox (after S3 is on main) |
| UP14-S5 | Collapsed Shipment panel (after S4 is on main) |
| UP14-S6 | Trays above Make, including Internal (after S5 is on main) |
| UP14-S7 | Instruction bullets and editor preview, BUG-006 (after S6 is on main) |
| UP14-S8 | Output per run (after S7 is on main; CSV column needs S2) |
| UP14-S9 | Pack workflow override (after S8 is on main; CSV column needs S3) |
| UP14-S10 | Mark done stays in place (after S9 is on main) |
| UP14-S11 | Finished pack leaves the Pack board (after S10 is on main) |
| UP14-S12 | Release the last tray on purpose (after S11 is on main) |

The operator is still adding items. Do not start a sprint until they ask.

## Kickoff snippet — UP14-S1 (copy into the implementation chat)

```
Implement Order Machine UP14-S1 only (Package 14). Do not start UP14-S2.

Branch from latest origin/main. Do not continue a previous sprint branch.

Read:
- .cursor/rules/order-machine-architecture.mdc
- stikerts/wordpress v15/01-Update-Overview.md
- stikerts/wordpress v15/03-Update-Catalog-Import-Export.md (§1–§4 and §7–§9)
- stikerts/wordpress v15/Update-14-Sprint-Plan.md (locked decisions and the UP14-S1 section)

Locked behaviour:
- Materials list gets Export CSV, Download template, Preview, and Import.
- Columns: name, unit, low_stock_threshold, unit_cost, preferred_supplier, is_active.
- Create through SOM_Materials::create. Stock stays 0. No stock log. Do not set source_product_id.
- Export omits materials that have source_product_id set.
- If the name already exists (case-insensitive), create Name (2), then Name (3). Never update.
- Unknown or ambiguous supplier fails that row only. Good rows still save.
- Comma or tab, UTF-8 BOM stripped, preview token then commit, same idea as order import.
- Put the name-suffix and preview-token helper where UP14-S2 and UP14-S3 can reuse it. Do not build the workflow or product importers.

Out of scope:
- Workflow CSV, product CSV, and the Add material budget checkbox.
- Stock, listings, script_config, schema changes, Version / SOM_VERSION bump, release tag.
- Do not run npx @wordpress/env. Do not rewrite the eleven stale smokes.

Tests:
- Write tests/sprint-up14-s1-smoke.php in the style of tests/sprint-up12-s7-smoke.php (ABSPATH guard, run only via wp eval-file).
- Assert: new material; second import of the same name becomes Name (2); unknown supplier fails that row and the good row still saves; stock stays 0; a made-in-house material is absent from export.
- php -l on changed PHP. Do not run the smoke (no wp-env in this chat).

Docs and PR:
- Unreleased changelog line. Update stikerts/wordpress v15/Update-14-Sprint-Progress.md and mark the S1 smoke pending wp-env.
- Short notes in USER-REFERENCE.md and FEATURES-AND-TESTING.md.
- Open a focused PR. Do not merge it.

The Local site ordermachine serves this plugin folder. Say that in the PR so the operator can reload wp-admin and try Materials import after the branch is checked out. Manual UI is the operator’s. Automated smokes wait for the package wp-env prompt.
```
