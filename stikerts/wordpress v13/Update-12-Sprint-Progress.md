# Update Package 12 — Sprint Progress

*Companion to [`Update-12-Sprint-Plan.md`](Update-12-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP12-S1 | Orders list / filter honesty | Done | Code complete; smoke written, pending wp-env |
| UP12-S2 | Order detail Produce N Make UI | Done | Code complete; smoke written, pending wp-env |
| UP12-S3 | Instructions Markdown toolbar | Done | Code complete; smoke written, pending wp-env |
| UP12-S4 | Copy command placeholder dropdown | Done | Code complete; smoke written, pending wp-env |
| UP12-S5 | Post-ship arrival step required (BUG-005) | Not started | Planning only |
| UP12-S6 | Clone workflow templates | Not started | Planning only |
| UP12-S7 | Clone products | Not started | Planning only |
| UP12-S8+ | Reserved | — | Waiting for further operator reports |

**Package 12:** UP12-S1 through UP12-S4 code complete. S5+ not started.

---

## Planning notes

- Seeded 2026-10-14 from Produce N testing (Make Board works; list **No workflow**; detail Make UI hidden).  
- Spec: [`03-Update-Produce-N-Workflow-Visibility.md`](03-Update-Produce-N-Workflow-Visibility.md).  
- Bug log: **BUG-004** in `stikerts/wordpress/BUGS.md`.  
- 2026-10-14: added instructions toolbar request (T1–T6 settled) → UP12-S3; spec [`05-Update-Instructions-Toolbar.md`](05-Update-Instructions-Toolbar.md).  
- 2026-10-14: added Copy command placeholder dropdown (P1–P6 settled) → UP12-S4; spec [`06-Update-Copy-Command-Placeholder-Picker.md`](06-Update-Copy-Command-Placeholder-Picker.md).  
- 2026-10-14: filed **BUG-005** (Post-ship saved without Package arrived step; A1–A6 settled) → UP12-S5; spec [`07-Update-Post-Ship-Arrival-Required.md`](07-Update-Post-Ship-Arrival-Required.md).  
- 2026-10-14 (answered in chat, no sprint): timer works (make steps show static UTC unlock time — operator OK as-is); Pack template is site-wide (no per-product); packaging materials live on Shipping packages; Materials used is increase-only; completion = shipment + arrival.  
- UP12-S1 implemented when requested. UP12-S2 implemented when requested. UP12-S3 implemented when requested. UP12-S4 implemented when requested. S5+ wait for a sprint kickoff.

---

## UP12-S1 — Orders list / filter honesty

- **Status:** done (code). Tests pending wp-env — smoke not run in this sprint.
- **Behaviour:** **No workflow** only when the order is open, not cancelled, has no item make progress, and has no order-level Pack/Post-ship progress. Status prefers the order-level step name, else the earliest in-progress make step name, else **Open**. Same predicate for the badge and the Needs workflow filter (`SOM_Orders::needs_workflow_sql` inside `query`).
- **Files:** `includes/class-som-orders.php`, `admin/views/orders-list.php`, `tests/sprint-up12-s1-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/BUGS.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s1-smoke.php` — Produce N open order must not match `needs_workflow` and must expose the current make step; multi-line make uses the earliest `step_order`; a customer order with no progress still matches. Not executed (no wp-env).
- **BUG-004:** list/filter part. Closed with the order-detail Make UI in UP12-S2. No schema. No Version bump.

---

## UP12-S2 — Order detail Produce N Make UI

- **Status:** done (code). Tests pending wp-env — smoke not run in this sprint.
- **Behaviour:** Internal-channel Produce N lines that already have item make progress use the same order-detail Make UI as a sellable line (step list, Mark done, instructions, tasks, copy command). Mid-job shows the current step, not **Pack-ready (internal)**. When every make step is done the badge is **Ready to pack** (same complete treatment as a sellable line; there is no pack). A customer-order internal line stays **Pack-ready (internal)** with no make progress and no Make controls. `unassigned_reason` stays empty when item progress exists and still flags true gaps. Produce N assignment is unchanged. No Pack or Post-ship bind. Orders list unchanged.
- **Files:** `includes/class-som-item-make.php` (`detail_shows_make_controls`, `detail_make_badge`, `progress_steps_all_done`), `admin/views/order-detail.php`, `tests/sprint-up12-s2-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/BUGS.md`, `stikerts/wordpress/USER-REFERENCE.md`, `stikerts/wordpress/USER-GUIDE.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s2-smoke.php` — Produce N detail path shows make controls and is not pack-ready mid-job; complete is Ready; customer internal line stays pack-ready with no progress; no unassigned notice when item progress exists; a true gap still returns `needs_mapping`. Not executed (no wp-env).
- **BUG-004:** **Fixed** (list/filter was S1; order detail Make is this sprint). No schema. No Version bump.

---

## UP12-S3 — Instructions Markdown toolbar

- **Status:** done (code). Tests pending wp-env — smoke not run in this sprint.
- **Behaviour:** Both instruction editors (workflow step cards, including ones added later, and product step-instruction overrides) show one Markdown toolbar above the textarea. Buttons insert Markdown: Bold, Italic, Bullet list, Numbered list, Heading H2, Heading H3, Link, Inline code. Preview stays on the toolbar. A selection is wrapped or line-prefixed; no selection inserts a placeholder (lists and headings use the current line). Numbered lines run `1.` `2.` `3.` and skip blanks. The same list or heading prefix again removes it. An insert that would pass 5000 characters is skipped. `render_html` and the kses allowlist are unchanged. `SOMMdInsert.insertAtCursor` is the shared insert helper for UP12-S4.
- **Files:** `includes/class-som-step-instructions.php` (`render_editor_toolbar`), `admin/views/workflow-step-editor.php`, `admin/views/product-edit.php`, `admin/assets/js/som-md-insert.js`, `admin/assets/js/admin.js`, `admin/assets/css/admin.css`, `admin/class-som-admin-menu.php`, `tests/sprint-up12-s3-smoke.php`, `tests/som-md-insert.test.js`, `CHANGELOG.md`, `stikerts/wordpress/USER-REFERENCE.md`, `stikerts/wordpress/USER-GUIDE.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s3-smoke.php` — toolbar markup and each `data-som-md-action` on both surfaces; toolbar-shaped Markdown still renders strong, em, ul, ol, h2, h3, a, code; allowlist unchanged. Not executed (no wp-env). Node unit test `tests/som-md-insert.test.js` covers wrap, line prefix, numbered lists, toggle-off, and the 5000-character skip.
- **Schema:** none. No Version bump.

---

## UP12-S4 — Copy command placeholder dropdown

- **Status:** done (code). Tests pending wp-env — smoke not run in this sprint.
- **Behaviour:** Every Copy command template, including step cards added while editing, has an **Insert placeholder…** dropdown above the textarea. Options come from `SOM_Copy_Command::placeholder_keys()` with labels from `placeholder_labels()` (Order number, House number, Street name, Style / design, Buyer first name, Buyer full name, Product name, Personalisation). The option value is `{{key}}`; the visible label is the friendly name; `title` is the token. Choosing one inserts that token at the caret, or replaces the current selection, using `SOMMdInsert.insertAtCursor` and `commitTextareaEdit`. Focus returns to the textarea with the caret after the token, the dropdown resets to the prompt, and `input` fires when the text changes. An insert that would pass 4000 characters is skipped. The plain-text placeholder list in the description stays. `SOM_Copy_Command::render` is unchanged. No new keys, no example-template button, no Pack Copy UI, no product override, no copy command on boards.
- **Files:** `includes/class-som-copy-command.php` (`placeholder_labels`, `render_placeholder_picker`), `admin/views/workflow-step-editor.php`, `admin/assets/js/admin.js` (`initCopyCommandPlaceholderPicker`), `admin/assets/css/admin.css`, `tests/sprint-up12-s4-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/USER-REFERENCE.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s4-smoke.php` — every placeholder key has its friendly label; the picker has one option per key with value `{{key}}`; a template that uses every key still substitutes. Not executed (no wp-env).
- **Schema:** none. No Version bump.
