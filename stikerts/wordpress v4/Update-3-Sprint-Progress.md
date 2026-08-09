# Order Machine — Update Package 3 Sprint Progress

*Companion to [`Update-3-Sprint-Plan.md`](Update-3-Sprint-Plan.md). Plan stays the source of scope; this file records what shipped and how it was verified.*

Assumption: base plugin + Update Package 1 (materials/costing) + Update Package 2 (budgets/board) in place (`SOM_DB::DB_VERSION` was `1.6.0`, `SOM_VERSION` was `0.18.1`). Specs in this folder: `01`–`05`.

**Sequencing (from plan):** Platform Selling Fees first, then Analytics Dashboard.

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| 1 | Fee schema + Channel Fee Estimates UI | **Done** | Verified on wp-env 2026-08-09 |
| 2 | Platform fee sync + order/recurring UI | Not started | |
| 3 | Product Costing + budgets fee-aware | Not started | |
| 4 | Analytics Dashboard | Not started | |

---

## Sprint 1 — Fee schema + Channel Fee Estimates UI

- **Status:** **Done** (confirmed complete vs `Update-3-Sprint-Plan.md` § Sprint 1 + §1 O1/O3/O11 + clarifying answers locked in chat)
- **Completed:** 2026-08-09
- **Verified on:** wp-env (`http://localhost:8888`) via `tests/sprint-up3-s1-smoke.php`; also re-ran existing smoke suite
- **Plugin version:** `0.19.0`
- **DB version:** `1.7.0`

