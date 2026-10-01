# Update Package 7 — Sprint Plan

*Planning only — no plugin code in this pass. Specs: `01`–`05` in this folder. Baseline: plugin **v0.32.2**.*

**Sequencing:** Make gate UX → Package materials + Materials used → Order package suggestion (1 vs 2+) + seed/docs.

## Locked decisions (operator)

- Packaging materials live on the **shipping package** (like product recipes on products).  
- **Workflows = steps only** — no material BOM on Pack/Make templates.  
- Package depends on the **order**: **1 pack** → one package; **2+ packs** → a different package.  
- On the order, report actual materials used **the same way as product Materials used**.  
- BUG-003 (Create PDF Done + checklist placement) fixed in S1.

---

## 1. Consolidated open items

| # | Source | Item | Blocks | Status / decision |
|---|---|---|---|---|
| O1 | `03` §8.1 | Multi package suggestion storage | UP7-S3 | **Default:** product `package_id` = qty 1; add `package_id_multi` for qty ≥ 2 |
| O2 | `03` §8.2 | Multi rule: sum sellable qty vs primary line | UP7-S3 | **Default:** **sum of sellable line quantities** |
| O3 | `03` §8.3 | When to reserve package materials | UP7-S2 | **Default:** when `shipping_package_id` is set/changed |
| O4 | `02` §F3 | Package change | UP7-S2 | **Default:** reverse prior `package_reserve`, reserve new |
| O5 | `03` §8.5 | Materials used rows by source | UP7-S2 | **Default:** separate Recipe / Package rows |
| O6 | `03` §8.4 | Product Costing packaging line | UP7-S3 | **Default:** optional / defer |
| O7 | `04` | Hard-block ungated first make step? | UP7-S1 | **Default:** **warn only** |
| O8 | `04` | Create PDF: misconfig vs engine bug | UP7-S1 | **Verify first** |
| O9 | Seed | Small + large packages with materials | UP7-S3 | **Default:** yes |

---

## 2. Clarifying questions (defaults)

1. **Materials on shipping package only (not workflow)?** — **locked yes**.  
2. **`package_id_multi` on product?** — default yes (O1).  
3. **Multi = total sellable qty ≥ 2?** — default yes (O2).  
4. **Warn (not block) zero-gate steps?** — default yes (O7).

---

## 3. Spec ↔ codebase

| Area | Today | Package 7 |
|---|---|---|
| Make BOM | Product recipe | Unchanged |
| Pack materials | None | **Shipping package** material recipe |
| Workflows | Steps (+ cost goals on make) | Steps only for materials (cost goals unchanged if present) |
| Package select | Product suggestion + Pack panel | Suggestion from **1 vs 2+** packs; materials follow selection |
| Materials used | Recipe only | Recipe + Package |
| Zero-gate / checklist | BUG-003 | S1 fix |

---

## 4. Sprints

### UP7-S1 — Make step gate & confirmation UX (BUG-003)

- **Covers:** `04-Update-Make-Step-Gate-UX.md`  
- **Schema:** none  

| Work | Detail |
|---|---|
| Reproduce | Confirm Create PDF vs Print gates in the operator template |
| Engine | Fix `enter_item_step` only if a gated step still auto-dones |
| Workflow editor | Warn on zero-gate steps; default manual confirm on new step cards |
| Order detail | Nest checklist under current step; heading includes step name |
| Copy | Remove Make panel “Pack & Ship controls arrive in UP6-S2” |
| Docs / tests | Zero-gate note; `tests/sprint-up7-s1-smoke.php`; close BUG-003 |

**Done when:** gated first steps stay In progress; checklist is clearly for the current step; stale copy gone; smoke passes.

---

### UP7-S2 — Package materials + Materials used (mirror product recipe)

- **Covers:** `03` materials/reserve/usage; `02` §A + §C  
- **Schema:** `shipping_package_materials`; reason `package_reserve`  

| Work | Detail |
|---|---|
| Schema | `wp_som_shipping_package_materials` via `dbDelta` |
| Package UI | Recipe-like rows on shipping package edit (same pattern as product recipe) |
| Reserve | On set/change of order `shipping_package_id`: idempotent `package_reserve` + budget fund; reverse on change (O4) |
| Materials used | Extend usage panel: Source Recipe / Package; planned/actual/overuse like today |
| COGS | Include `package_reserve` + extras in order material COGS |
| Tests | `tests/sprint-up7-s2-smoke.php` — package with thank-you+envelope; select package → planned; raise Actual |

**Done when:**

- You define materials on a package like on a product.  
- Selecting that package on an order reserves those qtys once.  
- Materials used lets you report actuals the same way as recipe materials.  
- Smoke passes.

**Not in S2:** auto 1-vs-2+ suggestion (that’s S3). S2 works with today’s manual/default package selection.

---

### UP7-S3 — Order-based package suggestion (1 vs 2+) + seed/docs

- **Covers:** `03` §4 selection; O1/O2/O9  
- **Schema:** optional `products.package_id_multi`  

| Work | Detail |
|---|---|
| Product fields | `package_id` = single-pack default; `package_id_multi` when total sellable qty ≥ 2 |
| Suggest on create/bind | Pre-fill `shipping_package_id` from rule; operator can override |
| Seed | Small + large packages with materials; wire sample product defaults |
| Docs | USER-GUIDE / REFERENCE / FEATURES — package BOM vs product recipe; 1 vs 2+ |
| Optional | Product Costing packaging line (O6) |
| Tests | `tests/sprint-up7-s3-smoke.php` — qty 1 → small; qty 2 → large |

**Done when:** one 4-pack order suggests package A; two packs suggest package B; materials still follow whatever package is selected; docs + smoke pass.

---

## 5. Suggested SemVer (alpha 0.x)

| After sprint | Plugin (approx) | Schema |
|---|---|---|
| UP7-S1 | 0.33.0 | unchanged |
| UP7-S2 | 0.34.0 | 1.17.0 |
| UP7-S3 | 0.35.0 | 1.18.0 if `package_id_multi` |

---

## 6. Out of scope

- Materials on Pack workflow templates  
- Thank-you on product recipes as the packaging solution  
- Decrease below planned  
- Multi-carton / split shipments  

---

## 7. Progress

Record verification in **`Update-7-Sprint-Progress.md`** when implementation starts.

---

## 8. Scope of this document

Planning only. Implement when you ask for **UP7-S1** (or later). Soft defaults apply unless overturned.

---

## 9. Quick checklist

### UP7-S1
- [ ] BUG-003: nest checklist; zero-gate warning; remove UP6-S2 copy; smoke

### UP7-S2
- [ ] Package material recipe UI + table
- [ ] Reserve/reverse on package select
- [ ] Materials used (Package source) like product overuse
- [ ] Smoke

### UP7-S3
- [ ] 1 pack vs 2+ package suggestion
- [ ] Seed small/large packages + docs
- [ ] Smoke
