# Plugin Update Package 11 — Overview

*Update set · **Post-ship** workflow (third kind alongside Make and Pack) for aftercare after the parcel leaves: buyer update, shipping details, arrival confirm, review reminder. Same step types as make/pack. Self-contained.*

---

## Assumption

- Make (per line) + Pack (order-level) exist (Package 6).
- Pack **Ship** today records shipment and (today) often drives order complete — **this package moves “order complete” to post-ship arrival confirm**.
- Baseline: **v0.32.2** (+ Packages 7–10 when started).

## Settled product decisions (operator)

| Topic | Decision |
|---|---|
| Kind | New workflow template kind **`post_ship`** (label: Post-ship) |
| Step types | **Same** as make/pack (manual, timer, confirmation, copy-command, tasks, instructions, unblock, timing) |
| Start | **Automatic** when Pack Ship completes **or** **manual** start/bind |
| Template selection | **Site default** + optional **per-product** override (resolve like: product override on primary sellable → else site default) |
| Order complete | **Not** at Pack Ship. Complete when **shipping details are entered** and operator **confirms package arrived** (post-ship) |
| Board | Separate **Post-ship board** |
| Review reminder | Lives on **Post-ship** (remove from Pack seed happy path) |
| Trays / package materials | **Not used** on post-ship |

## What's in this update

1. `workflow_templates.kind` includes `post_ship`.  
2. Bind/start post-ship (auto + manual); progress storage (order-level, like Pack).  
3. Order `is_complete` gated on post-ship arrival confirm (+ shipping details).  
4. Post-ship board + order detail panel.  
5. Seed/migrate: Review reminder → post-ship template.  

## Recommended build order

1. **UP11-S1** — Schema, kind, bind/start, progress, complete rules  
2. **UP11-S2** — Board + order UI + seed/docs  

## Out of scope

- Trays / package BOM on post-ship  
- Marketplace auto-message APIs (use copy-command / manual / future)  
- Multi-parcel post-ship trees  

## Files

1. `01-Update-Overview.md` (this file)  
2. `02-Update-Data-Model.md`  
3. `03-Update-Post-Ship-Workflow.md`  
4. `Update-11-Sprint-Plan.md`  
5. `04-Update-Cursor-Prompt.md`  
