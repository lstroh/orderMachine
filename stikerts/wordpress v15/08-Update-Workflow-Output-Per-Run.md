# Update — Workflow output per run

*Package 14 · UP14-S8 · Schema **1.25.0**. Operator request, settled 2026-10-19.*

---

## 1. What this is

An Internal job credits the product’s linked material when the make workflow finishes. Today that credit equals the **Produce N** quantity, so a job of 1 adds 1.

A make workflow gets **Output per run**. One finished run adds that many units, multiplied by the Produce N quantity.

| Produce N | Output per run | Material added | Recipe consumed |
|---|---|---|---|
| 1 | 1 (default) | 1 | 1 run |
| 1 | 12 | 12 | 1 run |
| 3 | 12 | 36 | 3 runs |

Unit cost on that stock is the order’s input cost divided by the units added. A job of 3 with output 12 spreads the input cost across 36 units. The stock value added still equals the input cost.

## 2. Where the number lives

On the make workflow template, next to **Uses trays**. Whole number from **1** to **10000**. Blank saves as **1**.

Pack and Post-ship do not show the field. Saving those kinds stores **1**.

Every internal product that uses the template shares the number. A customer order never credits stock from it.

The number is read when the Internal order completes, from that product’s make template. Changing it before the job finishes changes what that job will add. It is not copied onto the order at Produce time.

Clone copies the number onto the new template.

## 3. Credit path

`SOM_Production::credit_output_for_order()`:

- Still Internal channel only, still one `production_output` log row, still idempotent.
- `units = line quantity × output_per_run` (missing or invalid yield counts as 1).
- `unit_cost = input COGS / units`. `value_change = unit_cost × units`.
- Recipe reservation on create is unchanged (still the Produce N quantity).

## 4. Workflow CSV

UP14-S2’s file gains one optional column, `output_per_run`, in this sprint (S8 runs after S2). Blank means 1. A Pack or Post-ship row stores 1 even if the cell is filled. Export writes the stored number.

## 5. Tests and docs

`tests/sprint-up14-s8-smoke.php`

- Column exists, default 1, schema **1.25.0**.
- Make template saves 12. Pack template save leaves 1.
- Internal order quantity 3, yield 12, credits 36. Unit cost is input COGS / 36. A second credit does not add again.
- Yield 1 still credits the Produce N quantity.
- A customer order does not credit stock.
- Clone keeps the number.

`USER-REFERENCE.md` Workflows: one sentence on Output per run. `FEATURES-AND-TESTING.md`: the 3 × 12 = 36 example.
