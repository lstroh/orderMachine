# Order Machine — User Guide

*How to run your shop with Order Machine day to day.*  
*Covers Local (dummy fixtures) and a live/online WordPress site with real eBay/Etsy.*

For click-through testing and technical detail, see [`FEATURES-AND-TESTING.md`](FEATURES-AND-TESTING.md).

---

## What Order Machine does

Order Machine is a WordPress plugin that pulls orders from eBay and Etsy (or fixture data in dummy mode), matches them to your product catalogue, tracks production through a workflow (including batch gates and an Orders Board), reserves materials when new orders arrive, tracks raw-material purchasing with landed-cost / weighted-average costing, maintains material / manual budgets funded from sales and drawn down on PO receive, estimates and syncs platform selling fees, and charts sales / fee-aware profit / stock on an Analytics dashboard.

You work almost entirely under the **Order Machine** menu in wp-admin (requires an admin with `manage_options`).

---

## Who this guide is for

| You are… | Start here |
|---|---|
| Setting up or running the shop | This guide + [`USER-WORKFLOWS.md`](USER-WORKFLOWS.md) |
| Looking up one screen in depth | [`USER-REFERENCE.md`](USER-REFERENCE.md) |
| Reviewing / regression-testing the plugin | [`FEATURES-AND-TESTING.md`](FEATURES-AND-TESTING.md) / [`USER-ACCEPTANCE-TESTS.md`](USER-ACCEPTANCE-TESTS.md) |

This guide does **not** cover PHPUnit, smoke scripts, schema versions, or design open items.

---

## Where to go next

| Document | Use when |
|---|---|
| [`USER-WORKFLOWS.md`](USER-WORKFLOWS.md) | Step-by-step daily and weekly procedures |
| [`USER-REFERENCE.md`](USER-REFERENCE.md) | Screen-by-screen “what can I do here?” |
| [`MCP.md`](../../MCP.md) | Optional AI / MCP read-only query setup |

**Suggested reading order:** setup path below → Workflows 1–4 → skim Reference for screens you use often.

---

## Admin menu map

Top-level: **Order Machine**

| Screen | Purpose |
|---|---|
| **Orders** | List and open order detail (buyer, items, make/pack, fees) |
| **Make Board** | Kanban of open **order lines** by make step; drag to advance |
| **Pack Board** | Kanban of open **orders** in pack & ship (not Internal) |
| **Products** | Catalogue, recipes, workflow assignment, Product Costing; internal Produce N |
| **Materials** | Stock, WA / value, preferred supplier, R&D write-off |
| **Budgets** | Material + manual budgets, ledger, adjustments |
| **Suppliers** | Supplier contacts (no delete) |
| **Shipping packages** | Outer mailers/boxes (mm + tare g) with optional packaging materials; assign default + multi-pack on sellable products |
| **Purchase Orders** | Create / receive POs, Preview Impact |
| **Batches** | Collecting / release / mark done for batch steps |
| **Workflows** | Templates and step editor (timers, batch groups, goals) |
| **Listings** | Cached marketplace listings; link products; push updates |
| **Analytics** | Sales, profit, stock, channel mix, AOV charts |
| **Channel Fee Estimates** | Editable estimated fee components per channel |
| **Recurring Platform Expenses** | Non-order fees (e.g. listing fees) from fee sync |
| **Settings** | Channels, OAuth, sync, fee sync, MCP, API key, **Max trays**, Default Pack workflow |

---

## Two setup paths

### Path A — Local with dummy credentials (learn / demo)

Use this on a Local by Flywheel (or similar) site when you want fixture orders without live marketplace apps.

1. Activate **Order Machine** under Plugins.
2. In `wp-config.php` (above “That’s all, stop editing!”):

```php
define( 'SOM_USE_DUMMY_CREDENTIALS', true );
define( 'SOM_ENCRYPTION_KEY', 'your-local-dev-key-here' ); // keep stable once set
```

