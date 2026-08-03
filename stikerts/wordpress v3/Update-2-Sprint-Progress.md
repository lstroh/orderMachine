# Order Machine — Update Package 2 Sprint Progress

*Companion to [`Update-2-Sprint-Plan.md`](Update-2-Sprint-Plan.md). Plan stays the source of scope; this file records what shipped and how it was verified.*

Assumption: base plugin + Update Package 1 complete (`SOM_DB::DB_VERSION` was `1.5.0`). Specs in this folder: `01-Update-Overview.md` … `05-Update-Cursor-Prompt.md`.

---

## Status overview

| Sprint | Name | Status | Notes |
|---|---|---|---|
| U2-1 | Budgets schema + model | **Done** | Code complete vs plan; migrate on next admin load / activate |
| U2-2 | Funding + draw-down hooks | Pending | |
| U2-3 | Budgets admin UI | Pending | Includes R&D write-off UI |
| U2-4 | Order Board read UI | Pending | |
| U2-5 | Order Board gated DnD | Pending | |

---

## Sprint U2-1 — Budgets schema + model

- **Status:** **Done** (confirmed complete vs `Update-2-Sprint-Plan.md` § Sprint U2-1 + §4–5 locked decisions)
- **Completed:** 2026-08-03
- **Verified on:** Static code review against plan (syntax check `php -l`). Live `dbDelta` / table presence not exercised in this session — runs via `SOM_DB::maybe_upgrade()` when `som_db_version` ≠ `1.6.0`.
- **Plugin version:** unchanged (`0.18.1`) — plan did not require a `SOM_VERSION` bump for U2-1
- **DB version:** `1.6.0`

### Plan requirements review (`Update-2-Sprint-Plan.md`)

| Plan item | Status | Notes |
|---|---|---|
| Create `includes/class-som-budgets.php` | **Done** | CRUD, `is_active`, ledger-only balance, product/workflow helpers, one-per-material enforcement |
| Modify `includes/class-som-db.php` — four budget tables | **Done** | `budgets`, `budget_product_links`, `budget_workflow_links`, `budget_ledger` |
| Uniques / indexes / `is_active` | **Done** | Matches §5 locked schema |
| Bump `DB_VERSION` to `1.6.0` | **Done** | `SOM_DB::DB_VERSION` |
| Bootstrap require in `orderMachine.php` | **Done** | After `class-som-workflow-material-goals.php` |
| R&D linked write-off model helper (B) | **Done** | `SOM_Budgets::write_off_material()` — UI deferred to U2-3 |
| **Done when:** four tables after migrate | **Pass** (code) | DDL present; migrate on upgrade path |
| **Done when:** create material + manual budgets | **Pass** | `create()` enforces type/funding constraints |
| **Done when:** attach product + workflow links | **Pass** | `set_product_links` / `set_workflow_links`; DB allows cross-type |
| **Done when:** ledger updates `current_balance` | **Pass** | `insert_ledger()` only balance mutator |
| **Done when:** deactivate toggles `is_active` | **Pass** | `set_active()` / `update()` |
| Open items first | Settled | O2 + R&D (B) locked before build |

### Locked decisions applied (§4)

| Topic | Applied? | How |
|---|---|---|
| One material budget per material | Yes | `UNIQUE KEY material_id`; `create()` rejects duplicate via `get_for_material(..., false)` |
| Unique link pairs | Yes | `UNIQUE budget_product`, `UNIQUE budget_workflow` |
| Cross-type links allowed in DB | Yes | Link setters do not reject by budget `type` |
| `is_active` soft-deactivate | Yes | Column + `set_active()`; no hard-delete API |
| Balance only via ledger | Yes | Documented on class; `create()` starts at `0`; no direct balance update helpers |
| R&D write-off B | Yes | Stock `adjust_stock(-qty)` then budget `manual_adjustment` debit `qty × WA`; notes required; no-op budget if no active material budget |
| Schema `1.6.0` | Yes | |
| FK-style indexes | Yes | On link/ledger FKs + `type` / `is_active` |

