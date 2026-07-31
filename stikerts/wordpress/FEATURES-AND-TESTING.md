# Order Machine — Features & Testing Guide

*Review guide for everything shipped through Sprint 8 (plugin **v0.8.0**, schema **1.2.0**).*  
*Companion to [`Sprint-Plan.md`](Sprint-Plan.md) and [`Sprint-Progress.md`](Sprint-Progress.md).*

---

## At a glance

Order Machine is a WordPress plugin that pulls orders from eBay/Etsy (or fixture data), matches them to your product catalogue, tracks production through a workflow, and reserves materials when new orders arrive.

| Area | Status |
|---|---|
| Database schema (11 `wp_som_*` tables) | Done |
| Channel settings + OAuth / dummy credentials | Done |
| Order sync (incremental + history import) | Done |
| Orders list + detail UI | Done |
| Products, materials, recipes | Done |
| Workflow templates + step editor | Done |
| Workflow engine (manual confirm + timers) | Done |
| Material auto-decrement on new orders | Done (cancel reversal deferred) |
| Script / n8n / local actions execution | Not yet (config UI only) |
| Listings push, REST API, MCP | Listings done (Sprint 10); REST + MCP done (Sprint 11) |

**Recommended review path:** use **wp-env** for a clean, repeatable fixture walkthrough, then spot-check the same screens on your **Local** `ordermachine` site if you use that day-to-day.

---

## 1. How to get a test environment

### Option A — wp-env (recommended for this review)

Requires Docker Desktop + Node/`npx`. From the plugin root:

```bash
npx @wordpress/env start
```

| | |
|---|---|
| Admin | http://localhost:8888/wp-admin |
| Login | `admin` / `password` |
| Dummy mode | On via `.wp-env.json` (`SOM_USE_DUMMY_CREDENTIALS=true`) |

Dummy mode auto-seeds:

- Encrypted fake eBay + Etsy credentials
- Sample product **BIN-SET-4PK** with vinyl + laminate recipe
- Listing matches so some fixture lines resolve to that product
- Workflow template **Bin Sticker Production** (8 steps) assigned to the product

More commands: [`WP-ENV.md`](../../WP-ENV.md). Clean reset: `npx @wordpress/env destroy` then `start` again.

### Option B — Local site (`ordermachine`)

1. Open the Local site → wp-admin → **Plugins** → activate **Order Machine**.
2. Without dummy mode you need real eBay/Etsy developer app keys on **Settings**, then Connect + Sync.
3. To mirror the fixture path on Local, add to `wp-config.php` (above “That’s all, stop editing!”):

```php
define( 'SOM_USE_DUMMY_CREDENTIALS', true );
define( 'SOM_ENCRYPTION_KEY', 'your-local-dev-key-here' ); // optional but recommended
```

Deactivating the plugin does **not** delete data. Uninstall also keeps `som_*` tables/options by design.

---

## 2. Admin map

Top-level menu: **Order Machine** (capability: `manage_options`).

| Screen | URL slug | Purpose |
|---|---|---|
| **Orders** | `som-orders` | List, filters, badges; open a row for detail |
| Order detail | `som-orders&order_id=N` | Buyer, personalisation, address, items, workflow, stock |
| **Products** | `som-products` | Catalogue; edit SKU, workflow, material recipe |
| **Materials** | `som-materials` | Stock levels, low-stock, manual adjustments |
| **Workflows** | `som-workflows` | Templates + step editor |
| **Listings** | `som-listings` | Cached marketplace listings; refresh + push price/qty/description |
| **Settings** | `som-settings` | Channels, intervals, Sync now, Import history |

---

## 3. Features — what they do and how to use them

### 3.1 Settings & channels

**Where:** Order Machine → Settings

**What you can do:**

- Save eBay / Etsy app client ID + secret (secrets stored encrypted; leave blank to keep existing)
- See OAuth callback URLs for each channel
- **Connect** / **Disconnect** eBay and Etsy (live OAuth on Local with real apps; wp-env uses dummy tokens)
- Set **n8n base URL** (stored for later Sprint 9 steps; not executed yet)
- Configure intervals: order poll, engine tick, token refresh
- **Sync now** — incremental pull (fixtures when credentials are dummy)
- **Import history** — 30 or 90 days (history creates orders but skips workflow assignment and stock reservation)

**Background jobs (WP-Cron):**

| Hook | Role |
|---|---|
| `som_sync_orders` | Periodic incremental sync |
| `som_engine_tick` | Unlock workflow steps whose timer has elapsed |
| `som_refresh_tokens` | Refresh real OAuth tokens (skips dummy) |

