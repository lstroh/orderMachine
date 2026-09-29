# Changelog

All notable changes to Order Machine are documented here.

Format inspired by [Keep a Changelog](https://keepachangelog.com/). Versioning: SemVer on **`0.x.y`** during alpha (see [RELEASE.md](RELEASE.md)).

Installable builds and the three links per version: [RELEASES.md](RELEASES.md).

## [Unreleased]

## [0.32.2] - 2026-09-29

First GitHub Release since 0.23.0. Plugin versions 0.24–0.32.1 were in `main` but not tagged.

### Added

- Workflow step instructions (plain-text defaults) with optional per-product overrides; shown read-only on order detail for every step (schema 1.11.0)
- Order **Materials used** panel: raise actual recipe material usage (Extra material usage) → stock, COGS/profit, and material budget funding; increase-only
- Clearer R&D vs Adjust stock help copy (restock pot)
- Internal products (make-to-stock): Internal channel, Produce N, linked output material credited on workflow complete (`production_output`); schema 1.12.0
- Internal products UX/guards: list badges/filters, listing exclude, recipe cycle/depth limits, deactivate with open jobs blocked, analytics exclude production, low-stock Produce on linked materials
- Order **Notes** threaded log on order detail (admin-only, append-only); schema 1.13.0
- Shipping **packages** catalogue + product goods weight / package / planned postage; order planned shipping (seeded on create) vs shipment actual variance; Product Costing includes planned shipping; schema 1.14.0
- Operator docs: multipack pack sizes = separate SKUs (own recipe + shipping)
- Make and Pack split: per-line Make workflows and Make board; order-level Pack workflow, Pack board, and ship gates (schema 1.15.0 / 1.16.0)
- Seeded **Bin Sticker Make** (Print through Cut) and **Order Pack & Ship**; Settings default Pack workflow; **Repair pack binding** for open orders missing Pack (skips legacy monolithic progress)
- Thank-you on Pack is a packing checklist item, not a batch gate

### Fixed

- Product edit package dropdown no longer labels active packages as inactive (and shows Default again); hide Planned shipping on internal Product Costing
- Pack board search no longer hits a database error
- Unmatched lines still flag needs mapping after Pack bind; the Make board no longer treats pack progress as a make chain

### Notes

- Schema stays **1.16.0** for this patch
- Plugin SemVer remains on `0.x` for alpha; do not treat this as production-stable
- Use the Release asset `orderMachine-0.32.2.zip`, not the repository source zipball

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
