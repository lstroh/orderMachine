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

#### Moving the number or street text (location)

Edit `P02_NUMBER_CENTER_Y` or `P02_STREET_CENTER_Y` directly — these are
plain mm values (bottom-left origin, same as everywhere else in the
file):

```python
P02_NUMBER_CENTER_Y = 82.332 * mm   # vertical centre of "36"
P02_STREET_CENTER_Y = 66.151 * mm   # vertical centre of "GROVE STREET"
```

Increase to move up, decrease to move down. No side effects on anything
else. This is exactly what we did earlier in this project ("move the
number lower", "move the street text up") — small hand-tuned nudges on
top of the computed starting point are completely normal and expected
here.

There's no `_CENTER_X` for either — both are hardcoded to `cx` (card
horizontal centre) inside `_style_p02_house_banner`. Off-centre text
means editing that line directly (e.g. `cx + 3 * mm` instead of `cx`),
not adjusting a constant.

#### Changing the street text's curve

```python
P02_BANNER_CURVE_COEFFS = (a, b, c)   # mid_y(x) = a·x² + b·x + c
```

This is a parabola fitted to the real banner ribbon's shape, in the
source icon's own pixel space. Two things worth knowing before touching
it:

1. **`c` does nothing visually.** The curve is always measured *relative*
   to the text's own centre point, so the constant offset cancels out —
   only `a` and `b` actually affect the render.
2. **`a` controls how strong the curve is, `b` controls how skewed/
   off-centre the dip is:**
   - `a = 0` → flat text, no curve at all
   - Bigger `|a|` → more pronounced dip at the ends of the text
   - Negative `a` → flips direction, the ends arch *up* instead of down
   - `b` shifts where the highest/lowest point of the curve sits
     left-to-right — leave at 0 (or scale it alongside `a`) to keep the
     peak centred

We rendered a side-by-side comparison to make this concrete — original,
flat (`a=0`), 2× the curve strength, and flipped (`-a, -b`):

```python
import bin_sticker as bs
from reportlab.pdfgen import canvas

orig_coeffs = bs.P02_BANNER_CURVE_COEFFS
a, b, cc = orig_coeffs

variants = [
    ("original",          orig_coeffs),
    ("flat (a=0)",        (0, 0, cc)),
    ("2x stronger curve", (a * 2, b * 2, cc)),
    ("flipped (arch up)", (-a, -b, cc)),
]

c = canvas.Canvas("curve_variants.pdf", pagesize=(bs.CARD_W, bs.CARD_H))
for label, coeffs in variants:
    bs.P02_BANNER_CURVE_COEFFS = coeffs
    bs.draw_sticker(c, 0, 0, {"house_number": "36", "street_name": "Grove Street", "style": "house_banner"})
    c.showPage()
bs.P02_BANNER_CURVE_COEFFS = orig_coeffs  # restore before rendering anything else!
c.save()
```

Since `P02_BANNER_CURVE_COEFFS` is a module-level constant, reassigning
it like this affects every card rendered afterwards in the same
process — reset it back (as above) once you're done comparing, or you'll
get the wrong curve on unrelated orders rendered later in the same run.

Because this value came from measuring the actual banner artwork,
changing it means the text will stop matching the ribbon's real printed
shape — fine if that's the look you want, but worth knowing it's a
deliberate departure from "matches the icon exactly," not just a style
knob with no tradeoff.

#### Resizing or repositioning the icon itself

Edit `P02_ICON` (`x`, `y`, `w`, `h`) — but note `P02_ICON_SCALE`,
`P02_ICON_X_LEFT`, and `P02_ICON_Y_TOP` all need to change together with
it (they describe the same icon placement in a form the curve-fitting
code uses), or the text will stop lining up with the hollows. This isn't
a single-number edit — if you want to do this, it's easier to ask for
help regenerating the whole geometry block than to hand-adjust it.

#### Font sizes are automatic, not fixed

`_fit_font_size()` shrinks the number (44pt → down to 20pt) and street
name (19pt → down to 8pt) to fit the hollow widths, so unlike styles
1–10 there's no single font-size number to edit for a specific order;
it's already responsive to whatever text is in that order.

---

## 6. Changing `PAD` (the card margin)

