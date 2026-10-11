# Update Package 11 — Sprint Plan

*Planning only. Specs in this folder. Baseline: **v0.32.2**.*

**Sequencing:** Schema + complete-rule change + bind/start → board/UI/seed/docs.

## Locked decisions (operator)

- Post-ship kind; **same step types** as make/pack.  
- Start **auto and/or manual**.  
- Site **default** + **per-product** override.  
- Order complete when **shipping details entered** + **package arrived** confirmed (not at Pack Ship alone).  
- Separate **Post-ship board**.  
- Review reminder on post-ship.  
- No trays / package materials.

---

## 1. Open items

| # | Item | Blocks | Decision |
|---|---|---|---|
| O1 | `is_complete` at arrival vs end of template | UP11-S1 | **Default: at arrival; review may continue** |
| O2 | Auto-start default on | UP11-S1 | **Default: yes** |
| O3 | `current_step_id` reuse after pack | UP11-S1 | **Default: reuse** |
| O4 | Mixed products → primary product’s post_ship template | UP11-S1 | **Default: yes** |
| O5 | Board filter by template | UP11-S2 | **Default: yes** |

---

## 2. Spec ↔ codebase

| Area | Today | Package 11 |
|---|---|---|
| Template kinds | make, pack | + `post_ship` |
| Order complete | Pack finish | Arrival confirm (+ shipment exists) |
| Review reminder | Often on Pack seed | Post-ship seed |
| Boards | Make, Pack | + Post-ship |

---

## 3. Sprints

### UP11-S1 — Kind, bind/start, complete rules

| Work | Detail |
|---|---|
| Schema | kind enum; order post_ship bind fields; product override; arrival columns; site option |
| Engine | Bind/start auto on Pack Ship done; manual start; progress rows; **stop** setting `is_complete` on pack finish for customer orders |
| Arrival | Confirmation kind or action sets `arrival_confirmed_*`; drives `is_complete` (O1) |
| Settings / product | Default template, auto-start, per-product override |
| Tests | `tests/sprint-up11-s1-smoke.php` |

**Done when:** Ship does not complete order; post-ship starts; arrival confirm completes order; smoke passes.

---

### UP11-S2 — Board, UI, seed, docs

| Work | Detail |
|---|---|
| Post-ship board | Menu + Kanban by step (O5) |
| Order detail | Post-ship panel |
| Seed | Aftercare template; strip review from Pack; migrate docs |
| Docs | USER-GUIDE / REFERENCE / FEATURES |
| Tests | `tests/sprint-up11-s2-smoke.php` |

**Done when:** board usable; seed shape correct; docs updated; smoke passes.

---

## 4. Suggested SemVer

| After | Plugin (approx) |
|---|---|
| UP11-S1 | 0.45.0* |
| UP11-S2 | 0.46.0* |

\*After Packages 7–10 bumps.

---

## 5. Out of scope

- Marketplace send APIs  
- Trays on post-ship  
- Multi-carton aftercare  

---

## 6. Quick checklist

### UP11-S1
- [ ] kind post_ship + bind/auto/manual start
- [ ] Complete rule = shipment + arrival
- [ ] Product override + site default
- [ ] Smoke

### UP11-S2
- [ ] Post-ship board + order panel
- [ ] Seed / migrate review reminder
- [ ] Docs + smoke
