# Update — Pack workflow override on a product

*Package 14 · UP14-S9 · Schema **1.26.0**. Operator request, settled 2026-10-21.*

Package 6 bound every customer order to **Settings → Default Pack workflow**, with no per-product choice. This sprint adds the same override post-ship already has. The site default stays.

---

## 1. Product field

On a sellable product, **Pack workflow override**. Blank means the site default. The list is active Pack templates, plus the saved one if it is now inactive (same pattern as the post-ship dropdown).

Internal products do not show the field.

Clone copies the override, the same way it copies the post-ship override.

## 2. Which template an order gets

When pack is bound (new customer order, or **Repair pack binding**):

1. The override on the order’s primary product, when that template is an active Pack template with steps. The primary product is the first line that has a product.
2. Otherwise **Settings → Default Pack workflow**.

An Internal order still gets no pack workflow.

An order that already has a pack template keeps it. Changing the product or the site default does not move it. There is no control on the order to pick a different pack template.

If the override is inactive, the wrong kind, or has no steps, bind uses the site default.

Other lines on a multi-product order do not change the choice.

## 3. Schema

`products.pack_workflow_template_id` `bigint unsigned NULL`, indexed, same shape as `post_ship_workflow_template_id`.

UP14-S8 leaves the schema at **1.25.0**. This sprint bumps it to **1.26.0**. Existing products stay NULL (site default).

## 4. Product CSV

UP14-S3 does not include this column. This sprint adds optional `pack_workflow_name` to the product file. Blank means no override. A filled name must match exactly one Pack template. An internal product ignores the cell.

## 5. Tests and docs

`tests/sprint-up14-s9-smoke.php`

- Column exists. Schema **1.26.0**.
- A sellable product with an override binds that template on create. A product with no override binds the site default.
- A second line’s override is ignored when the first line has a product.
- An inactive override falls through to the site default.
- An order that already has a pack template is unchanged by repair.
- An Internal order stays unbound.
- Clone copies the override.

`USER-REFERENCE.md` Products and Settings: the override, and that the primary product decides. `FEATURES-AND-TESTING.md`: the same.
