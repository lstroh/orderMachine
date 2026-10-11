# Update Package 8 — Sprint Plan

*Planning only. Specs: `01`–`04` in this folder. Baseline: **v0.32.2** (+ Package 7 as applicable).*

**Sequencing:** Order-level assign/release → require-when-any + first-step + order-complete release + timer release + boards → docs.

## Locked decisions (operator)

- Trays assigned to the **order**; shared by **all** workflows on that order.  
- Assign at **first step** **or** on **order detail**.  
- **One or more** free trays; site pool **1 … max** (default 10).  
- Required if **any** involved template has `uses_trays`.  
- Auto-release on **order complete**; **manual** release anytime.  
- **No** make→pack transfer.  
- **Release timer** always; log who/when (+ order note).

---

## 1. Consolidated open items

| # | Item | Blocks | Status / decision |
|---|---|---|---|
| O1 | Block Mark done until ≥1 tray when required | UP8-S2 | **Default: yes** |
| O2 | Must keep ≥1 tray while required + open | UP8-S2 | **Default: yes** |
| O3 | Trays overview page | UP8-S3 | **Default: no** |
| O4 | Max range 1–20 | UP8-S1 | **Default: yes** |
| O5 | Lowering max while high trays held | UP8-S1 | **Default: block** |
| O6 | Timer early release always + audit | UP8-S2 | **Settled: yes** |
| O7 | Order note on timer early release | UP8-S2 | **Default: yes** |

---

## 2. Spec ↔ codebase

| Area | Today | Package 8 |
|---|---|---|
| Trays | None | Order-level pool assignments |
| `uses_trays` | — | Template flag; order requires if **any** match |
| Timer | Unlock when elapsed | + early **Release timer** + audit |
| Boards | Step badges | + tray numbers |

---

## 3. Sprints

### UP8-S1 — Schema, site max, order-level assign/release

| Work | Detail |
|---|---|
| Settings | Max trays (default 10; O4/O5) |
| Template UI | Uses trays checkbox |
| Model | `SOM_Trays` — free/held, assign many to `order_id`, release, conflicts |
| Order detail | Order-level trays panel |
| Tests | `tests/sprint-up8-s1-smoke.php` |

**Done when:** cannot double-book; order can hold multiple trays; manual release works; smoke passes.

---

### UP8-S2 — Require-when-any, first-step prompt, order-complete release, timer release, boards

| Work | Detail |
|---|---|
| Required? | Any line make template or pack template `uses_trays` |
| First-step / advance | If required and none held → assign UI; block advance (O1) |
| Auto release | On order complete → release all held trays |
| Release timer | Always on `waiting_timer`; audit + order note (O6/O7) |
| Boards | Tray badges on Make + Pack cards |
| Tests | `tests/sprint-up8-s2-smoke.php` |

**Done when:** required orders get trays; complete frees them; timer early release logged; smoke passes.

---

### UP8-S3 — Docs / polish

| Work | Detail |
|---|---|
| Docs | USER-GUIDE / REFERENCE / FEATURES |
| Seed | Leave `uses_trays` **off** on seed templates (operator opts in) |
| Progress | `Update-8-Sprint-Progress.md` |

---

## 4. Suggested SemVer (alpha 0.x)

| After | Plugin (approx) |
|---|---|
| UP8-S1 | 0.36.0* |
| UP8-S2 | 0.37.0* |
| UP8-S3 | 0.38.0* |

\*After Package 7 bumps if those shipped first.

---

## 5. Out of scope

- Per-line trays / make→pack transfer  
- Barcode / auto-assign  
- Trays overview page (unless O3 overturned)  

---

## 6. Related follow-on

**Copy command** step (replace script/api/n8n) is Package 9 — [`../wordpress v10/Update-9-Sprint-Plan.md`](../wordpress%20v10/Update-9-Sprint-Plan.md).

---

## 7. Quick checklist

### UP8-S1
- [ ] Site max + template `uses_trays`
- [ ] Order-level assign multiple free trays; reject in-use
- [ ] Manual release
- [ ] Smoke

### UP8-S2
- [ ] Required when any template uses trays
- [ ] First-step / order UI; block until assigned
- [ ] Auto-release on order complete
- [ ] Release timer + audit
- [ ] Board badges
- [ ] Smoke

### UP8-S3
- [ ] Docs
- [ ] Smoke / UAT notes
