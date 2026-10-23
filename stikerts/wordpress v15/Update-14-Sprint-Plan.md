# Update Package 14 — Sprint Plan

*Planning open for further operator items. Settled items are from 2026-10-19. Specs in this folder. Baseline: plugin **0.34.0**, schema **1.24.0**, `origin/main`.*

**Sequencing:** Materials file first (shared suffix + preview helper) → workflow file → product file → Add-material budget checkbox → collapsed Shipment panel → tray placement and Internal tray rule → instruction list markers and editor preview → output per run (after the workflow file, schema **1.25.0**) → pack override (after the product file and S8, schema **1.26.0**) → Mark done stays in place → finished pack leaves the Pack board → release the last tray on purpose. S4, S5, S6, S7, S10, S11, and S12 do not depend on the files. S8 depends on S2 only for the extra CSV column. S9 depends on S3 only for the extra CSV column.

## Locked decisions (operator, 2026-10-19)

- CSV import and export for **materials**, **workflows**, and **products**. Preview, then commit. Comma or tab. Same columns for export, template, and import.
- A name that already exists **creates** `Name (2)`, then `Name (3)`, and so on. Import never updates.
- A missing or ambiguous supplier, material, workflow, package, or batch group **fails that record**. Those records are not created by the importer.
- File contents are the form fields plus recipe lines and workflow steps. Stock, listings, step overrides, material goals, budget links, and `script_config` stay out. `script_config` stays out because Package 9 disables it and workflow clone does not copy it — this package does not bring it back.
- Post-ship import uses the existing Package arrived rule (BUG-005). A failed workflow or product writes nothing for that record.
- Add material checkbox creates one active **material** budget, unchecked by default. Material is kept if the budget insert fails.
- Shipment on order detail is a collapsed disclosure on every order, closed on each visit. The heading shows status and tracking number when a shipment exists. The form inside is unchanged.
- The Trays panel moves to immediately above Make.
- **Package 8 change:** an Internal order requires a held tray before Mark done and board advance when its make template has **Uses trays**. Package 8 skipped every internal product. A customer order still skips an internal component line. **Unblock** on the order detail still bypasses the tray gate. Unblock on boards and the Trays overview page stay deferred.
- **BUG-006:** instruction `<ul>` markers are visible. Editor Preview opens directly under the textarea. No new Markdown formats and no allowlist change (Package 12).
- **Output per run** on make workflows. Internal completion credits Produce N × that number. Default 1. Recipe quantity unchanged. Schema **1.25.0** in UP14-S8.
- **Pack override** on a sellable product. This overturns the Package 6 rule that Pack is site-wide only. Blank still uses Settings → Default Pack. The primary product decides at bind time. Orders already bound stay put. Schema **1.26.0** in UP14-S9.
- **Mark done** on Make and Post-ship uses the Pack in-place reload, so the page does not open at the top. The Ship step inside Pack already does this. Unblock and other forms still post normally.
- **Pack board:** an order leaves once every pack step is done. It stays on the Post-ship board. This narrows the Package 6 rule that the Pack board kept open orders until the order itself was complete. Orders still in pack, and orders with no pack progress, stay.
- **Release anyway** on the order Trays panel frees the trays you checked, including the last one, while the order still requires a tray. **Release selected** still keeps one. **Unblock step** still does not release a tray. This adds a bypass beside the Package 8 keep-one rule; it does not remove that rule.
- No Version bump inside a sprint. Unreleased changelog line only. S1–S7, S10, S11, and S12 do not change the schema.
- Deferred items from the planning brief stay deferred (Product Costing packaging line, Pack Copy UI, Unblock on boards, Trays overview, hard-delete of script dispatch, rewriting the eleven stale smokes, customer orders that contain only internal products).

---

## 1. Open items

