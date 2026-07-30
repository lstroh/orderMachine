# Order Machine — Sprint Progress

*Companion to [`Sprint-Plan.md`](Sprint-Plan.md). Plan stays the source of scope; this file records what shipped and how it was verified.*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| 0 | Env / Cursor setup | Done | Rules, wp-env, PHPCS scaffold, Sprint-Plan |
| 1 | Foundation | Done | Verified on wp-env; Local activation not yet confirmed by agent |
| 2 | Channel connection | Done | Verified on wp-env (dummy credentials + settings + cron) |
| 3 | Order sync | Done | Verified on wp-env (fixtures + de-dup + listing match) |
| 4 | Orders list + detail | Done | Verified on wp-env (filters, badges, detail) |
| 5 | Products + materials | Done | Verified on wp-env (CRUD, recipe, stock adjust, seed) |
| 6 | Workflow templates + step editor | Done | Verified on wp-env (CRUD, timers, script_config, seed) |
| 7 | Workflow engine (manual + timer) | Done | Verified on wp-env (assign, Mark done, timer unlock, script pass-through) |
| 8 | Material auto-decrement | Done (scoped) | Decrement + UI shipped; cancel reversal deferred (D3/A3) |
| 9+ | Later phases | Not started | Script / API / n8n steps |

---

## Sprint 0 — Env / Cursor setup

- **Status:** Done
- **Completed:** July 2026 (planning pass)

### Delivered

- `stikerts/wordpress/Sprint-Plan.md`
- `.cursor/rules/order-machine-architecture.mdc`
- `.cursor/rules/wordpress-php-standards.mdc`
- `.wp-env.json`
- `.editorconfig`
- `phpcs.xml.dist`
- Local vs wp-env usage documented (later also in `WP-ENV.md`)

---

## Sprint 1 — Foundation

- **Status:** Done
- **Roadmap phase:** 1
- **Completed:** 2026-07-28
- **Verified on:** wp-env (dev site `http://localhost:8888`)

### Decisions applied during build

| Topic | Decision |
|---|---|
| Admin menu label | **Order Machine** (plan default) |
| Uninstall behaviour | Keep tables and `som_db_version` (no destructive cleanup) |
| PHP target | 8.2+ (per plan / wp-env) |

### Files delivered

| File | Purpose |
|---|---|
| `orderMachine.php` | Plugin headers, activation/deactivation hooks, bootstrap |
| `includes/class-som-db.php` | `dbDelta` schema for all `{$wpdb->prefix}som_*` tables; `som_db_version` option |
| `uninstall.php` | Present; intentionally does **not** drop tables/options |
| `admin/class-som-admin-menu.php` | Top-level “Order Machine” menu stub (placeholder until Sprint 4) |

Also related (tooling, not Sprint 1 application code):

- `WP-ENV.md` — how to start/stop/destroy wp-env
- `.gitignore` — local/dependency/cache noise

### Schema created

`som_db_version` = `1.0.0`

Tables verified in wp-env:

- `wp_som_channels`
- `wp_som_materials`
- `wp_som_workflow_templates`
- `wp_som_products`
- `wp_som_product_materials`
- `wp_som_listings`
- `wp_som_workflow_steps`
- `wp_som_orders`
- `wp_som_order_items`
- `wp_som_order_step_progress`
- `wp_som_material_stock_log`

### Done-when checklist (from Sprint-Plan)

| Criterion | Result |
|---|---|
| Plugin activates on wp-env | Pass — active as `orderMachine` v0.1.0 |
| All `{$wpdb->prefix}som_*` tables exist | Pass — all 11 tables present |
| Deactivation does not destroy data | Pass — tables and `som_db_version` remained after deactivate; reactivate OK |
| Plugin activates on Local | Pending manual check in Local wp-admin |

### Open items

