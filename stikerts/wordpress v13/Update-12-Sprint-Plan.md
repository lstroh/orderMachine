# Update Package 12 — Sprint Plan

*Planning only. Specs in this folder. Baseline tag: **v0.33.0** (Packages 7–11); continue from current `main`.*

**Sequencing:** List/filter honesty first (cheap, unblocks trust) → order detail Make UI for Produce N → instructions toolbar (independent; can run before S1/S2 if preferred) → Copy command placeholder dropdown (after S3; reuses its insert helper) → Post-ship arrival-step rule (independent) → clone workflows (after S5; reuses its validator) → clone products → later sprints as new issues are reported.

## Locked decisions (operator)

- Package 12 seeded by **BUG-004** (Produce N false **No workflow** + missing order-detail Make UI).  
- More testing issues may be appended; do not treat this plan as closed until the operator says so.  
- **No schema** for BUG-004.  
- Do **not** bind Pack/Post-ship to Internal Produce N.  
- Do **not** overturn Package 6: internal lines on **customer** orders stay pack-ready with no make progress.  
- Deferred items from Packages 7–11 stay out of scope unless a new report clearly needs them.  
- Packages 7–11 already released as **0.33.0**; no back-release work required to start UP12-S1.
- Instructions stay **Markdown** (Package 9 lock). Toolbar inserts Markdown into the existing textarea — **no** WYSIWYG / TinyMCE.
- Toolbar buttons: Bold, Italic, Bullet list, Numbered list, H2, H3, Link, Inline code, Preview. On workflow step editor **and** product overrides. **No** keyboard shortcuts. **No** new rendered formats / kses changes. Multi-line selection → sequential `1.` `2.` `3.` (bullets → `- ` per line).
- Copy command: **Insert placeholder…** dropdown above the template (existing 8 keys only, friendly labels, `{{key}}` on hover), inserts at cursor / replaces selection. No example-template button; no new placeholders.
- **BUG-005:** Post-ship template save is **hard-blocked** unless ≥1 step has Confirmation checklist **Package arrived** (create / edit / kind change; active or inactive); validate before any write. Existing invalid templates warned (list + editor); Settings default picker **warn only**; stuck orders **notice only**. **No** Pack Ship-step rule.

---

## 1. Open items

| # | Item | Blocks | Decision |
|---|---|---|---|
| O1 | Multi-line Status when several make lines in progress | UP12-S1 | **Default:** earliest in-progress make step name (lowest `step_order`) |
| O2 | Produce N complete badge copy on detail | UP12-S2 | **Default:** same “Ready”/complete treatment as sellable make-complete — **not** “Pack-ready (internal)” |
| O3 | Combined vs split smoke files | UP12-S1–S5 | **Default:** one smoke per sprint (`sprint-up12-s1…s5-smoke.php`) |
| O4 | Further Package 12 sprints | Later | **Open** — operator will paste more bugs/UX; plan grows before implementation kickoff |
| O5 | Toolbar toggle-off for list/heading prefixes | UP12-S3 | **Default:** same prefix again removes it; bold/italic/code do not unwrap |
| O6 | Over-length insert | UP12-S3 | **Default:** skip insert if it would exceed 5000 chars |
| T1–T6 | Toolbar scope | UP12-S3 | **Settled** (operator 2026-10-14) — see `05-Update-Instructions-Toolbar.md` §3 |
| P1–P6 | Placeholder picker scope | UP12-S4 | **Settled** (operator 2026-10-14) — see `06-Update-Copy-Command-Placeholder-Picker.md` §3 |
| O7 | Placeholder friendly labels | UP12-S4 | **Default:** table in `06` §4 |
| A1–A6 | Post-ship arrival rule scope | UP12-S5 | **Settled** (operator 2026-10-14) — see `07-Update-Post-Ship-Arrival-Required.md` §3 |
| O8 | Browser pre-submit check for the arrival rule | UP12-S5 | **Default:** yes — avoids losing unsaved step edits; server stays authoritative |
| O9 | Error / notice copy | UP12-S5 | **Default:** wording in `07` §4 |

---

## 2. Spec ↔ codebase

