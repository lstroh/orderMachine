# Update — Read-only MCP abilities

*Package 15 · UP15-S1 · No schema. Operator request, soft defaults 2026-10-24.*

---

## 1. What this is

Register more read-only WordPress Abilities in `SOM_Abilities`, and add fields to three abilities that already exist.

Follow the class as it is. Register only when `SOM_Abilities::is_enabled()` is true and `wp_register_ability` exists. Category stays `order-machine`. Meta stays `mcp.public = true`, `mcp.type = tool`. Permission callback stays `can_read` (`manage_options`).

Extend the category description so it also names workflows, budgets, boards, trays, channels, and analytics. It stays a read-only description.

Do not add write abilities. Do not add a settings ability. Do not call `SOM_Settings::get()` from a new callback except the existing `is_enabled()` check. Do not call `SOM_Channels::get_credentials()`.

## 2. Secrets

Never put these keys on any ability payload, including nested board cards, workflow steps, and make-progress rows:

| Key | Why |
|---|---|
| `credentials` | Channel OAuth blob. `get_by_slug()` is `SELECT *` and must not be used for the channel list. |
| `script_config` | Step and batch-group column. `get-batch-groups` already omits it. Leave that ability as it is. |
| `command_template` | Step column. `get_steps()`, `get_progress()`, and `get_item_progress()` select it. Drop it when mapping. |
| `client_secret` | Settings. No settings payload. |
| `api_key` | Settings. No settings payload. |
| `raw_payload` | Marketplace blob on the order row. Pack and Post-ship board queries select `o.*`. Drop this key. Do not add it to `get-order-detail`. |

A recursive unset of those keys is the backstop. The callbacks still project the fields listed below, so a new secret column on `SELECT *` does not ride along on the shaped payloads.

Instruction text, order-note bodies, and task labels may contain those words. The smoke checks **keys**, not substrings inside text.

`SOM_Orders::get()` and `SOM_Item_Make::get_item_progress()` already call the timer-unlock helpers. `get-order-detail` already loads the order through `SOM_Orders::get()`. Keep using those methods. That existing unlock is not a new write ability.

## 3. Shared response rules

- List abilities whose query returns `total`, `pages`, and `paged` include those three keys.
- Board queries do not paginate. Return `total`, `capped`, and `warn`. Post-ship also returns `scope`. Do not invent `pages` or `paged`.
- `per_page` on a new list ability: default the method’s own default, cap at **100** (`max(1, min(100, …))`).
- A required id of 0, or an id `get()` does not find, returns `WP_Error`. Do not return an empty success object.
- `get-workflow-material-goals` is unchanged.

### `per_page` on abilities that already exist

`get-orders` accepts `per_page` in the schema (default 20) and does not pass it to `SOM_Orders::query()`, so the query uses `SOM_Orders::PER_PAGE` (20) no matter what the client sends. Pass `per_page` through with the same cap of 100.

The other list callbacks already pass `per_page`. Leave their caps as they are.

## 4. New abilities

### 4.1 Workflow templates

`order-machine/get-workflow-templates` calls `SOM_Workflows::query()`.

| Input | Meaning |
|---|---|
| `status` | `active`, `inactive`, or empty. Empty means all. Default **empty**. Always pass the key, including when it is empty, so the query does not substitute its active default. |
| `kind` | `make`, `pack`, `post_ship`, or empty for all. |
| `s` | Search, as the query already does. |
| `paged` | Default 1. |
| `per_page` | Default 20 (`SOM_Workflows::PER_PAGE`), cap 100. |

Each template: `id`, `name`, `description`, `kind`, `uses_trays`, `output_per_run`, `is_active`, `step_count`, `product_count`. `step_count` and `product_count` are the counts the query already selects. `product_count` is products whose **make** template is this id. It does not count Pack or Post-ship overrides.

Payload: `templates`, `total`, `pages`, `paged`.

`order-machine/get-workflow-template` requires `workflow_template_id` and calls `SOM_Workflows::get()`. Missing template: `WP_Error` `som_workflow_template_missing`.

Include:

