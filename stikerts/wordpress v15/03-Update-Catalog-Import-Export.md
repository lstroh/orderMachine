# Update — Catalog import and export

*Package 14 · No schema. Operator request, settled 2026-10-19.*

---

## 1. What this is

Each of Materials, Workflows, and Products gets:

- **Export CSV** — download the current records in the import columns.
- **Download template** — the header row only.
- **Import CSV** — upload, preview (final names and errors, nothing saved), then **Import**.

Follow the order-file pattern: `manage_options`, nonce, read the upload, `remember` / `recall` a preview token for the current user, commit from that token. Accept comma or tab. Detect the delimiter from the header line. Strip a UTF-8 BOM. Match headers case-insensitively. Unknown columns are ignored. A missing required column fails the file before preview rows are offered for commit.

Result copy: **Created N, failed N.** There is no updated count and no skipped count. A failed record does not roll back records that already saved in that commit.

## 2. Names

Names are not unique today. Import never updates an existing row and never matches on database id, so a file can move to another site.

| Situation | Result |
|---|---|
| No existing record with that name (case-insensitive, trimmed) | Create with the file’s name |
| That name is already taken, including by a row created earlier in this same commit | Create `Name (2)`. If that is taken, `Name (3)`, and so on |
| The suffixed name cannot fit the 100-character name column | Fail that record |

Trim the base name from the right when the suffix would overflow, the same way clone fits ` (copy)`. Comparison is case-insensitive. The stored name keeps the file’s capitalization when the name is free, and uses the generated suffix when it is not.

Preview shows the name that will be saved, including the suffix.

Re-importing an export onto the same site creates a second catalog. That is the settled rule.

Product **SKU** is stored as written, including on a suffixed name. SKU is not unique. When another product already has that SKU, the product is still created and the result lists a warning. Listing import matches on SKU, so two products can share one SKU after this import.

## 3. Shared fail rules

A referenced name that is missing, or that matches more than one record, fails the **whole** material, workflow, or product. The importer does not create suppliers, shipping packages, batch groups, materials, or workflows as a side effect of another file.

Header cells that repeat on child rows (workflow steps, product recipe lines) must agree. A disagreement fails that group.

Blank optional cells stay empty. Booleans accept `1` / `0`, `yes` / `no`, `true` / `false`. Blank `is_active` means active. Blank `uses_trays` means off.

## 4. Materials (UP14-S1)

One row per material.

| Column | Required | Notes |
|---|---|---|
| `name` | Yes | |
| `unit` | Yes | |
| `low_stock_threshold` | No | |
| `unit_cost` | No | Stored on the new row. Stock stays 0. No stock-log row |
| `preferred_supplier` | No | Supplier **name**. Blank means none |
| `is_active` | No | |

Create through `SOM_Materials::create`. Do not set `source_product_id`.

**Export** omits materials that have `source_product_id` set. Those output materials belong to an internal product. The product file recreates that link through `SOM_Products::create` → `ensure_linked_material`.

On-hand stock, weighted average, total value, purchase history, and the stock log are not columns.

## 5. Workflows (UP14-S2)

One row per step. Rows with the same `name` are one template. At least one step. Sort steps by `step_order`.

| Column | Required | Notes |
|---|---|---|
| `name` | Yes | Template name, before any suffix |
| `description` | No | |
| `kind` | Yes | `make`, `pack`, or `post_ship` |
| `uses_trays` | No | |
| `output_per_run` | No | Whole number 1–10000. Blank means 1. A Pack or Post-ship row stores 1 even when the cell is filled. Export writes the stored number. Added in UP14-S8 |
| `is_active` | No | |
| `step_order` | Yes | Integer |
| `step_name` | Yes | |
| `requires_manual_confirm` | No | |
| `timer_seconds` | No | Integer seconds. Blank means none |
| `planned_seconds` | No | Integer seconds, the stored column |
| `confirmation_kind` | No | Blank, or `print_vs_request`, `shipping_address`, `packing_items`, `package_arrived` |
| `instructions` | No | Markdown, as stored |
| `tasks` | No | Task labels joined with ` \| `. A label that itself contains `\|` fails the workflow. New task ids come from `SOM_Step_Tasks` |
| `command_template` | No | |
| `batch_group_key` | No | Existing `group_key`. Blank means none |

