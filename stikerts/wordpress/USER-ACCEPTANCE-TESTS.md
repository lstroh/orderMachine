# Order Machine — User Acceptance Tests

*Click-through checklist for an operator (or reviewer) in wp-admin.*  
*Covers base Sprints 1–11 + Update Package 1 (U1–U7) + Update Package 2 (U2-1–U2-5) + Update Package 3 (UP3-S1–S4) · plugin **0.22.0** · DB **1.8.0**.*

Companions (feature explainers, not this pass/fail list):

- [`FEATURES-AND-TESTING.md`](FEATURES-AND-TESTING.md) — what each feature does + troubleshooting  
- [`Sprint-Progress.md`](Sprint-Progress.md) / [`../wordpress v2/Update-Sprint-Progress.md`](../wordpress%20v2/Update-Sprint-Progress.md) / [`../wordpress v3/Update-2-Sprint-Progress.md`](../wordpress%20v3/Update-2-Sprint-Progress.md) / [`../wordpress v4/Update-3-Sprint-Progress.md`](../wordpress%20v4/Update-3-Sprint-Progress.md) — what shipped  

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
- [ ] **Plugins** → **Order Machine** is **Active** (v0.22.0)
- [ ] Left menu shows **Order Machine**
- [ ] After opening any Order Machine screen, schema is **1.8.0** (fee + budget tables present — or confirm on next admin load after upgrade)

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
| Orders Board | Kanban board of open orders |
| Products | Products list |
| Materials | Materials list |
| Budgets | Budgets list |
| Suppliers | Suppliers list |
| Purchase Orders | Purchase orders list |
| Batches | Batch groups + open batches |
| Workflows | Workflow templates list |
| Listings | Listings list |
| Analytics | Analytics dashboard (charts) |
| Channel Fee Estimates | Fee estimate components by channel |
| Recurring Platform Expenses | Non-order-linked platform fees |
| Settings | Order Machine Settings |

- [ ] All fourteen screens load
- [ ] Orders Board sits directly under Orders; Budgets sits directly under Materials
- [ ] Analytics / Channel Fee Estimates / Recurring Platform Expenses sit before Settings

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
- [ ] **Product Costing** panel shows target / material cost / margin fields (and platform fee £/% when estimates exist — §20–§21)
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
- [ ] Fields for n8n base URL, order poll interval, engine tick interval, token refresh, **fee poll interval**
- [ ] **Platform fee sync** section: last-run / cursor status, **Sync fees now** button
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
- [ ] After budgets exist (§18): matching budgets gained `sale_funding` on first create (not on second Sync)

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
- [ ] **Platform fees** panel appears when fees have been synced for this order (§21); otherwise absent / empty as designed
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
- [ ] Description notes Adjust stock does **not** debit a material budget

### 6.3 Unit-cost override / revalue

1. On a material with stock: change **unit cost** override → save.

- [ ] WA display stays distinct from the override control (copy makes sense)
- [ ] Value on hand revalues (`stock × unit cost`)
- [ ] A stock-log row records the value change

### 6.4 R&D write-off (after a material budget exists — §18)

1. Materials → edit vinyl → **R&D / non-sale write-off** with qty + required notes.

- [ ] Stock decreases
- [ ] Linked active budget is debited (or clear message that stock-only when no active budget)
- [ ] Same write-off also available from the material budget detail (§18)

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

- [ ] Products **list** shows target / material cost / margin columns (and Est. fees / Actual fees badge when Package 3 fee math applies)
- [ ] Product **edit** Costing panel shows recipe cost, fee-aware profit/margin, per-channel estimated £ + %, actual £ + % with n= when synced, listing prices side by side when listings exist
- [ ] Changing target price updates margin / fee % display after save
- [ ] Variance highlight appears when estimate vs actual differs by ≥2 percentage points (after §20–§21)

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
- [ ] If an active material budget exists for that material (§18): balance drops; ledger shows **`purchase_spend`** linked to the PO

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
- [ ] Mark received (shortfall alone) does **not** post an extra budget draw-down beyond prior Receive lines

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

- [ ] `POST /orders` creates an external order (workflow + stock + budget funding side effects when matched / stock applied)
- [ ] Duplicate same `external_order_id` → **409**
- [ ] `POST /orders/{id}/advance-step` advances like Mark done
- [ ] Response includes `progress_status` (and batch / DnD meta when applicable) for Board clients
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
- [ ] Materials/products responses include costing enrichments (WA, value, target, fee-aware margin/profit, `platform_fees`, `fee_source`, alerts)
- [ ] **No channel credentials** appear in any ability payload

