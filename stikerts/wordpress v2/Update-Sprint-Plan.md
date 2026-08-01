# Update Sprint Plan (Purchasing + Batching)

*Planning deliverable for `stikerts/wordpress v2/` · No plugin code in this pass*

Assumption: Phases 1–11 base plugin exists (`SOM_VERSION` ~0.11.0, `SOM_DB::DB_VERSION` **1.3.0**). Additive only.

Spec sources: `01-Update-Overview.md`, `02-Update-Data-Model.md`, `03-Update-Raw-Material-Purchasing.md`, `04-Update-Batch-Processing.md`.

---

## Settled decisions (from clarifying answers)

| Topic | Decision |
|---|---|
| Partial receipts (P1) | Keep PO `partially_received` until remaining qty received or manually closed |
| Manual close (U2) | Offer **Mark received** (accept shortfall) **or Cancel**; no stock reverse on cancel |
| Later receives (U2) | Allowed while `ordered` / `partially_received`; input = **delta this shipment** |
| Fully received (U2) | Every line `quantity_received >= quantity_ordered` (over-receive OK) |
| `received_date` (U2) | Overwrite on every successful receive |
| PO edit lock (U2) | Full edit while `ordered` with no receipts; lock lines/costs after first receive (notes still editable) |
| Receive edges (U2) | Over-receive, 0 delta (skip line), duplicate materials on one PO OK |
| `item_cost` (U2) | Total line cost (not unit price), GBP |
| Preferred supplier (U2) | Editable on material create/edit |
| Supplier delete (U2) | No delete |
| Post-receive edits (P2) | No retroactive WA rewrite — corrections via separate stock/value adjustment + new log row |
| Currency (P3) | GBP only |
| Alert surfaces (P4) | Materials badges + Product Costing only; dashboard widget later |
| `other_cost` (Q5) | Allocate like shipping; **add** `allocated_other_cost` on `purchase_order_items`; landed = `(item_cost + allocated_shipping + allocated_other) / qty_received` |
| Manual `unit_cost` (Q6) | Keep editable override — **revalue** `total_value_on_hand = current_stock × unit_cost` and write a stock-log row with `value_change` (same adjustment path as corrections) |
| Thank-you Python (B1/Q7) | 4-up already exists; this update only wires PHP batch → existing CLI |
| Mixed-product sheets (B2) | OK |
| Batch size (B3) | Both groups start at **4** |
| Gate composition (Q10) | **Batch-only steps** for v1 (no timer/script/manual combo with `batch_group_id`) |
| Existing thank-you steps (Q11) | **Auto-convert** on upgrade: set `batch_group_id`, clear per-order `script_config` |
| Schema upgrades (Q12) | **Recommendation locked:** keep existing **dbDelta + `DB_VERSION` bump**; no new migration framework. After `dbDelta`, run an explicit `ALTER TABLE` for `order_step_progress.status` ENUM (`waiting_batch`) because dbDelta is unreliable for ENUM changes. New tables/columns go in the declarative `CREATE TABLE` strings in `includes/class-som-db.php` |
| REST / MCP (Q13) | Expose suppliers, POs, batches on **`som/v1`** (CRUD where needed) and **Abilities (read-only)** per standing architecture |
| Build order (Q14) | Shared schema sprint first, then feature UIs |
| Partial receive costing (U3) | Allocate full PO `shipping_cost` / `other_cost` across lines by `item_cost` (same totals rewritten on later receives). Stable inbound unit for WA: `(item_cost + allocated_*) / quantity_ordered`. Each shipment: `value_change = delta ×` that unit. Stored `landed_unit_cost` = that same stable unit (not cumulative `/ quantity_received`) |
| `unit_cost_at_time` (U3) | Purchase rows = inbound landed; consumption / manual = current WA |
| Sync `unit_cost` on WA (U3) | After receive WA update, also write new WA into `materials.unit_cost` |
| Zero line-cost allocation (U3) | If total `item_cost` is 0, do **not** allocate shipping/other; surface a warning |
| Consumption at zero stock (U3) | If WA undefined (`current_stock` 0 and value 0), use `unit_cost` if set, else `0` |
| U3 vs U4 boundary | U3 domain only: goals CRUD + alert checks; preview-in-memory service; recipe/margin helpers; correcting adjustment path. Preview button, goals UI, Product Costing, alert badges → **U4** |
| U3 smoke (U3) | Yes — worked examples from 03 §2 + consumption value check + preview parity |
| Product Costing UI (U4) | **Both:** costing columns on products list **and** Costing panel on product edit (target price, recipe cost/margin, goal alerts, listing prices) |
| Goals UI home (U4) | **Material cost goals** section on workflow template editor (`workflow-step-editor.php`) — template-level, not per step |
| Preview Impact (U4) | PO **create/edit only** (not receive); **admin-ajax** from current form fields (works unsaved) |
| Lead time (U4) | One **overall average** per material (`received_date − order_date` across past POs) |
| Purchase history (U4) | Dedicated PO-history table on material edit (date, supplier, qty, landed unit cost, link to PO) — not just filtered stock log |
| Alert badges (U4) | Materials **list** badges only; full per-workflow breakdown on material **edit** |
| Material unit cost UI (U4) | Read-only WA + value on hand; keep `unit_cost` as explicit override/revalue control with clearer copy |
| Post-receive alerts (U4) | Success notice with alert summary on the **receive** screen |
| U4 smoke (U4) | Yes — `tests/sprint-u4-smoke.php` (preview handler + goals save round-trip + costing UI data helpers) |
| In-flight thank-you (U5) | **No migration** — assume no in-flight `waiting_script` thank-you orders |
| Batch retry columns (U5) | Add `retry_count` + `retry_after` (datetime NULL) on `step_batches`; bump `DB_VERSION` → `1.5.0` |
| Batch error → members (U5) | When batch → `error`, flip each member’s `order_step_progress` to `error` (copy `last_error`) |
| Script auto-run (U5) | Same request: size/manual release → `ready` → `processing` → run script immediately |
| Cancelled in batch (U5) | Leave cancelled orders in the collecting/ready batch (do not remove or shrink) |
| Duplicate membership (U5) | No uniqueness enforcement |
| Batch domain retry (U5) | Expose `SOM_Batches::retry` (reset retry budget, re-enter processing) for U6/U7 |
| U5 smoke / version (U5) | Yes — `tests/sprint-u5-smoke.php`; `SOM_VERSION` → `0.16.0`; DB → `1.5.0` |
| Seed thank-you (U5) | Leave seed with per-order `script_config`; `convert_thankyou_steps` on activate remains the fix |
| Shipping-label convert (U5) | Engine only — no auto-assign of existing Ship steps; shipping_label opt-in stays **U6** |