---

### 3.2 Order sync

**Where:** Settings → Sync now / Import history (or wait for cron)

**Behaviour:**

- De-duplicates on `channel_id` + `external_order_id` (re-sync updates; does not create duplicates)
- Stores full channel payload in `raw_payload`
- Matches line items via `wp_som_listings` (`external_listing_id` ↔ `product_id`)
- Unmatched lines keep `product_id = NULL` and are flagged in the UI
- Best-effort personalisation text extraction into `personalisation_text`
- On **new** incremental creates (not history import, not cancelled): assigns workflow + reserves materials

**Fixture set (dummy mode):** ~6 orders across eBay/Etsy — mix of matched, unmatched, and cancelled.

| Fixture listing IDs (seeded matches) | |
|---|---|
| eBay | `110000000001` and SKU `BIN-SET-4PK` |
| Etsy | `220000000001` |

---

### 3.3 Orders list & detail

**Where:** Order Machine → Orders

**List:**

- Filters: status, channel, date range, search
- Badges / flags: open, complete, cancelled, unmatched items, no workflow

**Detail (open an order):**

- Buyer and totals
- Personalisation (front-and-centre when present)
- Shipping address
- Line items (matched product or unmatched warning)
- Workflow progress: current step, timers, **Mark done** when allowed
- Material stock impact for this order (when reserved)
- Raw channel payload in a collapsed `<details>` block

**Workflow rules on the order:**

- One workflow per order, from the **primary product** = first line item with a non-null `product_id`
- If nothing matches → no progress rows; UI shows no-workflow / unmatched flags

---

### 3.4 Products

**Where:** Order Machine → Products → Add / edit

**What you can do:**

- Create / edit name, SKU, active flag
- Assign a **workflow template**
- Edit the **material recipe** (material + quantity per unit)
- See linked listings (links through to **Listings** admin)

Deactivate rather than hard-delete (soft inactive).

---

### 3.5 Materials & stock

**Where:** Order Machine → Materials

**What you can do:**

- CRUD materials (name, unit, stock, low-stock threshold, cost, active)
- **Manual stock adjust** (positive or negative delta) → writes `material_stock_log` with reason `manual_adjustment`
- See recent log entries on the edit screen
- Low-stock highlighting on the list

**Auto-decrement (Sprint 8):**

- When a **new** matched order is created by incremental sync, stock decreases per recipe × quantity
- Log reason: `new_order`
- Stock may go negative (by design — signals shortage)
- Skipped for: history import, cancelled orders, unmatched-only orders
- Idempotent per order (won’t double-reserve on re-sync)

**Not implemented yet:** stock reversal when an order is later cancelled (`order_cancelled`). Cancel status is stored/detected for display, but reversal is deferred until channel cancel fields are confirmed (open items D3 / A3).

---

### 3.6 Workflow templates & step editor

**Where:** Order Machine → Workflows

**Templates:** create / edit / deactivate; name + description.

**Steps (editor):**

- Add / remove / reorder
- Toggle **requires manual confirm**
- Set **timer** (seconds via friendly min/hr/day UI)
- Configure **script_config** (form fields + raw JSON fallback for `local` / `api` / `n8n`)

**Seeded template — Bin Sticker Production:**

| # | Step | Gates |
|---|---|---|
| 1 | Print | Manual confirm |
| 2 | Dry | Timer 15 minutes |
| 3 | Laminate | Manual confirm |
| 4 | Cut | Manual confirm |
| 5 | Pack | Manual confirm |
| 6 | Ship | Manual confirm |
| 7 | Thank-you | `script_config` local action (stored only — not executed yet) |
| 8 | Review reminder | Timer 7 days + manual confirm |

Script/API/n8n steps are **saved** and appear in progress, but the engine currently **pass-through / auto-completes** script-only steps until Sprint 9 builds the allowlisted runner and callbacks.

---

### 3.7 Workflow engine

**Triggers:**

- New order create (incremental sync) → assign steps from primary product’s template
- Admin **Mark done** on the current step (when gates allow)
- Cron `som_engine_tick` → unlock steps whose `timer_ends_at` has passed

**Gates:**

- Manual confirm: Mark done enabled only when that step is current and confirmed by you
- Timer: Mark done disabled until the countdown finishes (or tick unlocks it)
- Completing the last step marks the order complete

**Important for testing:** workflow + stock run on **new creates**. If fixtures were already imported **before** the product had a workflow/recipe, those old rows won’t retroactively get progress or stock. Fix: destroy/reseed wp-env, or sync after seed is in place (fresh env does this automatically).

