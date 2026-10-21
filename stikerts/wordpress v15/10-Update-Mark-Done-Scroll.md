# Update — Mark done stays in place

*Package 14 · UP14-S10 · No schema. Operator request, settled 2026-10-21.*

---

## 1. What happens today

**Pack** Mark done, including the Ship step inside Pack, is marked `data-som-advance-step`. The script calls `POST /som/v1/orders/{id}/advance-step` and then `window.location.reload()`. The scroll position stays.

**Make** Mark done is a normal form post. The handler redirects to the order URL with no fragment, so the page opens at the top.

**Post-ship** Mark done is the same kind of form post, so it also opens at the top. The operator confirmed it must stay put. The Ship step inside Pack already stays.

The REST route already accepts `order_item_id` and calls `SOM_Item_Make::mark_done`. Pack omits that id and advances the order-level step.

## 2. Behaviour

| Button | After this sprint |
|---|---|
| Make Mark done | Same stay-put reload as Pack. The request includes that line’s `order_item_id`. |
| Post-ship Mark done | Same stay-put reload. No `order_item_id` (order-level step). |
| Pack / Ship Mark done | Unchanged |
| Unblock, checklists, trays, shipment, notes | Still full posts. Out of this sprint |

On failure, show the same alert Pack uses and leave the button usable. Do not navigate away.

If the REST settings are missing, the form may still post. That fallback is unchanged.

No new route. No schema.

## 3. Tests and docs

`tests/sprint-up14-s10-smoke.php`

- The make Mark done form carries `data-som-advance-step` and the line’s `order_item_id`.
- The post-ship Mark done form carries `data-som-advance-step` and no item id.
- `initAdvanceStepRest` sends `order_item_id` when the form has one, then reloads.

`USER-REFERENCE.md` order detail: Mark done on Make, Pack, and Post-ship leaves you at the same place on the page.
