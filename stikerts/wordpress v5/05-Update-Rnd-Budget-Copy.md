# Update — R&D / Budget Operator Copy

*Package 4 · **No schema changes.** Self-contained.*

---

## 1. What this adds

Operator-facing explanation of why **R&D / non-sale write-off** reduces a material budget, and how that differs from **Adjust stock**.

Mental model (agreed in planning):

> A material budget is a **restock pot**.  
> **Sales** put money in (funding from material consumed on orders).  
> **Buying stock (PO receive)** and **R&D write-off** take money out.  
> R&D means you burned inventory with no sale — the pot must go down so it stays honest for restocking.  
> Plain **Adjust stock** is for corrections/counts and **never** touches the budget.

## 2. Where to update

| Surface | Change |
|---|---|
| Material edit — beside **Adjust stock** | Short note: does not affect budgets; use R&D for real non-sale consumption |
| Material edit — **R&D write-off** | Explain debit = qty × WA cost against the material restock pot when a budget exists; stock still drops if no budget |
| Budget detail — R&D action (if present) | Same restock-pot wording |
| `FEATURES-AND-TESTING.md` §3.5 / §3.13 | Align terminology with restock pot |
| `USER-GUIDE.md` / `USER-REFERENCE.md` (if they mention R&D or Adjust stock) | One short clarifying paragraph each where relevant |

No new admin pages. No behaviour change to `SOM_Budgets::write_off_material` unless copy review finds a bug (none expected).

## 3. Suggested microcopy (draft — edit for tone in implementation)

**Adjust stock help**

> Enter a positive or negative delta to correct on-hand quantity. This does not change any budget. For material used in R&D or other non-sale work, use R&D write-off so the restock budget stays accurate.

**R&D write-off help**

> Removes stock you used without a customer sale and, if this material has an active budget, reduces that restock pot by quantity × unit cost. Sales fund the pot; purchases and R&D draw it down.

## 4. Out of scope

- Changing funding/draw-down maths
- Renaming “R&D” in the database
- Dashboard widget for write-offs

## 5. Open items

1. Final wording / UK English tone pass with the operator.
2. Whether Budgets list needs a one-line glossary — recommend no, detail pages are enough.