### Plan requirements review (`Update-3-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Create `admin/views/channel-fee-estimates.php` | **Done** | Dedicated list + create/edit view (not Settings sub-section) |
| Create `includes/class-som-channel-fee-estimates.php` | **Done** | CRUD + idempotent seed + tier match helpers |
| Modify `includes/class-som-db.php` — 3 tables + tier columns; bump version | **Done** | `channel_fee_estimates` (+ min/max/`is_enabled`), `order_platform_fees`, `recurring_platform_expenses`; `1.6.0` → `1.7.0` |
| Modify `orderMachine.php` — require new class | **Done** | Require + `ensure_defaults()` on activate/init; `SOM_VERSION` → `0.19.0` |
| Modify `admin/class-som-admin-menu.php` — menu + handlers + caps | **Done** | Submenu **Channel Fee Estimates**; save/delete handlers; `manage_options` |
| Settings sub-view (optional) | **N/A** | Chose dedicated submenu (locked answer #1) |
| **Done when:** Migration creates three tables; version bumps | **Pass** | Smoke + `som_db_version` = `1.7.0` on wp-env |
| **Done when:** eBay/Etsy rows seeded (tiered `per_order_fee`; optional ads on) | **Pass** | Seed + smoke asserts |
| **Done when:** Admin can view/edit components (incl. min/max) | **Pass** | Full CRUD UI |
| **Done when:** No fee sync / Costing yet | **Pass** | Explicitly out of scope |
| Open items first | Settled | O1, O3; O11 doc-only |

### Locked decisions applied (planning chat + plan §1–§2)

| Topic | Decision | Applied? |
|---|---|---|
| O1 eBay per-order tiers | Under £10 → £0.30; ≥ £10 → £0.40 via `order_value_min`/`max` | Yes |
| Tier band semantics | Half-open: min inclusive, max exclusive; seed `(NULL, 10)` + `(10, NULL)` | Yes |
| O3 optional ads | Include by default (Promoted Listings / Offsite Ads `is_enabled = 1`) | Yes |
| UI placement | Dedicated submenu **Channel Fee Estimates** | Yes |
| Etsy payment processing | Two rows: `payment_processing` (4%) + `payment_processing_fixed` (£0.20) | Yes |
| Optional components toggle | `is_enabled` per row | Yes |
| CRUD scope | Full add / edit / delete | Yes |
| Seed behaviour | Idempotent — insert missing only; never overwrite user edits | Yes |
| `vat_on_fees` | Seed as percent 20 with note; application deferred to Sprint 3 | Yes |
| O11 “4 tables” typo | Implement **3** tables from `02` + tier columns | Yes |
| PK/FK types | `bigint(20) unsigned` to match existing `SOM_DB` | Yes |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | Fee DDL; `DB_VERSION` → `1.7.0`; call `ensure_defaults()` after migrate |
| `includes/class-som-channel-fee-estimates.php` | Domain CRUD, seed, tier match, URL helpers (new) |
| `admin/views/channel-fee-estimates.php` | List by channel + create/edit form (new) |
| `admin/class-som-admin-menu.php` | Submenu, render, save/delete handlers, CSS enqueue page |
| `orderMachine.php` | Require class; ensure on activate/init; notices page allowlist; `0.19.0` |
| `tests/sprint-up3-s1-smoke.php` | Schema / seed / tiers / CRUD / idempotency smoke (new) |
| `tests/sprint-u5-smoke.php` | Relax DB assert to `>= 1.5.0` (suite compatibility) |
| `tests/sprint-u6-smoke.php` | Same |
| `tests/sprint-u7-smoke.php` | Same |
| `tests/sprint11-smoke.php` | Prefer seed product with workflow (pre-existing flake fix) |
| `stikerts/wordpress v4/Update-3-Sprint-Progress.md` | This progress record |

### Schema created

New tables (no existing-table alters beyond additive CREATE):

1. **`wp_som_channel_fee_estimates`** — `channel_id`, `fee_component`, `rate_type` (`percent`\|`fixed`), `rate_value`, `order_value_min` / `order_value_max` (NULL = open end; both NULL = always), `is_enabled`, `notes`, timestamps
2. **`wp_som_order_platform_fees`** — actual per-order fee lines (unused until Sprint 2)
3. **`wp_som_recurring_platform_expenses`** — non-order-linked fees (unused until Sprint 2)

### Seed defaults

**eBay:** `final_value_fee` 12.8%; `per_order_fee` £0.30 / £0.40 (tiers); `regulatory_fee` 0.4%; `promoted_listings` 3% enabled.

**Etsy:** `listing_fee` £0.16; `transaction_fee` 6.5%; `payment_processing` 4%; `payment_processing_fixed` £0.20; `regulatory_fee` 0.32%; `vat_on_fees` 20% (note: on fee totals); `offsite_ads` 15% enabled.

### `SOM_Channel_Fee_Estimates` API surface (Sprint 1)

| Method | Role |
|---|---|
| `get` / `list_all` / `list_grouped_by_channel` | Read |
| `create` / `update` / `delete` | Full CRUD |
| `ensure_defaults` / `find_matching` | Idempotent seed |
| `matches_order_value` | Half-open tier check (for later estimate math) |
| `list_url` / `detail_url` / `delete_url` | Admin URLs |
| `format_rate` / `format_tier` | Display helpers |

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| Migration creates the three tables; version bumps cleanly | **Pass** (wp-env `som_db_version` = `1.7.0`) |
| eBay/Etsy estimate rows seeded (tiered `per_order_fee`; optional ads included) | **Pass** |
| Admin can view/edit estimate components (including min/max) | **Pass** (full CRUD + `is_enabled`) |
| No fee sync / Costing changes required yet | **Pass** |

### Explicitly out of scope for Sprint 1 (later sprints)

| Item | Sprint |
|---|---|
| `som_sync_platform_fees` cron + Finances/Ledger API | 2 |
| Order detail actual fee breakdown | 2 |
| Recurring platform expenses UI | 2 |
| OAuth scope expand + reconnect messaging | 2 |
| Product Costing estimate vs actual £/% | 3 |
| Budgets `percent_of_profit` fee-aware | 3 |
| Analytics Dashboard / Chart.js | 4 |

### Verification (wp-env, 2026-08-09)

```bash
npx @wordpress/env start
npx @wordpress/env run cli wp plugin activate orderMachine
npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s1-smoke.php
```

**Sprint 1 smoke:** `PASS — Update Package 3 Sprint 1 smoke` (schema, seed counts, tier edges at £9.99/£10, dual Etsy processing, optional ads enabled, idempotent seed, CRUD, inverted-tier rejection, seed preserves edits).

**Existing suite also run:** u1–u7, sprint9 (+ callback), sprint10, sprint11 (after product-selection fix), bugfix-001-002, seed-remove-restore — all exit 0 / PASS.

### Suggested live check (Local / operator)

1. Load WP admin so `maybe_upgrade` runs → option `som_db_version` = `1.7.0`.
2. Open **Order Machine → Channel Fee Estimates**.
3. Confirm eBay/Etsy seeded rows; edit a rate / toggle Enabled; add and delete a custom component.
4. Confirm Settings / Costing / order detail unchanged (no Sprint 2–3 UI yet).

### Gaps / residual risk

| Item | Notes |
|---|---|
| `dbDelta` “table already exists” noise | Seen on wp-env when re-running upgrade path for some tables (including pre-existing budgets); tables present and version set — monitor on Local upgrade |
| Estimate → £/% application | Intentionally Sprint 3; `vat_on_fees` is stored only |
| Fee sync tables empty | Expected until Sprint 2 |

---

## Sprint 2+ 

Not started. Follow `Update-3-Sprint-Plan.md` §5 in order; do not re-open settled open items in plan §1–§2 without confirmation.