- The same template fields as the list row, plus `description`.
- `products`: `SOM_Workflows::get_assigned_products()` mapped to `id` and `name` only. That method is make-template assignment. Do not add a Pack or Post-ship product list.
- `steps`: `get_steps()` in `step_order`. Each step is `id`, `step_order`, `name`, `requires_manual_confirm`, `timer_seconds`, `planned_seconds`, `batch_group_id`, `confirmation_kind`, `instructions`, and `tasks`. `tasks` is `SOM_Step_Tasks::decode_list()` of `task_list_json` (`id`, `label`). Omit `script_config`, `command_template`, and the raw JSON.

### 4.2 Budgets

`order-machine/get-budgets` calls `SOM_Budgets::query()`.

| Input | Meaning |
|---|---|
| `s` | Name search. |
| `type` | `material`, `manual`, or empty for both. |
| `status` | `active` (default, matching the Budgets screen), `inactive`, or `all`. Map to the query’s `is_active` the way `admin/views/budgets-list.php` does. |
| `paged` | Default 1. |
| `per_page` | Default 20, cap 100. |

Each budget row is the budget columns `get()` / `query()` already return: `id`, `name`, `type`, `material_id`, `funding_method`, `funding_value`, `target_reserve_amount`, `current_balance`, `notes`, `is_active`, `created_at`, `updated_at`.

Payload: `budgets`, `total`, `pages`, `paged`.

`order-machine/get-budget` requires `budget_id`. Missing: `WP_Error` `som_budget_missing`.

Include `SOM_Budgets::get()`, `get_ledger()` at the method default of 50 rows (no ledger-limit input), `get_product_link_ids()`, and `get_workflow_link_ids()`.

### 4.3 Shipping packages

`order-machine/get-shipping-packages` calls `SOM_Shipping_Packages::query()`.

| Input | Meaning |
|---|---|
| `s` | Name search. |
| `status` | `all` (default, matching the packages screen), `active`, or `inactive`. |
| `paged` | Default 1. |
| `per_page` | Default 20, cap 100. |

Each package: `id`, `name`, `length_mm`, `width_mm`, `height_mm`, `tare_weight_grams`, `is_active`, `is_default`.

Payload: `packages`, `total`, `pages`, `paged`.

`order-machine/get-shipping-package` requires `shipping_package_id`. Missing: `WP_Error` `som_shipping_package_missing`.

Include `SOM_Shipping_Packages::get()` and `get_materials()`. Each material line: `id`, `material_id`, `material_name`, `material_unit`, `quantity_per_package`.

### 4.4 Boards

Accept the filters the method already supports. Do not add pagination.

`order-machine/get-make-board` calls `SOM_Orders::query_make_board()`.

| Input | Meaning |
|---|---|
| `channel` | Channel slug, as the query already filters. |
| `product_id` | Integer. |
| `workflow_template_id` | Integer. |
| `s` | Search. |

The query already calls `SOM_Trays::attach_board_tray_numbers()`. Return those `tray_numbers`. Do not attach them a second time.

Return `orders`, `total`, `capped`, `warn`, and `workflow_steps`. Project `workflow_steps` with the step shape in §4.1 (so `script_config` and `command_template` are absent). Card fields are the fields the query already put on each card, minus the secret keys in §2.

`order-machine/get-pack-board` calls `SOM_Pack::query_board()`.

| Input | Meaning |
|---|---|
| `channel` | Slug. Internal is already excluded by the query. |
| `s` | Search. |

Return `orders`, `total`, `capped`, `warn`. The query already attaches tray numbers. Leave them on the card. There is no `workflow_steps` key on this query. Drop `raw_payload`.

`order-machine/get-post-ship-board` calls `SOM_Post_Ship::query_board()`.

| Input | Meaning |
|---|---|
| `scope` | `active` (method default), `unstarted`, or `complete`. Pass it through. An unknown value stays the method’s active default. |
| `channel` | Slug. |
| `workflow_template_id` | Integer. |
| `s` | Search. |

Return `orders`, `total`, `capped`, `warn`, `scope`, and `workflow_steps` using the §4.1 step shape. Drop `raw_payload`. This query does not attach trays. Do not add them.

