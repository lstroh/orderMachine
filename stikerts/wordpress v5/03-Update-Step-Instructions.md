# Update — Step Instructions

*Package 4 · Schema in `02-Update-Data-Model.md` §A. Self-contained.*

---

## 1. What this adds

Static, plain-text **how to do this step** guidance for operators:

- A **default** on each workflow template step (so different workflows can differ — e.g. Bin Sticker “Print” vs Name Label “Print”).
- An optional **per-product override** when several products share one template but need different notes (e.g. print profile X vs Y).

Shown **read-only** on **order detail** for the steps of that order. Does **not** gate Mark done, timers, batch, or confirmations.

Applies to **customer orders and future production jobs** (Internal Products) using the same resolve rules.

## 2. Behaviour

### Resolve

For an order, primary product = existing rule (first order item with non-null `product_id`). For each workflow step shown on the order:

1. If `product_step_instructions` exists for `(primary_product_id, workflow_step_id)` → use that text.
2. Else if `workflow_steps.instructions` is non-empty → use that.
3. Else → show nothing (no empty box clutter).

If the order has **no** primary product (unmatched), only step defaults apply.

### Editing

| Surface | Capability |
|---|---|
| **Workflow step editor** | Textarea “Instructions (default)” per step; saved with step CRUD |
| **Product edit** | Section “Step instructions” listing steps of the product’s assigned workflow; optional override textarea per step; clear override = delete row |
| **Order detail** | Read-only block under/beside each step name (or at least the current step — prefer **all steps** in the progress list so completed steps remain auditable) |
| **Orders Board** | **Out of scope** for v1 (open order detail to read) |

### Format

- Plain text only (preserve newlines in display with `nl2br` / CSS `white-space: pre-wrap`).
- No Markdown rendering in v1.
- Reasonable length soft limit in UI (e.g. ~5k chars) — **open item** for hard max.

### Workflow reassignment

If a product’s `workflow_template_id` changes, overrides pointing at old template steps become orphaned. **Recommendation:** on workflow change, delete overrides for steps not in the new template; flag in open items if you’d rather keep them.

## 3. UI requirements

| Page | Purpose |
|---|---|
| Workflow step editor | Default instructions field per step |
| Product edit | Override matrix for current template steps |
| Order detail | Read-only effective instructions in the workflow/progress section |

## 4. Out of scope

- Board snippets / tooltips
- Images, attachments, rich text
- Acknowledge / checklist gating based on instructions
- Instructions varying by channel or buyer

## 5. Open items

1. Show instructions on **all** progress steps vs **current step only** (recommend all).
2. Hard max length for text.
3. Orphan override cleanup on workflow reassignment (recommend delete orphans).
4. MCP/REST: expose effective or raw instructions on product/order reads? (nice-to-have; not required for v1 admin UI)