| # | Item | Blocks | Decision |
|---|---|---|---|
| O1 | File columns | UP14-S1–S3 | **Settled** — [`03-Update-Catalog-Import-Export.md`](03-Update-Catalog-Import-Export.md) |
| O2 | Name collision | UP14-S1–S3 | **Settled** — create `Name (2)`, `Name (3)`, … Never update |
| O3 | Missing references | UP14-S1–S3 | **Settled** — fail the record |
| O4 | Budget checkbox | UP14-S4 | **Settled** — [`05-Update-Material-Budget-On-Create.md`](05-Update-Material-Budget-On-Create.md) |
| O5 | `script_config` column | UP14-S2 | **Settled by conflict** — omitted. Package 9 / workflow clone already drop it |
| O6 | Product SKU when the name is suffixed | UP14-S3 | **Settled** — keep the file SKU and warn when another product already uses it |
| O7 | Shipment disclosure | UP14-S5 | **Settled** — [`06-Update-Order-Detail-Shipment-And-Trays.md`](06-Update-Order-Detail-Shipment-And-Trays.md) §1 |
| O8 | Tray placement and Internal requirement | UP14-S6 | **Settled** — `06` §2–§3. Extends Package 8. Unblock on the order stays |
| O9 | Instruction bullets and preview | UP14-S7 | **Settled** — [`07-Update-Instruction-List-Preview.md`](07-Update-Instruction-List-Preview.md). Preview stays a button and opens under the textarea |
| O10 | Output per run | UP14-S8 | **Settled** — Produce N × workflow number. [`08-Update-Workflow-Output-Per-Run.md`](08-Update-Workflow-Output-Per-Run.md). Schema [`02-Update-Data-Model.md`](02-Update-Data-Model.md) |
| O11 | Pack override | UP14-S9 | **Settled** — same pattern as post-ship. Overturns Package 6 “Pack is site-wide only.” [`09-Update-Pack-Workflow-Override.md`](09-Update-Pack-Workflow-Override.md) |
| O12 | Mark done scroll | UP14-S10 | **Settled** — Make and Post-ship stay put, like Pack. [`10-Update-Mark-Done-Scroll.md`](10-Update-Mark-Done-Scroll.md) |
| O13 | Pack board after pack | UP14-S11 | **Settled** — leave the Pack board when every pack step is done. Stay on Post-ship. [`11-Update-Pack-Board-After-Pack.md`](11-Update-Pack-Board-After-Pack.md) |
| O14 | Release last tray | UP14-S12 | **Settled** — **Release anyway** bypasses keep-one. **Release selected** unchanged. [`12-Update-Release-Last-Tray.md`](12-Update-Release-Last-Tray.md) |

No open planning questions. Further operator items can still be appended.

---

## 2. Spec ↔ codebase

| Area | Today | Package 14 |
|---|---|---|
| Materials / Workflows / Products lists | No catalog CSV | Export, template, preview, import |
| Order / listing CSV | `SOM_Order_Import` remember/recall | Catalog import uses the same preview-token pattern. It does not insert orders or listings |
| Names | Not unique | Suffix on collision. No new unique index |
| Material create | `SOM_Materials::create`, stock 0 | CSV calls `create`. No `source_product_id` |
| Workflow save | `validate_submission` + step write; Post-ship needs `package_arrived` | Import calls that path |
| Product create | `SOM_Products::create` + `save_recipe`; internal calls `ensure_linked_material` | Import calls that path |
| Clone | `Name (copy)`, product SKU blank | Unchanged. Import suffix is ` (2)` so it is distinct from clone |
| Add material | No budget | Optional `SOM_Budgets::create` type `material` |
| Shipment panel | Always expanded | `<details>` closed by default. Summary shows status and tracking |
| Trays panel | Below Make | Immediately above Make |
| `order_requires_trays` | Skips `is_internal` products | Internal-channel orders count the make template. Customer orders still skip internal component lines |
| Instruction lists | `<ul>` rendered, markers hidden by wp-admin `ul { list-style: none }` | Disc and decimal styles on `.som-step-instructions-body`. Preview box directly under the textarea |
| Internal credit | Produce N quantity, one `production_output` row | Produce N × make template `output_per_run` (default 1). Unit cost = input COGS ÷ units |
| Pack bind | Settings default only, at order create | Primary product’s Pack override when usable, else the site default. Repair uses the same rule |
| Mark done | Pack reloads in place. Make and Post-ship redirect to the top | Make and Post-ship use the Pack reload. Make sends `order_item_id` |
| Pack board | Incomplete orders with a pack template stay, including after pack is finished | Leave when every pack step is done. Post-ship board unchanged |
| Last tray | Manual release cannot free the last tray while the order requires one and is open | **Release selected** still cannot. **Release anyway** can, and writes an order note |
| Schema | **1.24.0** | **1.25.0** in UP14-S8, **1.26.0** in UP14-S9 |

