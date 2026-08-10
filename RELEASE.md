# Order Machine — release process

Private **alpha** distribution: Semantic Versioning on **`0.x.y`**, installable zip attached to a **GitHub Release**. WordPress.org is not used yet.

Current version lives in `orderMachine.php` (`Version:` header and `SOM_VERSION`).

- **Release notes:** [CHANGELOG.md](CHANGELOG.md)
- **Release catalog (three links each):** [RELEASES.md](RELEASES.md)

---

## Versioning policy

| Rule | Detail |
|------|--------|
| Scheme | SemVer `MAJOR.MINOR.PATCH` |
| Alpha | Stay on **`0.x.y`**. Major `0` means the API/plugin is still unstable. |
| Do not | Reset to `0.1.0` (history already past that; WordPress treats a lower number as a downgrade). |
| When to bump **minor** (`0.22.0` → `0.23.0`) | New features / sprint milestones |
| When to bump **patch** (`0.22.0` → `0.22.1`) | Bug fixes / small safe changes only |
| When to leave `0.x` | Until you intentionally call the plugin production-stable (`1.0.0`) |

### Version sources (must always match)

1. Plugin header `Version:` in `orderMachine.php`
2. Constant `SOM_VERSION` in `orderMachine.php`
3. Git tag `vX.Y.Z` (same numbers, `v` prefix)
4. Release asset name `orderMachine-X.Y.Z.zip` (created by CI)

### Separate: database version

`SOM_DB::DB_VERSION` tracks **schema** only. Bump it when tables/options migrations change — not on every plugin release.

---

## What a release produces

Pushing tag `v0.23.0` runs [`.github/workflows/release.yml`](.github/workflows/release.yml), which:

1. Checks that the tag version equals `Version:` and `SOM_VERSION`
2. Builds an installable zip (runtime files only)
3. Creates a GitHub Release titled **Order Machine 0.23.0** with asset **`orderMachine-0.23.0.zip`**

### Zip contents (allowlist)

| Included | Not included |
|----------|----------------|
| `orderMachine.php` | `stikerts/` (planning docs) |
| `uninstall.php` | `tests/`, `.cursor/`, `.github/` |
| `admin/` | `.git/`, `dist/`, `bin/` |
| `includes/` | logs, env files, tooling configs |

Documented exclude list: [`.distignore`](.distignore). Packaging scripts: [`bin/build-plugin-zip.ps1`](bin/build-plugin-zip.ps1), [`bin/build-plugin-zip.sh`](bin/build-plugin-zip.sh).

Zip layout (required for WordPress upload):

```text
orderMachine/
  orderMachine.php
  uninstall.php
  admin/
  includes/
```

---

## Cut a release (standard path)

Do this from a clean tree on the branch you intend to ship (usually `main`).

### 1. Finish the work

Merge or commit everything that should be in this version. Smoke-test on Local if the change is user-facing.

### 2. Bump the plugin version

In `orderMachine.php`, set **both** to the same value, for example:

```php
 * Version:           0.23.0
...
define( 'SOM_VERSION', '0.23.0' );
```

Bump `SOM_DB::DB_VERSION` in the same commit **only** if the schema changed.

### 3. Write release notes and catalog entry

The release skill (or you) should:

1. Draft notes in [CHANGELOG.md](CHANGELOG.md) under `## [X.Y.Z] - YYYY-MM-DD`
2. Add a top row in [RELEASES.md](RELEASES.md) with:
   - Release page URL
   - Zip download URL
   - Actions run URL (fill after CI; use `TBD` until then)

GitHub Release body stays the short CI install blurb; detailed notes stay in the repo.

### 4. Commit

```bash
git add orderMachine.php CHANGELOG.md RELEASES.md
# plus any other release-related files
git commit -m "Release 0.23.0"
```

### 5. Tag and push

```bash
git tag v0.23.0
git push origin HEAD
git push origin v0.23.0
```

### 6. Confirm on GitHub and finish RELEASES.md

1. Actions → workflow **Release** for tag `v0.23.0` (must be green)
2. Releases → **Order Machine 0.23.0** with `orderMachine-0.23.0.zip` attached
3. Paste the Actions run URL into [RELEASES.md](RELEASES.md) if it was still `TBD`

If the version in the tag does not match `orderMachine.php`, CI fails on purpose and does **not** create a release.

---

## Install a release on a WordPress site

1. Open the GitHub Release for that version
2. Download `orderMachine-X.Y.Z.zip`
3. In WP Admin: **Plugins → Add New → Upload Plugin**
4. Choose the zip → Install → Activate

Or replace the existing `wp-content/plugins/orderMachine/` folder with the unzipped package (keep the top-level `orderMachine/` folder name).

---

## Local zip only (no GitHub Release)

Useful to smoke-test packaging before tagging:

```powershell
powershell -File bin/build-plugin-zip.ps1
```

```bash
bash bin/build-plugin-zip.sh
```

Output: `dist/orderMachine-<version>.zip` (gitignored). Upload that zip the same way as a Release asset.

---

## Asking the agent to release

Say something like: **“Release 0.23.0”** or **“Cut a plugin release”**.

The project skill [`.cursor/skills/release-order-machine/`](.cursor/skills/release-order-machine/) tells the agent to:

- Keep `0.x` SemVer and synced version fields
- **Draft release notes** into `CHANGELOG.md` and add the three-link row in `RELEASES.md`
- Commit / tag / push **only** when you explicitly ask
- Rely on CI for the zip + GitHub Release (do not hand-upload the asset if Actions can do it)
- After CI succeeds, fill the Actions run link in `RELEASES.md`

---

## Troubleshooting

| Problem | Likely cause | Fix |
|---------|--------------|-----|
| Release workflow fails on version check | Tag `v0.23.0` but plugin still says `0.22.0` | Bump both version fields, commit, retag (delete/recreate tag only if it was never published successfully) |
| Zip missing admin screens / PHP classes | Built from wrong folder or incomplete copy | Use `bin/build-plugin-zip.*`; confirm zip root is `orderMachine/` |
| WordPress “already installed” / wrong files | Zip nested as `orderMachine/orderMachine/...` or flat files | Rebuild with the scripts; do not zip the parent `plugins` folder |
| Huge zip / includes `.git` or `stikerts` | Old broken deny-list packaging | Use current allowlist scripts; do not zip the working tree by hand |
| No GitHub Release after push | Tag not pushed, or workflow permissions | `git push origin vX.Y.Z`; check Actions logs |

---

## Related files

| File | Role |
|------|------|
| [`orderMachine.php`](orderMachine.php) | Canonical plugin version |
| [`CHANGELOG.md`](CHANGELOG.md) | Release notes |
| [`RELEASES.md`](RELEASES.md) | Version catalog + Release / Zip / Actions links |
| [`RELEASE.md`](RELEASE.md) | This document |
| [`.github/workflows/release.yml`](.github/workflows/release.yml) | Tag → zip → GitHub Release |
| [`bin/build-plugin-zip.ps1`](bin/build-plugin-zip.ps1) | Local Windows zip |
| [`bin/build-plugin-zip.sh`](bin/build-plugin-zip.sh) | Local/CI Unix zip |
| [`.distignore`](.distignore) | Documented non-runtime paths |
| [`.cursor/skills/release-order-machine/`](.cursor/skills/release-order-machine/) | Agent release checklist (writes notes + catalog) |
| [`.cursor/rules/order-machine-architecture.mdc`](.cursor/rules/order-machine-architecture.mdc) | Short versioning note for agents |
