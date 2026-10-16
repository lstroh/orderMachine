# Update Package 12 — Sprint Progress

*Companion to [`Update-12-Sprint-Plan.md`](Update-12-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP12-S1 | Orders list / filter honesty | Done | Code complete; smoke written, pending wp-env |
| UP12-S2 | Order detail Produce N Make UI | Not started | Planning only |
| UP12-S3 | Instructions Markdown toolbar | Not started | Planning only |
| UP12-S4 | Copy command placeholder dropdown | Not started | Planning only |
| UP12-S5 | Post-ship arrival step required (BUG-005) | Not started | Planning only |
| UP12-S6 | Clone workflow templates | Not started | Planning only |
| UP12-S7 | Clone products | Not started | Planning only |
| UP12-S8+ | Reserved | — | Waiting for further operator reports |

**Package 12:** UP12-S1 code complete (list/filter). S2+ not started.

---

## Planning notes

- Seeded 2026-10-14 from Produce N testing (Make Board works; list **No workflow**; detail Make UI hidden).  
- Spec: [`03-Update-Produce-N-Workflow-Visibility.md`](03-Update-Produce-N-Workflow-Visibility.md).  
- Bug log: **BUG-004** in `stikerts/wordpress/BUGS.md`.  
- 2026-10-14: added instructions toolbar request (T1–T6 settled) → UP12-S3; spec [`05-Update-Instructions-Toolbar.md`](05-Update-Instructions-Toolbar.md).  
- 2026-10-14: added Copy command placeholder dropdown (P1–P6 settled) → UP12-S4; spec [`06-Update-Copy-Command-Placeholder-Picker.md`](06-Update-Copy-Command-Placeholder-Picker.md).  
- 2026-10-14: filed **BUG-005** (Post-ship saved without Package arrived step; A1–A6 settled) → UP12-S5; spec [`07-Update-Post-Ship-Arrival-Required.md`](07-Update-Post-Ship-Arrival-Required.md).  
- 2026-10-14 (answered in chat, no sprint): timer works (make steps show static UTC unlock time — operator OK as-is); Pack template is site-wide (no per-product); packaging materials live on Shipping packages; Materials used is increase-only; completion = shipment + arrival.  
- UP12-S1 implemented when requested. S2+ wait for a sprint kickoff.

---

## UP12-S1 — Orders list / filter honesty

- **Status:** done (code). Tests pending wp-env — smoke not run in this sprint.
- **Behaviour:** **No workflow** only when the order is open, not cancelled, has no item make progress, and has no order-level Pack/Post-ship progress. Status prefers the order-level step name, else the earliest in-progress make step name, else **Open**. Same predicate for the badge and the Needs workflow filter (`SOM_Orders::needs_workflow_sql` inside `query`).
- **Files:** `includes/class-som-orders.php`, `admin/views/orders-list.php`, `tests/sprint-up12-s1-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/BUGS.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s1-smoke.php` — Produce N open order must not match `needs_workflow` and must expose the current make step; multi-line make uses the earliest `step_order`; a customer order with no progress still matches. Not executed (no wp-env).
- **BUG-004:** list/filter part noted in this work; bug stays **Open** until UP12-S2 (order detail Make UI). No schema. No Version bump.
