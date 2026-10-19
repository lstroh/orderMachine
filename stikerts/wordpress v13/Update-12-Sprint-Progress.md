# Update Package 12 — Sprint Progress

*Companion to [`Update-12-Sprint-Plan.md`](Update-12-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP12-S1 | Orders list / filter honesty | Done | wp-env PASS 2026-10-19 |
| UP12-S2 | Order detail Produce N Make UI | Done | wp-env PASS 2026-10-19 |
| UP12-S3 | Instructions Markdown toolbar | Done | wp-env PASS 2026-10-19 |
| UP12-S4 | Copy command placeholder dropdown | Done | wp-env PASS 2026-10-19 (assertion fix, then re-run) |
| UP12-S5 | Post-ship arrival step required (BUG-005) | Done | wp-env PASS 2026-10-19 |
| UP12-S6 | Clone workflow templates | Done | wp-env PASS 2026-10-19 |
| UP12-S7 | Clone products | Done | wp-env PASS 2026-10-19 |
| UP12-S8+ | Reserved | — | Waiting for further operator reports |

**Package 12:** code complete (UP12-S1 through UP12-S7). All seven smokes passed wp-env on 2026-10-19. Older regression smokes that still fail are listed under the pass notes; they were not marked passed.

---

## Planning notes

- Seeded 2026-10-14 from Produce N testing (Make Board works; list **No workflow**; detail Make UI hidden).  
- Spec: [`03-Update-Produce-N-Workflow-Visibility.md`](03-Update-Produce-N-Workflow-Visibility.md).  
- Bug log: **BUG-004** in `stikerts/wordpress/BUGS.md`.  
- 2026-10-14: added instructions toolbar request (T1–T6 settled) → UP12-S3; spec [`05-Update-Instructions-Toolbar.md`](05-Update-Instructions-Toolbar.md).  
- 2026-10-14: added Copy command placeholder dropdown (P1–P6 settled) → UP12-S4; spec [`06-Update-Copy-Command-Placeholder-Picker.md`](06-Update-Copy-Command-Placeholder-Picker.md).  
- 2026-10-14: filed **BUG-005** (Post-ship saved without Package arrived step; A1–A6 settled) → UP12-S5; spec [`07-Update-Post-Ship-Arrival-Required.md`](07-Update-Post-Ship-Arrival-Required.md).  
- 2026-10-14 (answered in chat, no sprint): timer works (make steps show static UTC unlock time — operator OK as-is); Pack template is site-wide (no per-product); packaging materials live on Shipping packages; Materials used is increase-only; completion = shipment + arrival.  
- UP12-S1 implemented when requested. UP12-S2 implemented when requested. UP12-S3 implemented when requested. UP12-S4 implemented when requested. UP12-S5 implemented when requested. UP12-S6 implemented when requested. UP12-S7 implemented when requested. S8+ wait for a sprint kickoff.

---

## UP12-S1 — Orders list / filter honesty

- **Status:** done. Smoke run 2026-10-19 — PASS.
- **Behaviour:** **No workflow** only when the order is open, not cancelled, has no item make progress, and has no order-level Pack/Post-ship progress. Status prefers the order-level step name, else the earliest in-progress make step name, else **Open**. Same predicate for the badge and the Needs workflow filter (`SOM_Orders::needs_workflow_sql` inside `query`).
- **Files:** `includes/class-som-orders.php`, `admin/views/orders-list.php`, `tests/sprint-up12-s1-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/BUGS.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s1-smoke.php` — Produce N open order must not match `needs_workflow` and must expose the current make step; multi-line make uses the earliest `step_order`; a customer order with no progress still matches. **2026-10-19 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s1-smoke.php`).
- **BUG-004:** list/filter part. Closed with the order-detail Make UI in UP12-S2. No schema. No Version bump.

---

## UP12-S2 — Order detail Produce N Make UI

- **Status:** done. Smoke run 2026-10-19 — PASS.
- **Behaviour:** Internal-channel Produce N lines that already have item make progress use the same order-detail Make UI as a sellable line (step list, Mark done, instructions, tasks, copy command). Mid-job shows the current step, not **Pack-ready (internal)**. When every make step is done the badge is **Ready to pack** (same complete treatment as a sellable line; there is no pack). A customer-order internal line stays **Pack-ready (internal)** with no make progress and no Make controls. `unassigned_reason` stays empty when item progress exists and still flags true gaps. Produce N assignment is unchanged. No Pack or Post-ship bind. Orders list unchanged.
- **Files:** `includes/class-som-item-make.php` (`detail_shows_make_controls`, `detail_make_badge`, `progress_steps_all_done`), `admin/views/order-detail.php`, `tests/sprint-up12-s2-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/BUGS.md`, `stikerts/wordpress/USER-REFERENCE.md`, `stikerts/wordpress/USER-GUIDE.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s2-smoke.php` — Produce N detail path shows make controls and is not pack-ready mid-job; complete is Ready; customer internal line stays pack-ready with no progress; no unassigned notice when item progress exists; a true gap still returns `needs_mapping`. **2026-10-19 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s2-smoke.php`).
- **BUG-004:** **Fixed** (list/filter was S1; order detail Make is this sprint). No schema. No Version bump.

