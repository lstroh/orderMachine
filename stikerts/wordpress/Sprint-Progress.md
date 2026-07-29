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
| 5+ | Later phases | Not started | **Pause after Phase 4** — use with real orders before Sprint 5+ unless waived |

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

## Next

**Phase 4 pause** — use the orders UI with real (or fixture) data before Sprint 5 (Products + materials) unless explicitly waived.
