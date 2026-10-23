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

None.

---

## Closed / fixed

### BUG-006 — Step instruction bullets do not show, and the editor preview is easy to miss

| | |
|---|---|
| **Severity** | UX |
| **Status** | Fixed |
| **Found in** | Order detail Make step instructions; Workflows step editor |
| **Fixed** | 2026-10-23 |
| **Env** | Local QA `ordermachine1910.local`, order 2, workflow template 2, Quality check. 2026-10-19. wp-env PASS 2026-10-23 |
| **Plan** | Package 14 — [`../wordpress v15/`](../wordpress%20v15/) (`07-Update-Instruction-List-Preview.md`, UP14-S7) |

**Symptoms**

- Workflow instructions use Markdown `- ` lines. On the order they read as plain lines. **Good sheet** is bold. There are no bullets.
- On the workflow editor the same text stays in the source box. The operator does not see a rendered preview next to it.

**Root cause / fix**

- `SOM_Step_Instructions::render_html()` already emits `<ul><li>` (Parsedown, allowlist includes `ul` / `li`). WordPress admin CSS sets `ul { list-style: none }`. **UP14-S7:** `.som-step-instructions-body ul` uses disc markers and `.som-step-instructions-body ol` uses decimal numbers, with left inset, on order detail (Make, Pack, Post-ship) and the editor preview.
- Preview stays a toolbar button and starts closed. The rendered box is the first block under the textarea, above the help line and the cheatsheet. Hide preview closes it.

**Files:** `admin/assets/css/admin.css`, `includes/class-som-step-instructions.php`, `tests/sprint-up14-s7-smoke.php`

---

### BUG-005 — Post-ship workflow can be saved without a Package arrived step

| | |
|---|---|
| **Severity** | Major / UX |
| **Status** | Fixed |
| **Found in** | Workflows (Post-ship) → order detail Post-ship panel |
| **Fixed** | 2026-10-17 |
| **Env** | Operator testing; 2026-10-14 |
| **Plan** | Package 12 — [`../wordpress v13/`](../wordpress%20v13/) (`07-Update-Post-Ship-Arrival-Required.md`, UP12-S5) |

**Symptoms**

- Custom Post-ship workflow; order shows **Awaiting arrival** but no step offers the **Package arrived** checklist.  
- Order can never complete (complete = shipment recorded **and** arrival confirmed via a `package_arrived` step).

**Root cause / fix**

- `SOM_Workflows::save_steps` validated individual steps only, and the admin save wrote the template before step validation. **UP12-S5:** `validate_submission()` normalises the posted steps and requires a Package arrived step when the resulting kind is `post_ship` (create, edit, or kind change; active or inactive). `save_submission()` runs that check before `update` / `create`, and `save_steps()` calls the same check. A failed save writes nothing. The editor blocks the same case before submit.  
- Existing invalid templates are not repaired. The Workflows list shows **No arrival step**, the editor shows a warning, and Settings suffixes **(no arrival step)** and warns without blocking the choice. An open order whose bound post-ship steps have no arrival step shows a notice and keeps those steps.

**Files:** `includes/class-som-workflows.php`, `admin/class-som-admin-menu.php`, `admin/views/workflow-templates.php`, `admin/views/workflow-step-editor.php`, `admin/views/settings.php`, `admin/views/order-detail.php`, `admin/assets/js/admin.js`, `tests/sprint-up12-s5-smoke.php`

---

### BUG-004 — Produce N shows “No workflow”; Make UI missing on order detail

| | |
|---|---|
| **Severity** | Major / UX |
| **Status** | Fixed |
| **Found in** | Products → Produce N → Make Board vs Orders list / order detail |
| **Fixed** | 2026-10-16 |
| **Env** | Operator testing; 2026-10-14 |
| **Plan** | Package 12 — [`../wordpress v13/`](../wordpress%20v13/) (`03-Update-Produce-N-Workflow-Visibility.md`, UP12-S1 / UP12-S2) |

**Symptoms**

- After **Produce N** on an internal product (workflow + recipe set), channel **Internal**.  
- **Make Board** shows the line and drag-advance works.  
- Orders list Flags: **No workflow**; Status often **Open**.  
- Order detail Make section does not show step progress / Mark done (looks like no workflow).

**Root cause / fix**

- List badge / `needs_workflow` keyed off order-level `current_step_id` + `order_step_progress` only. Produce N uses **`order_item_step_progress`** and never binds Pack. **UP12-S1:** Flags, Status, and the Needs workflow filter use per-line make progress.  
- Order detail hid Make controls whenever `product.is_internal`. That stays correct for internal lines on **customer** orders. **UP12-S2:** Internal-channel lines that already have item progress render the same step list, Mark done, instructions, tasks, and copy command as a sellable line. Mid-job is the current step (not **Pack-ready (internal)**). Make-complete uses the same **Ready to pack** treatment. `unassigned_reason` stays empty when item progress exists, and still flags true gaps.

**Files:** `includes/class-som-orders.php`, `admin/views/orders-list.php`, `includes/class-som-item-make.php`, `admin/views/order-detail.php`, `tests/sprint-up12-s1-smoke.php`, `tests/sprint-up12-s2-smoke.php`

---

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
| Produce N list + detail (BUG-004) | Fixed in UP12-S1 / UP12-S2 — smoke pending wp-env |

**Automated coverage:** `tests/bugfix-001-002-smoke.php` (wp-env) — PASS 2026-08-02; `tests/sprint-up7-s1-smoke.php` — UP7-S1 / BUG-003.

---

*End of bugs log. Append new open `BUG-NNN` entries above Closed as testing continues.*
