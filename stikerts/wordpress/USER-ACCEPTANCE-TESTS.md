# Order Machine — User Acceptance Tests

*Click-through checklist for an operator (or reviewer) in wp-admin.*  
*Covers base Sprints 1–11 + Update U1–U7 · plugin **0.18.0** · DB **1.5.0**.*

Companions (feature explainers, not this pass/fail list):

- [`FEATURES-AND-TESTING.md`](FEATURES-AND-TESTING.md) — what each feature does + troubleshooting  
- [`Sprint-Progress.md`](Sprint-Progress.md) / [`../wordpress v2/Update-Sprint-Progress.md`](../wordpress%20v2/Update-Sprint-Progress.md) — what shipped  

Mark each item **Pass / Fail / Skip** (Skip = intentionally out of scope or blocked by missing live apps).

---

## How to use this list

| | |
|---|---|
| **Primary env** | Local by Flywheel site **`ordermachine`** + dummy credentials (fixtures). See §0. |
| **Who** | Logged-in admin (`manage_options`) |
| **Order** | Work top → bottom. Later sections assume earlier ones passed. |
| **Not here** | PHPUnit / developer smoke scripts (optional appendix only; usually Skip on Local). |

### Pass criteria (per item)

You can do the action in the UI, see the expected result, and there is no PHP fatal / blank white screen / uncaught JS error in the console on that screen.

---

## 0. Prep

### 0.1 Open the Local site

1. In **Local**, start the site **ordermachine**.
2. Open wp-admin (Local “WP Admin” button, or your site URL + `/wp-admin`).
3. Log in with your Local admin user.

| | |
|---|---|
| Site | Local **ordermachine** |
| Plugin path | `app/public/wp-content/plugins/orderMachine/` (this repo) |
| Admin | Your Local site URL + `/wp-admin` |

- [ ] Site is running; wp-admin loads
- [ ] **Plugins** → **Order Machine** is **Active** (v0.18.0)
- [ ] Left menu shows **Order Machine**

### 0.2 Enable dummy credentials (fixture path)

For Sync / seed / listing fixtures without live eBay/Etsy apps, add to Local `wp-config.php` **above** “That’s all, stop editing!”:

```php
define( 'SOM_USE_DUMMY_CREDENTIALS', true );
define( 'SOM_ENCRYPTION_KEY', 'your-local-dev-key-here' ); // recommended; keep stable
```

Then reload any Order Machine admin page (seed runs on activate / init when dummy is on).

- [ ] Dummy constants are in `wp-config.php`
- [ ] Settings shows eBay + Etsy as connected (dummy)
- [ ] Seed catalogue appears (product / materials / workflow / batch groups) — see §2

**Live OAuth path (optional later):** remove or set `SOM_USE_DUMMY_CREDENTIALS` false, enter real app keys on Settings, Connect. Skip live Connect until developer apps exist.

### 0.3 Clean slate (when re-testing “first create” stock/workflow)

Local has no one-click destroy like wp-env. Pick one:

| Approach | When |
|---|---|
| **A.** Delete fixture orders in admin / DB, reset material stock to 25, clear related stock logs, then **Sync now** again | Prefer for most re-runs |
| **B.** Local → site → Database → wipe / restore a known-good backup, then reactivate plugin | Heavier reset |
| **C.** Deactivate + reactivate after enabling dummy (re-seeds channels/catalogue where missing; does **not** wipe existing orders) | Light; not a full wipe |

- [ ] You know which reset you will use before §6 / §7 “fresh create” checks

---

## 1. Navigation & foundation

### 1.1 Menu map

Open each submenu once and confirm the page title loads with no PHP error:

| Screen | Expected |
|---|---|
| Orders | Orders list |
| Products | Products list |
| Materials | Materials list |
| Suppliers | Suppliers list |
| Purchase Orders | Purchase orders list |
| Batches | Batch groups + open batches |
| Workflows | Workflow templates list |
| Listings | Listings list |
| Settings | Order Machine Settings |

- [ ] All nine screens load

### 1.2 Deactivate does not wipe data

1. **Plugins** → Deactivate Order Machine.  
2. Confirm Orders/menus gone.  
3. Reactivate.

