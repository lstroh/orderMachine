# Bin Sticker Material Test Plan — Cricut vs. Stickiply

*Prepared July 2026 · Last revised September 2026 · Goal: pick a durable, cost-effective vinyl + laminate source before committing to bulk ordering, without waiting the full advertised durability window.*

**Size note (update Sep 2026):** the baseline product is 140×100mm landscape, pack of 4, printed 4-up on a single A4 sheet — this supersedes the 30×20cm single-large-sticker assumption implied elsewhere, and is the reason Test 6 was added. Tests 1–5 use the same materials and finish for every size tier, so they don't change for the Medium and Large tiers. Test 6 does: those tiers need bigger envelopes (roughly C5 and C4, not yet sourced) and would need their own transit run once approved. Medium is 202×140mm and Large is 280×200mm (confirmed Sep 2026).

---

## 1. What's being tested

| | Material A: Cricut | Material B: Stickiply |
|---|---|---|
| Product | Printable Waterproof Sticker Set – A4 (20 ct), **White** | Glossy White Vinyl A4 + Matte Self-Adhesive Laminate A4 |
| Vinyl colour | White (confirmed opaque) | White (confirmed opaque) |
| Manufacturer claim | Waterproof, UV-resistant up to 2 years (tested) | Water-resistant; laminate recommended for outdoor use (self-described, not independently tested) |
| Cost per matched page (vinyl + laminate) | £0.65 (£13 per 20-ct set); £0.85 as bought incl. £4 shipping. Cricut UK list £12.99, currently £9.09 (30% off) | £0.77–£1.20 depending on pack size (500 down to 10 sheets) |
| Customer rating | 2.8/5 (32 reviews, Cricut UK page for the 20-ct set) | Not compared |

**Note (Sep 2026):** an earlier version of this plan used the 6-ct set (£1.87 per page) and excluded the 20-ct set as transparent-only in the UK. A White 20-ct set is now available and has been bought (along with a Transparent one for the clear-vinyl work), so it is the Cricut candidate. Prices come from the 'cost per item' sheet in `StickerBinStickersCosts_v4.xlsx`. Both materials are white/opaque, so this remains a like-for-like test. **Assumption to confirm:** the Day 0 samples in the tracking table below are treated as this same white Cricut product.

---

## 2. Sample prep (do this identically for both materials)

