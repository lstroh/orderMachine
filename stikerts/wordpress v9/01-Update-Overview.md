# Plugin Update Package 8 — Overview

*Update set · Physical **tray** assignment for make and pack workflows. Self-contained — assumes Update Packages 1–7 planning/behaviour (make/pack split, package materials plan) as context; implement after or alongside Package 7 as you prefer.*

---

## Assumption

- Per-line **make** workflows and order-level **Pack** workflow exist (Package 6).
- Operators need numbered physical trays on the bench; a tray must not be double-booked.
- Baseline plugin at planning: **v0.32.2** (+ whatever Package 7 has shipped when this starts).

## Settled product decisions (from operator)

| Topic | Decision |
|---|---|
| What uses trays | **Workflow template** opt-in (make **and** pack can enable) |
| When to assign | When **starting the first step** of that workflow |
| How many trays | **One or more** free trays per assignment |
| Tray numbers | **1 … max**; max is a **site** setting (default pool size **10**, configurable) |
| In use | Cannot assign a tray that is already held |
| Auto release | When that workflow **completes** (make-complete for make; pack/ship complete for pack) |
| Manual release | Always allowed |
| Make → Pack | **Option to transfer** tray assignment from make to pack/ship instead of releasing at make-complete |
| Timer early release | **Always** available on every timer step (make + pack); unlocks like countdown end; still requires Mark done; **log** who/when |

## What's in this update

1. **Tray pool & settings** — site max; tray numbers 1…max; occupancy.  
2. **Workflow template flag** — template uses trays (make/pack).  
3. **Assign / release / transfer UI** — first-step gate; order/Make/Pack surfaces; manual release.  
4. **Manual timer release** — early unlock on any `waiting_timer` step + audit log.  
5. **Data model** — tray assignments; timer early-release audit.

## Recommended build order

1. **UP8-S1** — Schema, site max, occupancy, assign/release APIs + order detail  
2. **UP8-S2** — First-step gate on make + pack; board badges; transfer make→pack  
3. **UP8-S3** — Seed/docs/smoke polish  

## Out of scope

- Auto-assign trays without operator choice  
- Trays on batch groups as a separate system  
- Barcode/NFC tray scanning (unless trivial later)  
- Changing Package 7 package-materials design  

## Files

1. `01-Update-Overview.md` (this file)  
2. `02-Update-Data-Model.md`  
3. `03-Update-Trays.md`  
4. `Update-8-Sprint-Plan.md`  
5. `04-Update-Cursor-Prompt.md`  
