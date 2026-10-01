# Update Package 7 — Sprint Plan

*Planning only — no plugin code in this pass. Specs: `01`–`05` in this folder. Baseline: plugin **v0.32.2** (Package 6 complete).*

**Sequencing:** Make gate / confirmation UX → Pack materials core → Package materials + seed/docs. Matches `01-Update-Overview.md`.

Operator decisions already captured:

- Thank-you / packing supplies are **per pack (order)**, not per product recipe qty.  
- Need **Materials used** (planned vs actual) for those materials too.  
- Create PDF showing **Done** before work + checklist placement is broken/confusing (**BUG-003**).

Open items: soft defaults below apply unless overturned before the relevant sprint kickoff.

---

## 1. Consolidated open items

| # | Source | Item | Blocks | Status / decision |
|---|---|---|---|---|
| O1 | `03` §7.4 | When to reserve pack materials | UP7-S2 | **Default:** on **pack bind** (create/repair), not wait for make-ready |
| O2 | `02` §F1 / `03` §7.1 | Package change after reserve | UP7-S3 | **Default:** reverse prior `package_reserve`, then reserve new |
| O3 | `02` §F3 / `03` §7.2 | Same material, multiple sources in Materials used | UP7-S2 | **Default:** **separate rows** by Source (Recipe / Pack / Package) |
| O4 | `03` §7.3 | Product Costing packaging line | UP7-S3 | **Default:** **optional in S3**; S2 leaves costing recipe-only |
| O5 | `03` §7.5 | Budget scope for pack materials | UP7-S2 | **Default:** active material budgets; Pack template workflow scope if budget is workflow-scoped |
| O6 | `02` §C | Distinct stock reasons vs reuse `new_order` | UP7-S2 | **Default:** `pack_reserve` + `package_reserve` |
| O7 | `04` §4 | Hard-block ungated first make step? | UP7-S1 | **Default:** **warn only**, do not hard-block |
| O8 | `04` §2 | Create PDF: misconfig vs engine bug | UP7-S1 | **Verify first**; fix engine only if gates were set and still auto-done |
| O9 | Docs | Seed thank-you material + package envelope | UP7-S3 | **Default:** yes, idempotent seed rows |

---

## 2. Clarifying questions (defaults unless overturned)

1. **Pack materials on Pack workflow template?** — default yes.  
2. **Extra materials on shipping package?** — default yes (S3).  
3. **Reserve pack materials at pack bind?** — default yes (O1).  
4. **Separate Materials used rows by source?** — default yes (O3).  
5. **Product Costing packaging this package?** — default S3 optional (O4).  
6. **Warn (not block) zero-gate steps in editor?** — default yes (O7).

Overturn any of these before kickoff if needed.

---

## 3. Spec ↔ codebase discrepancies

Treat **existing plugin code as ground truth**.

| Area | Today | Package 7 direction |
|---|---|---|
| Material BOM | `product_materials` only | Add pack-template + package BOMs |
| Materials used | Planned from `new_order` only | Also `pack_reserve` / `package_reserve` |
| Shipping packages | Dims/tare/postage only | Optional material rows |
| Thank-you | Pack checklist tick only | + stocked pack material |
| Zero-gate | Auto-done on enter (make + pack engines) | Keep; warn in editor; fix UX |
| Make panel copy | Still says “Pack & Ship controls arrive in UP6-S2” | Remove |

---

## 4. Sprints

### UP7-S1 — Make step gate & confirmation UX (BUG-003)

- **Covers:** `04-Update-Make-Step-Gate-UX.md`  
- **Schema:** none  

| Work | Detail |
|---|---|
| Reproduce | Open operator make template; confirm gates on **Create PDF** vs **Print** (`requires_manual_confirm`, `confirmation_kind`) |
| Engine | If Create PDF had gates and still auto-completed → fix `SOM_Item_Make::enter_item_step` (and pack `enter_step` if same path) |
| Workflow editor | On save / live: warn when a step has zero gates (“will auto-complete when entered”) |
| New step default | Soft default: check **Requires manual confirm** on newly added step cards (JS), so Create PDF–style steps don’t silently zero-gate |
| Order detail UX | Nest confirmation checklist **under the current make/pack step**; heading includes step name |
| Copy | Remove “Pack & Ship controls arrive in UP6-S2” from Make panel (`order-detail.php`) |
| Docs | USER-REFERENCE / FEATURES: zero-gate note; BUG-003 → Fixed when done |
| Tests | `tests/sprint-up7-s1-smoke.php` — ungated auto-done; gated first step stays `in_progress`; checklist gates Mark done |

**Done when:**

- Operator can see which step the checklist belongs to.  
- Ungated steps are warned in the editor.  
- Gated first steps stay In progress on new orders.  
- Stale UP6-S2 Make copy is gone.  
- Smoke passes.

---

### UP7-S2 — Pack workflow materials + Materials used

