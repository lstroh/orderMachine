# Order Machine — Sprint Plan

*Planning deliverable from `06-Cursor-Kickoff-Prompt.md` · July 2026 · No application code in this pass*

Plugin root: `wp-content/plugins/orderMachine/`  
Design docs: `stikerts/wordpress/` (this folder)  
Roadmap source: `05-Implementation-Roadmap.md` (**12** phases; kickoff “11” was outdated)

---

## Settled decisions

| Topic | Decision |
|---|---|
| Folder / WP slug | `orderMachine` |
| Internal naming | Keep design conventions: `class-som-*`, `SOM_*`, tables `{$wpdb->prefix}som_*`, options like `som_db_version` |
| Bootstrap file | `orderMachine.php` — Plugin Name: **Order Machine** |
| Mixed-workflow orders | **One workflow per order** from **primary product** = first `order_items` row with non-null `product_id`. If none matched: `current_step_id` null, no progress rows, flag in UI until mapped |
| Admin UI (v1) | Plain PHP + minimal JS (no React / build step) |
| Plugin type | Standard installable plugin (not must-use) |
| Multisite | Single site only |
| Listing ↔ product match | Manual rows in `wp_som_listings` |
| Review reminder (v1) | Timer + manual confirm as last workflow step; revisit after Phase 4 pause if awkward |
| MCP | Settings on/off toggle; `som_get_media` = full media library until install is known mixed-use |
| Phase order | Follow roadmap; **pause after Phase 4**. Phase 8 stays after Phase 7 even though it only depends on 3+5 (optional parallel later if accelerating) |

---

## 1. Consolidated open items

### From `01-Data-Model.md`

| # | Open item | Blocking? |
|---|---|---|
| D1 | **Multi-product orders with mixed workflows** | **Resolved** — one workflow per order via primary product (see Settled decisions). Unblocks Phase 7 / `order_step_progress`. |
| D2 | **Unmatched order items** | **Resolved in design** — save with `product_id = NULL`, flag in UI; do not drop or block sync. Implement in Phase 3–4. Does not block scaffolding. |
| D3 | **Cancellation/refund stock reversal** | **Blocks Phase 8** (reversal path). Trigger depends on channel cancel fields (see A3 / API doc). Can ship decrement-on-create first, then wire reversal once cancel detection is confirmed. |

### From `02-API-Integration.md`

| # | Open item | Blocking? |
|---|---|---|
| A1 | **Personalisation field location varies per listing** | **Does not block first sync** — store `raw_payload`; best-effort extract into `personalisation_text`. Refine after real order samples (ideally during/after Phase 4 pause). |
| A2 | **SKU / listing-ID → product matching rule** | **Resolved by recommendation (confirmed for plan)** — maintain `external_listing_id ↔ product_id` manually in `wp_som_listings`. Needed for sync matching and listing push. |
| A3 | **eBay cancel field details / Etsy `status` = canceled** | Tied to **D3**; blocks correct Phase 8 reversal. Poll and inspect sandbox/live payloads during Phase 3. |
| A4 | **eBay Inventory API requires SKUs on listings** | **Blocks clean Phase 10** for eBay push until listings have SKUs. Check current listings before Sprint 10. |
| A5 | **Etsy variations / per-variation quantity** | **May block flat qty model in Phase 10** if any live listings use variations. Check before Sprint 10. |

### From `03-Workflow-Engine.md`

| # | Open item | Blocking? |
|---|---|---|
| W1 | **Multi-item / two templates** | **Resolved** — same as D1 (primary product). |
| W2 | **`thankyou_card.py` call contract** | **Blocks Phase 9** local thank-you action. Script today is import/`render_sheet(orders, path)` only — needs a small CLI or JSON-stdin wrapper on the Python side plus PHP allowlisted handler. |
| W3 | **Review-reminder modelling** (in-workflow timer vs separate reminder list) | **Soft** — default timer + manual confirm for v1. Revisit after Phase 4 pause / once step editor exists. Does not block Phase 6–7. |

