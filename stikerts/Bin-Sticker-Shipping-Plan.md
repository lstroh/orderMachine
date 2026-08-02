# Bin Sticker Shipping Plan — Packaging & Postage

*Updated Aug 2026 · Covers the 100×140mm, 4-pack (1 A4 sheet) product · Companion to `Bin-Sticker-Material-Test-Plan.md` (Test 6) and `StickerBinStickersCosts_v2.xlsx` (Bin Stickers 100x140 4pk sheet).*

**Status: packaging narrowed to 2 candidates, pending final pick.** Initial handling/fit-testing has eliminated 2 of the original 4 candidates. Full Test 6 tracking (post-to-self, drop test — see `Test-Tracking-Sheet.xlsx`) is still to be logged for the two survivors before making the final call.

**Added Aug 2026 — second product shape now exists:** a landscape 140×100mm line (D01–D03) is catalogued alongside the original portrait 100×140mm line. The elimination below was tested against the portrait shape; the landscape shape's packaging fit is still unconfirmed against either survivor (see §1 note).

---

## 1. Packaging — 2 candidates remain, 2 eliminated

| Option | Status | Reason |
|---|---|---|
| Kraft envelope + tissue (flat wrap) | ❌ **Eliminated** | Product flexes/creases inside — no rigidity, confirmed by hands-on testing |
| Kraft envelope + rigid backing card | ✅ **Still in** | — |
| Padded mailer (Mail Lite A/000, 110×160mm) | ❌ **Eliminated** | Product doesn't physically fit inside — wrong size, not a performance issue |
| C6 box (163×112×20mm, rigid) | ✅ **Still in** | — |

| Surviving option | Cost/pack | Packed thickness | Royal Mail format | Protection | Presentation |
|---|---|---|---|---|---|
| Kraft envelope + rigid backing card | ~£0.09 | ~0.5mm | **Letter** (~85–91p postage) | Bend-resistant, no cushioning against knocks/crushing | Simple, slightly sturdier feel |
| C6 box (rigid) | ~£0.14 | 20mm | Large Letter (£1.55+ postage) | Best — resists bending entirely | Strongest "unboxing" moment |

**Decision still open between these two:** backing card is cheaper and qualifies for the lower Letter postage tier; the box costs more (both in materials and postage tier) but gives a stronger gift/unboxing feel. This is a cost-vs-presentation call, not a durability one — both passed the flex/fit checks the other two failed.

**Landscape (140×100mm) fit — still unconfirmed against either survivor:**
- **Kraft envelope (114×162mm), used for the backing-card candidate** — at 114mm wide, narrower than the landscape item's 140mm width. Same concern that ruled out the flat-wrap candidate for this shape; not yet physically checked with backing card in place.
- **C6 box (163×112×20mm)** — 140mm fits within 163mm length and 100mm fits within 112mm width, on paper. Not yet test-fitted with an actual landscape sticker.

Add landscape-shaped samples to the same next testing round rather than resolving separately.

### Remaining Test 6 steps for the 2 survivors
Log full transit-test data (packed thickness on a ruler, drop/flex test, post-to-self) for both remaining candidates in `Test-Tracking-Sheet.xlsx` before locking in the final choice — the elimination so far is based on initial handling/fit, not the full protocol.

---

## 2. Shipping method — Royal Mail Click & Drop

Decided: use Click & Drop (not counter stamps) for both testing and real orders, so the test reflects the actual production workflow.

**Setup:**
1. Free account at royalmail.com/business/click-and-drop — set up as a **business account** (better rates, can connect eBay/Etsy later for order import).
2. Add a payment method — pay-as-you-go by card is fine to start.
3. Create a shipment per parcel: enter sender/recipient address, select service tier, enter actual packed weight/dimensions (differs slightly by packaging type — enter the real figures so it bands correctly).

**Printing labels:**
- **Default/no extra cost: plain A4 paper + tape or glue stick.** Royal Mail officially supports this — not a workaround. Print at **100% scale** (never "fit to page," or the barcode becomes unreadable), cut along the guide lines, attach with the barcode fully visible and flat.
- **Sticker sheets** (A4, 21-per-sheet, ~£1–3 for a handful on eBay UK) also work, but are wasteful at low volume.
- **Efficient use of sticker sheets:** batch multiple shipments together and print via the **"2 or 4 labels per A4"** template.

---

## 3. Royal Mail estimated prices (checked July 2026)

| Service | Format | Price | Speed |
|---|---|---|---|
| 2nd Class (untracked) | **Letter**, up to 100g | ~85–91p (confirm exact current price in Click & Drop) | 2–3 working days |
| 2nd Class (untracked) — **standard tier** | Large Letter, up to 100g | £1.55 | 2–3 working days |
| 2nd Class (untracked) | Large Letter, up to 250g | £1.90 | 2–3 working days |
| Tracked 48 | Large Letter | £2.75 | 2–3 days, tracked |
| Tracked 24 | Large Letter | £3.65 | Aims next working day, tracked (not guaranteed) |
| Special Delivery Guaranteed by 1pm | Any | £8.75–9.95 | Guaranteed next working day, signed, compensation up to £750 |

Matches the tiering already set out in Business Plan Section 8: 2nd Class standard is free-to-customer (absorbed into item price), the others are paid upgrades.

**Correction to Business Plan Section 8:** its original table listed 2nd Class Large Letter as "~85p–£1.55" — the 85p figure is actually the standard **Letter** rate, not Large Letter (which starts at £1.55). Still needs fixing when Section 8 is rewritten.

**Weight check:** both surviving packaging options for a single 4-pack should sit comfortably under the 100g band — worth confirming on a kitchen scale once real samples are packed.

---

## 4. Shipping label cost

Separate from postage — the address/barcode label itself has a cost if printed on adhesive sticker sheet rather than plain paper:

| Item | Value |
|---|---|
| A4 sticker label sheet pack (Avery J8160, 21/sheet, 25-sheet pack) | £15.89 |
| Price per sheet | £15.89 ÷ 25 = £0.636 |
| Labels used per sheet (batching assumption: 3 shipments printed together) | 3 |
| **Cost per label** | £0.636 ÷ 3 = **~£0.21** |

**Cheaper default at low volume: plain A4 paper + tape/glue stick** (~1–2p per shipment) — Royal Mail officially supports this as a standard Click & Drop printing method, not a workaround.

---

## 5. Open items

- [x] ~~Run Test 6 initial fit/handling check~~ — flat wrap and padded mailer eliminated
- [ ] Run full Test 6 transit protocol (drop/flex + post-to-self) on the 2 surviving candidates — see `Test-Tracking-Sheet.xlsx`
- [ ] Decide backing-card vs box based on full results + cost/presentation trade-off
- [ ] **Update `StickerBinStickersCosts_v2.xlsx`** — "Selected packaging option" currently defaults to the now-eliminated flat wrap; needs updating to whichever of the 2 survivors is chosen
- [ ] Update the production guide's §9 (Package) once the final pick is made — currently still shows the eliminated flat-wrap default
- [ ] Test landscape (140×100mm) fit against both surviving candidates
- [ ] Update "Selected shipping tier" in the costs sheet once the packaging choice confirms Letter vs Large Letter eligibility
- [ ] Rewrite Business Plan Sections 7 & 8 to replace the old 30×20cm board-backed envelope with the winning option, and correct the 85p/Large Letter mislabelling noted above
- [ ] Confirm real packed weight on a scale to lock in the correct price band