Interactive Cursor / Claude connector setup is a **manual** one-time config — document Pass only if you completed it.

---

## 18. Budgets

*Requires schema ≥ **1.6.0** (full Package 3 stack is **1.8.0**). Prefer after Sync (§3) and before or interleaved with PO receive (§12). Fee-aware `percent_of_profit` needs estimates / fee sync (§20–§21).*

### 18.1 List & create

1. **Budgets** (under Materials) → list loads (default **Active**).  
2. **Add budget** → create a **material** budget for vinyl (or laminate).  
3. Optionally tick one or more **workflow** templates for scope.  
4. Set a **target reserve**; save.  
5. Re-open the material budget → confirm **type** and linked **material** cannot be changed.  
6. **Add budget** → create a **manual** budget (`percent_of_price`, `percent_of_profit`, or `fixed_amount`); optionally tick product(s).  
7. Re-open the manual budget → type is fixed; funding method/value and product links remain editable.

- [ ] Menu placement under Materials; active default filter
- [ ] Material picker hides materials that already have a material budget
- [ ] Ink help text visible on create
- [ ] Material: workflow checkboxes only (not product scope UI)
- [ ] Manual: funding method/value + product checkboxes (not workflow scope UI)
- [ ] After create, type / `material_id` are immutable; other allowed fields stay editable
- [ ] List shows balances; low-balance / overspent badges when applicable (negative balances allowed)

### 18.2 Sale funding on Sync create

*Best after a clean incremental create (§0.3 / §6.1) with budgets already active.*

1. Note budget balances.  
2. Sync / create a matched open order with stock applied.  
3. Open budget detail → ledger.

- [ ] Ledger shows **`sale_funding`** linked to the order
- [ ] Balance increased for matching material + manual budgets (respecting scopes)
- [ ] Ledger grain: material budget → one `sale_funding` row per consumed `new_order` stock-log material line; manual → one row per order item × matching budget
- [ ] For `percent_of_profit`: funding uses revenue − materials − platform fees (estimate until actuals synced) — amount is lower than material-only profit would imply when fees apply
- [ ] (Optional) Line with **`unit_price = 0`**: % funding uses sold price 0 (does **not** fall back to `target_selling_price`)
- [ ] (Optional) Loss-making `percent_of_profit` may post a **negative** `sale_funding` (not clamped to 0)
- [ ] Second Sync does **not** add duplicate `sale_funding` for the same order
- [ ] History import path does **not** fund (prefer Sync now for this check)
- [ ] Inactive budget is skipped
- [ ] (Optional) Material budget scoped to a workflow the order does **not** use → that material budget is not funded; global / matching scopes still are

### 18.3 Draw-down on PO receive

1. Receive a PO line for the material that has a material budget (§12.3).  
2. On a multi-line receive, confirm other lines still complete even if you only care about budget behaviour on one material.

- [ ] Ledger **`purchase_spend`** (negative) with link to the PO
- [ ] Balance decreased by `delta × landed_unit_cost`
- [ ] Shortfall **Mark received** does not add an extra draw-down
- [ ] Draw-down is by PO-line material (workflow scope does not block receive draw-down)
- [ ] If a budget ledger write failed after stock, remaining receive lines would still complete (Skip unless you force a failure) — stock is kept; repair via manual adjustment if needed

### 18.4 Manual adjustment & R&D

1. Budget detail → **manual adjustment** with amount + **required** notes.  
2. Material budget detail → **R&D write-off** with qty + notes.  
3. Material edit → same R&D write-off.  
4. Plain Adjust stock on the material.

- [ ] Manual adjustment rejected without notes; accepted with notes; balance + ledger update
- [ ] R&D on budget detail: stock ↓ + budget debit
- [ ] R&D on material edit: same behaviour
- [ ] Adjust stock still works and does **not** change budget balance
- [ ] Ledger shows newest ~50 rows; `sale_funding` → order; `purchase_spend` → PO

### 18.5 Deactivate

1. Soft-deactivate a budget; try Sync / receive again.

- [ ] Deactivated budget no longer funds or draws down
- [ ] Can reactivate from edit

---

## 19. Orders Board

### 19.1 Read UI

1. **Orders Board** (directly under Orders).