### From `04-WordPress-Integration.md`

| # | Open item | Blocking? |
|---|---|---|
| P1 | **Plugin vs mu-plugin vs theme code** | **Resolved for plan** — standard plugin. Change only if you have a strong reason later. |
| P2 | **Multisite** | **Resolved for plan** — not relevant (single site). |
| P3 | **Admin UI framework** | **Resolved for plan** — plain PHP + minimal JS per roadmap. |

### From `05-Implementation-Roadmap.md`

No separate “Open items” section. Checkpoint: **pause after Phase 4** and use the orders UI with real data before building the workflow engine (Phases 6–9).

### From `07-MCP-Integration.md`

| # | Open item | Blocking? |
|---|---|---|
| M1 | **Media scope** (whole library vs plugin-relevant only) | **Soft / Phase 12** — default full library. Narrow if Local/live install becomes mixed-use (blog, marketing, etc.). |
| M2 | **Always-on vs toggled MCP endpoint** | **Soft / Phase 12** — default **settings toggle** (off until enabled). |
| M3 | **Add as Claude connector** | **Post-build manual step** — not blocking code; document when Phase 12 ships. |

---

## 2. Clarifying questions (record)

### Answered

1. **Naming:** Keep design SOM / `wp_som_*` / `class-som-*` naming; folder and plugin display name are **orderMachine** / Order Machine.  
2. **Mixed workflows:** Option A — one workflow per order (primary product rule above).

### Remaining preferences (defaults applied; object anytime)

3. **PHP version target for code?** Host CLI is 8.5.1; **wp-env locked to PHP 8.2** for broader WP/docker image compatibility. Write plugin code for **PHP 8.2+** (typed properties, etc. fine; avoid 8.3+/8.5-only syntax). Confirm if you want to require 8.3+ instead.  
4. **Composer?** Not required for runtime plugin code. **Optional** for PHPCS (`composer.json` + `phpcs.xml.dist` scaffold). Default: scaffold PHPCS config now; add Composer when you want lint in CI.  
5. **Is the Local `ordermachine` site dedicated to this plugin?** Affects MCP media (M1). Default: treat as dedicated → full media library OK.  
6. **wp-env vs Local split** (see §4) — assumed: Local = daily UI/OAuth; wp-env = disposable/tests. Confirm if you want a different split.  
7. **Real eBay/Etsy OAuth during early sprints?** wp-env uses dummy credentials; Local can do sandbox/live when you have developer apps ready. No code-shape impact if sync is fixture-first.

### Recommendations (need your OK only if you disagree)

- Script retry budget: **3 attempts** (immediate / +1 min / +5 min) as suggested in `03-Workflow-Engine.md`.  
- Polling: orders **10–15 min**; engine tick **1–5 min**; token refresh **30–60 min**.  
- Credential encryption: `wp_salt()`-derived key or `SOM_ENCRYPTION_KEY` in `wp-config.php`.

---

## 3. Sprint breakdown

Roadmap phases preserved. **Do not start Sprint 5+ until after the Phase 4 pause** unless you explicitly waive it.

```mermaid
flowchart LR
  S0[Sprint0 Env] --> S1[S1 Foundation]
  S1 --> S2[S2 Channels]
  S2 --> S3[S3 Sync]
  S3 --> S4[S4 Orders UI]
  S4 --> Pause[Pause use real orders]
  Pause --> S5[S5 Products Materials]
  S5 --> S6[S6 Workflow UI]
  S6 --> S7[S7 Engine]
  S7 --> S8[S8 Stock auto]
  S8 --> S9[S9 Scripts n8n]
  S9 --> S10[S10 Listings]
  S10 --> S11[S11 REST plus MCP]
```

### Sprint 0 — Env / Cursor setup