---

## 4. What is intentionally out of scope (for now)

Use this so review time isn’t spent hunting missing screens:

| Planned later | Sprint |
|---|---|
| Execute local scripts / n8n / API steps + retries + REST callback | 9 (done) |
| Listings admin + push price/qty/description to channels (incl. variations) | 10 (done) |
| External `POST /som/v1/orders`, advance-step, MCP/Abilities | 11 (done) |
| Cancel → material stock reversal | After D3/A3 (tied to Phase 8 remainder) |

---

## 5. Testing — logical order

Work top-to-bottom. Each section builds on the previous. Checkboxes are for your pass/fail notes.

### Prep

- [ ] Docker running; from plugin root: `npx @wordpress/env start`
- [ ] Log in at http://localhost:8888/wp-admin (`admin` / `password`)
- [ ] Confirm **Order Machine** is active under Plugins
- [ ] Optional clean slate: `npx @wordpress/env destroy` → `start`

---

### Test 1 — Foundation & menu

1. Open **Order Machine** in the left admin menu.
2. Confirm submenus: Orders, Products, Materials, Workflows, Settings.

- [ ] Menu present and all five screens load without PHP errors

**Optional CLI check:**

```bash
npx @wordpress/env run cli wp option get som_db_version
npx @wordpress/env run cli wp db query "SHOW TABLES LIKE 'wp_som_%';"
```

Expect version `1.2.0` and 11 tables.

---

### Test 2 — Seeded catalogue (before first sync)

1. **Products** → open **Bin Sticker Set — 100x140mm 4-pack (sample)** (`BIN-SET-4PK`).
2. Confirm workflow template **Bin Sticker Production** is assigned.
3. Confirm recipe rows: vinyl + laminate (~1 each).
4. **Materials** → both sheets show stock (seed starts at 25) and threshold 5.
5. **Workflows** → open **Bin Sticker Production** → 8 steps as in §3.6.

- [ ] Product, recipe, materials, workflow all present without manual create

---

### Test 3 — Settings & Sync now

1. **Settings** → confirm eBay and Etsy show as connected (dummy).
2. Note material stock numbers (vinyl/laminate) for later comparison.
3. Click **Sync now**.
4. Confirm a success/summary notice (created / updated counts).
5. Click **Sync now** again → expect **created 0**, updates only (de-dup).

- [ ] First sync creates fixture orders
- [ ] Second sync does not duplicate

---

### Test 4 — Orders list

1. **Orders** → you should see ~6 fixture orders (eBay + Etsy).
2. Try filters: channel, status, dates, search (buyer name / order id).
3. Spot badges: unmatched, cancelled, open, etc.

- [ ] List populates
- [ ] Filters change the result set sensibly
- [ ] Cancelled / unmatched visibly distinct

---

### Test 5 — Order detail (matched vs unmatched)

1. Open a **matched** open order (line tied to `BIN-SET-4PK`).
2. Check personalisation (if present), shipping address, line items with product link.
3. Confirm a **workflow progress** section exists (steps, current step, Mark done).
4. Confirm a **material stock** panel / log mention if reservation ran.
5. Expand **raw payload** and skim structure.
6. Open an **unmatched** order → line(s) without product; no (or incomplete) workflow as designed.
7. Open a **cancelled** fixture → cancelled state clear; no fresh stock reservation expected.

- [ ] Matched order: address + items + workflow readable
- [ ] Unmatched flagged; not blocking the rest of the UI
- [ ] Cancelled identifiable

---

### Test 6 — Workflow: Mark done + timer

On a matched open order with progress:

1. Current step should be **Print** (manual).
2. Click **Mark done** → advances to **Dry** (15-minute timer).
3. Confirm Mark done is **disabled** (or blocked) while the timer is running.
4. Either:
   - Wait ~15 minutes and refresh / wait for engine tick, **or**
   - Force unlock for review (WP-CLI), e.g. set `timer_ends_at` in the past then run the tick:

```bash
npx @wordpress/env run cli wp eval 'do_action("som_engine_tick");'
```

5. After unlock, Mark done on Dry → Laminate, then walk a couple more manual steps if you like.
6. Optional: complete through Ship; Thank-you should pass through without running a real script; Review reminder starts a long timer.

- [ ] Manual advance works
- [ ] Timer blocks early completion
- [ ] Engine tick / elapsed timer unlocks the step

---

### Test 7 — Material auto-decrement