- P1 / P2 (plugin type / multisite) — already settled in Sprint-Plan; no blockers for Sprint 1.

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin list --name=orderMachine
npx @wordpress/env run cli wp option get som_db_version
npx @wordpress/env run cli wp db query "SHOW TABLES LIKE 'wp_som_%';"
npx @wordpress/env run cli wp plugin deactivate orderMachine
# tables + option still present
npx @wordpress/env run cli wp plugin activate orderMachine
```

Admin: http://localhost:8888/wp-admin — `admin` / `password`

---

## Sprint 2 — Channel connection

- **Status:** Done
- **Roadmap phase:** 2
- **Completed:** 2026-07-28
- **Verified on:** wp-env (dev site `http://localhost:8888`)

### Plan requirements review (`Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| `includes/class-som-channel-ebay.php` — OAuth + token storage | Done | Authorize URL, code exchange, proactive refresh, encrypted store |
| `includes/class-som-channel-etsy.php` — OAuth PKCE + token storage | Done | PKCE challenge/verifier, code exchange, rotating refresh tokens |
| `includes/class-som-cron.php` — `som_refresh_tokens` | Done | Scheduled (~30 min); skips dummy credentials |
| `admin/views/settings.php` — connect, n8n URL, polling fields | Done | Plus eBay/Etsy app fields, disconnect, callback URLs |
| Credential encrypt/decrypt helpers | Done | Shared `includes/class-som-crypto.php` (`som1:` AES-256-CBC) |
| **Done when:** Settings page loads | Pass | `Order Machine → Settings` (`som-settings`) |
| **Done when:** OAuth stores encrypted JSON in `credentials` | Pass | Storage path + encrypt round-trip verified on wp-env; live Connect deferred (no developer apps yet) |
| **Done when:** Dummy credentials in wp-env seed | Pass | `SOM_USE_DUMMY_CREDENTIALS` → encrypted dummy tokens on ebay/etsy |
| Open item: developer app credentials | Deferred | Not required for dummy path; user will add apps before real Local OAuth |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Credential encryption | AES-256-CBC via `SOM_Crypto`; prefers `SOM_ENCRYPTION_KEY`, else `wp_salt( 'auth' )` |
| `SOM_ENCRYPTION_KEY` | Set in Local `wp-config.php` (outside this repo) and separately in `.wp-env.json` (dev key) |
| App secrets in settings | Client secrets encrypted in `som_settings`; blank password fields keep existing values |
| Token refresh cron | `som_refresh_tokens` every 30 min by default (configurable); skips `dummy` credentials |
| Dummy credentials | Auto-seed when `SOM_USE_DUMMY_CREDENTIALS` is true (set in `.wp-env.json`) |
| Plugin version | Bumped to `0.2.0` |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-crypto.php` | Encrypt/decrypt helpers for credentials and secrets |
| `includes/class-som-settings.php` | `som_settings` option (n8n URL, poll/refresh intervals, app keys) |
| `includes/class-som-channels.php` | Ensure ebay/etsy rows; encrypted credential get/set/disconnect |
| `includes/class-som-channel-ebay.php` | eBay OAuth authorize + code exchange + proactive refresh |
| `includes/class-som-channel-etsy.php` | Etsy OAuth PKCE + code exchange + refresh (rotating refresh tokens) |
| `includes/class-som-cron.php` | Registers `som_refresh_tokens` (+ schedule placeholders for later sync) |
| `includes/seed/class-som-seed.php` | Dummy encrypted channel credentials for wp-env |
| `admin/views/settings.php` | Settings UI: connect buttons, n8n URL, polling fields |
| `admin/class-som-admin-menu.php` | Settings submenu + OAuth connect/callback/disconnect handlers |
| `orderMachine.php` | Bootstrap wiring, activation schedules cron + seed |
| `.wp-env.json` / `WP-ENV.md` | `SOM_ENCRYPTION_KEY` + dummy-cred docs for wp-env |

### Done-when checklist (from Sprint-Plan)

| Criterion | Result |
|---|---|
| Settings page loads | Pass — `Order Machine → Settings` (`som-settings`) |
| OAuth can store encrypted JSON in `wp_som_channels.credentials` | Pass — encrypt round-trip verified; real OAuth needs Local + developer apps |
| Dummy credentials loadable in wp-env seed | Pass — both channels active with `dummy` flag and `som1:` ciphertext |
| Token refresh cron registered | Pass — `som_refresh_tokens` scheduled (30 minutes) |

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin deactivate orderMachine
npx @wordpress/env run cli wp plugin activate orderMachine
npx @wordpress/env run cli wp cron event list
npx @wordpress/env run cli wp db query "SELECT slug, is_active, LEFT(credentials,40) FROM wp_som_channels"
# credentials start with som1:; is_active=1 for ebay + etsy
```

