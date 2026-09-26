# Cursor Kickoff Prompt — Plugin Update 4

*Paste this into Cursor as the first prompt after the Package 4 design docs exist. It assumes the base plugin and Update Packages 1–3 already exist and work. Plugin baseline: v0.23.0 / schema 1.10.0.*

---

```
TASK: Read this update spec from the folder `stikerts/wordpress v5/` only,
examine the existing plugin codebase, and produce a sprint plan for
implementing four additive features. Do NOT write any implementation code
in this task — planning only.

## READ FIRST — in this order

1. 01-Update-Overview.md — what this update covers, assumptions, settled
   decisions, recommended build order, and how the four features interact.
2. 02-Update-Data-Model.md — schema / log-reason changes (instructions,
   overuse, internal products); R&D copy has none.
3. 03-Update-Step-Instructions.md — step default + product override,
   plain text, order detail read-only.
4. 04-Update-Order-Material-Overuse.md — increase-only recipe materials on
   order detail; stock + COGS + budgets; no recipe write-back.
5. 05-Update-Rnd-Budget-Copy.md — operator copy only for R&D vs Adjust stock.
6. 06-Update-Internal-Products.md — make-to-stock internal products,
   production jobs on the same Board/list, nesting, keep thank-you batch.

Each of files 02–06 has its own "Open items" section — read those closely.
Settled decisions in 01-Update-Overview.md must not be reopened unless the
codebase makes them impossible.

## THEN — examine the existing codebase

Before planning anything, actually look at the current plugin code to confirm:

- `SOM_DB` schema versioning / `dbDelta` patterns and current `DB_VERSION`.
- Workflow step editor + product edit save paths (for instructions + overrides).
- Order detail material stock panel + `SOM_Material_Stock` / `adjust_stock`
  + `SOM_Budgets::fund_on_create` + `SOM_Analytics::order_material_cogs`
  (overuse must extend these without breaking idempotency).
- How primary product / workflow assignment works on orders.
- R&D write-off UI copy locations (`material-edit`, budgets views).
- Orders list + Board queries (where production discrimination must plug in).
- Listing product pickers (must exclude internal products later).

If the real code differs meaningfully from what these specs assume, stop and
report the discrepancy before planning further.

## YOUR TASK

1. **Produce a numbered list of every "Open item"** from files 02–06,
   noting which ones block which part of the implementation.

2. **Ask me any clarifying questions** — about the open items, anything
   ambiguous, or anything the existing codebase reveals that these specs
   didn't anticipate. Do not guess silently on anything that changes the
   shape of the code. Where 01-Update-Overview already settled a decision,
   do not re-ask it.

3. **Break this into concrete sprints.** Prefer the recommended order in
   01-Update-Overview (Instructions → Overuse + R&D copy → Internal Products)
   unless codebase evidence says otherwise — if you diverge, explain why.
   For each sprint list:
   - Sprint number and name
   - Which feature/part it covers
   - Specific files it will create/modify
   - What "done" looks like (short, testable)
   - Any open items this sprint needs resolved first

4. **Write the full result to a new file called `Update-4-Sprint-Plan.md`**
   in `stikerts/wordpress v5/` containing: the consolidated open-items list,
   your clarifying questions (kept visible even after I answer them in chat),
   the full sprint breakdown, and any discrepancies found between these specs
   and the real existing code.

## RULES

- Do not write any plugin code in this task. Planning only.
- Do not silently resolve an open item — surface it as a question, or if you
  have a genuine recommendation, present it and ask me to confirm.
- Treat the existing codebase as ground truth over the spec files where they
  conflict — flag the conflict rather than picking one silently.
- These are additive changes — don't propose reworking existing working
  functionality unless something in the existing code makes that genuinely
  unavoidable, in which case flag it as a question rather than just doing it.
- Honor workspace rules: plain PHP admin UI; `class-som-*` / `som_*` naming;
  no eval of DB script strings; pause/waive rules from older base roadmap do
  not block this package (base roadmap is already complete).

If you have questions before you can complete steps 1–4, ask them now.
```

---

## After the sprint plan exists

Implement **one sprint at a time** with a separate prompt, e.g.:

```
Implement Update Package 4 Sprint N from stikerts/wordpress v5/Update-4-Sprint-Plan.md.
Follow the feature specs. Do not expand scope. Update Update-4-Sprint-Progress.md when done.
Verify on wp-env with a smoke script or checklist.
```
