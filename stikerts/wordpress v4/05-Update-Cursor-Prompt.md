# Cursor Kickoff Prompt — Plugin Update 3 (Platform Fees + Analytics Dashboard)

*Paste this into Cursor as the first prompt for implementing this update package. It assumes the base plugin, Update Package 1, AND Update Package 2 already exist and work.*

---

```
TASK: Read this update spec from thr folder workpress v4 only, examine the existing plugin codebase, and produce
a sprint plan for implementing two additive features. Do NOT write any
implementation code in this task — planning only.

## READ FIRST — in this order

1. 01-Update-Overview.md — what this update covers, the assumption that the
   base plugin, Update Package 1, and Update Package 2 all already exist, and
   how the two features in this package relate to each other.
2. 02-Update-Data-Model.md — schema changes needed (3 new tables, all for
   Platform Fees — Analytics Dashboard needs no schema changes), self-contained.
3. 03-Update-Platform-Fees.md — full feature spec: syncing actual per-order
   fees from eBay's Finances API and Etsy's Payments/Ledger API, tracking
   Etsy's non-order-linked listing fee separately, a manually-editable
   estimated fee rate per channel (with seeded defaults), and an estimate-
   vs-actual comparison in the Product Costing view.
4. 04-Update-Analytics-Dashboard.md — full feature spec: sales/profit/
   material-stock-over-time charts plus two recommended companion charts,
   using Chart.js.

Each of files 2-4 has its own "Open items" section — read those closely.

## THEN — examine the existing codebase

Before planning anything, actually look at the current plugin code to confirm:
- How the existing eBay/Etsy sync classes authenticate and make API calls,
  since Platform Fees needs a new sync class following the same pattern for
  the Finances/Ledger endpoints specifically.
- The existing order-sync cron's structure, to confirm the new
  `som_sync_platform_fees` cron job (03-Update-Platform-Fees.md §3) can be
  registered the same way, as a genuinely separate job, not merged into the
  order-sync job.
- The existing Product Costing view's actual code/query structure (from
  Update Package 1), since Platform Fees extends its profit calculation
  and Analytics Dashboard's profit chart reuses the same calculation logic
  — confirm there's one shared function to extend/reuse, not duplicate.
- The existing `material_stock_log` table's actual columns and how balances
  are currently queried, since the material-stock-over-time chart
  reconstructs history from it.

If the real code differs meaningfully from what these specs assume, stop and
report the discrepancy before planning further.

## YOUR TASK

1. **Produce a numbered list of every "Open item"** from files 02-04,
   noting which ones block which part of the implementation.

2. **Ask me any clarifying questions** — about the open items, anything
   ambiguous, or anything the existing codebase reveals that these specs
   didn't anticipate. Do not guess silently on anything that changes the
   shape of the code.

3. **Break this into concrete sprints.** Note that Analytics Dashboard's
   profit chart is more accurate with Platform Fees built first, but doesn't
   strictly require it (01-Update-Overview.md, "How the two interact") — use
   your judgement on sequencing, but flag your reasoning if you choose to
   build Analytics Dashboard first. For each sprint list:
   - Sprint number and name
   - Which feature/part it covers
   - Specific files it will create/modify
   - What "done" looks like (short, testable)
   - Any open items this sprint needs resolved first

4. **Write the full result to a new file called `Update-3-Sprint-Plan.md`**
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