- **Phases:** — (tooling only)
- **Files created/modified:**
  - `stikerts/wordpress/Sprint-Plan.md` (this file)
  - `.cursor/rules/order-machine-architecture.mdc`
  - `.cursor/rules/wordpress-php-standards.mdc`
  - `.wp-env.json`
  - `.editorconfig`
  - `phpcs.xml.dist`
  - remove empty nested `OrderMachine/` if present
- **Done when:** Cursor rules and wp-env config exist; Local vs wp-env documented; open items captured here.
- **Open items first:** none.

### Sprint 1 — Foundation

- **Phases:** 1
- **Files:**
  - `orderMachine.php` — headers, activation/deactivation
  - `includes/class-som-db.php` — `dbDelta` schema for all tables in `01-Data-Model.md`, `som_db_version` option
  - `uninstall.php` — optional; ask before dropping tables by default
  - `admin/class-som-admin-menu.php` — stub top-level menu “Order Machine” (or “Order Manager” label if preferred later)
- **Done when:** Plugin activates on Local and wp-env; all `{$wpdb->prefix}som_*` tables exist; deactivation does not destroy data.
- **Open items first:** P1/P2 assumed resolved.

### Sprint 2 — Channel connection

- **Phases:** 2
- **Files:**
  - `includes/class-som-channel-ebay.php` — OAuth + token storage helpers
  - `includes/class-som-channel-etsy.php` — OAuth PKCE + token storage
  - `includes/class-som-cron.php` — register `som_refresh_tokens` (stub wiring OK)
  - `admin/views/settings.php` — connect buttons, n8n base URL, polling interval fields
  - Credential encrypt/decrypt helpers (in channel classes or small shared include)
- **Done when:** Settings page loads; OAuth connect can store encrypted JSON in `wp_som_channels.credentials` (sandbox/Local); dummy credentials loadable in wp-env seed.
- **Open items first:** Developer app credentials when testing real OAuth (not needed for dummy path).

### Sprint 3 — Order sync

- **Phases:** 3
- **Files:**
  - `includes/class-som-order-sync.php` — poll, de-dup (`UNIQUE channel_id + external_order_id`), item matching via listings
  - Channel clients: order-pull methods
  - `includes/class-som-cron.php` — `som_sync_orders` every 10–15 min
  - Seed/fixtures under e.g. `tests/fixtures/` or `includes/seed/` for wp-env (dummy order payloads)
- **Done when:** Cron or manual “Sync now” creates/updates orders and items without duplicates; unmatched lines have `product_id` null; `raw_payload` stored.
- **Open items first:** A1 soft (best-effort personalisation); A2 rule in use.

### Sprint 4 — Orders list + detail (pause checkpoint)

- **Phases:** 4
- **Files:**
  - `admin/views/orders-list.php`
  - `admin/views/order-detail.php`
  - `admin/assets/` — minimal CSS/JS
  - Menu wiring in `class-som-admin-menu.php`
- **Done when:** Can view synced eBay/Etsy (or fixture) orders in wp-admin; personalisation and shipping address front-and-centre on detail; filters by status/channel/date roughly work.
- **Open items first:** none blocking.
- **PAUSE:** Use with real orders before Sprint 5+. Capture real personalisation paths (A1) and address shapes.

### Sprint 5 — Products + materials

- **Phases:** 5
- **Files:**
  - `admin/views/products.php`
  - `admin/views/materials.php`
  - Material recipe editor UI; `wp_som_product_materials` writes
  - Manual stock adjust → `material_stock_log` with `manual_adjustment`
- **Done when:** Can CRUD products and materials, attach recipes, assign `workflow_template_id` (nullable until Sprint 6 templates exist — or create templates in Sprint 6 first then assign; assignment UI can land here with empty dropdown).
- **Open items first:** none.

### Sprint 6 — Workflow templates + step editor

- **Phases:** 6
- **Files:**
  - `admin/views/workflow-templates.php`
  - `admin/views/workflow-step-editor.php`