1. **Before** another new order: note vinyl + laminate `current_stock` on Materials.
2. If all fixtures already exist, create a **new** situation by destroying wp-env and syncing once, **or** temporarily lower stock and re-import on a fresh DB so you can watch the delta.
3. After a fresh incremental create of a matched order, stock should drop by recipe × qty (seed recipe: 1 vinyl + 1 laminate per unit).
4. Open the material edit screen → recent log shows `new_order` (negative quantity).
5. Re-sync the same orders → stock must **not** drop again for those order IDs.

- [ ] Stock decreased on first create
- [ ] Log reason `new_order`
- [ ] Re-sync is idempotent (no double decrement)

---

### Test 8 — Manual stock adjust

1. Materials → edit vinyl → enter a delta (e.g. `+5` or `-2`) → save.
2. Confirm `current_stock` updates and log shows `manual_adjustment`.

- [ ] Manual adjust + log entry work

---

### Test 9 — Product & material CRUD

1. Create a new product (unique SKU), leave workflow empty, save.
2. Attach one material to its recipe; assign a workflow; save.
3. Deactivate the product; confirm it disappears from “active” expectations / is marked inactive.
4. Create a material; edit threshold; deactivate.

- [ ] Create / edit / deactivate paths work without errors

---

### Test 10 — Workflow editor

1. Workflows → duplicate the idea of the seed: create a small template with 2–3 steps.
2. Reorder steps; set one timer (e.g. 1 minute); set one manual; add a `script_config` JSON blob; save.
3. Assign that template to a test product.
4. (Optional) On a fresh sync path, confirm a new matched order picks up this template.

- [ ] Step CRUD / reorder / timer / script_config persist
- [ ] Assignment on product sticks

---

### Test 11 — Import history vs Sync now

1. On a clean DB (or after noting behaviour): run **Import history** (30 days).
2. Confirm orders appear, but **new** history rows should **not** get workflow assignment / stock reservation the way incremental Sync now does.
3. Prefer **Sync now** for day-to-day “new order” behaviour during review.

- [ ] History import does not pretend to be a live new-order pipeline

---

### Test 12 — Smoke on Local (optional)

If you use the Local `ordermachine` site:

- [ ] Plugin activates; menus load
- [ ] With dummy constant: Sync now behaves like wp-env
- [ ] Without dummy: Settings accepts app keys; Connect shows correct callback URLs (live OAuth only when apps exist)

---

## 6. Suggested review focus (human judgment)

Beyond “does it click,” please watch for:

1. **Orders detail layout** — is personalisation and address easy to find for packing?
2. **Unmatched / no-workflow flags** — clear enough that you wouldn’t ship the wrong thing?
3. **Workflow step naming / seed defaults** — sensible for real bin-sticker production?
4. **Timer UX** — Dry at 15 minutes / review at 7 days: right defaults?
5. **Stock going negative** — acceptable warning, or do you want hard blocks later?
6. **Anything awkward** before Sprint 9 (scripts/n8n) — better to adjust data model/UI now.

---

## 7. Quick troubleshooting

| Symptom | Likely cause |
|---|---|
| No seed product / workflow | `SOM_USE_DUMMY_CREDENTIALS` not true; or load any admin page once to trigger seed |
| Sync creates 0 forever | Already synced; check Orders list; or destroy wp-env for a clean DB |
| Matched order has no workflow | Order was created before template was assigned; use fresh sync after seed |
| Mark done disabled on Dry | Timer still running; wait or force `som_engine_tick` after adjusting `timer_ends_at` |
| Stock didn’t move | History import, cancelled order, unmatched-only, or already reserved |
| OAuth Connect fails on wp-env | Expected without tunnel + real apps — use Local for live OAuth |
| Ciphertext / decrypt weirdness | `SOM_ENCRYPTION_KEY` changed after credentials were saved — reconnect or re-seed |

---

## 8. Design doc pointers

| Doc | Use when |
|---|---|
| [`Order-Management-Requirements.md`](Order-Management-Requirements.md) | Why / scope |
| [`01-Data-Model.md`](01-Data-Model.md) | Tables and relationships |
| [`02-API-Integration.md`](02-API-Integration.md) | eBay / Etsy behaviour |
| [`03-Workflow-Engine.md`](03-Workflow-Engine.md) | State machine rules |
| [`04-WordPress-Integration.md`](04-WordPress-Integration.md) | Plugin architecture |
| [`05-Implementation-Roadmap.md`](05-Implementation-Roadmap.md) | Phase order |
| [`Sprint-Progress.md`](Sprint-Progress.md) | What was verified per sprint |

---

*End of guide. Next build milestone after this review: Sprint 9 (script / API / n8n step execution).*