`PAD` controls the gap between the true cut edge and the visible design
border — it exists as **cutting tolerance**, not just white space. If the
design border sat exactly on the cut line, any real-world guillotine
imprecision (a laminated vinyl stack is thicker/tougher than plain paper,
and this hasn't been formally tested against your actual material yet —
see `Bin-Sticker-Material-Test-Plan.md`'s open corner-punch/peel-nick
checks) would slice unevenly through the border on whichever side the cut
drifted. Smaller `PAD` = less margin for that drift = more visual risk if
your actual cutting turns out less precise than hoped. Current value:
**2mm** (changed from an original 6mm — see chat history for the
full tradeoff discussion).

### Styles 1–10: just change `PAD`

These styles write both the border and every icon position directly in
terms of `PAD` (e.g. `oy + CARD_H - PAD - 12*mm`), so both move together
automatically. House number/street text isn't tied to `PAD` at all (it
uses fixed `CARD_H` fractions), so it won't move — that's fine, it just
changes how much white space sits between the text and the now-closer
border.

### Style 11 (and any future hollow-icon style built the same way): NOT just `PAD`

P02's icon and nested text positions are fixed absolute constants,
independent of `PAD` — they were derived once from the source image's
pixel geometry (see §4) assuming a specific border position. Changing
`PAD` alone moves the border but leaves the icon exactly where it was,
which desyncs the two: either an inconsistently large gap (if `PAD`
shrunk) or an overlap risk (if `PAD` grew).

**The fix is not to nudge the mm constants directly** — rescale from the
original pixel measurements, the same way they were derived in the first
place. The recipe:

```python
# 1. Original, fixed, NEVER changes regardless of PAD:
IMG_W_PX, IMG_H_PX = 1359, 935          # source master image size
NUMBER_CENTROID_PX = (697.76, 358.95)   # from component_centroid() on the erased digit pixels
STREET_CENTROID_PX = (663.58, 630.85)   # same, for the erased letter pixels
HOUSE_GAP_PX = 409.5                     # from measure_gap() on the house wall ink
BANNER_USABLE_WIDTH_PX = 960             # from trace_band()'s clean x-range

# 2. Decide the new scale by keeping the icon's margin-to-border
#    proportionally the same as before (this is a design choice, not a
#    law — but it's the one used for the 6mm->2mm change and it keeps
#    the icon visually consistent with how the other 10 styles respond
#    to PAD):
OLD_PAD, NEW_PAD, OLD_SCALE = 6.0, 2.0, 0.06093
k = (CARD_W - 2*NEW_PAD) / (CARD_W - 2*OLD_PAD)
new_scale = OLD_SCALE * k

# 3. Everything else follows mechanically — icon size, position, text
#    centres, and max-widths are all `pixel_measurement * new_scale`,
#    converted through the same top-down-to-bottom-up flip used in §4
#    of this doc. BANNER_CURVE_COEFFS does NOT change — it's defined
#    in the source image's own pixel space, independent of card scale.
```

This is exactly what `icon-silhouette-extraction`'s
`references/deriving-placement-constants.md` walks through in more
detail (§1–3) — the same skill/process used to build P02 in the first
place applies here too, just re-run with a new target scale instead of a
new source image. **Always re-run the full test suite after (single card,
4-up sheet — this is what previously caught the sheet-position `ox` bug
— full gallery, and edge-case long text) rather than trusting the
arithmetic alone.**

---

## 7. Adding a new illustrated icon to styles 3/4/5/8/9/10

Drop a transparent PNG at the path listed in `ICON_ASSETS` for that
style (e.g. `assets/icons/house_icon.png` for style 5) and it's used
automatically—`_draw_icon()` checks `os.path.exists()` and falls back
to the vector shape if nothing's there. ~2000px on the long edge is
plenty (well over 300dpi at this print size).

This is simpler than style 11 because these icons are drawn as a
centred square (`cx - size/2, cy - size/2, size, size` with
`preserveAspectRatio=True`)—no hollow-nesting, no curve-fitting, just
a normal centred image.

---

## 8. Running the script

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

## 9. Colour palettes: `ACCENTS` vs `CLEAR_VINYL_ACCENTS`

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
    "accent": "golden_yellow",   # from CLEAR_VINYL_ACCENTS — works exactly like a normal accent key
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


