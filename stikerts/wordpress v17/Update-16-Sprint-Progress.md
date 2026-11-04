# Update Package 16 — Sprint Progress

*Companion to [`Update-16-Sprint-Plan.md`](Update-16-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP16-S1 | Required step photos | Not started | Schema moves to 1.27.0 in this sprint |

**Package 16:** planning still open for further operator items. No sprint is on main. Schema is **1.26.0** until UP16-S1. Plugin version stays **0.36.0**. Suggested release **0.37.0** after S1 is not tagged.

---

## Planning notes

- 2026-11-02: operator asked for pictures on some steps, blocking Mark done, with a way to bypass, and where to store the files on the Local site.
- Settled 2026-11-02: **Require photo** plus an optional label on any Make, Pack, or Post-ship step. At least one JPEG or PNG, 5 MB each, under `uploads/order-machine/step-photos/YYYY/MM/`. Make is per line. Pack and Post-ship are per order. Bypass is the existing **Unblock step**. Photos stay visible after Mark done. Batch steps cannot require a photo. No board uploader and no per-product override.
- Local site **ordermachine** is this plugin folder. After a sprint branch is checked out, reload that site’s wp-admin.
- 2026-11-04: the operator started the package. It is one sprint, so the UP16-S1 chat also runs the wp-env smoke and records the pass. That chat does not bump the version or tag a release.
- Baseline when this file was opened: `origin/main` `84452e5`, plugin **0.36.0**, schema **1.26.0**.

---

## UP16-S1 — Required step photos

- **Status:** not started.
- **Behaviour:** see [`02-Update-Step-Photos.md`](02-Update-Step-Photos.md).
- **Smoke:** `tests/sprint-up16-s1-smoke.php` — not written yet.
