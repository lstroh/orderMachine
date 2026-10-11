# Update Package 10 — Sprint Plan

*Planning only. Specs in this folder. Baseline: **v0.32.2**.*

**Sequencing:** Planned fields + order detail actuals → Analytics.

## Locked decisions (operator)

- Store **actual + planned**.  
- **Wall clock** (includes timer waits).  
- Totals per **make line** and **pack**.  
- Show on **order detail** and **Analytics**.

---

## 1. Open items

| # | Item | Blocks | Decision |
|---|---|---|---|
| O1 | Persist `actual_seconds` | UP10-S1 | **Default: yes** |
| O2 | Workflow actual = span first→last | UP10-S1 | **Default: yes** |
| O3 | Live elapsed on current step | UP10-S1 | **Default: yes** |
| O4 | Analytics: table + one chart | UP10-S2 | **Default: yes** |

---

## 2. Spec ↔ codebase

| Area | Today | Package 10 |
|---|---|---|
| Step plan | None (timer ≠ plan) | `planned_seconds` on steps |
| Progress times | `started_at` / `completed_at` often unused in UI | Actuals + display |
| Order detail | No durations | Per-step + make/pack totals |
| Analytics | Profit/stock/fees | + timing views |

---

## 3. Sprints

### UP10-S1 — Planned + actual on order detail

| Work | Detail |
|---|---|
| Schema | `planned_seconds` on steps; `actual_seconds` on both progress tables |
| Editor | Planned duration control per step; show template sum |
| Engine | Set `actual_seconds` on complete; ensure `started_at`/`completed_at` always set |
| Order detail | Per-step planned/actual; make-line + pack totals; live elapsed (O3) |
| Tests | `tests/sprint-up10-s1-smoke.php` |

**Done when:** complete a make/pack path and see step + workflow times vs plan on order detail.

---

### UP10-S2 — Analytics + docs

| Work | Detail |
|---|---|
| Analytics | Timing section: avg actual vs plan by step; workflow totals table/chart (O4) |
| Filters | Date range + make/pack template |
| Docs | USER-GUIDE / REFERENCE — planned vs timer vs actual |
| Tests | `tests/sprint-up10-s2-smoke.php` |

**Done when:** Analytics shows timing; docs clear; smoke passes.

---

## 4. Suggested SemVer

| After | Plugin (approx) |
|---|---|
| UP10-S1 | 0.42.0* |
| UP10-S2 | 0.43.0* |

\*After Packages 7–9 bumps.

---

## 5. Out of scope

- Active-only time  
- Manual edit of actuals  
- SLA notifications  

---

## 6. Quick checklist

### UP10-S1
- [ ] planned_seconds + editor
- [ ] actual_seconds on complete
- [ ] Order detail step + make/pack totals
- [ ] Smoke

### UP10-S2
- [ ] Analytics timing
- [ ] Docs + smoke
