# Update — Order detail shipment and trays

*Package 14 · UP14-S5 and UP14-S6 · No schema. Operator requests, settled 2026-10-19.*

---

## 1. Shipment panel (UP14-S5)

On every order detail (customer and Internal), the **Shipment** panel is a collapsed disclosure. It starts closed on each visit. Nothing remembers open or closed.

| State | Closed heading |
|---|---|
| No shipment row | **Shipment** |
| Shipment saved | **Shipment**, the status label, and the tracking number when one is stored |

The form inside is the one that already exists: carrier, service, ship date, postage, pack weight, tracking, Click & Drop reference, proof, save, and push tracking. Saving and pushing stay allowed, including on an Internal order.

**Planned shipping** and **Shipping address** stay as they are. **Pack & Ship** stays hidden when the channel is Internal.

Make, Pack & Ship, and Post-ship stay expanded.

## 2. Tray panel placement (UP14-S6)

Move the existing **Trays** panel (`#som-trays`) to immediately above **Make**. Held trays, free-tray checkboxes, assign, and release stay the same controls.

The short warning inside Make stays, with the link to `#som-trays`, when a tray is still required and none is held.

## 3. Who must hold a tray (UP14-S6)

**Package 8 today:** `SOM_Trays::order_requires_trays()` is true when the bound Pack template has **Uses trays**, or when a **sellable** line’s make template has **Uses trays**. Lines whose product is internal are skipped. An Internal Produce N job therefore does not require a tray.

**This package:** also require a tray when the order’s channel is **Internal** and that product’s make template has **Uses trays**.

A customer order still skips an internal component line. That line is not made on the customer order (Package 6).

Unchanged around that:

| Rule | Stays |
|---|---|
| At least one tray held before **Mark done** and before a board advance | Yes. `trays_gate_ok()` already does this once `order_requires_trays()` is true |
| Cannot release the last held tray while the order is open and trays are required | Yes. The release check uses the same predicate |
| **Unblock** on the order detail | Yes. Package 8 still lets that one **Mark done** through without a tray |
| Unblock on boards | Still deferred. Do not add it |
| Trays overview page | Still deferred |
| Post-ship **Uses trays** | Still forced off |
| Auto-release when the order completes | Unchanged, including Internal |

## 4. Tests

`tests/sprint-up14-s5-smoke.php`

- Order detail markup for an order contains a Shipment disclosure that is not open by default.
- When a shipment row has a tracking number, the summary string includes that number.

`tests/sprint-up14-s6-smoke.php`

- An Internal order whose make template has **Uses trays** fails `trays_gate_ok()` until one tray is held, then passes.
- A customer order whose only internal component uses a tray workflow still passes `trays_gate_ok()` with no tray held.
- A sellable line with **Uses trays** still requires a tray (Package 8 regression).

## 5. Docs

`USER-REFERENCE.md` order detail: Shipment starts closed; Trays sits above Make; an Internal job with **Uses trays** needs a tray before Mark done. `FEATURES-AND-TESTING.md`: the same two notes.
