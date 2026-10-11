# Update Package 9 — Data Model Changes

*Copy-command steps + structured personalisation for placeholders. Baseline: **v0.32.2**.*

---

## A. Workflow step: copy command (replaces script_config for this use)

### Option A (recommended) — new columns on `workflow_steps`

| Column | Type | Notes |
|---|---|---|
| `command_template` | TEXT NULL | Editable template with `{{placeholders}}`. Null = no copy-command gate. |

**Exclusive gates (same spirit as confirmation):** if `command_template` is set, step must not also use timer/script/batch (timer may be allowed later — **default: exclusive with script/batch; may combine with manual confirm**). Soft default: copy-command implies manual confirm (Mark done after you run locally).

### Deprecate / stop writing `script_config` for new edits

- Editor no longer offers local / api / n8n.  
- Existing `script_config` rows: on load, show as legacy disabled notice, or one-time clear on save — **default: ignore at runtime + warn in editor until cleared**.

---

## B. Structured line fields (from eBay)

Today `order_items.personalisation_text` is a single string. Placeholders need structured values.

### `order_items` additions (recommended)

| Column | Type | Notes |
|---|---|---|
| `house_number` | VARCHAR(64) NULL | From eBay line aspect |
| `street_name` | VARCHAR(191) NULL | From eBay line aspect |
| `style` | VARCHAR(191) NULL | Design name (aspect or listing/variation title) |
| `personalisation_json` | TEXT NULL | Optional raw aspects map for debugging / future fields |

Keep `personalisation_text` as a display summary (e.g. joined aspects) for lists.

### Order-level placeholders (no new columns)

| Placeholder | Source |
|---|---|
| `order_number` | `orders.external_order_id` |
| `buyer_name` | First token of `orders.buyer_name` (or full name — open) |
| `buyer_full_name` | `orders.buyer_name` |
| `order_id` | Internal PK (optional) |

---

## C. eBay extract mapping

Extend `SOM_Channel_Ebay` line normalisation to fill structured columns from `variationAspects` / `properties` by aspect **name** (case-insensitive match).

**Soft-default aspect name aliases**

| Field | Aspect name candidates |
|---|---|
| `house_number` | house number, house no, house #, number |
| `street_name` | street name, street, road name |
| `style` | design, style, design name, ebay design |

If style missing: fallback to line item title from payload if present.

**Open:** confirm exact eBay custom aspect labels from a live/fixture order and lock the alias list in S1.

---

## D. Open items (data)

1. `buyer_name` placeholder = first name only vs full — **default: first word** for “Thank you, …!”.  
2. Store aspects only in columns vs also `personalisation_json` — **default: columns + keep summary text**.  
3. Etsy / External / test orders: same columns editable on test-order form or order detail — **default: yes, editable on order detail for non-eBay fills**.  