Admin: http://localhost:8888/wp-admin/admin.php?page=som-settings — `admin` / `password`

### Open items / notes for later

- Real eBay/Etsy OAuth: configure developer apps on **Local**; register Auth Accepted / redirect URLs shown on Settings.
- `SOM_ENCRYPTION_KEY` is set in Local `wp-config.php` and in `.wp-env.json` (separate keys). Changing a key invalidates existing ciphertext until reconnect/re-seed.

---

## Sprint 3 — Order sync

- **Status:** Done
- **Roadmap phase:** 3
- **Completed:** 2026-07-29
- **Verified on:** wp-env (dev site `http://localhost:8888`)

### Plan requirements review (`Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| `includes/class-som-order-sync.php` — poll, de-dup, listing match | Done | Header update on re-sync; line items immutable after create |
| Channel clients: order-pull methods | Done | Live HTTP + fixture path when credentials are `dummy` |
| `includes/class-som-cron.php` — `som_sync_orders` | Done | Uses `som_order_poll` schedule from Settings |
| Seed/fixtures under `tests/fixtures/` | Done | eBay + Etsy JSON (incl. cancel examples) |
| **Done when:** Cron or Sync now creates/updates without duplicates | Pass | Second sync: created 0, updated 6 |
| **Done when:** Unmatched lines have `product_id` null | Pass | Fixture unmatched listings → NULL |
| **Done when:** `raw_payload` stored | Pass | Full API/fixture JSON on each order |
| Open item A1 personalisation | Soft | Best-effort extractors; refine after real samples |
| Open item A2 listing match | Done | Via `wp_som_listings.external_listing_id` (+ SKU fallback key) |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Fixture-first | Dummy credentials load fixtures; no live HTTP |
| Re-sync | Update `buyer_name`, `shipping_address`, `raw_payload`, `order_date`; **do not** change existing `order_items` |
| Incremental window | Since `last_synced_at` (5 min overlap); if null → **7 days** |
| Import history | Separate Settings action: 30 or 90 days backfill |
| Cancel (A3) | Stored only inside `raw_payload` / fixtures; no stock reversal yet |
| Sync UI | Settings: Sync now + Import history + last-sync summary |
| Seed catalogue | Sample product + matched ebay/etsy listing rows when `SOM_USE_DUMMY_CREDENTIALS` |
| Plugin version | Bumped to `0.3.0` |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-order-sync.php` | Orchestrates sync, de-dup, listing→product match |
| `includes/class-som-channel-ebay.php` | `fetch_orders` + normalize + personalisation extract |
| `includes/class-som-channel-etsy.php` | `fetch_orders` + normalize + personalisation extract |
| `includes/class-som-channels.php` | `is_dummy`, `set_last_synced_at` |
| `includes/class-som-cron.php` | Registers/runs `som_sync_orders` |
| `includes/seed/class-som-seed.php` | Dummy catalogue (product + listings) |
| `tests/fixtures/ebay-orders.json` | Sample eBay orders (matched / unmatched / cancelled) |
| `tests/fixtures/etsy-orders.json` | Sample Etsy receipts (matched / unmatched / canceled) |
| `admin/views/settings.php` | Sync now, Import history, last-sync status |
| `admin/class-som-admin-menu.php` | Sync / import action handlers |
| `orderMachine.php` | Bootstrap `SOM_Order_Sync`; cron schedule on `init` |

### Done-when checklist (from Sprint-Plan)

| Criterion | Result |
|---|---|
| Cron or manual Sync now creates/updates without duplicates | Pass — 6 fixture orders; re-run updates only |
| Unmatched lines have `product_id` null | Pass — eBay `199999999999`, Etsy `299999999999` |
| `raw_payload` stored | Pass |
| `som_sync_orders` scheduled | Pass — listed in `wp cron event list` |

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin activate orderMachine
npx @wordpress/env run cli wp eval 'SOM_Seed::maybe_seed_catalogue(); $r = SOM_Order_Sync::sync_incremental(); echo wp_json_encode($r);'
# {"created":6,"updated":0,...,"ok":true}
npx @wordpress/env run cli wp eval '$r = SOM_Order_Sync::sync_incremental(); echo wp_json_encode($r);'
# {"created":0,"updated":6,...,"ok":true}
npx @wordpress/env run cli wp db query "SELECT o.external_order_id, oi.product_id, oi.personalisation_text FROM wp_som_orders o JOIN wp_som_order_items oi ON oi.order_id=o.id"
npx @wordpress/env run cli wp cron event list
```