| Area | Today | Package 12 |
|---|---|---|
| List Flags **No workflow** | `current_step_id` empty + no `order_step_progress` | Also require no open/relevant **item** make progress |
| List Status | Order-level step name or **Open** | Else current make step name from item progress |
| Filter `needs_workflow` | Order-level only | Same definition as Flags |
| Order detail Make (Produce N) | Hidden when `product_is_internal` | Show full Make UI when channel=internal **and** item progress exists |
| Internal line on customer order | Pack-ready badge, no make | **Unchanged** |
| Instructions editor | Textarea + cheatsheet + Preview (type Markdown by hand) | + Markdown toolbar above textarea (both surfaces) |
| Instructions render | Parsedown safe mode + kses allowlist | **Unchanged** |
| Copy command editor | Placeholders listed as text; typed by hand | + **Insert placeholder…** dropdown (from `placeholder_keys()`) |
| Post-ship template save | Per-step checks only; template meta written before step validation | + template-level **Package arrived** rule; validate whole submission first (atomic) |
| Post-ship visibility | Nothing flags a template without arrival | Badges / notices on Workflows list, editor, Settings, order detail |

---

## 3. Sprints

### UP12-S1 — Orders list / filter honesty (BUG-004 part A)

| Work | Detail |
|---|---|
| Query | `SOM_Orders::query`: item make progress count + current make step name (O1) for list rows |
| List UI | Flags **No workflow** + Status column use new fields |
| Filter | Status **Needs workflow** matches badge definition |
| Tests | `tests/sprint-up12-s1-smoke.php` — Produce N open order must **not** match needs_workflow; Status exposes make step |
| Docs | Short note in FEATURES / BUGS (status → In progress when PR lands) |

**Done when:** Fresh Produce N order shows current make step (or equivalent open make state), **not** **No workflow**; filter agrees; smoke passes.

**Suggested SemVer after merge:** `0.34.0`.

---

### UP12-S2 — Order detail Produce N Make UI (BUG-004 part B)

| Work | Detail |
|---|---|
| Detail UI | `order-detail.php` Make section: Internal-channel + progress → step list / Mark done / instructions / tasks / copy-command |
| Badges | Mid-job: not Pack-ready; complete: O2 |
| Notices | No false “No make workflow” when item progress exists |
| Tests | `tests/sprint-up12-s2-smoke.php` |
| Docs | USER-REFERENCE / USER-GUIDE / FEATURES — Produce N operable from detail + board |

**Done when:** Produce N order detail shows the same make controls as a sellable make line; customer-order internal lines unchanged; smoke + docs done.

**Suggested SemVer after merge:** `0.35.0`.

---

### UP12-S3 — Instructions Markdown toolbar

| Work | Detail |
|---|---|
| Markup | Shared helper renders a `role="toolbar"` button group directly above `.som-md-source` in every `[data-som-md-editor]` (workflow step editor + product overrides); Preview toggle joins the toolbar |
| JS | `initMarkdownToolbar` in `admin/assets/js/admin.js`: delegated `[data-som-md-action]` clicks (works on dynamically added step cards); wrap / line-prefix / sequential numbering helpers; restore focus + selection; fire `input` |
| CSS | Toolbar spacing in `admin/assets/css/admin.css` |
| Render | No change to `render_html` / allowlist |
| Tests | `tests/sprint-up12-s3-smoke.php` — toolbar markup + all actions present on both surfaces; toolbar-shaped Markdown still renders `<strong>`, `<em>`, `<ul>`, `<ol>`, `<h2>`, `<h3>`, `<a>`, `<code>`; allowlist unchanged |
| Docs | USER-REFERENCE / USER-GUIDE / FEATURES-AND-TESTING (manual check: select 3 lines → Numbered → `1.` `2.` `3.`) |

**Done when:** both instruction editors show the toolbar; every button inserts the right Markdown (with and without selection); multi-line numbered/bullet works; Preview still works; no schema/render changes; smoke + docs done.

**Suggested SemVer after merge:** `0.36.0`.

---

### UP12-S4 — Copy command placeholder dropdown

| Work | Detail |
|---|---|
| PHP | `SOM_Copy_Command::placeholder_labels()` keyed by `placeholder_keys()` (O7) |
| Markup | `<select>` “Insert placeholder…” above `.som-command-template` in `workflow-step-editor.php` (incl. new-step template); option values `{{key}}`, label = friendly name, `title` = token |
| JS | `initCopyCommandPlaceholderPicker` (delegated `change`): insert at cursor / replace selection via UP12-S3 helper; reset select; refocus; fire `input`; skip if over 4000 chars |
| Render | No change to `SOM_Copy_Command::render` |
| Tests | `tests/sprint-up12-s4-smoke.php` — label per key; select markup has one option per key; render regression |
| Docs | USER-REFERENCE (workflow editor Copy command) + FEATURES-AND-TESTING manual check |

