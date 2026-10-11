# Plugin Update Package 9 — Overview

*Update set · Replace auto-run workflow scripts (local / api / n8n) with a **copyable command** step driven by an editable template and eBay order fields. Self-contained.*

---

## Assumption

- Make/pack workflows, confirmation checklists, timers, batches exist.
- Pack thank-you is checklist-based (Package 6); scripted thank-you batch is legacy.
- eBay sync stores `external_order_id`, `buyer_name`, shipping address, line `personalisation_text` (blob), and `raw_payload`.
- Baseline: **v0.32.2** (+ Packages 7–8 when started).

## Settled product decisions (operator)

| Topic | Decision |
|---|---|
| New gate | **Copy command** — show rendered command + **Copy**; WordPress does **not** execute it |
| Remove from product | Workflow **local / api / n8n** script run + post (editor + engine dispatch) |
| Field source | From the **eBay order** (and line aspects) |
| Scope | **Per make line** (personalisation/design are line-level) |
| Template | Editable on the workflow step with placeholders e.g. `{{order_number}}` |
| Example skill shape | `/make-sticker-order Order number: … House number: … Street: … eBay design: … Buyer name: …` |
| Step instructions | **Markdown** (not plain-only); nicer typography on order detail / editor preview |
| Step task lists | Optional tasks (like instructions: default + product override); **never** block Mark done; confirmation checklists stay as hard gates |

## What's in this update

1. **Copy command step** — template + placeholders + order-detail Copy UI; Mark done still required.  
2. **Structured eBay fields** — map order/line data into placeholder values (`order_number`, `house_number`, `street_name`, `style`, `buyer_name`, …).  
3. **Remove script execution** — drop local/api/n8n from editor and engine; migrate/docs for legacy steps.  
4. **Formatted instructions** — Markdown in step defaults + product overrides; safe render + improved styling.  
5. **Optional step task lists** — tickable tasks on the order; always optional (`06-Update-Step-Task-Lists.md`).  

## Recommended build order

1. **UP9-S1** — Structured placeholders from eBay + copy-command step (store template, render, Copy UI)  
2. **UP9-S2** — Remove local/api/n8n from product; engine/batch cleanup; docs/seed  
3. **UP9-S3** — Markdown step instructions + UI polish  
4. **UP9-S4** — Optional step task lists (+ product overrides)  

## Out of scope

- Running `/make-sticker-order` or any shell from WordPress  
- Rebuilding thank-you PDF automation (pack checklist remains)  
- Full free-form templating language (fixed placeholder set only)  
- TinyMCE / arbitrary HTML in instructions (Markdown only)  
- Requiring all tasks before Mark done  

## Files

1. `01-Update-Overview.md` (this file)  
2. `02-Update-Data-Model.md`  
3. `03-Update-Copy-Command-Step.md`  
4. `05-Update-Markdown-Instructions.md`  
5. `06-Update-Step-Task-Lists.md`  
6. `Update-9-Sprint-Plan.md`  
7. `04-Update-Cursor-Prompt.md`  
