# Finished Products — Full Data Export

*Text-only companion to `bin_sticker_products_gallery.html` — same entries, no images, kept lightweight for reference/search. IDs match the HTML gallery exactly; open that file to see the actual proof thumbnails.*

**Total entries:** 17
**Status:** 15 proof approved · 2 pending

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