- [ ] Reactivate restores menus and previous data (orders/settings still there)

---

## 2. Seeded catalogue (before Sync)

Do this **before** first Sync now (or after a clean slate from §0.3). Seed appears when `SOM_USE_DUMMY_CREDENTIALS` is on.

### 2.1 Product

1. **Products** → open **Bin Sticker Set — 100x140mm 4-pack (sample)** (`BIN-SET-4PK`).

- [ ] Product exists without manual create
- [ ] Workflow **Bin Sticker Production** is assigned
- [ ] Recipe has vinyl + laminate (~1 each)
- [ ] **Product Costing** panel shows target / material cost / margin fields (may be empty target until you set one)
- [ ] Linked listings section is present (links toward Listings)

### 2.2 Materials

1. **Materials** → open vinyl and laminate seed materials.

- [ ] Both exist; stock starts around **25**; low-stock threshold visible (seed uses 5)
- [ ] **Weighted average** and **value on hand** display (read-only)
- [ ] Preferred supplier field present (may be empty)

### 2.3 Workflow template

1. **Workflows** → open **Bin Sticker Production**.

- [ ] Eight steps present in order: Print → Dry → Laminate → Cut → Pack → Ship → Thank-you → Review reminder
- [ ] Print / Laminate / Cut / Pack / Ship: manual confirm
- [ ] Dry: timer (~15 minutes)
- [ ] Thank-you: **batch group** set (`thank_you_card`), not a leftover per-order thank-you local script
- [ ] Review reminder: timer (~7 days) + manual confirm
- [ ] **Material cost goals** section exists on the template (may be empty)

### 2.4 Batch groups

1. **Batches** → top **Batch groups** table.

- [ ] `thank_you_card` (script) and `shipping_label` (manual_confirm) exist
- [ ] Default **batch size** is **4** for both
- [ ] Key / action type look fixed (not free-edit like display name)

---

## 3. Settings & channels

### 3.1 Settings form

1. **Settings** → review sections.

- [ ] eBay / Etsy show connected in dummy mode (or Connect / Disconnect buttons visible)
- [ ] OAuth callback URLs shown for each channel
- [ ] Fields for n8n base URL, order poll interval, engine tick interval, token refresh
- [ ] REST API key field + MCP / Abilities toggle
- [ ] Python binary field (thank-you script)
- [ ] Saving settings shows a success notice and values persist on reload

### 3.2 Sync now (incremental)

1. Note vinyl / laminate stock on Materials (for §6).  
2. **Settings** → **Sync now**.  
3. Read the summary notice (created / updated).  
4. **Sync now** again.

- [ ] First run creates fixture orders (typically ~6 created if clean DB)
- [ ] Second run: **created 0**, updates only (no duplicates)
- [ ] Last sync timestamp / summary visible on Settings

### 3.3 Import history

1. On a **clean** set of orders (or accept that history may overlap existing IDs): **Import history** (30 days).

- [ ] Orders appear (or notice explains result)
- [ ] History path does **not** behave like a live “new order” pipeline for workflow + stock (prefer Sync now for those checks — see §6 / FEATURES guide)

---

## 4. Orders list

1. **Orders** after Sync.

### 4.1 Population & badges

- [ ] ~6 fixture orders across eBay + Etsy
- [ ] Badges readable: Open / Complete / Cancelled / Unmatched / needs workflow as applicable
- [ ] Current step name visible for assigned open orders (when workflow ran)

### 4.2 Filters & search

- [ ] Filter **Open** / **Cancelled** / **Needs mapping** / **Needs workflow** each change the set sensibly
- [ ] Filter **eBay** vs **Etsy**
- [ ] Date from / to narrows results (or shows empty without error)
- [ ] Search by buyer name finds a known fixture (e.g. a name from the list)
- [ ] Search by external order ID finds one row
- [ ] Clear filters returns to full list

---

## 5. Order detail

### 5.1 Matched open order

Open a matched open order (line tied to `BIN-SET-4PK`).

