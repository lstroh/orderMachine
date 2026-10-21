# Update — Release the last tray on purpose

*Package 14 · UP14-S12 · No schema. Operator request, settled 2026-10-21.*

---

## 1. What happens today

**Release selected** can free extra trays. While the order requires a tray and is still open, it refuses to free the last one (`som_tray_keep_one`). That is the Package 8 keep-one rule.

**Unblock step** lets you mark the current step done without a tray. It does not release the tray. The number stays held by this order.

## 2. Behaviour

On the order’s Trays panel, beside **Release selected**, add **Release anyway**.

| Button | Last tray while the order still requires one |
|---|---|
| Release selected | Still refused. Keep at least one |
| Release anyway | Releases the trays you checked, including the last one |

**Release anyway** is on the order page only. It shows when the order requires trays and is not complete. You still have to check at least one tray.

It does not mark a step done. After the last tray is freed, Mark done is blocked again until a tray is assigned or you use **Unblock step**. **Unblock step** still does not release a tray.

The tray row is kept, with `release_reason` `manual_override`. An order note records who released it and that the tray requirement was bypassed. The note does not complete a step.

A tray freed this way can be assigned to another order. This order can be given a tray again later.

No schema. The reason fits the existing `release_reason` column.

## 3. Tests and docs

`tests/sprint-up14-s12-smoke.php`

- **Release selected** on the only held tray of an open order that requires trays returns `som_tray_keep_one`.
- **Release anyway** on that tray succeeds, the tray is free, and the order note is stored.
- Mark done on that order is still blocked for lack of a tray.
- **Unblock step** does not change held trays.

`USER-REFERENCE.md`: keep-one stays on **Release selected**. **Release anyway** is the bypass.
