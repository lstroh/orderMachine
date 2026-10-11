# Update Package 11 — Data Model Changes

*Post-ship workflow. Baseline: **v0.32.2** (+ prior packages).*

---

## A. Workflow template kind

Extend `workflow_templates.kind`:

```text
ENUM('make','pack','post_ship')  -- was make|pack
```

---

## B. Order binding

| Column | Type | Notes |
|---|---|---|
| `orders.post_ship_workflow_template_id` | BIGINT UNSIGNED NULL | Bound post-ship template |
| `orders.post_ship_started_at` | DATETIME NULL | Optional; when post-ship entered |
| `orders.arrival_confirmed_at` | DATETIME NULL | When “package arrived” confirmed |
| `orders.arrival_confirmed_by` | BIGINT UNSIGNED NULL | User id |

Progress: reuse **`order_step_progress`** for post-ship steps (same as Pack), distinguished by step → template `kind=post_ship`.  
**Open:** single `orders.current_step_id` today may point at pack **or** post-ship — **default:** add `orders.post_ship_current_step_id` **or** derive current post-ship step from progress (prefer explicit `post_ship_current_step_id` if pack current still needed during overlap). Soft default: pack finishes before post-ship starts → can reuse `current_step_id` for whichever is active; when pack done, current becomes post-ship.

---

## C. Product override

| Column | Type | Notes |
|---|---|---|
| `products.post_ship_workflow_template_id` | BIGINT UNSIGNED NULL | Optional override; null = use site default |

Site option: `som_post_ship_workflow_template_id` (like Pack default).

**Resolve order template:**  
1. Primary sellable product’s `post_ship_workflow_template_id` if set and active kind post_ship  
2. Else site default  
3. Else unbound (soft flag; manual assign)

---

## D. Order complete rule change

**Before (P6):** non-internal `is_complete` when Pack workflow finishes.  

**After:** `is_complete = 1` when:

1. Shipping details present (shipment row — already required at Pack Ship), **and**  
2. Arrival confirmed (`arrival_confirmed_at` set) via post-ship confirmation step / action, **and**  
3. Soft default: all post-ship steps done **or** arrival confirm is the designated complete gate — **default: order completes when arrival is confirmed** (later post-ship steps like Review reminder may still run on a complete order **or** complete only after last post-ship step).  

**Operator wording:** complete when shipping details entered **and** package arrived confirmed.  

**Soft default for Review reminder after arrival:** allow post-ship steps **after** arrival; set `is_complete` on **last** post-ship step **or** on arrival confirm with review still open — **recommend: `is_complete` on arrival confirm; Review reminder can remain open on complete orders** (like today’s review-after-ship). **Overturn:** complete only when entire post-ship template finishes.

---

## E. Open items

1. `is_complete` at arrival vs end of post-ship template — **default: arrival confirm; review may continue**.  
2. `current_step_id` reuse vs `post_ship_current_step_id` — **default: reuse after pack done**.  
3. Mixed products with different post_ship overrides — **default: primary sellable product wins**.  
