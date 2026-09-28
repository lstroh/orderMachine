# Update Package 6 — Sprint Plan

*Planning only — no plugin code in this pass. Specs: `01`–`06` in this folder. Baseline: plugin **v0.29.1**, schema **1.14.0**.*

**Sequencing:** Per-line make + Make board → Pack board / gates → Seed rewrite + migrate. Matches `01-Update-Overview.md` recommended order.

Settled product decisions in `01-Update-Overview.md` are **locked** (ship-together; thank-you in pack checklist; per-line make; Make + Pack boards; package required; internal always pack-ready / never Pack board; remove thank-you batch from pack path; browser print; hold blocks Ship; packed-by auto-stamp; optional pack weight). This plan does **not** reopen them.

Open items: soft defaults below apply unless overturned before the relevant sprint kickoff.

---

## 1. Consolidated open items

| # | Source | Item | Blocks | Status / decision |
|---|---|---|---|---|
| O1 | `02` §A2 / open 1; `03` §6.3 | Unmatched sellable line (`product_id` null) | UP6-S1/S2 Ship gate | **Default:** **block Ship** (not pack-ready) |
| O2 | `02` §A3 / open 2 | Pack progress storage | UP6-S2 schema/engine | **Default:** reuse **`order_step_progress`** + `orders.current_step_id` for pack steps only |
| O3 | `02` §D1 / open 3 | `workflow_templates.kind` | UP6-S1 schema + UI | **Default:** add column `ENUM('make','pack') NOT NULL DEFAULT 'make'` |
| O4 | `02` §A2 / open 4 | Cache `order_items.make_status` | UP6-S1 schema | **Default:** **no** column — derive in application code |
| O5 | `02` open 5; `04` §6.2 | Refresh `packed_at` on checklist re-save? | UP6-S2 stamp | **Default:** stamp **once** on first transition to complete; later edits do not refresh |
| O6 | `02` open 6; `05` §6 / §8.3 | In-flight open orders / repair strength | UP6-S3 migrate | **Default:** leave legacy progress rows; new creates use new model; ship **admin “Repair pack binding”** (assign default Pack + pack progress when missing) + docs |
| O7 | `03` §6.1 | Make board column model (multi-template) | UP6-S1 board | **Default:** filter by make workflow template; columns = that template’s steps |
| O8 | `03` §6.2 | Fate of `som-orders-board` | UP6-S1/S2 menus | **Default:** **repurpose** `som-orders-board` → **Make board** (line cards); add **`som-pack-board`** menu |
| O9 | `03` §6.3 | Sellable line with no workflow template | UP6-S1/S2 Ship gate | **Default:** **block Ship** (flag unassigned make; not pack-ready) |
| O10 | `03` §6.4 | Batch groups on make steps in v1? | UP6-S1 workflow save | **Default:** **disallow** — reject save (or hard-warn + reject) when `kind=make` and `batch_group_id` set |
| O11 | `03` §6.5 | Show Produce N internal jobs on Make board? | UP6-S1 board | **Default:** **yes** (Internal channel lines on Make only; never Pack) |
| O12 | `04` §6.1 | “Waiting for make” vs first pack step | UP6-S2 Pack board | **Default:** dedicated **Waiting for make** column/bucket before first pack step; Ship still blocked |
| O13 | `04` §6.3 | Open thank-you batch members after removal | UP6-S3 | **Default:** **document manual** clear; do not auto-rewrite collecting batches |
| O14 | `04` §6.4 | Pack template missing on create | UP6-S2 assign | **Default:** **soft flag** in UI; still create order; **block Ship** until Pack template configured/bound |
| O15 | `04` §6.5 | Address confirm vs merge into checklist | UP6-S2/S3 seed | **Default:** keep **separate** `shipping_address` step |
| O16 | `05` §8.1 | Rename make template | UP6-S3 seed | **Default:** rename seed to **Bin Sticker Make**; look up by option / old name for idempotency |
| O17 | `05` §8.2 | Package selection as own step vs app-only | UP6-S2/S3 | **Default:** **app gate** on Ship + dedicated seed step label **Package** (manual confirm); require `shipping_package_id` |
| O18 | `05` §8.4 | Restore seed Pack template idempotent? | UP6-S3 seed | **Default:** **yes** — option `som_seed_pack_workflow_id` + set `som_pack_workflow_template_id` when empty |
| O19 | Code | Interim make assign while seed still has pack steps | UP6-S1 assign | **Default:** when building line make progress, **stop before** first step with `packing_items` / `shipping_address`, or name matching Pack/Ship/Thank-you/Review (case-insensitive). After S3 truncate, heuristic is a no-op on seed make |
| O20 | Code | `convert_thankyou_steps()` on activate | UP6-S3 | **Must not** attach batch to Pack template steps; Pack seed uses checklist only (no `run_thankyou_card_script`). Optionally skip convert when step’s template `kind=pack` |
| O21 | Code | Admin menu / asset allowlists for Pack board | UP6-S2 | Register `som-pack-board` everywhere page gates exist (same pattern as shipping packages) |
| O22 | Code | Order complete = last pack step (not last make step) | UP6-S2 | Non-internal: `is_complete` when pack workflow finishes. Internal Produce N: complete when make finishes (no pack) |
| O23 | Code | Material budget funding still primary-product scoped | Out of P6 behaviour change | **Leave as-is** this package (order-level funding via primary product); do not redesign budgets |

