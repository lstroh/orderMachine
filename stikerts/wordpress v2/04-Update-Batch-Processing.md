# Update — Batch Processing

*Update set 4 of 4 (specs) · Schema referenced here is defined in `02-Update-Data-Model.md` Part B/C. Self-contained — no need to reference the original planning files.*

---

## 1. What this adds

A **fourth workflow gate type**, alongside the existing manual-confirm, timer, and script/API gates. The existing workflow engine advances one order at a time through its own step sequence. Batching means several orders sit and wait together at a step until enough of them (default 4) — or a manual override — triggers one shared action that then advances all of them at once.

**Batches pool across all workflows/products** for a given action (not scoped to one workflow template) — two different workflow templates can each have their own "Send thank-you card" step, but if both point at the same `batch_groups` row, their orders pool into the same batch. Release happens either when the target count is reached **or** via a manual "release now" override — no auto-timeout.

## 2. State machine

When an order reaches a step with `batch_group_id` set (see `02-Update-Data-Model.md` Part C):

1. `order_step_progress.status` → `waiting_batch`.
2. Order added to the currently `collecting` `step_batches` row for that `batch_group_id` (create one if none open), via a `step_batch_items` row recording both the order and its specific `workflow_step_id`.
3. If this brings the batch to `batch_groups.batch_size` → batch auto-transitions to `ready`.
4. If "Release batch now" is clicked at any point (even under size) → batch → `ready`, `released_manually = 1`.

**On batch becoming `ready`:**

- `action_type = 'manual_confirm'` (shipping labels): batch sits `ready`, visible on the Batches page. Nothing auto-executes. Clicking "Mark batch done" (after actually printing/actioning it externally) → batch → `done`, and **every** order in it advances to its own next step (looked up via each order's own `workflow_step_id` → its template's step sequence — not the batch's own sequence, since pooled orders may belong to different workflows).
- `action_type = 'script'` (thank-you cards): batch → `processing`, the group's `script_config` runs with the **full list of orders** in the batch as input (not one at a time). Success → batch `done`, all orders advance. Failure → same retry/backoff behaviour as an existing script step, applied to the whole batch as a unit — a partial success isn't split apart; the whole batch retries or sits in `error` together.

## 3. The two concrete applications

### Thank-you card printing (`batch_group.key = 'thank_you_card'`, `action_type = 'script'`)

`thankyou_card.py` needs a new **batch-capable mode**: given a list of up to 4 orders, lay out 4 cards on one A4 sheet rather than one card per page. This is real work on the Python script itself, not just plugin wiring — a new function/CLI mode accepting a list of orders instead of one.

Any workflow's existing "Send thank-you card" step should be updated to set `batch_group_id` to this group instead of calling the script standalone per order.

### Shipping label grouping (`batch_group.key = 'shipping_label'`, `action_type = 'manual_confirm'`)

Does **not** generate labels — Click & Drop stays fully manual, per the existing plan. This is purely organisational: once 4 orders are grouped, the Batches page shows them together (buyer name, address, order ref for each) so they can be selected as a set in Click & Drop's existing "4 per A4" label-print template in one pass. "Mark batch done" afterward advances all 4 past their "Ship" step.

## 4. UI requirements

| Page | Purpose |
|---|---|
| **Batches** | List of batches by group, each showing status and member orders/count (e.g. "3 of 4"). "Release batch now" on any `collecting` batch. "Mark batch done" on any `ready` `manual_confirm` batch. Script-type batches show their own progress/error state per §2. |

Also: an order's detail page, when `waiting_batch`, should show which batch it's in and the current count, linking through to the Batches page rather than looking stuck with no explanation.

## 5. Open items to resolve before/during build

1. **`thankyou_card.py` batch mode:** needs actual design work on the Python side — a 4-up A4 layout function given a list of orders. Treat as its own small piece of work, separate from the PHP/plugin side.
2. **Mixed-workflow batches on one thank-you card sheet:** since batching pools across all products/workflows, a single 4-up sheet could contain orders from genuinely different products. Given the thank-you card is already generic per order (only variation is the discount code for website orders), presumably fine — worth a quick confirm once laid out for real.
3. **Batch size per group vs. globally configurable:** `batch_size` is set once per `batch_groups` row — confirm 4 is right for both groups to start, or whether they might diverge later.