---

## UP12-S3 — Instructions Markdown toolbar

- **Status:** done. Smoke run 2026-10-19 — PASS. Node test `node tests/som-md-insert.test.js` also PASS (`PASS — som-md-insert`).
- **Behaviour:** Both instruction editors (workflow step cards, including ones added later, and product step-instruction overrides) show one Markdown toolbar above the textarea. Buttons insert Markdown: Bold, Italic, Bullet list, Numbered list, Heading H2, Heading H3, Link, Inline code. Preview stays on the toolbar. A selection is wrapped or line-prefixed; no selection inserts a placeholder (lists and headings use the current line). Numbered lines run `1.` `2.` `3.` and skip blanks. The same list or heading prefix again removes it. An insert that would pass 5000 characters is skipped. `render_html` and the kses allowlist are unchanged. `SOMMdInsert.insertAtCursor` is the shared insert helper for UP12-S4.
- **Files:** `includes/class-som-step-instructions.php` (`render_editor_toolbar`), `admin/views/workflow-step-editor.php`, `admin/views/product-edit.php`, `admin/assets/js/som-md-insert.js`, `admin/assets/js/admin.js`, `admin/assets/css/admin.css`, `admin/class-som-admin-menu.php`, `tests/sprint-up12-s3-smoke.php`, `tests/som-md-insert.test.js`, `CHANGELOG.md`, `stikerts/wordpress/USER-REFERENCE.md`, `stikerts/wordpress/USER-GUIDE.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s3-smoke.php` — toolbar markup and each `data-som-md-action` on both surfaces; toolbar-shaped Markdown still renders strong, em, ul, ol, h2, h3, a, code; allowlist unchanged. **2026-10-19 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s3-smoke.php`). Node unit test `tests/som-md-insert.test.js` covers wrap, line prefix, numbered lists, toggle-off, and the 5000-character skip. **2026-10-19 PASS** (`node tests/som-md-insert.test.js`).
- **Schema:** none. No Version bump.

---

## UP12-S4 — Copy command placeholder dropdown

- **Status:** done. Smoke run 2026-10-19 — PASS after the picker assertion was pointed at `render_placeholder_picker` rather than the first `som-command-template` string (the label `for`).
- **Behaviour:** Every Copy command template, including step cards added while editing, has an **Insert placeholder…** dropdown above the textarea. Options come from `SOM_Copy_Command::placeholder_keys()` with labels from `placeholder_labels()` (Order number, House number, Street name, Style / design, Buyer first name, Buyer full name, Product name, Personalisation). The option value is `{{key}}`; the visible label is the friendly name; `title` is the token. Choosing one inserts that token at the caret, or replaces the current selection, using `SOMMdInsert.insertAtCursor` and `commitTextareaEdit`. Focus returns to the textarea with the caret after the token, the dropdown resets to the prompt, and `input` fires when the text changes. An insert that would pass 4000 characters is skipped. The plain-text placeholder list in the description stays. `SOM_Copy_Command::render` is unchanged. No new keys, no example-template button, no Pack Copy UI, no product override, no copy command on boards.
- **Files:** `includes/class-som-copy-command.php` (`placeholder_labels`, `render_placeholder_picker`), `admin/views/workflow-step-editor.php`, `admin/assets/js/admin.js` (`initCopyCommandPlaceholderPicker`), `admin/assets/css/admin.css`, `tests/sprint-up12-s4-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/USER-REFERENCE.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s4-smoke.php` — every placeholder key has its friendly label; the picker has one option per key with value `{{key}}`; a template that uses every key still substitutes. First run 2026-10-19 failed `picker_above_textarea`. Re-run after the assertion fix: **PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s4-smoke.php`).
- **Schema:** none. No Version bump.

---

## UP12-S5 — Post-ship arrival step required (BUG-005)

