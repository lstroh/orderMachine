# Plugin Update Package 8 — Overview

*Update set · Physical **tray** assignment at **order** level (shared by all make/pack workflows on that order), plus manual timer release. Self-contained.*

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
| Timer early release | **Always** on timer steps; log who/when (+ order note); Mark done still required |

## What's in this update

1. Tray pool & settings  
2. Workflow template `uses_trays` (signals that orders using this template need trays)  
3. Order-level assign / release UI + first-step prompt when required  
4. Manual timer release + audit  
5. Data model  

## Recommended build order

1. **UP8-S1** — Schema, site max, order-level assign/release  
2. **UP8-S2** — Require-when-any-template; first-step prompt; auto-release on order complete; timer release; board badges  
3. **UP8-S3** — Docs / polish  

## Out of scope

- Per-line tray sets / make→pack transfer  
- Barcode scanning  
- Auto-pick tray without UI  

## Files

1. `01-Update-Overview.md` (this file)  
2. `02-Update-Data-Model.md`  
3. `03-Update-Trays.md`  
4. `Update-8-Sprint-Plan.md`  
5. `04-Update-Cursor-Prompt.md`  
