# Update Package 8 — Data Model Changes

*Trays (order-level) + timer early-release audit. Baseline: plugin **v0.32.2** (+ Package 7 if applied).*

---

## A. Site setting

| Setting | Type | Notes |
|---|---|---|
| `som_tray_max` | INT | Max tray number. **Default 10.** Allowed **1–20** (docs emphasise 10). |

Tray numbers = integers **1 … max**. Occupancy = open (held) assignments.

---

## B. Workflow template flag

### `workflow_templates.uses_trays`

| Column | Type | Notes |
|---|---|---|
| `uses_trays` | TINYINT(1) NOT NULL DEFAULT 0 | If **any** make template on the order’s lines or the bound Pack template has this set, the order **requires** tray assignment. |

---

## C. Tray assignments (order-level)

### `wp_som_tray_assignments` (new)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `tray_number` | SMALLINT UNSIGNED NOT NULL | 1 … site max |
| `order_id` | BIGINT UNSIGNED NOT NULL | Holder — **order only** |
| `status` | ENUM('held','released') NOT NULL DEFAULT 'held' | |
| `assigned_at` | DATETIME NOT NULL | |
| `released_at` | DATETIME NULL | |
| `release_reason` | VARCHAR(32) NULL | `order_complete` / `manual` |
| `assigned_by_user_id` | BIGINT UNSIGNED NULL | |
| `released_by_user_id` | BIGINT UNSIGNED NULL | |

**No** `order_item_id`, **no** `workflow_template_id` on the assignment — trays are not per-line or per-workflow-instance.

**Indexes / rules**

- At most one **held** row per `tray_number` (global).  
- KEY `order_id`, KEY `(status, tray_number)`.  
- Assign: insert `held` only if tray free.  
- Release: `status=released` + reason.  
- Retain released rows for audit.

---

## D. No tray catalogue table (v1)

Empty trays = numbers with no held assignment.

---

## E. Timer early-release audit

On `order_item_step_progress` (make) and `order_step_progress` (pack):

| Column | Type | Notes |
|---|---|---|
| `timer_released_early_at` | DATETIME NULL | |
| `timer_released_early_by` | BIGINT UNSIGNED NULL | WP user id |

Also write a short **order note** (soft default yes).

---

## F. Open items (data)

1. Max range 1–20 — **default yes**.  
2. Lowering max while higher trays held — **block** until released.  
3. Released tray rows retained — **yes**.  
4. Order note on timer early release — **default yes**.  
