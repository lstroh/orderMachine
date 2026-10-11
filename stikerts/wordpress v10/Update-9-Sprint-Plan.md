# Update Package 9 — Sprint Plan

*Planning only. Specs in this folder. Baseline: **v0.32.2**.*

**Sequencing:** Structured eBay fields + copy-command → remove local/api/n8n → Markdown instructions polish.

## Locked decisions (operator)

- New **Copy command** gate; **remove** local / api / n8n from the product.  
- Inputs from **eBay order**; **per make line**.  
- Editable step template with `{{placeholders}}`.  
- Example skill: `/make-sticker-order` with order number, house, street, design, buyer name.  
- Operator runs locally; SOM only displays + Copy + Mark done.  
- Step **instructions** support **Markdown** + better styling (not TinyMCE).  
- Optional **step task lists** (like instructions: default + product override); never gate Mark done; confirmation checklists unchanged.

---

## 1. Consolidated open items

| # | Item | Blocks | Status / decision |
|---|---|---|---|
| O1 | Exact eBay aspect names for house/street/style | UP9-S1 | **Confirm from live/fixture order** before locking aliases |
| O2 | `buyer_name` = first word? | UP9-S1 | **Default: yes** |
| O3 | Copy-command + timer on same step? | UP9-S1 | **Default: no** |
| O4 | Edit structured fields on order detail? | UP9-S1 | **Default: yes** |
| O5 | Legacy `script_config` at runtime | UP9-S2 | **Default: ignore (no dispatch); editor warns** |
| O6 | Script batch groups | UP9-S2 | **Default: disabled / soft-fail; docs** |
| O7 | Copy on Make board? | UP9-S1 | **Default: order detail only** |
| O8 | Instructions images in Markdown? | UP9-S3 | **Default: no** |
| O9 | Markdown parser (core vs lib) | UP9-S3 | **Choose at kickoff** |
| O10 | Editor preview for instructions? | UP9-S3 | **Default: yes, simple** |
| O11 | Task list require-all flag? | UP9-S4 | **Settled: no — always optional** |
| O12 | Task override = full replace | UP9-S4 | **Default: yes** |
| O13 | Pack task product overrides | UP9-S4 | **Default: no** (template only) |

---

## 2. Spec ↔ codebase

| Area | Today | Package 9 |
|---|---|---|
| Script step | local / api / n8n execute | **Removed** |
| Personalisation | Single text blob | + `house_number`, `street_name`, `style` from eBay |
| Make line UI | Gates / Mark done | + rendered command + Copy |
| Instructions | Plain `esc_html` | **Markdown** → kses HTML + CSS polish |
| Step tasks | None (only gating confirmations) | Optional tick list; confirmations stay gates |
| Thank-you | Pack checklist (P6) | Unchanged |

---

## 3. Sprints

### UP9-S1 — eBay structured fields + Copy command step

| Work | Detail |
|---|---|
| Schema | Line columns; `workflow_steps.command_template` |
| eBay extract | Map aspects → house/street/style (O1); buyer first name helper |
| Editor | Command template field; placeholder help; no script panels on new edits (can hide in S1, delete in S2) |
| Engine / UI | Copy-command is not `waiting_script`; show panel on make line; Copy button |
| Order detail | Optional edit of house/street/style (O4) |
| Tests | `tests/sprint-up9-s1-smoke.php` — render template; missing fields notice |

**Done when:** a make step can show a filled `/make-sticker-order …` block from eBay-like data and copy it; smoke passes.

---

### UP9-S2 — Remove local/api/n8n + docs/seed

| Work | Detail |
|---|---|
| Remove editor | Delete local/api/n8n/raw script_config UI |
| Engine | Never dispatch `SOM_Script_Dispatch` on enter; ignore legacy config (O5) |
| Batches | Script batch actions disabled/soft-fail (O6) |
| Seed | Example make step with copy-command template for small sticker flow |
| Docs | USER-GUIDE / REFERENCE / FEATURES — copy command; scripts removed |
| Cleanup | Deprecate callback docs; trim local-actions allowlist from workflow path |
| Tests | `tests/sprint-up9-s2-smoke.php` — no waiting_script from legacy seed |

**Done when:** product cannot configure or run script/api/n8n steps; copy-command is the documented path; smoke passes.

---

### UP9-S3 — Markdown instructions + styling

- **Covers:** `05-Update-Markdown-Instructions.md`  

| Work | Detail |
|---|---|
| Render helper | Markdown → safe HTML (`wp_kses_post` / allowlist) |
| Order detail | Replace `esc_html` body with rendered HTML; CSS for lists/headings/code |
| Editor | Markdown help + simple preview (O10); same for product overrides |
| Docs | USER-REFERENCE note: Markdown in instructions |
| Tests | `tests/sprint-up9-s3-smoke.php` — bold/list render; script tags stripped |

**Done when:** formatted instructions look clear on order detail; unsafe HTML stripped; smoke passes.

---

### UP9-S4 — Optional step task lists

- **Covers:** `06-Update-Step-Task-Lists.md`; `02` §D  

| Work | Detail |
|---|---|
| Schema | Step `task_list_json`; product override table/JSON; progress `task_checks_json` |
| Editor | Task rows on workflow steps; product override UI (like instructions) |
| Order detail | Tasks panel + tick save (AJAX or form); never affects `can_mark_done` |
| Docs | Distinguish Tasks vs Confirmation checklist |
| Tests | `tests/sprint-up9-s4-smoke.php` — override wins; Mark done with unticked tasks |

**Done when:** operators can tick optional tasks per step; Mark done works regardless; smoke passes.

---

## 4. Suggested SemVer

| After | Plugin (approx) |
|---|---|
| UP9-S1 | 0.39.0* |
| UP9-S2 | 0.40.0* |
| UP9-S3 | 0.41.0* |
| UP9-S4 | 0.42.0* |

\*After Packages 7–8 version bumps (Package 10 timing versions shift after this if needed).

---

## 5. Out of scope

- Server-side execution of Cursor skills / shell  
- Restoring n8n webhooks  
- AI parsing of unstructured personalisation  
- TinyMCE / block editor for instructions  
- Requiring all tasks before Mark done  

---

## 6. Quick checklist

### UP9-S1
- [ ] Schema: command_template + line house/street/style
- [ ] eBay aspect → fields (confirm labels)
- [ ] Step editor template + placeholders
- [ ] Make line: render + Copy
- [ ] Smoke

### UP9-S2
- [ ] Remove local/api/n8n from product
- [ ] Engine/batch cleanup
- [ ] Seed example + docs
- [ ] Smoke

### UP9-S3
- [ ] Markdown render + kses
- [ ] Order detail / editor styling + preview
- [ ] Docs + smoke

### UP9-S4
- [ ] Task list on steps + product overrides
- [ ] Order detail ticks (optional; no Mark done gate)
- [ ] Docs + smoke
