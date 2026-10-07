# Bin Sticker Shipping Plan — Packaging & Postage

*Updated 6 Oct 2026 · Covers the 140×100mm, 4-pack (1 A4 sheet) product · Companion to `Bin-Sticker-Material-Test-Plan.md` (Test 6), `TestTrackingSheet.xlsx`, `StickerBinStickersCosts_v13.xlsx` (Bin Stickers 100x140 4pk sheet) and `sticker_wrap_README.md` (personal wrap).*

**Update (Oct 2026): the owner reports the postage-limit checks (wrapped-pack gauge reading, AKAR box weights, post-to-self) are done, and the launch is on Letter postage at £5.00. Results are now logged in `TestTrackingSheet.xlsx` (6 Oct 2026): the envelope passed the Post Office counter check as a Letter, and the envelope and AKAR box both passed drop/flex and post-to-self (arrived in 2 days, no creasing, corner damage or shifting). The logged weights were taken without the wrap; wrapped weights and the exact thickness in mm are not logged; still open here is the AKAR box price in the cost sheet's "Packing Oct 2026" tab. eBay orders use eBay's label tool (see Section 2).**

**Status (Oct 2026): packaging confirmed as the A6/C6 board-backed "Do Not Bend" envelope (114×162mm, ~£0.25 sourced) for single packs, plus the AKAR white A6/C6 Large Letter box for multi-pack orders (2–4 packs). Test 6 passed (Oct 2026): Post Office counter check, drop/flex and post-to-self.**

