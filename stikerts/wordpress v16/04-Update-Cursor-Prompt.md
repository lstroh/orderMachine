# Update Package 15 — Cursor kickoff prompt

*Use when the operator asks to start a sprint. Do not implement from planning alone.*

## Locked model

- Read-only abilities in `includes/class-som-abilities.php`. Register only when MCP is on, category `order-machine`, existing `mcp` public/tool meta, `can_read` = `manage_options`.
- No write abilities. No settings ability. No new tables, columns, or admin screens. Schema stays **1.26.0**. No Version bump.
- Never return `credentials`, `script_config`, `command_template`, `client_secret`, `api_key`, or `raw_payload`. Do not call `SOM_Channels::get_credentials()`. Do not use `SOM_Channels::get_by_slug()` for the channel list.
- `get-orders` passes `per_page` (default 20, cap 100).
- Missing required ids return `WP_Error`.
- Empty workflow status means all templates. Pass the empty status through.
- Leave `get-workflow-material-goals` and `get-batch-groups` as they are.
- Plain PHP. SemVer `0.x.y` alpha.
- UP15-S2: sticky order header. Copy each field, including every held tray number. Correct house number, street name, and design on the line. Do not edit or split the ship-to address. Do not move assign or release into the header. No new column.

## Rules

- Read `.cursor/rules/order-machine-architecture.mdc`, [`01-Update-Overview.md`](01-Update-Overview.md), [`Update-15-Sprint-Plan.md`](Update-15-Sprint-Plan.md), and [`02-Update-Read-Abilities.md`](02-Update-Read-Abilities.md).
- One sprint per chat. Branch from latest `origin/main`. Do not continue a previous sprint branch.
- Do not start the next sprint.
- Do not run `npx @wordpress/env`.
- The Local site **ordermachine** uses this plugin folder. After the code is saved here, the operator can reload that site’s wp-admin, turn MCP on, and try the tools. Implementation still opens a PR and does not merge. Automated smokes wait for the package wp-env prompt.

## Sprints

| Sprint | Focus |
|---|---|
| UP15-S1 | Read-only MCP abilities and the three existing payload extensions |
| UP15-S2 | Sticky order header with per-field Copy and line-field correction (after S1 is on main) |

The operator is still adding items. Do not start a sprint until they ask.

## Kickoff snippet — UP15-S1 (copy into the implementation chat)

```
Implement Order Machine UP15-S1 only (Package 15). Do not start a later sprint.

Branch from latest origin/main. Do not continue a previous sprint branch.

Read:
- .cursor/rules/order-machine-architecture.mdc
- stikerts/wordpress v16/01-Update-Overview.md
- stikerts/wordpress v16/02-Update-Read-Abilities.md
- stikerts/wordpress v16/Update-15-Sprint-Plan.md (locked decisions and the UP15-S1 section)
- includes/class-som-abilities.php

Locked behaviour:
- Register the new read-only abilities in 02 §4, and extend get-products, get-listings, and get-order-detail as in 02 §5.
- Register only when MCP is enabled, on category order-machine, with the existing mcp public/tool meta and can_read (manage_options).
- Pass per_page through on get-orders (default 20, cap 100).
- A missing workflow_template_id, budget_id, shipping_package_id, or material_id returns WP_Error.
- Empty workflow status means all templates. Always pass status through, including when it is empty.
- Channel list selects id, slug, display_name, is_active, last_synced_at only. Do not call SOM_Channels::get_credentials() or get_by_slug().
- Never return credentials, script_config, command_template, client_secret, api_key, or raw_payload.
- Leave get-workflow-material-goals and get-batch-groups unchanged.
- No new tables, columns, admin screens, or REST routes. Schema stays 1.26.0. Do not bump Version or SOM_VERSION.

Out of scope:
- Write abilities, settings abilities, budget/board REST, the Trays overview page, Unblock on boards.
- Do not run npx @wordpress/env. Do not edit tests/sprint11-smoke.php or the other stale smokes.

Tests:
- Write tests/sprint-up15-s1-smoke.php in the style of tests/sprint-up14-s12-smoke.php (ABSPATH guard, run only via wp eval-file).
- Cover registration when MCP is on, and a direct read of get-workflow-templates, get-budgets, get-shipping-packages, get-channels, and get-analytics.
- Assert those payloads have no credentials, script_config, command_template, client_secret, or api_key key.
- Assert get-orders per_page 1 returns at most one order, and exactly one when the matching query total is at least 2.
- php -l on changed PHP. node --check on changed JavaScript, if any.

Docs:
- CHANGELOG.md Unreleased line only.
- MCP.md ability list. FEATURES-AND-TESTING.md §3.12 and the budget/board REST deferral row. USER-REFERENCE.md §16 only if a sentence is needed.

PR:
- Open a focused PR. Do not merge it.
- Say that the Local site ordermachine serves this plugin folder, so the operator can reload wp-admin and try the tools with MCP on.
```

