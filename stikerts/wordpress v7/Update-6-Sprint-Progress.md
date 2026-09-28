# Update Package 6 — Sprint Progress

*Companion to [`Update-6-Sprint-Plan.md`](Update-6-Sprint-Plan.md).*

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| UP6-S1 | Per-line make + Make board | In progress | Schema 1.15.0 / plugin 0.30.0 |
| UP6-S2 | Pack board + gates | Not started | |
| UP6-S3 | Seed rewrite + migrate | Not started | |

---

## UP6-S1 — Per-line make + Make board

- **Status:** Implementing
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

*(fill after wp-env smoke)*