---

## Spec vs codebase discrepancies (ground truth = code)

1. **Thank-you 4-up already implemented** — `stikerts/Thank you/thankyou_card.py` `render_sheet` + `thankyou_card_cli.py` accept 1–4 orders. **Closed in U5:** `SOM_Local_Actions::run_for_orders` builds multi-order JSON for batch release.
2. **No incremental ALTER migrator** — only `SOM_DB::create_tables()` + `maybe_upgrade()` version string check. Spec’s “migration step” language maps to bumping `DB_VERSION` and extending CREATE strings (+ explicit ENUM ALTER).
3. **`02-Update-Data-Model.md` has no Open items section** — only Migration notes. Open items are from 03–04 (+ codebase items below).
4. **Consumption must update value** — qty path in `SOM_Material_Stock` / `SOM_Materials::adjust_stock` must also maintain `total_value_on_hand` and log `unit_cost_at_time` / `value_change` once costing columns exist (additive extension, not a rewrite of decrement-on-create).
5. **`material_stock_log.reason` is `varchar(50)`** — adding `purchase_received` is app-level only.
6. **Base tables match** — materials, material_stock_log, products, workflow_steps, order_step_progress, workflow_templates, product_materials, orders all exist with expected columns. **U5 shipped** the batch gate (`batch_group_id` → `waiting_batch` via `SOM_Batches`).