- [ ] Design one test sheet with: solid dark fill, solid light fill, fine text, and a bold number (mimics real bin sticker content — this is where ink bleeding and fading show up first)
- [ ] Print using your actual Epson EcoTank settings you plan to use for real orders (same paper setting, same quality mode)
- [ ] Laminate using your actual laminator (Stickiply 14" cold roll, ordered Sep 2026) — or by hand if it hasn't arrived yet — note which method was used, since it affects bubble/wrinkle risk independently of the material
- [ ] Cut into **6 identical samples per material** (12 total) — one for each test below, so no single sample is subjected to multiple stresses that could confound results
- [ ] Label the back of each sample with material + date made (e.g. "Cricut — 23 Jul")

---

## 3. Test protocol

### Test 1 — Submersion (24 hours)
- [ ] Submerge one sample of each material fully in a bowl of water for 24 hours
- [ ] Check: ink bleeding, cloudiness in laminate, edge lifting, adhesive failure
- **Record:** Pass / Fail / Notes

### Test 2 — Hot water / dishwasher
- [ ] Run one sample of each through a dishwasher cycle, or hold under hot tap water for 2 minutes
- [ ] Check: same as above, plus whether laminate edge starts to peel from heat
- **Record:** Pass / Fail / Notes

### Test 3 — UV / outdoor exposure (2–3 weeks — the main test)
- [ ] Stick one sample of each material **side by side** on an outdoor surface with maximum direct sun (fence, wall, windowsill facing south)
- [ ] Photograph on Day 0, then every 3–4 days
- [ ] Check at each interval: fading, yellowing, edge lifting, cracking, adhesive residue/slippage
- **Record:** photo log + notes at each checkpoint (table below)

### Test 4 — Adhesion on real surface
- [ ] Stick one sample of each onto an actual plastic bin lid or similarly textured plastic (not a smooth desk surface)
- [ ] Leave outdoors for the same 2–3 week window as Test 3
- [ ] Check: edge lifting, corner curling, whether it survives being brushed against
- **Record:** Pass / Fail / Notes

### Test 5 — Scratch & handling
- [ ] Rub a fingernail or coin firmly across the laminate surface 5–10 times
- [ ] Wipe with a wet cloth immediately after
- [ ] Check: visible scratch marks, ink showing through, laminate lifting at the scratch point
- **Record:** Pass / Fail / Notes

### Test 6 — Transit/shipping stress (added for the 140×100mm size change; updated Sep 2026)
*Purpose: the material tests above (1–5) check the sticker itself once applied to a bin. This test checks whether it survives the journey through the post BEFORE it's ever applied — bending, crushing, corner knocks, flexing. Run this once you've settled on candidate packaging (see packaging brainstorm), so it doubles as a packaging test, not just a material test.*

**Why the postage result now matters more (Sep 2026):** the cost sheet assumes the Letter rate (£0.91). If the packed order is 5mm or thicker, or over 100g, Large Letter (£1.55) applies and eBay profit per pack at £4 falls from £0.80 to £0.16 (Business Plan §9). So this test decides both protection and the postage tier.

**Packaging comparison matrix (five candidates):**

| Packaging | Cost/pack | Packed thickness | Royal Mail format | 2nd Class postage | Protection | Presentation |
|---|---|---|---|---|---|---|
| Kraft envelope + tissue (flat wrap) | ~£0.08 | <1mm | **Letter** | £0.91 | Lowest — sheet can flex/crease in a stuffed mailbag | Nice, simple |
| Kraft envelope + rigid backing card (no padding) | ~£0.09 | 4mm (measured Aug 2026) | **Letter** | £0.91 | Bend-resistant (card stops flexing), but no cushioning against knocks/crushing | Simple, slightly sturdier feel |
| Padded/bubble mailer (Mail Lite A/000, 110×160mm) | ~£0.12 | ~5–10mm | Large Letter | £1.55 | Cushions knocks, stays flat | Less "gift," more "arrived safely" |
| C6 box (163×112×20mm, rigid) | ~£0.14 | 20mm (logged as 3mm — check) | Large Letter | £1.55 | Best — resists bending and crushing entirely | Strongest "unboxing" moment |
| A6/C6 board-backed "Do Not Bend" envelope (114×162mm), flat — **the confirmed choice** | ~£0.25 (sourced; R&D 50-pack works out at £0.147) | **To be measured** | Letter if under 5mm and 100g, otherwise Large Letter | £0.91 if Letter, £1.55 if not | Board backing resists bending; no cushioning against crushing | Functional, not gift-style |

**Logged so far (3–5 Aug 2026, `TestTrackingSheet.xlsx`):** flat wrap and the padded mailer did not fit (eliminated). Envelope + backing card measured 4mm and 36g (Letter-eligible, but much closer to the 5mm limit than the ~0.5mm estimate). The box was logged at 3mm and 48g (the thickness looks wrong for a 20mm box). Drop/flex and post-to-self are not yet logged, and the board-backed envelope has not been measured.

**Why the backing-card option matters:** a padded/bubble mailer's cushioning layer is, by definition, thick enough to protect against impact — which is also exactly why no genuine padded mailer fits under the 5mm Letter limit (checked directly: even slim/foam-lined alternatives are built around a padding layer that inherently exceeds 5mm). A rigid backing card adds bend-resistance without meaningfully adding thickness, so it's the one candidate that might combine Letter-rate postage with better-than-plain-wrap protection — worth testing directly rather than assuming.

**Set-up:** for each material (Cricut / Stickiply) prepare 5 more samples at the actual production size/layout (a 4-up sheet, not a single cut sticker), packed exactly as a real order would be — **five packaging candidates**:
- 1 sample flat-wrapped (kraft paper or tissue, no rigid backing) — e.g. C6 kraft envelope + tissue
- 1 sample in a kraft envelope with a rigid backing card, no tissue/padding
- 1 sample in a padded/bubble mailer — e.g. Mail Lite A/000, 110×160mm (~£11.66/100, ~5g, confirmed Royal Mail Large Letter size)
- 1 sample in a small box — e.g. Forms Plus C6 postal box, 163×112×20mm (£6.54–6.89/50)
- 1 sample in the A6/C6 board-backed "Do Not Bend" envelope, flat with no extra padding — e.g. Double Dragon A6/C6 manilla hard board backed, 50-pack (£7.37 in the R&D list)

- [ ] **Post-to-self test:** address and post all five packed samples to yourself (or a friend/family member) via standard 2nd class. This is the single most realistic test available — it goes through actual sorting machines, bags, and delivery handling.
- [ ] **Measure packed thickness and weight** of each with a ruler and kitchen scale before posting — confirm which candidates actually stay under 5mm and 100g (Letter) vs which are inherently Large Letter, since this affects the postage cost comparison as much as the protection result. The board-backed envelope is the one that matters most: it is the confirmed choice and the cost sheet assumes it qualifies for Letter
- [ ] **On arrival, check:** creasing/bend lines across the sheet, corner dents or crushing, laminate lifting or separating from flex, any shift/damage to the backing card, mailer, or box
- [ ] **DIY drop/flex test (while waiting for the post-to-self result):** drop each packed sample flat, on its edge, and corner-first from ~1m onto a hard floor, 3 times each orientation. Separately, flex the flat-wrapped and backing-card samples by hand — a firm bend to roughly 30°, held for 2 seconds, repeated 5 times — to simulate an envelope getting squeezed through a letterbox or postbag
- **Record:** Pass / Fail / Notes for each material × each packaging type (10 combinations: 2 materials × 5 packaging types), plus packed thickness and weight for each packaging type

**Decision this test feeds into:** if the backing-card option resists creasing about as well as the padded mailer or box, it's the standout choice — same Letter-rate postage as plain wrap, better durability. If it creases like the plain wrap does, that tells you rigidity alone isn't enough and the extra postage cost of a padded mailer/box is buying something real. Durability is this product's core sales promise (per competitor research), so a bent sticker on arrival undermines that even if the vinyl itself is fine. Because the cost sheet currently assumes the Letter rate, the result on the board-backed envelope also decides whether that assumption stands.

---

## 4. Tracking table

| Day | Date | Cricut — observations | Stickiply — observations |
|---|---|---|---|
| 0 (start) | | Navy renders as near-black, blue tone largely lost — consistent across Premium Glossy, Matte, and Photo Quality Ink Jet settings (ruling out driver setting as the cause). Gold/red comparable to Stickiply. Premium Glossy also showed heavy glare in initial photos, a separate/confounded issue now resolved. | True navy blue tone retained at Premium Glossy. Gold/red comparable to Cricut. No glare issue observed. |
| 3–4 | | | |
| 7 | | | |
| 10–11 | | | |
| 14 | | | |
| 17–18 | | | |
| 21 (end) | | | |

*Dates and observations after Day 0 have not yet been recorded in this file (Sep 2026) — add them from your notes.*

---

## 5. Decision criteria

At the end of 2–3 weeks, compare both materials against:

- [ ] Did either show visible fading or yellowing? (If yes, which one, and how much?)
- [ ] Did either show edge lifting or adhesive failure outdoors?
- [ ] Did either fail the submersion or hot water test?
- [ ] Did either scratch significantly more easily than the other?
- [ ] Which was easier to laminate/cut cleanly in practice (bubbles, wrinkles, cut precision)?
- [ ] **Colour rendering (Day 0 finding, see Addendum 5):** Cricut renders navy as near-black across all 3 print settings tested; Stickiply retains true navy. **Now a live factor (Sep 2026):** navy is the default accent in `bin_sticker.py` and berry/forest are in the palette, across 25 built designs — see the swatch test in Addendum 5.
- [ ] **Transit (Test 6):** of the five packaging types (flat wrap / envelope + backing card / padded mailer / box / board-backed envelope), which showed creasing, corner damage, or laminate lifting after the post-to-self and drop/flex tests, and which came through cleanest? Did this differ by material? Which candidates actually measured under 5mm and 100g (Letter-rate eligible) once packed — especially the board-backed envelope?

**Decision (Sep 2026): the Cricut Printable Waterproof Sticker Set (20 ct, White) is the chosen material for bin stickers.** This was decided before Tests 1–5 finished, so those tests now continue as validation rather than selection: if Cricut fails submersion, hot water, outdoor UV or adhesion, revisit the decision before bulk buying. The colour-rendering finding (Addendum 5) becomes a design constraint instead of a selection factor: navy is the default accent in `bin_sticker.py` and Cricut renders it near-black, so the swatch test now decides which accent colours can be offered on Cricut, and whether the navy default changes.

The comparison rules below are kept for reference.

**If both pass comparably:** the cost argument no longer points to Stickiply — the Cricut 20-ct works out at £0.65–£0.85 per page against Stickiply's £0.77–£1.20 (roughly level only at Stickiply's 500-sheet tier). Stickiply's remaining advantage is colour accuracy on dark accents (Addendum 5), so the choice then turns on whether navy, berry and forest need to be offered.
**If Cricut visibly outperforms on durability:** Cricut is the choice, subject to the colour-rendering result in Addendum 5 (Cricut is no longer the more expensive option, so there is no premium to justify).
**If Stickiply fails outdoors within 2–3 weeks:** that's a strong early signal it won't hold up long-term outdoors — don't commit bulk budget to it for bin stickers specifically (it may still be fine for indoor-leaning products like name labels or decals).

