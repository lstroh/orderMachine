# Update Package 16 — Cursor kickoff prompt

*Use when the operator asks to start a sprint. Do not implement from planning alone.*

## Locked model

- A workflow step may set `photo_required` and an optional `photo_label` (191 characters). At least one JPEG or PNG, 5 MB each.
- Files are WordPress attachments (parent 0) in `wp-content/uploads/order-machine/step-photos/YYYY/MM/`. Rows live in `som_step_photos`, one progress id set.
- Make uses `order_item_step_progress`. Pack and Post-ship use `order_step_progress`.
- `can_mark_done()` fails while a required photo is missing, unless `manual_unblock_at` is set. `blocking_gate_keys()` adds `photo`. Both enter paths treat the flag as manual so the step does not auto-complete.
- Unblock step is the bypass. It still skips every gate and does not Mark done.
- Add and remove only on the current step before it is done. Thumbnails remain after Mark done.
- Photo may combine with manual confirm, checklist, copy command, and timer. A batch step cannot require a photo.
- Clone, workflow CSV, and `map_workflow_step()` carry the flag and the label. Abilities do not return file URLs.
- Schema **1.27.0**. No Version bump. Plain PHP. SemVer `0.x.y` alpha.

## Rules

- Read `.cursor/rules/order-machine-architecture.mdc`, [`01-Update-Overview.md`](01-Update-Overview.md), [`Update-16-Sprint-Plan.md`](Update-16-Sprint-Plan.md), and [`02-Update-Step-Photos.md`](02-Update-Step-Photos.md).
- One sprint per chat. Branch from latest `origin/main`. Do not continue a previous sprint branch. The checkout may still be on `release-0.36.0`; do not continue that branch.
- Do not start the next sprint.
- Do not run `npx @wordpress/env`.
- The Local site **ordermachine** uses this plugin folder. After the code is saved here, the operator can reload that site’s wp-admin and try the screen. Implementation still opens a PR and does not merge. Automated smokes wait for the package wp-env prompt.

## Sprints

| Sprint | Focus |
|---|---|
| UP16-S1 | Required photos on a workflow step, blocking Mark done, bypassed by Unblock |

The operator is still adding items. Do not start a sprint until they ask.

## Kickoff snippet — UP16-S1 (copy into the implementation chat)

```
Implement Order Machine UP16-S1 only (Package 16). Do not start a later sprint.

Branch from latest origin/main. Do not continue a previous sprint branch. The checkout may still be on release-0.36.0; do not continue that branch.

Read:
- .cursor/rules/order-machine-architecture.mdc
- stikerts/wordpress v17/01-Update-Overview.md
- stikerts/wordpress v17/02-Update-Step-Photos.md
- stikerts/wordpress v17/Update-16-Sprint-Plan.md (locked decisions and the UP16-S1 section)
- includes/class-som-db.php
- includes/class-som-workflows.php (normalize_step_rows, save_steps, clone, step_gates_label)
- includes/class-som-workflow-engine.php (can_mark_done, blocking_gate_keys, enter_step)
- includes/class-som-item-make.php (enter_item_step)
- includes/class-som-workflow-import.php
- includes/class-som-abilities.php (map_workflow_step)
- includes/class-som-shipments.php (attach_proof, as the upload pattern)
- admin/views/workflow-step-editor.php
- admin/views/order-detail.php
- admin/class-som-admin-menu.php
- admin/assets/js/admin.js (stepHasGate)

Locked behaviour:
- Schema 1.27.0. som_workflow_steps gains photo_required (tinyint 1, default 0) and photo_label (varchar 191, null). New table som_step_photos as in 02 §2. Existing steps stay not required.
- Workflow editor: Require photo, plus an optional label. A label over 191 characters fails the save and writes nothing. Saving a photo step sets requires_manual_confirm to 1.
- Photo may combine with manual confirm, checklist, copy command, and timer. Batch plus photo is rejected, in normalize_step_rows and in the editor warning. The zero-gate warning treats Require photo as a gate.
- enter_step and enter_item_step treat photo_required as manual. A photo-only step stays in_progress.
- can_mark_done returns false when photo_required is set, that progress row has no som_step_photos row, and manual_unblock_at is empty. blocking_gate_keys adds photo, so Unblock step appears. Unblock still skips every gate and does not Mark done. The order note lists photo.
- New class SOM_Step_Photos. JPEG and PNG only, 5 MB, media_handle_upload parent 0, upload_dir limited to wp-content/uploads/order-machine/step-photos/YYYY/MM/ for that call only. Make rows use order_item_step_progress_id. Pack and Post-ship rows use order_step_progress_id. Exactly one id is set.
- Order detail shows the uploader on the current Make line, Pack step, and Post-ship step. Add and Remove only while that step is current and not done. A done step keeps its thumbnails and hides the file input. A step that does not require a photo has no uploader.
- Clone and the workflow CSV copy photo_required and photo_label. A CSV that omits the columns imports as not required. A batch row with the flag set fails the save and creates no template.
- map_workflow_step adds photo_required and photo_label. Do not return attachment ids or file URLs.
- Do not bump Version or SOM_VERSION.

Out of scope:
- Named photo slots, a photo-only skip, a board uploader, a per-product photo flag, shipment-proof changes, hiding Media library attachments.
- Budget and board REST, Pack Copy UI, Unblock on boards, the Trays overview page.
- Do not run npx @wordpress/env. Do not rewrite the eleven stale smokes.

Tests:
- Write tests/sprint-up16-s1-smoke.php in the style of tests/sprint-up15-s2-smoke.php (ABSPATH guard, run only via wp eval-file).
- Assert DB_VERSION 1.27.0, the two columns, and the som_step_photos table.
- Assert a photo step stores the flag and the label, a label over 191 characters fails, and batch plus photo is rejected.
- Assert entering a photo-only step leaves it in_progress, can_mark_done is false with no photo, and true after Unblock with photo in manual_unblock_gates.
- Assert a temporary JPEG is stored on the progress row and a PDF is rejected. A Make line and an order-level progress row do not share photos.
- Assert the order-detail markup has a file input on a current photo step, and a done photo step has the thumbnail markup and no file input.
- Assert clone and a workflow CSV row keep photo_required and photo_label, and map_workflow_step includes both.
- php -l on changed PHP. node --check on changed JavaScript.

Docs:
- CHANGELOG.md Unreleased line only.
- FEATURES-AND-TESTING.md workflow editor, the Unblock list in the workflow engine, and order detail.
- USER-REFERENCE.md step gates and order detail.
- MCP.md: one sentence that workflow steps include photo_required and photo_label, and that photo files are not in the ability payload.

PR:
- Open a focused PR. Do not merge it.
- Say that the Local site ordermachine serves this plugin folder, so the operator can reload wp-admin and try the order screen.
```
