# Finished Products — Full Data Export

*Text-only companion to `bin_sticker_products_gallery.html` — same entries, no images, kept lightweight for reference/search. IDs match the HTML gallery exactly; open that file to see the actual proof thumbnails.*

**Total entries:** 25
**Status:** 17 proof approved · 8 pending

This file tracks **built, code-backed designs** — a different thing from
`bin_sticker_idea_board_data.md`, which tracks unbuilt inspiration/research
(66 entries, filtered by theme/production/fits-spec). Every entry here
traces back to exactly one idea-board ID via **Source idea**, and forward
to exactly one function in `bin_sticker.py` via **Style key** — if you're
ever regenerating a proof or editing layout constants, the style key is
what you actually need, not the ID.

**ID scheme:** `D01`, `D02`, `D03`, ... — deliberately a different prefix
from the idea board's `P##` numbering, since a single idea (P25) produced
two different finished designs here (D02 and D03). "P25" always means the
idea-board card; it never doubles as a product ID.

---

## D01 — Cottage Bloom Banner

**Style key:** `house_banner`
**Source idea:** P02
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Illustrated house + flowers, house number nested inside the
house body, street name curved along a banner ribbon beneath. The only
illustrated (non-typographic) design in the lineup — every other style in
`bin_sticker.py` is built from plain vector shapes or borders/typography
only.

**Assets required:** `assets/icons/house_banner_master.png` (transparent
silhouette with two hollow areas — the house body and the banner ribbon —
that the number and street text are nested into; see
`bin_sticker_README.md` §4 for exactly how the nesting/curve-fitting
works). Per-accent recoloured copies (`house_banner_{accent}.png`) are
generated and cached automatically on first render.

**Draft marketing angle:** leans into "storybook cottage" charm rather
than the classic/formal look of D02–D03. Good candidate for a "whimsical"
shelf without actually being a kids' design.

---

## D02 — Regency Double Flourish

**Style key:** `p25_landscape_flourish`
**Source idea:** P25 (Image 1 of the mockup prompt run — see that card's
Mockup prompt / Outcome fields in `bin_sticker_idea_board_data.md`)
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid rounded border, bold serif house number, wide street
name flanked by a scroll flourish both above and below.

**Assets required:** `assets/icons/p25_flourish1.png` (above the street
name), `assets/icons/p25_flourish2.png` (below it) — both extracted from
the reference render via the `icon-silhouette-extraction` skill, not
hand-drawn, so the linework is more organic than a typical vector-only
design.

**Draft marketing angle:** the most symmetrical/formal of the three — the
matching double flourish reads as a "pair," which could suit pitching it
as a set or a gift-pair listing.

**Text limits (print-tested Aug 2026):** street name safe up to **32
characters** before auto-shrink hits its 16pt floor; house number/name
field safe up to **12 characters** as a name (numeric house numbers are
effectively unconstrained — up to ~17 digits still fit at the 40pt
floor). Both fields now auto-shrink with a 3–4mm side margin at any
length; the number vertically centres against the card's two flourish
lines using Times-Bold cap-height (676/1000 em), not full ascent/descent
— the earlier ascent/descent version centred long text correctly but
left short text sitting slightly high in its band. See
`P25_STREET_MAX_WIDTH` / `P25_NUMBER_MAX_WIDTH` /
`TIMES_BOLD_CAP_HEIGHT_RATIO` in `bin_sticker.py`.

---

## D03 — Manor Frame Classic

**Style key:** `p25b_landscape_flourish`
**Source idea:** P25 (Image 2 of the same mockup prompt run as D02 — one
Midjourney run, two variant outputs, two different finished designs)
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Double-line border with ornate corner brackets, single
flourish between the number and street name.

**Assets required:** `assets/icons/p25b_flourish.png` (the single
flourish) plus **four independently-extracted corner brackets** —
`p25b_corner_tl.png`, `_tr.png`, `_br.png`, `_bl.png`. These are NOT one
image rotated four ways: an early version tried that and a direct pixel
check showed the source art wasn't actually symmetric (3 of 4 corners
were 8–18% wrong when built that way) — see `bin_sticker_README.md` §10
for the full writeup, and `icon-silhouette-extraction`'s
`check_symmetry_assumption()` if you're ever tempted to take the
rotate-one-copy shortcut again.