---

## 3. Sprints

### UP14-S1 — Materials CSV

| Work | Detail |
|---|---|
| Helper | Name suffix and preview token, reusable by S2 and S3 |
| IO | Materials list: Export CSV, Download template, Preview, Import. Columns in `03` §4 |
| Create | `SOM_Materials::create` only. Stock remains 0. Export omits `source_product_id` materials |
| Tests | `tests/sprint-up14-s1-smoke.php` |
| Docs | USER-REFERENCE Materials, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** a material CSV creates new materials, a second pass of the same names creates `Name (2)`, a bad supplier fails only that row, and export skips made-in-house output materials.

**Suggested SemVer after merge:** `0.35.0`.

---

### UP14-S2 — Workflow CSV

Depends on the UP14-S1 suffix and preview helper.

| Work | Detail |
|---|---|
| IO | Workflows list: same four actions. One row per step. Columns in `03` §5 |
| Save | Editor validation, including Package arrived. Partial template is deleted on failure |
| Tests | `tests/sprint-up14-s2-smoke.php` |
| Docs | USER-REFERENCE Workflows, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** a workflow file creates a template and its steps, a name collision suffixes, and a Post-ship file without Package arrived leaves no new template.

**Suggested SemVer after merge:** `0.36.0`.

---

### UP14-S3 — Product CSV

Depends on the UP14-S1 helper. A useful file names materials and workflows that already exist (typed, or brought in by S1 and S2).

| Work | Detail |
|---|---|
| IO | Products list: same four actions. One row per recipe line. Columns in `03` §6 |
| Save | `SOM_Products::create` then `save_recipe`. Internal product gets a new output material. Roll back a half product |
| SKU | Kept from the file. Warning when the SKU is already used. No listing rows |
| Tests | `tests/sprint-up14-s3-smoke.php` |
| Docs | USER-REFERENCE Products, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** a product file creates the product and recipe, a missing material fails that product only, and an internal product does not reuse an existing output material.

**Suggested SemVer after merge:** `0.37.0`.

---

### UP14-S4 — Budget checkbox on Add material

No dependency on S1–S3.

| Work | Detail |
|---|---|
| UI | Add material checkbox, unchecked. Spec `05` |
| Create | After a successful material insert, `SOM_Budgets::create` type `material` when the box is checked |
| Failure | Material remains; error shown |
| Tests | `tests/sprint-up14-s4-smoke.php` |
| Docs | USER-REFERENCE, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** checking the box on a new material yields one active material-cost budget at £0, and leaving it unchecked yields none.

**Suggested SemVer after merge:** `0.38.0`.

---

### UP14-S5 — Collapsed Shipment panel

No dependency on S1–S4.

| Work | Detail |
|---|---|
| UI | Order detail Shipment section is a disclosure, closed on each load. Spec `06` §1 |
| Summary | Status label and tracking number when a shipment exists |
| Scope | Every order, including Internal. Save and push tracking stay inside the panel |
| Tests | `tests/sprint-up14-s5-smoke.php` |
| Docs | USER-REFERENCE, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** opening an order shows Shipment closed, and a saved tracking number is visible in the heading without opening the panel.

**Suggested SemVer after merge:** `0.39.0`.

---

### UP14-S6 — Trays above Make, including Internal

No dependency on S5. Extends Package 8; does not remove Unblock.