Admin: http://localhost:8888/wp-admin/admin.php?page=som-settings — Sync now / Import history

### Open items / notes for later

- Live OAuth + real order pull still needs developer apps on Local.
- A1: refine personalisation paths after Phase 4 pause with real payloads.
- A3: cancel field shapes are in fixtures (`orderFulfillmentStatus` / Etsy `status=canceled`) for Sprint 8 reversal.
- Material decrement / workflow assignment still deferred (Sprints 7–8).

---

## Sprint 4 — Orders list + detail

- **Status:** Done
- **Roadmap phase:** 4
- **Completed:** 2026-07-29
- **Verified on:** wp-env (dev site `http://localhost:8888`)

### Plan requirements review (`Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| `admin/views/orders-list.php` | Done | Filters, search, badges, pagination |
| `admin/views/order-detail.php` | Done | Personalisation + address front-and-centre; collapsed raw payload |
| `admin/assets/` — minimal CSS/JS | Done | CSS only (`admin/assets/css/admin.css`); `<details>` for raw JSON (no JS) |
| Menu wiring in `class-som-admin-menu.php` | Done | Replaces placeholder; `?order_id=` for detail |
| **Done when:** View synced/fixture orders in wp-admin | Pass | List + detail against 6 fixture orders |
| **Done when:** Personalisation + shipping address front-and-centre | Pass | Highlight panels on detail |
| **Done when:** Filters by status/channel/date roughly work | Pass | Plus search (buyer / external ID) |
| Open items first | None blocking | A1 refine during pause with real payloads |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Status filter | Open / Complete / Needs mapping / Cancelled (plus All) |
| Cancelled detection | Best-effort from `raw_payload` (eBay fulfillment/cancelState; Etsy `status`) |
| Unmatched | List badge (+ detail warning + line-item badge) |
| Address | Display only (no copy button) |
| Raw payload | Collapsed `<details>` on detail |
| Search | Buyer name or external order ID |
| Cancelled in list | Shown with Cancelled badge |
| Plugin version | Bumped to `0.4.0` |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-orders.php` | List query, detail fetch, cancel detection, address format |
| `admin/views/orders-list.php` | Filterable orders table |
| `admin/views/order-detail.php` | Single-order view |
| `admin/assets/css/admin.css` | List/detail layout + badges |
| `admin/class-som-admin-menu.php` | Orders render + asset enqueue |
| `orderMachine.php` | Bootstrap `SOM_Orders`; version `0.4.0` |

### Done-when checklist (from Sprint-Plan)

| Criterion | Result |
|---|---|
| Can view synced eBay/Etsy (or fixture) orders in wp-admin | Pass — 6 fixture orders |
| Personalisation and shipping address front-and-centre on detail | Pass |
| Filters by status/channel/date roughly work | Pass — cancelled=2, needs_mapping=2, search Alex=1, ebay=3 |

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin activate orderMachine
# Sync fixtures (already present): created 0, updated 6
# SOM_Orders::query() → 6 rows; cancelled filter 2; needs_mapping 2; search Alex 1
# SOM_Orders::get() → buyer, items, formatted address
```

Admin: http://localhost:8888/wp-admin/admin.php?page=som-orders — `admin` / `password`

### Open items / notes for later

