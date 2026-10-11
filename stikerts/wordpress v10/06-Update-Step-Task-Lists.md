# Update — Optional Step Task Lists

*Package 9 · Parallel to step instructions (defaults + product overrides). Self-contained.*

---

## 1. What this adds

Each workflow step can have an **optional task list**. While running an order, operators see the tasks for that step and can tick them complete. Ticking is **not required** to **Mark done**.

This is **separate** from existing **confirmation checklists** (print / packing / address), which remain hard gates.

## 2. Settled rules

| Topic | Decision |
|---|---|
| Authoring | **Like instructions** — step default on template + optional **per-product override** |
| Gating | **Always optional** — Mark done never blocked by unticked tasks |
| Confirmation checklists | **Keep** as the gating mechanism |
| Scope | Make + pack steps |

## 3. Behaviour

### Workflow editor

- Per step: repeatable task rows (label text), ordered.  
- Empty list = no Tasks panel on the order.  
- Help: “Optional checklist for operators. Does not block Mark done.”

### Product edit

- Same pattern as step instructions: for each step of the assigned make template, optional **task list override**.  
- Clear override = fall back to template default.  
- Soft default: override replaces the whole list (not merge) — same spirit as instruction override.

### Effective resolution

1. If product override exists for `(product_id, workflow_step_id)` → use that task list.  
2. Else → template step default tasks.  
3. Else → none.

Pack steps: order-level (no product override) unless we later add pack-product overrides — **default: pack uses template tasks only**.

### Order detail (make line / pack)

- For steps that have an effective list: show **Tasks** with checkboxes.  
- Persist tick state per progress row (make line step or pack step).  
- Show on current step at minimum; **default: current + completed steps keep their ticks visible**.  
- Mark done independent of ticks.

## 4. Data (see `02` addition)

- Template: JSON/text list on `workflow_steps` or child table `workflow_step_tasks`.  
- Override: `product_step_tasks` (like `product_step_instructions`).  
- Runtime: tick map on progress (`task_checks` JSON) keyed by task id/index.

## 5. Out of scope

- Requiring all tasks (no “require” flag in v1 — always optional)  
- Merging override with default  
- Tasks inside Markdown instructions  

## 6. Open items

1. Child table vs JSON on step — **default: JSON array on step + override table/JSON**.  
2. Show tasks only on current step vs all — **default: all steps that have lists, ticks persisted**.  
3. Pack product overrides — **default: no** (template only).  
