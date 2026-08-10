---
name: release-order-machine
description: >-
  Cut an Order Machine plugin release (0.x SemVer alpha): bump Version/SOM_VERSION,
  tag vX.Y.Z, and rely on GitHub Actions for the installable zip + GitHub Release.
  Use when the user asks to release, cut a version, tag a release, publish a plugin
  zip, or create a GitHub Release for this plugin.
---

# Release Order Machine

## Rules

- Use SemVer **`0.x.y`** while alpha. Never reset to `0.1.0`.
- Keep **plugin header `Version:`** and **`SOM_VERSION`** identical.
- Git tag format: **`vX.Y.Z`** (must match those numbers).
- Do **not** hand-upload a zip to GitHub if CI can do it — push the tag and let `.github/workflows/release.yml` create the Release + asset.
- Do **not** push tags or create releases unless the user explicitly asked to release.
- Bump `SOM_DB::DB_VERSION` only when schema changes (independent of plugin version).

## Checklist

1. Confirm working tree is clean enough to release (no unrelated WIP the user did not want included).
2. Read current version from `orderMachine.php`.
3. Agree the next version with the user if they did not specify it (default: bump minor in `0.x`, e.g. `0.22.0` → `0.23.0`; use patch for fixes only).
4. Update both `Version:` and `SOM_VERSION` in `orderMachine.php`.
5. Commit when the user wants a commit (follow repo commit rules).
6. Create annotated or lightweight tag `vX.Y.Z` and push the commit + tag when the user asked to publish.
7. Point the user at the Actions run / Releases page; CI builds `dist/orderMachine-X.Y.Z.zip` and attaches it.

## Local zip only

If the user only wants a local install zip (no GitHub Release):

```powershell
powershell -File bin/build-plugin-zip.ps1
```

See [RELEASE.md](../../../RELEASE.md) for the full human process.