`output_per_run` is optional. A file that omits the column imports as 1.

Save through the same validation as the editor (`SOM_Workflows::validate_submission` and the step writer). A Post-ship template with no `package_arrived` step fails and writes no template row. Per-step combination rules (timer, copy command, batch, manual confirm) fail the whole workflow the same way Save does.

`script_config` is not a column and is stored empty. The editor already treats stored script config as disabled and clears it on save. Workflow clone does not copy it.

Not in this file: material cost goals, budget links, products, orders, and the site Pack / Post-ship defaults.

If step insert fails after the template row exists, delete the partial template. Do not leave a half workflow.

## 6. Products (UP14-S3)

One row per recipe line. Rows with the same `name` are one product. A product with no recipe is one row with `material_name` and `quantity_per_unit` empty.

| Column | Required | Notes |
|---|---|---|
| `name` | Yes | |
| `sku` | No | |
| `is_internal` | No | Blank means sellable |
| `workflow_name` | When the product is internal, or when the cell is filled | Make template **name**. Must match exactly one template |
| `post_ship_workflow_name` | No | Post-ship template name. Exactly one match when filled |
| `pack_workflow_name` | No | Pack template name. Blank means no override. Exactly one Pack template when filled. An internal product ignores the cell |
| `target_selling_price` | No | |
| `weight_grams` | No | |
| `package_name` | No | Shipping package **name** |
| `package_name_multi` | No | Shipping package **name** |
| `planned_shipping_gbp` | No | |
| `is_active` | No | |
| `material_name` | No | Recipe material **name**. Exactly one match when filled |
| `quantity_per_unit` | When `material_name` is filled | Greater than 0 |

Create through `SOM_Products::create`, then `SOM_Materials` recipe via `SOM_Products::save_recipe`. An internal product gets a new output material from the existing create path (stock 0). Never point `linked_material_id` at an existing material.

Not in this file: listings, per-product step instructions, per-product task overrides, budget links, orders.

`pack_workflow_name` is optional (UP14-S9). Blank means no override. A filled name must match exactly one Pack template. An internal product ignores the cell. A file from before this column still imports.

If recipe save fails after the product exists, delete the new product (and its new output material when the product is internal). Do not leave a half product.

## 7. UI

On each list page, a disclosure matching **Import orders** on the Orders list:

- File input, **Preview**, template download, **Export CSV**.
- Preview table: file name, name that will be saved, and a short error column.
- **Import** commits the preview token. Expired token: ask for the file again.
- Export and template are nonce-protected downloads.

## 8. Tests

One smoke per sprint, `wp eval-file` only:

| Sprint | File | Assert |
|---|---|---|
| UP14-S1 | `tests/sprint-up14-s1-smoke.php` | New material created; second import of the same name becomes `Name (2)`; unknown supplier fails that row and the good row still saves; stock stays 0; a made-in-house material is absent from export |
| UP14-S2 | `tests/sprint-up14-s2-smoke.php` | Steps, confirmation, tasks, and command template round-trip onto a new template; name collision suffixes; Post-ship without Package arrived creates no template; unknown batch group key fails the workflow |
| UP14-S3 | `tests/sprint-up14-s3-smoke.php` | Recipe lines attach by material name; unknown material fails the whole product; internal product gets its own output material; suffixed name keeps the file SKU; listings are not created |

Fixtures may be PHP strings inside the smoke. A separate CSV on disk is optional.

## 9. Docs in the implementation sprints

Short notes in `USER-REFERENCE.md` (the three screens) and `FEATURES-AND-TESTING.md` (how to export, preview, and what a second import does).
