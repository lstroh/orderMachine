# Bin Sticker Shipping Plan — Packaging & Postage

*Updated Sep 2026 · Covers the 140×100mm, 4-pack (1 A4 sheet) product · Companion to `Bin-Sticker-Material-Test-Plan.md` (Test 6), `TestTrackingSheet.xlsx` and `StickerBinStickersCosts_v4.xlsx` (Bin Stickers 100x140 4pk sheet).*

**Status (Sep 2026): packaging confirmed as the A6/C6 board-backed "Do Not Bend" envelope (114×162mm, ~£0.25 sourced) — still to be validated by Test 6.** The two candidates that survived the early handling/fit check (kraft envelope + rigid backing card, and C6 box) become comparators. The board-backed envelope was not one of the four candidates in the original Test 6 set, so its packed thickness, weight, drop/flex and post-to-self results are still to be logged in `TestTrackingSheet.xlsx`.

**Resolved Sep 2026 — product shape:** the baseline is the landscape 140×100mm card only; all 25 catalogued designs (D01–D25) are landscape. The earlier portrait 100×140mm line no longer exists, so the August portrait-vs-landscape fit concern is removed.

---

## 1. Packaging — decision and comparators

| Option | Status | Reason |
|---|---|---|
| Kraft envelope + tissue (flat wrap) | ❌ **Eliminated** | Product flexes/creases inside — no rigidity, confirmed by hands-on testing |
| Kraft envelope + rigid backing card | Comparator (survived the early check; fallback) | — |
| Padded mailer (Mail Lite A/000, 110×160mm) | ❌ **Eliminated** | Product doesn't physically fit inside — wrong size, not a performance issue |
| C6 box (163×112×20mm, rigid) | Comparator (survived the early check) | — |
| A6/C6 board-backed "Do Not Bend" envelope (114×162mm) | ✅ **Confirmed choice (Sep 2026)** | Rigid board backing built into the envelope; functional, not gift-style. Not yet measured or transit-tested |

**Measured so far (logged in `TestTrackingSheet.xlsx`, 3–5 Aug 2026):**

| Option | Cost/pack | Packed thickness | Weight | Royal Mail format | Protection | Presentation |
|---|---|---|---|---|---|---|
| Kraft envelope + rigid backing card | ~£0.09 | **4mm** (earlier estimate was ~0.5mm) | 36g | **Letter** (£0.91) | Bend-resistant, no cushioning against knocks/crushing | Simple, slightly sturdier feel |
| C6 box (rigid) | ~£0.14 | Logged as 3mm — looks like an error for a 20mm box | 48g | Large Letter (£1.55) | Best — resists bending entirely | Strongest "unboxing" moment |
| A6/C6 board-backed envelope | ~£0.25 (sourced; R&D 50-pack works out at £0.147) | **Not yet measured** | Not yet weighed | Letter if under 5mm and 100g, otherwise Large Letter (£1.55) | Board backing resists bending; no cushioning against crushing | Functional |

Flat wrap and the padded mailer were logged as "doesn't fit" (eliminated). Drop/flex and post-to-self results are not yet logged for any candidate.

**Decision (Sep 2026):** the board-backed envelope is the confirmed choice. Test 6 still has to show that it (a) stays under 5mm and 100g — the backing-card comparator already measured 4mm, close to the limit, so this is not a given — and (b) survives the drop/flex and post-to-self tests. Getting the Letter rate (£0.91) rather than Large Letter (£1.55) is worth £0.64 a pack: at the £4 eBay price that is the difference between about £0.80 and £0.16 profit per pack (Business Plan §9). If the board-backed envelope fails on thickness or protection, the backing-card comparator is the fallback (Letter-eligible at 4mm/36g, £0.09); the box costs more in both materials and postage.

**Landscape (140×100mm) fit — check:** a C6 envelope (114×162mm) opens on its long edge, so a 140×100mm card should sit inside lengthwise (140mm < 162mm, 100mm < 114mm). The August worry that 114mm is narrower than 140mm looks mistaken, but it has not been physically test-fitted — check with a real card and the board backing in place. The C6 box (163×112×20mm) fits on paper, also unchecked.

### Remaining Test 6 steps
Log thickness, weight, drop/flex and post-to-self for the **board-backed envelope** and, for comparison, the two comparators in `TestTrackingSheet.xlsx`. Note the workbook's Test 6 tab currently has four rows and no board-backed row — add a fifth. The elimination so far is based on initial handling/fit, not the full protocol.

---

## 2. Shipping method — Royal Mail Click & Drop

Decided: use Click & Drop (not counter stamps) for both testing and real orders, so the test reflects the actual production workflow.

