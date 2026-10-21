# Update — Material budget on create

*Package 14 · UP14-S4 · No schema. Operator request, settled 2026-10-19.*

---

## 1. What this is

On **Add material** only, a checkbox **Create a material budget**.

Unchecked by default. The material save is unchanged when it is unchecked.

When it is checked, after the material row exists, create one budget through `SOM_Budgets::create`:

| Field | Value |
|---|---|
| Type | `material` |
| Material | the new material id |
| Name | the saved material name |
| Funding | `material_cost` (the only method for this type; label **Material cost (auto)**) |
| Funding value | empty |
| Target reserve | empty |
| Balance | £0 |
| Notes | empty |
| Active | yes |
| Workflow scope | none checked, which already means every workflow |

This is the same budget as **Budgets → Add budget → Material**. One budget per material stays in force.

## 2. Failure

If the material is saved and the budget is not, keep the material and show the budget error on the material screen. Do not delete the material.

The checkbox is absent on **Edit material**. An existing material with no budget is still added from the Budgets screen.

## 3. Out of this sprint

- The checkbox does not appear on the material CSV.
- No target-reserve field and no workflow picker on Add material.
- No change to manual budgets, funding on orders, or purchase draw-down.

## 4. Tests and docs

`tests/sprint-up14-s4-smoke.php`:

- Create a material with the flag on → one active material budget, balance 0, funding `material_cost`, no workflow links.
- Create a material with the flag off → no budget.
- A second create for a material that already has a budget is not this form’s job; `SOM_Budgets::create` still rejects a duplicate material id.

`USER-REFERENCE.md` Materials: one sentence that the checkbox starts the restock budget. `FEATURES-AND-TESTING.md`: the same, plus the failure case (material kept).
