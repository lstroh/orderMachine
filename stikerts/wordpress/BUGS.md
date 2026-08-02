# Order Machine — Bugs & UX issues (user testing)

*Logged during Local acceptance testing against plugin **0.18.0** / DB **1.5.0**.*  
*Companion: [`USER-ACCEPTANCE-TESTS.md`](USER-ACCEPTANCE-TESTS.md).*  
*Do not treat this file as a fix list yet — capture only. Fix when asked.*

---

## How to add an entry

| Field | Meaning |
|---|---|
| **ID** | `BUG-NNN` sequential |
| **Severity** | Blocker / Major / Minor / UX / Question |
| **Status** | Open / Won’t fix / Fixed |
| **Found in** | UAT section (e.g. §4.1) |
| **Env** | Local + dummy unless noted |

---

## Open

### BUG-001 — Orders status filter has no “current step” options (e.g. Print)

| | |
|---|---|
| **Severity** | UX |
| **Status** | Open |
| **Found in** | §4.1 Orders list filters |
| **Env** | Local, dummy credentials, fixture orders |
| **Date** | 2026-08-02 |

**Observed**

Status dropdown only offers order-lifecycle options:

- All statuses  
- Open  
- Complete  
- Needs mapping  
- Needs workflow  
- Cancelled  

There is no way to filter the list by **workflow step** (e.g. Print, Dry, Laminate, Ship, Thank-you).

**Expected (tester)**

Ability to filter to orders currently on a given production step (e.g. “show me everything on Print”).

**Notes (current behaviour — not fixing here)**

Today the status filter is intentionally **order status / mapping flags**, not workflow progress. Current step name can appear as a column/badge on the list for open assigned orders, but it is not a filter dimension.

**Possible later directions (ideas only)**

- Add a separate “Current step” filter (template-aware or global step name).  
- Or rename the existing control so it’s clearer it is not “production stage”.

---

### BUG-002 — “Add material” button does nothing

| | |
|---|---|
| **Severity** | Major |
| **Status** | Open |
| **Found in** | §8.1 / Materials list (around UAT materials CRUD) |
| **Env** | Local, dummy credentials |
| **Date** | 2026-08-02 |

**Observed**

On **Materials**, clicking **Add material** does not open the create form — page appears unchanged / nothing useful happens.

**Expected**

Open the new-material edit screen (`material_id=new`).

**Likely cause (investigation only — not fixed)**

`SOM_Materials::detail_url( 'new' )` casts the id with `(int)`, so `'new'` becomes `0`:

```php
// includes/class-som-materials.php — detail_url()
return self::list_url( array( 'material_id' => (int) $material_id ) );
```

Link becomes `…&material_id=0`. Renderer only opens the edit form for `material_id=new` or a positive numeric id, so `0` falls through to the list again.

Products / suppliers / listings / POs pass `'new'` through without casting — materials is the outlier.

**Workaround until fixed**

None clean in UI. Editing an existing material still works via its name link.

---

## Closed / not bugs

*(Move items here when confirmed by design or fixed.)*

---

## Notes from testing (not filed as bugs)

### Unmatched orders — when & how to resolve

**When it happens**

An order line stays **unmatched** when sync cannot link the channel listing to an internal product:

- New eBay/Etsy listing not yet mapped in **Listings** (`external_listing_id` ↔ `product_id`)
- Wrong / outdated listing ID or SKU on the map
- Fixture path: deliberate unmatched listing IDs so the UI can be tested

The line is still saved (`product_id = NULL`); the order is **not** dropped. UI shows Unmatched / Needs mapping.

**Effects**

- No recipe → no stock reservation for that line  
- If **no** line matches a product → no primary product → **no workflow** assigned  
- Mixed orders: matched lines can still drive workflow/stock; unmatched lines stay flagged

**How to solve (operator)**

1. **Order Machine → Listings** → Add / edit a listing map: channel + external listing ID (and SKU if used) → choose the correct **Product**.  
2. Future syncs for **new** orders with that listing will match.  
3. **Existing** order line items are **not** rewritten on re-sync (items are immutable after create) — so already-imported unmatched lines stay unmatched until/unless a future feature adds rematch. For testing, map first then Sync fresh orders; for live ops, map listings before they sell (or accept manual handling of that order).

Fixture unmatched rows are **expected** for UAT §5.2 — not a defect.

---

## Testing progress snapshot

| UAT range | Result |
|---|---|
| §0 – §4.1 | OK aside from BUG-001 |
| §4.2 – §8.1 | Mostly OK; BUG-002 blocks Add material (2026-08-02) |

---

*End of bugs log. Append new `BUG-NNN` entries above the Closed section as testing continues.*