---

*This test does not replicate a full 2-year UV cycle — it's designed to catch early failure signs (which most weak materials show within days-to-weeks under concentrated summer sun) rather than to prove long-term performance. Treat a "pass" as "no red flags found in an accelerated test," not as a guarantee.*

---

## Addendum (July 2026) — Cutting tool verification: corner punch & peel-nick — ON HOLD (superseded Sep 2026)

**Status (Sep 2026): on hold.** Bin sticker cutting is now the Cricut Explore 5 in cut-only mode for all sizes, with the guillotine trimming the sheet into separate backed stickers (Business Plan §10, Equipment Guide). The corner punch (the Cricut cuts the rounded corners) and the Slice peel-nick are only worth testing if Addendum 6 shows a need, e.g. a peel-start problem once the backing is trimmed flush. The original checks are kept below for that case.

Added after switching bin sticker cutting from Cricut Print Then Cut to a manual guillotine + corner rounder punch + Slice 00200 safety cutter workflow (see Business Plan Section 10 and the Equipment Guide). This addendum is deliberately unnumbered to avoid clashing with this file's own Test 6 (packaging transit test) above — treat it as a separate, parallel check.

**Why this needs testing:** the corner punch and peel-nick technique are both proven on plain paper/cardstock, but your finished bin sticker is a laminated vinyl stack (vinyl + adhesive + self-adhesive laminate), which is thicker and tougher than what either tool is normally used on. Both were tried on Stickiply on 3 Aug 2026 (results below); neither has been tried on Cricut.

