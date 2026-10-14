# Plugin Update Package 12 — Overview

*Update set · Fixes **Produce N / Internal-channel make** visibility gaps left after Package 6 (per-line make). Planning started from operator testing; more issues may be appended before implementation. Self-contained.*

---

## Assumption

- Packages **7–11** are code-complete on `main` and shipped in release **v0.33.0** (schema through **1.24.0**). Latest post-ship polish: UP11-S2 (#30 / #35).
- Per-line make + Make Board exist (Package 6). Produce N creates channel=`internal` orders via `SOM_Production::produce`.
- Baseline for this package: **v0.33.0** / current `main`.
- Standing architecture: `.cursor/rules/order-machine-architecture.mdc` and docs under `stikerts/wordpress/` (+ v7–v12 update packages).

## Operator report (seed for this package)

1. Created an **internal product**, assigned make workflow + material recipe, ran **Products → Produce N**.
2. Job appears on **Make Board** and can be advanced by drag.
3. On **Orders** list / order detail, workflow looks missing: **No workflow** flag; Make section does not show step progress.

## Root cause (planning diagnosis)

| Layer | Behaviour today | Why it misleads |
|---|---|---|
| Assign | Produce N **does** insert `order_item_step_progress` and enter the first make step | Engine path is correct |
| Make Board | Queries **item** progress | Board works |
| Orders list badge / `needs_workflow` filter | Uses legacy **order-level** `current_step_id` + `order_step_progress` only | Produce N never binds Pack → both empty → false **No workflow** |
| Order detail Make UI | Hides step list whenever `product.is_internal` | Correct for internal **component lines on customer orders**; wrong for **Internal-channel Produce N** jobs that have item progress |

Logged as **BUG-004** in [`../wordpress/BUGS.md`](../wordpress/BUGS.md).

## Operator request 2 — instructions formatting toolbar

Step instructions are Markdown (Package 9) but styling requires typing syntax. Operator wants buttons (bold, numbered list, bullet list, etc.). Settled as a **Markdown toolbar** that inserts syntax into the existing textarea — **no** WYSIWYG, no new rendered formats. Spec: [`05-Update-Instructions-Toolbar.md`](05-Update-Instructions-Toolbar.md).

## Settled product decisions (operator)

| Topic | Decision |
|---|---|
| Scope so far | BUG-004 only; package folder grows as more testing issues arrive |
| List / filter | “No workflow” must respect **per-line make** progress (and Pack / Post-ship order progress) |
| Status column | For open Produce N (and other make-active lines without a useful order-level step), show **current make step name** when available |
| Order detail Make | Internal-channel Produce N lines **with** make progress → same Make step UI as sellable lines |
| Pack-ready badge | Keep **Pack-ready (internal)** only for internal product lines on **non-Internal** orders |
| Schema | **None** expected for BUG-004 or the toolbar |
| Instructions editor | Markdown **toolbar** above textarea on workflow step editor + product overrides: Bold, Italic, Bullet, Numbered, H2, H3, Link, Inline code, Preview. No WYSIWYG, no shortcuts, no new formats. Multi-line selection numbers `1.` `2.` `3.` |
| Release cut | Optional after sprints land; SemVer suggestions only in the sprint plan |
| Deferred from 7–11 | Stay deferred unless a new report clearly needs them |

## What's in this update (current)

1. Correct Orders list / status / `needs_workflow` for orders that only have item make progress (Produce N).  
2. Show Make workflow controls on order detail for Produce N Internal-channel lines.  
3. Markdown formatting toolbar for step instructions (both editing surfaces).  
4. Docs + smoke for each.  

## Recommended build order

1. **UP12-S1** — List / filter / status: stop false **No workflow**; surface current make step  
2. **UP12-S2** — Order detail Produce N Make UI + USER docs + smoke  
3. **UP12-S3** — Instructions Markdown toolbar + docs + smoke  

Further sprints (**UP12-S4+**) reserved for issues reported after this draft.

## Out of scope (unless later reports land here)

- Product Costing packaging line (UP7 O6)  
- Pack Copy UI  
- Unblock on boards  
- Trays overview page  
- Hard-delete of unused script dispatch / workflow-callback route  
- SemVer release bumps for Packages 7–11  
- Changing Produce N stock/credit rules  
- WYSIWYG / TinyMCE for instructions; new rendered formats (underline, colour, tables, callouts…)  

## Files

1. `01-Update-Overview.md` (this file)  
2. `03-Update-Produce-N-Workflow-Visibility.md`  
3. `05-Update-Instructions-Toolbar.md`  
4. `Update-12-Sprint-Plan.md`  
5. `Update-12-Sprint-Progress.md`  
6. `04-Update-Cursor-Prompt.md`  
