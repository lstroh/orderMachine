# Update Package 9 — Sprint Progress

*Companion to [`Update-9-Sprint-Plan.md`](Update-9-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP9-S1 | eBay structured fields + Copy command step | Code complete | Schema 1.21.0; this PR |
| UP9-S2 | Remove local/api/n8n + docs/seed | Not started | |
| UP9-S3 | Markdown instructions + styling | Not started | |
| UP9-S4 | Optional step task lists | Not started | |

---

## UP9-S1 — eBay structured fields + Copy command step

- **Status:** Code complete (this PR)
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
