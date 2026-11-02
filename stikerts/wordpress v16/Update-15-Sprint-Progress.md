# Update Package 15 — Sprint Progress

*Companion to [`Update-15-Sprint-Plan.md`](Update-15-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP15-S1 | Read-only MCP abilities | Done | wp-env PASS 2026-11-02. Schema stays 1.26.0 |
| UP15-S2 | Sticky order header | Done | wp-env PASS 2026-11-02. Schema stays 1.26.0 |

**Package 15:** planning still open for further operator items. UP15-S1 and UP15-S2 are on main. Both smokes passed wp-env on 2026-11-02. Schema is **1.26.0**. Plugin version stays **0.35.0**. Suggested releases **0.36.0** after S1 and **0.37.0** after S2 are not tagged.

---

## Planning notes

- 2026-10-24: operator asked for read-only MCP abilities covering workflows, budgets, shipping packages, boards, trays, channels, fee estimates, recurring expenses, analytics, and the material stock log, plus fields on `get-products`, `get-listings`, and `get-order-detail`, and `per_page` on `get-orders`.
- Soft defaults, 2026-10-24: one sprint; empty workflow status means all; budget list defaults to active; package list defaults to all; channel list is a five-column select; board cards omit `raw_payload`; smoke is `tests/sprint-up15-s1-smoke.php` and does not edit sprint 11; budget and board REST stays deferred.
- 2026-10-24: operator asked for a sticky order header with a Copy button on each important field, and a way to correct house number and street name when import split them wrong. Soft defaults: ship-to components stay separate from the sticker line fields; also copy buyer, town, postcode, quantity, and product name; design is editable; one editor in the header; no new personalisation type.
- 2026-10-24: the header also shows every held tray number, each with Copy. No tray shows **No tray**. Assign and release stay on the Trays panel.
- Local site **ordermachine** is this plugin folder. After a sprint branch is checked out, reload that site’s wp-admin. wp-env smokes for UP15-S1 and UP15-S2 passed on 2026-11-02.
- Baseline when this file was opened: `origin/main` `963ca49`, plugin **0.35.0**, schema **1.26.0**.

---

## UP15-S1 — Read-only MCP abilities

- **Status:** done. On main. Smoke run 2026-11-02 — PASS.
- **Behaviour:** see [`02-Update-Read-Abilities.md`](02-Update-Read-Abilities.md).
- **Smoke:** `tests/sprint-up15-s1-smoke.php` — **2026-11-02 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up15-s1-smoke.php`).

## UP15-S2 — Sticky order header

- **Status:** done. On main. Smoke run 2026-11-02 — PASS.
- **Behaviour:** see [`03-Update-Order-Sticky-Header.md`](03-Update-Order-Sticky-Header.md). The bar sticks under the admin bar. Each field has Copy, including every held tray number. House number, street name, and design save through the existing line-field update. The Make line-fields form is removed. Assign and release stay on the Trays panel.
- **Smoke:** `tests/sprint-up15-s2-smoke.php` — **2026-11-02 PASS** (`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up15-s2-smoke.php`).

---

## wp-env pass — 2026-11-02

Plugin 0.35.0, db 1.26.0. No Version bump.

Command from the plugin root:

`npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/<file>`

UP15-S1 and UP15-S2: PASS.

These older smokes were not run again, were not rewritten, and are not marked passed. They remain the failures recorded in the Package 12 wp-env pass ([`../wordpress v13/Update-12-Sprint-Progress.md`](../wordpress%20v13/Update-12-Sprint-Progress.md)): `bugfix-001-002`, `shipments`, `sprint11`, `sprint-u1`, `sprint-u5`, `sprint-u6`, `sprint-u7`, `sprint-up6-s3`, `sprint-up8-s2`, `sprint-up10-s1`, `step-confirmations`.