**Draft marketing angle:** the most "architectural"/heritage-plaque feel
of the three, thanks to the corner brackets — could pitch toward
period-property or conservation-area customers specifically, distinct
from D02's softer symmetrical look.

**Text limits (print-tested Aug 2026):** street name safe up to **30
characters** before auto-shrink hits its 16pt floor — tighter than D02
because this style's double-line-plus-corner-bracket border leaves a
narrower true interior (measured ~121mm vs. D02's roomier single
border). House number/name field safe up to **12 characters** as a
name; numeric house numbers effectively unconstrained. Was previously
touching/crossing the border at these lengths on a real print (fixed by
giving this style its own tighter width budget rather than sharing
D02's). See `P25B_STREET_MAX_WIDTH` / `P25B_NUMBER_MAX_WIDTH` in
`bin_sticker.py`.

---

## D04 — Homestead Silhouette

**Style key:** `p27_landscape_house`
**Source idea:** P27 (landscape variant, deliberately off-spec per user request -- P27's own idea-board entry has fits_spec=Yes for the standard 100x140mm portrait card)
**Card shape:** 140×100mm landscape
**Status:** Needs proof

**Layout:** Bold house-outline icon with a chimney, house number nested inside the hollow interior, street name printed below. Icon extracted (not hand-drawn) via icon-silhouette-extraction from the Midjourney/Editor reference render, same approach as D01.

**Assets required:** `assets/icons/p27_house_icon.png` (transparent hollow house-outline silhouette -- number nested inside the interior, street name printed below it). Per-accent recoloured copies (`p27_house_{accent}.png`) are generated and cached automatically on first render.

**Draft marketing angle:** the strongest real-world sales evidence of any design in the lineup -- 4th sighting of the house-outline-with-nested-number concept on the idea board, same trusted EDSG line as D02/D03's Amazon's Choice comparables, chimney detail as a differentiator. Pending: street-name auto-fit renders smaller than the source mockup's proportions imply (font-width mismatch, Helvetica-Bold vs. the mockup's narrower typeface) -- see this file for the full numbers before approving.

---

## D05 — Threshold Numeral

**Style key:** `p47_house`
**Source idea:** P47
**Card shape:** 140×100mm landscape
**Status:** Needs proof

**Layout:** House-outline icon (black line art only, no colour) with the house number nested inside the hollow interior. Numbers-only -- no street-name field, unlike D01/D04's house+banner designs. Icon extracted via icon-silhouette-extraction from the Midjourney 'Image 1' mockup, which had to be de-rotated (~2.618deg) before extraction/measurement -- the tilt was initially under-measured (~0.44deg) because the first fit averaged over the house's rounded corners; isolating the straight base-wall segment found the real angle.

**Assets required:** `assets/icons/p47_house_icon.png` (transparent hollow house-outline silhouette, no street band). Per-accent recoloured copies (`p47_house_{accent}.png`) are generated and cached automatically on first render.

**Draft marketing angle:** the cleanest/most minimal of the house-outline family -- black-only line art with no street name to fit, so it reads well small and suits a 'just the number' customer who finds D01/D04's street-name band unnecessary.

---

## D06 — Duck Family — Father & Duckling (Scene 1 of 4, draft)

**Style key:** `duck_family_father`
**Source idea:** None -- original concept developed in chat, no idea-board (P##) precursor. Grew out of the P03 (cat peeking) discussion, first of a planned 4-scene duck family set.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black silhouette scene: father mallard (identifiable by a small curled tail feather, the real drake-vs-hen anatomical cue) walking with one duckling trailing behind, house number and street name printed below in black ink -- not accent-recolourable, unlike this file's other silhouette icons.

**Assets required:** assets/icons/duck_family_father_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render)

**Draft marketing angle:** DRAFT: first of a 4-scene 'collect the family' set (dad+duckling, mum+duckling, 2x ducklings playing) -- sellable standalone, or as a bundle across a household's bin set (main bin / second bin / food caddy), same validated bundling pattern as P08/P16/P20 on the idea board.

---

## D07 — Duck Family — Mother & Duckling (Scene 2 of 4, draft)

**Style key:** `duck_family_mother`
**Source idea:** None -- original concept developed in chat, second scene of the duck family set started with D06 (father duck). No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black silhouette scene: mother mallard (no curled tail feather, unlike D06's father) with one duckling close beside her, house number and street name printed below in black ink -- same layout convention and placement constants as D06, not accent-recolourable.

**Assets required:** assets/icons/duck_family_mother_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected from 4 v8 options)

**Draft marketing angle:** DRAFT: second of a planned 4-scene 'collect the family' set (dad+duckling done, mum+duckling here, 2x ducklings playing still to come) -- sellable standalone or as a bundle across a household's bin set, same validated bundling pattern as P08/P16/P20.

---

## D08 — Duck Family — Ducklings Playing (Scene 3 of 4, draft)

**Style key:** `duck_family_playing1`
**Source idea:** None -- original concept developed in chat, third scene of the duck family set started with D06/D07 (father, mother). No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black silhouette scene: three ducklings playing/splashing near a wavy water line, no adult duck, house number and street name printed below in black ink -- same layout convention and placement constants as D06/D07, not accent-recolourable.

**Assets required:** assets/icons/duck_family_playing1_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected from 4 v8 options)