- **Done when:** Can create templates; add/remove/reorder steps; toggle manual confirm, set `timer_seconds`, configure script_config via form (+ raw JSON fallback for api/n8n).
- **Open items first:** W3 soft (default review-reminder as timer step).

### Sprint 7 — Workflow engine (manual + timer)

- **Phases:** 7
- **Files:**
  - `includes/class-som-workflow-engine.php` — assign on order (primary product), advance, timer hard-gate, status transitions
  - `includes/class-som-cron.php` — `som_engine_tick`
  - Order detail UI: Mark done / countdown (disabled until timer elapsed)
- **Done when:** New matched orders get `order_step_progress` rows; manual confirm advances; timer steps block until `timer_ends_at`; engine cron unlocks elapsed timers.
- **Open items first:** D1/W1 resolved. Script/API execution deferred to Sprint 9 (steps with `script_config` can sit `waiting_script` or be skipped until Sprint 9 — prefer create rows but no execute until Sprint 9).

### Sprint 8 — Material auto-decrement

- **Phases:** 8
- **Files:**
  - `includes/class-som-material-stock.php` — decrement on new order; reversal on cancel
  - Hook from order sync / create path
- **Done when:** New order writes negative `material_stock_log` rows and updates `current_stock` (can go negative); cancel detection writes positive reversal with reason `order_cancelled` when channel status says cancelled.
- **Open items first:** D3 / A3 — pin cancel fields from real/sandbox payloads.

### Sprint 9 — Script / API / n8n steps

- **Phases:** 9
- **Files:**
  - `includes/class-som-local-actions.php` — allowlist only (`run_thankyou_card_script`, etc.)
  - Workflow engine: script dispatch, retries, `error` + manual retry
  - `includes/class-som-rest-api.php` — `workflow-callback/{token}`
  - Optional: small Python CLI wrapper beside `stikerts/Thank you/thankyou_card.py`
- **Done when:** n8n webhook and/or local allowlisted action can run from a step; failures retry then surface error; callback can complete a step.
- **Open items first:** **W2** thank-you card contract must be decided (CLI args vs stdin JSON).

### Sprint 10 — Listings view + price/qty push

- **Phases:** 10
- **Files:**
  - `admin/views/listings.php`
  - Channel clients: inventory GET/PUT / Etsy PATCH
- **Done when:** Can view cached listings and push price/quantity updates to connected channels.
- **Open items first:** A4 (eBay SKUs), A5 (Etsy variations).

### Sprint 11 — External order REST + MCP

- **Phases:** 11 + 12
- **Files:**
  - `includes/class-som-rest-api.php` — `POST /wp-json/som/v1/orders`, `advance-step`
  - MCP/Abilities registration (read-only abilities per `07-MCP-Integration.md`)
  - Settings: MCP on/off toggle
  - Document installing WordPress MCP Adapter + Claude connector (M3)
- **Done when:** External API key can create an order (Amazon-email-workaround groundwork); Cursor can query via local MCP; live Claude path documented (HTTPS + OAuth 2.1). Credentials never exposed via Abilities.
- **Open items first:** M1/M2 defaults above.

---

## 4. Environment setup summary

### Files created this pass

| File | Purpose |
|---|---|
| [`.cursor/rules/order-machine-architecture.mdc`](../../.cursor/rules/order-machine-architecture.mdc) | Always-on architecture, design-doc pointers, naming, allowlist, pause checkpoint |
| [`.cursor/rules/wordpress-php-standards.mdc`](../../.cursor/rules/wordpress-php-standards.mdc) | PHP / `$wpdb` / caps / nonces when editing `**/*.php` |
| [`.wp-env.json`](../../.wp-env.json) | Official `@wordpress/env` — WP 7.0.x, PHP 8.2, this plugin mapped |
| [`.editorconfig`](../../.editorconfig) | Indentation / charset defaults |
| [`phpcs.xml.dist`](../../phpcs.xml.dist) | WordPress-Coding-Standards scaffold (run once Composer/PHPCS installed) |