| Work | Detail |
|---|---|
| UI | Move `#som-trays` to immediately above Make. Spec `06` §2 |
| Rule | `order_requires_trays()` is true for an Internal order whose make template has **Uses trays**. Spec `06` §3 |
| Customer orders | An internal component line still does not, by itself, require a tray |
| Tests | `tests/sprint-up14-s6-smoke.php` |
| Docs | USER-REFERENCE, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** the Trays panel is above Make, an Internal job with **Uses trays** cannot Mark done until a tray is held, and a customer order is unchanged when only an internal component line uses trays.

**Suggested SemVer after merge:** `0.40.0`.

---

### UP14-S7 — Instruction bullets and editor preview (BUG-006)

No dependency on S1–S6. Does not change the Markdown allowlist.

| Work | Detail |
|---|---|
| CSS | Disc bullets and decimal numbers on `.som-step-instructions-body` lists. Spec `07` |
| Preview | Rendered box directly under the textarea. Still opened with **Preview** and closed with **Hide preview** |
| Tests | `tests/sprint-up14-s7-smoke.php` |
| Docs | USER-REFERENCE, FEATURES-AND-TESTING, CHANGELOG Unreleased. BUG-006 stays Open until the wp-env pass |

**Done when:** a `- ` list shows bullets on the order and in Preview, and Preview opens under the instruction box.

**Suggested SemVer after merge:** `0.41.0`.

---

### UP14-S8 — Output per run

Depends on UP14-S2 for the workflow CSV column. Credit and the editor field do not depend on S2.

| Work | Detail |
|---|---|
| Schema | `workflow_templates.output_per_run`, default 1. DB **1.25.0**. Spec `02` |
| Editor | Make templates only, beside Uses trays. 1–10000. Spec `08` |
| Credit | `credit_output_for_order()` uses Produce N × yield. Recipe reservation unchanged |
| CSV | Optional `output_per_run` on the S2 workflow file |
| Clone | Copies the number |
| Tests | `tests/sprint-up14-s8-smoke.php` |
| Docs | USER-REFERENCE, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** a make workflow set to 12, produced 3 times, adds 36 to the linked material, and a workflow left at 1 still adds the Produce N quantity.

**Suggested SemVer after merge:** `0.42.0`.

---

### UP14-S9 — Pack workflow override

Depends on UP14-S8 for schema **1.25.0** → **1.26.0**, and on UP14-S3 for the product CSV column. The product field and bind rule do not depend on S3.

| Work | Detail |
|---|---|
| Schema | `products.pack_workflow_template_id`, nullable. DB **1.26.0**. Spec `02` |
| Product | Sellable products only. Blank = site default. Spec `09` |
| Bind | `SOM_Pack::bind_on_create` and repair use the primary product’s override when that template is an active Pack template with steps, else the site default |
| Already bound | Leave the order’s pack template alone |
| Internal | Still no pack bind. No field on internal products |
| Clone | Copy the override |
| CSV | Optional `pack_workflow_name` on the S3 product file |
| Tests | `tests/sprint-up14-s9-smoke.php` |
| Docs | USER-REFERENCE, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** a product with a Pack override binds that template on a new customer order, a product without one still binds Default Pack, and an order that already has a pack template is not moved.

**Suggested SemVer after merge:** `0.43.0`.

---

### UP14-S10 — Mark done stays in place

No dependency on S1–S9. No schema.

| Work | Detail |
|---|---|
| Make | Mark done form uses `data-som-advance-step` and sends `order_item_id`. Spec `10` |
| Post-ship | Same attribute, no item id |
| JS | Existing reload path. Include `order_item_id` in the JSON body when the form has one |
| Pack | Unchanged, including the Ship step |
| Tests | `tests/sprint-up14-s10-smoke.php` |
| Docs | USER-REFERENCE, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** Mark done on a make step and on a post-ship step reloads in place, and a failed advance shows an alert without leaving the page.

**Suggested SemVer after merge:** `0.44.0`.

---

### UP14-S11 — Finished pack leaves the Pack board

No dependency on S1–S10. No schema.