**Draft marketing angle:** DRAFT: third of the planned 4-scene 'collect the family' set (dad+duckling and mum+duckling done, this is the first ducklings-only scene, one more to come) -- sellable standalone or as a bundle across a household's bin set. NOTE: source art has visible fur texture on the ducklings, a style departure from D06/D07's flat fill -- flagged at selection time, user's explicit choice over 3 flatter alternatives from the same batch.

---

## D09 — Duck Family — Ducklings Playing, Energetic (Scene 4 of 4, draft)

**Style key:** `duck_family_playing2`
**Source idea:** None -- original concept developed in chat, fourth and final scene of the duck family set (D06 father, D07 mother, D08 ducklings playing). No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black silhouette scene: three ducklings mid-hop/tumbling with wings flared and bigger splashes than D08, no adult duck, house number and street name printed below in black ink -- same layout convention and placement constants as D06-D08, not accent-recolourable.

**Assets required:** assets/icons/duck_family_playing2_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render)

**Draft marketing angle:** DRAFT: fourth and final scene completing the 'collect the family' set (D06 father+duckling, D07 mother+duckling, D08 and D09 both ducklings-only but distinct energy levels -- D08 a determined little march, D09 more dynamic/playful per explicit user request) -- sellable standalone or as a 4-bin/caddy bundle, same validated bundling pattern as P08/P16/P20. Fur-texture style matches D08, both distinct from D06/D07's flat fill.

---

## D10 — Dog Family — Adult & Puppy, Walking (Scene 1 of 4, draft)

**Style key:** `dog_family_1`
**Source idea:** None -- original concept developed in chat, first scene of a new dog family set (second animal-family line after ducks D06-D09). No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black flat-silhouette scene: adult dog with a puppy trailing behind, both walking, house number and street name printed below in black ink -- same layout convention and placement constants as the duck family set, not accent-recolourable. No gendering (dad/mum) -- dogs have no reliable visual cue for this in silhouette, unlike the ducks' drake/hen tail-feather difference, so this set uses composition rather than implied parentage to differentiate its two adult+pup scenes (explicit user decision).

**Assets required:** assets/icons/dog_family_1_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected from 4 v8 options)

**Draft marketing angle:** DRAFT: first of a planned 4-scene dog family set (2x adult+pup, 2x pups playing), same 'collect the family' concept validated by the duck set -- sellable standalone or as a bundle across a household's bin set. FLAT SILHOUETTE (no fur texture) -- used as the safe default since the duck texture test (Animal-Family-Texture-Test-Plan.md) hasn't been run yet; revisit once that's decided.

---

## D11 — Dog Family — Adult & Puppy, Close Beside (Scene 2 of 4, draft)

**Style key:** `dog_family_2`
**Source idea:** None -- original concept developed in chat, second scene of the dog family set started with D10. No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black flat-silhouette scene: adult dog with a puppy close beside it (not trailing, unlike D10), house number and street name printed below in black ink -- same layout convention and placement constants as D10 and the duck family set, not accent-recolourable. No gendering, same as D10.

**Assets required:** assets/icons/dog_family_2_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected from 3 v8 options)

**Draft marketing angle:** DRAFT: second of the planned 4-scene dog family set (2x adult+pup done, 2x pups playing still to come) -- sellable standalone or as a bundle across a household's bin set, same validated bundling pattern as the duck set. Flat silhouette, matches D10.

---

## D12 — Dog Family — Puppies Playing, Calm (Scene 3 of 4, draft)

