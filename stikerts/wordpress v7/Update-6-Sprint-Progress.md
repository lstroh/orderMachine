# Update Package 6 — Sprint Progress

*Companion to [`Update-6-Sprint-Plan.md`](Update-6-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP6-S1 | Per-line make + Make board | Code complete | Schema 1.15.0 / plugin 0.30.0; wp-env smoke pending (no Docker in agent VM) |
| UP6-S2 | Pack board + gates | Code complete | Schema 1.16.0 / plugin 0.31.0; wp-env smoke pending (no Docker in agent VM) |
| UP6-S3 | Seed rewrite + migrate | Code complete | Plugin 0.32.0; schema unchanged; wp-env smoke pending (no Docker in agent VM) |

---

## UP6-S1 — Per-line make + Make board

- **Status:** Code complete (PR)
- **Schema:** `1.15.0` (`order_item_step_progress`, `workflow_templates.kind`)
- **Plugin:** `0.30.0`

### Delivered

- Per-line make assign on create (`SOM_Item_Make`); interim truncate before pack-ish steps
- Internal sellable-channel lines: pack-ready, no make rows; Produce N: make on Make board
- Make Board (repurposed `som-orders-board`) — line cards, workflow filter columns
- Order detail Make section; legacy order-level panel when present
- Make templates reject batch groups; products can only assign `kind=make`
- Smoke: `tests/sprint-up6-s1-smoke.php`

### Soft defaults applied

O1, O3, O4, O7–O11, O19 (see sprint plan).

### Verification

- PHP lint on changed files: pass
- wp-env smoke: **not run in cloud agent** (`docker` missing). Run locally:

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up6-s1-smoke.php
```

---

## UP6-S2 — Pack workflow, Pack board, gates

- **Status:** Code complete (PR)
- **Schema:** `1.16.0` (order pack columns; `shipments.pack_weight_grams`)
- **Plugin:** `0.31.0`

### Delivered

- Default Pack template option (`som_pack_workflow_template_id`) on Settings; bind on create for non-Internal (soft flag if unset — O14)
- Pack progress in `order_step_progress`; Pack Board (`som-pack-board`) with Waiting for make + Held
- Ship gates: make-ready, hold, package, checklist+thank-you, shipment row; `is_complete` via pack finish (Internal Produce N still completes on make)
- Packing checklist thank-you tick; packed-by/at stamp once (O5); optional pack weight; browser print pack list
- Order detail Pack panel (package, hold, buyer note, print)
- Smoke: `tests/sprint-up6-s2-smoke.php`

### Soft defaults applied

O2, O5, O12, O14, O15, O17, O21, O22 (see sprint plan).

### Verification

- PHP lint on changed files: pass
- wp-env smoke: **not run in cloud agent** (`docker` missing). Run locally:

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up6-s2-smoke.php
```

---

## UP6-S3 — Seed rewrite + migrate

- **Status:** Code complete (PR)
- **Schema:** unchanged (`1.16.0`)
- **Plugin:** `0.32.0`

### Delivered

- Seed make → **Bin Sticker Make** (Print…Cut); lookup by option / legacy name **Bin Sticker Production** (O16)
- Seed Pack → **Order Pack & Ship** (`som_seed_pack_workflow_id`); set `som_pack_workflow_template_id` when empty (O18)
- Pack seed steps: Confirm pack (checklist+thank-you) → Confirm address → Package → Ship → Review reminder; no thank-you batch
- `convert_thankyou_steps` skips Pack templates (O20)
- Settings **Repair pack binding** for open non-Internal unbound orders; skips legacy order-level progress (O6)
- Docs: migrate steps; manual thank-you batch clear (O13)
- Smoke: `tests/sprint-up6-s3-smoke.php`

### Soft defaults applied

O6, O13, O16, O18, O20 (see sprint plan).

### Verification

- PHP lint on changed files: pass
- wp-env smoke: **not run in cloud agent** (`docker` missing). Run locally:

```bash
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up6-s3-smoke.php
```
