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

### BUG-004 — Produce N shows “No workflow”; Make UI missing on order detail

| | |
|---|---|
| **Severity** | Major / UX |
| **Status** | Open |
| **Found in** | Products → Produce N → Make Board vs Orders list / order detail |
| **Env** | Operator testing; 2026-10-14 |
| **Plan** | Package 12 — [`../wordpress v13/`](../wordpress%20v13/) (`03-Update-Produce-N-Workflow-Visibility.md`, UP12-S1 / UP12-S2) |

**Symptoms**

- After **Produce N** on an internal product (workflow + recipe set), channel **Internal**.  
- **Make Board** shows the line and drag-advance works.  
- Orders list Flags: **No workflow**; Status often **Open**.  
- Order detail Make section does not show step progress / Mark done (looks like no workflow).

**Likely cause**

- List badge / `needs_workflow` filter still key off order-level `current_step_id` + `order_step_progress` only; Produce N uses **`order_item_step_progress`** and never binds Pack.  
- Order detail hides Make controls whenever `product.is_internal`, which is correct for internal lines on **customer** orders but wrong for Internal-channel Produce N jobs that have item progress.

**Expected**

- List must not false-flag Produce N as **No workflow**; Status should surface current make step when appropriate.  
- Order detail must show the Make step UI for Produce N; keep **Pack-ready (internal)** only for internal lines on non-Internal orders.

**Progress**

- **UP12-S1** (this change): Orders list Flags, Status, and the Needs workflow filter use per-line make progress. An open Produce N job is not flagged **No workflow**; Status shows the current make step when no order-level step is set. Smoke: `tests/sprint-up12-s1-smoke.php` (pending wp-env).  
- Order detail Make UI is still missing. Status stays **Open** until UP12-S2.

### BUG-005 — Post-ship workflow can be saved without a Package arrived step

| | |
|---|---|
| **Severity** | Major / UX |
| **Status** | Open |
| **Found in** | Workflows (Post-ship) → order detail Post-ship panel |
| **Env** | Operator testing; 2026-10-14 |
| **Plan** | Package 12 — [`../wordpress v13/`](../wordpress%20v13/) (`07-Update-Post-Ship-Arrival-Required.md`, UP12-S5) |

**Symptoms**

- Custom Post-ship workflow; order shows **Awaiting arrival** but no step offers the **Package arrived** checklist.  
- Order can never complete (complete = shipment recorded **and** arrival confirmed via a `package_arrived` step).

**Likely cause**

- `SOM_Workflows::save_steps` validates individual steps only; no template-level rule that a Post-ship template contains an arrival step.  
- Save handler writes template fields before step validation, so a failed step save can still leave partial changes / a new template row.

**Expected**

- Saving a Post-ship template (create, edit, or kind change; active or inactive) is blocked unless at least one step uses Confirmation checklist **Package arrived**; nothing is written on failure.  
- Existing invalid templates are flagged (Workflows list, editor, Settings default picker — warn only); stuck orders show a notice on order detail.

---

## Closed / fixed

### BUG-003 — Make step shows Done before work; checklist not tied to step

| | |
|---|---|
| **Severity** | Major / UX |
| **Status** | Fixed |
| **Found in** | Order detail → MAKE (post Package 6) |
| **Fixed** | 2026-10-11 |
| **Env** | Operator testing; screenshot 2026-10-01 |

**Symptoms**

- First make step (e.g. **Create PDF**) badge **Done** before any Mark done.  
- Confirmation checklist (“print matches the client request”) appears above the step list while a later step (**Print…**) is In progress with Mark done disabled.  
- Make panel still shows stale copy: “Pack & Ship controls arrive in UP6-S2.”

**Root cause / fix**

- **Create PDF Done** was zero-gate auto-complete (`enter_item_step` / `enter_step`), not an engine bug when gates are set. Gated first steps stay `in_progress`.  
- Nested confirmation checklist under the current make/pack step `<li>`; heading includes the step name.  
- Removed stale UP6-S2 Make panel copy.  
- Workflow editor: warn on Save for zero-gate steps; default **Requires manual confirm** on new step cards.  
- USER-REFERENCE note: zero-gate steps auto-complete.

**Files:** `admin/views/order-detail.php`, `admin/views/workflow-step-editor.php`, `admin/assets/js/admin.js`, `stikerts/wordpress/USER-REFERENCE.md`, `tests/sprint-up7-s1-smoke.php`

**Plan:** Update Package 7 / **UP7-S1** — `stikerts/wordpress v8/04-Update-Make-Step-Gate-UX.md`

---

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
| Order detail Make (BUG-003) | Fixed in UP7-S1 — checklist nested; zero-gate warn |

**Automated coverage:** `tests/bugfix-001-002-smoke.php` (wp-env) — PASS 2026-08-02; `tests/sprint-up7-s1-smoke.php` — UP7-S1 / BUG-003.

---

*End of bugs log. Append new open `BUG-NNN` entries above Closed as testing continues.*
