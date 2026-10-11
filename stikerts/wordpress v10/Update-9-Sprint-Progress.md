# Update Package 9 — Sprint Progress

*Companion to [`Update-9-Sprint-Plan.md`](Update-9-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP9-S1 | eBay structured fields + Copy command step | Done on main | Schema 1.21.0; #23 |
| UP9-S2 | Remove local/api/n8n + docs/seed | Done on main | #24 |
| UP9-S3 | Markdown instructions + styling | Code complete | This PR |
| UP9-S4 | Optional step task lists | Not started | |

---

## UP9-S1 — eBay structured fields + Copy command step

- **Status:** Done on `main` (#23)
- **Schema:** 1.21.0 (`workflow_steps.command_template`; `order_items.house_number` / `street_name` / `style` / `personalisation_json`)
- **Plugin:** unchanged (release bump optional later → ~0.39.0)

### Delivered

- Schema + `SOM_DB::upgrade_copy_command_columns()`
- `SOM_Copy_Command`: placeholders, first-name helper (O2), render + “Missing: …” notice; unknown `{{…}}` left literal
- eBay extract (O1): case-insensitive aspect aliases from `02` §C; style → line title fallback; fixture `tests/fixtures/ebay-orders.json` updated with House Number / Street Name / Design
- Persist structured fields on `create_from_external` / channel ingest (`insert_item`)
- Workflow editor: **Copy command template** + placeholder help; script panels collapsed under legacy `<details>` (full delete → S2)
- Soft exclusive (O3): copy-command cannot combine with timer / script / batch; implies manual confirm; may combine with confirmation checklist
- Make enter: copy-command → `in_progress` (not `waiting_script`); no server dispatch
- Order detail (O7): monospace rendered command + **Copy** on make line; Mark done still manual
- Order detail (O4): editable house / street / style (+ personalisation summary); re-renders after save
- Smoke: `tests/sprint-up9-s1-smoke.php`

### Soft defaults applied

- Per make line only (Pack Copy UI deferred)
- O2: `buyer_name` = first whitespace token; `{{buyer_full_name}}` supported
- O3: no copy-command + timer on same step
- O4: editable structured fields on order detail
- O7: Copy UI on order detail only (not Make board)
- No server-side skill/shell execution
- Script engine dispatch left in place for legacy steps (S2 removes)

### Explicitly deferred

- UP9-S2: remove local/api/n8n from product; seed example template; docs
- UP9-S3: Markdown instructions
- UP9-S4: step task lists

### O1 aspect aliases (locked for S1 from fixture + doc §C)

| Field | Aspect name candidates (exact, case-insensitive) |
|---|---|
| `house_number` | house number, house no, house #, number |
| `street_name` | street name, street, road name |
| `style` | design, style, design name, ebay design (+ line `title` fallback) |

### Verification

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up9-s1-smoke.php
```

---

## UP9-S2 — Remove local/api/n8n + docs/seed

- **Status:** Done on `main` (#24)
- **Schema:** unchanged (1.21.0)
- **Plugin:** unchanged (release bump optional later → ~0.40.0)

### Delivered

- **Editor:** deleted local / api / n8n / raw `script_config` UI; legacy rows show disabled warning until cleared on save (O5)
- **Save path:** `SOM_Workflows::save_steps` always writes `script_config = null` (clears legacy); `sanitize_script_config` is a no-op
- **Engine (O5):** make + pack enter never set `waiting_script` / never call `SOM_Script_Dispatch::execute`; legacy script-only → `in_progress` (Mark done). Timer unlock no longer dispatches. `attempt_script` / retry open for Mark done
- **Dispatch:** `SOM_Script_Dispatch::execute` always returns `som_script_removed`; class kept for `has_script()` legacy detection
- **Batches (O6):** script batch release soft-fails with clear message; does not run `SOM_Local_Actions`
- **REST:** `workflow-callback` left registered but documented unused; Settings n8n / callback copy deprecated
- **Seed:** Bin Sticker Make **Print** step gets `/make-sticker-order` copy-command template (`SOM_Copy_Command::EXAMPLE_MAKE_STICKER_TEMPLATE`); idempotent ensure + clears legacy script on Print
- **Docs:** USER-GUIDE / USER-REFERENCE / FEATURES — Copy command + placeholders + editable fields; scripts removed; script batches disabled
- **Smoke:** `tests/sprint-up9-s2-smoke.php` (+ S1 smoke assertion updated for removed script UI)

### Soft defaults applied

- O5: ignore legacy `script_config` at runtime; editor warns; clear on save
- O6: script batches soft-fail / disabled; document
- No server-side skill/shell execution
- Do not restore n8n/API
- Unblock step unchanged for remaining gates (trays, timer, checklist, batch, ship)

### Explicitly deferred

- UP9-S3: Markdown instructions
- UP9-S4: step task lists
- Deleting `SOM_Script_Dispatch` / `SOM_Local_Actions` classes entirely (kept as safe no-op / migration)

### Verification

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up9-s2-smoke.php
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up9-s1-smoke.php
```

---

## UP9-S3 — Markdown instructions + styling

- **Status:** Code complete (this PR)
- **Schema:** unchanged (still TEXT; no bump)
- **Plugin:** unchanged (release bump optional later → ~0.41.0)

### Parser choice (O9)

- **Vendored [Parsedown](https://github.com/erusev/parsedown) 1.7.4** (MIT) at `includes/lib/parsedown/Parsedown.php`
- WP has no core Markdown parser; single file under `includes/` ships in release zips
- `setSafeMode( true )` + `setBreaksEnabled( true )`; then tight `wp_kses` allowlist via `SOM_Step_Instructions::allowed_html()` (not full `wp_kses_post` — **no `<img>`**)

### Delivered

- `SOM_Step_Instructions::render_html( $markdown )` — Markdown → safe HTML; do not `esc_html` the body
- Order detail make + pack: rendered HTML in `.som-step-instructions-body` (same `effective()` resolution)
- CSS: spacing, lists, headings, `code` / `pre` under `.som-step-instructions-body`
- Workflow step editor + product overrides: “Markdown supported” help, cheatsheet, simple AJAX **Preview** (O10)
- Max length unchanged (**5000**)
- Docs: USER-REFERENCE (+ brief GUIDE / FEATURES) — Markdown; no images; no HTML editor; Copy command stays separate
- Smoke: `tests/sprint-up9-s3-smoke.php`

### Soft defaults applied

- O8: no images in instructions Markdown
- O9: Parsedown 1.7.4 vendored at kickoff
- O10: simple editor preview (AJAX → same `render_html`)
- Keep MAX_LENGTH 5000
- Copy-command remains its own gate / field
- No UP9-S4 task lists

### Explicitly deferred

- UP9-S4: optional step task lists
- TinyMCE / block editor
- Images in instructions

### Verification

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up9-s3-smoke.php
```
