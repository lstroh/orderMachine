# Update — Seed Rewrite & Existing-Site Migration

*Package 6 · No dedicated schema beyond `02`. Self-contained.*

---

## 1. What this adds

1. **New seed shape** for dummy catalogue: product **make** template ends at ready-to-pack; separate **Pack** template owns pack → ship → review.  
2. **Operator notes** for existing Local / wp-env sites that already have the monolithic Bin Sticker workflow.  
3. Removal of thank-you **batch** from the seeded pack path.

## 2. Settled rules

| Topic | Decision |
|---|---|
| Seed rewrite | **Yes** for new installs / Restore seed data |
| Existing sites | Document migrate; optional repair helper — **open item** |
| Thank-you batch | Not used on Pack seed |
| Review reminder | On **Pack** template after Ship (make seed has none); make templates may still add review later |

## 3. Seeded make template — Bin Sticker Production (make)

Name may stay `Bin Sticker Production` or become `Bin Sticker Make` — **open item** (recommend rename to **Bin Sticker Make** and keep old name only if migrate maps it).

| Order | Step | Gates |
|---|---|---|
| 1 | Print | Manual |
| 2 | Confirm print | Confirmation `print_vs_request` |
| 3 | Dry | Timer 15 min |
| 4 | Laminate | Manual |
| 5 | Cut | Manual |

**Stops here** — line is ready to pack.  
No Confirm pack, Pack, Confirm address, Ship, Thank-you, Review on this template.  
`kind = make`. Assign to seed sellable product `BIN-SET-4PK`.

## 4. Seeded Pack template — Order Pack & Ship

New template (e.g. name **Order Pack & Ship**), `kind = pack`, stored as `som_pack_workflow_template_id`.

| Order | Step | Gates |
|---|---|---|
| 1 | Confirm pack | Confirmation checklist: **all lines + thank-you** (extend/replace `packing_items`) |
| 2 | Confirm address | Confirmation `shipping_address` |
| 3 | Select package | Manual confirm **or** soft gate enforced in app when `shipping_package_id` set — **open item** (recommend app gate + optional dedicated step label “Package”) |
| 4 | Ship | Manual; existing shipment-required rule |
| 5 | Review reminder | Timer 7 days + manual |

No `batch_group_id` on thank-you. Thank-you is part of Confirm pack checklist.

## 5. Other seed entities

| Entity | Change |
|---|---|
| Batch groups | `thank_you_card` / `shipping_label` rows may still be seeded for backwards compatibility; **not** attached to Pack seed steps |
| Internal logo sticker | Unchanged make-only / Produce N |
| Listings / materials | Unchanged |
| Convert-on-activate thank-you→batch | Must **not** rewrite Pack template steps into batches |

## 6. Existing Local / wp-env sites (migrate notes)

Document in USER / FEATURES / this file:

1. Upgrade plugin (Package 6 release).  
2. Ensure Pack template exists (seed restore **or** Settings → create/assign Pack template).  
3. Edit **Bin Sticker Production**: remove Pack / address / Ship / Thank-you / Review steps (or switch product to new Make template).  
4. Open orders mid-flight on old monolithic progress: finish manually or run optional repair — **open item**.  
5. Disable reliance on thank-you **Batches** for new orders; clear collecting thank-you batches as needed.

**Recommendation:** ship a WP-CLI or admin “Repair pack binding” that for open non-internal orders without `pack_workflow_template_id` assigns the default Pack template and creates pack progress **without** destroying historical make rows — details in sprint plan.

## 7. Out of scope

- Auto-rewriting every custom (non-seed) workflow on customer sites  
- Deleting batch group rows  
- Re-introducing 4-up thank-you PDF as required pack gate  

## 8. Open items

1. Rename make template vs keep `Bin Sticker Production`.  
2. Package selection as its own step vs app-only gate.  
3. Strength of migrate repair tool (docs-only vs one-click).  
4. Whether Restore seed data recreates Pack template idempotently when make template already exists.
