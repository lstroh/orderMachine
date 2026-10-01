# Order Machine — Bugs & UX issues (user testing)

*Logged during Local acceptance testing against plugin **0.18.0** / DB **1.5.0**.*  
*Companion: [`USER-ACCEPTANCE-TESTS.md`](USER-ACCEPTANCE-TESTS.md).*

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

### BUG-003 — Make step shows Done before work; checklist not tied to step

| | |
|---|---|
| **Severity** | Major / UX |
| **Status** | Open |
| **Found in** | Order detail → MAKE (post Package 6) |
| **Env** | Operator testing; screenshot 2026-10-01 |

**Symptoms**

- First make step (e.g. **Create PDF**) badge **Done** before any Mark done.  
- Confirmation checklist (“print matches the client request”) appears above the step list while a later step (**Print…**) is In progress with Mark done disabled.  
- Make panel still shows stale copy: “Pack & Ship controls arrive in UP6-S2.”

**Likely cause**

Zero-gate auto-complete on ungated steps (`enter_item_step`), plus checklist UI rendered above the list for the **current** step (not nested under it). Verify whether Create PDF actually had `requires_manual_confirm` / `confirmation_kind` set.

**Plan**

Update Package 7 / **UP7-S1** — see `stikerts/wordpress v8/04-Update-Make-Step-Gate-UX.md` and `Update-7-Sprint-Plan.md`.

---

## Closed / fixed

### BUG-001 — Orders status filter has no “current step” options (e.g. Print)

| | |
|---|---|
| **Severity** | UX |
| **Status** | Fixed |
| **Found in** | §4.1 Orders list filters |
| **Fixed** | 2026-08-02 |

**Fix**

Added a separate **Current step** dropdown on the Orders list (alongside Status). Options are distinct workflow step names (e.g. Print, Dry). Filtering matches `orders.current_step_id` → `workflow_steps.name`. Status filter unchanged (Open / Complete / Needs mapping / etc.).

**Files:** `includes/class-som-orders.php` (`step_name_options`, `query` `current_step`), `admin/views/orders-list.php`

---

### BUG-002 — “Add material” button does nothing

| | |
|---|---|
| **Severity** | Major |
| **Status** | Fixed |
| **Found in** | §8.1 / Materials list |
| **Fixed** | 2026-08-02 |

**Fix**

`SOM_Materials::detail_url()` no longer casts the id to `(int)`, so `'new'` is preserved in the URL (same pattern as products / suppliers / listings / POs).

**Files:** `includes/class-som-materials.php`

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
| §0 – §4.1 | OK; BUG-001 fixed — re-check Current step filter |
| §4.2 – §8.1 | Re-check Add material after BUG-002 |
| §8.2+ | Continue |

**Automated coverage:** `tests/bugfix-001-002-smoke.php` (wp-env) — PASS 2026-08-02.

---

*End of bugs log. Append new open `BUG-NNN` entries above Closed as testing continues.*
