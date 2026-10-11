# Plugin Update Package 8 — Overview

*Update set · Physical **tray** assignment at **order** level (shared by all make/pack workflows on that order), plus **manual unblock** for any blocked workflow step. Self-contained.*

---

## Assumption

- Per-line **make** + order-level **Pack** (Package 6).
- Operators need numbered physical trays; a tray must not be double-booked across orders.
- Baseline: plugin **v0.32.2** (+ Package 7 when this starts).

## Settled product decisions (operator)

| Topic | Decision |
|---|---|
| What holds trays | The **order** (not a single make line / not transferred between workflows) |
| Scope on that order | Trays apply to **all** make lines + pack on the order |
| When to assign | At **first workflow step** start **or** anytime from **order detail** |
| How many | **One or more** free trays |
| Pool | Site max (default **10**); numbers **1 … max** |
| In use | Cannot assign a tray already held by another order |
| When required | If **any** make or pack template on the order has **`uses_trays`** |
| Auto release | When the **order is complete** (pack/ship done; Internal Produce N when make completes) |
| Manual release | Yes, anytime while held |
| Make → Pack transfer | **Not needed** — same order-level assignment |
| Manual step unblock | **Always**; clears **any** gate blocking Mark done (timer, checklist, script, batch, ship/package/hold, trays); **Mark done still required**; log who/when |

## What's in this update

1. Tray pool & settings  
2. Workflow template `uses_trays` (signals that orders using this template need trays)  
3. Order-level assign / release UI + first-step prompt when required  
4. **Unblock step** for all gates + audit (`05-Update-Manual-Step-Unblock.md`)  
5. Data model  

## Recommended build order

1. **UP8-S1** — Schema, site max, order-level assign/release  
2. **UP8-S2** — Require-when-any-template; first-step prompt; auto-release on order complete; **Unblock step** (all gates); board badges  
3. **UP8-S3** — Docs / polish  

## Out of scope

- Per-line tray sets / make→pack transfer  
- Barcode scanning  
- Auto-pick tray without UI  
- Auto Mark done on unblock  

## Files

1. `01-Update-Overview.md` (this file)  
2. `02-Update-Data-Model.md`  
3. `03-Update-Trays.md`  
4. `05-Update-Manual-Step-Unblock.md`  
5. `Update-8-Sprint-Plan.md`  
6. `04-Update-Cursor-Prompt.md`  