### 4.5 Trays

`order-machine/get-trays` takes no filters.

| Key | Source |
|---|---|
| `max` | `SOM_Trays::get_max()` |
| `held` | `SOM_Trays::held_tray_numbers()`, as a list of `{ tray_number, order_id }` sorted by tray number |
| `free` | `SOM_Trays::free_tray_numbers()` |

This is not a Trays overview page. That page stays deferred.

### 4.6 Channels, fees, and recurring expenses

`order-machine/get-channels` lists channel rows. There is no list method that omits `credentials`. `get_by_slug()` selects every column, so do not use it. Select only `id`, `slug`, `display_name`, `is_active`, `last_synced_at`, ordered by `id`. The `credentials` column is not in the query. Do not call `get_credentials()`. No pagination. Payload: `channels`.

`order-machine/get-channel-fee-estimates` calls `SOM_Channel_Fee_Estimates::list_grouped_by_channel()`. Return `channels` as a list of `{ channel_id, estimates }`. Each estimate keeps the row fields (`id`, `channel_id`, `fee_component`, `rate_type`, `rate_value`, `order_value_min`, `order_value_max`, `is_enabled`, `notes`, timestamps). No credentials column exists on that table.

`order-machine/get-recurring-platform-expenses` calls `SOM_Platform_Fee_Sync::list_recurring()`.

| Input | Meaning |
|---|---|
| `listing_id` | Optional. |
| `channel_id` | Optional. |
| `limit` | Method default 100. Cap 100. |
| `offset` | Method default 0. |

The method returns `rows` and `total`, not pages. Return `expenses`, `total`, `limit`, and `offset`. Do not invent `pages`. The join already adds `channel_name`, `channel_slug`, `listing_title`, and `external_listing_id`. It does not select `credentials`.

### 4.7 Analytics

`order-machine/get-analytics` calls `SOM_Analytics::parse_filters()` and then `SOM_Analytics::dashboard_payload()`.

`order-machine/get-analytics-timing` uses the same filters and calls `SOM_Analytics::timing_payload()`.

Input keys are the admin screen’s names:

| Input | Meaning |
|---|---|
| `som_range` | `7`, `30` (default), `90`, `year`, `custom`. |
| `som_date_from`, `som_date_to` | Used when the range is custom. `Y-m-d`, as `parse_filters()` already accepts. |
| `som_granularity` | `daily` (default), `weekly`, `monthly`. |
| `som_channel` | Channel id. `0` is every channel. |
| `som_materials` | Array of material ids. |
| `som_timing_kind` | `make` (default) or `pack`. |
| `som_timing_template` | Template id. `0` lets `timing_payload()` keep its current fallback. |

Return the payload arrays those methods already build. They do not contain credentials. `dashboard_payload()` already includes a `timing` section. Leave it. The timing ability is the timing section on its own.

### 4.8 Material stock log

`order-machine/get-material-stock-log` requires `material_id`.

Call `SOM_Materials::get()` first. Missing material: `WP_Error` `som_material_missing`. Then `SOM_Materials::get_stock_log()`.

| Input | Meaning |
|---|---|
| `limit` | Default **10** (the method default). Cap **100**. |

Return `material_id` and `entries`. Each entry is the log row the method returns, including `external_order_id`. No secret columns are on that log.

## 5. Abilities that already exist

### `get-products`

Keep the current fields. Add, from the product row `SOM_Products::query()` already selects:

| Field | Source |
|---|---|
| `workflow_template_name` | The joined `workflow_name` (the make template). Null when the product has no make template. |
| `pack_workflow_template_id` | Column. Null when unset. |
| `post_ship_workflow_template_id` | Column. Null when unset. |
| `weight_grams` | Column. |
| `package_id` | Column. |
| `package_id_multi` | Column. |
| `planned_shipping_gbp` | Column. |
| `step_instruction_overrides` | `SOM_Step_Instructions::get_overrides_for_product()`. Map of step id to instruction text. |
| `step_task_overrides` | `SOM_Step_Tasks::get_overrides_for_product()`. Map of step id to `{ id, label }` tasks. |

