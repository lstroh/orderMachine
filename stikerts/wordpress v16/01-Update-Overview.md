# Plugin Update Package 15 — Overview

*Update set · Read-only MCP Abilities, and a sticky order header with copy buttons. Planning open for further operator items. Soft defaults below are from 2026-10-24 and can still be corrected. Self-contained.*

---

## Assumption

- Packages **7–14** are on `origin/main`. Plugin **0.35.0** (schema **1.26.0**), tag `v0.35.0`. Package 14 passed wp-env on 2026-10-23 (pull request #61, merge `a546f67`) and was released as 0.35.0.
- `origin/main` is past that release (`963ca49` when this plan was written). The later commits are the 0.35.0 release catalog and the name-label Print Then Cut sheets. They are not unfinished Package 14 work.
- Standing architecture: `.cursor/rules/order-machine-architecture.mdc` and docs under `stikerts/wordpress/` plus the v8–v15 update packages.
- This package adds **no tables and no columns**. Schema stays **1.26.0**. There is no data-model spec.
- Plain PHP admin. SemVer stays on **`0.x.y`**. Sprint work adds an Unreleased changelog line and does not bump `Version` / `SOM_VERSION`.

## Operator requests

1. **Read-only WordPress Abilities** so an MCP client can read workflows, budgets, shipping packages, boards, trays, channels, fee estimates, recurring expenses, analytics, and the material stock log. Extend the product, listing, and order-detail abilities that already exist. Pass `per_page` through on `get-orders`.
2. **Sticky order header** with a Copy button on each important field, and a way to correct house number and street name when the import split them wrong.

## Settled behaviour

| Topic | Decision |
|---|---|
| Where | `includes/class-som-abilities.php` only, plus the docs and the new smoke. No new admin screen. No REST routes. |
| When they register | Only when Settings → MCP is on, on the existing `order-machine` category, with the existing `mcp` public/tool meta and `can_read` (`manage_options`). |
| Writes | None. Do not register a write ability. Do not add a settings ability. |
| Secrets | Never return channel `credentials`, settings secrets, API keys, step `script_config`, step `command_template`, or batch-group `script_config`. Do not call `SOM_Channels::get_credentials()`. |
| Shape | Same as today. A paginated query returns `total`, `pages`, and `paged`. A missing required id returns `WP_Error`. |
| `per_page` | `get-orders` is the callback that drops it. Pass it through, default 20, cap 100. |
| Schema | Stays **1.26.0**. |
| Boards vs the old deferral | [`FEATURES-AND-TESTING.md`](../wordpress/FEATURES-AND-TESTING.md) still lists “Budget / Board REST + Abilities” as admin-only. This package adds the **Abilities** half the operator asked for. REST for budgets and boards stays deferred. |
| Smoke | New `tests/sprint-up15-s1-smoke.php`. Do not edit `tests/sprint11-smoke.php`. That file is one of the eleven stale smokes. |
| Workflow list status | Empty status returns every template. The admin Workflows list still defaults to active. The ability must pass the empty status through so `SOM_Workflows::query()` does not apply its own active default. |
| Sticky header | Order detail only. Sticks under the WordPress admin bar. One Copy button per field, reusing the existing copy helper. |
| Ship-to vs personalisation | Ship-to stays `full_name`, `line1`, `line2`, `city`, `state`, `postcode`, `country`. House number, street name, and design stay the line fields. They are not parsed out of `line1`. A sticker address can differ from the ship-to address. |
| Correcting a bad import | House number, street name, and design edit in the header and save through `SOM_Orders::update_item_structured_fields()`. Re-sync does not overwrite those line fields. The ship-to address stays read-only because re-sync does overwrite it. |
| Which id | Copy uses the marketplace order number. A second button copies the Order Machine id. |
| Extra header fields | Buyer name, town, postcode, quantity, and product name, each with Copy. Also one Copy address and one Copy line. |
| Trays in the header | Every held tray number, each with Copy. The button copies the number. None held shows **No tray** and, when a tray is required, a link to the Trays panel. Assign and release stay on that panel. |
| Future personalisation | No new field type and no new column. Every line shows house, street, and design. Other imported aspects are copy-only chips from `personalisation_json`. |
| One editor | The Make “Line fields” form is removed. The header is the only place those four inputs are saved. |

Specs: [`02-Update-Read-Abilities.md`](02-Update-Read-Abilities.md), [`03-Update-Order-Sticky-Header.md`](03-Update-Order-Sticky-Header.md).

## Local QA site

The Local site **ordermachine** runs this plugin folder. There is no separate deploy.

- **0.35.0 cannot do this yet.** MCP, when enabled, still exposes the abilities registered today (orders, products, materials, listings, media, suppliers, purchase orders, material goals, batches, batch groups). Order detail has no sticky header. House number, street name, and design are edited inside Make, under Line fields.
- After a sprint branch is checked out in this folder, reload the Local site’s wp-admin. Turn MCP on and the new tools appear to a client that reconnects. Start the site in Local first.
- Implementation chats still do not run `npx @wordpress/env`. Automated smokes wait for the package’s final wp-env prompt.

## What's in this update

1. Read-only abilities for the gaps above, and the three existing payloads that grow (UP15-S1).
2. Sticky order header with copy buttons and line-field correction (UP15-S2).

Further operator items can still be appended before implementation starts.

## Recommended build order

1. **UP15-S1** — All of the read abilities in one sprint. They share one class, one permission callback, and one smoke.
2. **UP15-S2** — Sticky header. Independent of S1. No schema change.

## Release

Suggest **0.36.0** after S1 and **0.37.0** after S2. Do not tag until the operator asks for a release. Later sprints in this package, if any, take the next `0.x.y` and still do not tag.
