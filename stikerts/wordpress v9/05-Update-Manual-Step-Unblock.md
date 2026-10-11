# Update — Manual Step Unblock

*Package 8 · Extends / replaces “timer-only early release”. Self-contained behaviour spec.*

---

## 1. What this adds

Admins can **Unblock step** on the **current** make or pack step when any gate is holding **Mark done**. Unblock clears the block; the operator still clicks **Mark done**.

## 2. Settled rules

| Topic | Decision |
|---|---|
| Effect | Allow **Mark done** — does **not** auto-advance |
| Availability | **Always** (no per-step opt-in) |
| Scope | **All** gates that can block advance |
| Audit | Log who/when (+ order note) |

## 3. Gates covered

| Gate / block | Unblock behaviour (soft default) |
|---|---|
| `waiting_timer` | Unlock as if countdown ended (timer ready) |
| Confirmation checklist incomplete | Treat checklist as satisfied for `can_mark_done` (or mark complete in state) |
| `waiting_script` / script `error` | Clear wait/error so Mark done allowed (skip success callback) |
| `waiting_batch` | Remove from open batch / clear waiting_batch so Mark done allowed on the order/line |
| Ship: missing shipment | Allow Mark done without shipment row **for this override** (still prefer recording shipment) |
| Ship/pack: missing package | Allow Mark done without `shipping_package_id` for this override |
| Pack hold | Allow Mark done while held **or** clear hold for this override — **default: override Ship block only for this Mark done, leave hold flag unless operator clears hold separately** |
| Make-ready / other pack Ship gates | Same pattern: one-shot override flag so `can_mark_done` passes |
| Trays required but none assigned | Allow Mark done for this step without trays (does not assign trays) |

**Implementation sketch:** set `manual_unblock_at` / `manual_unblock_by` on the progress row (and/or a one-shot `gates_bypassed` flag cleared after Mark done). `can_mark_done` returns true when bypass is set.

## 4. UI

| Surface | Control |
|---|---|
| Order detail (current make step / current pack step) | **Unblock step** button when Mark done is disabled due to a gate |
| Boards (optional) | Same if cheap — **default: order detail first; board in polish if easy** |

Confirm copy: “Allows Mark done without meeting this step’s gates. Does not complete the step.”

## 5. Audit

- Progress columns: `manual_unblock_at`, `manual_unblock_by` (generalise; timer-only columns can merge into these or sit alongside).  
- Order note: “Step {name} unblocked by {user} ({gates}): …”  

## 6. Out of scope

- Auto Mark done on unblock  
- Unblocking non-current / future steps  
- Skipping multiple steps at once  

## 7. Open items

1. Hold flag: leave set vs clear on unblock — **default: leave hold; only bypass Ship gate check**.  
2. Batch: remove member from batch vs leave orphan member — **default: remove from collecting/ready batch when unblocking**.  
3. Board Unblock control in v1? **Default: order detail only**.  