**New Oct 2026 — inside the envelope:** each pack now goes in a **personal paper wrap** (`sticker_wrap.py`: the customer's address, a preview of their design and the logo, closed with a reclosable tab, no glue or tape). Each sticker also carries two small **peel markers** on its back (a "PEEL THIS FIRST" pill and an arrow, Oct 2026), which add well under 1g a pack. The envelope is closed with the **40mm Doorstep Studio seal sticker** on regular sticker paper under **Sellotape Zero Plastic** clear tape (plant-based cellulose, £5.94 for 24mm × 30m). The wrap adds about **4.1g** (120gsm) and roughly **0.5–0.6mm** of thickness (about 0.15mm a layer, up to 4 layers where the flaps overlap). **None of the measurements below include the wrap yet — re-measure before relying on the Letter rate or the 4-pack box cap.** The two candidates that survived the early handling/fit check (kraft envelope + rigid backing card, and C6 box) become comparators. The board-backed envelope was not one of the four candidates in the original Test 6 set, so it was tested separately; its counter check, drop/flex and post-to-self results are now logged in `TestTrackingSheet.xlsx` (all passed, Oct 2026).

**Resolved Sep 2026 — product shape:** the baseline is the landscape 140×100mm card only; all 25 catalogued designs (D01–D25) are landscape. The earlier portrait 100×140mm line no longer exists, so the August portrait-vs-landscape fit concern is removed.

---

## 1. Packaging — decision and comparators

| Option | Status | Reason |
|---|---|---|
| Kraft envelope + tissue (flat wrap) | ❌ **Eliminated** | Product flexes/creases inside — no rigidity, confirmed by hands-on testing |
| Kraft envelope + rigid backing card | Comparator (survived the early check; fallback) | — |
| Padded mailer (Mail Lite A/000, 110×160mm) | ❌ **Eliminated** | Product doesn't physically fit inside — wrong size, not a performance issue |
| C6 box (163×112×20mm, rigid) | Comparator (survived the early check) | — |
| A6/C6 board-backed "Do Not Bend" envelope (114×162mm) | ✅ **Confirmed choice (Sep 2026) — single packs** | Rigid board backing built into the envelope; functional, not gift-style. Measured Oct 2026: 31g packed, thickness reported under 4mm — passed the Post Office counter check as a Letter (Oct 2026); exact mm not logged |
| AKAR white A6/C6 Large Letter box | ✅ **Multi-pack choice (Oct 2026), 2–4 packs** | 23mm external height (2mm under the 25mm limit); 82g with 4 loose packs (unwrapped). Passed drop/flex and post-to-self (Oct 2026). Cap at 3 wrapped packs until 4 wrapped are weighed (see wrap impact below). Price per box still to be added |

**Measured so far (logged in `TestTrackingSheet.xlsx`, 3–5 Aug 2026):**

| Option | Cost/pack | Packed thickness | Weight | Royal Mail format | Protection | Presentation |
|---|---|---|---|---|---|---|
| Kraft envelope + rigid backing card | ~£0.09 | **4mm** (earlier estimate was ~0.5mm) | 36g | **Letter** (£0.91) | Bend-resistant, no cushioning against knocks/crushing | Simple, slightly sturdier feel |
| C6 box (rigid) | ~£0.14 | Logged as 3mm — looks like an error for a 20mm box | 48g | Large Letter (£1.55) | Best — resists bending entirely | Strongest "unboxing" moment |
| A6/C6 board-backed envelope + 1 pack + tape | ~£0.25 (sourced; R&D 50-pack works out at £0.147) | **Under 4mm without the wrap (reported; exact mm not logged); passed the counter check. With the wrap: about 4.5–4.6mm estimated, close to the 5mm limit** | **31g; about 35g with the wrap** | **Letter (£0.91)** — passed the Post Office counter check (Oct 2026) | Board backing resists bending; no cushioning against crushing | Functional |
| AKAR box + 2 / 3 / 4 loose packs | Box price to be added | **23mm** (all pack counts) | **57g taped (2 packs); 69g (3); 82g (4), about 84g taped. With wraps (+4.1g each): about 65g / 81g / 100g — 4 wrapped packs reaches the 100g band limit** | Large Letter (£1.55) | Rigid, resists bending and crushing | Strong |

*Measured Oct 2026: empty envelope 14g, empty box 26g, 4 stickers 13g, thank-you note 3g; each extra pack in the box adds about 13–14g; tape adds about 2g (30cm used per envelope, 45cm per box). Labels are excluded (printed at the Post Office). The personal wrap was added afterwards and is not in these figures (about 4.1g each at 120gsm, 3.4g at 100gsm).*

Flat wrap and the padded mailer were logged as "doesn't fit" (eliminated). Drop/flex and post-to-self were run on the board-backed envelope and the AKAR box only — both passed (Oct 2026); the comparators were not posted.

**Wrap impact (Oct 2026):** two limits are now at risk. (1) **Thickness:** the envelope was reported under 4mm without the wrap; adding about 0.5–0.6mm brings it close to 5mm, so the Post Office gauge check was done with a wrapped pack — the owner reports it passed (Oct 2026); exact thickness not logged. (2) **Box weight:** 4 wrapped packs come to about 100g, the edge of the Large Letter 100g band (above it, postage rises from £1.55 to £1.90). Until weighed, cap the box at **3 wrapped packs** (about 81g); 4 packs on 100gsm paper would be about 97g, still too close.

**Decision (confirmed Oct 2026):** the board-backed envelope is the single-pack packaging. Test 6 showed it (a) qualifies as a Letter — it passed the Post Office counter check — and (b) survives drop/flex and post-to-self (2-day delivery, no damage). Getting the Letter rate (£0.91) rather than Large Letter (£1.55) is worth £0.64 a pack: at the £4 eBay price that is the difference between about £0.80 and £0.16 profit per pack (Business Plan §9). The profit figures in this paragraph predate cost sheets v6–v12; see Business Plan §9 for the current numbers (£0.87 at Letter and £0.23 at Large Letter at the £5.00 launch price, cost sheet v13). If the board-backed envelope fails on thickness or protection, the backing-card comparator is the fallback (Letter-eligible at 4mm/36g, £0.09); the box costs more in both materials and postage.

**Landscape (140×100mm) fit — check:** a C6 envelope (114×162mm) opens on its long edge, so a 140×100mm card should sit inside lengthwise (140mm < 162mm, 100mm < 114mm). The August worry that 114mm is narrower than 140mm looks mistaken, but it has not been physically test-fitted — check with a real card and the board backing in place. The C6 box (163×112×20mm) fits on paper, also unchecked.

### Remaining Test 6 steps
Thickness, weight and the drop/flex note for the board-backed envelope and the AKAR box are now logged in `TestTrackingSheet.xlsx` (Test 6 tab rows 11–12 and the new "Packing Results Oct 2026" tab). The counter check and post-to-self (2-day delivery, no damage) are also logged — **Test 6 is complete for the chosen packaging.** The elimination so far is based on initial handling/fit, not the full protocol.

---

## 2. Shipping method — eBay labels (Click & Drop as fallback)

Decided (updated Oct 2026): for eBay orders, buy Royal Mail postage through **eBay's label tool** (not counter stamps). A Royal Mail Click & Drop business account also exists and stays as a fallback and for Etsy/Amazon/own-site orders later.

**eBay postage policy (set up 6 Oct 2026):** `Free 2nd Class – Small` — Royal Mail **2nd Class Letter** (eBay lists Letter as its own service, separate from Large Letter), free postage, 1 working day dispatch, UK only. Tracked upgrades are deferred (see Section 3). Ship-from and return addresses on labels are set to the virtual address.

**To confirm at the first eBay order** (labels can only be bought against a real order): that eBay's label tool offers untracked 2nd Class **Letter** at £0.91 — a 2025 eBay community thread reports business sellers suddenly being offered only Large Letter and up; if that happens, buy that order's label in Click & Drop. Also pick the QR code option and check the Ship from / Return to box before paying.

**Printing (Oct 2026):** the owner printed a label at a **Royal Mail locker** via QR code — a second free printing route alongside the Post Office counter. Royal Mail says Post Office label printing for **Tracked** services is parcel-format only, so a Tracked Large Letter label would probably need printing at home (plain A4 is fine, see below).

**Setup:**
1. Free account at royalmail.com/business/click-and-drop — set up as a **business account** (better rates, can connect eBay/Etsy later for order import).
2. Add a payment method — pay-as-you-go by card is fine to start.
3. Create a shipment per parcel: enter sender/recipient address, select service tier, enter actual packed weight/dimensions (differs slightly by packaging type — enter the real figures so it bands correctly).

**Printing labels (updated Oct 2026): use Click & Drop with in-branch printing.** Buy postage online, then take the QR code to a Post Office or other participating drop-off point to print the label for free. Royal Mail's help pages describe this as "Labels to Go", but they disagree on which locations print postage labels and mention personal accounts, so confirm it works on your business account at the first real post. Home-printing options below are the fallback.

**Printing labels at home (fallback):**
- **Default/no extra cost: plain A4 paper + tape or glue stick.** Royal Mail officially supports this — not a workaround. Print at **100% scale** (never "fit to page," or the barcode becomes unreadable), cut along the guide lines, attach with the barcode fully visible and flat.
- **Sticker sheets** also work. The SmithPackaging A4 self-adhesive labels bought for testing are one full-A4 label per sheet (199.6×289.1mm, 100 sheets, £9), so print via the 2 or 4 labels per A4 template and cut them apart, or use one per sheet (wasteful).
- **Efficient use of sticker sheets:** batch multiple shipments together and print via the **"2 or 4 labels per A4"** template.

---

## 3. Royal Mail prices (letters checked July 2026; Tracked updated 6 Oct 2026 after Royal Mail's 5 Oct price change, cost sheet v13)

| Service | Format | Price | Speed |
|---|---|---|---|
| 2nd Class (untracked) | **Letter**, up to 100g (max 5mm thick) | £0.91 (cost sheet figure — confirm in Click & Drop) | 2–3 working days |
| 2nd Class (untracked) — **standard tier** | Large Letter, up to 100g | £1.55 | 2–3 working days |
| 2nd Class (untracked) | Large Letter, up to 250g | £1.90 | 2–3 working days |
| Tracked 48 | Large Letter | £2.90 online (was £2.75) | 2–3 days, tracked |
| Tracked 24 | Large Letter | £3.90 online (was £3.65) | Aims next working day, tracked (not guaranteed) |
| Special Delivery Guaranteed by 1pm | Any | £8.75–9.95 before 5 Oct 2026 (raised by £0.70–3.20; new figure not checked) | Guaranteed next working day, signed, compensation up to £750 |

Matches the tiering in Business Plan Section 8: 2nd Class standard is free-to-customer (absorbed into item price), the others are paid upgrades. **5 Oct 2026 price change:** 2nd Class Letter (£0.91) and Large Letter (£1.55) unchanged; only parcels and Tracked/Special Delivery went up.

**Launch decision (6 Oct 2026): free 2nd Class only; paid upgrades added later.** At the new prices, and allowing for eBay fees on what the buyer pays for postage, the break-even upgrade charge is about £2.37–2.51 for Tracked 48 and £3.55–3.77 for Tracked 24 (extra cost over the free £0.91 Letter: £1.99 and £2.99). Suggested prices when added: **£2.69** and **£3.99**.

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
| **Cost per label** | **£0.09 if printed at home on this stock; £0 when printed free in branch (Oct 2026 plan — the cost sheet now uses £0)** |

If you print 3 shipments on one sheet and cut them apart, the cost falls to about £0.03. The earlier plan used an Avery J8160 pack (21 per sheet, 25-sheet pack, £15.89 → about £0.21 a label); it was replaced by the SmithPackaging pack in Sep 2026.

**Cheaper default at low volume: plain A4 paper + tape/glue stick** (~1–2p per shipment) — Royal Mail officially supports this as a standard Click & Drop printing method, not a workaround.

---

## 5. Open items

- [x] ~~Run Test 6 initial fit/handling check~~ — flat wrap and padded mailer eliminated
- [x] ~~Log packed thickness and weight for the backing card (4mm, 36g) and box (48g)~~ — 3–5 Aug 2026
- [ ] Check the 3mm logged for the C6 box (it is a 20mm box) — the AKAR box measured 23mm external, so the earlier entry is likely 23mm; flagged in the tracking sheet
- [x] ~~Measure packed weight of the A6/C6 board-backed envelope~~ — 31g with tape (Oct 2026). Passed the Post Office counter check as a Letter (Oct 2026); exact mm not logged
- [x] ~~Drop/flex on the board-backed envelope and AKAR box~~ — passed Oct 2026 (all drops and flex); logged in `TestTrackingSheet.xlsx`
- [x] Post-to-self for the board-backed envelope and the AKAR box (Click & Drop, label printed in branch) — passed Oct 2026: arrived in 2 days, no creasing, corner damage or shifting; logged in `TestTrackingSheet.xlsx`
- [ ] Add the AKAR box price (pack price and boxes per pack) to the cost sheet's "Packing Oct 2026" tab
- [x] ~~Add the tape roll price and length~~ — Sellotape Zero Plastic, £5.94 / 30m, in cost sheet v7 (Oct 2026)
- [x] **Measure the envelope with a wrapped pack** at the Post Office gauge (5mm Letter limit) and on the kitchen scale — passed the counter check, Oct 2026; launching on Letter postage; exact thickness and wrapped weight not logged
- [x] **Weigh the AKAR box with 3 and 4 wrapped packs** — keep the cap at 3 until 4 is confirmed under 100g — reported done Oct 2026; weights not yet logged, so keep the cap at 3 wrapped packs in these documents until the 4-pack figure is recorded
- [x] Use the exact wrapped pack, taped seal sticker and stickers with peel markers applied in the post-to-self test, and check the markers stay put in transit — covered by the post-to-self test, passed Oct 2026 (2-day delivery, no damage; marker adhesion not separately reported)
- [ ] Re-measure tape per envelope: 30cm was measured with regular tape; taping only over the seal sticker needs about 7cm
- [x] ~~Update cost sheet packaging and shipping selections~~ — done in v4 (A6/C6 £0.25, Letter £0.91); revisit if the measurement puts the envelope over 5mm
- [x] ~~Rewrite Business Plan Sections 7 & 8~~ — done Sep 2026 (A6/C6 replaces the old C4 assumption; 85p/Large Letter mislabel corrected)
- [ ] Update the production guide's §9 (Package) — Test 6 confirmed the final pick (Oct 2026), so this is now unblocked
- [ ] Physically test-fit the landscape 140×100mm card in the A6/C6 envelope with the board backing
- [x] ~~Multi-pack orders of the same product~~ — resolved Oct 2026: AKAR A6/C6 box, loose packs, cap 4 unwrapped — now 3 wrapped packs until 4 are weighed (23mm, about 84g taped, Large Letter). Saves vs separate Letters from 2 packs (£0.27), £1.18 at 3, £2.09 at 4. **Still open:** orders mixing different products
- [ ] Medium (~C5) and Large (~C4) envelopes are not yet sourced or costed
- [ ] Reconcile the £0.25 sourced envelope price with the £0.147 R&D pack price, and confirm £0.25 does not already include the thank-you card (costed separately at £0.16 in the cost sheet)