- [ ] Channel + external order ID in title
- [ ] **Personalisation** panel front-and-centre (text or clear “none” message)
- [ ] **Shipping address** readable for packing
- [ ] Buyer shown
- [ ] **Line items** show product link (not Unmatched)
- [ ] **Workflow** panel lists steps; current step highlighted
- [ ] **Mark done** available on first manual step (Print) when not blocked
- [ ] **Material stock** shows reserved materials / “Stock reserved” when reservation ran
- [ ] **Raw payload (debug)** expands and shows JSON

### 5.2 Unmatched order

Open an unmatched fixture.

- [ ] Unmatched badge / warning notice
- [ ] Line item(s) show Unmatched (no product)
- [ ] No (or incomplete) workflow as designed — clear warning, UI still usable

### 5.3 Cancelled order

Open a cancelled fixture.

- [ ] Cancelled badge clear
- [ ] Workflow actions blocked / no fresh reservation expected
- [ ] Stock panel explains cancelled / no reservation / deferred reversal as applicable

---

## 6. Material stock from orders

*Best after a clean slate (§0.3): confirm seed stock → Sync once.*

### 6.1 Auto-decrement on Sync create

1. Before Sync: note vinyl + laminate `current_stock`.  
2. Sync now (first create).  
3. Re-check Materials + matched order detail.

- [ ] Stock dropped by recipe × qty for matched open orders only
- [ ] Material edit → recent log shows reason **`new_order`** (negative qty)
- [ ] Value fields (`value_change` / value on hand) look consistent (not blank-broken)
- [ ] Cancelled / unmatched-only orders did **not** reserve stock
- [ ] Second Sync does **not** drop stock again for the same orders

### 6.2 Manual adjust

1. Materials → edit vinyl → enter delta (e.g. `+5` or `-2`) → save.

- [ ] `current_stock` updates
- [ ] Log shows **`manual_adjustment`**
- [ ] Negative stock allowed if you push below zero (by design)

### 6.3 Unit-cost override / revalue

1. On a material with stock: change **unit cost** override → save.

- [ ] WA display stays distinct from the override control (copy makes sense)
- [ ] Value on hand revalues (`stock × unit cost`)
- [ ] A stock-log row records the value change

---

## 7. Workflow on an order (manual + timer)

Use a matched open order with progress. Prefer a clean create so current step is **Print**.

### 7.1 Manual Mark done

- [ ] Current step is **Print**
- [ ] **Mark done** advances to **Dry**
- [ ] List / detail show updated current step

### 7.2 Timer hard-gate

- [ ] On **Dry**, Mark done is **disabled** / blocked while timer running
- [ ] Countdown or “Unlocks at … UTC” visible
- [ ] After timer elapses **or** you unlock it (wait ~15 min and refresh / visit the site so WP-Cron runs, or temporarily set the engine tick interval low and trigger cron via Local), Mark done becomes available
- [ ] Mark done on Dry → **Laminate** (timer does **not** auto-advance without Mark done)

### 7.3 Walk further manual steps (spot-check)

- [ ] Laminate → Cut → Pack → Ship each advance with Mark done
- [ ] Cancelled order cannot Mark done

### 7.4 Thank-you enters batch (not per-order Mark done)

After Ship → Thank-you:

- [ ] Progress status is **waiting_batch** (badge / copy)
- [ ] Per-order **Mark done** is **hidden / disabled** with explanation that advance is batch-level
- [ ] **View batch** link goes to `Batches` with that batch expanded / focused
- [ ] Order appears as a member under **Batches**

---

## 8. Products CRUD & costing UI

### 8.1 Create / edit / deactivate

1. **Products** → Add product (unique SKU).  
2. Assign workflow, attach recipe row(s), set **target selling price**, save.  
3. Deactivate product.

- [ ] Create / edit save without error
- [ ] Recipe add/remove rows work; duplicate material on same recipe is rejected or blocked
- [ ] Deactivate marks inactive / hides from active expectations (no hard delete required)
- [ ] Cannot leave critical fields broken (blank required SKU etc. fails gracefully)

### 8.2 Product Costing surfaces

- [ ] Products **list** shows target / material cost / margin columns (and badges when goals fire)
- [ ] Product **edit** Costing panel shows recipe cost, profit/margin, listing prices side by side when listings exist
- [ ] Changing target price updates margin display after save

