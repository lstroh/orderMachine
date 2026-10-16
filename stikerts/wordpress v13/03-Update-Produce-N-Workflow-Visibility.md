# Update — Produce N workflow visibility (BUG-004)

*Package 12 · No schema. Driven by operator testing (Produce N → Make Board vs Orders list / order detail).*

---

## 1. What operators reported

After **Products → Produce N** on an internal product with make template + recipe:

- Make Board shows the line card; drag advances steps.  
- Orders list Flags: **No workflow**; Status often just **Open**.  
- Order detail Make section does not show the step list / Mark done (looks like there is no workflow). Channel is **Internal**.

## 2. Intended model (Packages 4 + 6 — do not overturn)

| Case | Make progress | Pack / Post-ship | Order detail Make UI |
|---|---|---|---|
| Sellable line on customer order | Yes (if template) | Pack bound; later post-ship | Full per-line Make UI |
| Internal product line on **customer** order | **No** — always pack-ready | Pack includes line as stock-in-box | Badge **Pack-ready (internal)** only |
| **Produce N** (`channel=internal`) | **Yes** — make-to-stock job | **Never** Pack / Post-ship board | **Should** show full Make UI (this package) |

Settled in Package 6: Produce N stays on Make / Internal only; completes when make finishes; never Pack board. That stays locked.

## 3. Likely causes (code pointers)

### A. False **No workflow** on Orders list

`admin/views/orders-list.php` treats needs-workflow as:

- not complete / not cancelled, and  
- `orders.current_step_id` empty, and  
- `progress_count` from **`order_step_progress` only** (`SOM_Orders::query`).

Produce N has rows in **`order_item_step_progress`** and leaves `current_step_id` null (no Pack bind). Same gap affects the status filter `needs_workflow` in `SOM_Orders::query`.

### B. Order detail hides Make controls for all internal products

`admin/views/order-detail.php` Make section:

- Badge path: if `product_is_internal` → always **Pack-ready (internal)** (even mid-job).  
- Step list: skipped when `$is_internal_l || empty( $line_progress )`.

So even when `$line_progress` is populated for Produce N, the UI never renders it.

### C. Assign path is fine

`SOM_Item_Make::assign_on_create` skips internal products on non-Internal orders, then separately assigns make for Internal-channel Produce N lines. Make Board already uses item progress. Do **not** “fix” by removing item progress or by binding Pack to Internal orders.

## 4. Settled fix goals

| Goal | Detail |
|---|---|
| Honest list Flags | **No workflow** only when there is no item make progress **and** no order-level pack/post-ship progress (and order still open) |
| Honest Status | Prefer order-level current step name when set; else current **in-progress make** step name from item progress when present; else **Open** |
| Filter parity | Status filter **Needs workflow** uses the same definition as the badge |
| Produce N detail | Internal channel + internal product + item make progress → render Make step list / Mark done / instructions / tasks / copy-command like sellable lines |
| Customer internal lines | Unchanged: no make progress, **Pack-ready (internal)** badge, no Make controls |
| Boards | Make Board behaviour unchanged; still no Pack board for Internal |

## 5. Soft defaults

| Topic | Default |
|---|---|
| List query shape | Add item-progress awareness in `SOM_Orders::query` (counts / current make step subquery or lightweight attach) — prefer one place shared by badge + filter |
| Multi-line make current step | For list Status, if multiple in-progress make lines, show the **earliest** current step name (lowest step_order among in-progress lines); if mixed templates, still show that name (board remains source of truth for multi-line) |
| Produce N detail badge | While make incomplete: show current step / in-progress (not Pack-ready). When make complete: **Ready** / complete styling consistent with sellable make-complete (not Pack-ready — there is no pack) |
| Unassigned notice | Do not show “No make workflow” for Internal Produce N that already has item progress; keep existing `unassigned_reason` for true gaps |
| Retroactive data | No migration; open Produce N orders already have item progress — UI/list fix is enough |
| Schema | None |

## 6. Work items (detail)

1. Reproduce: Produce N → confirm `order_item_step_progress` rows exist while list shows **No workflow**.  
2. Fix `SOM_Orders::query` + list Flags / Status / `needs_workflow` filter.  
3. Fix order-detail Make branch for Internal-channel lines with progress.  
4. Smoke: `tests/sprint-up12-s1-smoke.php` (list/filter) and `tests/sprint-up12-s2-smoke.php` (detail UI helpers / assign+flags) or one combined smoke if S2 lands immediately after S1.  
5. Docs: USER-REFERENCE / USER-GUIDE / FEATURES — Produce N: advance on Make Board **or** order detail Make; list no longer false-flags.

## 7. Out of scope

- Changing when Produce N credits linked material  
- Showing Pack / Post-ship panels on Internal orders  
- Unblock on Make Board  
- Repair tools for historical false badges (display-only fix)  
