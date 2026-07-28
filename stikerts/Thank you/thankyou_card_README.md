# Thank-you card generator — `thankyou_card.py`

Generates print-ready A6 thank-you cards, 4-up on a single A4 sheet, as a PDF.
This is the core rendering logic the future order-intake skill will call —
today you call it directly with order details as input.

## What it produces

- One PDF page per call to `render_sheet()`, sized A4.
- Up to 4 cards per page, arranged 2x2 (each cell is exactly A6, 105 x 148.5mm
  — a quarter of A4, so cutting is two straight lines, not a contour cut).
- Each card has a front half (flower motif + "Thank you" + brand name) and a
  back half (personalised thank-you line + care instructions + social handle
  + optional discount code block).
- Thin cut-guide lines and corner tick marks print in light ink so you can
  trim accurately with a paper trimmer/guillotine.

## Not compatible with

**Cricut Print Then Cut.** The cut-guide marks in this PDF are a visual
trim reference only. Cricut's Print Then Cut feature requires its own
proprietary registration marks, which Design Space inserts automatically
only when printing from within a live Design Space session — a PDF
generated and printed outside that flow will not be read correctly by the
machine's sensor. Since A6 is an exact quarter of A4, cutting is two
straight lines — a paper trimmer is faster than the Cricut here anyway and
keeps the Cricut free for sticker cutting. If you ever want a non-rectangular
card shape, that would need to be built directly in Design Space instead of
this script.

## Requirements

```
pip install reportlab
```

Everything else (`pdfmetrics`, `canvas`, `colors`) ships with reportlab.

## How the paper setting works — important

`order["paper"]` (`"white"` or `"kraft"`) changes **ink colour only** — it
does **not** draw a coloured background. The PDF page is always left
transparent/unfilled where the card sits; the paper's actual colour comes
from whichever physical cardstock (white or kraft) is loaded in the printer.
This is deliberate: printing a solid coloured rectangle to fake the kraft
look would waste ink and defeat the point of buying coloured stock. Text and
line colours are simply chosen to stay legible on each stock (dark charcoal
on white, dark brown on kraft).

## Usage

```python
from thankyou_card import render_sheet

orders = [
    {
        "buyer_name": "Priya",
        "product": "bin sticker set",
        "care_line": "laminated for weatherproof durability",
        "channel": "etsy",              # "ebay" | "etsy" | "website"
        "paper": "white",               # "white" | "kraft" — ink colour only, see above
        "flower_color": "blush",        # key into FLOWER_COLORS
        "brand_name": "Kerbside Craft Co.",
        "handle": "kerbsidecraftco",
        "discount_code": None,          # only ever rendered when channel == "website"
    },
    # ...up to 4 orders per sheet. Fewer than 4 is fine — remaining cells
    # are simply left blank, no placeholder printed.
]

render_sheet(orders, "cards_today.pdf")
```

### `order` dict — field reference

| Field           | Type          | Notes |
|-----------------|---------------|-------|
| `buyer_name`    | str           | First name only recommended (keeps the greeting line short enough to fit two wrapped lines) |
| `product`       | str           | Feeds into the greeting sentence: "your **{product}** is {care_line}" |
| `care_line`     | str           | Product-specific durability/care claim — keep in sync with what you actually test/claim (e.g. Bin-Sticker-Material-Test-Plan results) |
| `channel`       | `"ebay"` / `"etsy"` / `"website"` | Controls whether a discount code block can render at all — see platform rules below |
| `paper`         | `"white"` / `"kraft"` | Ink colour theme only, see above — must match the cardstock physically loaded |
| `flower_color`  | key in `FLOWER_COLORS` | See palette below |
| `brand_name`    | str           | Printed on the card front |
| `handle`        | str           | Printed without the `@` — script adds it |
| `discount_code` | str or `None` | Only rendered if `channel == "website"`, even if set — eBay/Etsy have no compliant way to carry a code on this card at present (see chat history: eBay's coded coupons need a paid Shop subscription; Etsy's own promo-code tool is a possible future addition but isn't wired in here yet) |

### Built-in flower colours (`FLOWER_COLORS`)

| Key           | Petal    | Centre   |
|---------------|----------|----------|
| `blush`       | `#D4537E`| `#993556`|
| `sage`        | `#639922`| `#3B6D11`|
| `dusty_blue`  | `#378ADD`| `#185FA5`|
| `terracotta`  | `#D85A30`| `#993C1D`|
| `lavender`    | `#7F77DD`| `#534AB7`|

Adding a new colour is a one-line addition to the `FLOWER_COLORS` dict at the
top of the file — no new artwork, no design tool. The flower itself is drawn
procedurally in `draw_flower()` (4 ellipse petals + a centre circle), so any
hex colour works immediately.

## Key functions

| Function | Purpose |
|---|---|
| `draw_flower(c, cx, cy, size, petal_hex, center_hex)` | Draws the flower motif at a given point/size/colour. Pure vector, no external assets. |
| `wrap_text(text, font, size, max_width)` | Simple word-wrap helper so the greeting line fits the card width regardless of name/product length. |
| `draw_card(c, ox, oy, order)` | Draws one full A6 card (front + back + cut guides) at a given page position. |
| `render_sheet(orders, out_path)` | Top-level entry point — lays out up to 4 `draw_card()` calls in a 2x2 grid on one A4 page and saves the PDF. |

## Cost per card (materials only)

Based on July 2026 UK pricing research:

- A4 250gsm white cardstock: ~£0.08/sheet ÷ 4 cards ≈ **£0.02/card**
- A4 250gsm kraft cardstock: ~£0.15–0.30/sheet ÷ 4 cards ≈ **£0.04–0.08/card**
- Ink (light coverage — line art + text): ~**£0.01/card**

Both are comfortably under the £0.15/unit already budgeted for
"Packaging: thank-you card" in `StickerBinStickersCosts.xlsx` (Bin Stickers -
Unit Economics sheet) — no change needed there.

## Known limitations / not yet built

- No CSV or marketplace-export input — orders are passed in directly as
  Python dicts for now, per current workflow.
- No automatic mapping from product → flower colour/theme yet (you're not
  sure of your design lines yet — `flower_color` is set manually per order
  for now; once you settle on real design families this can become a lookup
  table keyed by product/listing).
- No handling for order lists longer than 4 (i.e. multiple sheets in one
  call) — call `render_sheet()` once per batch of up to 4 for now.
