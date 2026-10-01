# Update — Make Step Gate & Confirmation UX

*Package 7 · No schema. Driven by operator testing (order detail Make panel).*

---

## 1. What operators reported

On order detail **MAKE** for a line (e.g. `1× Small sticker bin`):

- Step **Create PDF** already shows badge **Done** before any operator action.  
- A **Confirmation checklist** (“I confirmed the print matches the client request”) appears above the step list.  
- Step **Print on plain paper** is **In progress** with **Mark done** disabled until the checklist is saved.

Stale copy also remains on the Make panel: “Pack & Ship controls arrive in UP6-S2” even though Pack & Ship already exists.

Logged as **BUG-003** in `stikerts/wordpress/BUGS.md`.

## 2. Likely causes (to verify in UP7-S1)

### A. Zero-gate auto-complete (most likely for Create PDF)

`SOM_Item_Make::enter_item_step` / `SOM_Workflow_Engine::enter_step`: if a step has **no** timer, **no** manual confirm, **no** confirmation kind, **no** script, **no** batch → status set to **done** immediately and the engine advances to the next step.

So if **Create PDF** was saved without “Requires manual confirm” (and without a checklist), new orders will mark it Done on assign.

The checklist text matches confirmation kind **print vs request**, which belongs to the **current** step (Print), not Create PDF — but the panel is rendered **above** the whole step list, so it looks like it belongs to Create PDF / the section.

### B. UX layout

Confirmation checklist is not nested under the current step card, so operators cannot tell which step it gates.

### C. Stale Make header copy

`admin/views/order-detail.php` still prints the UP6-S2 placeholder next to Open Make Board.

## 3. Settled fix goals

| Goal | Detail |
|---|---|
| No mystery Done | Ungated steps must be obvious in the **workflow editor** before save |
| Checklist ownership | Checklist UI must sit **inside / under the current step** |
| Copy | Remove UP6-S2 placeholder; Pack lives in Pack & Ship section |
| Docs | Short note in USER-REFERENCE: zero-gate = auto-complete |

## 4. Soft defaults

| Topic | Default |
|---|---|
| Workflow editor | **Warn** on save when any step has zero gates (“This step will auto-complete when entered”) |
| First make step | Soft warning if ungated (do not hard-block — some templates may want pass-through) |
| Order detail | Move confirmation panel into the current step’s `<li>` |
| Mark done | Unchanged rules (`can_mark_done` + checklist complete) |
| Existing open orders | Do not rewrite already-Done zero-gate steps; fix applies to **new** enters + UI |

## 5. Work items (detail)

1. Reproduce with the operator’s make template: confirm Create PDF gates in DB (`requires_manual_confirm`, `confirmation_kind`, timer, script).  
2. If gates were set and it still auto-completed → treat as engine bug and fix `enter_item_step`.  
3. If gates were empty → editor warning + optional “Requires manual confirm” default when adding a new step.  
4. Nest checklist under current step; show step name in checklist heading (e.g. “Confirmation checklist — Print on plain paper”).  
5. Remove stale UP6-S2 string from Make panel.  
6. Smoke: ungated step auto-completes; gated first step stays `in_progress`; checklist only enables Mark done on that step.  
7. Fix typo opportunity only if editing seed/operator template names (e.g. “papger”) — not a product bug.

## 6. Out of scope

- Removing zero-gate behaviour entirely (still useful for pass-through steps)  
- Board DnD changes  
- Auto Mark done when checklist saves (checklist Save ≠ Mark done; keep explicit)  