**Results logged in `TestTrackingSheet.xlsx` (3 Aug 2026, Stickiply only):** corner punch — 4mm and 7mm cut cleanly but jammed; 10mm did not jam but tore/stretched the material, yet was logged as the cleanest radius (the entry looks inconsistent — re-check). Peel-nick (Slice 00200) — 3 attempts: the backing stayed intact in 2 of 3 and the nick caught a fingernail in 2 of 3; logged decision "all good". Both tools are on hold anyway now that the Cricut cuts the corners.

### Corner punch check
- [ ] Trim one sample of each material (Cricut and Stickiply) to 100x140mm using the guillotine.
- [ ] Punch a corner with the corner rounder punch at each available radius (4mm/7mm/10mm if using a 3-in-1).
- [ ] Check: does it punch cleanly through vinyl + laminate, or does the vinyl stretch/tear instead of cutting? Does the punch struggle or jam on the laminated thickness?
- **Record:** Pass / Fail / Notes, and which radius gives the cleanest result.

### Peel-nick check (Slice 00200 / craft knife)
- [ ] On a corner-rounded sample, use the Slice 00200 (or a craft knife as a fallback) to nick a ~1cm line through the vinyl + laminate only, along one straight edge.
- [ ] Check: does the backing stay intact and uncut? Does the nick actually separate the top layers enough to catch a fingernail and start a peel? Is the cut consistent across multiple attempts, or does pressure need constant readjustment?
- **Record:** Pass / Fail / Notes, and roughly how many practice attempts it took to get a consistent result.