- [ ] Open incomplete non-cancelled orders appear as cards
- [ ] Columns match current step names; **Unassigned** appears when needed
- [ ] Cancelled / completed orders are absent
- [ ] **View history** / Orders list link works for completed orders
- [ ] Cards show channel, buyer, personalisation preview, step, time in step, progress badges
- [ ] Batch link present when status is `waiting_batch`
- [ ] Only order ID, product name(s), and **View** are links (card body not one big link)
- [ ] Horizontal scroll works if columns overflow

### 19.2 Filters, pins, column order

1. Filter by channel, product, workflow, free-text (incl. a personalisation snippet).  
2. Pin ★ a card; toggle **Pinned only**; unpin.  
3. Use column header **←/→**; refresh the page.

- [ ] Each filter changes the card set sensibly
- [ ] Pin persists after refresh; pinned-only hides unpinned
- [ ] Column order persists per user after refresh
- [ ] Volume info notice if ≥200; warning + oldest-only if over 500 (optional / Skip if volume low)

### 19.3 Gated drag-and-drop

1. Find an **In progress** / advanceable card (grab cursor).  
2. Drag toward a **wrong** column.  
3. Drag into the **correct** next-step column (including an empty prefilled next-step column if shown).  
4. Confirm waiting / error / Unassigned cards do not drag.  
5. On a final-step advanceable card, drag into **Complete**.

- [ ] Wrong-column drop snaps back (no lasting move)
- [ ] Valid drop advances like Mark done; card moves to response step; badges update
- [ ] Locked cards (`waiting_*` / error / Unassigned) are not draggable
- [ ] Complete zone removes the card when the order becomes complete
- [ ] API/network failure alerts and snaps back (optional force / Skip)
- [ ] Column ←/→ still works; Complete zone is not saved into column order

---

## 20. Channel Fee Estimates

1. **Channel Fee Estimates** (before Settings).

### 20.1 Seeded defaults

- [ ] eBay and Etsy rows exist without manual create
- [ ] eBay has tiered `per_order_fee` (£0.30 under £10 / £0.40 at or above) and Promoted Listings **enabled**
- [ ] Etsy has listing fee, transaction %, payment processing % + fixed £, Offsite Ads **enabled**, VAT-on-fees row present
- [ ] Rate display shows % or £ sensibly; tier min/max visible where set

### 20.2 CRUD

1. Edit a rate / toggle Enabled → save → reload.  
2. Add a custom component → save.  
3. Delete the custom component.

- [ ] Edit + enable/disable persist
- [ ] Create / delete work without PHP errors
- [ ] Re-loading admin / re-activate does **not** overwrite your edited seeded rate (idempotent seed)

---

## 21. Platform fee sync, order fees & recurring expenses

### 21.1 Sync fees now

1. **Settings** → Platform fee sync → note fee poll interval (default 30).  
2. Click **Sync fees now**.

- [ ] Success / summary notice (inserted / skipped / unmatched as applicable)
- [ ] Last-run / cursor status updates
- [ ] Second Sync fees now does **not** duplicate the same external fee entry IDs

### 21.2 Order detail fees

1. Open a fixture / synced order that should have fee lines.

- [ ] **Platform fees** panel lists itemized amounts / components
- [ ] Orders without synced fees do not break the detail page

### 21.3 Recurring Platform Expenses

1. **Recurring Platform Expenses**.

- [ ] At least one listing-style / unmatched fee row appears after dummy Sync fees now
- [ ] Channel filter and listing filter narrow the list
- [ ] Page loads with no PHP errors when empty filters yield zero rows

### 21.4 Live reconnect (optional / Skip without live eBay)

- [ ] Live eBay connected before Finances scope → Settings shows reconnect warning
- [ ] Etsy does **not** require a new scope solely for ledger (existing `transactions_r`)

---

## 22. Analytics Dashboard

1. **Analytics**.

### 22.1 Shared filters & charts

1. Apply **Last 30 days** / **daily** (leave channel All).  
2. Confirm summary totals (sales / profit / priced-order count).  
3. Confirm five chart areas: sales, profit, AOV, orders by channel, material stock.

- [ ] Page loads; Chart.js canvases render (no blank white / console fatal)
- [ ] Sales / profit / AOV lines and orders-by-channel bar show data when fixture orders have sold prices
- [ ] Changing granularity / range / channel reloads via Apply (GET) without PHP error
- [ ] Custom date range fields appear when range = custom