Schema delta vs `02-Update-Data-Model.md`: add column `allocated_other_cost` on `purchase_order_items` (not in original Part A table — settled here). `batch_groups.key` is stored as DB column `group_key` (dbDelta cannot UNIQUE a reserved `key` column); PHP still exposes `->key`. U5 adds `retry_count` / `retry_after` on `step_batches` (not in original Part B — settled for batch-unit backoff).

---

## 1. Consolidated open items

### From `03-Update-Raw-Material-Purchasing.md` §7

| ID | Item | Status | Blocks |
|---|---|---|---|
| P1 | Partial receipts behaviour | **Settled** — keep open as `partially_received` | — |
| P2 | Edit after receive | **Settled** — correcting adjustment only | — |
| P3 | Multi-currency | **Settled** — GBP only | — |
| P4 | Dashboard cost alerts | **Settled** — defer widget | — |
| P5 | Workflow reassignment follows new goals | **Noted** — expected; document in UI copy | None |

### From `04-Update-Batch-Processing.md` §5

| ID | Item | Status | Blocks |
|---|---|---|---|
| B1 | thankyou_card batch mode | **Settled** — Python already 4-up; PHP wires batch | — |
| B2 | Mixed-workflow cards on one sheet | **Settled** — OK | — |
| B3 | batch_size per group | **Settled** — 4 for both | — |

### Codebase / planning extras (were blocking until answered)

| ID | Item | Status |
|---|---|---|
| X1 | Schema upgrade mechanism | **Settled** — dbDelta + version bump + ENUM ALTER |
| X2 | `other_cost` column shape | **Settled** — `allocated_other_cost` |
| X3 | Gate combo with batch | **Settled** — batch-only steps |
| X4 | Auto-convert thank-you steps | **Settled** — yes on upgrade |
| X5 | REST + Abilities scope | **Settled** — REST + read-only Abilities |
| X6 | Manual `unit_cost` vs WA | **Settled** — override revalues `total_value_on_hand` |

---

## 2. Clarifying questions (kept on record)

These were asked against the specs + codebase before settling the plan. Answers are in Settled decisions above; kept here for audit trail.

1. **P1 — Partial receipts:** Keep the same PO open as `partially_received` until remaining qty is received (or manually closed), or treat first receive as final? → **Keep open.**
2. **P2 — Post-receive edits:** Confirm no retroactive WA rewrite — corrections via separate stock/value adjustment + new log row? → **Yes.**
3. **P3 — Currency:** Confirm GBP only? → **Yes.**
4. **P4 — Alerts:** Materials + Product Costing only (no dashboard widget this update)? → **Yes; widget maybe later.**
5. **`other_cost`:** Allocate like shipping and include in landed unit cost? Store how? → **Yes; add `allocated_other_cost`.**
6. **Existing `unit_cost` field:** After WA lands, stop editing `unit_cost` or keep manual override? → **Keep manual override** (revalues `total_value_on_hand`).
7. **B1 — Thank-you Python:** Treat 4-up as already done; only extend PHP to pass the full batch list into the existing CLI? → **Yes.**
8. **B2 — Mixed products on one sheet:** Confirm OK? → **OK.**
9. **B3 — Batch size:** Both `thank_you_card` and `shipping_label` start at 4? → **Yes.**
10. **Gate composition:** Batch-only step for v1, or finish per-order gates then join batch? → **Batch-only step.**
11. **Migrating existing thank-you steps:** Auto-convert on upgrade to `batch_group_id` (clear per-order script)? → **Yes, convert.**
12. **Schema upgrades:** Follow existing dbDelta + `DB_VERSION` bump (+ explicit ENUM ALTER), or invent incremental migrator? → **Recommendation locked: dbDelta + bump + ENUM ALTER.**
13. **REST / MCP:** Admin UI only, or also expose suppliers/POs/batches on `som/v1` / Abilities? → **Also expose** (REST CRUD; Abilities read-only).
14. **Sprint order:** Purchasing first, Batching first, or interleaved? → **Schema for both early, then feature UIs.**

