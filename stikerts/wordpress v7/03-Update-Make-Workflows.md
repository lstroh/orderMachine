# Update — Per-line Make Workflows & Make Board

*Package 6 · Schema in `02-Update-Data-Model.md` §A. Self-contained.*

---

## 1. What this adds

Replaces **one order-level workflow from the primary product** for manufacturing steps with:

1. **Per-line make progress** for each sellable `order_item` (using that line’s product `workflow_template_id`).
2. A **Make board** oriented around **order lines** (not whole-order pack/ship steps).
3. Product make templates that **end at ready to pack** (Cut / last make step) — no Pack, Ship, Thank-you, or address confirm on the product template.

## 2. Settled rules

| Topic | Decision |
|---|---|
| Unit of make | **Order line** (sellable product) |
| Internal lines | Always **pack-ready**; no make progress rows |
| Internal Produce N orders | Stay on Make / Internal channel only; **never** Pack board |
| Template content | Make only → ends when line is ready to pack |
| Primary-product rule | **Retired** for assigning make+pack as one chain |

## 3. Behaviour

### Assign on order create

For each `order_item` on a **non-internal-channel** order:

| Line | Action |
|---|---|
| `product_id` null | No make progress; flag unmatched (existing UX) |
| Product `is_internal` | No make progress; treat as pack-ready |
| Sellable + has make template | Insert `order_item_step_progress` for each make step; start first step |
| Sellable + no template | No make progress; flag unassigned make — **open item** for Ship gate |

Also bind Pack template on the order (see `04`) unless channel is `internal`.

### Advance make

- Mark done / timers / scripts / confirmations run **in the context of an order line** (UI: order detail Make section + Make board).
- Batch groups on **make** steps: **open item** — recommend disallow batch on make templates in v1 (pack removed thank-you batch; shipping_label batch unused by seed).
- When the last make step for a line is `done`, that line becomes **pack-ready**.

### Order detail (Make section)

- List each sellable line with product name, personalisation, current make step, Mark done (line-scoped).
- Internal lines: badge “Pack-ready (internal)” — no make controls.
- Do not show Pack/Ship controls here beyond a link to Pack board / pack panel.

### Make board

**Purpose:** Day-to-day production queue for **lines being made**.

| Behaviour | Notes |
|---|---|
| Cards | One card per **in-progress sellable line** (order id + product + personalisation snippet) |
| Columns | Steps of a selected make workflow template (filter), or “In progress” buckets — **open item** (recommend: filter by workflow template; columns = that template’s steps) |
| Drag / Mark done | Advances **that line’s** make step (same gates as today, line-scoped) |
| Excludes | Internal production orders may appear if desirable for Produce N — **open item** (recommend show Internal channel lines on Make board only; never on Pack) |
| Pack-ready lines | Leave Make board (or land in a final “Ready” column that is informational) |

Existing **Orders Board** (`som-orders-board`): **open item** — replace with Make board, rename, or keep as Pack-only. Recommend: **repurpose** current board route into Make board and add Pack board menu item (or reverse). Settled product intent: two boards — Make + Pack.

## 4. UI requirements

| Page | Purpose |
|---|---|
| Make board (new or repurposed) | Line Kanban |
| Order detail | Per-line make progress |
| Product / workflow editor | Make templates only assignable to products; warn if template still contains pack-ish steps (heuristic or `kind=make`) |
| Workflows list | Filter/badge Make vs Pack templates |

## 5. Out of scope

- Customer-facing make tracker  
- Capacity planning / work centres  
- Auto-splitting lines across operators  

## 6. Open items

1. Make board column model when multiple make templates exist.  
2. Fate of legacy `som-orders-board` URL/label.  
3. Sellable line with no workflow: block Ship or allow pack with warning?  
4. Batch groups allowed on make steps in v1? (recommend no)  
5. Show Produce N internal jobs on Make board? (recommend yes)