---

## 9. Materials list & edit (purchasing surfaces)

### 9.1 List

- [ ] WA + value on hand columns visible
- [ ] Low-stock highlighting when under threshold
- [ ] Goal-alert **badges** appear when a material is approaching / over a workflow goal (set goals in §10, then receive a PO in §12 to fire)

### 9.2 Edit extras

- [ ] Preferred supplier dropdown works (after a supplier exists — §11)
- [ ] Average lead time shows after at least one completed receive with dates
- [ ] **Purchase history** table lists past PO lines (date, supplier, qty, landed unit, link to PO)
- [ ] Recent stock log still visible

---

## 10. Workflow editor (gates, goals, batch)

### 10.1 Template + steps

1. **Workflows** → create a small template (2–3 steps).  
2. Reorder with up/down; set timer on one; manual on another; save.  
3. Add a script config (local / api / n8n form **or** raw JSON); save; reopen.

- [ ] Create template works
- [ ] Add / remove / reorder persist
- [ ] Timer value + unit persist as expected seconds
- [ ] Script form / raw JSON round-trips
- [ ] Deactivate blocked while a product still uses the template (try on seed template or assign first)

### 10.2 Batch group on a step

1. Assign **shipping_label** (or thank_you_card) to a step.  
2. Also tick manual and/or timer and/or script → note warning.  
3. Try save with invalid combo.

- [ ] Batch dropdown lists groups
- [ ] Combo warning appears in UI
- [ ] Save **rejects** batch + other gates together
- [ ] Saving batch-only (other gates cleared) succeeds

### 10.3 Material cost goals

1. On a template, add goal row(s) for a material (target / approaching thresholds).  
2. Save; reopen.

- [ ] Goals persist
- [ ] Copy notes that reassignment / WA changes affect alerts (P5 messaging present)

---

## 11. Suppliers

1. **Suppliers** → Add supplier (name, contact, notes).  
2. Edit and save again.  
3. Search / list.

- [ ] Create / edit / list work
- [ ] **No delete** control (by design)
- [ ] Supplier selectable later as preferred supplier and on POs

---

## 12. Purchase orders & costing

*Use GBP amounts. Keep a notepad of stock / WA before and after receives.*

### 12.1 Create PO

1. **Purchase Orders** → Add.  
2. Choose supplier, order date, shipping cost, other cost.  
3. Add two material lines with **item_cost** = **total line cost** (not unit price).  
4. Save → status **ordered**.

- [ ] PO creates and appears on list
- [ ] Filters by status / supplier work

### 12.2 Preview Impact (before receive)

1. On create/edit (including unsaved field changes), click **Preview Impact**.

- [ ] Preview panel shows projected WA / goal / product margin impact
- [ ] Stock and WA on Materials **do not** change from Preview alone

### 12.3 Partial receive

1. Open PO → **Receive stock**.  
2. Enter a **short** delta on one line only (leave other 0 to skip).  
3. Submit.

- [ ] Status → **partially_received**
- [ ] Stock rises only for received qty
- [ ] Log reason **`purchase_received`**
- [ ] WA / value on hand update on Materials
- [ ] `received_date` set / updated
- [ ] Success notice; if goals fire, **alert summary** appears on the receive flow

### 12.4 Later receive → fully received

1. Receive remaining (or **over-receive**).

- [ ] Status → **received** when every line `qty_received >= qty_ordered`
- [ ] Over-receive accepted
- [ ] Later receive while partially open still works

### 12.5 Edit lock after first receive

1. Re-open a PO that has any receipt.

- [ ] Line items / costs **locked**
- [ ] Notes still editable
- [ ] Clear message about lock after first receipt

### 12.6 Mark received (shortfall) & Cancel

1. New PO → receive partial → **Mark received (accept shortfall)**.  
2. Another PO → receive something → **Cancel**.

- [ ] Mark received closes with shortfall accepted
- [ ] Cancel closes; **already-received stock is kept** (no reverse)
- [ ] Confirm dialogs appear before destructive closes

### 12.7 Zero line-cost + shipping warning

