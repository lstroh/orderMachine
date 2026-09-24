# New feature: Cricut placement-reference SVG (Sep 2026)

## What this is

A new **optional** output, separate from the normal PDF sheet, for one
specific problem: the Cricut Explore 5 is being run in **cut-only mode**
(no Print Then Cut, no camera/registration), so there's no automatic way
to align a cut file to a printed sheet on the mat. This SVG is a visual
placement aid — load it into Design Space as a **background reference**
to position the real cut shapes against by eye. It is never sent to the
blade itself, and it is never part of a real customer order.

## What it looks like

A picture of the actual sheet layout, but **lines only** — no icons, no
house numbers, no street names. Four lines per sticker, each a distinct
colour (Design Space auto-splits an imported multi-colour SVG into one
layer per colour, which is exactly what this relies on):

- **Green** — the full physical page boundary (one rectangle, sharp
  corners, drawn once for the whole sheet — it's the page itself)
- **Grey** — each sticker's true edge (sharp corners) + the same 3mm
  corner tick marks `draw_base` already draws
- **Black** — each sticker's *real* accent-border outline, at that
  specific style's own true inset and corner radius (see below — this
  is computed per style, not a guess)
- **Red** — a kiss-cut reference line, a fixed 1mm inside the true edge,
  guaranteed to sit *outside* every style's own black border (confirmed
  by testing, not assumed — the smallest real border inset across the
  whole catalogue is 2mm, so 1mm always clears it)

## Where the code lives

- **`bin_sticker_core.py`** — the actual SVG-writing logic:
  `render_cricut_reference_svg(orders, out_path, card_w, card_h, cols,
  rows, page_size, style_pad_radius, margin_x=None, margin_y=None,
  kiss_cut_inset=1*mm, tick=3*mm)`. Shared by all three sizes, same
  pattern as `render_sheet_grid`/`render_gallery_grid` already are.
- **Each size script** (`bin_sticker_small.py`/`medium.py`/`large.py`)
  — two new things each:
  1. `STYLE_PAD_RADIUS` — a dict, `style_key -> (pad, radius)` or
     `None`. This is the important part: it's *not* one generic
     assumption, it's each style's **real** border pad/radius, pulled
     from what that style's own `draw_border` call actually uses (most
     styles use the shared default `PAD`/`BORDER_CORNER_RADIUS`, but
     `P27_PAD` (3mm), `P21_PAD`/`DUCK_FATHER_PAD` (3.4mm, the whole
     animal family shares this), and `P25_BORDER_RADIUS` (own distinct
     radius per size, NOT the shared one) are all genuinely different
     per style. `None` means "no real border to show" —
     `p09a_borderless` has none by design; `p25b_landscape_flourish`'s
     border is bespoke corner-bracket artwork, structurally too
     different from a simple rounded rectangle to represent this way.
  2. `render_cricut_reference(orders, out_path)` — a thin wrapper
     around the core function, using that size's own grid/margin
     settings. Same calling convention as `render_sheet`: pass the same
     `orders` list, it just ignores `house_number`/`street_name` since
     there's no text in this output.

## How to use it

```python
import bin_sticker_small as bs

orders = [
    {"house_number": "28", "street_name": "North Avenue", "style": "house_banner"},
    {"house_number": "28", "street_name": "North Avenue", "style": "p31_olive_wreath"},
    {"house_number": "28", "street_name": "North Avenue", "style": "p21_paw_trail"},
    {"house_number": "28", "street_name": "North Avenue", "style": "duck_family_father"},
]

bs.render_sheet(orders, "sheet.pdf")                    # unchanged, the real print file
bs.render_cricut_reference(orders, "reference.svg")      # NEW, optional, Cricut-only aid
```

Same pattern for `bin_sticker_medium`/`bin_sticker_large` — just note
their grids hold fewer cards per sheet (Medium: 2, Large: 1), so
`orders` can't exceed `cols*rows` for that size or it raises
`ValueError` (same guard `render_sheet_grid` already has).

## Important gotcha to carry forward — Design Space's import scale is unreliable

This was hard-won today through direct testing, not theory: an earlier
version of a similar SVG cut file imported into Design Space at ~2.77x
the intended size using explicit `mm` unit suffixes. Switching to plain
unitless coordinates (1 unit = 1px @ 96dpi, the SVG spec's own default —
what this feature uses) did better, but a *separate* file still once
imported at ~1.3x too large with the exact same convention. There is no
known-reliable unit convention for this importer as of today.

**So: every time this SVG is loaded into Design Space, check the Size
panel for the whole imported group before trusting it** — it should read
the true page size (e.g. 29.7 × 21.0cm for Small). If it's off, the
fix that's worked every time so far is manual: select the group, turn
on the aspect-ratio lock, type the correct width directly into the Size
field, and confirm height auto-corrects. This isn't a one-time fix to
apply once and forget — treat it as a standing check on every import.

## What was verified before this shipped

- All three `STYLE_PAD_RADIUS` dicts cover exactly the same 25 keys as
  that size's own `STYLES` dict (no style silently missing).
- Every one of the 25 styles renders without error at all three sizes,
  including both `None` (no-border) cases (`p09a_borderless`,
  `p25b_landscape_flourish`).
- The over-capacity `ValueError` guard fires correctly (tested directly
  by requesting more orders than a grid holds).
- Every generated SVG parses as valid XML.
- Rendered each size's output independently (via `cairosvg`, outside
  Design Space) and visually confirmed the 4-colour layout is correct,
  including that each size's own asymmetric print margin (Small's 14mm
  left, Medium's 14mm top, Large's 14mm left) shows up correctly in the
  green frame's position relative to the grey cards.
- **Full regression check**: rendered all 25 styles' real PDF output
  (`draw_sticker`) both before and after this change and diffed the raw
  PDF content streams byte-for-byte. Zero differences — this feature is
  purely additive and cannot have changed any existing print output.

## What this deliberately does NOT do

- Does not touch `render_sheet`, `render_gallery`, or `draw_sticker` at
  all — it's a fully separate, opt-in function you call only when you
  want this specific output.
- Does not include any of the real illustrated design content (icons,
  numbers, street names) — reference lines only, by design, not an
  oversight.
- Does not attempt to represent `p25b_landscape_flourish`'s real
  corner-bracket border shape — that style's black layer is simply
  omitted (grey and red still show normally for it).