### Decision criteria
- **If both pass cleanly:** proceed with the guillotine + corner punch + Slice 00200 workflow as planned — no Cricut needed for bin sticker cutting.
- **If the corner punch tears the laminate:** try a lower radius (4mm) before ruling it out entirely; if none work cleanly, fall back to square corners (no punch) or a corner-rounding guillotine attachment (heavier-duty, ~£15–25).
- **If the peel-nick doesn't cut consistently:** more practice may resolve it (this is a hand-skill, similar to the Cricut kiss-cut depth calibration many home crafters describe needing a few attempts to dial in) — but if it remains unreliable after several tries, it's reasonable to skip the peel-nick feature and rely on the corner rounding alone; a rounded corner already gives some finger clearance versus a sharp square corner.

---

## Addendum 2 (July 2026) — Clear vinyl colour visibility test

Added after reviewing the 52-idea Pinterest board (`bin_sticker_idea_gallery.html` + `Idea-Board-Solutions-Reference.md`). Most competitor designs are printed in white ink direct onto the bin with no card background — a look your Epson can't reproduce (no white ink channel), and even switching to clear vinyl doesn't fully solve it, since inkjet ink on transparent film isn't fully opaque (confirmed as low as ~9% opacity for some clear inkjet films) — the bin colour shows through and mutes whatever's printed on top.

**Purpose:** identify which printable colours (as an alternative to white) stay genuinely visible when printed on clear vinyl and applied over the three most common bin colours — black, dark green, dark brown — so this can be offered as a real second production path (Technique F in the Solutions Reference) alongside the standard white-card method.

**The 5 candidate colours to test** (see Solutions Reference §2F for full rationale):

| # | Colour | Hex |
|---|---|---|
| 1 | Golden Yellow | `#F2B705` |
| 2 | Warm Cream/Ivory | `#F5E8C8` |
| 3 | Burnt Amber/Orange | `#D9782E` |
| 4 | Powder Sky Blue | `#8FB8DE` |
| 5 | Dusty Rose/Coral | `#E08A73` |