| Work | Detail |
|---|---|
| Pack board | `SOM_Pack::query_board` omits an order that has pack progress and no pack step still open. Spec `11` |
| Still listed | A pack step still open, including Waiting for make and Held. An order with no pack progress |
| Post-ship board | Unchanged |
| Order page | Finished Pack section still shows |
| Tests | `tests/sprint-up14-s11-smoke.php` |
| Docs | USER-REFERENCE, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** marking the last pack step done removes the order from the Pack board and leaves it on the Post-ship board.

**Suggested SemVer after merge:** `0.45.0`.

---

### UP14-S12 — Release the last tray on purpose

No dependency on S1–S11. No schema. S6’s Internal tray rule does not have to be on main first; the button uses whatever `order_requires_trays()` returns.

| Work | Detail |
|---|---|
| Button | **Release anyway** on the order Trays panel, beside **Release selected**. Spec `12` |
| Keep-one | **Release selected** unchanged |
| After | Mark done stays blocked until a tray is assigned or **Unblock step** is used |
| Unblock step | Does not release trays |
| Audit | `release_reason` `manual_override` and an order note |
| Tests | `tests/sprint-up14-s12-smoke.php` |
| Docs | USER-REFERENCE, FEATURES-AND-TESTING, CHANGELOG Unreleased |

**Done when:** **Release anyway** frees the only held tray on an open order that requires trays, and **Release selected** still refuses that same case.

**Suggested SemVer after merge:** `0.46.0`.

---

## 4. Out of scope

- Updating an existing material, workflow, or product from a file
- Importing on-hand stock, stock logs, purchase history, or value on hand
- Creating suppliers, shipping packages, batch groups, materials, or workflows to satisfy a reference
- Listings (listing report import already exists), product step overrides, workflow material goals, budget links
- `script_config` / local script steps
- A budget column on the material CSV, manual budgets, and budget balances
- Supplier, shipping-package, or budget files (a later package if asked)
- Schema, version bump, release tag
- The eleven stale smokes, and the deferred items listed in the locked decisions
- Hiding Shipment on Internal orders, and refusing Save shipment there
- Removing **Unblock** on the order, or adding Unblock on the boards
- A trays overview page
- Requiring a tray on a customer order only because an internal component’s workflow uses trays
- New Markdown formats, images, or a change to the instruction HTML allowlist
- Leaving Preview open on every visit
- A different yield per product when those products share one make workflow
- Changing recipe consumption to match the output count
- Crediting stock on a customer order
- A pack-template picker on the order itself
- Rebinding orders that already have a pack workflow when the product override changes
- Changing Unblock, checklists, or other order forms to the in-place reload
- Board drag-and-drop advance
- A finished column on the Pack board
- Hiding the Pack section on the order after the steps are done
- Making **Unblock step** release trays
- A tray release control on the boards

---

## 5. Checklist

### UP14-S1
- [x] Suffix helper + preview token
- [x] Materials export, template, preview, import
- [x] Smoke + docs + Unreleased changelog
- [x] Progress file: smoke pending wp-env

### UP14-S2
- [x] Workflow file uses the S1 helper
- [x] Steps + Package arrived rule
- [x] Smoke + docs + Unreleased changelog
- [x] Progress file: smoke pending wp-env

### UP14-S3
- [x] Product file uses the S1 helper
- [x] Recipe + internal output material
- [x] Smoke + docs + Unreleased changelog
- [x] Progress file: smoke pending wp-env

### UP14-S4
- [x] Add material checkbox
- [x] Material kept when budget create fails
- [x] Smoke + docs + Unreleased changelog
- [x] Progress file: smoke pending wp-env

### UP14-S5
- [x] Shipment disclosure, closed by default
- [x] Summary shows status and tracking number
- [x] Smoke + docs + Unreleased changelog
- [x] Progress file: smoke pending wp-env

### UP14-S6
- [x] Trays panel above Make
- [x] Internal + Uses trays blocks Mark done until a tray is held
- [x] Customer-order internal component line does not, by itself, require a tray
- [x] Smoke + docs + Unreleased changelog
- [x] Progress file: smoke pending wp-env

