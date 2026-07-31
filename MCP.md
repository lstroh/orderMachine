# MCP setup — Cursor + Claude

Order Machine registers **read-only** WordPress Abilities when **Settings → MCP (AI query)** is enabled. The [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) turns those Abilities into MCP tools.

Abilities (WP naming):

- `order-machine/get-orders`
- `order-machine/get-order-detail`
- `order-machine/get-products`
- `order-machine/get-materials`
- `order-machine/get-listings`
- `order-machine/get-media`

Credentials (`channels.credentials`) are never exposed.

When the MCP toggle is **off**, Abilities are **not registered** at all.

---

## wp-env (recommended for Cursor)

`.wp-env.json` installs **Order Machine** and **MCP Adapter** automatically.

```bash
npx @wordpress/env start
npx @wordpress/env run cli wp plugin activate orderMachine mcp-adapter
```

1. Log in: http://localhost:8888/wp-admin (`admin` / `password`)
2. **Order Machine → Settings** → enable **MCP (AI query)** → Save
3. Create an [Application Password](https://wordpress.org/documentation/article/application-passwords/) for `admin` (Users → Profile), or use STDIO via WP-CLI (below)

### Cursor — HTTP proxy (typical)

Add to Cursor MCP settings (adjust paths if needed):

```json
{
  "mcpServers": {
    "order-machine-wp-env": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "http://localhost:8888/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "admin",
        "WP_API_PASSWORD": "YOUR_APPLICATION_PASSWORD"
      }
    }
  }
}
```

### Cursor / CLI — STDIO (wp-env)

From the plugin root (Docker WP-CLI):

```bash
npx @wordpress/env run cli wp mcp-adapter list
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' | npx @wordpress/env run cli wp mcp-adapter serve --server=mcp-adapter-default-server --user=admin
```

Discover Order Machine abilities via `mcp-adapter/discover-abilities`, then run them with `mcp-adapter/execute-ability`.

---

## Local (Flywheel) — optional

1. Install MCP Adapter into the Local site plugins folder (same zip as wp-env uses):

   `https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip`

2. Activate **MCP Adapter** and **Order Machine**
3. Enable **MCP (AI query)** in Order Machine Settings
4. Point Cursor at your Local URL instead of `localhost:8888`, e.g.:

   `WP_API_URL=https://ordermachine.local/wp-json/mcp/mcp-adapter-default-server`

   (Use your real Local domain + an Application Password.)

---

## Claude (live site) — post-build manual step (M3)

1. Site must be reachable over **HTTPS**
2. MCP Adapter + Order Machine active; MCP toggle **on**
3. Create an Application Password for a user with `manage_options`
4. In Claude settings, add a connector / MCP server using HTTP + Application Password (same pattern as the Cursor proxy above, with the live `WP_API_URL`)
5. Test on staging / wp-env seed data before pointing at production orders

---

## Quick PHP smoke (no Cursor)

With MCP enabled on wp-env:

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint11-smoke.php
```
