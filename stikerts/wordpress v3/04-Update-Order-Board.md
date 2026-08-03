# Update — Order Board

*Update set 4 of 4 (specs) · No schema changes needed — pure UI addition. Self-contained — no need to reference the original planning files.*

---

## 1. What this adds

A card-based Kanban-style view of open orders, as an alternative to the existing Orders list (table) page — both stay available side by side.

Standard Kanban patterns apply: columns represent stages, cards carry key info at a glance, filtering narrows focus, drag-and-drop is the expected interaction between columns. Implemented with **SortableJS** — a small MIT-licensed vanilla JS library loaded via CDN script tag, no React/build tooling needed, consistent with the existing plugin's plain-PHP admin approach.

## 2. Columns — dynamic

Columns are **not** a fixed global list. Since different products can use different workflow templates with differently-named steps, a column exists for each distinct step name currently held by at least one active (non-complete) order's current step, rebuilt on page load. A step with no orders currently in it has no column until an order reaches it.

**Column ordering:** default heuristic — order columns by the lowest step-order position seen for that step name across whichever workflow template(s) it appears in. Reasonable default, not a technically perfect measure since the same-named stage could sit at different positions in different workflows — flagged in §6 as worth revisiting if it looks wrong in practice.

## 3. Cards

Each card shows: order reference/external ID, channel badge (eBay/Etsy), buyer name, product name, personalisation text (truncated preview), current step name, time spent in current step (e.g. "2h", "3d" — surfaces stalled orders), and a batch indicator if the order is waiting on a batch (e.g. "Batch: 2 of 4," linking through to the existing Batches page from Update Package 1).

## 4. Filters

- Channel (eBay/Etsy)
- Product / workflow template
- Free-text search (buyer name, personalisation text, order reference)
- **"Pinned only" toggle** — a pin/star icon on each card adds it to a pinned set; toggling "Pinned only" hides everything else. Pins stored per-admin-user via WordPress user meta — no new database table needed.

## 5. Drag-and-drop — gated

- Cards live inside per-column lists using SortableJS with a shared group across columns.
- Dropping a card calls the **existing** advance-step REST endpoint (`/wp-json/som/v1/orders/{id}/advance-step`, already built as part of the base plugin) — no separate or duplicated business logic; the board is just another caller of the existing engine.
- Using SortableJS's move-validation hook, only the column matching the order's actual eligible next step is a valid drop target. Dropping into any other column is rejected client-side before the API call is made, and the card snaps back.
- **Important nuance:** dragging into the valid next-step column only satisfies a manual-confirm gate on that step — equivalent to clicking "mark done" on the existing order detail page. If the target step also has a timer or script/API/batch gate attached, the card shouldn't falsely appear complete; it should show a "pending" state rather than moving as if fully done.
- The existing order-detail view remains the place for anything drag can't fully resolve — this board is a faster way to action simple advances, not a replacement for the detail view.

## 6. UI requirements

| Page | Purpose |
|---|---|
| **Orders Board** | The card/Kanban view described above — an alternative to the existing Orders list table, not a replacement |

## 7. Open items to resolve before/during build

1. **Column ordering heuristic** (§2) — confirm "lowest step-order seen" is good enough to start, or whether manual column pinning/reordering should be built from the outset.
2. **Completed orders:** should the board only show active/incomplete orders (recommended — keeps it focused, with a link back to the full Orders list for history), or include a way to peek at recently completed ones?
3. **Narrow-screen behaviour:** horizontal scroll vs. a stacked/collapsed-column mobile view — likely low priority for a solo-user internal tool used mostly on desktop, but worth confirming.
