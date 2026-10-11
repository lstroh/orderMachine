# Order Machine — User Reference

*Screen-by-screen reference for operators.*  
*Hub: [`USER-GUIDE.md`](USER-GUIDE.md) · Procedures: [`USER-WORKFLOWS.md`](USER-WORKFLOWS.md)*

Tone: what each screen is for, main actions, important rules, and what you will not find. For QA depth see [`FEATURES-AND-TESTING.md`](FEATURES-AND-TESTING.md).

---

## Contents

1. [Settings & channels](#1-settings--channels)
2. [Order sync behaviour](#2-order-sync-behaviour)
3. [Orders list & detail](#3-orders-list--detail)
4. [Make Board](#4-make-board)
4b. [Pack Board](#4b-pack-board)
4c. [Post-ship Board](#4c-post-ship-board)
5. [Products](#5-products) (incl. multipacks / pack sizes)
5b. [Shipping packages](#5b-shipping-packages)
6. [Materials & stock](#6-materials--stock)
7. [Workflows](#7-workflows)
8. [Workflow engine (how progress moves)](#8-workflow-engine-how-progress-moves)
9. [Listings](#9-listings)
10. [Suppliers & purchase orders](#10-suppliers--purchase-orders)
11. [Landed cost, WA & goals](#11-landed-cost-wa--goals)
12. [Batches](#12-batches)
13. [Budgets](#13-budgets)
14. [Platform selling fees](#14-platform-selling-fees)
15. [Analytics](#15-analytics)
16. [Automation (REST & MCP)](#16-automation-rest--mcp)
17. [What is out of scope for now](#17-what-is-out-of-scope-for-now)

---

## 1. Settings & channels

**Where:** Order Machine → Settings

**Pack:** choose **Default Pack workflow** (active templates with kind Pack). Bound on new non-Internal orders for the Pack Board and Ship gates. Create Pack templates under Workflows.

**Post-ship:** choose **Default Post-ship workflow** (kind Post-ship) and **Auto-start Post-ship** (default on). Used after Pack Ship for aftercare; products may override. Create Post-ship templates under Workflows.

**Trays:** **Max trays** (site pool **1–20**, default **10**). Numbers are **1 … max**. Lowering is blocked while a higher tray is still held.

**What it is for:** Connect marketplaces, pull orders and fees, tune background intervals, manage API/MCP access, and (in dummy mode) seed data tools.

**Main actions:**

- Save eBay / Etsy app client ID + secret (secrets encrypted; leave blank to keep existing)
- Copy OAuth callback URLs into developer apps (**live**)
- **Connect** / **Disconnect** each channel
- **n8n base URL** (deprecated — script/n8n steps removed; kept for existing installs only)
- Set **Max trays**
- Intervals: order poll, engine tick, token refresh, fee poll (default 30 min, min 5)
- Toggle **MCP / Abilities**; manage **REST API key**
- **Sync now** — incremental orders (fixtures when dummy)
- **Import history** — 30 or 90 days backfill
- **Platform fee sync** — status, **Sync fees now**, eBay Finances reconnect notice when needed
- **Remove seed data** / **Restore seed data** (restore requires dummy mode)
- **Repair pack binding** — bind Default Pack on open unbound non-Internal orders (skips legacy monolithic progress); clear leftover thank-you batches under Batches manually if needed

**Important rules:**

- **Dummy:** connected tokens are fake; Sync uses fixtures.  
- **Live:** real OAuth; changing `SOM_ENCRYPTION_KEY` after saving credentials breaks decrypt — reconnect.  
- Cron still runs in the background; manual Sync buttons are for immediate pulls.
- No trays overview page — assign/release on order detail only.

**You will not find:** Per-order editing of channel credentials; exposed raw secrets in the UI after save; a site-wide trays occupancy screen.

---

## 2. Order sync behaviour

**Where:** Settings → Sync now / Import history (or cron)

**What it is for:** Bring channel orders into Order Machine without duplicates.

**Main behaviour:**

- De-duplicates on channel + external order ID (re-sync updates; does not create duplicates)
- Matches line items via Listings (`external_listing_id` ↔ product)
- Unmatched lines keep no product and are flagged
- Best-effort personalisation text extraction
- On **new incremental creates** (not history, not cancelled): assign workflow, reserve materials, fund matching budgets

**Important rules:**

| Mode | Workflow | Stock reserve | Budget funding |
|---|---|---|---|
| Sync now (new create) | Yes (if primary product has template) | Yes (if recipe matches) | Yes (if budgets match) |
| Import history | No | No | No |
| Cancelled create | No fresh reservation | No | No |

**Dummy fixtures:** roughly half a dozen orders across eBay/Etsy — matched, unmatched, and cancelled mix. Seeded listing IDs commonly include eBay `110000000001` / SKU `BIN-SET-4PK` and Etsy `220000000001`.

---

## 3. Orders list & detail

**Where:** Order Machine → Orders · detail via `order_id`

**What it is for:** Find orders, inspect packing data, advance non-batch steps, see fees and stock impact.

**List — main actions:**

- Filters: status, channel, date range, search
- Badges: open, complete, cancelled, unmatched items, no workflow

**Detail — main sections:**

- Buyer and totals
- Personalisation (prominent when present)
- Shipping address
- Line items (matched product or unmatched warning)
- Workflow progress: current step, timers, **waiting_batch** badge + Batches link
- **Step timing** — Planned · Actual (wall clock) on completed steps; live Elapsed on the current step; Make total / Pack total footers (planned sum · span actual)
- **Step instructions** — Markdown rendered as HTML on make/pack progress (product override → step default); plain text still works
- **Tasks** — optional tick list on make/pack steps that have an effective list (product override → template default for make; template only for pack). Shown on current + completed steps; Save tasks persists ticks. **Does not block Mark done.** Separate from Confirmation checklist.
- **Copy command** on make lines when the step has a `command_template` — monospace rendered command + **Copy** (not executed by WordPress); then Mark done
- Editable line fields: house number, street name, style (and personalisation summary) for placeholders / bad eBay payloads
- **Confirmation checklist** nested under the **current** make/pack step when that step has a confirmation kind (print / address / packing) — Save checklist, then Mark done (checklist Save does not mark the step done). Hard gate — not optional Tasks.
- **Unblock step** on the current make/pack step when Mark done is gated (confirm: allows Mark done without meeting gates; does not complete the step)
- **Open on eBay/Etsy** link when channel supports it
- **Mark done** when gates allow (hidden while waiting on a batch; locked until confirmation checklist is complete; also blocked when trays are required and none held)
- **Trays** — order-level panel: list held trays; multi-select free trays to assign; release selected held trays
- **Materials used** — planned from product **Recipe** and selected shipping **Package**; raise Actual above planned when you used more (stock, profit, budget); increase-only; same material can appear once per source
- **Platform fees** panel when synced fee lines exist
- **Notes** — admin-only append-only log (cannot edit/delete); not synced to marketplaces
- **Planned shipping** — expected postage (seeded from product defaults on create; editable; re-sync does not overwrite). Variance vs shipment **postage paid** when recorded. Internal orders leave planned null
- Raw channel payload in a collapsed block

**Important rules:**

- One workflow per order from the **primary product** = first line with a matched product.
- If nothing matches → no progress rows; flags show unmatched / no workflow.
- Batch advance is batch-level only while `waiting_batch`.
- Confirmation ticks persist on the order; Mark done / Board drag stay locked until every required box is saved.

**Trays (order-level) — concise rules:**

| Rule | Behaviour |
|---|---|
| Holder | **Order** only (shared by all make lines + pack; not per line) |
| Double-book | Rejected — a held tray cannot be assigned to another order |
| Required | If any sellable make template or bound Pack template has **Uses trays** → block Mark done / advance until ≥1 tray held |
| Keep ≥1 | While required and order open, cannot release the last held tray |
| Auto-release | On order complete → all held trays freed (`order_complete`) |
| Manual release | Anytime (subject to keep ≥1 when required) |
| Transfer | No make→pack transfer (same order-level set) |

**Unblock step — concise rules:**

| Rule | Behaviour |
|---|---|
| Where | **Order detail only** (not on Make/Pack boards) |
| Effect | Enables **Mark done** for the current step; does **not** Mark done / auto-advance |
| Gates | Bypasses timer, checklist, script/error, batch, ship/package/hold, trays-required |
| Pack hold | Leave hold flag set; only bypass Ship check for this Mark done |
| Batch | Removes the line/order from the collecting/ready batch |
| Trays | Allows Mark done without assigning trays (does not assign any) |
| Audit | Progress who/when/gates + order note; audit remains after Mark done |

---

## 4. Make Board

**Where:** Order Machine → Make Board

**What it is for:** Day-to-day **make** queue as a Kanban of open **order lines** (per-line make progress).

**Main actions:**

- Filter by make workflow template; columns = that template’s steps (+ **Unassigned**)
- Pin cards; filter channel / free-text
- Drag advanceable line cards to the next make step
- Open order detail (Make section) via card links

**Important rules:**

- Cards are **lines**, not whole orders. Advancing one line does not advance sibling lines.
- Internal Produce N lines appear here; they never go to the Pack Board.
- Incomplete / non-cancelled only; completed history stays on Orders.
- Held tray numbers show as **Tray N** badges on cards (order-level set).

**You will not find:** Pack/ship columns (use Pack Board); free drag to arbitrary past steps; **Unblock step** (use order detail).

---

## 4b. Pack Board

**Where:** Order Machine → Pack Board

**What it is for:** Pack & ship queue as a Kanban of open **orders** (not Internal).

**Main actions:**

- Columns include **Waiting for make**, **Held**, then current pack step names
- Filter channel / free-text; open Pack panel on order detail
- Drag advanceable cards when make-ready and not held

**Important rules:**

- New non-Internal orders bind the Settings **Default Pack workflow** (kind Pack). If unset, orders soft-flag and **Ship stays blocked** until a Pack template is configured — then Settings → **Repair pack binding**.
- **Ship** stays blocked until: all sellable lines make-complete, no pack hold, shipping package selected, packing checklist + thank-you tick, address confirm, and shipment row recorded.
- Packed-by / packed-at stamps once when the packing checklist first completes; optional pack weight (g) on the shipment.
- Browser print on order detail renders a pack list (buyer, address, lines, notes).
- Held tray numbers show as **Tray N** badges on cards.

**You will not find:** Internal / Produce N orders; make-line cards (use Make Board); **Unblock step** (use order detail); Review reminder (moved to Post-ship).

---

## 4c. Post-ship Board

**Where:** Order Machine → Post-ship Board

**What it is for:** Aftercare queue after the parcel is shipped — buyer/tracking update, arrival confirm, review reminder.

**Main actions:**

- Scope filters: **Active** (in-progress aftercare), **Unstarted** (shipped, not started), **Complete** (all post-ship steps done)
- Filter by **Post-ship workflow** (fixes Kanban columns to that template’s steps), channel, search
- Cards: **Start** (unstarted when a template resolves) / **Open** order detail; pin; drag advance when gates allow
- Order detail **Post-ship** panel: progress, instructions, tasks, copy-command, confirmation (incl. package arrived), unblock, timer, planned/actual timing

**Important rules:**

- Starts **auto** when Pack Ship completes (Settings toggle, default on) and/or **manual** Start on order detail / board.
- Template resolve: primary product Post-ship override → site default → unbound (pick on Start).
- **Order complete** = shipping details recorded **and** package arrived confirmed — **not** at Pack Ship alone. Review reminder may continue after complete.
- No trays / package materials on post-ship. Internal Produce N never gets post-ship.

**You will not find:** Pack Ship columns; tray badges; marketplace send APIs (use Copy command locally).

---

## 5. Products

**Where:** Order Machine → Products

**What it is for:** Catalogue SKUs that drive matching, recipes, workflows, and costing.

**Main actions:**

- Create / edit name, SKU, active flag (deactivate rather than hard-delete)
- Toggle **Internal product** for make-to-stock components (linked output material; **Produce N** creates an Internal order)
- Assign workflow template
- **Step instruction overrides** (Markdown; leave blank for workflow default; Preview in editor)
- Edit material recipe (material + qty per unit; cycles and nesting deeper than 5 levels are rejected)
- Set target selling price; review **Product Costing** (recipe cost, platform fees £/% estimate vs actual, planned shipping when set, fee-aware profit/margin, goal alerts, listing prices)
- For sellable products: goods weight (g), **default package** (qty 1), optional **multi-pack package** (qty 2+), flat **planned shipping** £ (hidden on internal products)
- Follow links to related Listings (internal products are excluded from marketplace listing pickers)

**List columns:** target price, material cost, fee-aware margin with Est./Actual fees badge, goal alerts, Internal badge; type filter sellable/internal.

**Deactivate:** blocked while open Internal production jobs exist for that product.

**Important rules:**

- Primary product on an order is the first matched line — that product’s workflow applies to the whole order.
- Representative price for fee %: target for estimates; listing price when a channel listing is linked.

### Multipacks (pack sizes)

A **multipack** is N identical units sold as one listing/SKU (e.g. bin sticker **4-pack**). Bundles of different products are out of scope.

| Topic | Convention |
|---|---|
| Catalogue | **Separate product** per pack size (1-pack, 4-pack, 10-pack, …) |
| Recipe | Materials for **one sold unit** of that pack (qty per unit already scales) |
| Shipping | Own goods weight, default + multi-pack packages, and planned postage per SKU |
| Engine | No `base_product_id` / `units_per_pack` — deferred; no pack→base inventory explosion |

**Naming:** e.g. `Bin Sticker Set — 4-pack` / `BIN-SET-4PK` (seed style). Do **not** create a phantom loose-unit SKU unless you sell singles. Seed ships one sample 4-pack only — add other sizes as normal products when needed.

Also see order **Notes** (§3) and **Planned shipping** on order detail (§3) / packages below.

---

## 5b. Shipping packages

**Where:** Order Machine → Shipping packages

**Package materials (BOM):** On edit, define a material recipe (e.g. thank-you + envelope). Selecting that package on an order reserves those quantities once; Materials used shows them under Source **Package**. Packaging materials live on the package — not on the product recipe or Pack workflow.

**What it is for:** Catalogue of outer mailers/boxes (L×W×H mm, tare grams). Assign on sellable products with goods weight and flat planned postage. Actual postage is recorded on the order shipment, not here.

**Main actions:** Create / edit; set one site **default**; deactivate (prefer over delete); delete blocked while products still reference the package (default or multi-pack).

**Order package suggestion (1 vs 2+):**

| Order shape | Suggested package |
|---|---|
| Total sellable qty **1** | Product **Default package** |
| Total sellable qty **≥ 2** (same or mixed SKUs) | Each line votes **Multi-pack package** if set, else Default; if all agree → that package |
| Mixed SKUs disagree | Site default package + Pack UI flag **Mixed products — confirm package** |

Operator can always override on the Pack panel; changing package reverses prior package reserves and reserves the new BOM.

**Create test order:** Orders list → addable lines (product + qty + optional unit price / personalisation, up to 5) for multi-qty and mixed-SKU tests without channel sync.

**With multipacks:** each pack-size SKU usually gets its own planned £; use Default vs Multi-pack package when one vs several sold packs need different outer packaging.

---

## 6. Materials & stock

**Where:** Order Machine → Materials

**What it is for:** Track raw materials, costs, stock movements, and purchase history.

**Main actions:**

- CRUD materials (name, unit, stock, low-stock threshold, active)
- Preferred supplier
- Read WA and total value on hand
- **Unit cost override** — revalues value on hand; logs the change
- **Manual stock adjust** — delta; does **not** touch budgets (corrections/counts)
- **R&D / non-sale write-off** — stock down + restock-pot debit when an active material budget exists (notes required). Sales fund the pot; purchases and R&D draw it down
- Goal-alert badges; per-workflow breakdown on edit
- Lead time from past POs; purchase history table; recent stock log
- **Made in-house** badge when linked to an internal product; **Produce N** when that material is low stock (no auto job)

**Auto-decrement on new orders:**

- Incremental create of a matched order reduces stock by recipe × qty
- Stock may go **negative** (shortage signal)
- Skipped for history import, cancelled, unmatched-only
- Idempotent per order (re-sync does not double-reserve)

**You will not find yet:** Automatic stock reversal when an order is cancelled later.

---

## 7. Workflows

**Where:** Order Machine → Workflows

**What it is for:** Define production templates and step gates.

**Templates:** create / edit / deactivate; name + description; **Uses trays** checkbox (make or Pack).

**Uses trays:** when **any** make template on a sellable line or the bound Pack template has this on, the order requires ≥1 held tray before Mark done / advance. Seed **Bin Sticker Make** and **Order Pack & Ship** leave this **off** (operator opts in).

**Step editor — main actions:**

- Add / remove / reorder steps
- **Instructions (default)** — Markdown (bold, lists, links, inline code, `##` / `###`); cheatsheet + Preview under the textarea. Max 5000 characters. No images; no TinyMCE / HTML editor. Products may override per step.
- **Tasks (optional)** — repeatable task rows (stable ids + labels). Empty = no Tasks panel. Help: does not block Mark done. Make products may override the whole list (full replace, not merge). Pack uses template tasks only.
- Requires manual confirm
- **Copy command template** — `{{placeholders}}` filled per make line; Copy + Mark done (not run by WordPress). Exclusive with timer and batch; implies manual confirm. May combine with confirmation checklist. Stays separate from instructions (not embedded in the Markdown body).
- **Confirmation checklist** kind (print vs request / shipping address / packing items) — exclusive with timer and batch; hard gate (unlike optional Tasks)
- Timer (friendly min/hr/day → seconds) — **hard gate**; not the same as planned duration
- **Planned duration** (minutes / hours / days → `planned_seconds`) — target for reporting only; can differ from the timer. Template shows a **planned total** (sum of steps; “partial plan” if some steps have no plan)
- Assign **batch group** (batch-only step in v1; combo with other gates is rejected). Script batch groups soft-fail on release.
- Template-level **material cost goals**

**Planned vs timer vs actual:**

| Concept | Role |
|---|---|
| Planned duration | Target for ops reporting / Analytics averages |
| Timer | Blocks Mark done until countdown ends (or Unblock) |
| Actual | Wall clock from `started_at` → `completed_at` (includes timer waits). Make/pack totals = first start → last complete |

On **order detail**, each step shows Planned · Actual (or live Elapsed while in progress). Make-line and Pack footers show planned sum · span actual.

**Instructions Markdown (v1):** paragraphs/line breaks, **bold** / *italic*, bullet + numbered lists, links, inline `code`, simple headings. Unsafe HTML is stripped. Legacy plain text still renders readably.

**Removed:** local / API / n8n `script_config` UI and runtime dispatch. Legacy rows show a disabled warning in the editor and clear on save; at runtime they are ignored (treated as manual Mark done).

**Placeholders (v1):** `{{order_number}}`, `{{house_number}}`, `{{street_name}}`, `{{style}}`, `{{buyer_name}}` (first word), `{{buyer_full_name}}`, `{{product_name}}`, `{{personalisation}}`.

**Seeded make — Bin Sticker Make:** Print (copy-command `/make-sticker-order` example) → Confirm print → Dry → Laminate → Cut.  
**Seeded pack — Order Pack & Ship:** Confirm pack (lines + thank-you) → Confirm address → Package → Ship. Review reminder is on Post-ship (not Pack). No thank-you batch on Pack. Existing Local templates are not auto-truncated — Restore seed or edit manually, then **Repair pack binding**.  
**Seeded post-ship — Post-ship — Order Aftercare:** Update buyer / send tracking (copy-command) → Confirm shipping details → Confirm package arrived → Review reminder (timer 7 days + manual). Set as Default Post-ship when unset.

---

## 8. Workflow engine (how progress moves)

**What it is for:** Understanding why Mark done / Board drag works or does not.

**Triggers:**

- New order create (incremental) → assign steps from primary product’s template
- Admin Mark done / Board advance when gates allow
- Cron engine tick → unlock timers; batch retries for non-script groups only
- Batch release / mark-done → advance all batch members (script batches soft-fail; do not run)

**Gates:**

| Gate | Behaviour |
|---|---|
| Manual | Mark done / drag only when current and allowed |
| Copy command | Display-only on make line; never `waiting_script`; Mark done stays manual |
| Tasks | Optional ticks on order detail; **never** required for Mark done / drag |
| Confirmation | Checklist on order detail must be saved complete before Mark done / drag |
| Timer | Blocked until countdown ends (or tick unlocks) |
| Script | **Removed** — legacy `script_config` ignored at runtime (manual Mark done) |
| Batch | Entering sets `waiting_batch` and enqueues; other gates ignored on that step in v1. Script action groups soft-fail on release |
| Trays required | If order requires trays and none held → Mark done / drag blocked until ≥1 assigned (or Unblock) |
| **Zero-gate** (none of the above) | Step **auto-completes** as soon as it is entered, and the engine advances to the next step. Useful for intentional pass-throughs. The workflow editor warns on Save (does not hard-block) and defaults **Requires manual confirm** on newly added step cards |

**Unblock step:** on order detail for the current make/pack step when any gate blocks Mark done. One-shot bypass so `can_mark_done` passes; you still click Mark done. See §3 rules table.

**Important:** Workflow + stock run on **new creates**. Orders imported before a product had a workflow/recipe do not get progress or stock retroactively. Already-Done zero-gate steps on open orders are not rewritten when you later add gates to the template.

---

## 9. Listings

**Where:** Order Machine → Listings

**What it is for:** Cache marketplace listings, link them to products, push updates.

**Main actions:**

- Browse / refresh listings (live API or fixtures)
- Link / unlink to catalogue products
- Push price, quantity, and/or description (variations where supported)

**Important rules:** Linking fixes matching for **future** syncs; already-stored unmatched lines may need separate handling.

---

## 10. Suppliers & purchase orders

**Where:** Order Machine → Suppliers · Purchase Orders

### Suppliers

- Create / edit name, contact, notes
- **No delete** (by design)

### Purchase orders

**Main actions:**

- Create with supplier, dates, shipping/other costs, lines (material, qty, item cost GBP)
- **Preview Impact** before/while editing (no DB stock write)
- **Receive** deltas per line (partial / full / over-receive)
- **Mark received** (shortfall close) / **Cancel** (no reverse of already-received stock)
- Edit notes after lock; lines/costs lock after first receive

**Status ideas:** ordered → partially_received / received / cancelled / manually closed.

**Important rules:**

- Stock rises on receive; active material budgets draw down by landed delta on receive.
- Mark received shortfall does **not** draw down for missing qty.
- Duplicate materials on one PO are allowed.

---

## 11. Landed cost, WA & goals

**What it is for:** Understand cost numbers after purchasing.

**Rules (GBP):**

- Shipping/other allocated across lines by item cost
- Inbound unit cost from ordered qty (not cumulative received qty)
- After receive, new WA written into the material’s unit cost
- If total line item cost is 0 → no shipping/other allocation; warning shown
- Post-receive PO edits do **not** rewrite past WA — correct via adjustment / unit-cost override

**Goals:** Per workflow template + material; approaching / over after WA changes. Surfaces on Materials, Product Costing, post-receive notices. No separate dashboard cost-alerts widget yet.

---

## 12. Batches

**Where:** Order Machine → Batches

**What it is for:** Multi-order steps (shipping-label confirm; thank-you script group is legacy).

**Seeded groups (editable name/size; key + action type fixed):**

| Group key | Action | Default size |
|---|---|---|
| `thank_you_card` | Script (**disabled** — release soft-fails; do not execute) | 4 |
| `shipping_label` | Manual confirm | 4 |

**States:** collecting → ready → (processing was for scripts; now unused) → done / error

**Main actions:** Release, Mark done (manual groups), expand members, edit group display name/size. Script Retry is unsupported / soft-fails.

**Important rules:**

- Same batch group pools **across** workflows
- Cancelled members may stay listed but are skipped on advance
- Order Mark done is hidden while `waiting_batch`
- Prefer `manual_confirm` batches; Pack checklist covers thank-you ticks without script batches

---

## 13. Budgets

**Where:** Order Machine → Budgets

**What it is for:** Restock pots funded from sales (and Extra material usage on orders) and spent on purchases / R&D.

### Types

| Type | Funding | Scope |
|---|---|---|
| **Material** | Consumption cost on new orders | Optional workflow list (empty = all); **one budget per material** |
| **Manual** | % of price, % of profit (fee-aware), or fixed per unit | Optional product list (empty = all) |

**Main actions:**

- Create / edit (type and linked material immutable after create)
- Soft-deactivate
- View ledger (sale_funding, Extra material usage, purchase_spend, adjustments)
- Manual adjustment (notes required)
- R&D write-off (also on Materials)

**Important rules:**

- Funding runs with stock on incremental create; skipped for history / cancelled / inactive / already funded
- Raising **Materials used** on an order funds the same material pot again, labelled **Extra material usage**
- Sold price 0 on a line is treated as 0 (does not fall back to target); empty price can fall back to target
- `% of profit` may fund a **negative** amount on loss
- PO receive draws material budgets; Mark received shortfall does not
- Negative balances allowed
- Plain Adjust stock never touches budgets

---

## 14. Platform selling fees

**Where:** Channel Fee Estimates · Recurring Platform Expenses · Settings (fee sync) · order detail · Product Costing / Budgets / Analytics consumers

### Channel Fee Estimates

- Seeded eBay/Etsy defaults (do not overwrite your edits)
- Components: percents, fixed amounts, optional order-value tiers, enable/disable
- eBay examples: final value %, tiered per-order fee, regulatory %, Promoted Listings % (on by default)
- Etsy examples: listing fee, transaction %, payment processing, VAT-on-fees, Offsite Ads % (on by default)

### Fee sync

- Separate from order sync (own button + cron + cursor)
- Receipt-linked → per-order platform fees; unmatched listing-style → recurring expenses
- Amounts treated as GBP; no FX
- **Live eBay:** Finances scope required — reconnect if warned
- **Dummy:** fee fixtures

### Shared math

- Per order: **actual** fee totals if rows exist, else **estimate**
- Costing shows £ and % (no single opaque “effective rate” product)
- Budgets `% of profit` and Analytics profit reuse the same helpers
- Recurring expenses are **out** of profit charts / costing order profit

---

## 15. Analytics

**Where:** Order Machine → Analytics

**What it is for:** Live charts for ops review (no pre-computed summary tables in v1).

| Chart | Notes |
|---|---|
| Sales over time | Revenue from line `unit_price` × qty only (no target fallback) |
| Profit over time | Revenue − material COGS from new_order stock log − platform fees (estimate→actual). Recurring expenses excluded |
| Material stock over time | Multi-select materials + Apply; reconstructed from current stock + log |
| Orders by channel | Counts in range |
| Average order value | Revenue ÷ priced-order count |
| Workflow timing | Avg actual vs planned **per step** (bar chart + table); make-line / pack **workflow totals** (span actual). Make / Pack tabs + template filter |

**Filters (shared):** date range, granularity, channel, material multi-select. Site timezone → UTC query window. Timing also uses Make/Pack tabs + template select (same date range).

**Timing notes:** Actual is wall clock (includes timer waits). Workflow total = first step start → last step complete. Steps with avg actual > planned × 1.2 are highlighted. Cancelled / incomplete workflows do not invent totals.

**Exclusions:** cancelled + best-effort refunded. Chart.js loaded from CDN (no build step).

---

## 16. Automation (REST & MCP)

**Where:** Settings (API key, MCP toggle) · details in [`MCP.md`](../../MCP.md)

**What operators need to know:**

- REST under `som/v1` supports automation (orders, purchasing, batches, etc.) with API key or admin auth.
- Channel credentials are **never** exposed via REST or MCP.
- MCP Abilities are **read-only** and only register when the MCP toggle is on.
- Day-to-day admin UI stays on form POST / admin-ajax; REST is parallel for automation / AI tools.

This reference is not a full API manual — use developer docs and MCP.md when wiring integrations.

---

## 17. What is out of scope for now

So you do not hunt for missing features:

| Deferred | Operator note |
|---|---|
| Cancel → stock reversal | Cancelled status shows; stock not put back automatically yet |
| Dashboard cost-alerts widget | Use Materials + Product Costing badges |
| Multi-currency / FX | GBP only |
| Rewrite received POs’ WA | Adjust stock / unit cost instead |
| Batch + timer/script/manual on one step | Batch-only steps in v1 |
| Completed orders on Board | Use Orders list |
| Amazon fee sync | eBay/Etsy only |
| Recurring expenses in profit charts | Order fees only |
| Dedicated ink type | Recipe material or manual fixed budget |
| Stacked mobile Board | Horizontal scroll only |
| Trays overview page | Assign/release on order detail; badges on boards |
| Per-line trays / make→pack transfer | Order-level trays only |
| Auto Mark done on Unblock | Unblock enables Mark done only |
| Unblock on Make/Pack boards | Order detail only |

---

*End of operator reference. Procedures live in [`USER-WORKFLOWS.md`](USER-WORKFLOWS.md).*