1. Create a PO with line `item_cost` all **0** but shipping/other > 0.  
2. Preview and/or receive.

- [ ] System does **not** allocate shipping/other into landed cost
- [ ] Warning is surfaced (preview and/or receive path)

### 12.8 Alerts after WA move

After a receive that pushes WA toward a goal:

- [ ] Materials **list** badge updates
- [ ] Material **edit** shows per-workflow breakdown
- [ ] Product Costing list/edit reflects pressure when that material is in the recipe

---

## 13. Batches UI & batch advance

### 13.1 List defaults & expand

1. **Batches** with at least one collecting/ready batch (from §7.4 or shipping_label path).

- [ ] Default list is **open** only (collecting / ready / processing / error)
- [ ] Optional **include done** / status / group filters work
- [ ] Expand batch → members show name + order ref
- [ ] Expand member → full shipping address

### 13.2 Group edit

1. Change display name and/or batch size → **Save groups**.

- [ ] Name / size persist
- [ ] Key and action type unchanged

### 13.3 Thank-you script batch (release / auto-size)

1. Get enough orders to size **4** in `thank_you_card`, **or** Release early while collecting.

- [ ] Auto-ready when size reached **or** **Release** on collecting works
- [ ] Script path runs once for the group (or soft-fails clearly if Python/reportlab missing — Retry available)
- [ ] On success, **all** members advance past Thank-you
- [ ] On `error`, batch + members show error; **Retry** resets and retries

### 13.4 Shipping-label manual batch

1. Assign `shipping_label` to a step (e.g. Ship) on a test template / product.  
2. Park ≥1 order in that batch.  
3. **Release** → **Mark done** on the batch.

- [ ] Manual-confirm group waits for batch Mark done
- [ ] Members advance together after Mark done
- [ ] Per-order Mark done stayed hidden while `waiting_batch`

### 13.5 Deep-link from order

- [ ] Order detail **View batch** opens Batches focused on that `batch_id`
- [ ] Done batches still open via deep-link even if hidden from default open list

---

## 14. Listings

### 14.1 Browse

1. **Listings**.

- [ ] Seeded / cached listings appear (eBay + Etsy)
- [ ] Channel filter + search work
- [ ] Open a listing → edit form loads (title, description, price, qty / variations)

### 14.2 Map create

1. **Add listing map** — product + channel + external ID → save.

- [ ] New map appears in list
- [ ] Product edit “linked listings” can see it after refresh

### 14.3 Refresh & Push (dummy)

1. Edit a seed listing → change price or qty → save.  
2. **Push to channel**.  
3. Change local values → **Refresh from channel**.

- [ ] Push succeeds with notice (dummy simulate)
- [ ] Refresh pulls cached/fixture state without fatal
- [ ] Variations mode (if present on seed) editable and pushable

---

## 15. Scripts / settings for automation (UI-level)

### 15.1 Settings prerequisites

- [ ] Can set / rotate **REST API key** and see it saved (masked or confirm-on-blank keep)
- [ ] MCP toggle off by default; turning **on** saves
- [ ] Python binary field accepts a path (needed for real thank-you PDF on Local)

### 15.2 Script error + Retry on order (if you hit a non-batch script step)

If you configure a temporary api/n8n/local script step on a test template:

- [ ] Failed script shows error + attempt count on order detail
- [ ] **Retry now** is available after error
- [ ] Waiting-for-callback copy shows when that mode is used

*(Thank-you is batch-script in current seed — use Batches Retry for that path.)*

---

## 16. REST API (optional — still “user” with API key)

*Use the API key from Settings. Admin cookie also works for some routes. Skip if you only want UI.*

Base: `https://YOUR-LOCAL-SITE/wp-json/som/v1/` (use your Local site URL; `http` if SSL is off)  
Header: `X-SOM-API-Key: <key>`

### 16.1 Core (Sprint 11)

- [ ] `POST /orders` creates an external order (workflow + stock side effects when matched)
- [ ] Duplicate same `external_order_id` → **409**
- [ ] `POST /orders/{id}/advance-step` advances like Mark done
- [ ] Order detail **Mark done** still works (UI uses REST under the hood)