U3 clarifications (answered before build):

15. **Partial receive costing:** Use cumulative `/ quantity_received` for WA, or allocate once and use stable `/ quantity_ordered`? → **Allocate full shipping/other by `item_cost` once; stable landed = `/ quantity_ordered`; each shipment WA uses that unit.**
16. **`unit_cost_at_time` on purchases:** Landed vs WA? → **Purchase = landed; consumption/manual = WA.**
17. **Sync `unit_cost` after WA?** → **Yes.**
18. **Zero total line cost + shipping?** → **Do not allocate; warn.**
19. **Consumption at zero stock / undefined WA?** → **Fall back to `unit_cost`, else 0.**
20. **U3 vs U4?** → **Domain only in U3; UI surfaces in U4.**
21. **U3 smoke test?** → **Yes.**

U4 clarifications (answered before build):

22. **Product Costing home:** Separate page, edit panel, or both? → **Both** (list columns + edit panel).
23. **Goals UI:** Template-level section on workflow editor (not per step)? → **Yes.**
24. **Preview Impact:** Create/edit only; admin-ajax from unsaved form? → **Yes** (not on receive; admin-ajax).
25. **Lead time:** Overall average vs per-supplier breakdown? → **Overall average.**
26. **Purchase history:** Filtered stock log or dedicated PO-history table? → **Dedicated PO-history table.**
27. **Alert badges:** List + edit, or list badges + edit breakdown? → **List badges; full workflow breakdown on edit.**
28. **Material unit cost UI:** Read-only WA + value; `unit_cost` as override/revalue? → **Yes.**
29. **Post-receive alerts:** Receive-screen notice vs badges only? → **Receive-screen success notice.**
30. **U4 smoke?** → **Yes.**

U5 clarifications (answered before build):

31. **In-flight thank-you orders:** Auto-migrate into batches, leave stuck, or none expected? → **None expected — no migration.**
32. **Batch retry storage:** New columns on `step_batches`, encode in `last_error`, or other? → **Add `retry_count` + `retry_after` on `step_batches` (DB bump).**
33. **Member status on batch error:** Stay `waiting_batch` or flip to `error`? → **Flip members to `error`.**
34. **Script auto-run timing:** Same request vs park at `ready` for cron? → **Same request** (`ready` → `processing` → run).
35. **Cancelled orders in collecting batch:** Remove, leave, or block cancel? → **Leave in batch.**
36. **Duplicate membership uniqueness?** → **No.**
37. **Domain batch retry after error?** → **Yes — expose retry.**
38. **U5 smoke + version bump?** → **Yes** (`sprint-u5-smoke.php`; plugin `0.16.0`; DB `1.5.0`).
39. **Seed thank-you steps:** Set `batch_group_id` in seed, or keep convert-on-activate? → **Keep convert-on-activate.**
40. **Auto-convert shipping_label onto Ship steps in U5?** → **No — engine only; U6 editor opt-in.**

No further blockers.

---

## 3. Sprint breakdown

Shared schema first, then Purchasing depth, then Batching (engine + UI), then API/Abilities + polish. Interleave only where natural (schema is Sprint U1).

```mermaid
flowchart LR
  U1[U1 Shared schema]
  U2[U2 Suppliers and POs]
  U3[U3 Costing and goals]
  U4[U4 Purchasing UI]
  U5[U5 Batch engine]
  U6[U6 Batches UI and convert]
  U7[U7 REST and Abilities]
  U1 --> U2 --> U3 --> U4
  U1 --> U5 --> U6
  U4 --> U7
  U6 --> U7
```

### Sprint U1 — Shared schema upgrade