Do not add Pack or Post-ship template names. The operator asked for those ids, and for the make template’s name.

### `get-listings`

Add `description` and `last_synced_at` from the listing row. Keep the current fields. Do not add `inventory_json`. Do not add anything from the channel row except the slug and names already returned.

### `get-order-detail`

Keep the current order, item, and progress fields. Add, from the order `SOM_Orders::get()` already loads:

| Field | Source |
|---|---|
| `pack_workflow_template_id` | Order column. Null when unset. |
| `post_ship_workflow_template_id` | Order column. Null when unset. |
| `post_ship_started_at` | Order column. |
| `pack_hold_reason` | Order column. |
| `shipping_package_id` | Order column. Null when unset. |
| `tray_numbers` | `SOM_Trays::held_for_order()`, the `tray_number` values only, ascending. |
| `notes` | `SOM_Order_Notes::list_for_order()`. Each note is `id`, `body`, `created_at`, `user_id`. Do not add `author_name`. |

On each item, add `make_progress` from the rows `SOM_Orders::get()` already attached via `SOM_Item_Make::get_item_progress()` (`$item->make_progress`). Map each row to its progress fields and the joined step fields, including `workflow_template_id` when the step row has it. Omit `script_config` and `command_template`.

On each `workflow_progress` row, add `workflow_template_id` when `SOM_Workflow_Engine::get_progress()` has it on the step (it joins `s.workflow_template_id`). Keep the fields the ability already returns. Do not start returning `script_config` or `command_template`.

## 6. Smoke

`tests/sprint-up14-s12-smoke.php` is the style source for the file header, the `ABSPATH` guard, and `wp eval-file` only. Name this file `tests/sprint-up15-s1-smoke.php`.

Do not edit `tests/sprint11-smoke.php`.

The smoke:

1. Turns MCP on the same way sprint 11 does, then calls `wp_get_ability()` for `get-workflow-templates`, `get-budgets`, `get-shipping-packages`, `get-channels`, and `get-analytics` when that function exists. Callbacks are still invoked directly, as sprint 11 invokes `get_orders`.
2. Calls those five with no extra filters and asserts each returns an array (analytics returns the dashboard payload; the lists return their list key plus `total` where the query paginates). Channels after `SOM_Channels::ensure_rows()` has at least one row.
3. Walks those payloads and asserts no array key is `credentials`, `script_config`, `command_template`, `client_secret`, or `api_key`.
4. Calls `get-orders` with `per_page` 1. Asserts the returned order count is at most 1. When a matching `SOM_Orders::query()` with `per_page` 1 reports `total` >= 2, the ability returns exactly one order. If the database has fewer than two orders, create two minimal external orders the same way `tests/sprint11-smoke.php` creates one, then re-check. Do not change sprint 11.
5. Calls `get-workflow-template`, `get-budget`, and `get-shipping-package` with id `0` and asserts `WP_Error`.

A site with no budgets or packages still passes the list calls: an empty list with `total` 0 is data. Analytics with no orders still returns a payload.

## 7. Docs in this sprint

- `CHANGELOG.md` under **Unreleased**. One line. Do not bump `Version` or `SOM_VERSION`.
- `MCP.md` ability list, so the new names sit with the ones already listed. That file is behind the code today (it stops at media). Add the abilities registered since then as well as the Package 15 names, still read-only, still “credentials never exposed”.
- `FEATURES-AND-TESTING.md` §3.12, and the deferred row “Budget / Board REST + Abilities”: Abilities are this sprint; REST for those screens stays deferred.
- `USER-REFERENCE.md` §16 stays a pointer at `MCP.md`. One sentence is enough if the section still says abilities are read-only and credentials stay hidden.

## 8. Out of this sprint

- Write abilities, REST routes, and any change to admin screens.
- Schema, `SOM_DB::DB_VERSION`, `Version`, `SOM_VERSION`, and a release tag.
- `get-workflow-material-goals`, Unblock on boards, the Trays overview page, and the eleven stale smokes.
- Rewriting `07-MCP-Integration.md`. The read-only rule in that doc stays. This sprint fills read gaps. It does not start the “write access later” section.
