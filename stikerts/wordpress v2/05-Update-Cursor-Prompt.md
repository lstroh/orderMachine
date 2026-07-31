# Cursor Kickoff Prompt — Plugin Update (Raw Material Purchasing + Batch Processing)

*Paste this into Cursor as the first prompt for implementing this update package. It assumes the base plugin already exists and works.*

---

```
TASK: Read this update spec, examine the existing plugin codebase, and produce
a sprint plan for implementing two additive features. Do NOT write any
implementation code in this task — planning only.

## READ FIRST — in this order

1. 01-Update-Overview.md — what this update covers, the assumption that the
   base plugin (Phases 1-11) already exists, and how the two features relate.
2. 02-Update-Data-Model.md — every schema change needed (7 new tables, 5
   modified tables), self-contained.
3. 03-Update-Raw-Material-Purchasing.md — full feature spec: suppliers,
   purchase orders, landed cost, weighted-average costing, target selling
   price, per-workflow cost-ceiling alerts, pre-purchase preview calculator.
4. 04-Update-Batch-Processing.md — full feature spec: a fourth workflow gate
   type, batched thank-you card printing, batched shipping label grouping.

Each of files 2-4 has its own "Open items" section — read those closely.

## THEN — examine the existing codebase

Before planning anything, actually look at the current plugin code (not just
these spec files) to confirm:
- The existing DB schema matches what 02-Update-Data-Model.md assumes is
  already there (materials, material_stock_log, products, workflow_steps,
  order_step_progress, workflow_templates, product_materials, orders) — flag
  any mismatch rather than assuming the specs are accurate to the real code.
- How the existing schema-versioning/migration mechanism works, so the new
  tables and ALTERs follow the same pattern rather than inventing a new one.
- The existing workflow engine's step-advancement code, since Batch
  Processing's state machine (04-Update-Batch-Processing.md §2) needs to
  slot into it as a new branch, not a parallel/duplicate implementation.
- The existing `thankyou_card.py` script's actual current interface, since
  04-Update-Batch-Processing.md §3 requires extending it with a batch mode.

If the real code differs meaningfully from what these specs assume, stop and
report the discrepancy before planning further.

## YOUR TASK

1. **Produce a numbered list of every "Open item"** from files 02-04,
   noting which ones block which part of the implementation.

2. **Ask me any clarifying questions** — about the open items, anything
   ambiguous, or anything the existing codebase reveals that these specs
   didn't anticipate. Do not guess silently on anything that changes the
   shape of the code.

3. **Break this into concrete sprints.** Since the two features are
   independent (per 01-Update-Overview.md, "How the two interact"), they can
   be sequenced in either order or interleaved — use your judgement on what
   makes sense given the existing codebase structure. For each sprint list:
   - Sprint number and name
   - Which feature/part it covers
   - Specific files it will create/modify
   - What "done" looks like (short, testable)
   - Any open items this sprint needs resolved first

4. **Write the full result to a new file called `Update-Sprint-Plan.md`**
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
  functionality (order sync, the core workflow gates, listings) as part of
  implementing these two features, unless something in the existing code
  makes that genuinely unavoidable, in which case flag it as a question
  rather than just doing it.

If you have questions before you can complete steps 1-4, ask them now.
```
