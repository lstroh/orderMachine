# Update — Trays (order-level)

*Package 8 · Schema in `02-Update-Data-Model.md`. Self-contained.*

---

## 1. What this adds

Operators assign **numbered physical trays** to an **order**. Those trays stay with the order for **all** make lines and pack/ship on that order. A tray held by one order cannot be assigned to another until released.

## 2. Settled rules

| Topic | Decision |
|---|---|
| Holder | **Order** |
| Shared by | All make workflows on lines + Pack workflow on that order |
| Assign when | **First workflow step** starts **or** anytime on **order detail** |
| Count | **One or more** free trays |
| Pool | Site **1 … max** (default max 10) |
| Required when | **Any** make template on a sellable line **or** bound Pack template has `uses_trays` |
| In use | Reject if tray held by another order |
| Auto release | **Order complete** |
| Manual release | Yes |
| Transfer make→pack | **Removed** — not needed |

## 3. Behaviour

### Settings

- **Max trays** (site), default 10.

### Workflow template editor

- Checkbox **Uses trays** on make and pack templates.  
- Meaning: “Orders that use this template must have trays assigned.”

### When trays are required on an order

Compute once (and refresh when products/pack bind change):

- Any matched sellable line’s product make template has `uses_trays`, **or**  
- Bound pack template has `uses_trays`.

If required and no held trays yet → prompt/block as below.

### Assign

- **Order detail:** multi-select free trays; save assigns to this `order_id`.  
- **First step:** when the order first enters an active make or pack step and trays are required but none held → show assign UI; **block Mark done / advance** until ≥1 tray (soft default).  
- Can add/remove free trays later while order open (removing = release those numbers; adding = assign more free ones). **Soft default:** can release individual trays manually; must keep ≥1 while required and order not complete.

### Release

| Trigger | Effect |
|---|---|
| Order `is_complete` | Release all held trays for that order (`order_complete`) |
| Manual | Operator releases selected/all trays |

### Surfaces

| UI | Purpose |
|---|---|
| Settings | Max trays |
| Workflow editor | Uses trays |
| Order detail | Assign / list / manual release (order-level panel) |
| Make + Pack boards | Tray badge(s) on cards |

## 4. Interaction with existing features

| Feature | Interaction |
|---|---|
| Multiple make lines | Share the **same** order tray set |
| Pack | Same trays; no transfer step |
| Materials / packages | Unrelated |
| Internal Produce N | Release when that order completes (make-complete) |
| Batches | Unaffected |

## 5. Manual timer release (same package)

| Topic | Decision |
|---|---|
| Availability | **Always** on every timer step (make + pack) |
| Effect | Unlock early → Timer ready; **Mark done** still required |
| Audit | Progress columns who/when + order note |

## 6. Out of scope

- Per-line tray assignments  
- Make→pack transfer  
- Barcode scan  
- Auto-assign without picking  

## 7. Open items

1. Block advance until trays when required? **Default: yes.**  
2. After assign, allow releasing last tray while order still open and required? **Default: no** (keep ≥1).  
3. Trays overview page? **Default: no.**  
4. Max 1–20? **Default: yes.**  
