# Update — Copy command placeholder picker

*Package 12 · No schema. Driven by operator request (insert placeholders into the Copy command template without typing them).*

---

## 1. What operators asked for

In the workflow step editor’s **Copy command template**, a control to add any of the supported placeholders into the template.

## 2. Today (Package 9 — locked)

| Topic | Today |
|---|---|
| Field | `textarea.som-command-template` in `admin/views/workflow-step-editor.php` (`[data-som-copy-command-gate]`), max 4000 chars (`SOM_Copy_Command::MAX_TEMPLATE_LENGTH`) |
| Placeholders | `SOM_Copy_Command::placeholder_keys()` — `order_number`, `house_number`, `street_name`, `style`, `buyer_name`, `buyer_full_name`, `product_name`, `personalisation` |
| Help | Placeholders listed as plain text in the field description; operator types `{{key}}` by hand |
| Render | `SOM_Copy_Command::render` on make lines (order detail only); unknown `{{…}}` left literal |
| Where edited | Workflow step editor only (no product override for copy command) |

## 3. Settled decisions (operator, 2026-10-14)

| # | Topic | Decision |
|---|---|---|
| P1 | Control | **Dropdown** (“Insert placeholder…”) above the Copy command textarea — not a row of chips |
| P2 | Labels | Friendly name (e.g. “Order number”); the `{{order_number}}` token visible on hover (`title`) |
| P3 | Insert | At the **cursor**, replacing any selected text |
| P4 | Example template button | **No** |
| P5 | New placeholders | **No** — only the existing 8 |
| P6 | Sprint | Own sprint **UP12-S4**, reusing UP12-S3’s cursor-insert JS helper |

## 4. Behaviour

- One `<select>` per copy-command gate, labelled for screen readers (“Insert placeholder”). First option is a non-value prompt “Insert placeholder…”.  
- Options generated server-side from `SOM_Copy_Command::placeholder_keys()` with a label map (single source of truth — adding a key later only needs a label).  
- On change: insert `{{key}}` at the caret / replace selection in the sibling `.som-command-template`; return focus to the textarea with the caret after the token; reset the select to the prompt; fire `input` so the existing copy/timer/batch combo warning logic stays in sync.  
- Works on step cards added dynamically (delegated `change` handling).  
- Respect `maxlength` 4000: skip insert if it would exceed (same rule as UP12-S3 O6).  
- Keep the plain-text placeholder list in the description (useful reference).

Soft default labels:

| Key | Label |
|---|---|
| `order_number` | Order number |
| `house_number` | House number |
| `street_name` | Street name |
| `style` | Style / design |
| `buyer_name` | Buyer first name |
| `buyer_full_name` | Buyer full name |
| `product_name` | Product name |
| `personalisation` | Personalisation |

## 5. Work items (detail)

1. Add `SOM_Copy_Command::placeholder_labels()` (or similar) keyed by `placeholder_keys()`.  
2. Render the dropdown in `workflow-step-editor.php` above the textarea (and in the JS template used for new step cards if separate).  
3. JS: `initCopyCommandPlaceholderPicker` in `admin/assets/js/admin.js` using the shared insert-at-cursor helper from UP12-S3.  
4. CSS: minimal spacing.  
5. Smoke `tests/sprint-up12-s4-smoke.php`: every placeholder key has a label; editor markup contains the select with one option per key (`{{key}}` values); render of a template built from all keys still substitutes correctly (regression).  
6. Docs: USER-REFERENCE (workflow editor → Copy command) + FEATURES-AND-TESTING manual check.

## 6. Out of scope

- New placeholders / value sources  
- “Insert example template” button  
- Pack Copy UI (still deferred from Packages 7–11)  
- Copy command on boards  
- Product-level copy-command overrides  