---

## 2. Clarifying questions (defaults unless overturned)

Answers already locked in overview are not re-asked. Soft defaults:

### Make (UP6-S1)

1. **`kind` column on templates?** — default yes (`make`/`pack`).  
2. **No `make_status` cache column?** — default yes (derive).  
3. **Make board = repurposed `som-orders-board` (line cards), filter by template?** — default yes.  
4. **Produce N lines on Make board?** — default yes.  
5. **No batch on make templates?** — default yes.  
6. **Unmatched / no-template sellable lines block Ship?** — default yes.  
7. **Interim assign truncates before pack-ish steps?** — default yes (O19).

### Pack (UP6-S2)

8. **Pack progress in existing `order_step_progress`?** — default yes.  
9. **Missing Pack template = soft flag, block Ship?** — default yes.  
10. **Waiting for make column on Pack board?** — default yes.  
11. **`packed_at` stamp once?** — default yes.  
12. **Address confirm stays its own step?** — default yes.  
13. **Package = app gate + seed “Package” step?** — default yes.

### Seed / migrate (UP6-S3)

14. **Rename seed make to Bin Sticker Make?** — default yes.  
15. **Admin Repair pack binding + docs (not docs-only)?** — default yes.  
16. **Thank-you collecting batches: manual clear docs only?** — default yes.  
17. **Idempotent Pack seed + set site option when empty?** — default yes.

Overturn any of these before kickoff if needed.

---

## 3. Spec ↔ codebase discrepancies

Treat **existing plugin code as ground truth**. None block planning; they shape sprint work.

| Spec assumption | Actual code today | Plan impact |
|---|---|---|
| One workflow via primary product | [`SOM_Workflow_Engine::assign_on_create`](../../includes/class-som-workflow-engine.php) + `primary_product_id()` | S1 replaces with per-line make assign; stop inserting full product chain into order-level progress for new orders |
| Order Kanban | [`som-orders-board`](../../admin/views/orders-board.php) + JS; cards = orders by `current_step_id` | S1 repurposes to **line** Make board; S2 adds Pack board (order cards) |
| `packing_items` | [`SOM_Step_Confirmations`](../../includes/class-som-step-confirmations.php) — one tick per line only | S2 extend state/complete check with **`thank_you_included`** (or equivalent) |
| Thank-you batch | Seed Thank-you step uses `run_thankyou_card_script`; [`convert_thankyou_steps()`](../../includes/class-som-batch-groups.php) on activate | S3 Pack seed must not use that script; guard convert for `kind=pack` (O20) |
| Ship gate | [`SOM_Shipments::step_requires_shipment`](../../includes/class-som-shipments.php) + engine `can_mark_done` | Keep; add make-ready, hold, package, checklist gates in S2 |
| Shipping packages | Catalogue + product `package_id` + order `planned_shipping_gbp` (P5) | Pack selects order `shipping_package_id` (new column); product default = suggestion only |
| Order notes | [`SOM_Order_Notes`](../../includes/class-som-order-notes.php) on order detail | Surface read-only on Pack panel (S2) |
| Internal / Produce N | [`SOM_Production`](../../includes/class-som-production.php) channel `internal`; still calls `assign_on_create` | S1: make progress for internal product lines only; no pack bind. S2: exclude Internal from Pack board |
| Schema | `DB_VERSION` **1.14.0**; no item progress; no template `kind`; no pack columns on orders | S1 → **1.15.0**; S2 → **1.16.0** |
| Order complete | Last step of assigned template → `is_complete` | S2: non-internal complete on **pack** finish (O22); make-complete ≠ order-complete |
| Budgets / primary product | Material budgets scope by primary-product workflow | Leave unchanged this package (O23) |