### 22.2 Stock series

1. Select one or more materials → Apply.

- [ ] Stock chart empty / placeholder until materials selected
- [ ] After Apply with selection, series appears (backward from current stock)
- [ ] Clearing selection + Apply returns to empty stock chart

### 22.3 Exclusions

- [ ] Cancelled orders do not inflate sales / profit / AOV / channel counts
- [ ] Lines without sold `unit_price` are omitted from sales / profit / AOV (not zero-filled from target)

---

## 23. Known deferred (expect Skip — do not Fail)

| Item | Why |
|---|---|
| Cancel → stock reversal (`order_cancelled`) | D3/A3 — UI may say reversal not applied yet |
| Live eBay/Etsy OAuth + live Sync/Push / live Finances | Needs developer apps; use dummy path for this checklist unless apps are ready |
| Dashboard cost-alerts widget | Explicitly out of update (P4) |
| Multi-currency / FX on fee sync | GBP only; store fee amounts as returned |
| Edit received PO costs to rewrite WA | Corrections via adjustment / unit-cost override only |
| Batch + timer/script/manual on **same** step | Batch-only steps in v1 |
| Write Abilities / admin rewritten onto REST | Admin stays form POST / ajax |
| Thank-you PDF without Python/reportlab | Soft fail / retry until tooling installed |
| Amazon-specific email automation | REST create groundwork only |
| Amazon / SP-API Financials | Package 3 is eBay/Etsy only |
| Budget / Board REST + MCP Abilities | Package 2 is admin UI + existing `advance-step` only |
| Dedicated ink material type | Ops: recipe material or manual `fixed_amount` |
| Completed orders on the Board | Active/incomplete only by design |
| Stacked mobile board layout | Horizontal scroll only |
| Pre-computed analytics summary tables | Live queries only for v1 |
| Opaque blended “effective fee rate” product | £ + % of representative price only |
| Recurring expenses inside profit charts | Order fees only (same as Costing / budgets) |

---

## 24. Judgment checklist (not pass/fail binaries)

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
10. Are material vs manual budgets / scopes understandable? Low/overspent badges useful?  
11. Does the Orders Board (columns, pins, gated DnD, Complete) feel natural for production?  
12. Are seeded fee estimates / tiers / optional ads defaults sensible?  
13. Is Costing estimate vs actual (£ + %) clear enough for pricing decisions?  
14. Are Analytics filters + five charts enough for weekly ops review?  
15. Anything missing for your next live order week?

---

## Suggested minimal path (≈90–120 min)

If you only have one sitting:

1. §0 Prep → §1 Menu (all 14 screens) → §2 Seed → §3 Sync  
2. §4–§5 Orders list + matched / unmatched / cancelled detail  
3. §6 Stock decrement + manual adjust  
4. §7 Mark done + timer + thank-you → waiting_batch  
5. §20 Channel Fee Estimates seed + one edit  
6. §21 Sync fees now → order Platform fees + Recurring expenses  
7. §8.2 / product Costing fee £/% + list badge  
8. §18 Create one material + one manual (`percent_of_profit`) budget; confirm funding after a fresh create if possible  
9. §11–§12 One supplier + one PO (preview → partial → full); confirm budget draw-down  
10. §13 Release / mark-done a batch  
11. §19 Orders Board: pin/filter + one valid DnD advance (and Complete if convenient)  
12. §22 Analytics: Last 30 days + one material on stock chart  
13. §14 One listing Refresh/Push  
14. Skim §23 deferred so you don’t hunt ghosts  

---

## Optional developer smokes (not user tests)

These scripts were written for **wp-env** CLI. On Local you can **Skip**, or run them only if you have WP-CLI against this site (Local’s site shell / `wp` pointing at `app/public`):

```bash
wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s4-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s3-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s2-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s1-smoke.php
# Optional earlier packages:
wp eval-file wp-content/plugins/orderMachine/tests/sprint-u7-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint-u4-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint-u5-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint-u6-smoke.php
wp eval-file wp-content/plugins/orderMachine/tests/sprint11-smoke.php
```

- [ ] UP3 S1–S4 smokes PASS (optional / Skip on Local)
- [ ] U7 / other smokes PASS (optional / Skip on Local)

---

*End of user acceptance tests. Aligns with shipped scope through plugin **0.22.0** / DB **1.8.0** (Packages 1–3).*