- **Status:** done. Smoke run 2026-10-19 — PASS.
- **Behaviour:** A Post-ship template (active or inactive) cannot be saved — create, edit, or kind change — unless at least one step has Confirmation checklist **Package arrived**. `SOM_Workflows::validate_submission()` normalises the posted steps the same way `save_steps()` does and rejects a Post-ship result with no arrival step. `save_submission()` runs that check before `update` / `create`, and `save_steps()` calls the same check. On failure nothing is written. The editor blocks the same case before submit and shows the same message; the server check still runs. Existing invalid templates are not repaired: the Workflows list shows **No arrival step**, the editor shows a warning, and Settings suffixes **(no arrival step)** and warns when that default is selected, without blocking the choice. An open order whose post-ship has started, whose arrival is not confirmed, and whose bound post-ship steps have no `package_arrived` step shows a notice and keeps those steps. Pack Ship detection, the complete rule, and the seed Aftercare template are unchanged. `validate_submission()` is the check `clone_template()` calls before cloning a Post-ship template.
- **Files:** `includes/class-som-workflows.php` (`validate_submission`, `save_submission`, `template_has_arrival_step`, `steps_include_arrival`), `admin/class-som-admin-menu.php`, `admin/views/workflow-templates.php`, `admin/views/workflow-step-editor.php`, `admin/views/settings.php`, `admin/views/order-detail.php`, `admin/assets/js/admin.js`, `admin/assets/css/admin.css`, `tests/sprint-up12-s5-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/BUGS.md`, `stikerts/wordpress/USER-REFERENCE.md`, `stikerts/wordpress/USER-WORKFLOWS.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s5-smoke.php` — new Post-ship without an arrival step errors and creates no row (inactive too); removing the arrival step errors and leaves name and steps unchanged; changing kind from make to post_ship without an arrival step errors and leaves kind unchanged; a template with an arrival step saves, including an inactive one; a legacy row inserted directly is flagged by `template_has_arrival_step`; a Pack template without a Ship step still saves. **2026-10-19 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s5-smoke.php`).
- **BUG-005:** **Fixed**. No schema. No Version bump.

---

## UP12-S6 — Clone workflow templates

- **Status:** done. Smoke run 2026-10-19 — PASS.
- **Behaviour:** **Clone** on each Workflows list row and on the editor (C1). The action is POST with nonce `som_clone_workflow` and `manage_options` (O11). The copy is an independent template named **X (copy)**, trimmed to the 100-character name column, with the same description, kind, Uses trays, and active/inactive state (C2). Every step is copied in the same `step_order`, including manual confirm, timer, planned duration, copy command, batch group, confirmation checklist, instructions, and tasks. Legacy `script_config` is stored null on the copy. `workflow_material_goals` and `budget_workflow_links` are copied onto the new template id (C6). Products, order progress, and Settings Pack / Post-ship defaults stay on the original. `SOM_Workflows::clone_template()` calls `validate_submission()` before any write (C7, O10): a Post-ship template with no Package arrived step returns the Save error, and a step that fails the existing gate combination checks is rejected the same way. The insert runs in a transaction; on failure the transaction is rolled back and any remaining clone rows are deleted (O11). Success redirects to the copy’s editor with **Cloned from X.**
- **Files:** `includes/class-som-workflows.php` (`clone_template`, `render_clone_button`), `admin/class-som-admin-menu.php`, `admin/views/workflow-templates.php`, `admin/views/workflow-step-editor.php`, `admin/assets/css/admin.css`, `tests/sprint-up12-s6-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/USER-REFERENCE.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s6-smoke.php` — step count, order, and copied fields match; goals and budget links are copied; products still point at the original; Pack / Post-ship site defaults are unchanged; order progress is not moved; a Post-ship template with no arrival step errors and creates no new template row; a step with a rejected gate combination errors and creates no new template row. **2026-10-19 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s6-smoke.php`).
- **Schema:** none. No Version bump. Product clone is not in this sprint.

---

## UP12-S7 — Clone products