### 16.2 Purchasing / batches (U7) — spot checks

- [ ] `GET /suppliers` returns list; `POST` creates; `PUT` updates; **no DELETE** route used
- [ ] `POST /purchase-orders/preview` returns impact without writing stock
- [ ] `POST /purchase-orders/{id}/receive` updates stock / WA
- [ ] `GET /batches` open list; `POST …/release` and `…/mark-done` (and `…/retry` if error)
- [ ] `GET /batch-groups` read-only

---

## 17. MCP Abilities (optional)

1. Install **WordPress MCP Adapter** on Local if not already (zip / plugin install — see [`MCP.md`](../../MCP.md); unlike wp-env it is not auto-bundled).  
2. Settings → enable **MCP / Abilities**.  
3. Confirm abilities register per `MCP.md` (Application Password + Cursor config as needed).

- [ ] MCP Adapter active on Local (or Skip this whole section)
- [ ] With toggle **off**, abilities are not registered / not queryable
- [ ] With toggle **on**, can list/read orders, products, materials, listings, suppliers, POs, batches, batch groups, goals
- [ ] Materials/products responses include costing enrichments (WA, value, target, margin, alerts)
- [ ] **No channel credentials** appear in any ability payload

Interactive Cursor / Claude connector setup is a **manual** one-time config — document Pass only if you completed it.

---

## 18. Known deferred (expect Skip — do not Fail)

| Item | Why |
|---|---|
| Cancel → stock reversal (`order_cancelled`) | D3/A3 — UI may say reversal not applied yet |
| Live eBay/Etsy OAuth + live Sync/Push | Needs developer apps; use dummy path for this checklist unless apps are ready |
| Dashboard cost-alerts widget | Explicitly out of update (P4) |
| Multi-currency | GBP only |
| Edit received PO costs to rewrite WA | Corrections via adjustment / unit-cost override only |
| Batch + timer/script/manual on **same** step | Batch-only steps in v1 |
| Write Abilities / admin rewritten onto REST | Admin stays form POST / ajax |
| Thank-you PDF without Python/reportlab | Soft fail / retry until tooling installed |
| Amazon-specific email automation | REST create groundwork only |

---

## 19. Judgment checklist (not pass/fail binaries)

Use while walking the UI; note free-text feedback:

1. Is personalisation + address easy enough for packing?  
2. Are unmatched / no-workflow flags strong enough to avoid shipping mistakes?  
3. Are seed step names / timers sensible for real bin-sticker production?  
4. Is negative stock as a warning acceptable?  
5. Are PO receive + Preview Impact clear for day-to-day purchasing?  
6. Is WA vs unit-cost override wording understandable?  
7. Are goal alerts useful or noisy?  
8. Is the expandable Batches list good enough vs a separate detail page?  
9. Does thank-you batch size 4 / cross-workflow pooling feel right?  
10. Anything missing for your next live order week?

---

## Suggested minimal path (≈60–90 min)

If you only have one sitting:

1. §0 Prep → §1 Menu → §2 Seed → §3 Sync  
2. §4–§5 Orders list + matched / unmatched / cancelled detail  
3. §6 Stock decrement + manual adjust  
4. §7 Mark done + timer + thank-you → waiting_batch  
5. §11–§12 One supplier + one PO (preview → partial → full)  
6. §13 Release / mark-done a batch  
7. §14 One listing Refresh/Push  
8. Skim §18 deferred so you don’t hunt ghosts  

---

## Optional developer smokes (not user tests)

These scripts were written for **wp-env** CLI. On Local you can **Skip**, or run them only if you have WP-CLI against this site (Local’s site shell / `wp` pointing at `app/public`):

```bash
wp eval-file wp-content/plugins/orderMachine/tests/sprint-u7-smoke.php
# Optional regression:
wp eval-file wp-content/plugins/orderMachine/tests/sprint-u4-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint-u5-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint-u6-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint11-smoke.php
```

- [ ] U7 smoke PASS (optional / Skip on Local)
- [ ] Other smokes PASS (optional / Skip on Local)

---

*End of user acceptance tests. Aligns with shipped scope through plugin **0.18.0** / DB **1.5.0**.*
