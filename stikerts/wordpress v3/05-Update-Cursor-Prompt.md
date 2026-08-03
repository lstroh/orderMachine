# Cursor Kickoff Prompt — Plugin Update 2 (Budgets + Order Board)

*Paste this into Cursor as the first prompt for implementing this update package. It assumes the base plugin AND Update Package 1 (Raw Material Purchasing + Batch Processing) already exist and work.*

---

```
TASK: Read this update spec, examine the existing plugin codebase, and produce
a sprint plan for implementing two additive features. Do NOT write any
implementation code in this task — planning only.

## READ FIRST — in this order

1. 01-Update-Overview.md — what this update covers, the assumption that the
   base plugin AND Update Package 1 already exist, and how the two features
   in this package relate to each other.
2. 02-Update-Data-Model.md — schema changes needed (3 new tables, all for
   Budgets — Order Board needs no schema changes), self-contained.
3. 03-Update-Budgets.md — full feature spec: material and manual budgets,
   funding logic (hooks into existing order-creation flow), draw-down logic
   (hooks into existing purchase-receipt flow from Update Package 1), alerts.
4. 04-Update-Order-Board.md — full feature spec: Kanban card view, dynamic
   columns, filters, gated drag-and-drop via the existing advance-step REST
   endpoint.

Each of files 2-4 has its own "Open items" section — read those closely.

## THEN — examine the existing codebase

Before planning anything, actually look at the current plugin code to confirm:
- The existing order-creation handler's structure, since Budgets needs a new
  funding step added alongside the existing material-stock-decrement logic —
  find the right hook point rather than guessing.
- The existing purchase-receipt handler's structure (from Update Package 1),
  since Budgets needs a new draw-down step added alongside the existing
  weighted-average-cost-update logic.
- The existing advance-step REST endpoint's actual request/response shape,
  since Order Board's drag-and-drop calls it directly and needs to match its
  real contract, not an assumed one.
- Whether `products.target_selling_price` and `order_items` (with actual
  sold price, if captured) already exist as these specs assume — flag any
  mismatch rather than assuming the specs are accurate to the real code.

If the real code differs meaningfully from what these specs assume, stop and
report the discrepancy before planning further.

## YOUR TASK

1. **Produce a numbered list of every "Open item"** from files 02-04,
   noting which ones block which part of the implementation.

2. **Ask me any clarifying questions** — about the open items, anything
   ambiguous, or anything the existing codebase reveals that these specs
   didn't anticipate. Do not guess silently on anything that changes the
   shape of the code.

3. **Break this into concrete sprints.** Budgets and Order Board are
   independent (per 01-Update-Overview.md) and can be sequenced in either
   order or interleaved. For each sprint list:
   - Sprint number and name
   - Which feature/part it covers
   - Specific files it will create/modify
   - What "done" looks like (short, testable)
   - Any open items this sprint needs resolved first

4. **Write the full result to a new file called `Update-2-Sprint-Plan.md`**
   containing: the consolidated open-items list, your clarifying questions
   (kept visible even after I answer them in chat), the full sprint
   breakdown, and any discrepancies found between these specs and the real
   existing code.

## RULES

- Do not write any plugin code in this task. Planning only.
- Do not silently resolve an open item — surface it as a question, or if you
  have a genuine recommendation, present it and ask me to confirm.
- Treat the existing codebase as ground truth over the spec files where they
  conflict — flag the conflict rather than picking one silently.
- These are additive changes — don't propose reworking existing working
  functionality as part of implementing these two features, unless something
  in the existing code makes that genuinely unavoidable, in which case flag
  it as a question rather than just doing it.

If you have questions before you can complete steps 1-4, ask them now.
```