### UP14-S7
- [x] Disc and decimal list styles on instruction HTML
- [x] Preview box directly under the textarea
- [x] Smoke + docs + Unreleased changelog
- [x] BUG-006 stays Open until wp-env
- [x] Progress file: smoke pending wp-env

### UP14-S8
- [x] Column `output_per_run`, schema 1.25.0
- [x] Make editor field; Pack and Post-ship stay at 1
- [x] Credit is Produce N × yield; recipe unchanged
- [x] Workflow CSV column
- [x] Smoke + docs + Unreleased changelog
- [x] Progress file: smoke pending wp-env

### UP14-S9
- [x] Column `pack_workflow_template_id`, schema 1.26.0
- [x] Sellable product override; internal products have no field
- [x] New orders and repair: primary product override, else site default
- [x] Already-bound orders stay
- [x] Product CSV column
- [x] Smoke + docs + Unreleased changelog
- [x] Progress file: smoke pending wp-env

### UP14-S10
- [ ] Make Mark done sends `order_item_id` and reloads in place
- [ ] Post-ship Mark done reloads in place
- [ ] Pack Mark done unchanged
- [ ] Failed advance alerts and stays
- [ ] Smoke + docs + Unreleased changelog
- [ ] Progress file: smoke pending wp-env

### UP14-S11
- [ ] Pack board omits orders whose pack steps are all done
- [ ] Orders with an open pack step, and orders with no pack progress, stay
- [ ] Post-ship board still lists the finished-pack order
- [ ] Smoke + docs + Unreleased changelog
- [ ] Progress file: smoke pending wp-env

### UP14-S12
- [ ] **Release anyway** frees the last required tray
- [ ] **Release selected** still keeps one
- [ ] Order note stored; Mark done stays blocked with no tray
- [ ] **Unblock step** does not release trays
- [ ] Smoke + docs + Unreleased changelog
- [ ] Progress file: smoke pending wp-env

---

## 6. Local QA (operator)

This repo is the Local site **ordermachine** plugin. After a sprint branch is in this folder and the Local site is running, the screen is on wp-admin. No extra deploy.

Manual pass, after the relevant sprint is in the folder:

| Sprint | Check |
|---|---|
| S1 | Materials → Export CSV. Import that file. Preview shows ` (2)` names. Import. Stock on the new rows is 0. A bad supplier name is listed and does not block the good rows |
| S2 | Workflows → export a make template and a post-ship template. Import. Steps match. Import a post-ship file with the Package arrived step removed. Preview shows an error and Import creates no template |
| S3 | Products → export a sellable product that has a recipe. Import. A second product exists with the recipe. Change a material name in the file to one that does not exist. That product is listed as failed |
| S4 | Add material with the box checked. Budgets shows a material budget at £0. Add another material with the box clear. It has no budget |
| S5 | Open a customer order and an Internal order. Shipment starts closed. Save a shipment with a tracking number, reload, and read the number in the heading before opening the panel |
| S6 | On an Internal order whose make workflow has **Uses trays**, Mark done stays blocked until a tray is assigned. The Trays panel is above Make. Unblock on that order still allows one Mark done |
| S7 | Quality check (or any step with `- ` lines) shows bullets on the order. On the workflow editor, Preview opens under the instruction box and shows the same bullets |
| S8 | Set a make workflow’s Output per run to 12. Produce 3 of an internal product that uses it. The linked material increases by 36. A workflow left at 1 still increases by the Produce N quantity |
| S9 | Set Pack workflow override on one sellable product. A new order whose first line is that product uses the override. A product with no override still uses Default Pack. An order that already has a pack workflow stays on it |
| S10 | On an order, Mark done on a make step leaves the page at that step. Mark done on a post-ship step does the same. Pack Mark done still stays put |
| S11 | Finish the last pack step. The order is gone from the Pack board and still on the Post-ship board. An order that still has a pack step stays on the Pack board |
| S12 | On an open order that requires trays and holds one tray, **Release selected** refuses. **Release anyway** frees it. Mark done stays blocked until you assign a tray or use **Unblock step** |

Automated `wp eval-file` smokes stay on the final wp-env prompt after every Package 14 PR is on `main`.
