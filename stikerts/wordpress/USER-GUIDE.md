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
| **Orders** | List and open order detail (buyer, items, workflow, fees) |
| **Orders Board** | Kanban of open orders by current step; drag to advance |
| **Products** | Catalogue, recipes, workflow assignment, Product Costing |
| **Materials** | Stock, WA / value, preferred supplier, R&D write-off |
| **Budgets** | Material + manual budgets, ledger, adjustments |
| **Suppliers** | Supplier contacts (no delete) |
| **Purchase Orders** | Create / receive POs, Preview Impact |
| **Batches** | Collecting / release / mark done for batch steps |
| **Workflows** | Templates and step editor (timers, batch groups, goals) |
| **Listings** | Cached marketplace listings; link products; push updates |
| **Analytics** | Sales, profit, stock, channel mix, AOV charts |
| **Channel Fee Estimates** | Editable estimated fee components per channel |
| **Recurring Platform Expenses** | Non-order fees (e.g. listing fees) from fee sync |
| **Settings** | Channels, OAuth, sync, fee sync, MCP, API key |

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
5. Confirm seed catalogue: sample product **BIN-SET-4PK**, materials, workflow **Bin Sticker Production**, batch groups.
6. **Settings → Sync now** to pull fixture orders. Optionally **Sync fees now** for fee fixtures.

**Seed tools:** Settings → Seed data — **Remove seed data** / **Restore seed data** (restore needs dummy mode on). Does not delete your own products, suppliers, or POs.

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

---

## Limits operators should know

| Topic | Behaviour |
|---|---|
| Cancelled orders | Status is shown; **material stock is not reversed** yet when an order cancels later. |
| Currency | **GBP only** (fee amounts stored as returned and treated as GBP; no FX conversion). |
| Orders Board | **Open / incomplete** orders only. Completed history stays on **Orders** (use View history). |
| Batch steps | In v1 a step is **batch-only** (not combined with timer/script/manual on the same step). |
| PO after first receive | Line quantities/costs **lock**; notes stay editable. Corrections via stock/value adjustment, not rewriting old receives. |
| Negative stock / budgets | Allowed as a shortage / overspend signal. |
| Ink | No special type — track via a recipe material or a manual fixed-amount budget. |
| Mobile Board | Horizontal scroll; no stacked mobile layout. |

---

## Quick troubleshooting

| Symptom | What to try |
|---|---|
| No seed product / workflow | Dummy constant not on; open any Order Machine admin page once. |
| Sync creates 0 | Already synced — check Orders; or you need new marketplace activity (live). |
| Matched order has no workflow | Order was created before a workflow was on the product — new Sync after assignment won’t rewrite old rows. |
| Mark done disabled on a timer step | Wait for the timer, or wait for the engine tick after it ends. |
| Mark done missing on Thank-you | Expected while **waiting_batch** — use **Batches**. |
| Stock didn’t move | History import, cancelled, unmatched-only, or already reserved for that order. |
| Budget didn’t fund | Same as stock skip reasons; inactive budget; product/workflow scope miss; already funded. |
| Budget didn’t draw on receive | No active material budget for that material; or you used **Mark received** (shortfall) instead of **Receive**. |
| Adjust stock didn’t change budget | By design — use **R&D write-off** for stock + budget. |
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

- Product **Bin Sticker Set — 100x140mm 4-pack (sample)** (`BIN-SET-4PK`) with vinyl + laminate recipe  
- Workflow **Bin Sticker Production** (Print → Dry → Laminate → Cut → Pack → Ship → Thank-you batch → Review)  
- Batch groups **thank_you_card** (script) and **shipping_label** (manual), size 4  
- Fixture listing matches so some synced lines resolve to that product  

Use this as a practice path — see [Workflow 5](USER-WORKFLOWS.md#5-bin-sticker-style-production-path).

---

*Operator docs companion to plugin Order Machine. Start daily work in [`USER-WORKFLOWS.md`](USER-WORKFLOWS.md).*