### Local (Local by Flywheel / WP Engine)

**Use for:** day-to-day browsing and debugging of wp-admin UI; real or sandbox OAuth; system cron experiments against a persistent DB.

**Setup:**

1. Site already exists: Local site **ordermachine** → `app/public` is the WordPress root (WP **7.0.2**).
2. This repo **is** the plugin folder: `app/public/wp-content/plugins/orderMachine/`.
3. In wp-admin → Plugins, activate **Order Machine** once `orderMachine.php` exists (Sprint 1).
4. Prefer real server cron / Local’s cron options over default request-triggered WP-Cron for timers (see `03` / `04`).

### wp-env

**Use for:** fast disposable environments, seed/fixture data, automated checks, MCP Adapter experiments without touching the Local DB.

```bash
# from plugin root (requires Node + Docker)
npx @wordpress/env start
npx @wordpress/env run cli wp plugin list
```

- Plugin is mapped from the repo root via `.wp-env.json`.
- Dummy channel credentials / fixture orders are for local testing only — real OAuth will not work against throwaway wp-env URLs without tunnel/ngrok-style setup; do OAuth on Local instead.

### When to use which

| Task | Tool |
|---|---|
| Click through admin UI, visual CSS | Local |
| OAuth connect to eBay/Etsy | Local (or tunnel + wp-env if you insist) |
| Reset DB / re-run activation | wp-env |
| PHPCS / future PHPUnit | wp-env or host CLI |
| Cursor MCP against seed data | wp-env or Local |

### Other improvements worth doing later

- Install Composer + `wp-coding-standards/wpcs` and run `vendor/bin/phpcs`.
- Install WP-CLI on the host or use `npx @wordpress/env run cli`.
- Cursor/VS Code: PHP Intelephense; set `intelephense.environment.phpVersion` to `8.2.0`.
- Never commit real OAuth tokens; keep sandbox keys in Local-only `wp-config` or env vars.

---

## 5. Environment / setup assumptions

Documented during kickoff (July 2026) so future sprints need not re-discover:

| Fact | Value |
|---|---|
| Workspace / plugin path | `c:\Users\liron\Local Sites\ordermachine\app\public\wp-content\plugins\orderMachine` |
| Local site name | `ordermachine` |
| WordPress (Local) | **7.0.2** (`wp-includes/version.php`) |
| PHP CLI (host) | **8.5.1** |
| wp-env PHP target | **8.2** |
| wp-env WP core | **7.0.x** |
| Composer | Not in use yet |
| WP-CLI on PATH | Not available (`wp` not found) |
| Existing plugin PHP | None yet — design docs under `stikerts/` only |
| Thank-you card script | `stikerts/Thank you/thankyou_card.py` — `render_sheet(orders, out_path)`; no CLI yet |
| n8n | Self-hosted, available as workflow step target |
| Nested empty `OrderMachine/` | Removed during Sprint 0 if empty (accidental) |

---

## Design doc index (standing context)

1. [`Order-Management-Requirements.md`](Order-Management-Requirements.md) — why / scope  
2. [`01-Data-Model.md`](01-Data-Model.md) — schema  
3. [`02-API-Integration.md`](02-API-Integration.md) — eBay / Etsy  
4. [`03-Workflow-Engine.md`](03-Workflow-Engine.md) — state machine  
5. [`04-WordPress-Integration.md`](04-WordPress-Integration.md) — plugin architecture  
6. [`07-MCP-Integration.md`](07-MCP-Integration.md) — read-only MCP  
7. [`05-Implementation-Roadmap.md`](05-Implementation-Roadmap.md) — phased build order  
8. [`06-Cursor-Kickoff-Prompt.md`](06-Cursor-Kickoff-Prompt.md) — this planning task  

---

*End of sprint plan. Next build work starts at Sprint 1 (Foundation) after Sprint 0 tooling is in place.*
