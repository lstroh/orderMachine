# Update Package 8 — Sprint Plan

*Planning only — no plugin code in this pass. Specs: `01`–`04` in this folder. Baseline: plugin **v0.32.2** (start after or in parallel with Package 7 as you choose).*

**Sequencing:** Schema + assign/release → first-step gates + transfer + boards → seed/docs.

## Locked decisions (operator)

- Trays belong to **workflow templates** that opt in (`uses_trays`) — make **and** pack.  
- Assign when **starting the first step**; **one or more** free trays.  
- Numbers **1 … site max** (default max **10**).  
- Cannot assign a tray that is **in use**.  
- Release on **workflow complete** (make or pack/ship) or **manual**.  
- **Transfer** make → pack/ship optional instead of releasing at make-complete.  
- **Manual timer release** always available on timer steps; unlocks early; Mark done still required; **log** who/when (+ order note).

---

## 1. Consolidated open items

| # | Source | Item | Blocks | Status / decision |
|---|---|---|---|---|
| O1 | `03` §7.1 | Block first step until ≥1 tray? | UP8-S2 | **Default:** **block** Mark done / advance |
| O2 | `03` §7.2 | Pack not bound at make-complete | UP8-S2 | **Default:** keep trays held; transfer when pack binds |
| O3 | `03` §7.3 | Trays overview page | UP8-S3 | **Default:** **no** in v1 |
| O4 | `02` §F1 | Max range 1–10 vs 1–20 | UP8-S1 | **Default:** allow **1–20**, docs say 10 |
| O5 | `03` §7.5 | Transfer UX control | UP8-S2 | **Default:** checkbox / choice at make-complete |
| O6 | `02` §F2 | Lowering max while high trays held | UP8-S1 | **Default:** **block** settings save until released |
| O7 | Timer | Per-step opt-in for early release? | UP8-S2 | **Settled:** **always** (no opt-in) |
| O8 | `02` §E | Also write order note on early timer release? | UP8-S2 | **Default:** **yes** |

---

## 2. Clarifying questions (defaults)

1. **Block first step until trays assigned?** — default yes (O1).  
2. **Keep trays if pack not bound yet?** — default yes (O2).  
3. **No separate Trays admin list in v1?** — default yes (O3).  
4. **Max up to 20?** — default yes (O4).  
5. **Timer early release always + audit log?** — **settled yes** (O7/O8).

Overturn before kickoff if needed.

---

## 3. Spec ↔ codebase

| Area | Today | Package 8 |
|---|---|---|
| Trays | None | Site pool + assignments |
| Make first step | Gates only | + tray assign when template `uses_trays` |
| Pack first step | Gates only | Same |
| Make-complete | Pack-ready | + release or transfer trays |
| Boards | Step badges | + tray number badge(s) |
| Timer steps | Unlock only when elapsed | + **Release timer** anytime; audit who/when |

---

## 4. Sprints

### UP8-S1 — Tray pool, schema, assign/release API + order detail

- **Covers:** `02`; `03` settings + assign/release (no first-step hard gate yet)  
- **Schema:** `uses_trays` on templates; `tray_assignments`; settings max  

| Work | Detail |
|---|---|
| Settings | Max trays (default 10; range per O4); block lower max if held trays above new max (O6) |
| Template UI | Uses trays checkbox on make/pack templates |
| Model | `SOM_Trays` — list free/held, assign many, release, conflict errors |
| Order detail | Assign trays / show held / manual release for make line + pack |
| Tests | `tests/sprint-up8-s1-smoke.php` — assign 1+3; reject in-use; manual release |

**Done when:** site max works; template flag saves; cannot double-book a tray; order detail can assign/release; smoke passes.

---

### UP8-S2 — First-step gate, tray release/transfer, manual timer release, board badges

- **Covers:** `03` assign timing, tray release, transfer; manual timer release (`02` §E)  
- **Schema:** progress columns `timer_released_early_at` / `timer_released_early_by` (if not done in S1)  

| Work | Detail |
|---|---|
| First-step gate | Make + pack: if `uses_trays`, block Mark done/advance until ≥1 tray (O1) |
| Auto release | Make-complete → release make trays **or** transfer path; pack-complete → release pack trays |
| Transfer | Choice at make-complete (O5); pack not bound → keep held (O2); on pack bind offer transfer |
| **Release timer** | On any make/pack `waiting_timer` current step: admin action unlocks early (same as elapsed unlock); does **not** Mark done |
| Timer audit | Set progress early-release columns; write order note (O8) |
| Boards | Make + Pack cards show tray numbers; optional Release timer on card if cheap |
| Tests | `tests/sprint-up8-s2-smoke.php` — gate; complete releases; transfer; early timer unlock + log |

**Done when:** first step requires trays; complete frees or transfers; timer can be released early with audit; boards show trays; smoke passes.

---

### UP8-S3 — Seed, docs, polish

- **Covers:** docs; optional seed `uses_trays` on Dry-related make template if useful  
- **Schema:** none  

| Work | Detail |
|---|---|
| Docs | USER-GUIDE / REFERENCE / FEATURES — trays, transfer, max setting |
| Seed | Soft default: leave `uses_trays` off on seed templates (operator opts in) **or** enable on Bin Sticker Make — **recommend off** |
| Progress | `Update-8-Sprint-Progress.md` |
| Tests | Light smoke / UAT notes |

**Done when:** docs explain the flow; operator can enable on real templates; smoke/docs done.

---

## 5. Suggested SemVer (alpha 0.x)

| After sprint | Plugin (approx) | Schema |
|---|---|---|
| UP8-S1 | 0.36.0* | +1 minor schema |
| UP8-S2 | 0.37.0* | unchanged |
| UP8-S3 | 0.38.0* | unchanged |

\*Adjust if Package 7 has already consumed 0.33–0.35.

---

## 6. Out of scope

- Barcode tray scan  
- Auto-assign without picking  
- Tray nicknames catalogue  
- Linking trays to material stock  

---

## 7. Progress

Record verification in **`Update-8-Sprint-Progress.md`** when implementation starts.

---

## 8. Scope of this document

Planning only. Implement when you ask for **UP8-S1** (after your testing / Package 7 kickoff as you prefer).

---

## 9. Quick checklist

### UP8-S1
- [ ] Site max trays + template `uses_trays`
- [ ] Assign multiple free trays; reject in-use
- [ ] Manual release on order detail
- [ ] Smoke

### UP8-S2
- [ ] First-step gate (make + pack)
- [ ] Auto release on workflow complete
- [ ] Transfer make → pack
- [ ] Release timer early (always) + who/when log (+ order note)
- [ ] Board badges
- [ ] Smoke

### UP8-S3
- [ ] Docs (+ optional seed)
- [ ] Smoke / UAT notes
