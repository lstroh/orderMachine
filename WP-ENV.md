## wp-env Guide

This plugin includes a `.wp-env.json` file so you can run a disposable WordPress environment in Docker for local testing.

### Prerequisites

- Docker Desktop running
- Node.js / `npx` available

### Start

From the plugin root:

```bash
npx @wordpress/env start
```

On first run, Docker may need a few minutes to pull images.

Default URLs:

- Site: `http://localhost:8888`
- Admin: `http://localhost:8888/wp-admin`
- Test site: `http://localhost:8889`

Default login is usually:

- Username: `admin`
- Password: `password`

### Stop

Stops the containers but keeps their data:

```bash
npx @wordpress/env stop
```

### Restart

If you want to stop and start again:

```bash
npx @wordpress/env stop
npx @wordpress/env start
```

### Destroy

Removes the containers and their Docker volumes for this environment:

```bash
npx @wordpress/env destroy
```

Use this when you want a clean WordPress/database reset.

### Common checks

List plugins:

```bash
npx @wordpress/env run cli wp plugin list
```

Activate this plugin:

```bash
npx @wordpress/env run cli wp plugin activate orderMachine
```

Check WordPress version:

```bash
npx @wordpress/env run cli wp core version
```

### How this repo uses wp-env

Current config in `.wp-env.json`:

- WordPress core: `7.0.2`
- PHP: `8.2`
- Plugin mount: current repo root as a WordPress plugin
- Extra config:
  - `SOM_ENV=wp-env`
  - `SOM_USE_DUMMY_CREDENTIALS=true`
  - `SOM_ENCRYPTION_KEY` — encrypts channel credentials at rest (dev key; Local uses its own in `wp-config.php`)

### When to use wp-env

Use `wp-env` for:

- plugin activation checks
- schema creation/migration checks
- repeatable testing against a clean database
- WP-CLI commands
- future automated checks

Use Local for:

- day-to-day browsing/debugging in wp-admin
- real OAuth work against persistent data
- visual/manual verification against your normal local site

### Troubleshooting

If `start` hangs for a long time:

1. Confirm Docker Desktop is running.
2. Run `docker ps -a` to see whether containers are being created.
3. Retry with:

```bash
npx @wordpress/env --debug start
```

If ports are already in use, stop the conflicting containers or app using `8888` / `8889`.
