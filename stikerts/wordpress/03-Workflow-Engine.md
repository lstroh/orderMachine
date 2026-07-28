# Workflow Engine — Design

*Detailed design, part 3 of 4 · Builds on `01-Data-Model.md` (`workflow_templates`, `workflow_steps`, `order_step_progress`) · This is the most novel piece of the build — a small state machine, not just a status dropdown.*

---

## 1. Step types and their config

Every `wp_som_workflow_steps` row can combine any mix of the three gate types — a step isn't necessarily one type; a single step could require manual confirmation *and* have a timer, for example.

### Manual confirmation
- `requires_manual_confirm = 1`.
- UI shows a "Mark done" button/checkbox on the order's current step.
- No config needed beyond the flag itself.

### Timer (hard gate)
- `timer_seconds` set to a value > 0.
- When the order reaches this step, `order_step_progress.status` → `waiting_timer`, `timer_ends_at` = now + `timer_seconds`.
- The "Mark done" action for this step is **disabled in the UI** (not just discouraged) until `timer_ends_at` has passed — hard gate per your decision.
- A visible countdown is shown so you know when it'll unlock.

### Script/API trigger
- `script_config` JSON, one of three shapes depending on `type`:

```jsonc
// type: "local" — runs a registered PHP action/shell command on the server running WordPress
{
  "type": "local",
  "action": "send_print_job",      // maps to a registered handler in the plugin, not arbitrary code from the DB
  "params": { "printer": "epson_ecotank" }
}

// type: "api" — calls an arbitrary external HTTP endpoint (e.g. pushing tracking to eBay/Etsy)
{
  "type": "api",
  "method": "POST",
  "url": "https://api.ebay.com/sell/fulfillment/v1/order/{order_id}/shipping_fulfillment",
  "body_template": { "trackingNumber": "{{tracking_number}}", "shippingCarrierCode": "ROYAL_MAIL" }
}

// type: "n8n" — triggers a self-hosted n8n workflow via webhook
{
  "type": "n8n",
  "webhook_url": "https://your-n8n-instance/webhook/thank-you-card",
  "payload_template": { "order_id": "{{order_id}}", "buyer_name": "{{buyer_name}}", "product": "{{product_name}}" }
}
```

- `{{...}}` placeholders are resolved from the order/order_item record at execution time — keep this to a small fixed set of known fields (order id, buyer name, product name, personalisation text, tracking number if present) rather than a full templating language, to avoid scope creep.
- **Local action allowlist:** `type: "local"` should dispatch to a small registered set of named PHP functions in the plugin (e.g. `send_print_job`, `run_thankyou_card_script`), never eval arbitrary stored code — this keeps the config data-only and avoids turning the workflow editor into an arbitrary-code-execution surface.

## 2. Order state machine

When an order is created (synced in from a channel):
1. Its product's `workflow_template_id` is looked up.
2. One `order_step_progress` row is created per step in that template, all `pending`, in `step_order`.
3. `orders.current_step_id` is set to the first step.
4. The first step's row moves to `in_progress` (or `waiting_timer` / `waiting_script` immediately if it has no manual-confirm requirement and only a timer/script gate).

**Advancing a step**, once all its required gates are satisfied (manual confirm clicked AND timer elapsed AND script succeeded — all three if all three are configured on that step):
1. Current step's `order_step_progress.status` → `done`, `completed_at` = now.
2. Next step (by `step_order`) becomes `orders.current_step_id`, its row moves to `in_progress`/`waiting_timer`/`waiting_script` as appropriate.
3. If there is no next step, `orders.is_complete = 1`, `current_step_id = NULL`.

**Script/API step execution + retry:**
1. Step becomes `waiting_script`.
2. Plugin cron (or an immediate attempt on step-entry, with cron as the retry mechanism) attempts the call.
3. On success → proceed as above.
4. On failure → `retry_count++`, retry with a short backoff (suggest: 3 attempts total, e.g. immediate / +1 min / +5 min).
5. After the retry budget is exhausted → `status = error`, `last_error` populated, order visibly flagged in the UI for manual retry (a "retry now" button that re-runs the same call and resets into the retry cycle).

## 3. Two confirmed workflow steps, worked through as examples

**"Send thank-you card" step:**
```json
{
  "type": "local",
  "action": "run_thankyou_card_script",
  "params": { "script_path": "thankyou_card.py" }
}
```
Likely implemented as a PHP `shell_exec`/`proc_open` call to the existing Python script (see `thankyou_card_README.md` for its interface), passing order details as arguments or a temp JSON file. Flag for detailed design: decide the exact call contract (CLI args vs. stdin JSON) against the actual script signature in `thankyou_card.py`.

**"Review reminder" step:**
- This one is different in character from the others — it's not really something to "complete" immediately, it's a *delayed* nudge.
- Suggested modelling: a timer step with a multi-day `timer_seconds` (e.g. 7–10 days after shipping — exact figure a business decision, not a technical one), which unlocks a manual-confirm action like "Review request sent" rather than blocking anything further (since it's likely the last step in the workflow, blocking nothing).
- Alternative modelling worth considering during build: since this doesn't gate anything downstream, it could instead be a simple flagged reminder list ("orders ready for a review nudge") outside the step-progress mechanism entirely, if forcing it into the same state machine as production steps feels awkward once you see it in practice.

## 4. Execution/scheduling

- **WP Cron tick** (suggest every 1–5 minutes) checks for:
  - Steps in `waiting_timer` whose `timer_ends_at` has passed → auto-unlock (still needs the manual-confirm click if that's also required, per the step's flags).
  - Steps in `waiting_script` due for a retry attempt.
- WP Cron is request-triggered by default (fires on page load), which is unreliable for a tool that needs timers to fire even when nobody's browsing — **use a real server cron hitting `wp-cron.php` on a fixed schedule**, or disable `DISABLE_WP_CRON` and set up a system cron job, rather than relying on default WP Cron behaviour.

## 5. Open items to resolve before/during build

- **Multi-item orders spanning two workflow templates** (flagged in Data Model §4) — needs deciding before this engine can assume "one workflow per order."
- **`thankyou_card.py` call contract** — needs the actual script's expected input format nailed down (it currently takes an `orders` list as Python dicts in `__main__`, not a CLI/stdin interface yet — a small wrapper may be needed on the Python side, not just the PHP side).
- **Review-reminder modelling** (§3 above) — worth a quick decision once you see the workflow editor in practice; both options are cheap to build, not worth over-deciding now.