- **Covers:** Both features’ DDL; migration pattern; seed `batch_groups`; thank-you step auto-convert; suppliers domain class; material value backfill
- **Modify:** `includes/class-som-db.php` (bump `DB_VERSION` → `1.4.0`); `includes/seed/class-som-seed.php`; `orderMachine.php` (`SOM_VERSION` → `0.12.0`)
- **Create:** `includes/class-som-batch-groups.php` (ensure + thank-you convert); `includes/class-som-suppliers.php` (CRUD; admin UI still U2)
- **Schema:** 7 new tables per 02; `allocated_other_cost` on PO items; alter materials / material_stock_log / products / workflow_steps; ENUM + `waiting_batch`
- **Also:** backfill `total_value_on_hand = current_stock × unit_cost` where value still 0; auto-convert thank-you script steps → `batch_group_id` (pulled forward from U6)
- **Done when:** Fresh and upgraded installs create all tables/columns; `som_db_version` / plugin version bump; two batch groups exist; thank-you steps converted; existing material values backfilled; existing rows remain valid
- **Open items first:** none (all settled)

### Sprint U2 — Suppliers + purchase orders (domain, no full costing UI)

- **Covers:** Purchasing CRUD + receive status machine (partial receive)
- **Create:** `includes/class-som-purchase-orders.php` (`SOM_Suppliers` already shipped in U1)
- **Modify:** `orderMachine.php` requires; `admin/class-som-admin-menu.php` + views under `admin/views/` (suppliers list/edit, PO list/edit/receive); material edit for `preferred_supplier_id`; `SOM_Materials::adjust_stock` accepts `purchase_order_item_id`
- **Done when:** Can CRUD suppliers (no delete); create PO `ordered`; receive lines (delta; short → `partially_received`, all lines met → `received`); later receives; mark-received / cancel close; stock qty rises with `purchase_received` log rows (cost fields stubbed until U3); preferred supplier on materials
- **Open items first:** P1 settled; U2 clarifying answers recorded in Settled decisions

### Sprint U3 — Landed cost, weighted average, goals, preview

- **Covers:** Costing math + consumption value consistency + goals/alerts data layer + shared preview service (domain only; UI in U4)
- **Create:** e.g. `includes/class-som-material-costing.php` (landed allocation, WA update, preview-in-memory, goal checks); `includes/class-som-workflow-material-goals.php`; `tests/sprint-u3-smoke.php`
- **Modify:** `includes/class-som-materials.php` (`adjust_stock` writes `unit_cost_at_time` / `value_change` / updates `total_value_on_hand`; manual `unit_cost` override revalues; sync `unit_cost` from WA on receive); `includes/class-som-material-stock.php` (consumption uses same path); PO receive in U2 class calls costing service; product helpers for recipe cost / margin
- **Costing rules (settled):** Full PO shipping/other allocated by `item_cost`; stable landed = `(item_cost + allocated_*) / quantity_ordered`; WA delta uses that unit; purchase log `unit_cost_at_time` = landed, consumption = WA; zero total line cost → no allocation + warning; zero-stock consumption falls back to `unit_cost` or 0
- **Done when:** Receive runs worked examples from 03 §2; preview matches receive without DB writes; consumption keeps `total_value_on_hand` consistent; goals fire approaching/over; correcting adjustment path exists (no edit-received-PO rewrite); U3 smoke PASS
- **Open items first:** P2, X2, X6 + U3 clarifying answers settled

### Sprint U4 — Purchasing admin UI (costing surfaces)

- **Covers:** Materials enhanced, workflow goals UI, Product Costing, Preview Impact button, post-receive alert notice
- **Modify:** `admin/views/materials-list.php`, `admin/views/material-edit.php`, `admin/views/product-edit.php`, `admin/views/products-list.php`, `admin/views/workflow-step-editor.php` (goals section), `admin/views/purchase-order-edit.php` (Preview Impact), `admin/views/purchase-order-receive.php` (alert notice), `admin/class-som-admin-menu.php` (POST/ajax handlers), `admin/assets` CSS/JS; domain helpers as needed (avg lead time, PO history for material); `orderMachine.php` (`SOM_VERSION` → `0.15.0`); `tests/sprint-u4-smoke.php`
- **UI rules (settled):** Product Costing = list columns + edit panel; goals = template-level on workflow editor; Preview = admin-ajax on PO create/edit only; lead time = overall average; dedicated PO-history table on material edit; list badges + edit breakdown; WA/value read-only + `unit_cost` override; receive-screen alert notice
- **Done when:** All UI rows from 03 §6 work (except deferred dashboard widget); Preview Impact shows WA + goal + product margin impact; alerts on Materials list + Product Costing + receive notice; U4 smoke PASS
- **Open items first:** P4 settled (no widget); U4 clarifying answers settled