**Setup:**
1. Free account at royalmail.com/business/click-and-drop — set up as a **business account** (better rates, can connect eBay/Etsy later for order import).
2. Add a payment method — pay-as-you-go by card is fine to start.
3. Create a shipment per parcel: enter sender/recipient address, select service tier, enter actual packed weight/dimensions (differs slightly by packaging type — enter the real figures so it bands correctly).

**Printing labels:**
- **Default/no extra cost: plain A4 paper + tape or glue stick.** Royal Mail officially supports this — not a workaround. Print at **100% scale** (never "fit to page," or the barcode becomes unreadable), cut along the guide lines, attach with the barcode fully visible and flat.
- **Sticker sheets** also work. The SmithPackaging A4 self-adhesive labels bought for testing are one full-A4 label per sheet (199.6×289.1mm, 100 sheets, £9), so print via the 2 or 4 labels per A4 template and cut them apart, or use one per sheet (wasteful).
- **Efficient use of sticker sheets:** batch multiple shipments together and print via the **"2 or 4 labels per A4"** template.

---

## 3. Royal Mail estimated prices (checked July 2026; Letter figure per cost sheet v4, Sep 2026)

| Service | Format | Price | Speed |
|---|---|---|---|
| 2nd Class (untracked) | **Letter**, up to 100g (max 5mm thick) | £0.91 (cost sheet figure — confirm in Click & Drop) | 2–3 working days |
| 2nd Class (untracked) — **standard tier** | Large Letter, up to 100g | £1.55 | 2–3 working days |
| 2nd Class (untracked) | Large Letter, up to 250g | £1.90 | 2–3 working days |
| Tracked 48 | Large Letter | £2.75 | 2–3 days, tracked |
| Tracked 24 | Large Letter | £3.65 | Aims next working day, tracked (not guaranteed) |
| Special Delivery Guaranteed by 1pm | Any | £8.75–9.95 | Guaranteed next working day, signed, compensation up to £750 |

Matches the tiering already set out in Business Plan Section 8: 2nd Class standard is free-to-customer (absorbed into item price), the others are paid upgrades.

**Correction to Business Plan Section 8 (done Sep 2026):** its original table listed 2nd Class Large Letter as "~85p–£1.55" — the 85p figure was the standard **Letter** rate, not Large Letter (which starts at £1.55). Fixed in the rewritten Section 8.

**Weight check:** logged weights for the two comparators are 36g (backing card) and 48g (box) — both well under 100g. The board-backed envelope still needs weighing.

---

## 4. Shipping label cost

Separate from postage — the address/barcode label itself has a cost if printed on adhesive sticker sheet rather than plain paper:

| Item | Value |
|---|---|
| A4 self-adhesive address label pack (SmithPackaging, 100 sheets, 1 label per sheet) | £9 |
| Price per sheet | £9 ÷ 100 = £0.09 |
| Labels used per sheet (cost sheet assumption) | 1 |
| **Cost per label** | **£0.09** |

If you print 3 shipments on one sheet and cut them apart, the cost falls to about £0.03. The earlier plan used an Avery J8160 pack (21 per sheet, 25-sheet pack, £15.89 → about £0.21 a label); it was replaced by the SmithPackaging pack in Sep 2026.

**Cheaper default at low volume: plain A4 paper + tape/glue stick** (~1–2p per shipment) — Royal Mail officially supports this as a standard Click & Drop printing method, not a workaround.

---

## 5. Open items

- [x] ~~Run Test 6 initial fit/handling check~~ — flat wrap and padded mailer eliminated
- [x] ~~Log packed thickness and weight for the backing card (4mm, 36g) and box (48g)~~ — 3–5 Aug 2026
- [ ] Check the 3mm logged for the C6 box (it is a 20mm box)
- [ ] **Measure packed thickness and weight of the A6/C6 board-backed envelope** — must be under 5mm and 100g for the Letter rate the cost sheet assumes
- [ ] Run drop/flex and post-to-self on the board-backed envelope (and the comparators) — add a board-backed row to `TestTrackingSheet.xlsx`
- [x] ~~Update cost sheet packaging and shipping selections~~ — done in v4 (A6/C6 £0.25, Letter £0.91); revisit if the measurement puts the envelope over 5mm
- [x] ~~Rewrite Business Plan Sections 7 & 8~~ — done Sep 2026 (A6/C6 replaces the old C4 assumption; 85p/Large Letter mislabel corrected)
- [ ] Update the production guide's §9 (Package) once Test 6 confirms the final pick
- [ ] Physically test-fit the landscape 140×100mm card in the A6/C6 envelope with the board backing
- [ ] Multi-item orders: packaging for more than one product or set is still unresolved (Letter/Large Letter thickness limit)
- [ ] Medium (~C5) and Large (~C4) envelopes are not yet sourced or costed
- [ ] Reconcile the £0.25 sourced envelope price with the £0.147 R&D pack price, and confirm £0.25 does not already include the thank-you card (costed separately at £0.16 in the cost sheet)

