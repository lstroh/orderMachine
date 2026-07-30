# bin_sticker.py — Documentation

Generates print-ready PDFs for Kerbside Craft Co.'s wheelie bin number stickers:
100×140mm cards, 4-up on an A4 sheet, 11 design presets. Built on `reportlab`.

This doc covers how the file is structured, what each style does, and —
since it comes up a lot — exactly how to move text and icons around by
hand if you want to tweak a design yourself.

---

## 1. The basics: coordinate system and card layout

Everything is drawn in **millimetres**, using reportlab's `mm` unit
(`from reportlab.lib.units import mm` — you'll see `12 * mm` etc. throughout).

- **Origin `(ox, oy)`**: the bottom-left corner of one card. Every draw
  function takes `(c, ox, oy, order)` and positions everything relative to
  that corner — this is what lets the same style function place a card
  in any of the 4 slots on an A4 sheet.
- **Y increases upward** (standard PDF/reportlab convention) — `oy` is the
  bottom of the card, `oy + CARD_H` is the top. This trips people up if
  you're used to image-editing tools (Photoshop, PowerPoint, CSS) where Y
  increases *downward*. Keep this in mind if you're ever comparing
  coordinates against a mockup made in one of those.
- **Key constants** (top of file):
  ```python
  CARD_W, CARD_H = 100 * mm, 140 * mm   # card size
  PAD = 6 * mm                          # border inset from the cut edge
  ```
- **`cx = ox + CARD_W / 2`** — every style computes this once and uses it
  to horizontally centre things. If you want to move something
  left/right, you're usually adjusting an offset *from* `cx`, not `cx`
  itself.

---

## 2. File structure

| Section | What's there |
|---|---|
| Constants & `ICON_ASSETS` | Card size, colours, and the PNG-icon lookup table |
| `recolour_silhouette` / `_p02_icon_path` | Recolours a single-colour silhouette PNG to any accent colour, with disk caching |
| `_draw_icon` / `_draw_icon_rotated` | Draws a PNG if one exists at the `ICON_ASSETS` path, otherwise falls back to a built-in vector shape |
| `ACCENTS` | The 6 accent colours every style can use |
| `_draw_base` / `_draw_border` | Every card's white background + cut-guide corner ticks, and the border line (single/double/dashed) |
| Vector icon helpers (`draw_flower_icon`, `draw_house_icon`, etc.) | Plain-shape fallback icons, no external files needed |
| **`_style_*` functions (11 of them)** | **One per design — this is what you'll edit to move text/icons** |
| `_fit_font_size` | Shrinks text to fit a given width — used by style 11 |
| P02 geometry block + `_draw_curved_text` | Style 11's icon-nesting and curved-text machinery (see §4) |
| `STYLES` / `STYLE_LABELS` | Dicts mapping a style key (e.g. `"classic"`) to its function and display label |
| `draw_sticker` / `render_sheet` / `render_gallery` | Top-level entry points |

---

## 3. The 11 styles

| # | Key | Icon | Number font | Street font | Border |
|---|---|---|---|---|---|
| 1 | `classic` | none (rule + dot divider) | Times-Bold 58 | Times-Roman 17 | double |
| 2 | `minimal` | none (rule line) | Helvetica-Bold 62 | Helvetica 18 | single |
| 3 | `floral` | flower (vector/PNG) | Times-Bold 52 | Times-Italic 16 | single |
| 4 | `recycle` | recycle loop (vector/PNG) | Helvetica-Bold 54 | Helvetica 16 | single |
| 5 | `house` | house silhouette (vector/PNG) | Helvetica-Bold 54 | Helvetica 16 | single |
| 6 | `reverse_block` | none (filled colour block) | Helvetica-Bold 62 (white) | Helvetica 17 (white) | single |
| 7 | `split_panel` | none (colour band) | Helvetica-Bold 56 (white) | Times-Roman 17 | single |
| 8 | `vintage` | postmark circle (vector/PNG) | Times-Roman 54 | Times-Italic 15 | dashed |
| 9 | `corner_flourish` | 4× corner ornament (vector/PNG) | Times-Bold 54 | Times-Roman 16 | double |
| 10 | `paw` | paw print (vector/PNG) | Helvetica-Bold 54 | Helvetica-Oblique 16 | single |
| 11 | `house_banner` | illustrated house+flowers+banner (PNG only, no vector fallback shape) | Helvetica-Bold, auto-fit 20–44 | Helvetica-Bold, auto-fit 8–19 (curved) | single |

Styles 3, 4, 5, 8, 9, 10 use `ICON_ASSETS` — if you drop a matching PNG
at the path listed in that dict, the style automatically switches from
its plain vector icon to the illustrated one. No code change needed.

Style 11 (`house_banner`) is different from the other 10 in two ways
that matter if you want to edit it:
- It's driven entirely by one illustrated PNG (`assets/icons/house_banner_master.png`) — there's no vector-only fallback design, only a rough placeholder if that file goes missing.
- The number and street text aren't placed at fixed coordinates — they're
  centred on specific hollow areas *inside* the icon artwork (the empty
  house body and the empty banner ribbon), and the street text curves to
  match the banner's shape. See §4.

---

## 4. Style 11 in detail: how the icon-nested text works

This is the style most people will ask "how do I move the text" about,
so it gets its own section.

**The icon (`assets/icons/house_banner_master.png`)** is a transparent
silhouette — a house outline with two hollow "windows": the house body
(empty, for the number) and the banner ribbon (empty, for the street
name). `_p02_icon_path()` recolours this master file to whichever accent
is requested and caches the result to
`assets/icons/house_banner_{accent}.png`, so the per-pixel recolour loop
only runs once per accent, not once per order.

**Where the numbers came from:** rather than eyeballing positions, the
house and banner hollow regions were found programmatically (by
analysing the PNG's transparency channel) and the text was centred on
their actual pixel geometry — the house hollow's *centroid* (not its
bounding-box middle, since it's roof-shaped, not rectangular) for the
number, and the banner ribbon's own midline for the street name. The
street name's curve is a quadratic fitted to the ribbon's real top/bottom
edge, sampled column-by-column from the icon file — not an arbitrary arc.

The relevant constants:

```python
P02_ICON = dict(x=7.995*mm, y=16.614*mm, w=82.772*mm, h=105.684*mm)  # icon position/size on the card
P02_ICON_SCALE = 0.206415        # mm per pixel of the source PNG
P02_ICON_X_LEFT = 7.995 * mm     # icon's left edge (mm from card left)
P02_ICON_Y_TOP = 17.702 * mm     # icon's top edge (mm from card TOP, not bottom)

P02_NUMBER_CENTER_Y = 82.332 * mm   # where "36" is vertically centred (card bottom-left coords)
P02_NUMBER_MAX_WIDTH = 21.26 * 0.90 * mm   # house hollow width, minus 10% safety margin

P02_STREET_CENTER_Y = 66.151 * mm   # where the street name is vertically centred
P02_STREET_MAX_WIDTH = 59.03 * 0.90 * mm   # banner hollow width, minus 10% safety margin

P02_BANNER_CURVE_COEFFS = (1.23454754e-03, -4.83493871e-01, 3.19350864e+02)  # a, b, c of the fitted curve
```

**All of these numbers are specific to the current `house_banner_master.png`.**
If that source image is ever replaced with different artwork, every one
of these values would need re-deriving from the new file — they're not
generic percentages, they're measurements of this specific icon's pixels.

---

## 5. Moving text yourself — what's easy and what isn't

### Styles 1–10: easy, just edit a fraction

Every one of these positions text with a line like:

```python
c.drawCentredString(cx, oy + CARD_H * 0.48, order["house_number"])
```

`CARD_H * 0.48` means "48% of the way up the card, from the bottom."
To move something, change that fraction:
- **Move it up** → increase the number (e.g. `0.48` → `0.52`)
- **Move it down** → decrease it (e.g. `0.48` → `0.44`)
- Every `0.01` ≈ 1.4mm of vertical movement (1% of the 140mm card height)

To move something **left/right**, most positions use `cx` directly
(centred). To offset from centre, do e.g. `cx - 5 * mm` instead of `cx`.

Icons in these styles are positioned as `(cx, oy + CARD_H - PAD - 12*mm)`
— that's "centred horizontally, `12mm` down from the border's inner top
edge." Change the `12 * mm` to move it up/down; add `± N*mm` to `cx` to
move it left/right.

**This is safe to hand-edit freely** — nothing else depends on these
values, so changing one style's text position doesn't affect anything
else.

### Style 11 (`house_banner`): possible, but do it deliberately

You *can* move things here too, but because the text is centred on
specific hollow regions inside the icon artwork, moving the text without
also moving the icon (or vice versa) will make them drift apart — the
number sliding out from inside the house, or the street text sliding off
the banner.

**If you just want to nudge text position without touching the icon:**
edit `P02_NUMBER_CENTER_Y` or `P02_STREET_CENTER_Y` directly — these are
plain mm values (bottom-left origin, same as everywhere else in the
file). Increase to move up, decrease to move down. This is exactly what
we did earlier in this project ("move the number lower", "move the
street text up") — small hand-tuned nudges on top of the computed
starting point are completely normal and expected here.

**If you want to resize or reposition the icon itself:** edit `P02_ICON`
(`x`, `y`, `w`, `h`) — but note `P02_ICON_SCALE`, `P02_ICON_X_LEFT`, and
`P02_ICON_Y_TOP` all need to change together with it (they describe the
same icon placement in a form the curve-fitting code uses), or the text
will stop lining up with the hollows. This isn't a single-number edit —
if you want to do this, it's easier to ask for help regenerating the
whole geometry block than to hand-adjust it.

**Font sizes are automatic, not fixed** — `_fit_font_size()` shrinks the
number (44pt → down to 20pt) and street name (19pt → down to 8pt) to fit
the hollow widths, so unlike styles 1–10 there's no single font-size
number to edit for a specific order; it's already responsive to
whatever text is in that order.

---

## 6. Adding a new illustrated icon to styles 3/4/5/8/9/10

Drop a transparent PNG at the path listed in `ICON_ASSETS` for that
style (e.g. `assets/icons/house_icon.png` for style 5) and it's used
automatically — `_draw_icon()` checks `os.path.exists()` and falls back
to the vector shape if nothing's there. ~2000px on the long edge is
plenty (well over 300dpi at this print size).

This is simpler than style 11 because these icons are drawn as a
centred square (`cx - size/2, cy - size/2, size, size` with
`preserveAspectRatio=True`) — no hollow-nesting, no curve-fitting, just
a normal centred image.

---

## 7. Running the script

### Quickest option: just run the file

```bash
python3 bin_sticker.py
```

This runs the `if __name__ == "__main__":` block at the bottom, which
renders one of every style (all 11) into a gallery PDF at
`/mnt/user-data/outputs/bin_sticker_design_gallery.pdf` — useful for
comparing designs side by side, not for producing a real customer order.

For anything else — a single real sticker, a specific accent colour, a
full sheet of 4 — write a small script that imports the file and calls
its functions directly, as below. Save any of these as e.g. `make.py` in
the same folder as `bin_sticker.py` and run `python3 make.py`.

### One card, one style

```python
from reportlab.pdfgen import canvas
import bin_sticker as bs

order = {
    "house_number": "36",
    "street_name": "Grove Street",
    "style": "minimal",
    "accent": "navy",
}

c = canvas.Canvas("one_card.pdf", pagesize=(bs.CARD_W, bs.CARD_H))
bs.draw_sticker(c, 0, 0, order)
c.showPage()
c.save()
```

### A full A4 sheet of 4 (real order — this is what you'd actually print)

```python
import bin_sticker as bs

orders = [
    {"house_number": "36", "street_name": "Grove Street",  "style": "minimal",  "accent": "navy"},
    {"house_number": "12", "street_name": "Elm Close",      "style": "classic",  "accent": "charcoal"},
    {"house_number": "7",  "street_name": "Rye",            "style": "floral",   "accent": "terracotta"},
    {"house_number": "128","street_name": "Kings Cross Ave","style": "house",    "accent": "navy"},
]
bs.render_sheet(orders, "sheet.pdf")
```
`render_sheet` takes up to 4 orders and fills all 4 slots on one A4 page.
Fewer than 4 just leaves the remaining slots blank.

### Gallery of every style, using your own sample text

```python
import bin_sticker as bs

sample_order = {"house_number": "28", "street_name": "North Avenue"}
bs.render_gallery(list(bs.STYLES.keys()), sample_order, "gallery.pdf")
```
Pass a subset of `bs.STYLES.keys()` instead of all of them if you only
want to compare a few styles.

### Style 11 (`house_banner`) example

Same `draw_sticker` / `render_sheet` calls as any other style — just set
`"style": "house_banner"`. The only thing worth calling out: the master
icon (`assets/icons/house_banner_master.png`) has to exist relative to
wherever you run the script from, since accent-coloured versions are
generated from it on first use (see §4). Run this from the same folder
`bin_sticker.py` lives in, with its `assets/` folder alongside it.

```python
from reportlab.pdfgen import canvas
import bin_sticker as bs

order = {
    "house_number": "36",
    "street_name": "Grove Street",
    "style": "house_banner",
    "accent": "navy",       # try "forest", "berry", "terracotta", "mustard", "charcoal" too
}

c = canvas.Canvas("p02_card.pdf", pagesize=(bs.CARD_W, bs.CARD_H))
bs.draw_sticker(c, 0, 0, order)
c.showPage()
c.save()
```

First run with a new accent colour (e.g. the first time you ever use
`"berry"`) will take a moment longer — that's `recolour_silhouette()`
running its per-pixel loop once to build
`assets/icons/house_banner_berry.png`. Every order after that using
`"berry"` reuses the cached file and is instant.

Longer text works too — the number and street name auto-shrink to fit
their hollows, so you don't need to do anything differently for e.g.
`"house_number": "1400"` or a long street name:

```python
order = {
    "house_number": "1400",
    "street_name": "Old Winchester Road",
    "style": "house_banner",
    "accent": "terracotta",
}
```

### Previewing a PDF as an image (optional, for checking layout without printing)

```python
from pdf2image import convert_from_path

pages = convert_from_path("p02_card.pdf", dpi=300)
pages[0].save("p02_card_preview.png")
```
Needs `pip install pdf2image` and the system `poppler-utils` package
(`pdftoppm`) installed — this is how every test render earlier in this
project was turned into a viewable image.

## 8. Colour palettes: `ACCENTS` vs `CLEAR_VINYL_ACCENTS`

There are two separate colour dicts in the file now, for two different
production methods — don't treat them as interchangeable style choices.

### `ACCENTS` — the original 6, for standard white-card printing

```python
ACCENTS = {
    "charcoal":   "#2C2C2A",
    "forest":     "#2F5233",
    "navy":       "#1F3A5F",
    "terracotta": "#B0532E",
    "berry":      "#7A2E4D",
    "mustard":    "#B4841F",
}
```
These are what every style has been using all along — darker, muted
tones chosen for contrast against the white card stock `_draw_base()`
prints on.

### `CLEAR_VINYL_ACCENTS` — the 5 from the material test plan

```python
CLEAR_VINYL_ACCENTS = {
    "golden_yellow": "#F2B705",
    "cream":         "#F5E8C8",
    "burnt_amber":   "#D9782E",
    "powder_blue":   "#8FB8DE",
    "dusty_rose":    "#E08A73",
}
```
These come from `Bin-Sticker-Material-Test-Plan.md` (Addendum 2) and
`Idea-Board-Solutions-Reference.md` §2F — Technique F, printing coloured
ink on **clear** vinyl and applying it straight to a coloured bin, no
white card behind it. They were picked for a completely different reason
than `ACCENTS`: staying visible when printed on semi-transparent film
over dark bin plastic, not for how they look on white card. Green and
brown were deliberately left out since they'd blend into 2 of the 3 most
common bin colours.

**Important — two things this does NOT do yet:**

1. **Not production-validated.** Per the material test plan, Addendum 2
   (visibility on black/green/brown bins) and Addendum 3 (UV/scratch
   durability on clear film) are both still pending physical testing.
   These are the colours *to test*, not colours confirmed to work.
2. **Doesn't switch the card to clear vinyl.** Picking one of these as
   your `accent` only changes the ink colour used for text/icon/border —
   `_draw_base()` still fills a solid white background behind it, same
   as every other style. For a genuine clear-vinyl-direct-on-bin result
   (no white fill, letting the film itself be the "background"), the
   script would need a separate render mode that skips that fill — not
   built yet. Worth asking for once Addendum 2/3 testing confirms which
   colour(s) are actually worth using for real.

### How to use either palette

Every style's `accent` field now checks both palettes automatically —
you don't need to specify which dict a key comes from:

```python
order = {
    "house_number": "36",
    "street_name": "Grove Street",
    "style": "minimal",
    "accent": "golden_yellow",   # from CLEAR_VINYL_ACCENTS -- works exactly like a normal accent key
}
```

This is handled by a small helper, `_resolve_accent()`, which checks
`ACCENTS` first, then `CLEAR_VINYL_ACCENTS`, and falls back to a default
if the key isn't in either:

```python
def _resolve_accent(accent_key, default="navy"):
    if accent_key in ACCENTS:
        return ACCENTS[accent_key]
    if accent_key in CLEAR_VINYL_ACCENTS:
        return CLEAR_VINYL_ACCENTS[accent_key]
    return ACCENTS[default]
```

Every style function, `_draw_border`, and style 11's icon-recolouring
all go through this helper now, so any of the 11 keys (6 + 5) works
anywhere `accent` is accepted.

### `order` dict reference

```python
{
    "house_number": "36",
    "street_name": "Grove Street",
    "style": "house_banner",    # key in STYLES, defaults to "minimal"
    "accent": "navy",           # key in ACCENTS or CLEAR_VINYL_ACCENTS, each style has its own default
    "bin_type": None,           # only used by the "recycle" style
}
```


