# Changelog

All notable changes to Order Machine are documented here.

Format inspired by [Keep a Changelog](https://keepachangelog.com/). Versioning: SemVer on **`0.x.y`** during alpha (see [RELEASE.md](RELEASE.md)).

Installable builds and the three links per version: [RELEASES.md](RELEASES.md).

## [Unreleased]

### Added

- Workflow step instructions (plain-text defaults) with optional per-product overrides; shown read-only on order detail for every step (schema 1.11.0)

## [0.23.0] - 2026-09-16

### Added

- Create test order panel on the Orders list (External-channel rows without calling REST)
- Outbound shipment records (carrier, service, postage, tracking); Ship waits for that record; optional tracking push to eBay/Etsy (schema 1.9.0)
- Workflow confirmation checklists (print / packing / shipping address) that block Mark done and Board drag until saved (schema 1.10.0)
- Live timer unlock on order detail and Board (`Timer ready`) so Mark done / drag do not wait for cron; optional browser notifications

### Notes

- Plugin SemVer remains on `0.x` for alpha; do not treat this as production-stable
- Use the Release asset `orderMachine-0.23.0.zip`, not the repository source zipball

## [0.22.0] - 2026-08-10

First tagged alpha release with GitHub Releases packaging.

### Added

- Tag-triggered GitHub Actions workflow that builds an installable plugin zip and creates a GitHub Release
- Local zip scripts (`bin/build-plugin-zip.ps1`, `bin/build-plugin-zip.sh`) using a runtime allowlist (`orderMachine.php`, `uninstall.php`, `admin/`, `includes/`)
- Release process documentation (`RELEASE.md`) and agent release skill

### Notes

- Plugin SemVer remains on `0.x` for alpha; do not treat this as production-stable
- Use the Release asset `orderMachine-0.22.0.zip`, not the repository source zipball
