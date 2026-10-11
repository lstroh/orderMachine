# Update — Post-Ship Workflow

*Package 11 · Schema in `02-Update-Data-Model.md`. Self-contained.*

---

## 1. What this adds

A third workflow lane for **after the parcel is shipped**:

- Update buyer / tracking communication  
- Ensure shipping details stored  
- Confirm package arrived  
- Review reminder (timer), etc.

Uses the **same step machinery** as Make/Pack (manual, timer, confirmation, copy-command, optional tasks, Markdown instructions, unblock, planned/actual timing).

## 2. Settled rules

| Topic | Decision |
|---|---|
| Start | **Auto** when Pack Ship completes, **or manual** bind/start |
| Template | Site **default** + **per-product** override |
| Order complete | Shipping details entered **and** package **arrived** confirmed (not at Pack Ship alone) |
| Board | Separate **Post-ship board** |
| Review reminder | On Post-ship (not Pack seed) |
| Trays / package materials | **No** |

## 3. Lifecycle

```
Make (per line) → Pack (order) → Ship step (shipment recorded)
       → Post-ship starts (auto and/or manual)
       → … steps …
       → Confirm arrived → order complete (see open item on review-after)
       → optional Review reminder (timer)
```

Internal Produce N: **no** post-ship (no pack/ship path).

## 4. Template resolution

1. Primary sellable line’s product `post_ship_workflow_template_id` if set  
2. Else Settings **Default Post-ship workflow**  
3. Else unbound — UI flag; **Manual start** can pick a template  

## 5. Start modes

| Mode | Behaviour |
|---|---|
| Automatic | On successful Pack Ship Mark done (shipment present): bind template if needed, insert progress, enter first step |
| Manual | Order detail / Post-ship board: **Start post-ship** (if not started); optional choose template if unbound |

Settings soft default: **Auto-start post-ship = on** (checkbox).

## 6. Order complete

Pack Ship **no longer** sets `is_complete` for customer orders.

Complete when:

- Shipment / shipping details exist, **and**  
- Arrival confirmation recorded (dedicated confirmation kind or step action `confirm_arrival`)

See `02` §D for review-reminder vs complete timing.

## 7. UI

| Surface | Purpose |
|---|---|
| Settings | Default post-ship template; auto-start toggle |
| Products | Optional post-ship template override |
| Workflows | Create/edit kind Post-ship |
| Order detail | Post-ship panel (progress, unblock, tasks, copy-command) |
| **Post-ship board** | Order cards by post-ship step (+ Unstarted / Complete filters) |

## 8. Seed (soft default)

**Post-ship — Order Aftercare** (name TBD):

1. Update buyer / send tracking (manual or copy-command)  
2. Confirm shipping details (manual / checklist)  
3. Confirm package arrived (confirmation — complete gate)  
4. Review reminder (timer + manual)  

Remove Review reminder from Pack seed; migrate docs for existing sites.

## 9. Out of scope

- Auto eBay/Etsy messages (unless copy-command only)  
- Trays on post-ship  
- Split shipments / multi-carton aftercare  

## 10. Open items

1. Complete at arrival vs last post-ship step — **default: arrival; review may continue on complete orders**.  
2. Auto-start default on — **default: yes**.  
3. Board columns for multi-template — **default: filter by template** (like Make board).  