### Sprint U5 — Batch gate in workflow engine

- **Covers:** Batch Processing state machine (04 §2); batch-only step rule; batch-unit retry
- **Create:** `includes/class-som-batches.php` (collecting batch, add item, release, mark done, script run for group, domain retry); `tests/sprint-u5-smoke.php`
- **Modify:** `includes/class-som-db.php` (`step_batches.retry_count` / `retry_after`; `DB_VERSION` → `1.5.0`); `includes/class-som-workflow-engine.php` `enter_step` — if `batch_group_id` set → `waiting_batch` + enqueue (batch-only: ignore other gates for that step in v1); advance all members on batch done via each item’s `workflow_step_id`; on batch `error` flip members to `error`; `includes/class-som-local-actions.php` — batch thank-you path: build multi-order JSON, call existing CLI; `includes/class-som-workflows.php` step save validation (batch-only); `orderMachine.php` (`SOM_VERSION` → `0.16.0`)
- **Behaviour (settled):** Same-request script path on size/manual release; cancelled members left in batch; no duplicate-membership uniqueness; no in-flight thank-you migration; seed still relies on convert-on-activate; shipping_label assignment stays U6
- **Done when:** Orders pool cross-workflow; auto-ready at size 4; manual release; script group runs once for all members and advances all; manual_confirm group waits for mark-done; failure leaves whole batch + members in `error`; domain retry works; U5 smoke PASS
- **Open items first:** B1–B3, X3 + U5 clarifying answers settled

### Sprint U6 — Batches admin UI + thank-you step conversion

- **Covers:** Batches page; order detail waiting_batch; step editor `batch_group_id`
- **Create:** `admin/views/batches.php` (and detail if needed)
- **Modify:** `admin/class-som-admin-menu.php`, `admin/views/order-detail.php`, `admin/views/workflow-step-editor.php`; shipping_label opt-in via editor
- **Note:** Thank-you step auto-convert shipped in **U1** (`SOM_Batch_Groups::convert_thankyou_steps`) — U6 only needs UI + editor wiring; re-run convert remains idempotent on load
- **Done when:** Batches UI matches 04 §4; order detail links to batch; step editor can assign `batch_group_id` (shipping_label opt-in)
- **Open items first:** Q11 settled (convert done in U1)

### Sprint U7 — REST + Abilities + smoke tests

- **Covers:** `som/v1` suppliers, purchase orders (+ receive/preview), workflow material goals, batches (list/get/release/mark-done); read-only Abilities mirroring safe reads
- **Modify:** `includes/class-som-rest-api.php`, `includes/class-som-abilities.php`; smoke test under `tests/` (pattern like existing sprint smokes)
- **Done when:** Authenticated REST covers CRUD/actions needed by admin flows; Abilities list/get only (no credential leakage); smoke script covers schema presence, one PO receive WA path, one batch collect→release→advance
- **Open items first:** X5 settled

---

## 4. Out of scope (explicit)

- Dashboard cost-alerts widget
- Reorder-point formula (03 §4 future)
- Reworking order sync, core three gates, or listings
- Multi-currency
- Retroactive rewrite of received POs
- Combining batch with timer/script/manual on the same step
- Rewriting thank-you card PDF layout (already 4-up)

---

## 5. Next step

This file is the planning deliverable only. **Do not** implement plugin code until asked to execute sprints U1–U7.
