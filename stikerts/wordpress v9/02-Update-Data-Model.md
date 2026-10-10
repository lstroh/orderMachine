# Update Package 8 — Data Model Changes

*Trays. Baseline: plugin **v0.32.2** (+ Package 7 schema if already applied).*

---

## A. Site setting

| Option / setting | Type | Notes |
|---|---|---|
| `som_tray_max` (or Settings field) | INT | Max tray number in the pool. **Default 10.** Allowed range soft-default **1–20** (UI label emphasises 1–10; higher allowed if needed). |

Tray numbers in use are the integers **1 … max** inclusive. No row per empty tray required — occupancy is derived from open assignments.

---

## B. Workflow template flag

### `workflow_templates.uses_trays`

| Column | Type | Notes |
|---|---|---|
| `uses_trays` | TINYINT(1) NOT NULL DEFAULT 0 | When 1, starting the first step of this template requires tray assignment (unless already held via transfer). |

Applies to **make** and **pack** kinds.

---

## C. Tray assignments

### `wp_som_tray_assignments` (new)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `tray_number` | SMALLINT UNSIGNED NOT NULL | 1 … site max |
| `order_id` | BIGINT UNSIGNED NOT NULL | |
| `order_item_id` | BIGINT UNSIGNED NULL | Set for **make** (per-line); NULL for **pack** (order-level) |
| `workflow_template_id` | BIGINT UNSIGNED NOT NULL | Template that holds the tray |
| `workflow_kind` | ENUM('make','pack') NOT NULL | Denormalised for queries |
| `status` | ENUM('held','released') NOT NULL DEFAULT 'held' | |
| `assigned_at` | DATETIME NOT NULL | |
| `released_at` | DATETIME NULL | |
| `release_reason` | VARCHAR(32) NULL | `workflow_complete` / `manual` / `transferred` |
| `assigned_by_user_id` | BIGINT UNSIGNED NULL | |
| `released_by_user_id` | BIGINT UNSIGNED NULL | |

**Indexes**

- UNIQUE partial / application rule: at most one **held** row per `tray_number` (enforce in PHP + unique index on `(tray_number)` WHERE status=held if MySQL version allows; else unique on tray_number among held via careful updates).  
- KEY `order_id`, KEY `order_item_id`, KEY `(status, tray_number)`.

**Rules**

- Assign: insert `held` only if no other `held` row for that `tray_number`.  
- Release: set `status=released`, `released_at`, reason.  
- Transfer make→pack: release make rows with reason `transferred` **or** update the same rows to pack scope (prefer **close make rows + open pack rows** for the same tray numbers in one transaction so history is clear).

---

## D. No tray catalogue table (v1)

Empty trays are implicit (number not in any `held` assignment). Optional later: nicknames (“Tray 3 — blue”).

---

## E. Timer early-release audit

No new table required if progress already stores enough context. Prefer one of:

### Option A (recommended) — columns on progress rows

On `order_item_step_progress` (make) and `order_step_progress` (pack):

| Column | Type | Notes |
|---|---|---|
| `timer_released_early_at` | DATETIME NULL | Set when admin releases timer before `timer_ends_at` |
| `timer_released_early_by` | BIGINT UNSIGNED NULL | WP user id |

Clear or leave set when step completes (leave set for audit).

### Option B — order note

Append a system order note: “Timer released early on {step} by {user} at {time}.”  
Use **in addition** to Option A if you want the Notes panel to show it without opening progress internals.

**Settled:** log who/when (Option A required; Option B soft-default **yes** — also write a short order note).

---

## F. Open items (data)

1. Hard max ceiling (10 vs 20) for `som_tray_max`. **Default:** allow 1–20, seed/docs say 10.  
2. Reducing max while higher trays are held — **block reduce** until those released, or allow and keep held orphans (recommend **block**).  
3. Whether released tray rows are retained forever (audit) — **yes, retain**.  
4. Timer early release: Option B order note — **default yes**.  