- **Covers:** `03` pack-template half; `02` §A + §C (`pack_reserve`)  
- **Schema:** `workflow_pack_materials`; stock reason `pack_reserve`; bump `som_db_version`  

| Work | Detail |
|---|---|
| Schema / DB | `dbDelta` + version bump for `wp_som_workflow_pack_materials` |
| Model class | e.g. `SOM_Pack_Materials` — list/sync for template; validate pack kind only |
| Workflow UI | Pack template editor section: material + qty per pack (add/remove rows); save via `sync_for_workflow` pattern (like material goals) |
| Reserve | On `SOM_Pack::bind_on_create` / repair: idempotent `pack_reserve` stock decrement + budget fund |
| Materials used | Extend `SOM_Material_Stock::get_usage_by_material` (or successor) to include pack reserves; **Source** column on order detail |
| Overuse | Reuse `apply_overuse` / `order_usage_extra` / `fund_usage_extras` for pack lines (increase-only) |
| Analytics COGS | Ensure pack reserves + extras included in order material COGS |
| REST / Abilities | Optional read of pack materials (mirror goals) — only if cheap; else defer |
| Tests | `tests/sprint-up7-s2-smoke.php` — bind reserves 1× thank-you; qty 2 order still 1 card; overuse raises Actual |

**Done when:**

- Pack template can define thank-you (etc.) qty **per pack**.  
- New non-Internal orders reserve those materials once.  
- Materials used shows Pack source + allows Actual ↑.  
- Smoke passes.

---

### UP7-S3 — Shipping package materials + seed / docs / polish

- **Covers:** `03` package half; `02` §B; O2/O4/O9  
- **Schema:** `shipping_package_materials`; reason `package_reserve`  

| Work | Detail |
|---|---|
| Schema | `wp_som_shipping_package_materials` |
| Package admin UI | Materials rows on package edit |
| Reserve on select | When order `shipping_package_id` set/changed: reserve / reverse+reserve (O2) |
| Materials used | Package source rows |
| Seed | Thank-you cardstock material on Pack template; sample envelope material on default/seed package |
| Product Costing | Optional packaging cost line if O4 kept |
| Docs | USER-GUIDE / USER-REFERENCE / FEATURES / UAT — pack vs recipe materials; Materials used sources |
| BUGS / Progress | Close BUG-003 if not closed in S1; `Update-7-Sprint-Progress.md` |
| Tests | `tests/sprint-up7-s3-smoke.php` — package select reserves; change package reverses |

**Done when:**

- Selecting a shipping package reserves its materials.  
- Seed demo shows pack + package materials path.  
- Operator docs explain recipe vs pack vs package.  
- Smoke passes.

---

## 5. Suggested SemVer (alpha 0.x)

| After sprint | Plugin (approx) | Schema |
|---|---|---|
| UP7-S1 | 0.33.0 | unchanged |
| UP7-S2 | 0.34.0 | 1.17.0 (confirm at kickoff) |
| UP7-S3 | 0.35.0 | 1.18.0 |

Exact bumps follow `RELEASE.md` when cutting tags.

---

## 6. Out of scope (this package)

- Decrease below planned usage  
- Multi-carton / split shipments  
- Thank-you PDF generation as a stock gate  
- Moving product make materials onto workflows  
- Redesigning Material cost goals (workflow £ ceilings)  
- Auto-rewriting existing product recipes that mistakenly include thank-you × unit  

---

## 7. Progress tracking

After each implemented sprint, record verification in **`Update-7-Sprint-Progress.md`** (create on first implementation sprint).

---

## 8. Explicit scope of this document

This file is the Package 7 **sprint plan** only. It does not implement features. Implementation starts when you explicitly ask to implement **UP7-S1** (or a later sprint). Soft defaults above apply unless overturned before that sprint.

---

## 9. Sprint checklist summary (quick list)

### UP7-S1 — Bug / UX
- [ ] Verify Create PDF gates (BUG-003)
- [ ] Fix engine if gated step still auto-dones
- [ ] Workflow editor zero-gate warning
- [ ] Default manual confirm on new step cards
- [ ] Nest checklist under current step (+ step name in heading)
- [ ] Remove Make panel UP6-S2 placeholder
- [ ] Docs + smoke

### UP7-S2 — Pack materials
- [ ] Table `workflow_pack_materials` + CRUD/UI on Pack templates
- [ ] Reserve on pack bind (`pack_reserve`), idempotent
- [ ] Materials used: Source=Pack, planned/actual/overuse
- [ ] COGS + budget funding
- [ ] Smoke (1 card per pack even if qty>1)

### UP7-S3 — Package materials + polish
- [ ] Table `shipping_package_materials` + package UI
- [ ] Reserve/reverse on package select change
- [ ] Seed thank-you + envelope examples
- [ ] Optional Product Costing packaging line
- [ ] Operator docs + UAT + smoke