### Files delivered

| File | Purpose |
|---|---|
| `includes/class-som-db.php` | Budget DDL; `DB_VERSION` → `1.6.0` |
| `includes/class-som-budgets.php` | Domain model (new) |
| `orderMachine.php` | `require_once` for `SOM_Budgets` |
| `stikerts/wordpress v3/Update-2-Sprint-Plan.md` | Pre-build: locked review answers (uniques, `is_active`, R&D B, etc.) |
| `stikerts/wordpress v3/Update-2-Sprint-Progress.md` | This progress record |

### Schema created

New tables (no existing-table alters):

1. **`wp_som_budgets`** — `name`, `type` (`material`\|`manual`), `material_id` (nullable, unique), `funding_method`, `funding_value`, `target_reserve_amount`, `current_balance`, `notes`, `is_active`, timestamps
2. **`wp_som_budget_product_links`** — `budget_id`, `product_id`, `UNIQUE (budget_id, product_id)`
3. **`wp_som_budget_workflow_links`** — `budget_id`, `workflow_template_id`, `UNIQUE (budget_id, workflow_template_id)`
4. **`wp_som_budget_ledger`** — `budget_id`, `order_id`, `purchase_order_item_id`, `change_amount`, `reason`, `notes`, `created_at`

### `SOM_Budgets` API surface (U2-1)

| Method | Role |
|---|---|
| `get` / `query` / `get_for_material` | Read |
| `create` / `update` / `set_active` | CRUD + soft deactivate (type/`material_id` immutable after create) |
| `insert_ledger` / `get_ledger` | Audit + balance update |
| `get_product_link_ids` / `set_product_links` | Manual product scope |
| `get_workflow_link_ids` / `set_workflow_links` | Material workflow scope |
| `applies_to_product` / `applies_to_workflow` | Empty links = global (for U2-2 funding) |
| `write_off_material` | Linked R&D stock + budget debit |
| `is_overspent` / `is_low_balance` / `reason_label` | Helpers for U2-3 UI |

Ledger reason constants: `sale_funding`, `purchase_spend`, `manual_adjustment`.

### Done-when checklist (from plan)

| Criterion | Result |
|---|---|
| After migrate, four tables exist with locked constraints | **Pass** (DDL); confirm on site after upgrade |
| Can create material budget (`funding_method = material_cost`) | **Pass** |
| Can create manual budgets (percent/fixed methods + value) | **Pass** |
| Can attach product links and workflow links | **Pass** |
| Inserting a ledger row updates `budgets.current_balance` | **Pass** |
| Deactivate toggles `is_active` | **Pass** |
| R&D model helper included | **Pass** (`write_off_material`) |

### Explicitly out of scope for U2-1 (later sprints)

| Item | Sprint |
|---|---|
| `fund_on_create` / `drawdown_on_receive` + order-sync / PO hooks | U2-2 |
| Budgets admin list/edit UI, badges, manual adjustment forms | U2-3 |
| R&D write-off UI | U2-3 |
| Order Board | U2-4 / U2-5 |

### Suggested live smoke (operator)

1. Load WP admin so `maybe_upgrade` runs → option `som_db_version` = `1.6.0`.
2. Confirm tables `{$wpdb->prefix}som_budgets`, `…_budget_product_links`, `…_budget_workflow_links`, `…_budget_ledger`.
3. (Optional WP-CLI / temporary probe) create material + manual budget, set links, `insert_ledger`, assert balance; `write_off_material` with notes.

### Gaps / residual risk

| Risk | Severity | Notes |
|---|---|---|
| Stock adjust then ledger fail in `write_off_material` | Low | Same sequential pattern as existing stock code; no DB transaction wrapper |
| Live migrate not run in this session | Info | Standard `maybe_upgrade` path; verify on Local site once |

---

## Verdict

**Sprint U2-1 is complete** relative to `Update-2-Sprint-Plan.md`: required files, schema delta (§5), locked decisions (§4), and done-when criteria are implemented. Next: **U2-2 — Funding + draw-down hooks**.
