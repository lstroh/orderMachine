# Update Package 15 — Sprint Plan

*Planning open for further operator items. Soft defaults are from 2026-10-24. Specs in this folder. Baseline: plugin **0.35.0**, schema **1.26.0**, `origin/main` (`963ca49` when this plan was written; tag `v0.35.0`).*

**Sequencing:** Read-only abilities first (UP15-S1), then the sticky order header (UP15-S2). S2 does not depend on S1. No schema change. Further operator items append as UP15-S3 onward and do not reopen these sprints.

## Locked decisions (operator, 2026-10-24)

- Read-only abilities only. Register only when MCP is enabled, on category `order-machine`, with the existing `mcp` public/tool meta and `can_read` (`manage_options`).
- No write abilities. No settings ability. Do not call `SOM_Channels::get_credentials()`.
- Never return `credentials`, `script_config`, `command_template`, `client_secret`, `api_key`, or order `raw_payload`.
- Use the existing query and get methods. The channel list is the exception: no existing method omits `credentials`, so the ability selects `id`, `slug`, `display_name`, `is_active`, `last_synced_at` only.
- `get-orders` passes `per_page` through (default 20, cap 100). New list abilities cap `per_page` at 100. Boards stay capped by the existing board query, not by pages.
- A missing required id returns `WP_Error`.
- `get-workflow-material-goals` stays as it is. `get-batch-groups` stays as it is (it already omits `script_config`).
- Empty workflow `status` means all templates. Pass that empty value through.
- Budget list defaults to active. Shipping-package list defaults to all. Both match the admin screens.
- No Version bump inside a sprint. Unreleased changelog line only. Schema stays **1.26.0**.
- New smoke: `tests/sprint-up15-s1-smoke.php`. Do not edit `tests/sprint11-smoke.php` or the other ten stale smokes.
- Budget and board **Abilities** are in this package. Budget and board **REST** stays deferred, as does the rest of the planning-brief deferral list (Product Costing packaging line, Pack Copy UI, Unblock on boards, Trays overview, hard-delete of script dispatch, customer orders that contain only internal products).
- Order detail gets a sticky header. Ship-to components, the line’s house number, street name, and design, and each held tray number have a Copy button. House, street, and design are corrected in that header through the existing line-field save. The ship-to address is not edited. Assign and release stay on the Trays panel. No new personalisation type. See [`03-Update-Order-Sticky-Header.md`](03-Update-Order-Sticky-Header.md).

---

## 1. Open items

| # | Item | Blocks | Decision |
|---|---|---|---|
| O1 | Ability surface | UP15-S1 | **Soft default** — [`02-Update-Read-Abilities.md`](02-Update-Read-Abilities.md). One sprint. |
| O2 | Channel list has no safe query method | UP15-S1 | **Soft default** — five-column select inside the ability. Do not call `get_by_slug()` or `get_credentials()`. |
| O3 | Workflow status default | UP15-S1 | **Soft default** — empty means all, unlike the admin list. |
| O4 | Budget status default | UP15-S1 | **Soft default** — active, matching the Budgets screen. |
| O5 | Board `raw_payload` | UP15-S1 | **Soft default** — omit. Buyer name on the card stays. |
| O6 | Smoke file | UP15-S1 | **Soft default** — new UP15 smoke. Leave sprint 11 stale. |
| O7 | FEATURES deferral “Budget / Board REST + Abilities” | UP15-S1 | **Soft default** — Abilities now, REST still deferred. |
| O8 | Ship-to vs sticker text | UP15-S2 | **Soft default** — do not parse `line1`. House and street stay line fields. |
| O9 | Extra header fields | UP15-S2 | **Soft default** — also buyer, town, postcode, quantity, product name, Copy address, Copy line, and every held tray number. Marketplace id is the main Copy. |
| O10 | Design editable | UP15-S2 | **Soft default** — yes. Same save as house and street. Column is `style`. |
| O11 | One editor | UP15-S2 | **Soft default** — remove the Make line-fields form. |
| O12 | Future personalisation | UP15-S2 | **Soft default** — no new type. Extra JSON aspects are copy-only chips. |
| O13 | Sticky height | UP15-S2 | **Soft default** — sticks under the admin bar. More than three lines scroll inside the bar. |