---

## 4. Sprints

### UP6-S1 — Per-line make + Make board

- **Covers:** `03-Update-Make-Workflows.md` + `02` §A + §D (`kind`)  
- **Open items first:** O1, O3, O4, O7–O11, O19  
- **DDL:** `order_item_step_progress`; `workflow_templates.kind`; bump **1.15.0**  
- **Note:** Pack/Ship path for **new** non-internal orders is incomplete until UP6-S2 (make-only assign). Document in FEATURES for the interim release.

**Files (expected):**

| File | Change |
|---|---|
| `includes/class-som-db.php` | New item progress table; `kind` on templates; DB **1.15.0** |
| `includes/class-som-workflow-engine.php` | Per-line `assign_on_create` / advance / mark-done (line-scoped); interim truncate (O19); derive make-complete helpers |
| `includes/class-som-workflows.php` | Persist/filter `kind`; reject batch on make (O10); products may only assign `kind=make` |
| `includes/class-som-orders.php` | Load item make progress; list helpers for board |
| `admin/views/orders-board.php` (+ JS/CSS) | Repurpose → **Make board** (line cards, template filter, columns = steps) |
| `admin/views/order-detail.php` | Per-line Make section; internal pack-ready badge |
| `admin/views/workflow-templates.php` / step editor | Kind badge; make vs pack filters |
| `admin/class-som-admin-menu.php` | Make board labels/handlers; line mark-done actions |
| `orderMachine.php` | Version bump with sprint |
| `tests/sprint-up6-s1-smoke.php` | **New** — multi-line assign, internal skip, unmatched flag, line advance, board query |
| Docs | FEATURES / USER — Make board + interim Ship note |

**Done when:**

- New order with multiple sellable lines gets **per-line** make progress (not one primary-product chain for make).  
- Internal product lines: no make rows; treated pack-ready.  
- Internal Produce N orders appear on Make board; no pack binding.  
- Make board shows line cards; advancing a card advances that line only.  
- Make templates cannot save with batch groups (O10).  
- wp-env smoke passes.

---

### UP6-S2 — Pack workflow, Pack board, gates

- **Covers:** `04-Update-Pack-Ship.md` + `02` §B–C  
- **Open items first:** O2, O5, O12, O14, O15, O17, O21, O22  
- **DDL:** order pack columns; `shipments.pack_weight_grams`; bump **1.16.0**  
- **Confirmations:** extend `packing_items` (or successor) with thank-you tick

**Files (expected):**

