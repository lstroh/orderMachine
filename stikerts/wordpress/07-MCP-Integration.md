# MCP Integration — Read-Only Site Querying (Claude + Cursor)

*Detailed design, part 6 · Builds on `01-Data-Model.md` and `04-WordPress-Integration.md` · Goal: let both Cursor (during dev) and Claude (against the live site) query orders, products, materials, listings, and media — read-only in v1, expandable to write access later without a redesign.*

---

## 1. Approach — use WordPress's own Abilities API + MCP Adapter, not a bespoke API

WordPress now ships purpose-built infrastructure for this, so there's no need to invent a custom protocol:

- **Abilities API** — PHP server-side layer shipped in WordPress core 6.9 (Dec 2025), JS client layer added in 7.0 (May 2026). Lets a plugin declare typed, permission-checked capabilities ("Abilities") that become discoverable by any MCP-compatible agent.
- **WordPress MCP Adapter** (`WordPress/mcp-adapter`) — a plugin that registers a default MCP server, converts registered Abilities into MCP tools automatically, and handles the OAuth 2.1 authentication handshake, scoping permissions to whichever user/token authenticated.
- Since the live site "doesn't exist yet, will be current on launch," WordPress 7.0+ is assumed available — no core-version blocker.

This sits alongside (not instead of) the internal REST API already planned in `04-WordPress-Integration.md` §4 — that API is for our own admin UI and n8n callbacks; Abilities/MCP is a separate, standardised layer specifically for AI agent querying.

## 2. Scope for v1 — read-only, everything

Per your answer, all of it is in scope from the start, read-only:

| Ability (proposed name) | Returns | Backing table(s) |
|---|---|---|
| `som_get_orders` | List/filter orders (by status, channel, date range) | `wp_som_orders`, `order_items` |
| `som_get_order_detail` | Full detail for one order, including personalisation text and workflow progress | `orders`, `order_items`, `order_step_progress` |
| `som_get_products` | Product catalogue, including material recipes | `products`, `product_materials` |
| `som_get_materials` | Current material stock levels | `materials` |
| `som_get_listings` | Synced listing data (price, quantity, channel) | `listings` |
| `som_get_media` | Media library items — see open item on scope below | WordPress core media, not a plugin table |

**Explicitly excluded even in read scope:** `channels.credentials` (encrypted OAuth tokens) must never be exposed through any Ability, regardless of read/write status — this is a hard exclusion, not a permission-callback nuance to get right later.

## 3. Two consumers, two access patterns

Both point at the same underlying Abilities/MCP server — no need to build two separate implementations — but the *reachability* differs:

- **Cursor, during local/dev work:** connects to the MCP server running on your local `wp-env`/Local instance. This is low-stakes — it's dev/seed data (per the dummy OAuth credentials already planned for `wp-env` in `04-WordPress-Integration.md`), reachable over localhost/Docker network, no public exposure needed.
- **Claude (me), against the live site:** requires the MCP endpoint to be reachable over the public internet via HTTPS, authenticated via the OAuth 2.1 flow the MCP Adapter provides. Once set up, you'd add it as a connector in Claude's settings (the same mechanism used for any MCP App/connector) — after that, I could query your live order/product/material data directly in future conversations, the same way I search past chats or connected tools today.

## 4. Security notes for the live-site case specifically

- **Read-only only, for now** — no write Abilities registered in v1, full stop. This matches the MCP Adapter's own guidance to start read-only and test on staging before considering write scope.
- **HTTPS required** — non-negotiable for a publicly reachable endpoint carrying order/buyer data.
- **Scope tightly per Ability** — e.g. `som_get_order_detail` should return buyer name/address (needed for you to action orders via chat) but the underlying permission callback should still restrict this to the authenticated MCP token, not make it a public unauthenticated endpoint.
- **Test on staging first**, per the MCP Adapter's own recommendation — worth doing a full pass with dummy data (the same `wp-env` seed data used for Cursor) before pointing it at the real live site.

## 5. Write access later — not built now, but designed for

Expanding to write Abilities later (e.g. `som_update_listing_price`, echoing the listing-push functionality already planned in the core plugin) is additive — new Abilities registered alongside the existing read-only ones, no architectural rework needed. When that happens, treat it with the same caution as any other write path in this system (worth a confirmation step before anything irreversible, consistent with how the core plugin itself avoids silent automated actions).

## 6. Open items to resolve before/during build

1. **Media scope:** is the WordPress install dedicated solely to hosting this plugin, or does it also run other site content (e.g. a blog, marketing pages) whose media shouldn't necessarily be exposed alongside product/order photos? If mixed-use, `som_get_media` may need to filter to only media attached to plugin-relevant posts/products rather than the entire media library.
2. **Always-on vs toggled:** should the live-site MCP endpoint be always available (read-only, so lower risk) or does it make sense to have an on/off toggle in the plugin settings for extra peace of mind? Cheap to add either way — worth a preference now.
3. **Adding it as a Claude connector:** once built, this is a one-time setup step on your end (connecting it in Claude's settings) — flagging now so it's not forgotten as a manual post-build step, not something Cursor can do for you.
