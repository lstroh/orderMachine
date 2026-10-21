# Update — Finished pack leaves the Pack board

*Package 14 · UP14-S11 · No schema. Operator request, settled 2026-10-21.*

---

## 1. What happens today

The Pack board lists every customer order that is not complete and has a pack template (or make progress and no template). Package 11 keeps `is_complete` off when the last pack step is marked done, then Post-ship starts. The order is still incomplete, so the card stays on the Pack board. It also appears on the Post-ship board, which is correct.

## 2. Behaviour

An order **leaves the Pack board** when it has pack progress and every pack step is done.

| Order | Pack board |
|---|---|
| A pack step is still open, including Waiting for make and Held | Stays |
| Pack was never bound (no pack progress) | Stays, so the missing-template case is still visible |
| Every pack step is done | Leaves |

The Post-ship board is unchanged. The order stays there while post-ship work remains.

The order page still shows the finished Pack section. This sprint only changes the board.

If Auto-start Post-ship is off, the order still leaves the Pack board. It shows under Post-ship → Not started once a shipment is recorded, which is the existing rule.

This narrows the Package 6 Pack board rule. That board showed open orders until the order itself was complete. Finished pack is no longer “still in pack.”

No schema. No change to Make or Post-ship membership.

## 3. Tests and docs

`tests/sprint-up14-s11-smoke.php`

- An order with a pack step still pending is returned by `SOM_Pack::query_board`.
- An order whose pack steps are all done is not returned.
- That same order is still returned by the active Post-ship board query.
- An order with no pack progress is still returned by the Pack board.

`USER-REFERENCE.md` and `FEATURES-AND-TESTING.md`: the Pack board drops an order when its pack steps are all done.
