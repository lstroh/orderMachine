# Update — Copy Command Workflow Step

*Package 9 · Schema in `02-Update-Data-Model.md`. Self-contained.*

---

## 1. What this adds

A workflow step that **shows a command to copy and run locally**, filled from the eBay order / make line. WordPress never executes the command.

Also **removes** product support for auto-run **local / api / n8n** script steps.

## 2. Settled rules

| Topic | Decision |
|---|---|
| Gate | New **Copy command** (not script dispatch) |
| Remove | local / api / n8n from editor + engine for this product |
| Data | From **eBay order** (structured line fields + order ids/names) |
| Scope | **Per make line** |
| Template | Editable on the step; placeholders like `{{order_number}}` |
| After copy | Operator runs locally; still **Mark done** in SOM |

## 3. Example

**Step template (editable):**

```text
/make-sticker-order Order number: {{order_number}}
House number: {{house_number}}
Street: {{street_name}}
eBay design: {{style}}
Buyer name: {{buyer_name}}
```

**Rendered on make line (order detail):**

```text
/make-sticker-order Order number: 12-34567-89012
House number: 36
Street: Grove Street
eBay design: Classic Scrolls
Buyer name: Priya
```

**UI:** monospace block + **Copy** button. Missing placeholders show empty or `???` + warning — **default: empty string + notice “Missing: house_number, …”**.

## 4. Placeholder set (v1)

| Placeholder | Source |
|---|---|
| `{{order_number}}` | `external_order_id` |
| `{{house_number}}` | line `house_number` (eBay aspect) |
| `{{street_name}}` | line `street_name` |
| `{{style}}` | line `style` (design) |
| `{{buyer_name}}` | buyer first name |
| `{{buyer_full_name}}` | full buyer name |
| `{{product_name}}` | matched product name (optional) |
| `{{personalisation}}` | legacy summary text (optional) |

Unknown `{{…}}` left unchanged or stripped — **default: leave as literal `{{name}}`** so typos are visible.

## 5. Workflow editor

- Remove Script / API / n8n panels and raw `script_config` JSON entry.  
- Add **Copy command template** textarea + placeholder cheat-sheet.  
- Mutual exclusion: cannot combine with batch; confirmation/timer policy per open items.  
- Seed / docs: example Small Sticker make step with the `/make-sticker-order` template.

## 6. Order detail (make line)

When current (or any) step has `command_template`:

- Render command for **that line**.  
- **Copy** to clipboard.  
- Mark done unchanged (manual).

## 7. Removing script execution

| Area | Change |
|---|---|
| Editor | No local/api/n8n |
| Engine | Do not enter `waiting_script` for legacy configs; treat as no script gate (or force manual-only) |
| `SOM_Script_Dispatch` / local actions | Stop calling from workflow enter; may keep class stub or delete in S2 |
| Batch groups `action` script | Pack path already checklist; document that script batches are unsupported — **default: script batch release fails soft / disabled** |
| REST workflow-callback | Leave route but unused, or deprecate in docs |

## 8. eBay field quality

If aspects are missing, Copy still works but shows missing-field notice. Operator can **edit structured fields on order detail** (S1/S2) so External/test orders and bad eBay payloads are fixable.

## 9. Out of scope

- Executing the skill/command from the server  
- n8n/API reintroduction  
- Parsing free-text personalisation with AI  

## 10. Open items

1. Exact eBay aspect labels for house / street / style — confirm from a real order.  
2. Combine copy-command with timer on same step? **Default: no** (copy + manual only).  
3. Show Copy on board cards? **Default: order detail only.**  
4. First-name parsing for multi-word / non-English names — **default: first whitespace token**.  