**Style key:** `dog_family_playing1`
**Source idea:** None -- original concept developed in chat, third scene of the dog family set (D10, D11 done). No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black flat-silhouette scene: two puppies nose-to-nose in a calm nuzzle, no adult dog, house number and street name printed below in black ink -- same layout convention and placement constants as D10/D11 and the duck family set, not accent-recolourable.

**Assets required:** assets/icons/dog_family_playing1_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected from 4 v8 options)

**Draft marketing angle:** DRAFT: third of the planned 4-scene dog family set (2x adult+pup done, this is the calmer of 2 pups-playing scenes, one more energetic scene to come) -- companion to duck_family_playing1/D08 at the same energy level within its own set. Sellable standalone or as a bundle, same validated pattern as the duck set.

---

## D13 — Dog Family — Puppies Playing, Energetic (Scene 4 of 4, draft)

**Style key:** `dog_family_playing2`
**Source idea:** None -- original concept developed in chat, fourth and final scene of the dog family set (D10, D11, D12 done). No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black flat-silhouette scene: three puppies playing -- a low crouch/pounce, one rolled onto its back, one mid-leap -- no adult dog, house number and street name printed below in black ink -- same layout convention and placement constants as D10-D12 and the duck family set, not accent-recolourable.

**Assets required:** assets/icons/dog_family_playing2_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected from 3 v8 options)

**Draft marketing angle:** DRAFT: fourth and final scene completing the dog family set (D10 and D11 adult+pup, D12 calmer pups-playing, D13 more energetic pups-playing) -- sellable standalone or as a 4-bin/caddy bundle, same validated bundling pattern as the duck set. Flat silhouette throughout, matches D10-D12. Companion to duck_family_playing2/D09 at the same energy level within its own set.

---

## D14 — Cat Family — Adult & Kitten, Walking (Scene 1 of 4, draft)

**Style key:** `cat_family_1`
**Source idea:** None -- original concept developed in chat, first scene of a new cat family set (third animal-family line after ducks D06-D09 and dogs D10-D13). No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black flat-silhouette scene: adult cat with a kitten trailing behind, both walking, tails naturally curved, house number and street name printed below in black ink -- same layout convention and placement constants as the duck/dog family sets, not accent-recolourable. No gendering, same precedent as the dog set.

**Assets required:** assets/icons/cat_family_1_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected from 3 v8 options)

**Draft marketing angle:** DRAFT: first of a planned 4-scene cat family set (2x adult+kitten, 2x kittens playing), same 'collect the family' concept validated by the duck and dog sets -- sellable standalone or as a bundle across a household's bin set. FLAT SILHOUETTE (no fur texture) -- same safe default as the dog set, pending the duck texture test (Animal-Family-Texture-Test-Plan.md).

---

## D15 — Cat Family — Adult & Kitten, Close Beside (Scene 2 of 4, draft)

**Style key:** `cat_family_2`
**Source idea:** None -- original concept developed in chat, second scene of the cat family set started with D14. No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black flat-silhouette scene: adult cat with a kitten close beside it (not trailing, unlike D14), house number and street name printed below in black ink -- same layout convention and placement constants as D14 and the duck/dog family sets, not accent-recolourable. No gendering, same as D14.

**Assets required:** assets/icons/cat_family_2_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected from 2 v8 options)

**Draft marketing angle:** DRAFT: second of the planned 4-scene cat family set (2x adult+kitten done, 2x kittens playing still to come) -- sellable standalone or as a bundle across a household's bin set, same validated bundling pattern as the duck/dog sets. Flat silhouette, no whiskers -- kept consistent with D14 rather than a whiskered alternative in the same generation batch.

---

## D16 — Cat Family — Kittens Playing, Calm (Scene 3 of 4, draft)

**Style key:** `cat_family_playing1`
**Source idea:** None -- original concept developed in chat, third scene of the cat family set (D14, D15 done). No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black flat-silhouette scene: two kittens nuzzling gently, no adult cat, house number and street name printed below in black ink -- same layout convention and placement constants as D14/D15 and the duck/dog family sets, not accent-recolourable.

**Assets required:** assets/icons/cat_family_playing1_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected after 2 generation batches)