- **PAUSE:** Use with real orders before Sprint 5+. Capture real personalisation paths (A1) and address shapes.
- Live OAuth + real order pull still needs developer apps on Local.
- Workflow step UI / Mark done deferred to Sprint 7.
- Copy-to-clipboard for Click & Drop deferred (display-only for now).

---

## Sprint 5 — Products + materials

- **Status:** Done
- **Roadmap phase:** 5
- **Completed:** 2026-07-29
- **Verified on:** wp-env (dev site `http://localhost:8888`)

### Plan requirements review (`Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| `admin/views/products.php` (list + edit) | Done | `products-list.php` + `product-edit.php` |
| `admin/views/materials.php` (list + edit) | Done | `materials-list.php` + `material-edit.php` |
| Material recipe editor; `product_materials` writes | Done | Repeatable rows + duplicate-material validation |
| Manual stock adjust → `material_stock_log` | Done | Delta input; reason `manual_adjustment` |
| **Done when:** CRUD products and materials | Pass | Deactivate-only (no hard delete) |
| **Done when:** Attach recipes | Pass | Replace-all save per product |
| **Done when:** Assign `workflow_template_id` | Pass | Nullable dropdown (empty until Sprint 6) |
| Open items first | None | Phase 4 pause waived by user |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Phase 4 pause | Waived — proceed with Sprint 5 |
| Delete behaviour | Deactivate only (`is_active` on products + materials; schema bump adds `materials.is_active`) |
| Material fields | `low_stock_threshold` + `unit_cost` on form |
| Stock log UI | Last 10 entries on material detail |
| Manual adjustment | Delta (+/-); stock may go negative |
| Recipe duplicates | Rejected server-side |
| Linked listings | Read-only table on product edit |
| Seed | 2 materials + recipe on sample product |
| Plugin version | Bumped to `0.5.0` |
| DB version | `1.1.0` (`materials.is_active`) |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-products.php` | Product CRUD, recipe save, listings lookup |
| `includes/class-som-materials.php` | Material CRUD, stock adjust, stock log |
| `admin/views/products-list.php` | Product list with filters |
| `admin/views/product-edit.php` | Product form, recipe editor, linked listings |
| `admin/views/materials-list.php` | Material list with low-stock badge |
| `admin/views/material-edit.php` | Material form, delta adjust, stock log |
| `admin/assets/js/admin.js` | Add/remove recipe rows |
| `admin/class-som-admin-menu.php` | Products/Materials menu + POST handlers |
| `includes/class-som-db.php` | `materials.is_active`; DB `1.1.0` |
| `includes/seed/class-som-seed.php` | Sample vinyl + laminate + recipe |
| `admin/assets/css/admin.css` | Catalogue + recipe + stock styles |
| `orderMachine.php` | Bootstrap + v0.5.0 + admin notices |

### Done-when checklist (from Sprint-Plan)

| Criterion | Result |
|---|---|
| CRUD products and materials | Pass |
| Attach recipes | Pass — 2 materials on seed product |
| Assign `workflow_template_id` (nullable) | Pass — dropdown present |
| Manual stock → `material_stock_log` | Pass — `manual_adjustment` verified |

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp option get som_db_version
# 1.1.0
npx @wordpress/env run cli wp db query "SHOW COLUMNS FROM wp_som_materials LIKE 'is_active'"
npx @wordpress/env run cli wp eval 'SOM_Seed::maybe_seed_catalogue();'
npx @wordpress/env run cli wp db query "SELECT m.name, pm.quantity_per_unit FROM wp_som_product_materials pm JOIN wp_som_materials m ON m.id=pm.material_id"
npx @wordpress/env run cli wp eval 'var_dump(SOM_Materials::adjust_stock(1, -2.5));'
npx @wordpress/env run cli wp db query "SELECT reason, change_qty FROM wp_som_material_stock_log"
```

Admin: http://localhost:8888/wp-admin/admin.php?page=som-products — `admin` / `password`

### Open items / notes for later

- Workflow templates shipped in Sprint 6 — product assignment dropdown is populated.
- Auto-decrement on order sync deferred to Sprint 8.
- Listings push/edit deferred to Sprint 10 (read-only on product edit for now).

