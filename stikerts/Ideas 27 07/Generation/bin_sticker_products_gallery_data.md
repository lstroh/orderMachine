# Finished Products — Full Data Export

*Text-only companion to `bin_sticker_products_gallery.html` — same entries, no images, kept lightweight for reference/search. IDs match the HTML gallery exactly; open that file to see the actual proof thumbnails.*

**Total entries:** 3
**Status:** 3 proof approved · 0 pending

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
