# Plugin Update Package 16 — Overview

*Update set · Required photos on workflow steps, blocking Mark done, bypassed by Unblock step. Planning open for further operator items. Soft defaults below are from 2026-11-02 and can still be corrected. Self-contained.*

---

## Assumption

- Packages **7–15** are on `origin/main`. Plugin **0.36.0** (schema **1.26.0**), tag `v0.36.0`. Package 15 passed wp-env on 2026-11-02 (pull request #65, merge `06dec24`) and was released as 0.36.0.
- `origin/main` is that release (`84452e5` when this plan was written). The later commits are the 0.36.0 release and the release-catalog link. They are not unfinished Package 15 work.
- Standing architecture: `.cursor/rules/order-machine-architecture.mdc` and docs under `stikerts/wordpress/` plus the v8–v16 update packages.
- This package’s first sprint adds two columns and one table. Schema moves from **1.26.0** to **1.27.0** in that sprint. Later sprints stay on **1.27.0** unless they add a column or table.
- Plain PHP admin. SemVer stays on **`0.x.y`**. Sprint work adds an Unreleased changelog line and does not bump `Version` / `SOM_VERSION`.

## Operator requests

1. **Required step photos.** Some workflow steps need one or more pictures before Mark done. The operator can bypass that block. The site runs on the Local site **ordermachine**. Pictures are stored with the site, under `wp-content/uploads/order-machine/step-photos/`.

## Settled behaviour

| Topic | Decision |
|---|---|
| Where the flag lives | On the workflow step: **Require photo**, plus an optional label (what to photograph). Any Make, Pack, or Post-ship step. |
| What it blocks | Mark done, until that step’s progress has at least one saved JPEG or PNG. A photo-only step waits. It does not auto-complete. |
| Bypass | The existing **Unblock step** on order detail. It still skips every gate on that step, records who and which gates, and does not Mark done by itself. A missing photo is the gate key `photo`. |
| How many | At least one. No named slots. No maximum count. Each file is 5 MB or smaller. |
| Whose photo | Make: one set per order line (item progress). Pack and Post-ship: one set per order (order progress). |
| After Mark done | Thumbnails stay on that step. Add and remove are only on the current step, before it is done. |
| Files | WordPress attachments, parent `0`. The plugin stores the attachment id. Directory `uploads/order-machine/step-photos/YYYY/MM/`. |
| Batch | A batch step cannot also require a photo. Photo may combine with manual confirm, a checklist, a copy command, and a timer. |
| Boards | No uploader on the boards. Drag stays blocked while the photo gate fails. Unblock on boards stays deferred. |
| Product override | None. Instructions and tasks keep their own per-product overrides. |

Spec: [`02-Update-Step-Photos.md`](02-Update-Step-Photos.md).

## Local QA site

The Local site **ordermachine** runs this plugin folder. There is no separate deploy.

- **0.36.0 cannot do this yet.** Workflow steps have manual confirm, checklist, copy command, timer, and batch. None of them ask for a picture. Shipment proof is a separate JPEG, PNG, or PDF on the shipment row, stored in the normal uploads month folder.
- After a sprint branch is checked out in this folder, reload the Local site’s wp-admin. Start the site in Local first. New photos appear under that site’s `wp-content/uploads/order-machine/step-photos/`.
- Implementation chats still do not run `npx @wordpress/env`. Automated smokes wait for the package’s final wp-env prompt.

## What's in this update

1. Required photos on a workflow step, stored as attachments and enforced on order detail (UP16-S1).

Further operator items can still be appended before implementation starts.

## Recommended build order

1. **UP16-S1** — Schema, the step editor, the order-detail uploader, and the Mark done / Unblock gate. One sprint, because the checkbox and the uploader are the same feature.

## Release

Suggest **0.37.0** after S1. Do not tag until the operator asks for a release. Later sprints in this package, if any, take the next `0.x.y` and still do not tag.
