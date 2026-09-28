# Plugin Update Package 6 — Overview

*Update set · Separates **product make** workflows from **order pack & ship**. Self-contained — assumes the base plugin and Update Packages 1–5 (including internal products, shipments, shipping packages / planned postage, order notes, step instructions, confirmation checklists, batches) are already built and working.*

---

## Assumption

Everything built so far is in place and working, in particular:

- **One workflow per order** today via **primary product** (first matched `order_items` row) — this package **replaces** that model for make/pack.
- Orders Board is a single Kanban of order-level `current_step_id` / `order_step_progress`.
- Confirmation kinds include `packing_items` and `shipping_address`; Ship waits for a `som_shipments` row.
- Thank-you is often a **batch** step (`thank_you_card`); shipping-label batch exists but is opt-in.
- Shipping **packages** catalogue + product defaults + order planned postage exist (Package 5).
- Order **notes** exist (admin threaded log).
- Internal products / Produce N use the Internal channel and must **not** appear on the Pack board.

This package is a **behavioural + schema** update focused on make/pack separation. Do not rework unrelated costing, fees, or channel sync.

Current plugin baseline at planning time: **v0.29.1**, schema **`som_db_version` 1.14.0**.

## What's in this update

1. **Per-line make workflows** (`03-Update-Make-Workflows.md`) — each **sellable** order line runs its product’s make template; progress is per line. **Internal** product lines are always **pack-ready** (no make queue). **Make board** (line-oriented). Product make templates end at **ready to pack** (no Pack/Ship/Thank-you on the product template).
2. **Order Pack & Ship** (`04-Update-Pack-Ship.md`) — shared **Pack workflow** (site-level template); **Pack board**; checklist = **lines in this pack + thank-you**; address confirm; **package required** before Ship; hold/exception blocks Ship; printable pack list (browser print); buyer/channel note + order notes on Pack; packed-by auto-stamp; optional pack weight on shipment. **Ship together** (one outbound when all sellable lines are make-complete). **Remove thank-you batch** from the happy path.
3. **Seed & migration** (`05-Update-Seed-Migration.md`) — rewrite Bin Sticker seed: make ends after Cut; new Pack template owns pack/thank-you/address/package/Ship/review; document migrate for existing Local sites.

## How the features interact

| Dependency | Note |
|---|---|
| Make ↔ Pack | Sellable line make-complete → line is pack-ready. Pack UI may show mixed ready/not-ready lines; **Ship** waits until **all sellable** lines are ready (+ pack gates). |
| Internal lines | Always pack-ready; Produce N jobs stay on Make only (never Pack board). |
| Packages (P5) | Product `package_id` = **suggestion**; order must select package on Pack before Ship. |
| Shipments (existing) | Actual postage unchanged; optional **pack weight** field; Ship step still requires shipment record. |
| Notes (P5) | Surface on Pack detail (read); pack hold reason is separate from notes. |
| Batches | **Remove** thank-you batch from seed / pack path; shipping_label batch may remain unused by seed. |
| Review reminder | Allowed on **make** templates and/or **pack** template (seed: pack after Ship). |

**Recommended build order**

1. Schema + per-line make assignment/progress + Make board (stop assigning full product workflow as order-level pack steps)
2. Pack workflow engine + Pack board + checklist / hold / package / print / weight
3. Seed rewrite + migrate helper/docs + remove thank-you batch from pack path

## Full schema change list

Detailed specs: `02-Update-Data-Model.md`. Summary:

| Change | Feature |
|---|---|
| Per-line make progress (new table or extend progress) | Make |
| Order pack workflow binding + pack step progress | Pack |
| Order pack hold fields; packed_by / packed_at; selected `shipping_package_id` | Pack |
| Shipment optional `pack_weight_grams` | Pack |
| Seed: make template truncated; pack template created | Seed |

## Settled product decisions (from planning chat)

Do **not** re-litigate:

| Topic | Decision |
|---|---|
| Partials vs ship | Pack UI may reflect lines as they become ready; **Ship together** when all sellable lines are ready |
| Thank-you | **In pack**; on **every pack** (v1 ship-together ⇒ once per order pack) |
| Line make | **Yes** per sellable line; **internal lines always pack-ready** |
| Boards | **Make board** + separate **Pack board** |
| Product templates | Make ends at ready-to-pack; Pack/Ship/Thank-you/address move to Pack workflow |
| Pack checklist v1 | **Lines in this pack + thank-you** |
| Package | **Required** on Pack before Ship; product default is suggestion only |
| Internal / Produce N | **Make only** (never Pack board) |
| Thank-you batch | **Remove** from pack path (no 4-up batch gate) |
| Review reminder | Allowed on **both** make and pack workflows |
| Pack list | **Browser print** |
| Hold | **Blocks Ship** (order may stay visible on Pack board) |
| Packed-by | **Auto-stamp** current WP user + time (no initials UI) |
| Buyer/channel note | Show on Pack (read-only) |
| Pack weight | Optional on shipment |
| Seed | **Rewrite** Bin Sticker make + new Pack template; document existing-site migrate |

## Out of scope (this package)

- Barcode scan-to-verify  
- Multi-carton / split shipments  
- Live carrier rate APIs / auto-label purchase  
- Insert inventory SKUs / promo rule engines  
- Care-card / coupon matrix (thank-you only in v1)  
- Customer-facing production tracker  
- Changing Analytics / order_profit postage rules  

## Files in this package

1. `01-Update-Overview.md` — this file  
2. `02-Update-Data-Model.md` — schema delta  
3. `03-Update-Make-Workflows.md` — per-line make + Make board  
4. `04-Update-Pack-Ship.md` — Pack board, checklist, hold, package, print, ship gates  
5. `05-Update-Seed-Migration.md` — seed rewrite + Local migrate notes  
6. `06-Update-Cursor-Prompt.md` — kickoff (**planning → sprint plan**, then implement per sprint)  
7. `Update-6-Sprint-Plan.md` — created when you ask to **Run** the sprint plan (not in this design pass)