---

## Sprint 6 — Workflow templates + step editor

- **Status:** Done
- **Roadmap phase:** 6
- **Completed:** 2026-07-30
- **Verified on:** wp-env (dev site `http://localhost:8888`)

### Plan requirements review (`Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| `admin/views/workflow-templates.php` | Done | List + filters + in-use badge |
| `admin/views/workflow-step-editor.php` | Done | Meta + ordered steps with gates |
| **Done when:** Create templates | Pass | Deactivate-only (`is_active`) |
| **Done when:** Add/remove/reorder steps | Pass | Up/down buttons; replace-all save |
| **Done when:** Toggle manual confirm | Pass | |
| **Done when:** Set `timer_seconds` | Pass | Friendlier value + unit (min/hr/day) |
| **Done when:** Configure `script_config` via form + raw JSON | Pass | local / api / n8n + raw fallback |
| Open item W3 | Soft / applied | Review reminder modelled as timer + manual confirm (seed: 7 days); still revisitable after editor exists |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Template lifecycle | `is_active` deactivate-only; block deactivate while products assigned |
| Template in use | Warning banner + product links; full edit still allowed |
| Timer UI | Value + unit → store seconds |
| Local allowlist (provisional) | `run_thankyou_card_script`, `send_print_job` (+ None) |
| Zero-gate steps | Allowed |
| Reorder | Up/down buttons (minimal JS) |
| Seed | Print → Dry (15m) → Laminate → Cut → Pack → Ship → Thank-you → Review reminder (7d) |
| Plugin version | Bumped to `0.6.0` |
| DB version | `1.2.0` (`workflow_templates.is_active`) |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-workflows.php` | Template/step CRUD, timer + script_config sanitizers |
| `admin/views/workflow-templates.php` | Template list |
| `admin/views/workflow-step-editor.php` | Template + step editor |
| `admin/class-som-admin-menu.php` | Workflows menu + POST handlers |
| `admin/assets/js/admin.js` | Step add/remove/reorder + script panels |
| `admin/assets/css/admin.css` | Step editor styles |
| `includes/class-som-db.php` | `workflow_templates.is_active`; DB `1.2.0` |
| `includes/seed/class-som-seed.php` | Sample workflow + assign to seed product |
| `includes/class-som-products.php` | Dropdown via active templates (+ current) |
| `admin/views/product-edit.php` | Link to Workflows; inactive label |
| `orderMachine.php` | Bootstrap + v0.6.0 |

### Done-when checklist (from Sprint-Plan)

| Criterion | Result |
|---|---|
| Create templates | Pass |
| Add/remove/reorder steps | Pass — reorder verified (B then A; C removed) |
| Manual confirm / timer / script_config | Pass — form + raw JSON path |
| Deactivate blocked when in use | Pass — `som_workflow_in_use` |
| Seed assigned to sample product | Pass — 8 steps; product `workflow_template_id=1` |

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp option get som_db_version
# 1.2.0
npx @wordpress/env run cli wp db query "SHOW COLUMNS FROM wp_som_workflow_templates LIKE 'is_active'"
npx @wordpress/env run cli wp eval 'SOM_Seed::maybe_seed_catalogue();'
npx @wordpress/env run cli wp db query "SELECT step_order, name, requires_manual_confirm, timer_seconds FROM wp_som_workflow_steps ORDER BY step_order"
# Deactivate in-use template → WP_Error som_workflow_in_use
# Create template + save_steps + reorder/remove verified via service class
```

Admin: http://localhost:8888/wp-admin/admin.php?page=som-workflows — `admin` / `password`

### Open items / notes for later

- Script execution / allowlist handlers deferred to Sprint 9 (config only here; Sprint 7 treats script gates as pass-through).
- Provisional local actions can grow when real handlers exist.

---

## Sprint 7 — Workflow engine (manual + timer)

- **Status:** Done
- **Roadmap phase:** 7
- **Completed:** 2026-07-30
- **Verified on:** wp-env (dev site `http://localhost:8888`)

