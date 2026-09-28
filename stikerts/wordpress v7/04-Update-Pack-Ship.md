# Update — Order Pack & Ship

*Package 6 · Schema in `02-Update-Data-Model.md` §B–C. Self-contained.*

---

## 1. What this adds

An **order-level Pack & Ship** lane separate from product make:

1. Site **Pack workflow template** (kind `pack`) bound on each sellable/channel order.
2. **Pack board** (order Kanban through pack steps).
3. Pack checklist: **every line in this pack + thank-you included**.
4. Address confirm, **required package** selection, Ship (existing shipment record), optional review reminder.
5. Hold/exception (**blocks Ship**), browser-print pack list, buyer/channel note + order notes, packed-by auto-stamp, optional shipment pack weight.
6. **Remove thank-you batch** from this path (per-order thank-you tick instead).

## 2. Settled rules

| Topic | Decision |
|---|---|
| Ship timing | **Ship together** — Ship enabled only when all **sellable** lines are make-complete (internal lines always ready) |
| Thank-you | In pack checklist; every pack (v1 ⇒ once) |
| Package | Required before Ship; product `package_id` is suggestion only |
| Hold | Blocks Ship; order can remain visible |
| Pack list | Browser print |
| Packed-by | Auto-stamp WP user + time |
| Buyer note | Show read-only on Pack |
| Pack weight | Optional on shipment |
| Thank-you batch | Removed from pack path |
| Review | May exist on pack template after Ship (and/or on make templates) |

## 3. Behaviour

### Bind Pack workflow

On create of non-`internal` orders: set `pack_workflow_template_id` from option `som_pack_workflow_template_id`. Create order-level pack step progress rows. If no Pack template configured, flag in UI — **open item** (recommend block Ship until configured).

Internal channel orders: no pack binding.

### Readiness for Ship

All must be true:

1. Every sellable matched line is make-complete (internal lines OK).  
2. Pack hold inactive (`pack_hold_reason` empty).  
3. Pack checklist complete (all lines in pack + thank-you).  
4. `shipping_package_id` set.  
5. Address confirmation complete (existing kind on pack step).  
6. Shipment row exists when advancing past Ship (existing rule).  
7. Current pack step gates (manual/timer/script) satisfied.

### Pack checklist

| Tick | Notes |
|---|---|
| One per order line | Label with product name, qty, personalisation snippet; include internal lines (stock items to put in the box) |
| Thank-you included | Required |

Saving complete checklist → auto-set `packed_by_user_id` + `packed_at` (stamp on first completion; **open item** if later edits refresh timestamp).

### Package selection

- Dropdown of **active** shipping packages (Package 5).  
- Pre-select suggestion: primary sellable line’s `package_id`, else site default package, else empty.  
- Required before Ship.

### Hold / exception

- Fields: reason (required to hold), held_at, held_by.  
- UI: “Hold pack” / “Clear hold”.  
- While held: **Ship** (and pack advance into Ship) blocked; order still listed (e.g. Held column or badge).

### Pack list (browser print)

Printable view/section including:

- Order id, channel, buyer, ship-to address  
- Line table (SKU/name, qty, personalisation)  
- Thank-you required  
- Suggested/selected package  
- Buyer/channel note if present  
- Checklist tickboxes for paper use (optional; screen checklist remains source of truth)

Use `window.print` CSS; no PDF library in v1.

### Pack detail surfaces

| Data | Source |
|---|---|
| Buyer / channel note | Order raw payload / buyer fields already stored — surface read-only |
| Order notes | Existing threaded notes (P5) |
| Planned vs actual postage | Existing P5 panel |
| Personalisation | Per line |

### Pack board

| Behaviour | Notes |
|---|---|
| Cards | One per **order** in the pack lane (not Internal channel) |
| Columns | Pack template steps (+ optional Held / Waiting for make) |
| Waiting for make | Orders where some sellable lines are not make-complete — visible but Ship blocked |
| Ready to pack | All sellable lines ready; checklist not done |
| Drag | Same gated rules as today’s board, pack-step scoped |

### Thank-you batch removal

- Seed Pack template: **manual confirm** “Thank-you included” (checklist), **not** `batch_group_id` / local thank-you script.  
- Existing `thank_you_card` batch group may remain in DB; convert/migrate open thank-you batch members — **open item** (recommend: document manual; stop auto-convert of thank-you steps on activate for pack template).  
- Optional: keep local thank-you **script** as a non-batch pack step later — **out of scope** unless trivial; v1 is checklist only.

### Shipment pack weight

Optional `pack_weight_grams` on shipment create/edit. Display alongside postage; no rate API.

## 4. UI requirements

| Page | Purpose |
|---|---|
| Pack board | Order Kanban |
| Order detail — Pack panel | Checklist, package, hold, print, link to shipment, notes, buyer note |
| Settings (or Workflows) | Choose default Pack template (`som_pack_workflow_template_id`) |
| Workflow editor | Author Pack template steps; `kind=pack` |
| Shipment form | Optional pack weight |

## 5. Out of scope

- Split ship / multi-parcel  
- Scan verification  
- Carrier purchase  
- Care-card matrix  
- Regenerating 4-up thank-you PDFs via batch  

## 6. Open items

1. Column set for “Waiting for make” vs first pack step.  
2. Refresh `packed_at` on checklist re-save?  
3. Migrate path for open orders stuck in old thank-you batch.  
4. If Pack template missing on create — hard fail vs soft flag.  
5. Whether address confirm stays a separate step or merges into pack checklist (recommend keep separate step for clarity).
