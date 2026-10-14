# Update Package 12 — Sprint Plan

*Planning only. Specs in this folder. Baseline tag: **v0.33.0** (Packages 7–11); continue from current `main`.*

**Sequencing:** List/filter honesty first (cheap, unblocks trust) → order detail Make UI for Produce N → instructions toolbar (independent; can run before S1/S2 if preferred) → later sprints as new issues are reported.

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

---

## 1. Open items

| # | Item | Blocks | Decision |
|---|---|---|---|
| O1 | Multi-line Status when several make lines in progress | UP12-S1 | **Default:** earliest in-progress make step name (lowest `step_order`) |
| O2 | Produce N complete badge copy on detail | UP12-S2 | **Default:** same “Ready”/complete treatment as sellable make-complete — **not** “Pack-ready (internal)” |
| O3 | Combined vs split smoke files | UP12-S1/S2/S3 | **Default:** one smoke per sprint (`sprint-up12-s1/s2/s3-smoke.php`) |
| O4 | Further Package 12 sprints | Later | **Open** — operator will paste more bugs/UX; plan grows before implementation kickoff |
| O5 | Toolbar toggle-off for list/heading prefixes | UP12-S3 | **Default:** same prefix again removes it; bold/italic/code do not unwrap |
| O6 | Over-length insert | UP12-S3 | **Default:** skip insert if it would exceed 5000 chars |
| T1–T6 | Toolbar scope | UP12-S3 | **Settled** (operator 2026-10-14) — see `05-Update-Instructions-Toolbar.md` §3 |

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

### UP12-S4+ — Reserved

Placeholder for issues filed after this draft. Do not invent work here.

---

## 4. Suggested SemVer

| After | Plugin |
|---|---|
| UP12-S1 | 0.34.0 |
| UP12-S2 | 0.35.0 |
| UP12-S3 | 0.36.0 |

Planning does not require tagging; cut releases when the operator asks.

---

## 5. Out of scope

- Implementation until operator asks for a specific sprint prompt / “implement UP12-Sn”  
- Product Costing packaging line, Pack Copy UI, Unblock on boards, Trays overview  
- Hard-delete unused script/callback routes  
- Pack/Post-ship on Internal channel  
- WYSIWYG / TinyMCE; keyboard shortcuts; new rendered formats (underline, colour, tables, callouts, code blocks, images)  
- Toolbar on non-instruction textareas (notes, copy command, tasks)  

---

## 6. Quick checklist

### UP12-S1
- [ ] Query exposes item make progress + current make step
- [ ] List Flags / Status / needs_workflow filter honest for Produce N
- [ ] Smoke

### UP12-S2
- [ ] Produce N order detail Make UI
- [ ] Customer internal lines still Pack-ready only
- [ ] Docs + smoke

### UP12-S3
- [ ] Toolbar on workflow step editor + product overrides
- [ ] Bold / Italic / Code / Link wrap-or-insert
- [ ] Bullet / Numbered (multi-line, sequential) / H2 / H3 line prefixes
- [ ] Preview unchanged; render/allowlist unchanged
- [ ] Docs + smoke

### Later
- [ ] Append new BUG/UX items from testing; re-slice sprints before kickoff if needed