## Kickoff snippet — UP15-S2 (copy into the implementation chat)

```
Implement Order Machine UP15-S2 only (Package 15). Do not start a later sprint.

Branch from latest origin/main. Do not continue a previous sprint branch.

Read:
- .cursor/rules/order-machine-architecture.mdc
- stikerts/wordpress v16/01-Update-Overview.md
- stikerts/wordpress v16/03-Update-Order-Sticky-Header.md
- stikerts/wordpress v16/Update-15-Sprint-Plan.md (locked decisions and the UP15-S2 section)
- admin/views/order-detail.php
- admin/assets/js/admin.js (initCopyText)
- SOM_Orders::update_item_structured_fields()

Locked behaviour:
- Order detail has a sticky header under the WordPress admin bar (top 32px, 46px below 782px).
- Each field in 03 §4 has its own Copy button. Reuse initCopyText(). An input copies its current value. Empty does nothing. Keep the Copied label and the prompt fallback.
- Ship-to shows the stored components (name, line 1, line 2, town, county, postcode, country) plus Copy address. Do not parse line 1 into house number and street name. Do not edit the ship-to address.
- Each order line shows product, quantity, house number, street name, and design. Design is the style column. Save uses the existing som_save_line_fields path and update_item_structured_fields(). Blank clears the column. Do not change personalisation_text or personalisation_json from this form.
- The bar shows every held tray number from SOM_Trays::held_numbers_for_order(), each with Copy. The button copies the number only. No held tray shows "No tray" and no tray copy button. When a tray is required, that text links to #som-trays. Do not put assign or release in the bar.
- Extra personalisation_json keys that are not house, street, or design are copy-only chips.
- Remove the Make "Line fields" form. The make line still shows the personalisation snippet.
- More than three lines scroll inside the bar.
- No new tables or columns. Schema stays 1.26.0. Do not bump Version or SOM_VERSION.

Out of scope:
- MCP abilities, a per-product personalisation type, copy buttons on the boards, editing the ship-to address.
- Do not run npx @wordpress/env. Do not rewrite the eleven stale smokes.

Tests:
- Write tests/sprint-up15-s2-smoke.php in the style of tests/sprint-up14-s12-smoke.php (ABSPATH guard, run only via wp eval-file).
- Assert the bar has copy controls for the external order id, postcode, house number, street name, design, and each held tray number. An order with no tray shows "No tray" and no tray copy button.
- Assert the Make markup no longer posts som_line[house_number].
- Assert a saved house number and street name are stored, and two lines render two blocks.
- php -l on changed PHP. node --check on changed JavaScript.

Docs:
- CHANGELOG.md Unreleased line only.
- USER-REFERENCE order detail, and the FEATURES-AND-TESTING order-detail row.

PR:
- Open a focused PR. Do not merge it.
- Say that the Local site ordermachine serves this plugin folder, so the operator can reload wp-admin and try the order screen.
```