### Test setup
- [ ] Print a small swatch of each of the 5 colours (a filled square, roughly 30×30mm, plus a sample of small text/thin linework at the size you'd actually use — e.g. a house number) on clear/transparent printable vinyl
- [ ] Cut the 5 swatches apart
- [ ] Source or improvise 3 test surfaces matching real bin colours: black, dark green, dark brown (an actual bin lid/offcut is ideal; failing that, coloured card or painted board close to real bin shades)

### What to check
- [ ] Apply (or hold firmly against) each of the 5 swatches on each of the 3 surfaces — 15 combinations total
- [ ] For each: is the colour still clearly identifiable, or does it wash out/shift toward the background colour?
- [ ] Check thin linework/small text specifically, not just the solid block — opacity loss often shows up worse on fine detail than on a solid fill
- [ ] Photograph each combination in consistent daylight for a fair side-by-side comparison
- **Record:** Pass / Marginal / Fail for each of the 15 combinations, plus a photo log

### Decision criteria
- **If a colour passes cleanly on all 3 surfaces:** confirmed safe to offer as a general-purpose white-ink alternative for any direct-on-bin-style design.
- **If a colour passes on some surfaces but not others:** note which surfaces it works for — still usable, just recommend it only for matching bin colours rather than as a universal option.
- **If all 5 fail on black specifically:** this would confirm black is the hardest case (darkest, most light-absorbing) — expected given the ~9% opacity finding — and would mean the white-card method (Technique A/B/C) should be the default recommendation for any bin sticker likely to end up on a black bin, with clear vinyl reserved for green/brown bins only.
- **This test does not need repeating per design** — once the 5 colours are ranked against the 3 bin colours, that ranking applies to any future design using this technique, not just the ones currently in the idea board.

---

## Addendum 3 (July 2026) — Durability check for winning clear vinyl colour(s)

Added because Addendum 2 only tests visibility/opacity — it doesn't tell you whether clear vinyl itself (a different substrate from the white Cricut/Stickiply stock used in Tests 1–5, with no white base layer under the ink) holds up the same way outdoors or under handling. Running the full Test 1–5 suite across all 5 candidate colours would mean 25 test instances just to answer a visibility question that Addendum 2 already settles more directly — so this addendum is scoped narrower and runs only after Addendum 2 is complete.

**Scope:** take only the colour(s) that passed Addendum 2 (cleanly, or on at least one target bin surface) and run them through the two tests most likely to expose a real difference between clear and white vinyl:

- **Test 3 — UV/outdoor exposure:** the core durability question for this product (per competitor research, durability is the main sales promise), and the ink sits differently on clear film than on white card, so fading/yellowing behaviour isn't guaranteed to match the Tests 1–5 result.
- **Test 5 — Scratch & handling:** clear vinyl has no white base layer to mask a scratch, so ink lifting or showing through at a scratch point may be more visible than on the white stock.

Submersion, hot water, and adhesion (Tests 1, 2, 4) are **not** repeated here — same Stickiply base film family as the vinyl already validated in Tests 1–5, just without the white backing, so water and adhesive behaviour isn't expected to differ meaningfully. Skip unless Test 3 or 5 turns up something unexpected that makes you want to double-check.

### Set-up
- [ ] From Addendum 2's results, identify the winning colour(s) — pass cleanly on all 3 surfaces, or pass on at least one
- [ ] Print 2 fresh samples per winning colour on clear vinyl (one for Test 3, one for Test 5), laminated the same way as the rest of this plan
- [ ] Apply the Test 3 sample to its best-matching bin surface (per Addendum 2's result) and run it through the same 2–3 week outdoor exposure and photo-log process as Section 3

### What to check
- [ ] **Test 3 (UV/outdoor):** same checks as Section 3 — fading, yellowing, edge lifting, cracking — plus specifically whether the colour visibility itself degrades faster than it did on day 0 (i.e. does it fade toward the bin colour underneath, on top of any general fading)
- [ ] **Test 5 (scratch & handling):** same checks as Section 5 — visible scratch marks, ink showing through, laminate lifting — plus specifically whether a scratch is more visible/obvious on clear vinyl than it was on the white stock, given there's no white layer to hide it
- **Record:** Pass / Fail / Notes for each, same format as Sections 3 and 5

### Decision criteria
- **If the winning colour(s) pass both tests at a level comparable to the white vinyl results:** confirmed safe to offer Technique F (clear vinyl + colour) as a genuine production path alongside the white-card method, not just a visually-plausible option.
- **If UV fading is notably worse than the white vinyl result:** clear vinyl may need to be positioned as an indoor-leaning or shorter-guarantee option rather than a like-for-like alternative to the white-card method.
- **If scratches show up more visibly than on the white stock:** worth flagging in the product listing (e.g. recommend the laminate finish more strongly, or avoid this technique for high-handling use cases like water bottles).

---

## Addendum 4 (Aug 2026) — Cut file format verification (SVG vs DXF) — CLOSED (Sep 2026)

**Status (Sep 2026): closed.** The Cricut Explore 5 was bought and is the cutting machine, so the Joy Xtra vs Silhouette Portrait 4 comparison is moot. The one surviving check — Cricut Design Space importing the SVG at the correct size — is folded into Addendum 6, Part 1. The text below is kept as a record.

Added while evaluating a machine-driven kiss-cut upgrade to the guillotine + corner punch + Slice 00200 workflow (see Equipment Guide, "Kiss-Cut Upgrade Path"). Two small cutting machines are candidates — Cricut Joy Xtra and Silhouette Portrait 4 — and the deciding factor came down to file compatibility rather than the machines themselves:

- The cut file for this product is a plain SVG or DXF (a fixed 100×140mm rectangle, not artwork needing tracing — no Print Then Cut/registration marks needed, see chat history for why).
- **Cricut Design Space** imports SVG for free, no paid tier.
- **Silhouette Studio's free Basic Edition cannot import SVG at all** — it needs the Designer Edition upgrade (~£40–50 one-time) to open SVG directly. Its free-tier fallback is DXF import, which works on Basic Edition but has a reputation for unit/scaling issues on import — worth verifying before buying either machine.

**Purpose:** confirm whether the DXF path actually imports cleanly and at the correct size on Silhouette Studio's free tier, before spending money on either machine. If DXF works cleanly, the Portrait 4 is a no-compromise pick (cheaper, no subscription). If it doesn't, the Joy Xtra's free-SVG path is the safer bet.

**Two cut files exist for this test:** `sticker_cut_lines.svg` and `sticker_cut_lines.dxf`, both generated to match `bin_sticker.py`'s exact 2×2 A4 grid (`generate_cut_lines_svg.py` / `generate_cut_lines_dxf.py` — see chat history; regenerate either any time the card size changes).

### Part 1 — Software import check (free, no machine needed)
- [ ] Install Cricut Design Space (free) and import `sticker_cut_lines.svg` — check the reported shape size
- [ ] Install Silhouette Studio Basic Edition (free) and import `sticker_cut_lines.dxf` — check the reported shape size
- [ ] For each: does the imported size read exactly 100×140mm? Do the paths look clean (four simple rectangles) or mangled/distorted?
- **Record:** Y/N match, clean/mangled, notes for each

### Part 2 — Printed comparison (regular paper only, not vinyl)
- [ ] Print 2 copies of `bin_sticker.py`'s cut-guide sheet (`sheet.pdf`) on regular printer paper
- [ ] Measure the printed cut-guide rectangle itself with a ruler first — confirms `bin_sticker.py`'s own output is accurate before blaming the cut file format for any mismatch
- [ ] Compare that measurement against the on-screen imported shape size in each app
- **Record:** measured vs expected (100×140mm) for the printed rectangle, the SVG import, and the DXF import

### Decision criteria
- **DXF imports clean and at the correct size:** Silhouette Portrait 4 becomes a no-compromise pick — cheaper than the Joy Xtra and no subscription ever required.
- **DXF is off-size or visibly mangled:** proceed with the Cricut Joy Xtra and the free SVG path instead — don't spend the £40–50 on Designer Edition to work around a format that's already causing problems.
- **Both import clean:** either machine works from a file-compatibility standpoint — fall back to the other trade-offs already discussed (price, subscription, cut width) to decide.

---

## Addendum 5 (Aug 2026) — Colour rendering check (dark/saturated colours)

Added after a Day 0 print comparison surfaced an unplanned finding: Tests 1–5 in this plan focus on durability (fading, water, scratch, adhesion), not colour *accuracy* at the moment of printing — this addendum fills that gap for dark, saturated colours specifically, after Cricut's material showed an unexpected result on navy.

**Finding:** printing the same navy design element on both materials, Cricut's output rendered as near-black with the blue tone largely lost, while Stickiply's rendered as a true, clearly visible navy. This was confirmed **consistent across all 3 print settings tested on the Cricut material** — Premium Glossy, Matte, and Photo Quality Ink Jet — which rules out driver/paper-type setting as the cause. Gold and red tones were comparable between the two materials; the issue appears specific to dark/saturated colours rendering on Cricut's coating, not a general colour accuracy problem.

**Note on a separate, resolved issue:** the first Cricut sample tested (Premium Glossy) also showed heavy glare in photos that initially made it look worse than Stickiply on *all* colours, not just navy. Once photographed flat and glare-free, gold/red were actually comparable between materials — the glare was a photography artifact, not a print quality issue. This is a good example of why a same-conditions, side-by-side comparison matters before concluding a material is worse: glare and the genuine navy-rendering gap turned out to be two separate, independent findings, easy to conflate if judged from a single photo.

**Note on red text sharpness (also investigated, resolved as non-issue):** a phone-photo comparison separately raised a concern that Stickiply's red text looked sharper than Cricut's. Re-tested with flatbed scans of both full sheets (eliminating lighting, glare, and camera focus as variables) — the sharpness difference did not hold up. Both materials render red text comparably crisp under a clean scan; the original impression was a camera-focus artifact, not a real print quality gap. No action needed on this one.

**Confirmed a 4th time:** the navy finding above was also visible in these same flatbed scans — Cricut's navy still reads as near-black/dark purple-navy, Stickiply's as true navy, with lighting/glare/focus fully removed as possible causes. This is now the most solidly confirmed finding in this addendum (4 independent checks: 3 print settings + 1 scan), while the sharpness concern is the opposite — investigated and ruled out.

### What to check
- [ ] ~~Check current designs for use of dark saturated colours~~ **Re-opened (Sep 2026):** the earlier check found only D01 planned, in black. 25 designs are now built (17 proofs approved, 8 pending). Most are solid black, but accent-recolourable designs default to **navy** in `bin_sticker.py`, the heart design (D21) defaults to **berry**, and the recycle style uses **forest** — so the risk category is now in the main product line.
- [ ] **Swatch test (added Sep 2026):** print one sheet per material with solid swatches (~30×30mm) plus a bold house number in navy (`#1F3A5F`), berry (`#7A2E4D`) and forest (`#2F5233`), with black as a control — hex values from `ACCENTS` in `bin_sticker.py`. Use the same print settings and lamination for both materials
- [ ] Compare side by side: is each colour clearly distinguishable from black on each material? Also check the gap holds after lamination
- **Record:** distinguishable from black? Y/N for each colour × each material, plus a flatbed scan of both sheets
- [ ] ~~Not currently planned: confirming the gap holds after lamination, or for other dark colours~~ — now included in the swatch test above

### Decision criteria
- **No longer assumed to be a non-blocker (Sep 2026):** the earlier reasoning (only D01, in black) no longer holds, because navy is the default accent and berry/forest are in use. Run the swatch test before the material decision.
- **If Cricut fails on navy, berry or forest:** since Cricut is now the chosen material, stop offering the failing colours and change the navy default in `bin_sticker.py` (or test whether a lighter shade renders correctly). Styles that fill large areas with the accent (**reverse_block**, **split_panel**) are the most exposed.
- **If both materials render all three colours correctly:** colour is no longer a deciding factor and the choice rests on Tests 1–5 and cost.
- **This does not override Tests 1–5's durability comparison** — a material can win on colour rendering and still lose on durability, or vice versa. Treat this addendum's result as one additional input to the Section 5 decision, not a replacement for it.



---

## Addendum 6 (Sep 2026) — Cricut Explore 5 cut-only verification (all bin sticker sizes)

Added after the decision to cut every bin sticker size on the Explore 5 in cut-only mode (no Print Then Cut, no registration marks), then trim the kiss-cut sheet into separate backed stickers on the guillotine. Nothing in this workflow has yet been verified on your actual laminated stock.

### Part 1 — Design Space import (free, no machine needed)
- [ ] Import the cut-lines SVG into Cricut Design Space and check the reported size against the expected card size
- [ ] Check the paths are clean rounded rectangles, not mangled or distorted
- **Record:** size match Y/N, clean/mangled, notes

### Part 2 — Kiss-cut depth and pressure on the real stock
- [ ] Use an offcut of each material laminated exactly as for real orders (vinyl + laminate on backing)
- [ ] Cut with a custom material setting, starting at moderate pressure and adjusting in small steps
- [ ] Goal: vinyl and laminate cut through cleanly, backing left intact, corners clean
- **Record:** the pressure/blade settings that work for each material (Cricut and Stickiply have different backings and thicknesses, so expect different settings)

### Part 3 — Alignment
- [ ] Square a full printed 4-up sheet on the mat by hand against the printed corner tick marks, then cut
- [ ] Measure how far the cut lines sit from the printed artwork edges on all four stickers
- **Record:** largest offset in mm. Decide an acceptable tolerance before you start (suggest 1mm or less)

### Part 4 — Trim into separate stickers
- [ ] Trim the kiss-cut sheet along the gaps between outlines on the guillotine
- [ ] Check the backing trims cleanly, the stickers don't lift, and the rounded corners survive
- [ ] Check how the peel-start feels with the backing trimmed flush. If it is hard to start a peel, revive the Slice peel-nick check in the July addendum
- **Record:** Pass / Fail / Notes

### Part 5 — Mat fit and repeatability
- [ ] Confirm all three sheets fit the 12×12 mat (cut areas about 200–202mm × 280mm; the 12×24 mat is no longer needed for Medium). Medium and Large now print in normal (bordered) mode, so check the print margins at those exact sizes
- [ ] Repeat the full cycle on 5 sheets and note any drift in settings or alignment

### Decision criteria
- **All parts pass:** cut-only is confirmed for every size; the corner punch and Slice stay on hold.
- **Kiss-cut depth is inconsistent:** fall back to the options in the Equipment Guide's "Kiss-Cut Upgrade Path" before buying anything.
- **Alignment offset is over tolerance:** add a simple placement jig or guide on the mat before changing method.
- **Peel-start is poor after trimming:** bring back the peel-nick check (July addendum) or leave a small tab.