Further operator items can still be appended.

---

## 2. Spec ↔ codebase

| Area | Today (`includes/class-som-abilities.php`) | Package 15 |
|---|---|---|
| Registered tools | Orders, order detail, products, materials, listings, media, suppliers, purchase orders, material goals, batches, batch groups | Those, plus the tools in `02` §4 |
| `get-orders` | Schema has `per_page`. Callback omits it | Pass `per_page`, cap 100 |
| `get-products` | Recipe and costing. Make template id only | Also make template name, pack and post-ship template ids, weight, both package ids, planned shipping, step instruction overrides, step task overrides |
| `get-listings` | Id, product, channel, title, price, quantity | Also `description` and `last_synced_at` |
| `get-order-detail` | Items, address, shipment, order-level progress | Also pack and post-ship template ids, post-ship start, pack hold, shipping package, tray numbers, notes, per-item make progress, `workflow_template_id` on progress |
| Workflows, budgets, packages, boards, trays, channels, fees, recurring expenses, analytics, stock log | Not abilities | Read-only abilities in §4 of the spec |
| Secrets | Credentials are not on current payloads. Step queries still select `script_config` and `command_template` | Projected fields. Those keys never leave the ability |
| Order detail header | Page title scrolls away. Personalisation is a text list. Address is one block. House, street, and design are a disclosure inside Make | Sticky bar. Per-field Copy. Those three inputs move to the bar |
| Schema | **1.26.0** | **1.26.0** |

---

## 3. Sprints

### UP15-S1 — Read-only MCP abilities

No dependency on another Package 15 sprint.

| Work | Detail |
|---|---|
| Register | The abilities in [`02-Update-Read-Abilities.md`](02-Update-Read-Abilities.md) §4, plus the field adds in §5, plus `per_page` on `get-orders` |
| Secrets | Allowlists in the spec. Recursive unset of the six keys in §2 as a backstop |
| Channels | Five-column select. No `get_credentials()` |
| Tests | `tests/sprint-up15-s1-smoke.php` |
| Docs | `MCP.md`, FEATURES-AND-TESTING §3.12 and the REST deferral row, USER-REFERENCE §16 if a sentence is needed, CHANGELOG Unreleased |

**Done when:** with MCP enabled, `get-workflow-templates`, `get-budgets`, `get-shipping-packages`, `get-channels`, and `get-analytics` return data; a missing workflow, budget, or package id returns `WP_Error`; `get-orders` with `per_page` 1 returns at most one order; and no returned payload has a `credentials` or `script_config` key.

**Suggested SemVer after merge:** `0.36.0`.

---

### UP15-S2 — Sticky order header

No dependency on UP15-S1.

| Work | Detail |
|---|---|
| Header | Sticky bar on order detail, under the admin bar. Spec [`03-Update-Order-Sticky-Header.md`](03-Update-Order-Sticky-Header.md) |
| Copy | One button per field, reusing `initCopyText()`. Inputs copy the current value |
| Edit | House number, street name, and design save through `update_item_structured_fields()`. Remove the Make line-fields form |
| Tests | `tests/sprint-up15-s2-smoke.php` |
| Docs | USER-REFERENCE order detail, FEATURES-AND-TESTING order-detail row, CHANGELOG Unreleased |

**Done when:** the bar stays under the admin bar while the page scrolls; each listed field has a Copy button, including every held tray number; saving a corrected house number and street name stores them; the Make panel no longer has that form.

**Suggested SemVer after merge:** `0.37.0`.

---

## 4. After the last sprint

The package wp-env prompt runs every Package 15 smoke, records the pass in [`Update-15-Sprint-Progress.md`](Update-15-Sprint-Progress.md), and does not bump the version or tag a release. It does not rewrite the eleven stale smokes.

The eleven stale smokes stay as recorded in `stikerts/wordpress v13/Update-12-Sprint-Progress.md`: bugfix-001-002, shipments, sprint11, sprint-u1, sprint-u5, sprint-u6, sprint-u7, sprint-up6-s3, sprint-up8-s2, sprint-up10-s1, step-confirmations.
