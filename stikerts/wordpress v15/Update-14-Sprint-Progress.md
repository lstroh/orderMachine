# Update Package 14 — Sprint Progress

*Companion to [`Update-14-Sprint-Plan.md`](Update-14-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP14-S1 | Materials CSV | Not started | |
| UP14-S2 | Workflow CSV | Not started | Reuses the S1 suffix and preview helper |
| UP14-S3 | Product CSV | Not started | Reuses the S1 helper |
| UP14-S4 | Budget checkbox on Add material | Not started | Independent of the files |
| UP14-S5 | Collapsed Shipment panel | Not started | Independent of the files |
| UP14-S6 | Trays above Make, including Internal | Not started | Extends Package 8. Unblock on the order stays |
| UP14-S7 | Instruction bullets and editor preview | Not started | BUG-006. No allowlist change |
| UP14-S8 | Output per run | Not started | Schema 1.25.0. After S2 for the CSV column |
| UP14-S9 | Pack workflow override | Not started | Schema 1.26.0. Overturns Package 6 site-wide Pack |
| UP14-S10 | Mark done stays in place | Not started | Make and Post-ship. Pack already does this |
| UP14-S11 | Finished pack leaves the Pack board | Not started | Stays on the Post-ship board |
| UP14-S12 | Release the last tray on purpose | Not started | **Release anyway**. **Release selected** still keeps one |

**Package 14:** planning still open for further operator items. No implementation yet. Schema stays **1.24.0** until UP14-S8 (**1.25.0**) and UP14-S9 (**1.26.0**). Suggested releases **0.35.0**–**0.46.0** are not tagged.

---

## Planning notes

- Operator asked for import/export of materials, workflows, and products, and for a budget option when creating a material.
- Settled 2026-10-19: create `Name (2)` when the name exists (never update); fail a record when a referenced name is missing or ambiguous; budget checkbox on Add material only, unchecked by default.
- Settled 2026-10-19: Shipment disclosure closed on every order; Trays panel above Make; Internal orders require a tray when the make template has **Uses trays** (Package 8 skipped those products).
- 2026-10-19: filed **BUG-006**. Instruction lists already render as `<ul>`; wp-admin hides bullets. Preview opens under the textarea (UP14-S7).
- Settled 2026-10-19: make workflow **Output per run**. Internal completion adds Produce N × that number (UP14-S8, schema 1.25.0).
- Settled 2026-10-21: sellable product **Pack workflow override**. Site default remains. Primary product decides (UP14-S9, schema 1.26.0). Overturns Package 6 “Pack is site-wide only.”
- Settled 2026-10-21, confirmed: Make and Post-ship Mark done stay in place, same as Pack (UP14-S10).
- Settled 2026-10-21: an order leaves the Pack board when every pack step is done, and stays on the Post-ship board (UP14-S11). Narrows the Package 6 “open until the order is complete” board rule.
- Settled 2026-10-21: **Release anyway** frees the last tray while the order still requires one. **Release selected** still keeps one. **Unblock step** does not release a tray (UP14-S12).
- `script_config` is omitted so Package 9’s removal stays in force.
- Local site **ordermachine** is this plugin folder. UI appears there when a sprint branch is checked out. wp-env smokes wait until the operator runs the final package prompt.
- Specs: [`02-Update-Data-Model.md`](02-Update-Data-Model.md), [`03-Update-Catalog-Import-Export.md`](03-Update-Catalog-Import-Export.md), [`05-Update-Material-Budget-On-Create.md`](05-Update-Material-Budget-On-Create.md), [`06-Update-Order-Detail-Shipment-And-Trays.md`](06-Update-Order-Detail-Shipment-And-Trays.md), [`07-Update-Instruction-List-Preview.md`](07-Update-Instruction-List-Preview.md), [`08-Update-Workflow-Output-Per-Run.md`](08-Update-Workflow-Output-Per-Run.md), [`09-Update-Pack-Workflow-Override.md`](09-Update-Pack-Workflow-Override.md), [`10-Update-Mark-Done-Scroll.md`](10-Update-Mark-Done-Scroll.md), [`11-Update-Pack-Board-After-Pack.md`](11-Update-Pack-Board-After-Pack.md), [`12-Update-Release-Last-Tray.md`](12-Update-Release-Last-Tray.md).

---

## UP14-S1 — Materials CSV

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s1-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S2 — Workflow CSV

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s2-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S3 — Product CSV

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s3-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S4 — Budget checkbox on Add material

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s4-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S5 — Collapsed Shipment panel

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s5-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S6 — Trays above Make, including Internal

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s6-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S7 — Instruction bullets and editor preview

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s7-smoke.php` — not written. Pending wp-env after implementation.
- **Bug:** BUG-006 stays Open until the wp-env pass.

## UP14-S8 — Output per run

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s8-smoke.php` — not written. Pending wp-env after implementation.
- **Schema:** 1.25.0 in this sprint only.

## UP14-S9 — Pack workflow override

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s9-smoke.php` — not written. Pending wp-env after implementation.
- **Schema:** 1.26.0 in this sprint only.

## UP14-S10 — Mark done stays in place

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s10-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S11 — Finished pack leaves the Pack board

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s11-smoke.php` — not written. Pending wp-env after implementation.

## UP14-S12 — Release the last tray on purpose

- **Status:** not started.
- **Smoke:** `tests/sprint-up14-s12-smoke.php` — not written. Pending wp-env after implementation.