### Plan requirements review (`Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| `includes/class-som-workflow-engine.php` — assign (primary product), advance, timer hard-gate, status transitions | Done | `assign_on_create`, `mark_done`, `enter_step`, `tick` |
| `includes/class-som-cron.php` — `som_engine_tick` | Done | Scheduled; interval from Settings |
| Order detail UI: Mark done / countdown (disabled until timer elapsed) | Done | Workflow panel + live countdown JS |
| **Done when:** New matched orders get `order_step_progress` rows | Pass | On create only; primary product + template |
| **Done when:** Manual confirm advances | Pass | |
| **Done when:** Timer steps block until `timer_ends_at` | Pass | Unlock Mark done — no auto-advance |
| **Done when:** Engine cron unlocks elapsed timers | Pass | Plus lazy unlock on detail / Mark done |
| Open items D1/W1 | Resolved (plan) | One workflow per order via primary product |
| Script/API execution | Deferred Sprint 9 | Rows created; script gate pass-through (option B) — no execute |

### Decisions applied during build

| Topic | Decision |
|---|---|
| Script steps until Sprint 9 | Pass-through (auto-complete script-only / zero-gate on entry) |
| Timer elapsed | Unlock Mark done — do **not** auto-advance |
| Existing fixture orders | Assign on **new creates only** (no backfill) |
| Unassigned / no template | Flag on detail + list badge + `needs_workflow` filter |
| Cancelled orders | No assignment; Mark done / tick blocked |
| Engine tick interval | Settings field next to order polling; **default 60 min** |
| Plugin version | Bumped to `0.7.0` |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-workflow-engine.php` | State machine: assign, mark done, tick, progress helpers |
| `includes/class-som-cron.php` | `som_engine_tick` schedule + handler |
| `includes/class-som-settings.php` | `engine_tick_interval_minutes` (default 60) |
| `includes/class-som-order-sync.php` | Call `assign_on_create` after new order insert |
| `includes/class-som-orders.php` | Progress on detail; `needs_workflow` filter; list step name |
| `admin/views/order-detail.php` | Workflow panel, Mark done, countdown |
| `admin/views/orders-list.php` | No-workflow badge; current step in status |
| `admin/views/settings.php` | Engine tick interval field |
| `admin/class-som-admin-menu.php` | Mark done handler; enqueue JS on orders |
| `admin/assets/js/admin.js` | Countdown ticker |
| `admin/assets/css/admin.css` | Workflow progress styles |
| `includes/seed/class-som-seed.php` | Re-point existing listings to seed product if drifted |
| `orderMachine.php` | Bootstrap engine; v0.7.0 |

### Done-when checklist (from Sprint-Plan)

| Criterion | Result |
|---|---|
| New matched orders get `order_step_progress` rows | Pass — 8 steps; current = Print |
| Manual confirm advances | Pass — Print → Dry → … → Ship |
| Timer steps block until elapsed | Pass — Dry locked until unlock |
| Engine cron unlocks elapsed timers | Pass — `tick()` + lazy unlock on detail |
| Script-only Thank-you does not block (Sprint 7) | Pass — auto-done; lands on Review reminder |
| Cancelled blocked | Pass — no progress; `som_order_cancelled` |

**Plan scope:** All Sprint 7 file and done-when items are complete. Script/API execution remains intentionally out of scope (Sprint 9).

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp cron event list
# som_engine_tick scheduled (1 hour default)
# Delete orders + re-sync fixtures → matched orders assigned
# mark_done Print → Dry waiting_timer (Mark done disabled)
# Force timer_ends_at past + tick → in_progress, Mark done enabled
# Advance through Ship → Thank-you auto-done → Review reminder waiting_timer
# Cancelled mark_done → som_order_cancelled
```

Admin: http://localhost:8888/wp-admin/admin.php?page=som-orders — `admin` / `password`

### Open items / notes for later

- Script/API/n8n execution + retries remain Sprint 9.
- Prefer real system cron for reliable timer unlocks (WP-Cron is request-triggered by default).

---

## Sprint 8 — Material auto-decrement