- **Status:** done. Smoke run 2026-10-19 — PASS.
- **Behaviour:** **Clone** on each Products list row and on the edit screen (C1). The action is POST with nonce `som_clone_product` and `manage_options` (O11). The copy is an independent product named **X (copy)**, trimmed to the 100-character name column, with a blank SKU and the same active/inactive state, target price, and shipping settings (C2). `workflow_template_id` and `post_ship_workflow_template_id` stay the same (C5). There is no “also clone the workflow” option. The material recipe, `product_step_instructions`, `product_step_tasks` (same step ids), and `budget_product_links` are copied (C3). Listings, orders, stock logs, and fees are not copied. An internal clone calls `SOM_Products::ensure_linked_material()` after insert so it gets a new output material named from **X (copy)** with stock 0; the original keeps its own material (C4). `SOM_Products::clone_product()` uses the same checks as `create()` before any write. The insert runs in a transaction; on failure the transaction is rolled back and any remaining clone rows are deleted (O11). Success redirects to the copy’s edit screen with **Cloned from X. SKU left blank; listings not copied.**
- **Files:** `includes/class-som-products.php` (`clone_product`, `render_clone_button`), `admin/class-som-admin-menu.php`, `admin/views/products-list.php`, `admin/views/product-edit.php`, `admin/assets/css/admin.css`, `tests/sprint-up12-s7-smoke.php`, `CHANGELOG.md`, `stikerts/wordpress/USER-REFERENCE.md`, `stikerts/wordpress/FEATURES-AND-TESTING.md`
- **Smoke:** `tests/sprint-up12-s7-smoke.php` — fields copied, SKU null, same workflow ids; recipe, step instruction overrides, step task overrides, and budget links copied; listings not copied; an internal clone gets a new linked material at stock 0 and the original keeps its own. **2026-10-19 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s7-smoke.php`).
- **Schema:** none. No Version bump. Package 12 code is complete. All seven Package 12 smokes passed wp-env on 2026-10-19.

---

## wp-env pass — 2026-10-19

Plugin 0.33.0, db 1.24.0. No Version bump.

Command from the plugin root:

`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/<file>`

Node (no WordPress): `node tests/som-md-insert.test.js` — PASS (`PASS — som-md-insert`, exit 0).

UP12-S1 through UP12-S7: PASS. UP12-S4 failed once on `picker_above_textarea` (the assertion matched the label `for="som-command-template-…"` before the picker). The assertion now compares `render_placeholder_picker` with `class="large-text code som-command-template"`. Re-run PASS.

`tests/sprint-up7-s1-smoke.php` failed once on `make_checklist_after_step_list_open` (first `som_order_item_id` is the line-fields form above the make list). The assertion now requires `som_save_confirmation` after `som-workflow-progress`. Re-run PASS.

These older smokes still FAIL. They were not rewritten and are not marked passed. They pin behaviour later packages replaced: the order’s current step is the first pack step, completion is shipment plus arrival, script steps are gone, a confirmation step cannot also have a timer, the pack seed no longer includes Review reminder, and the schema is 1.24.0.

| File | Failing assertions |
|---|---|
| `tests/bugfix-001-002-smoke.php` | `order_on_print_step`, `bug001_print_filter_includes_order`, `bug001_open_plus_print_filter` |
| `tests/shipments-smoke.php` | `order_on_pack`, `mark_pack_done`, `order_on_ship`, `ship_blocked_without_shipment`, `ship_done_untracked`, `is_ship_step_by_batch_group`, `batch_enqueued`, `batch_release`, `batch_blocked_missing_shipment`, `batch_mark_done_with_shipments` |
| `tests/sprint11-smoke.php` | `FAIL: advance-step` (`after_create_step: Confirm pack`, `advance_status: 400`) |
| `tests/sprint-u1-smoke.php` | `no_per_order_thankyou_script` |
| `tests/sprint-u5-smoke.php` | `batch_only_validation`, `preserve_setup`, `manual_setup`, `auto_setup`, `script_setup`, `fail_setup` |
| `tests/sprint-u6-smoke.php` | `editor_setup`, `manual_setup`, `find_for_order_collecting`, `find_for_order_count`, `query_contains_batch`, `items_with_orders`, `member_buyer_name`, `member_shipping_address` |
| `tests/sprint-u7-smoke.php` | `batch_product_setup` |
| `tests/sprint-up10-s1-smoke.php` | `db_version_1.23.0`, `const_DB_VERSION_1.23.0`, `order_complete` |
| `tests/sprint-up6-s3-smoke.php` | `pack_steps_shape`, `pack_steps_unchanged_on_reseed` |
| `tests/sprint-up8-s2-smoke.php` | `save_make_steps` then the timer/tray assertions that depend on that save (`summary: FAILED (18)`) |
| `tests/step-confirmations-smoke.php` | `current_is_print_confirm`, `can_mark_after_print_ticks`, `advance_print_confirm`, `can_mark_full_pack`, `advance_pack_confirm`, `now_on_address_confirm`, `advance_address_confirm`, `now_on_done_manual` |
