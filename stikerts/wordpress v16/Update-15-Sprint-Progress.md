# Update Package 15 — Sprint Progress

*Companion to [`Update-15-Sprint-Plan.md`](Update-15-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP15-S1 | Read-only MCP abilities | Not started | Spec [`02-Update-Read-Abilities.md`](02-Update-Read-Abilities.md). Schema stays 1.26.0 |
| UP15-S2 | Sticky order header | Not started | Spec [`03-Update-Order-Sticky-Header.md`](03-Update-Order-Sticky-Header.md). Schema stays 1.26.0 |

**Package 15:** planning still open for further operator items. No sprint is on main. Schema is **1.26.0**. Plugin version stays **0.35.0**. Suggested releases **0.36.0** after S1 and **0.37.0** after S2 are not tagged.

---

## Planning notes

- 2026-10-24: operator asked for read-only MCP abilities covering workflows, budgets, shipping packages, boards, trays, channels, fee estimates, recurring expenses, analytics, and the material stock log, plus fields on `get-products`, `get-listings`, and `get-order-detail`, and `per_page` on `get-orders`.
- Soft defaults, 2026-10-24: one sprint; empty workflow status means all; budget list defaults to active; package list defaults to all; channel list is a five-column select; board cards omit `raw_payload`; smoke is `tests/sprint-up15-s1-smoke.php` and does not edit sprint 11; budget and board REST stays deferred.
- 2026-10-24: operator asked for a sticky order header with a Copy button on each important field, and a way to correct house number and street name when import split them wrong. Soft defaults: ship-to components stay separate from the sticker line fields; also copy buyer, town, postcode, quantity, and product name; design is editable; one editor in the header; no new personalisation type.
- 2026-10-24: the header also shows every held tray number, each with Copy. No tray shows **No tray**. Assign and release stay on the Trays panel.
- Local site **ordermachine** is this plugin folder. After a sprint branch is checked out, reload that site’s wp-admin. wp-env waits for the package prompt.
- Baseline when this file was opened: `origin/main` `963ca49`, plugin **0.35.0**, schema **1.26.0**.

---

## UP15-S1 — Read-only MCP abilities

- **Status:** not started.
- **Behaviour:** see [`02-Update-Read-Abilities.md`](02-Update-Read-Abilities.md).
- **Smoke:** `tests/sprint-up15-s1-smoke.php` — not written. wp-env not run.

## UP15-S2 — Sticky order header

- **Status:** not started.
- **Behaviour:** see [`03-Update-Order-Sticky-Header.md`](03-Update-Order-Sticky-Header.md). The bar sticks under the admin bar. Each field has Copy, including every held tray number. House number, street name, and design save through the existing line-field update. The Make line-fields form is removed. Assign and release stay on the Trays panel.
- **Smoke:** `tests/sprint-up15-s2-smoke.php` — not written. wp-env not run.
