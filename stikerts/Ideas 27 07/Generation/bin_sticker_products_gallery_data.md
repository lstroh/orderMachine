# Finished Products — Full Data Export

*Text-only companion to `bin_sticker_products_gallery.html` — same entries, no images, kept lightweight for reference/search. IDs match the HTML gallery exactly; open that file to see the actual proof thumbnails.*

**Total entries:** 4
**Status:** 3 proof approved · 1 pending

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
**Source idea:** P27 (landscape variant — P27's own idea-board entry has
`fits_spec=Yes` for the standard 100×140mm PORTRAIT card; this is a
deliberate landscape departure requested by the user, same as D02/D03's
relationship to P25's portrait-spec entry, not "P27 built to spec")
**Card shape:** 140×100mm landscape
**Status:** Pending — proof rendered and structurally verified, not yet
user-approved (see open issue below)

**Layout:** Bold house-outline icon with a chimney (thick line, no fill),
house number nested inside the hollow interior, street name printed
below.

**Assets required:** `assets/icons/p27_house_icon.png` — extracted via
`icon-silhouette-extraction` from the Midjourney/Editor reference render
(2624×1856px). The house+chimney was the only component kept; the
placeholder "36" digits and "GROVE STREET" letters (11 components,
exact match for G-R-O-V-E-S-T-R-E-E-T) were identified by connected-
component analysis and erased to a hollow, then their own erased
positions used as the real placement constants — same approach as D01.
Per-accent recoloured copies (`p27_house_{accent}.png`) generated and
cached automatically on first render, same pattern as D01/D02/D03.

**Open issue — font/width mismatch:** the source mockup's typeface is
narrower per unit of cap-height than Helvetica-Bold (this codebase's
only available bold sans). For "GROVE STREET" (11 characters) this
compounds enough that the width-safe auto-fit lands at ~34.5pt, well
under the ~65pt the measured cap-height implies — the street name prints
smaller/lighter than the mockup's proportions. The house number is
affected less (~107.5pt vs. ~129pt implied). Kept the width-safe
behaviour (consistent with every other `_fit_font_size` use in this
file) rather than risk overflowing the border. Revisit with a real
condensed bold TTF if matching the mockup's visual weight matters more
than the safety margin — see `bin_sticker.py`'s `P27_STREET_MAX_WIDTH`
comment for the full numbers.

**Border style:** the source render came out with a double-line border
despite the mockup prompt asking for a single line. Built with SINGLE
here to match the original prompt intent rather than what the render
happened to show — flag if double was actually wanted.

**Cutting margin:** uses its own 3mm cut-to-border tolerance (`P27_PAD`)
instead of the shared 2mm `PAD` every other style uses — scoped to this
design only, not a global change. Safe as a pure border-inset shift with
no icon/text rescaling needed, since the icon already sits 3.57–29mm
clear of the card edges on every side.

**Draft marketing angle:** the strongest real-world sales evidence of
any design in the lineup — P27 is the 4th sighting of the house-outline-
with-nested-number concept on the idea board (after P10/P19/P20), from
the same trusted EDSG line as D02/D03's Amazon's Choice comparables, and
the chimney detail differentiates it from the plainer versions.

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