3. Open any Order Machine admin screen so seed data can run.
4. **Settings** → confirm eBay and Etsy show as connected (dummy).
5. Confirm seed catalogue: sample product **BIN-SET-4PK**, materials, make **Bin Sticker Make**, Pack **Order Pack & Ship**.
6. **Settings → Sync now** to pull fixture orders. Optionally **Sync fees now** for fee fixtures.

**Seed tools:** Settings → Seed data — **Remove seed data** / **Restore seed data** (restore needs dummy mode on). After a Package 6 upgrade, use **Repair pack binding** for open orders missing Pack. Does not delete your own products, suppliers, or POs.

Deactivating the plugin does **not** wipe tables. Uninstall also keeps `som_*` data by design.

Continue with [Workflow 1](USER-WORKFLOWS.md#1-first-time-setup-catalogue) (if extending the seed) and [Workflow 2](USER-WORKFLOWS.md#2-connect--sync).

---

### Path B — Live / online site (real eBay & Etsy)

Use this when the WordPress site is online and you have developer apps for the marketplaces.

1. Activate **Order Machine**. Do **not** set `SOM_USE_DUMMY_CREDENTIALS` (or set it to `false`).
2. Set a stable `SOM_ENCRYPTION_KEY` in `wp-config.php` before saving secrets (changing it later breaks decrypting saved credentials).
3. **Order Machine → Settings:**
   - Enter eBay and/or Etsy client ID + secret (leave a secret blank to keep the existing value).
   - Note the OAuth callback URLs shown for each channel; register them in the developer apps.
   - Click **Connect** for each channel and complete OAuth.
4. Build your catalogue (materials, products, recipes, workflows, listing links) — see [Workflow 1](USER-WORKFLOWS.md#1-first-time-setup-catalogue).
5. Prefer **Sync now** for day-to-day new orders. Use **Import history** only when you want past orders **without** workflow assignment, stock reservation, or budget funding.
6. Configure **Channel Fee Estimates** if needed, then use **Sync fees now** (and the fee poll interval).  
   **Live eBay:** if Settings warns that Finances scope is missing, **Disconnect** then **Connect** again so fee sync can run. Token refresh alone does not grant a new scope.
7. Optionally set n8n base URL, poll intervals, MCP toggle, and REST API key.

Background jobs (WP-Cron) handle periodic order sync, fee sync, timer unlocks / batch retries, and token refresh. You can still use **Sync now** / **Sync fees now** manually.

Continue with [Workflow 2](USER-WORKFLOWS.md#2-connect--sync) and [Workflow 3](USER-WORKFLOWS.md#3-morning--new-order-loop).

---

## Mini glossary

| Term | Meaning |
|---|---|
| **Primary product** | First order line item with a matched `product_id`. That product’s workflow is assigned to the whole order. |
| **Unmatched** | A line (or order) with no link from marketplace listing → catalogue product. Flagged in the UI; often no workflow. |
| **waiting_batch** | Current step is a batch step. Advance happens on **Batches**, not via Mark done on the order. |
| **WA (weighted average)** | Material unit cost after purchases; used for value on hand and consumption costing. |
| **Estimate vs actual fees** | Costing / profit use **actual** synced fee lines when present for an order; otherwise **channel fee estimates**. |
| **Dummy mode** | `SOM_USE_DUMMY_CREDENTIALS` — fixture sync, seed catalogue, no real OAuth. |
| **Sync now** | Incremental pull of new/updated orders; new matched creates get workflow + stock + budget funding. |
| **Import history** | Backfill of past orders; does **not** assign workflow / reserve stock / fund budgets like a live create. |
| **Multipack** | N identical units sold as one SKU (e.g. a 4-pack). Model as a **separate product** with its own recipe and shipping defaults — not a multiplier on a base SKU. |
| **Trays** | Numbered physical trays (site pool **1 … Max trays**) assigned to an **order**. Shared by all make lines and pack on that order. |
| **Uses trays** | Template checkbox (make or Pack). If **any** make or Pack template on the order has it on, the order **requires** ≥1 held tray before Mark done / advance. |
| **Unblock step** | Order-detail control that lets you **Mark done** despite gates (timer, checklist, script, batch, ship/package/hold, trays). Does **not** complete the step for you. |

---

## Pack sizes (multipacks)

Sell pack sizes as **separate catalogue products**. There is no pack→base inventory explosion and no `units_per_pack` link in Order Machine.

| Do | Don’t |
|---|---|
| One product per pack size (1-pack, 4-pack, 10-pack, …) | Expect one SKU to “multiply” into N loose units |
| Recipe = materials for **one sold unit** of that pack | Create a phantom loose-unit SKU unless you actually sell singles |
| Set goods weight, default package, multi-pack package, and planned shipping **per SKU** | Share one planned postage across all pack sizes |

**Naming (seed style):** name like `Bin Sticker Set — 4-pack`, SKU like `BIN-SET-4PK`. One sold 4-pack uses the **default** package; two or more sold packs on an order suggest the **multi-pack** package when set. Mixed SKUs that disagree fall back to the site default with a Pack confirm flag. Link each pack SKU to its own marketplace listing.

Order notes, planned vs actual postage, package materials, and Create test order (multi-line) are covered in [`USER-REFERENCE.md`](USER-REFERENCE.md).

---

## Trays & Unblock step

Physical trays stay with the **order** (not a single make line). There is no make→pack tray transfer and no separate trays overview page — manage them on **order detail** and see numbers as badges on the Make / Pack boards.

### Set up trays

1. **Settings → Max trays** — pool size **1–20** (default **10**). Numbers are **1 … max**. Lowering max is blocked while a higher tray number is still held.
2. **Workflows** → open a make or Pack template → tick **Uses trays** when orders that use that template must hold trays. Seed templates leave this **off** — turn it on when you are ready.

### Day to day

| Action | Where |
|---|---|
| Assign free trays / release held trays | Order detail → **Trays** panel (multi-select) |
| See held tray numbers | Make Board and Pack Board card badges (**Tray N**) |
| Bypass a blocked step | Order detail → **Unblock step** on the **current** make or pack step |

**When required:** if any sellable line’s make template **or** the bound Pack template has **Uses trays**, the order needs ≥1 held tray. Mark done / Board advance stay blocked until you assign; the Trays panel highlights. You may assign more trays later; while required and the order is still open you must **keep at least one** held (cannot release the last).

**Auto-release:** when the order completes (pack/ship done, or Internal Produce N when make completes), all held trays for that order free back to the pool.

**Unblock step:** available on order detail when Mark done is gated. Confirm copy: *Allows Mark done without meeting this step’s gates. Does not complete the step.* Still click **Mark done** yourself. Logs who/when and adds an order note. Not on the boards. Concise rules: [`USER-REFERENCE.md`](USER-REFERENCE.md) §3 / §8.

---

## Limits operators should know

| Topic | Behaviour |
|---|---|
| Cancelled orders | Status is shown; **material stock is not reversed** yet when an order cancels later. |
| Currency | **GBP only** (fee amounts stored as returned and treated as GBP; no FX conversion). |
| Make / Pack Boards | **Open / incomplete** only. Make = line cards; Pack = order cards (excludes Internal). Completed history stays on **Orders**. |
| Batch steps | In v1 a step is **batch-only** (not combined with timer/script/manual on the same step). |
| PO after first receive | Line quantities/costs **lock**; notes stay editable. Corrections via stock/value adjustment, not rewriting old receives. |
| Negative stock / budgets | Allowed as a shortage / overspend signal. |
| Ink | No special type — track via a recipe material or a manual fixed-amount budget. |
| Mobile Board | Horizontal scroll; no stacked mobile layout. |
| Trays | Order-level only; cannot double-book a tray; no trays overview page; seed **Uses trays** is off until you opt in. |
| Unblock step | Order detail only; does not auto Mark done; pack hold flag stays set (Ship gate bypassed for that Mark done). |

---

## Quick troubleshooting

| Symptom | What to try |
|---|---|
| No seed product / workflow | Dummy constant not on; open any Order Machine admin page once. |
| Sync creates 0 | Already synced — check Orders; or you need new marketplace activity (live). |
| Matched order has no workflow | Order was created before a workflow was on the product — new Sync after assignment won’t rewrite old rows. |
| Mark done disabled on a timer step | Wait for the timer, or wait for the engine tick after it ends — or **Unblock step** on order detail (still Mark done after). |
| Mark done blocked — trays required | Order detail → **Trays** → assign ≥1 free tray (or Unblock step to bypass without assigning). |
| Can’t release last tray | Expected while trays are required and the order is still open — keep ≥1. |
| Can’t lower Max trays | A higher tray number is still held — release it first. |
| Mark done missing on Thank-you | Expected while **waiting_batch** — use **Batches**, or **Unblock step** (removes the line/order from the open batch). |
| Stock didn’t move | History import, cancelled, unmatched-only, or already reserved for that order. |
| Budget didn’t fund | Same as stock skip reasons; inactive budget; product/workflow scope miss; already funded. |
| Budget didn’t draw on receive | No active material budget for that material; or you used **Mark received** (shortfall) instead of **Receive**. |
| Adjust stock didn’t change budget | By design — Adjust stock is for corrections only. Use **R&D write-off** so the restock pot drops when you burn material without a sale. |
| Used more material than the recipe | Order detail → **Materials used** → raise Actual → Save (cannot go below planned). |
| Board card won’t drag | Not in progress / gates blocked / Unassigned / waiting badges. |
| Card snapped back | Drop must be the **next** step column (or Complete on the last step). |
| Can’t edit PO lines | Already received once — lock by design. |
| Batch stuck in error | **Retry** on Batches; check the error message. |
| Ciphertext / decrypt errors | Encryption key changed after credentials were saved — reconnect or re-seed. |
| Abilities / MCP missing | Turn MCP on in Settings. |
| Order has no Platform fees panel lines | Run **Sync fees now**; live eBay may need Finances reconnect. |
| Analytics empty | No priced lines in range; cancelled/refunded excluded; or date filter too narrow. |
| Analytics stock chart blank | Select material(s) and Apply. |
| Charts / Board DnD missing | CDN blocked (offline admin) — SortableJS / Chart.js. |

More detail: [`FEATURES-AND-TESTING.md`](FEATURES-AND-TESTING.md) §7.

---

## Seeded demo (dummy mode)

When dummy mode seeds successfully you typically get:

- Product **Bin Sticker Set — 100x140mm 4-pack (sample)** (`BIN-SET-4PK`) with vinyl + laminate recipe — a **multipack SKU** (materials for one 4-pack sold unit)  
- Make workflow **Bin Sticker Make** (Print → Confirm print → Dry → Laminate → Cut) — **Uses trays** off  
- Pack workflow **Order Pack & Ship** (Confirm pack + thank-you checklist → Confirm address → Package → Ship → Review) — **Uses trays** off  
- Batch groups still seeded for optional use; thank-you is checklist-only on Pack (not a batch gate)  
- Fixture listing matches so some synced lines resolve to that product  

No second pack size is seeded; add a 1-pack or 10-pack as another product if you need it for demos. Turn on **Uses trays** on a template when you want the tray gate. Use this as a practice path — see [Workflow 5](USER-WORKFLOWS.md#5-bin-sticker-style-production-path).

---

*Operator docs companion to plugin Order Machine. Start daily work in [`USER-WORKFLOWS.md`](USER-WORKFLOWS.md).*
