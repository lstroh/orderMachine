# Order Machine — Sprint Progress

*Companion to [`Sprint-Plan.md`](Sprint-Plan.md). Plan stays the source of scope; this file records what shipped and how it was verified.*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| 0 | Env / Cursor setup | Done | Rules, wp-env, PHPCS scaffold, Sprint-Plan |
| 1 | Foundation | Done | Verified on wp-env; Local activation not yet confirmed by agent |
| 2 | Channel connection | Done | Verified on wp-env (dummy credentials + settings + cron) |
| 3 | Order sync | Not started | |
| 4 | Orders list + detail | Not started | Pause checkpoint after this sprint |
| 5+ | Later phases | Not started | Do not start until after Phase 4 pause unless waived |

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
- Order sync cron (`som_sync_orders`) lands in Sprint 3.

---

## Next

Sprint 3 — Order sync (poll, de-dup, listing match, fixtures).
