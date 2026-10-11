# Update Package 9 — Sprint Plan

*Planning only. Specs in this folder. Baseline: **v0.32.2**.*

**Sequencing:** Structured eBay fields + copy-command step → remove local/api/n8n + docs.

## Locked decisions (operator)

- New **Copy command** gate; **remove** local / api / n8n from the product.  
- Inputs from **eBay order**; **per make line**.  
- Editable step template with `{{placeholders}}`.  
- Example skill: `/make-sticker-order` with order number, house, street, design, buyer name.  
- Operator runs locally; SOM only displays + Copy + Mark done.

---

## 1. Consolidated open items

| # | Item | Blocks | Status / decision |
|---|---|---|---|
| O1 | Exact eBay aspect names for house/street/style | UP9-S1 | **Confirm from live/fixture order** before locking aliases |
| O2 | `buyer_name` = first word? | UP9-S1 | **Default: yes** |
| O3 | Copy-command + timer on same step? | UP9-S1 | **Default: no** |
| O4 | Edit structured fields on order detail? | UP9-S1 | **Default: yes** |
| O5 | Legacy `script_config` at runtime | UP9-S2 | **Default: ignore (no dispatch); editor warns** |
| O6 | Script batch groups | UP9-S2 | **Default: disabled / soft-fail; docs** |
| O7 | Copy on Make board? | UP9-S1 | **Default: order detail only** |

---

## 2. Spec ↔ codebase

| Area | Today | Package 9 |
|---|---|---|
| Script step | local / api / n8n execute | **Removed** |
| Personalisation | Single text blob | + `house_number`, `street_name`, `style` from eBay |
| Make line UI | Gates / Mark done | + rendered command + Copy |
| Thank-you | Pack checklist (P6) | Unchanged |

---

## 3. Sprints

### UP9-S1 — eBay structured fields + Copy command step

| Work | Detail |
|---|---|
| Schema | Line columns; `workflow_steps.command_template` |
| eBay extract | Map aspects → house/street/style (O1); buyer first name helper |
| Editor | Command template field; placeholder help; no script panels on new edits (can hide in S1, delete in S2) |
| Engine / UI | Copy-command is not `waiting_script`; show panel on make line; Copy button |
| Order detail | Optional edit of house/street/style (O4) |
| Tests | `tests/sprint-up9-s1-smoke.php` — render template; missing fields notice |

**Done when:** a make step can show a filled `/make-sticker-order …` block from eBay-like data and copy it; smoke passes.

---

### UP9-S2 — Remove local/api/n8n + docs/seed

| Work | Detail |
|---|---|
| Remove editor | Delete local/api/n8n/raw script_config UI |
| Engine | Never dispatch `SOM_Script_Dispatch` on enter; ignore legacy config (O5) |
| Batches | Script batch actions disabled/soft-fail (O6) |
| Seed | Example make step with copy-command template for small sticker flow |
| Docs | USER-GUIDE / REFERENCE / FEATURES — copy command; scripts removed |
| Cleanup | Deprecate callback docs; trim local-actions allowlist from workflow path |
| Tests | `tests/sprint-up9-s2-smoke.php` — no waiting_script from legacy seed |

**Done when:** product cannot configure or run script/api/n8n steps; copy-command is the documented path; smoke passes.

---

## 4. Suggested SemVer

| After | Plugin (approx) |
|---|---|
| UP9-S1 | 0.39.0* |
| UP9-S2 | 0.40.0* |

\*After Packages 7–8 version bumps.

---

## 5. Out of scope

- Server-side execution of Cursor skills / shell  
- Restoring n8n webhooks  
- AI parsing of unstructured personalisation  

---

## 6. Quick checklist

### UP9-S1
- [ ] Schema: command_template + line house/street/style
- [ ] eBay aspect → fields (confirm labels)
- [ ] Step editor template + placeholders
- [ ] Make line: render + Copy
- [ ] Smoke

### UP9-S2
- [ ] Remove local/api/n8n from product
- [ ] Engine/batch cleanup
- [ ] Seed example + docs
- [ ] Smoke
