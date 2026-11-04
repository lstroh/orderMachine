# Update Package 16 — Sprint Plan

*Planning open for further operator items. Soft defaults are from 2026-11-02. Specs in this folder. Baseline: plugin **0.36.0**, schema **1.26.0**, `origin/main` (`84452e5` when this plan was written; tag `v0.36.0`).*

**Sequencing:** Required step photos first (UP16-S1). The schema change is part of that sprint. Further operator items append as UP16-S2 onward and do not reopen this sprint.

## Locked decisions (operator, 2026-11-02)

- A workflow step may **Require photo**, with an optional label of at most 191 characters. At least one JPEG or PNG. Each file is 5 MB or smaller.
- Mark done stays locked until that progress row has a photo. A photo-only step does not auto-complete.
- Bypass is the existing **Unblock step** on order detail. It skips every gate, including `photo`, and does not Mark done. The existing confirm copy and order note stay. The note’s gate list includes `photo`.
- Make photos hang off `order_item_step_progress`. Pack and Post-ship photos hang off `order_step_progress`.
- Files are WordPress attachments (parent 0) under `wp-content/uploads/order-machine/step-photos/YYYY/MM/`. The plugin stores the attachment id on `som_step_photos`.
- Add and remove only on the current, not-done step. After Mark done the thumbnails stay.
- Photo may combine with manual confirm, checklist, copy command, and timer. It may not combine with a batch group.
- No board uploader. No per-product override. No named slots.
- Workflow CSV and clone copy `photo_required` and `photo_label`. `get-workflow-template` steps include those two fields and do not include file URLs.
- Schema becomes **1.27.0** in UP16-S1. No Version bump inside the sprint. Unreleased changelog line only.
- New smoke: `tests/sprint-up16-s1-smoke.php`. Do not edit the eleven stale smokes.

The planning-brief deferrals stay deferred: Product Costing packaging line, Pack Copy UI, Unblock on boards, Trays overview, hard-delete of script dispatch classes, rewriting the eleven stale smokes, a customer order that contains only internal products, and budget and board REST.

---

## 1. Open items

| # | Item | Blocks | Decision |
|---|---|---|---|
| O1 | Photo gate | UP16-S1 | **Settled** — [`02-Update-Step-Photos.md`](02-Update-Step-Photos.md). One sprint. |
| O2 | Bypass | UP16-S1 | **Settled** — existing Unblock step. No photo-only skip. |
| O3 | How many | UP16-S1 | **Settled** — at least one, optional label, no slots. |
| O4 | Which steps | UP16-S1 | **Settled** — Make, Pack, and Post-ship. Make is per line. |
| O5 | Storage | UP16-S1 | **Settled** — `uploads/order-machine/step-photos/YYYY/MM/`, JPEG and PNG, 5 MB, attachment id. |
| O6 | Other gates | UP16-S1 | **Settled** — combines with manual, checklist, copy command, and timer. Exclusive with batch. |
| O7 | After Mark done | UP16-S1 | **Settled** — thumbnails stay. Add and remove only while current. |
| O8 | Abilities | UP16-S1 | **Settled** — `photo_required` and `photo_label` on the step projection. No file payload. |

Further operator items can still be appended.

---

## 2. Spec ↔ codebase

| Area | Today (`0.36.0`) | Package 16 |
|---|---|---|
| Step gates | Manual, timer, checklist, copy command, batch. Zero gates auto-complete | **Require photo** is another gate. Photo-only stays `in_progress` |
| `can_mark_done()` | Timer, ship, checklist, trays. Unblock short-circuits | Also fails when a required photo is missing |
| `blocking_gate_keys()` | No photo key, so Unblock would not appear for a photo-only wait | Adds `photo` |
| Make enter | `SOM_Item_Make::enter_item_step()` auto-completes when timer and manual are empty | `photo_required` counts as manual |
| Proof files | `SOM_Shipments` stores one JPEG, PNG, or PDF on the shipment, default uploads folder | Step photos are a new class and a new table. Shipment proof is unchanged |
| Workflow CSV | Columns end at `batch_group_key` | Appends `photo_required`, `photo_label` |
| Abilities | `map_workflow_step()` omits any photo field | Adds the flag and the label |
| Schema | **1.26.0** | **1.27.0** |

---

## 3. Sprints

### UP16-S1 — Required step photos

No dependency on another Package 16 sprint.

| Work | Detail |
|---|---|
| Schema | Columns and `som_step_photos` in [`02-Update-Step-Photos.md`](02-Update-Step-Photos.md) §2. `DB_VERSION` **1.27.0** |
| Editor | Require photo + label. Save, clone, CSV, and the zero-gate / batch warnings |
| Engine | `can_mark_done()`, `blocking_gate_keys()`, both enter paths |
| Order detail | Uploader and thumbnails on the current Make, Pack, and Post-ship step. Remove while current |
| Abilities | Two fields on the workflow step projection |
| Tests | `tests/sprint-up16-s1-smoke.php` |
| Docs | CHANGELOG Unreleased. FEATURES-AND-TESTING workflow editor, workflow engine (Unblock list), and order detail. USER-REFERENCE step gates and order detail. `MCP.md` one sentence on the two step fields |

**Done when:** a step can require a photo; Mark done waits for a JPEG or PNG on that progress row; Unblock allows Mark done and records `photo`; Make and Pack keep separate pictures; a done step still shows its thumbnails; clone and CSV keep the flag. See §8 of the spec.

**Suggested SemVer after merge:** `0.37.0`.

---

## 4. After the last sprint

This package is one sprint. The UP16-S1 chat runs `tests/sprint-up16-s1-smoke.php` with wp-env after the implementation, records the pass in [`Update-16-Sprint-Progress.md`](Update-16-Sprint-Progress.md), and does not bump the version or tag a release. It does not rewrite the eleven stale smokes. There is no separate wp-env chat.

The eleven stale smokes stay as recorded in `stikerts/wordpress v13/Update-12-Sprint-Progress.md`: bugfix-001-002, shipments, sprint11, sprint-u1, sprint-u5, sprint-u6, sprint-u7, sprint-up6-s3, sprint-up8-s2, sprint-up10-s1, step-confirmations.