| File | Change |
|---|---|
| `includes/class-som-db.php` | `orders.pack_workflow_template_id`, `shipping_package_id`, hold + packed_* fields; shipment `pack_weight_grams`; DB **1.16.0** |
| `includes/class-som-workflow-engine.php` | Bind pack template on create (non-internal); pack progress in `order_step_progress`; Ship gates (make-ready, hold, package, checklist); `is_complete` = pack done (O22) |
| `includes/class-som-step-confirmations.php` | Checklist = all lines + thank-you; stamp packed_by/at once (O5) |
| `includes/class-som-shipments.php` | Optional pack weight validate/save/display |
| `includes/class-som-settings.php` / workflows UI | Option `som_pack_workflow_template_id` (pack kind only) |
| `admin/views/orders-pack-board.php` (+ JS) | **New** — order Kanban; Waiting for make (O12); Held badge |
| `admin/views/order-detail.php` | Pack panel: checklist, package select, hold, print CSS, buyer note, notes, shipment weight |
| `admin/class-som-admin-menu.php` | `som-pack-board` menu + allowlists (O21); pack actions |
| `orderMachine.php` | Require + version |
| `tests/sprint-up6-s2-smoke.php` | **New** — pack bind, ship-together gate, hold blocks, package required, thank-you tick, weight optional, Internal excluded |
| Docs | FEATURES / USER — Pack board + gates |

**Done when:**

- Non-internal new orders bind default Pack template (or soft-flag if missing — O14).  
- Pack board lists orders (not Internal); Waiting for make when lines incomplete.  
- Ship blocked until: all sellable lines make-complete (O1/O9), no hold, checklist+thank-you, package set, address confirm, shipment row.  
- Browser print pack list works.  
- Packed-by auto-stamps once.  
- wp-env smoke passes.

---

### UP6-S3 — Seed rewrite + migrate

- **Covers:** `05-Update-Seed-Migration.md`  
- **Open items first:** O6, O13, O16, O18, O20  
- **DDL:** none expected (unless tiny additive fix)

**Files (expected):**

| File | Change |
|---|---|
| `includes/seed/class-som-seed.php` | Truncate/rewrite make → **Bin Sticker Make** (Print…Cut); create **Order Pack & Ship** pack template; set options idempotently (O16/O18); no thank-you batch on pack |
| `includes/class-som-batch-groups.php` | Guard `convert_thankyou_steps` for pack kind / avoid rewriting pack steps (O20) |
| Admin repair action (settings or tools) | “Repair pack binding” for open non-internal orders missing pack bind (O6) |
| Docs | USER-GUIDE / USER-REFERENCE / FEATURES — migrate steps; thank-you batch manual clear (O13); Make vs Pack |
| `stikerts/wordpress/Sprint-Progress.md` | Point at Update-6 progress when sprints land |
| `tests/sprint-up6-s3-smoke.php` | **New** — seed shape (make ends at Cut; pack has checklist+address+package+ship+review; no batch on thank-you); repair assigns pack |

**Done when:**

- Fresh seed / Restore seed: make template ends at Cut; Pack template exists and is site default when unset.  
- Thank-you is checklist-only on pack path (no batch gate).  
- Migrate docs + repair helper cover existing Local/wp-env sites.  
- wp-env smoke passes.

---

## 5. Suggested SemVer (alpha 0.x)

| After sprint | Plugin (approx) | Schema |
|---|---|---|
| UP6-S1 | 0.30.0 | 1.15.0 |
| UP6-S2 | 0.31.0 | 1.16.0 |
| UP6-S3 | 0.32.0 | unchanged (unless tiny fix) |

Exact bumps follow `RELEASE.md` when cutting tags.

---

## 6. Out of scope (this package)

- Barcode scan-to-verify  
- Multi-carton / split shipments  
- Live carrier rate APIs / auto-label purchase  
- Insert inventory SKUs / promo / care-card matrix  
- Customer-facing production tracker  
- Changing Analytics / `order_profit` postage rules  
- Redesigning material budget primary-product scoping (O23)  
- Auto-rewriting every custom (non-seed) workflow on customer sites  
- Deleting `thank_you_card` / `shipping_label` batch group rows  

---

## 7. Progress tracking

After each implemented sprint, record verification in **`Update-6-Sprint-Progress.md`** (create on first implementation sprint).

---

## 8. Explicit scope of this document

This file is the Package 6 **sprint plan** only. It does not implement features. Implementation starts when you explicitly ask to implement **UP6-S1** (or a later sprint). Soft defaults above apply unless overturned before that sprint.