**Depends on:** UP12-S3 (shared insert-at-cursor helper). If S4 must run first, add the helper in S4 and let S3 reuse it.

**Done when:** every step’s Copy command field has the dropdown; picking an option inserts `{{key}}` at the cursor; new step cards work; smoke + docs done.

**Suggested SemVer after merge:** `0.37.0`.

---

### UP12-S5 — Post-ship must have a Package arrived step (BUG-005)

| Work | Detail |
|---|---|
| Validation | Side-effect-free submission validator in `SOM_Workflows` (resulting kind + normalised steps); `handle_save_workflow` calls it **before** `update` / `create`; `save_steps` also calls it |
| Atomic | On failure: no template update, no new template row, no step writes; flash error + redirect |
| Browser | Pre-submit check on the editor for Post-ship kind (O8) |
| Warnings | `template_has_arrival_step()` helper → Workflows list badge, editor top notice, Settings option suffix `(no arrival step)` + notice when selected (warn only) |
| Order detail | Post-ship panel notice when bound steps have no arrival step and order is open |
| Unchanged | Pack Ship detection; complete rule; seed Aftercare |
| Tests | `tests/sprint-up12-s5-smoke.php` — cases in `07` §5 |
| Docs | USER-REFERENCE / USER-WORKFLOWS / FEATURES-AND-TESTING; BUG-005 status |

**Done when:** a Post-ship template can’t be saved (any path, active or inactive) without a Package arrived step, and a failed save writes nothing; existing invalid templates and stuck orders are clearly flagged; Pack templates unaffected; smoke + docs done.

**Suggested SemVer after merge:** `0.38.0`.

---

### UP12-S6+ — Reserved

Placeholder for issues filed after this draft. Do not invent work here.

---

## 4. Suggested SemVer

| After | Plugin |
|---|---|
| UP12-S1 | 0.34.0 |
| UP12-S2 | 0.35.0 |
| UP12-S3 | 0.36.0 |
| UP12-S4 | 0.37.0 |
| UP12-S5 | 0.38.0 |

Planning does not require tagging; cut releases when the operator asks.

---

## 5. Out of scope

- Implementation until operator asks for a specific sprint prompt / “implement UP12-Sn”  
- Product Costing packaging line, Pack Copy UI, Unblock on boards, Trays overview  
- Hard-delete unused script/callback routes  
- Pack/Post-ship on Internal channel  
- WYSIWYG / TinyMCE; keyboard shortcuts; new rendered formats (underline, colour, tables, callouts, code blocks, images)  
- Markdown toolbar on non-instruction textareas (notes, copy command, tasks)  
- New Copy command placeholders; example-template button; Pack Copy UI; copy command on boards  
- Pack “must have Ship step” rule / explicit Ship checkbox; rebinding orders on invalid Post-ship templates; carrier-tracking auto-arrival  

---

## 6. Quick checklist

### UP12-S1
- [ ] Query exposes item make progress + current make step
- [ ] List Flags / Status / needs_workflow filter honest for Produce N
- [ ] Smoke

### UP12-S2
- [x] Produce N order detail Make UI
- [x] Customer internal lines still Pack-ready only
- [x] Docs + smoke

### UP12-S3
- [ ] Toolbar on workflow step editor + product overrides
- [ ] Bold / Italic / Code / Link wrap-or-insert
- [ ] Bullet / Numbered (multi-line, sequential) / H2 / H3 line prefixes
- [ ] Preview unchanged; render/allowlist unchanged
- [ ] Docs + smoke

### UP12-S4
- [ ] Placeholder labels from `placeholder_keys()`
- [ ] Insert placeholder dropdown on every Copy command field (incl. new steps)
- [ ] Insert at cursor / replace selection
- [ ] Docs + smoke

### UP12-S5
- [ ] Post-ship save blocked without Package arrived step (create / edit / kind change; inactive too)
- [ ] Atomic — nothing written on failure
- [ ] Browser pre-submit check
- [ ] Warnings: list, editor, Settings (warn only), order detail notice
- [ ] Pack templates unaffected
- [ ] Docs + smoke

### Later
- [ ] Append new BUG/UX items from testing; re-slice sprints before kickoff if needed
