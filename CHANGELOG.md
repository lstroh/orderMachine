# Changelog

All notable changes to Order Machine are documented here.

Format inspired by [Keep a Changelog](https://keepachangelog.com/). Versioning: SemVer on **`0.x.y`** during alpha (see [RELEASE.md](RELEASE.md)).

Installable builds and the three links per version: [RELEASES.md](RELEASES.md).

## [Unreleased]

### Added

- Materials can be exported, and a CSV can be previewed and imported from the Materials list. A name that already exists is created as Name (2). New rows start at stock 0. Made-in-house materials are left out of the export. An unknown or ambiguous supplier fails that row only
- Workflows can be exported, and a CSV can be previewed and imported from the Workflows list. One row per step. A name that already exists is created as Name (2). A Post-ship file without a Package arrived step writes no template. An unknown batch group key fails that workflow only
- Products can be exported, and a CSV can be previewed and imported from the Products list. One row per recipe line. A name that already exists is created as Name (2) and keeps the file SKU. An unknown material, workflow, or package fails that product. An internal product gets a new output material. Listings are not created
- Add material can start that material's restock budget from an unchecked checkbox. If the budget cannot be created, the material stays and the error is shown on the material screen. The materials CSV does not create a budget

## [0.34.0] - 2026-10-19

Packages 12–13: Produce N on the boards, editor helpers, clones, and eBay/Amazon orders and listings entered without a live sync.

### Added

- Products can be cloned from the list or the edit screen. The copy is named “X (copy)”, keeps the same workflow, recipe, step overrides, and budget links, and opens on its edit screen. SKU is left blank. Listings, orders, and stock are not copied. An internal clone gets its own output material
- Workflow templates can be cloned from the list or the editor. The copy is named “X (copy)”, keeps the same steps, goals, and budget links, and opens in the editor. Products, orders, and Pack / Post-ship defaults stay on the original. A Post-ship template with no Package arrived step cannot be cloned
- Copy command templates have an Insert placeholder dropdown that puts `{{key}}` at the cursor
- Step instruction editors (workflow steps and product overrides) have a Markdown toolbar for bold, italic, lists, headings, links, and inline code
- Amazon is a channel with no OAuth. **Add order** on Orders creates an eBay or Amazon order by hand (separate from Create test order). A matched line gets a workflow, stock reservation, and budget funding the same way as Sync now. The same channel and order number cannot be added twice
- **Import orders** reads an eBay Seller Hub orders report, an Amazon order report, or an Order Machine template. Preview shows recognised orders and row errors; nothing is saved until Import. An order number that already exists for that channel is skipped. A failed order does not undo orders that already saved
- **Import listing report** on Listings reads an eBay active-listings report or an Amazon inventory report. A row is saved only when its SKU matches a product and that listing id is not already mapped. Price and quantity are stored on the new map and are not sent to eBay or Amazon

### Fixed

- A Post-ship workflow cannot be saved unless a step uses Confirmation checklist **Package arrived**, and a failed save writes nothing. Existing templates without that step stay in place and are flagged; Settings only warns (BUG-005)
- Orders list and the Needs workflow filter treat per-line make progress as a workflow, so an open Produce N job is not flagged **No workflow** and Status shows the current make step when there is no order-level step
- Order detail Make shows the step list, Mark done, instructions, tasks, and copy command for Internal Produce N lines that already have make progress. A customer-order internal line stays **Pack-ready (internal)** with no make controls

### Notes

- Schema stays **1.24.0**
- Plugin SemVer remains on `0.x` for alpha; do not treat this as production-stable
- Use the Release asset `orderMachine-0.34.0.zip`, not the repository source zipball

## [0.33.0] - 2026-10-12

Packages 7–11: package materials and suggestion, trays and Unblock, Copy command, workflow timing, and post-ship.

### Added

- Make step gate UX: confirmation checklists sit under the current step, and a step with no gates warns instead of showing Done before any work (BUG-003)
- Shipping package materials (BOM) and a Package source on Materials used when a package is reserved (schema 1.17.0)
- Package suggestion for quantity 1 versus 2+ or mixed lines, plus multi-line test orders (schema 1.18.0)
- Order trays: site maximum, assign and release on the order (schema 1.19.0)
- Tray required before complete, an Unblock step on order detail that bypasses gates, and tray badges on the boards (schema 1.20.0)
- Copy command on a workflow step, and structured eBay order fields (schema 1.21.0)
- Step instructions as Markdown, rendered to safe HTML on order detail
- Optional step task lists that do not gate Mark done; confirmation checklists still do (schema 1.22.0)
- Planned and actual workflow timing on order detail, including span totals (schema 1.23.0)
- Analytics for planned, timer, and actual time, including steps over plan (actual greater than 1.2× planned)
- Post-ship workflow: Pack Ship does not complete a customer order; auto-start binds and enters post-ship; confirming arrival with a shipment completes the order while later post-ship steps can still run; a product post-ship override wins over the site default; Internal Produce N skips post-ship (schema 1.24.0)
- Post-ship board (unstarted and active), aftercare seed, and operator docs. Seed Pack no longer includes Review reminder

### Changed

- Local, API, and n8n script steps are removed; seed steps use Copy command instead

### Notes

- Schema moves from **1.16.0** to **1.24.0**
- Plugin SemVer remains on `0.x` for alpha; do not treat this as production-stable
- Use the Release asset `orderMachine-0.33.0.zip`, not the repository source zipball

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
