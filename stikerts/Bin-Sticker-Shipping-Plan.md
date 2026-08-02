# Bin Sticker Shipping Plan — Packaging & Postage

*Prepared July 2026 · Covers the 100×140mm, 4-pack (1 A4 sheet) product · Companion to `Bin-Sticker-Material-Test-Plan.md` (Test 6) and `StickerBinStickersCosts_v2.xlsx` (Bin Stickers 100x140 4pk sheet — tab name corrected Aug 2026, was a stale "100x150" label left over from before the sticker size was reduced; see §3 of that sheet's Product Spec, cell C6).*

**Status: packaging decision pending Test 6 results.** This file documents the four candidates under test and the Click & Drop shipping workflow agreed so far. Update the "chosen packaging" section once transit-test results are in.

**Added Aug 2026 — second product shape now exists:** a landscape 140×100mm line (D01–D03: Cottage Bloom Banner, Regency Double Flourish, Manor Frame Classic — see `bin_sticker_products_gallery_data.md`) is now catalogued as "Proof approved" alongside the original portrait 100×140mm line. Every packaging candidate below was sized and tested only against the portrait shape — the landscape shape's packaging fit is now folded into the same pending Test 6 decision (see the note under §1) rather than resolved separately.

---

## 1. Packaging — four candidates under test

Superseded: the old C4 board-backed envelope in Business Plan Section 7 was sized for the original 30×20cm sticker — no longer appropriate at 100×140mm. These four replace it, pending Test 6.

| Option | Cost/pack | Packed thickness | Royal Mail format | Protection | Presentation | Source |
|---|---|---|---|---|---|---|
| Kraft envelope + tissue (flat wrap) | ~£0.08 | <1mm | **Letter** | Lowest — sheet can flex/crease in a stuffed mailbag | Nice, simple — matches most Etsy sticker sellers | C6 kraft envelope, ~£6–7/50 (Amazon UK) |
| Kraft envelope + rigid backing card (no padding) | ~£0.09 | ~0.5mm | **Letter** | Bend-resistant (card stops flexing), no cushioning against knocks/crushing | Simple, slightly sturdier feel | Envelope as above + backing card made in-house from cardstock offcuts (near-zero marginal cost, per Business Plan Section 7) |
| Padded mailer (Mail Lite A/000, 110×160mm) | ~£0.12 | ~5–10mm | Large Letter | Middle — cushions knocks, stays flat | Less "gift," more "arrived safely" | ~£11.66/100, confirmed Royal Mail Large Letter size |
| C6 box (163×112×20mm, rigid) | ~£0.14 | 20mm | Large Letter | Best — resists bending entirely | Strongest "unboxing" moment, matches gift-feel packaging in Business Plan Section 7 | £6.54–6.89/50 (Forms Plus) |

**Competitor context:** most bin-sticker and decal sellers on Etsy use a plain kraft envelope, not a box — a box would be a genuine differentiator here, not just matching the bar (see Competitor Research file).

**Why a fourth candidate was added:** checked directly whether a genuinely thin padded/bubble mailer exists that could squeeze under the 5mm Letter limit — it doesn't. Padding (bubble or foam) is, by definition, thick enough to cushion impact, which is exactly why every padded format checked is built well past 5mm; even a small flat item in a slim padded envelope typically fails the Letter test on padding thickness alone. A rigid backing card sidesteps this: it resists bending without adding meaningful thickness, so it's the one option that might combine Letter-rate postage with better-than-plain-wrap protection.

**Royal Mail format limits:** Letter = 240×165×5mm, up to 100g. Large Letter = 353×250×25mm, up to 750g. The box's 20mm depth stays comfortably under the Large Letter ceiling; the padded mailer's ~5-10mm thickness rules out Letter format but easily clears Large Letter.

**Landscape (140×100mm) fit — unconfirmed, folded into the pending packaging decision:** all four candidates above were sized against the portrait 100×140mm sheet. The landscape D01–D03 line (140mm wide × 100mm tall) hasn't been checked against any of them:
- **C6 kraft envelope (114×162mm) — very likely too narrow.** At 114mm wide, it's narrower than the landscape item's 140mm width, so the envelope itself (used for both the flat-wrap and backing-card candidates) probably doesn't fit without folding the sticker — which would defeat the point of either candidate. Not yet physically checked, but the dimensions don't look compatible.
- **C6 box (163×112×20mm) — plausibly compatible, but not confirmed.** 140mm fits within the box's 163mm length and 100mm fits within its 112mm width, on paper. This has not been test-fitted with an actual landscape sticker, so treat it as unconfirmed rather than a working solution.
- **Padded mailer (Mail Lite A/000, 110×160mm)** — not checked either; 110mm width looks tight against a 140mm-wide item on the same logic as the kraft envelope, but this hasn't been measured against a real sample.

No packaging decision should be made for the landscape line ahead of the portrait line's own Test 6 results — when Test 6 samples are packed, add landscape-shaped samples to the same round rather than resolving this separately, since both shapes now need transit-testing against whichever candidate(s) survive.

### Test 6 — how the decision gets made
Extended into `Bin-Sticker-Material-Test-Plan.md`: pack one sample of each material (Cricut/Stickiply) in each of the four packaging types, post to yourself via 2nd Class, measure actual packed thickness, and run a drop/flex test in parallel. Check for creasing, corner damage, and laminate lifting on arrival. Whichever performs best relative to its cost (and postage tier) becomes the standing choice.

**Testing shopping list (~£33–35 total, covers first weeks of real orders too):**
- C6 kraft envelopes, 50-pack — ~£6–7 (covers both the flat-wrap and backing-card candidates)
- Tissue paper, small bulk pack — ~£4
- Mail Lite A/000 padded mailers, 100-pack — ~£11.66
- Forms Plus C6 postal box, 50-pack — £6.54–6.89
- Backing card: made in-house from cardstock offcuts, no extra purchase
- Postage: 4 test parcels × 2nd Class (Letter or Large Letter depending on candidate) — ~£4.80–6.20

---

## 2. Shipping method — Royal Mail Click & Drop

Decided: use Click & Drop (not counter stamps) for both testing and real orders, so the test reflects the actual production workflow.

**Setup:**
1. Free account at royalmail.com/business/click-and-drop — set up as a **business account** (better rates, can connect eBay/Etsy later for order import).
2. Add a payment method — pay-as-you-go by card is fine to start.
3. Create a shipment per parcel: enter sender/recipient address, select service tier, enter actual packed weight/dimensions (differs slightly by packaging type — enter the real figures so it bands correctly).

**Printing labels:**
- **Default/no extra cost: plain A4 paper + tape or glue stick.** Royal Mail officially supports this — not a workaround. Print at **100% scale** (never "fit to page," or the barcode becomes unreadable), cut along the guide lines, attach with the barcode fully visible and flat.
- **Sticker sheets** (A4, 21-per-sheet, ~£1–3 for a handful on eBay UK) also work, but are wasteful at low volume — you pay for 21 labels to use 1 (~30–45p wasted per shipment vs ~1–2p for paper).
- **Efficient use of sticker sheets:** batch multiple shipments together and print via the **"2 or 4 labels per A4"** template — worthwhile once shipping more than one parcel at a time, not for single one-off shipments.
- For the 3 test parcels specifically: since all 3 ship together, this is the ideal case for sticker sheets — select all 3, print "4 per A4," cut apart, no waste.

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

Matches the tiering already set out in Business Plan Section 8: 2nd Class standard is free-to-customer (absorbed into item price), the others are paid upgrades. Special Delivery is reserved for bundled/high-value orders (£20+), not the standard single-pack tier.

**Correction to Business Plan Section 8:** its original table listed 2nd Class Large Letter as "~85p–£1.55" — the 85p figure is actually the standard **Letter** rate, not Large Letter (which starts at £1.55). Worth fixing when Section 8 is rewritten.

**Format eligibility by packaging (the real lever for reducing shipping cost):**
- Flat wrap and envelope + backing card candidates are thin enough to plausibly qualify for the cheaper **Letter** rate (~85–91p) instead of Large Letter (£1.55) — a ~45% saving per shipment. Needs confirming with an actual ruler measurement once Test 6 samples are packed; must stay under 5mm and 100g.
- Padded mailer and box are both structurally Large Letter — no way around this, since their protection comes specifically from added thickness.

**Weight check:** all four packaging options for a single 4-pack should sit comfortably under the 100g band — worth confirming on a kitchen scale once real samples are packed, since crossing into the 100–250g band adds 35p (Large Letter) or moves a Letter-format item into Large Letter territory.

---

## 4. Shipping label cost

Separate from postage — the address/barcode label itself has a cost if printed on adhesive sticker sheet rather than plain paper:

| Item | Value |
|---|---|
| A4 sticker label sheet pack (Avery J8160, 21/sheet, 25-sheet pack) | £15.89 |
| Price per sheet | £15.89 ÷ 25 = £0.636 |
| Labels used per sheet (batching assumption: 3 shipments printed together) | 3 |
| **Cost per label** | £0.636 ÷ 3 = **~£0.21** |

**Cheaper default at low volume: plain A4 paper + tape/glue stick** (~1–2p per shipment) — Royal Mail officially supports this as a standard Click & Drop printing method, not a workaround. Print at 100% scale, never "fit to page." Sticker sheets only pay off once you're batching several shipments onto one sheet at a time (e.g. the "2 or 4 per A4" Click & Drop template) — otherwise you're paying for 21 labels to use 1.

---

## 5. Open items

- [ ] Run Test 6 (transit stress, 4 packaging candidates × 2 materials) — see `Bin-Sticker-Material-Test-Plan.md`
- [ ] Measure actual packed thickness of each candidate — confirms which qualify for Letter-rate postage
- [ ] Update "Selected packaging option" in `StickerBinStickersCosts_v2.xlsx` (Bin Stickers 100x140 4pk sheet) once results are in — currently defaulted to the cheapest option (kraft envelope + tissue) as a placeholder
- [ ] Once landscape packaging is tested (see landscape note in §1), confirm whether the same "Selected packaging option" cell can serve both shapes or whether the landscape line needs its own selection
- [ ] Update "Selected shipping tier" in the same sheet once the packaging choice confirms Letter vs Large Letter eligibility
- [ ] Rewrite Business Plan Sections 7 & 8 to replace the old 30×20cm board-backed envelope with the winning option from this file, and correct the 85p/Large Letter mislabelling noted above
- [ ] Confirm real packed weight on a scale to lock in the correct price band
