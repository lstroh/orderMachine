# Update Package 16 — Sprint Progress

*Companion to [`Update-16-Sprint-Plan.md`](Update-16-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP16-S1 | Required step photos | Done | wp-env PASS 2026-11-04. Schema **1.27.0**. Plugin stays **0.36.0** |

**Package 16:** planning still open for further operator items. UP16-S1 is on this branch, not merged. Schema is **1.27.0**. Plugin version stays **0.36.0**. Suggested release **0.37.0** after S1 is not tagged.

---

## Planning notes

- 2026-11-02: operator asked for pictures on some steps, blocking Mark done, with a way to bypass, and where to store the files on the Local site.
- Settled 2026-11-02: **Require photo** plus an optional label on any Make, Pack, or Post-ship step. At least one JPEG or PNG, 5 MB each, under `uploads/order-machine/step-photos/YYYY/MM/`. Make is per line. Pack and Post-ship are per order. Bypass is the existing **Unblock step**. Photos stay visible after Mark done. Batch steps cannot require a photo. No board uploader and no per-product override.
- Local site **ordermachine** is this plugin folder. After a sprint branch is checked out, reload that site’s wp-admin.
- 2026-11-04: the operator started the package. It is one sprint, so the UP16-S1 chat also runs the wp-env smoke and records the pass. That chat does not bump the version or tag a release.
- 2026-11-04: UP16-S1 wp-env smoke PASS. Plugin stays **0.36.0**. Schema **1.27.0**. No release tag.
- Baseline when this file was opened: `origin/main` `84452e5`, plugin **0.36.0**, schema **1.26.0**.

---

## UP16-S1 — Required step photos

- **Status:** done on this branch. Smoke run 2026-11-04 — PASS. Not merged. No Version bump.
- **Behaviour:** see [`02-Update-Step-Photos.md`](02-Update-Step-Photos.md). Require photo plus an optional label. Mark done waits for a JPEG or PNG on that progress row. Unblock records `photo` and does not complete the step. Make and Pack keep separate pictures. A done step keeps its thumbnails.
- **Smoke:** `tests/sprint-up16-s1-smoke.php` — **2026-11-04 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up16-s1-smoke.php`).

---

## wp-env pass — 2026-11-04

Plugin 0.36.0, schema 1.27.0. No Version bump.

Command from the plugin root:

`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up16-s1-smoke.php`

UP16-S1: PASS.

These older smokes were not run again, were not rewritten, and are not marked passed. They remain the failures recorded in the Package 12 wp-env pass ([`../wordpress v13/Update-12-Sprint-Progress.md`](../wordpress%20v13/Update-12-Sprint-Progress.md)): `bugfix-001-002`, `shipments`, `sprint11`, `sprint-u1`, `sprint-u5`, `sprint-u6`, `sprint-u7`, `sprint-up6-s3`, `sprint-up8-s2`, `sprint-up10-s1`, `step-confirmations`.
