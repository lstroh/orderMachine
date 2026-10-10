# Update — Trays

*Package 8 · Schema in `02-Update-Data-Model.md`. Self-contained.*

---

## 1. What this adds

Operators assign **numbered physical trays** when a make or pack workflow starts. Trays are a **site pool**; a tray in use cannot be assigned again until released.

## 2. Settled rules

| Topic | Decision |
|---|---|
| Scope | Workflow **template** opt-in (`uses_trays`) |
| Make | Yes — per **order line** when first make step starts |
| Pack | Yes — per **order** when first pack step starts |
| Assign timing | Start of **first step** of that workflow |
| Count | **Several** trays allowed per assignment |
| Pool | Site max (default **10**); numbers **1 … max** |
| Conflict | Reject assign if tray already **held** |
| Auto release | Workflow **complete** (make-complete / pack complete) |
| Manual release | Yes, anytime while held |
| Transfer | Option to **transfer** make trays → pack instead of releasing at make-complete |

## 3. Behaviour

### Settings

- **Max trays** (site): integer; default 10.  
- Changing max only affects which numbers are choosable for **new** assigns.

### Workflow template editor

- Checkbox: **Uses trays**.  
- Help text: “When the first step starts, assign one or more free tray numbers. Released when this workflow completes, or manually. Make can transfer trays to Pack.”

### Assign (first step)

When entering/showing the first step of a `uses_trays` template:

1. Show tray picker: multi-select or checkboxes for free trays (1…max, excluding held).  
2. Require **≥ 1** tray before the step can proceed as normal (Mark done still follows existing gates).  
   - **Soft default:** block Mark done / board advance on first step until ≥1 tray assigned; allow saving assignment without completing the step.  
3. Persist held assignments for that make line or pack order.

### In use

- Any tray with `status=held` is unavailable everywhere.  
- UI shows who holds it (order # / product line) when disabled.

### Release

| Trigger | Effect |
|---|---|
| Make line make-complete | Release that line’s make trays **unless** transfer-to-pack is chosen |
| Pack workflow complete | Release that order’s pack trays |
| Manual release | Operator frees selected/all trays for that holder |

### Transfer make → pack

At make-complete (or a clear control on order detail when make is done and pack is bound):

- **Transfer** (default offer when pack uses trays or pack is bound): same tray numbers become **pack** holdings for that order; make holdings close as `transferred`.  
- **Release**: free trays to the pool (current auto behaviour if transfer declined / pack does not use trays).

**Soft defaults**

- If Pack template `uses_trays` and pack is bound → prompt **Transfer** vs **Release**.  
- If Pack does not use trays → auto **Release** on make-complete.  
- If pack not bound yet → hold make trays until pack bind + transfer, or allow manual release (open item).

### Surfaces

| UI | Purpose |
|---|---|
| Settings | Max trays |
| Workflow editor | Uses trays |
| Order detail (Make line / Pack) | Assign, list held trays, manual release, transfer |
| Make board / Pack board | Tray number badge(s) on cards |
| (Optional) Trays overview | Which trays are free/held — open item |

## 4. Interaction with existing features

| Feature | Interaction |
|---|---|
| Product recipe / package materials | Unrelated — trays are location/resource, not stock |
| Batches | Trays stay on the order/line; batching does not steal trays |
| Multiple make lines | Each line has its own tray set; no sharing the same tray number |
| Ship-together | Pack trays are order-level once (after transfer or pack assign) |

## 5. Related: manual timer release (same package)

Not a tray feature, but the same “manual release” operator need for **timer** steps:

| Topic | Decision |
|---|---|
| Availability | **Always** on every timer step (make + pack) — no per-step opt-in |
| Effect | Unlock `waiting_timer` as if countdown ended → Timer ready; **Mark done** still required |
| Audit | Log **who** and **when** on the progress row; also write a short **order note** (soft default) |
| UI | Order detail (and board if cheap): **Release timer** while waiting |

See `02-Update-Data-Model.md` §E and sprint **UP8-S2**.

## 6. Out of scope

- Scanning trays  
- Tray ↔ material stock link  
- Auto-pick next free tray without UI (unless added later as shortcut)  
- Auto-completing the step on timer release (Mark done stays explicit)  

## 7. Open items

1. Must first step block until trays assigned, or warn only? **Default: block Mark done / advance.**  
2. Pack not bound at make-complete: keep held + “transfer when pack binds” vs force release. **Default: keep held; offer transfer when pack binds.**  
3. Trays overview admin page in v1? **Default: no — order detail + board badges enough.**  
4. Max range 1–10 hard vs 1–20 soft. **Default: 1–20, docs emphasise 10.**  
5. Transfer UI: modal at make-complete vs checkbox “Transfer trays to Pack” before Mark done on last make step. **Default: checkbox on last make step / make-complete action.**  