**Draft marketing angle:** DRAFT: third of the planned 4-scene cat family set (2x adult+kitten done, this is the calmer of 2 kittens-playing scenes, one more energetic scene to come) -- companion to duck_family_playing1/D08 and dog_family_playing1/D12 at the same energy level within their own sets. IMPORTANT STYLE NOTE: this is a REAR/THREE-QUARTER VIEW, not the side profile used by every other design in the catalogue -- multiple Midjourney batches only produced this angle for the calm-nuzzle pose; user explicitly accepted it as the best available option rather than a new style direction. Do not default to this angle for future animal-family scenes.

---

## D17 — Cat Family — Kittens Playing, Energetic (Scene 4 of 4, draft)

**Style key:** `cat_family_playing2`
**Source idea:** None -- original concept developed in chat, fourth and final scene of the cat family set (D14, D15, D16 done). No idea-board (P##) precursor.
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Solid-black flat-silhouette scene: three kittens playing -- one pouncing low, two batting paws mid-leap -- no adult cat, house number and street name printed below in black ink -- same layout convention and placement constants as D14-D16 and the duck/dog family sets, not accent-recolourable.

**Assets required:** assets/icons/cat_family_playing2_icon.png (solid black silhouette, extracted via icon-silhouette-extraction from a Midjourney render, user-selected after 2 generation batches)

**Draft marketing angle:** DRAFT: fourth and final scene completing the cat family set (D14 and D15 adult+kitten, D16 calmer kittens-playing [rear-view exception], D17 more energetic kittens-playing [genuine side profile]) -- sellable standalone or as a 4-bin/caddy bundle, same validated bundling pattern as the duck/dog sets. This scene's side-profile prompt fix worked cleanly where D16's batch didn't -- worth reusing this exact phrasing ('seen strictly in side profile... not from behind... smooth solid black shapes only, absolutely no individual fur strands') as the template for any future animal-family prompts.

---

## D18 — Grove Wreath Circlet

**Style key:** `p06_wreath`
**Source idea:** P06 (floral wreath border, shown at 3 size options)
**Card shape:** 140×100mm landscape
**Status:** Needs proof

**Layout:** Delicate black line-art floral vine wreath, house number nested in the upper interior, street name in flat (straight) text in the lower interior beneath it.

**Assets required:** assets/icons/p06_wreath_icon.png (transparent hollow wreath silhouette -- number and street name nested in the interior). Per-accent recoloured copies (p06_wreath_{accent}.png) are generated and cached automatically on first render.

**Draft marketing angle:** the only wreath/floral-border design in the lineup with real extracted line-art (not a plain vector flourish) -- adapted from the idea board's pinned circular 15/20/30cm die-cut sizes onto the standard printed rectangle card (Technique A), so it reads as a premium/romantic option alongside the more graphic house-outline family (D04/D05).

**Text limits (print-tested Aug 2026):** street name safe up to **12
characters** before auto-shrink hits its 12pt floor -- e.g. "High
Street" (11) and "Mill Lane" (9) fit; "Amersham-on-the-Hill Road" (25)
does not and will render small and overflowing regardless of tuning.
Materially tighter than P25/P25b's ~28-30 char limit -- this wreath's
interior is just smaller, and no floor-size choice fixes that (raising
the floor for legibility only shrinks the safe character count further;
see chat history for the full 10pt/12pt/14pt trade-off table). House
number/name field unaffected by this constraint. Icon and all P06_*
text-fit constants were scaled up ~13.6% in this same round (wreath was
leaving ~12-14mm of unused margin on a 100mm-tall card) -- see
`P06_ICON` / `P06_STREET_MIN_SIZE` / `P06_STREET_MAX_WIDTH` in
`bin_sticker.py`.

---

## D19 — Grove Wreath Circlet — Numbers Only

**Style key:** `p06_wreath_numbers`
**Source idea:** P06 (floral wreath) + P30a/P30b (leaf wreath, number only, no street) -- companion to D18, built for the numbers-only market segment those confirm
**Card shape:** 140×100mm landscape
**Status:** Needs proof

**Layout:** Same wreath artwork as D18, no street-name field -- a single larger number recentred in the wreath's true geometric middle rather than the upper half.

**Assets required:** assets/icons/p06_wreath_icon.png (same asset as D18 -- shared, not duplicated). Per-accent recoloured copies shared with D18 too.

**Draft marketing angle:** the numbers-only pairing for D18, aimed directly at the segment Etsy's own bestseller list confirms exists ("Circle Design ... House Number", no street) -- offer alongside D18 as a with/without-street-name choice on the same wreath artwork rather than a separate design.

---

## D20 — Laurel Circlet — Numbers Only

**Style key:** `p30_laurel_numbers`
**Source idea:** P30a/P30b ("56"/"64" in a small floral leaf wreath, number only, no street name)
**Card shape:** 140×100mm landscape
**Status:** Needs proof

**Layout:** Open-top laurel leaf wreath (two symmetrical branches meeting at a small stem at the bottom, no flowers), a single large number centred inside. No street-name field.

**Assets required:** assets/icons/p30_laurel_icon.png (transparent hollow laurel wreath silhouette). Per-accent recoloured copies (p30_laurel_{accent}.png) are generated and cached automatically on first render.

**Draft marketing angle:** the simpler, leaner sibling to D18/D19's dense floral wreath -- open-top laurel shape reads as classic/formal rather than romantic, and the plainer linework leaves more visual room for a large, highly legible number. Third independent wreath-family design, all sharing the same numbers-only market validation (Etsy's own bestseller "Circle Design ... House Number" listing, P30a/P30b's two real-world sightings).

---

## D21 — Fishpond Heart Circlet

**Style key:** `p15_heart_wreath`
**Source idea:** P15 (blue bin's heart-vine wreath badge, "46 Fishpond Lane" -- one of 3 designs in that pin)
**Card shape:** 140×100mm landscape
**Status:** Needs proof

**Layout:** Thin vine wreath with small heart-shaped leaves, house number nested in the upper interior with the street name in flat (not curved) text below it.

**Assets required:** assets/icons/p15_heart_icon.png (transparent hollow heart-vine wreath silhouette). Per-accent recoloured copies (p15_heart_{accent}.png) are generated and cached automatically on first render -- defaults to the "berry" accent rather than charcoal, fitting the heart theme.

**Draft marketing angle:** the romantic/gift-market entry in the wreath family, distinct from D18/D19's floral and D20's classic laurel -- a lighter, more open ring than either, with room for a much bigger number than D18 allowed. Positioned as a deliberate "gift" or Valentine/anniversary-adjacent variant rather than a mainline everyday option.

**Text limits (print-tested Aug 2026):** street name safe up to **29
characters** before auto-shrink hits its floor. Size ceiling deliberately
set above "HIGH STREET"'s own ~23pt width limit so short names like "RYE"
use the full ceiling while longer names stay clamped by their own width
-- not a single shared size for every length. See
`P15_HEART_STREET_MAX_SIZE` / `P15_HEART_STREET_MAX_WIDTH` in
`bin_sticker.py`.

---

## D22 — Central Avenue Arrow Circlet

**Style key:** `p28_arrow_wreath`
**Source idea:** P28 (TheVinylStudioGB's "36 Central Avenue" listing -- real pricing on record: £2.95+, 4.5 stars, "Various Colours, Various Sizes")
**Card shape:** 140×100mm landscape
**Status:** Needs proof

**Layout:** Alternating arrowhead and hatched-fletching shapes forming a ring, house number nested in the upper interior with the street name in flat (not curved) text below it.

**Assets required:** assets/icons/p28_arrow_icon.png (transparent hollow arrow-wreath silhouette). Per-accent recoloured copies (p28_arrow_{accent}.png) generated and cached automatically on first render.

**Draft marketing angle:** the rustic/boho entry in the wreath family, visually distinct from the three floral-adjacent designs (D18/D19/D20/D21) -- differentiates the range for customers who want something other than florals. Fourth and final wreath from the original 3-pattern plan (laurel, heart-vine, arrow), completing the set.

**Asset note:** regenerated (v2) after the original source had one visibly inconsistent arrowhead node (caught by the user, confirmed by close zoom -- not fixable via raster splice, see bin_sticker.py's P28 constants block for the full writeup). Current asset's 9 arrow/fletching nodes measured within ~10% of each other by pixel area -- no known inconsistency remaining.

**Text limits (print-tested Aug 2026):** street name safe up to **31
characters** before auto-shrink hits its floor -- the roomiest of the
wreath family. Also has a small (~3mm) added margin between number and
street after the first print showed them sitting too close at max
sizes. Ceiling set above "HIGH STREET"'s own ~25pt width limit, same
reasoning as D21. See `P28_ARROW_STREET_MAX_SIZE` /
`P28_ARROW_NUMBER_CENTER_Y` in `bin_sticker.py`.

---

## D23 — Maple Olive Circlet

**Style key:** `p31_olive_wreath`
**Source idea:** None -- no catalogued P## idea, no confirmed bin-sticker market sighting. Searched directly for olive/eucalyptus/wheat wreath bin stickers before building; found only general home-decor door wreaths (Michaels, West Elm, Etsy general wreath category), no bin-sticker precedent. Built as a deliberate experiment, not a validated pattern like D18-D22.
**Card shape:** 140×100mm landscape
**Status:** Needs proof

**Layout:** Loose olive-branch wreath (rounded leaves in irregular alternating pairs, distinct from D20's tight symmetrical laurel), house number nested in the upper interior with the street name in flat text below it.

**Assets required:** assets/icons/p31_olive_icon.png (transparent hollow olive-branch wreath silhouette). Per-accent recoloured copies (p31_olive_{accent}.png) generated and cached automatically on first render.

**Draft marketing angle:** EXPERIMENTAL -- unlike every other wreath in the lineup (D18-D22), this has no real competitor listing behind it. Positioned as a softer, more rustic/relaxed alternative to D20's laurel -- worth watching sell-through closely before treating it as validated, rather than assuming it will perform like the market-backed designs.

**Text limits (print-tested Aug 2026):** street name safe up to **24
characters** before auto-shrink hits its floor -- the tightest of the
wreath family (had an unusually low 12pt size ceiling originally,
corrected after two rounds of real prints). See
`P31_OLIVE_STREET_MAX_SIZE` in `bin_sticker.py`.

---

## D24 — Grove Line — Minimal Borderless

**Style key:** `p09a_borderless`
**Source idea:** P09a/b/c/d (borderless number + underline + street, no border, no icon — 4 sightings across the idea board)
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** Bold house number, thin underline rule sized to match the street name width, street name in caps below it. No icon, no border.

**Assets required:** None — pure vector/typographic style, no external PNG dependencies.

**Draft marketing angle:** DRAFT: the clean, borderless minimalist look that keeps showing up on best-selling competitor listings — for customers who want something understated rather than an illustrated or bordered design.

---

## D25 — Trailing Paws

**Style key:** `p21_paw_trail`
**Source idea:** P21 (EDSG 'Design 5' -- trailing paw prints + number + street, on a white card)
**Card shape:** 140×100mm landscape
**Status:** Proof approved

**Layout:** A diagonal trail of 5 illustrated paw prints (largest at bottom-left, shrinking toward top-right), house number and street name printed beside it, accent-recolourable.

**Assets required:** assets/icons/p21_paw_trail_icon.png (solid single-colour silhouette, accent-recolourable via recolour_silhouette; extracted via icon-silhouette-extraction from a Midjourney render, user-selected from 6 renders across 2 seed batches)

**Draft marketing angle:** DRAFT: real market-validated pet design (EDSG's own bestselling 'Design 5', seen at genuine Amazon scale) reproduced as an upgrade over the catalogue's existing single-paw accent (style 10) -- the 5-print diagonal trail reads as more premium/illustrated than a lone paw icon, first of a planned multi-species paw-trail line (cat/rabbit/fox/hedgehog to follow using the same layout).

**Text limits (print-tested Aug 2026):** street name safe up to **22
characters** before auto-shrink hits its floor -- the tightest limit of
any style tested so far. Two real bugs also found and fixed here:
number/street text was rendering in a fixed ink colour instead of
matching the icon's accent colour, and the street text's centre-X
didn't match the number's, so it wasn't actually centred underneath it.
Fixing the alignment also required recalculating the width budget,
since the number's centre sits well right-of-card-centre, giving the
street's right side much less clearance than its left. See
`P21_STREET_MAX_WIDTH` / `P21_STREET_CENTER_X` in `bin_sticker.py`.

---

## Keeping this file and the HTML gallery in sync

Every entry here should exist in both places with matching field values.
If you add a 4th finished design by hand later without asking for help,
mirror it into both files the same way:
- HTML: a new `.card` block in `bin_sticker_products_gallery.html`
- Here: a new `## D0N — Name` section with the same 5 fields plus a
  marketing-angle line

Both files are now kept in sync by the `products-gallery-add` skill's
`products_io.py` (mirrors `gallery_io.py`'s approach for the idea board:
one `entry` dict, written to both files together, then cross-verified).
Use that skill for new entries rather than hand-editing both files —
it also auto-renders the real proof thumbnail via `bin_sticker.py`,
which hand-editing can't do.
