---
name: release-order-machine
description: >-
  Cut an Order Machine plugin release (0.x SemVer alpha): bump Version/SOM_VERSION,
  write CHANGELOG + RELEASES entries (agent drafts release notes), tag vX.Y.Z, and
  rely on GitHub Actions for the installable zip + GitHub Release. Use when the user
  asks to release, cut a version, tag a release, publish a plugin zip, update release
  notes, or create a GitHub Release for this plugin.
---

# Release Order Machine

## Rules

- Use SemVer **`0.x.y`** while alpha. Never reset to `0.1.0`.
- Keep **plugin header `Version:`** and **`SOM_VERSION`** identical.
- Git tag format: **`vX.Y.Z`** (must match those numbers).
- Do **not** hand-upload a zip to GitHub if CI can do it — push the tag and let `.github/workflows/release.yml` create the Release + asset.
- Do **not** push tags or create releases unless the user explicitly asked to release.
- Bump `SOM_DB::DB_VERSION` only when schema changes (independent of plugin version).
- **You (the agent) write the release notes** in `CHANGELOG.md` and add the row in `RELEASES.md`. Show the drafted notes to the user before committing if they did not already approve wording.
- Leave the GitHub Release body as the CI template (generic install blurb). Detailed notes live in-repo only.

## Docs to maintain

| File | Purpose |
|------|---------|
| [CHANGELOG.md](../../../CHANGELOG.md) | Human release notes (Added / Changed / Fixed / Notes) |
| [RELEASES.md](../../../RELEASES.md) | Catalog: version, date, **three links** (Release, Zip, Actions run) |
| [RELEASE.md](../../../RELEASE.md) | Process documentation |

Repo base: `https://github.com/lstroh/orderMachine`

### Three links (every RELEASES.md row)

1. **Release:** `https://github.com/lstroh/orderMachine/releases/tag/vX.Y.Z`
2. **Zip:** `https://github.com/lstroh/orderMachine/releases/download/vX.Y.Z/orderMachine-X.Y.Z.zip`
3. **Actions run:** `https://github.com/lstroh/orderMachine/actions/runs/<run_id>` (only known after CI starts/finishes)

## Checklist

1. Confirm working tree is clean enough to release (no unrelated WIP the user did not want included).
2. Read current version from `orderMachine.php` and the previous tag (`git describe --tags --abbrev=0` or latest in `RELEASES.md`).
3. Agree the next version with the user if they did not specify it (default: bump minor in `0.x`; use patch for fixes only).
4. **Draft release notes** (see below) and update:
   - `CHANGELOG.md` — move items out of `[Unreleased]` if any; add `## [X.Y.Z] - YYYY-MM-DD` with sections
   - `RELEASES.md` — insert a new top table row with Release + Zip URLs; use `TBD` for Actions run until after CI
5. Update both `Version:` and `SOM_VERSION` in `orderMachine.php`.
6. Commit when the user wants a commit (include version bump + CHANGELOG + RELEASES). Follow repo commit rules.
7. Create tag `vX.Y.Z` and push the commit + tag when the user asked to publish.
8. After the Release workflow succeeds, resolve the Actions run URL and **update the RELEASES.md Actions cell** (replace `TBD`). Commit that link fix when the user wants it (often a tiny follow-up commit).
9. Point the user at Release / Zip / Actions links.

## How to draft release notes

1. Collect changes since the previous tag:
   - `git log --oneline <prev_tag>..HEAD`
   - skim meaningful product/docs/tooling diffs (ignore noise)
2. Write concise bullets under Keep-a-Changelog-style headings as needed:
   - `### Added` / `### Changed` / `### Fixed` / `### Notes`
3. Prefer user-facing outcomes over file lists. Mention DB schema bumps explicitly when `SOM_DB::DB_VERSION` changes.
4. If the user already provided notes, use their wording; otherwise draft and confirm briefly.
5. Date the heading with the release day (`YYYY-MM-DD`).

### CHANGELOG entry shape

```markdown
## [X.Y.Z] - YYYY-MM-DD

### Added

- …

### Fixed

- …
```

### RELEASES.md row shape

```markdown
| [X.Y.Z](CHANGELOG.md#xyz---yyyy-mm-dd) | YYYY-MM-DD | [Release](https://github.com/lstroh/orderMachine/releases/tag/vX.Y.Z) | [orderMachine-X.Y.Z.zip](https://github.com/lstroh/orderMachine/releases/download/vX.Y.Z/orderMachine-X.Y.Z.zip) | [Run](https://github.com/lstroh/orderMachine/actions/runs/RUN_ID) |
```

Anchor: GitHub-style slug from heading — e.g. `0.22.0` + `2026-08-10` → `#0220---2026-08-10`.

After push, find `RUN_ID` via the Actions UI, `gh run list`, or `https://api.github.com/repos/lstroh/orderMachine/actions/workflows/release.yml/runs?per_page=1`.

## Local zip only

If the user only wants a local install zip (no GitHub Release):

```powershell
powershell -File bin/build-plugin-zip.ps1
```

Skip tagging; still optional to draft notes if they ask.

See [RELEASE.md](../../../RELEASE.md) for the full human process.