- **Status:** Done (scoped) — decrement path + order UI complete; cancel reversal explicitly deferred
- **Roadmap phase:** 8
- **Completed:** 2026-07-30
- **Verified on:** wp-env (dev site `http://localhost:8888`)

### Plan requirements review (`Sprint-Plan.md`)

Plan text:

> **Files:** `includes/class-som-material-stock.php` — decrement on new order; reversal on cancel; hook from order sync / create path  
> **Done when:** New order writes negative `material_stock_log` rows and updates `current_stock` (can go negative); cancel detection writes positive reversal with reason `order_cancelled` when channel status says cancelled.  
> **Open items first:** D3 / A3 — pin cancel fields from real/sandbox payloads.

| Plan item | Status | Notes |
|---|---|---|
| `includes/class-som-material-stock.php` — decrement on new order | Done | `decrement_on_create()` |
| Reversal on cancel | Deferred | Stub `maybe_reverse_on_cancel()` only — not hooked from sync |
| Hook from order sync / create path | Done | Incremental creates only (`apply_stock`); Import history skipped |
| **Done when:** New order → negative log + `current_stock` update | Pass | Can go negative (same rule as manual adjust) |
| **Done when:** Cancel → positive `order_cancelled` reversal | Deferred | Matches plan D3: “Can ship decrement-on-create first, then wire reversal once cancel detection is confirmed.” User chose hold until live/sandbox payload. |
| Open items D3 / A3 | Open for reversal | Fixture cancel fields already used for UI/`is_cancelled`; live payload still needed before wiring reversal |

### Decisions applied during build (user answers)

| Topic | Decision |
|---|---|
| Already-cancelled on first import | Skip decrement entirely |
| Import history / backfill | No stock reservation |
| Reversal idempotency (when built) | Only if `new_order` logs exist and no `order_cancelled` yet |
| Which line items | Every matched item’s recipe × quantity |
| Partial refunds | Full-order cancel only (when reversal lands) |
| A3 / reversal timing | Ship decrement first; hold reversal |
| Order detail UI | Material stock panel (reserved / none / reversed-ready) |
| Plugin version | `0.8.0` |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-material-stock.php` | Decrement on create; order stock summary; reversal stub |
| `includes/class-som-materials.php` | `adjust_stock( $id, $delta, $args )` — optional `order_id` + `reason` |
| `includes/class-som-order-sync.php` | Call `decrement_on_create` after assign on incremental create |
| `includes/class-som-orders.php` | Attach `stock_summary` on detail |
| `admin/views/order-detail.php` | Material stock panel |
| `admin/assets/css/admin.css` | Stock panel + reserved/reversed badges |
| `orderMachine.php` | Require material-stock; version `0.8.0` |

### Done-when checklist (from Sprint-Plan)

| Criterion | Result |
|---|---|
| New order writes negative `material_stock_log` + updates `current_stock` | **Pass** — matched open fixtures: 4 `new_order` rows; vinyl/laminate 25→23 |
| Cancel detection writes positive `order_cancelled` reversal | **Deferred** — intentional; not a silent miss |
| Already-cancelled create does not reserve | Pass |
| Unmatched lines do not reserve | Pass |
| Re-sync does not double-decrement | Pass (idempotent via existing `new_order` logs) |
| Import history does not reserve | Pass |

### How verified (wp-env)

```bash
npx @wordpress/env run cli wp plugin list --name=orderMachine
# orderMachine active v0.8.0
# Wipe orders + stock logs; reset materials to 25; sync_incremental
# created 6; stock 23/23; 4 new_order logs (matched open only)
# cancelled + unmatched: 0 logs; resync: still 4 logs
# sync_history after wipe: created 6, 0 stock logs, stock still 25
```

Admin: http://localhost:8888/wp-admin/admin.php?page=som-orders — matched order shows “Stock reserved”

### Open items / notes for later

- **D3 / A3:** Wire `maybe_reverse_on_cancel` from sync update path once a live/sandbox cancel payload confirms fields; reverse from logged quantities (not current recipe); only when `new_order` exists and no `order_cancelled` yet.
- Script/API/n8n execution remains Sprint 9.

---

## Next

**Sprint 9** — Script / API / n8n steps.
