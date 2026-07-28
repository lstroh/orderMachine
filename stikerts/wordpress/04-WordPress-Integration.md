# WordPress Integration — Plugin Architecture

*Detailed design, part 4 of 4 · Builds on all three prior files · Native WP plugin, PHP + custom MySQL tables via `$wpdb`.*

---

## 1. Plugin structure

```
sticker-order-manager/
├── sticker-order-manager.php        # main plugin file, headers, activation/deactivation hooks
├── includes/
│   ├── class-som-db.php             # schema creation/migration (dbDelta), table constants
│   ├── class-som-channel-ebay.php   # eBay API client: auth, order pull, listing push
│   ├── class-som-channel-etsy.php   # Etsy API client: auth, order pull, listing push
│   ├── class-som-order-sync.php     # orchestrates polling both channels, de-dup, item matching
│   ├── class-som-workflow-engine.php# state machine: advance steps, timers, script dispatch, retries
│   ├── class-som-material-stock.php # decrement/reversal logic, stock log writes
│   ├── class-som-local-actions.php  # allowlisted local action handlers (print job, thankyou_card.py call)
│   ├── class-som-cron.php           # registers WP Cron hooks, ties them to sync/engine tick
│   └── class-som-rest-api.php       # exposes internal REST endpoints (see §4)
├── admin/
│   ├── class-som-admin-menu.php     # registers admin pages under one top-level menu
│   ├── views/
│   │   ├── orders-list.php          # main dashboard — the unified order view
│   │   ├── order-detail.php         # single order: items, personalisation, current step, actions
│   │   ├── workflow-templates.php   # list/create/edit workflow templates
│   │   ├── workflow-step-editor.php # add/remove/reorder steps within a template, configure gates
│   │   ├── products.php             # product list, material recipe editor, workflow assignment
│   │   ├── materials.php            # material stock list, manual adjustment
│   │   ├── listings.php             # listing view + price/qty push
│   │   └── settings.php             # channel credentials, n8n base URL, polling interval
│   └── assets/                      # admin CSS/JS — plain, no build step needed for v1
└── uninstall.php                    # optional: clean up tables on uninstall, ask before assuming this
```

## 2. Database setup

- All tables from `01-Data-Model.md` created in `class-som-db.php` using WordPress's `dbDelta()` on plugin activation — this is the standard, safe way to create/alter custom tables in a WP plugin (handles "if not exists" and future column additions cleanly).
- Store a plugin DB schema version in `wp_options` (`som_db_version`) so future plugin updates can run incremental migrations rather than re-running the full schema blindly.

## 3. Admin UI — pages needed

| Page | Purpose |
|---|---|
| **Orders (main dashboard)** | The unified order view (§6.2 of requirements) — filterable list, click into an order for detail + current-step actions |
| **Order detail** | Personalisation text front and centre, shipping address (for copying into Click & Drop), current workflow step with its available actions (confirm/timer countdown/retry) |
| **Workflow templates** | List existing templates; create new; each opens the step editor |
| **Workflow step editor** | Ordered list of steps for one template; add/remove/reorder; per-step toggle manual-confirm, set timer duration, configure script/API JSON (a simple form, not raw JSON editing, for the common cases — with a raw JSON fallback for the n8n/API cases that need arbitrary payload templates) |
| **Products** | List products; each has its material recipe (add material + qty) and workflow template assignment |
| **Materials** | Stock levels, manual adjustment (writes a `material_stock_log` row with reason `manual_adjustment`), low-stock flags |
| **Listings** | View synced listings per channel; edit price/description/quantity and push |
| **Settings** | eBay/Etsy OAuth connect buttons (kicks off the auth flow, stores resulting tokens), n8n base URL, polling interval |

Single top-level admin menu item ("Order Manager") with the above as submenu pages — standard WP admin pattern, no need for anything more elaborate for a single-user tool.

## 4. Internal REST API

WordPress's built-in REST API infrastructure (`register_rest_route`) is the natural fit — gives you authenticated endpoints for free via WP's existing auth (application passwords, or a simple custom API key for external callers like n8n/Amazon-email-automation).

| Route | Method | Purpose |
|---|---|---|
| `/wp-json/som/v1/orders` | POST | **Create an order externally** — this is the endpoint the future Amazon email→n8n workaround would call (requirements §7). Build this now even though nothing calls it yet, since it costs little extra and is genuinely useful groundwork. |
| `/wp-json/som/v1/orders/{id}/advance-step` | POST | Mark current step done (used by admin UI, but exposed as a real endpoint so n8n callbacks can also complete a script/API step from the outside, e.g. "print job finished" callback) |
| `/wp-json/som/v1/workflow-callback/{token}` | POST | n8n calls this back when a triggered workflow finishes, to report success/failure into `order_step_progress` |

Auth for external callers: a simple per-integration API key stored in `wp_options`, checked via a custom header — sufficient for a single-user internal tool talking to your own n8n instance, no need for full OAuth here.

## 5. Cron jobs

| Job | Frequency | Does |
|---|---|---|
| `som_sync_orders` | every 10–15 min | Poll eBay + Etsy for new/updated orders, de-dup, create order + item rows, trigger material decrement + workflow assignment |
| `som_engine_tick` | every 1–5 min | Check `waiting_timer` steps for elapsed timers, retry due `waiting_script`/`error` steps within retry budget |
| `som_refresh_tokens` | every 30–60 min | Proactively refresh eBay/Etsy OAuth tokens before expiry |

**Important:** register these via real server-level cron hitting `wp-cron.php` (or system cron calling WP-CLI `wp cron event run`), not default request-triggered WP Cron — timers and polling need to fire reliably even with no admin browser session open, as noted in `03-Workflow-Engine.md` §4.

## 6. Security notes

- Encrypt channel credentials at rest (see `02-API-Integration.md` §3).
- All admin pages behind `current_user_can('manage_options')` or a dedicated capability — single-user tool, but still worth gating properly rather than assuming.
- REST endpoints that accept external calls (order creation, workflow callback) need their own API-key check, separate from WP's cookie-based admin auth, since callers are automations (n8n), not logged-in browser sessions.
- Local script/action allowlist (per `03-Workflow-Engine.md` §1) is a security boundary, not just a convenience — never let `script_config` specify an arbitrary shell command from the DB.

## 7. Open items to resolve before/during build

- **Plugin vs. must-use plugin vs. theme-level code:** a standard installable plugin (not mu-plugin) is the default recommendation — easier to version, activate/deactivate, and hand to Cursor as a self-contained folder. Flag if you have a reason to prefer otherwise.
- **Multisite:** assumed not relevant (single site) — flag if wrong.
- **Admin UI framework:** plain PHP + minimal JS (no React/build step) keeps this simple and matches "settings screen, not visual builder" from the requirements doc — confirm that's still the right call before Cursor starts, since it affects how much scaffolding gets generated.
