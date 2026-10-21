# Update Package 14 — Data model

*Only UP14-S8 and UP14-S9 change the schema. Every other sprint in this package leaves the database alone.*

---

## `workflow_templates.output_per_run`

| | |
|---|---|
| Column | `output_per_run` |
| Type | `int unsigned NOT NULL DEFAULT 1` |
| Meaning | How many units of the linked material one completed Internal run adds, before multiplying by the Produce N quantity |
| Who uses it | Make templates only. Pack and Post-ship store `1` and ignore it |

Bump `SOM_DB::DB_VERSION` from **1.24.0** to **1.25.0** in UP14-S8. `dbDelta` / the existing upgrade helper adds the column. Existing rows become `1`.

No change to stock-log reasons. The credit is still one `production_output` row per completed Internal order.

## `products.pack_workflow_template_id`

| | |
|---|---|
| Column | `pack_workflow_template_id` |
| Type | `bigint(20) unsigned NULL` |
| Key | `KEY pack_workflow_template_id (pack_workflow_template_id)` |
| Meaning | Optional Pack template for customer orders whose primary product is this one. NULL means Settings → Default Pack workflow |
| Who uses it | Sellable products. Internal products leave it NULL |

UP14-S9 bumps `SOM_DB::DB_VERSION` from **1.25.0** to **1.26.0**. Existing products stay NULL.
