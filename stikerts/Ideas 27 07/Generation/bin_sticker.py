"""
Bin sticker generator — Kerbside Craft Co.
4x 100x140mm stickers, 2x2, one A4 sheet, printed on white vinyl.
Same cut-guide-lines-and-tick-marks convention as thankyou_card.py
(guillotine + corner rounder punch + Slice 00200 workflow — this PDF
does NOT use Cricut Print Then Cut / registration marks).

10 design presets, researched July 2026 against current bestseller/trend
categories (Etsy "wheelie bin numbers" bestseller list, wheeliebinnumbers.net
popular collections, and a Dec-2025 UK design-ideas roundup). Every icon is
drawn as plain vector shapes in this file — not traced/copied from any
seller's artwork — see the chat writeup for which real listings each style
is responding to and why.

Every sticker is bordered (single or double line, or a filled colour block
which reads as its own border) — see chat history for why "no border" was
dropped as a differentiator.
"""

from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor, white
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase.pdfmetrics import stringWidth, getAscentDescent
from PIL import Image
import os
import math

CARD_W, CARD_H = 100 * mm, 140 * mm
GUIDE = "#CCCCCC"
PAD = 2 * mm  # inset of the design/border from the cut edge

# All icon paths in this file (ICON_ASSETS, P02_ICON_MASTER) are written
# as relative strings like "assets/icons/house_icon.png" for readability.
# Resolved through this function so they work no matter what directory
# the script is *run* from -- without it, running e.g.
# `python3 /some/other/folder/make_order.py` that imports this file would
# silently fail to find the assets (relative paths resolve against the
# current working directory, not this file's location) and every style
# would fall back to its plain vector icon with no error raised.
_SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))


def _asset_path(rel_path):
    return os.path.join(_SCRIPT_DIR, rel_path)

BG = "#FFFFFF"
INK = "#111111"
INK_MUTED = "#555555"

# Illustrated-artwork upgrade path: drop a PNG at the path below (exported
# from Vectorizer.ai after the Midjourney generation workflow — see chat
# history for the prompts/process) and the matching style automatically
# switches from the built-in vector shape to the real artwork. No other
# code change needed. PNG needs a transparent background (mask='auto'
# below relies on that) — Vectorizer.ai's export already gives you this.
# Recommended source resolution: ~2000px on the long edge (comfortably
# over 300dpi at this sticker's print size, no benefit from going higher).
ICON_ASSETS = {
    "floral": "assets/icons/flower_icon.png",
    "recycle": "assets/icons/recycle_icon.png",
    "house": "assets/icons/house_icon.png",
    "vintage": "assets/icons/postmark_icon.png",
    "corner_flourish": "assets/icons/flourish_icon.png",
    "paw": "assets/icons/paw_icon.png",
}

# ---------------------------------------------------------------------------
# P02 (house + flowers + banner illustrated icon) — the source art is a
# single-colour transparent silhouette (assets/icons/house_banner_master.png),
# recoloured per accent on first use and cached to disk so the per-pixel
# recolour loop only ever runs once per accent, not once per order.
# ---------------------------------------------------------------------------
P02_ICON_MASTER = "assets/icons/house_banner_master.png"


def recolour_silhouette(in_path, out_path, hex_color):
    """Recolours a single-colour silhouette PNG to any hex value, using
    its own alpha channel as the mask (so anti-aliased edges stay clean)."""
    img = Image.open(in_path).convert("RGBA")
    r, g, b = tuple(int(hex_color.lstrip("#")[i:i + 2], 16) for i in (0, 2, 4))
    pixels = img.load()
    for y in range(img.height):
        for x in range(img.width):
            _, _, _, a = pixels[x, y]
            if a > 0:
                pixels[x, y] = (r, g, b, a)
    img.save(out_path)


def _p02_icon_path(accent_key):
    """Returns the cached, accent-coloured icon PNG, generating it from
    the master silhouette on first use. Returns None if the master art
    isn't present (caller should fall back to the vector house icon)."""
    master = _asset_path(P02_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _asset_path(f"assets/icons/house_banner_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _draw_icon(c, cx, cy, size, color, style_key, vector_fn):
    """Use the illustrated PNG in ICON_ASSETS if it exists on disk,
    otherwise fall back to the built-in vector shape. This is the single
    switchover point — once real artwork lands at the path above, every
    sticker using that style upgrades automatically."""
    asset_path = ICON_ASSETS.get(style_key)
    if asset_path and os.path.exists(_asset_path(asset_path)):
        img = ImageReader(_asset_path(asset_path))
        c.drawImage(img, cx - size / 2, cy - size / 2, size, size,
                    mask='auto', preserveAspectRatio=True)
    else:
        vector_fn(c, cx, cy, size, color)


def _draw_icon_rotated(c, cx, cy, size, color, style_key, vector_fn, rot=0):
    """Same as _draw_icon, but for placements that need rotating (e.g.
    the same corner ornament used 4x at 4 different angles). Works for
    both the asset path (rotates the canvas before drawImage) and the
    vector fallback (passes rot straight through)."""
    asset_path = ICON_ASSETS.get(style_key)
    if asset_path and os.path.exists(_asset_path(asset_path)):
        img = ImageReader(_asset_path(asset_path))
        c.saveState()
        c.translate(cx, cy)
        c.rotate(rot)
        c.drawImage(img, -size / 2, -size / 2, size, size,
                    mask='auto', preserveAspectRatio=True)
        c.restoreState()
    else:
        vector_fn(c, cx, cy, size, color, rot)

ACCENTS = {
    "charcoal":   "#2C2C2A",
    "forest":     "#2F5233",
    "navy":       "#1F3A5F",
    "terracotta": "#B0532E",
    "berry":      "#7A2E4D",
    "mustard":    "#B4841F",
}

# Second palette for a different production method: printing coloured ink
# on CLEAR vinyl and applying direct to a coloured bin, no white card
# behind it (Technique F in Idea-Board-Solutions-Reference.md). These 5
# were chosen for visibility on dark bin plastic (green/brown deliberately
# avoided so they don't blend into 2 of the 3 most common bin colours) --
# they are NOT a stylistic alternative to ACCENTS, they solve a different
# problem (opacity on clear film) and are picked for that, not for how
# they look on a white card.
#
# STATUS: not yet production-validated. Bin-Sticker-Material-Test-Plan.md
# Addendum 2 (visibility on black/green/brown) and Addendum 3 (UV/scratch
# durability on clear film specifically) are both still pending physical
# testing as of this palette being added to the script.
#
# ALSO NOTE: selecting one of these colours via `accent` only changes the
# ink colour. It does NOT change the card itself to clear/transparent --
# _draw_base() below still fills a solid white background, because this
# script was built for printing on opaque white card stock. Genuine
# clear-vinyl output (no white fill, since the film itself is the
# "background") would need a separate render mode that isn't built yet --
# ask if you want that added once Addendum 2/3 testing confirms these
# colours are worth using for real.
CLEAR_VINYL_ACCENTS = {
    "golden_yellow": "#F2B705",
    "cream":         "#F5E8C8",
    "burnt_amber":   "#D9782E",
    "powder_blue":   "#8FB8DE",
    "dusty_rose":    "#E08A73",
}


def _resolve_accent(accent_key, default="navy"):
    """Looks up an accent key in ACCENTS first, then CLEAR_VINYL_ACCENTS,
    so any style function can accept a key from either palette. Falls
    back to ACCENTS[default] if the key isn't found in either."""
    if accent_key in ACCENTS:
        return ACCENTS[accent_key]
    if accent_key in CLEAR_VINYL_ACCENTS:
        return CLEAR_VINYL_ACCENTS[accent_key]
    return ACCENTS[default]

# ---------------------------------------------------------------------------
# Base layer (every sticker) and border layer (drawn LAST so colour-block
# styles never paint over it)
# ---------------------------------------------------------------------------

def _draw_base(c, ox, oy, w=CARD_W, h=CARD_H):
    c.setFillColor(HexColor(BG))
    c.rect(ox, oy, w, h, fill=1, stroke=0)

    c.setStrokeColor(HexColor(GUIDE))
    c.setLineWidth(0.4)
    c.rect(ox, oy, w, h, fill=0, stroke=1)

    tick = 3 * mm
    for cx, cy, dx, dy in [
        (ox, oy, -tick, -tick), (ox + w, oy, tick, -tick),
        (ox, oy + h, -tick, tick), (ox + w, oy + h, tick, tick),
    ]:
        c.line(cx, cy, cx + dx, cy)
        c.line(cx, cy, cx, cy + dy)


def _draw_border(c, ox, oy, order, weight="single", w=CARD_W, h=CARD_H):
    accent = HexColor(_resolve_accent(order.get("accent", "charcoal")))
    c.setStrokeColor(accent)
    c.setLineWidth(1.1)
    c.setDash()
    c.rect(ox + PAD, oy + PAD, w - 2 * PAD, h - 2 * PAD, fill=0, stroke=1)
    if weight == "double":
        c.setLineWidth(0.5)
        inset = PAD + 2.2 * mm
        c.rect(ox + inset, oy + inset, w - 2 * inset, h - 2 * inset, fill=0, stroke=1)
    elif weight == "dashed":
        c.setDash(2, 2)
        c.setLineWidth(0.7)
        inset = PAD + 2.0 * mm
        c.rect(ox + inset, oy + inset, w - 2 * inset, h - 2 * inset, fill=0, stroke=1)
        c.setDash()

# ---------------------------------------------------------------------------
# Vector icon helpers — plain shapes only, no external art
# ---------------------------------------------------------------------------

def draw_flower_icon(c, cx, cy, size, color):
    c.saveState()
    # flanking leaves, drawn first so the flower sits on top
    c.setFillColor(HexColor(color))
    leaf_w, leaf_h = size * 0.55, size * 0.20
    for side in (-1, 1):
        c.saveState()
        c.translate(cx + side * size * 0.5, cy - size * 0.08)
        c.rotate(side * 25)
        c.ellipse(-leaf_w / 2, -leaf_h / 2, leaf_w / 2, leaf_h / 2, fill=1, stroke=0)
        c.restoreState()
    pw, ph = size * 0.35, size * 0.5
    for dx, dy in [(0, 1), (0, -1)]:
        c.ellipse(cx - pw / 2, cy + dy * size * 0.1, cx + pw / 2, cy + dy * size * 0.1 + dy * ph, fill=1, stroke=0)
    for dx, dy in [(1, 0), (-1, 0)]:
        c.ellipse(cx + dx * size * 0.1, cy - pw / 2, cx + dx * size * 0.1 + dx * ph, cy + pw / 2, fill=1, stroke=0)
    c.setFillColor(HexColor("#4A3B1D"))
    r = size * 0.13
    c.ellipse(cx - r, cy - r, cx + r, cy + r, fill=1, stroke=0)
    c.restoreState()


def draw_recycle_icon(c, cx, cy, size, color):
    """3-arrow loop with connecting arcs — generic/universal recycling
    motif, not a traced copy of any brand's mark. Previous version was
    3 disconnected arrowheads with no connecting body, which read as
    small flecks rather than a recycle symbol; this version draws the
    arc body first so the loop actually reads as a loop."""
    c.saveState()
    r = size * 0.4
    c.setStrokeColor(HexColor(color))
    c.setLineWidth(size * 0.11)
    c.setLineCap(1)
    for i in range(3):
        c.arc(cx - r, cy - r, cx + r, cy + r, i * 120 + 12, i * 120 + 88)
    c.setFillColor(HexColor(color))
    for i in range(3):
        ang = math.radians(i * 120 + 88)
        tip_x = cx + r * math.cos(ang)
        tip_y = cy + r * math.sin(ang)
        perp = ang + math.pi / 2
        wing = size * 0.15
        back_x = tip_x - wing * 1.3 * math.cos(ang)
        back_y = tip_y - wing * 1.3 * math.sin(ang)
        p = c.beginPath()
        p.moveTo(tip_x + wing * math.cos(perp), tip_y + wing * math.sin(perp))
        p.lineTo(tip_x - wing * math.cos(perp), tip_y - wing * math.sin(perp))
        p.lineTo(back_x, back_y)
        p.close()
        c.drawPath(p, fill=1, stroke=0)
    c.restoreState()


def draw_house_icon(c, cx, cy, size, color):
    c.saveState()
    c.setFillColor(HexColor(color))
    w, h = size * 0.8, size * 0.5
    c.rect(cx - w / 2, cy - h / 2, w, h, fill=1, stroke=0)
    p = c.beginPath()
    p.moveTo(cx - w / 2 - size * 0.08, cy + h / 2)
    p.lineTo(cx, cy + h / 2 + size * 0.32)
    p.lineTo(cx + w / 2 + size * 0.08, cy + h / 2)
    p.close()
    c.drawPath(p, fill=1, stroke=0)
    c.setFillColor(white)
    dw, dh = w * 0.22, h * 0.55
    c.rect(cx - dw / 2, cy - h / 2, dw, dh, fill=1, stroke=0)
    c.restoreState()


def draw_paw_icon(c, cx, cy, size, color):
    c.saveState()
    c.setFillColor(HexColor(color))
    c.ellipse(cx - size * 0.22, cy - size * 0.20, cx + size * 0.22, cy + size * 0.12, fill=1, stroke=0)
    toe_r = size * 0.10
    for dx, dy in [(-0.22, 0.22), (-0.08, 0.30), (0.08, 0.30), (0.22, 0.22)]:
        c.ellipse(cx + dx * size - toe_r, cy + dy * size - toe_r,
                   cx + dx * size + toe_r, cy + dy * size + toe_r, fill=1, stroke=0)
    c.restoreState()


def draw_postmark_icon(c, cx, cy, size, color):
    c.saveState()
    r = size * 0.42
    c.setStrokeColor(HexColor(color))
    c.setDash(1, 2)
    c.setLineWidth(1)
    c.circle(cx, cy, r, stroke=1, fill=0)
    c.setDash()
    # radiating ticks — this is what actually makes it read as a postmark
    # stamp rather than an empty dashed circle
    c.setLineWidth(0.7)
    for i in range(16):
        ang = math.radians(i * 22.5)
        x1 = cx + (r + 0.6 * mm) * math.cos(ang)
        y1 = cy + (r + 0.6 * mm) * math.sin(ang)
        x2 = cx + (r + 2.2 * mm) * math.cos(ang)
        y2 = cy + (r + 2.2 * mm) * math.sin(ang)
        c.line(x1, y1, x2, y2)
    c.restoreState()


def draw_corner_ornament(c, cx, cy, size, color, rot=0):
    """Base shape (rot=0) is oriented for the TOP-LEFT corner: the arc
    curls from the top edge to the left edge, bulging toward the outer
    corner (up-left) — i.e. it hugs the corner from the inside, rather
    than swinging out past the border. Previous version swept 0-90 deg,
    which bulged the wrong way (toward up-right) and, combined with too
    little clearance from the border, poked outside it — see chat photo."""
    c.saveState()
    c.translate(cx, cy)
    c.rotate(rot)
    c.setStrokeColor(HexColor(color))
    c.setLineWidth(0.8)
    c.arc(-size, -size, size, size, 90, 180)
    c.setFillColor(HexColor(color))
    c.circle(-size * 0.5, size * 0.5, size * 0.09, fill=1, stroke=0)
    c.restoreState()

# ---------------------------------------------------------------------------
# 10 style presets
# ---------------------------------------------------------------------------

def _style_classic(c, ox, oy, order):
    """1. Classic serif + border — the dominant 'safe' seller (matches
    EDSG, 4.8*/5,046 reviews, and most top Etsy listings)."""
    accent = _resolve_accent(order.get("accent", "charcoal"))
    cx = ox + CARD_W / 2
    c.setFillColor(HexColor(INK))
    c.setFont("Times-Bold", 58)
    c.drawCentredString(cx, oy + CARD_H * 0.58, order["house_number"])
    fy = oy + CARD_H * 0.49
    c.setStrokeColor(HexColor(accent))
    c.setLineWidth(0.8)
    c.line(cx - 16 * mm, fy, cx - 4 * mm, fy)
    c.line(cx + 4 * mm, fy, cx + 16 * mm, fy)
    c.circle(cx, fy, 1.1 * mm, stroke=1, fill=0)
    c.setFont("Times-Roman", 17)
    c.drawCentredString(cx, oy + CARD_H * 0.36, order["street_name"])
    _draw_border(c, ox, oy, order, "double")


def _style_minimal(c, ox, oy, order):
    """2. Modern minimalist sans — the 'modern-font' niche shop angle."""
    accent = _resolve_accent(order.get("accent", "charcoal"))
    cx = ox + CARD_W / 2
    c.setFillColor(HexColor(INK))
    c.setFont("Helvetica-Bold", 62)
    c.drawCentredString(cx, oy + CARD_H * 0.56, order["house_number"])
    c.setFont("Helvetica", 18)
    c.setFillColor(HexColor(INK_MUTED))
    c.drawCentredString(cx, oy + CARD_H * 0.32, order["street_name"].upper())
    # subtle anchor line — fixes the empty-lower-half balance issue the
    # original version had versus the other 9 designs
    c.setStrokeColor(HexColor(accent))
    c.setLineWidth(0.6)
    c.line(cx - 14 * mm, oy + CARD_H * 0.24, cx + 14 * mm, oy + CARD_H * 0.24)
    _draw_border(c, ox, oy, order, "single")


def _style_floral(c, ox, oy, order):
    """3. Floral corner accent — floral/foliage is a consistently
    popular category (wheeliebinnumbers.net lists it as one of their
    most popular collections)."""
    accent = _resolve_accent(order.get("accent", "terracotta"))
    cx = ox + CARD_W / 2
    _draw_icon(c, cx, oy + CARD_H - PAD - 11 * mm, 15 * mm, accent, "floral", draw_flower_icon)
    c.setFillColor(HexColor(INK))
    c.setFont("Times-Bold", 52)
    c.drawCentredString(cx, oy + CARD_H * 0.48, order["house_number"])
    c.setFont("Times-Italic", 16)
    c.drawCentredString(cx, oy + CARD_H * 0.30, order["street_name"])
    _draw_border(c, ox, oy, order, "single")


def _style_recycle(c, ox, oy, order):
    """4. Recycling-icon informational — icons + friendly text reinforcing
    recycling rules; also ties to the Growth Plan's recycling/food-caddy
    bundle idea."""
    accent = _resolve_accent(order.get("accent", "forest"))
    cx = ox + CARD_W / 2
    _draw_icon(c, cx, oy + CARD_H - PAD - 11 * mm, 14 * mm, accent, "recycle", draw_recycle_icon)
    c.setFillColor(HexColor(INK))
    c.setFont("Helvetica-Bold", 54)
    c.drawCentredString(cx, oy + CARD_H * 0.48, order["house_number"])
    c.setFont("Helvetica", 16)
    c.drawCentredString(cx, oy + CARD_H * 0.30, order["street_name"])
    if order.get("bin_type"):
        c.setFont("Helvetica-Bold", 10)
        c.setFillColor(HexColor(accent))
        c.drawCentredString(cx, oy + CARD_H * 0.24, order["bin_type"].upper())
    _draw_border(c, ox, oy, order, "single")


def _style_house(c, ox, oy, order):
    """5. House silhouette — contemporary, elegant, versatile across
    house styles per current design-trend coverage."""
    accent = _resolve_accent(order.get("accent", "navy"))
    cx = ox + CARD_W / 2
    _draw_icon(c, cx, oy + CARD_H - PAD - 12 * mm, 14 * mm, accent, "house", draw_house_icon)
    c.setFillColor(HexColor(INK))
    c.setFont("Helvetica-Bold", 54)
    c.drawCentredString(cx, oy + CARD_H * 0.48, order["house_number"])
    c.setFont("Helvetica", 16)
    c.drawCentredString(cx, oy + CARD_H * 0.30, order["street_name"])
    _draw_border(c, ox, oy, order, "single")


def _style_reverse_block(c, ox, oy, order):
    """6. Bold reverse-block (white-on-colour) — high-contrast styling in
    the spirit of the reflective/high-visibility category."""
    accent = _resolve_accent(order.get("accent", "navy"))
    cx = ox + CARD_W / 2
    inset = PAD + 1.3 * mm
    c.setFillColor(HexColor(accent))
    c.rect(ox + inset, oy + inset, CARD_W - 2 * inset, CARD_H - 2 * inset, fill=1, stroke=0)
    c.setFillColor(white)
    c.setFont("Helvetica-Bold", 62)
    c.drawCentredString(cx, oy + CARD_H * 0.56, order["house_number"])
    c.setFont("Helvetica", 17)
    c.drawCentredString(cx, oy + CARD_H * 0.32, order["street_name"].upper())
    _draw_border(c, ox, oy, order, "single")


def _style_split_panel(c, ox, oy, order):
    """7. Split panel — colour band with the number, white lower half
    with the street name. A layout differentiator, easy to spot from a
    car/from a distance."""
    accent = _resolve_accent(order.get("accent", "berry"))
    cx = ox + CARD_W / 2
    inset = PAD + 1.3 * mm
    band_h = (CARD_H - 2 * inset) * 0.5
    c.setFillColor(HexColor(accent))
    c.rect(ox + inset, oy + CARD_H - inset - band_h, CARD_W - 2 * inset, band_h, fill=1, stroke=0)
    c.setFillColor(white)
    c.setFont("Helvetica-Bold", 56)
    c.drawCentredString(cx, oy + CARD_H - inset - band_h * 0.62, order["house_number"])
    c.setFillColor(HexColor(INK))
    c.setFont("Times-Roman", 17)
    c.drawCentredString(cx, oy + CARD_H * 0.28, order["street_name"])
    _draw_border(c, ox, oy, order, "single")


def _style_vintage(c, ox, oy, order):
    """8. Vintage dashed-border / postmark — a visual-style gap versus
    the single/double solid borders every competitor uses."""
    accent = _resolve_accent(order.get("accent", "mustard"))
    cx = ox + CARD_W / 2
    c.setFillColor(HexColor(INK))
    c.setFont("Times-Roman", 54)
    c.drawCentredString(cx, oy + CARD_H * 0.55, order["house_number"])
    c.setFont("Times-Italic", 15)
    c.setFillColor(HexColor(INK_MUTED))
    c.drawCentredString(cx, oy + CARD_H * 0.34, order["street_name"])
    _draw_icon(c, cx, oy + CARD_H - PAD - 10 * mm, 18 * mm, accent, "vintage", draw_postmark_icon)
    _draw_border(c, ox, oy, order, "dashed")


def _style_corner_flourish(c, ox, oy, order):
    """9. Four-corner flourish — vector-only take on the floral-wreath +
    traditional-font look called out in current design trend coverage,
    without using photographic floral art."""
    accent = _resolve_accent(order.get("accent", "berry"))
    cx = ox + CARD_W / 2
    inset = PAD + 7 * mm
    corner_size = 4.5 * mm
    for (px, py, rot) in [
        (ox + inset, oy + CARD_H - inset, 0),
        (ox + CARD_W - inset, oy + CARD_H - inset, -90),
        (ox + inset, oy + inset, 90),
        (ox + CARD_W - inset, oy + inset, 180),
    ]:
        _draw_icon_rotated(c, px, py, corner_size, accent, "corner_flourish", draw_corner_ornament, rot)
    c.setFillColor(HexColor(INK))
    c.setFont("Times-Bold", 54)
    c.drawCentredString(cx, oy + CARD_H * 0.55, order["house_number"])
    c.setFont("Times-Roman", 16)
    c.drawCentredString(cx, oy + CARD_H * 0.32, order["street_name"])
    _draw_border(c, ox, oy, order, "double")


def _style_paw(c, ox, oy, order):
    """10. Paw-print accent — pet designs (dog breeds, cat silhouettes)
    are called out as surprisingly popular across households; a cat-
    silhouette listing is named directly among Etsy's bestsellers."""
    accent = _resolve_accent(order.get("accent", "terracotta"))
    cx = ox + CARD_W / 2
    _draw_icon(c, cx, oy + CARD_H - PAD - 12 * mm, 19 * mm, accent, "paw", draw_paw_icon)
    c.setFillColor(HexColor(INK))
    c.setFont("Helvetica-Bold", 54)
    c.drawCentredString(cx, oy + CARD_H * 0.48, order["house_number"])
    c.setFont("Helvetica-Oblique", 16)
    c.drawCentredString(cx, oy + CARD_H * 0.30, order["street_name"])
    _draw_border(c, ox, oy, order, "single")


def _fit_font_size(text, font, max_size, min_size, max_width, step=0.5):
    """Shrinks from max_size until `text` fits within max_width (points),
    or hits min_size. Used so P02's number/street text auto-fit their
    hollows in the icon regardless of how long the actual order text is."""
    size = max_size
    while size > min_size and stringWidth(text, font, size) > max_width:
        size -= step
    return round(size, 1)


# ---------------------------------------------------------------------------
# P02 — house + flowers + banner illustrated icon. REGENERATED (v2, bolder
# linework) from a new source image that already had example text ("36" /
# "GROVE STREET") baked into the artwork. Rather than eyeballing, the
# original text was found via connected-component analysis of the ink
# (house/flowers/banner outline vs. the individual digit/letter shapes),
# the text glyphs were erased back to transparent (recreating a hollow
# silhouette from what was originally a finished mockup), and the number/
# street placement below is the *actual measured position* the artist
# used — not a re-derived hollow centroid like v1. The banner curve is
# still a fitted quadratic (residual std <1px) to the ribbon's own
# top/bottom edge, same method as before. Full derivation in chat
# history. These numbers are specific to the current
# house_banner_master.png (1359x935px); replacing that file again means
# re-deriving all of this again, not reusing it.
#
# PAD-DEPENDENT: unlike styles 1-10 (whose icon/border positions are
# written directly in terms of PAD, so they move automatically), these
# constants were rescaled by hand to match PAD=2mm -- if PAD changes
# again, these need rescaling again too, not just the border. See
# bin_sticker_README.md's "Changing PAD for hollow-icon styles" section
# for the general recipe (rescale from the ORIGINAL pixel measurements,
# don't nudge these mm values directly).
# ---------------------------------------------------------------------------
P02_CARD_W = 140 * mm  # landscape -- this style's card is a different shape/size
P02_CARD_H = 100 * mm  # than the other 10 styles' portrait CARD_W/CARD_H

P02_ICON = dict(x=8.8 * mm, y=7.9391 * mm, w=122.4 * mm, h=84.1219 * mm)
P02_ICON_SCALE = 0.090066  # mm per source-icon px -- rescaled for the landscape card, see chat history
P02_ICON_X_LEFT = 8.8 * mm
P02_ICON_Y_TOP = 7.9391 * mm  # icon's top edge, measured from the card's top edge

P02_NUMBER_CENTER_Y = 59.7317 * mm  # RL y
P02_NUMBER_MAX_WIDTH = 36.8821 * 0.90 * mm  # house interior gap width, 10% safety margin

P02_STREET_CENTER_Y = 35.2426 * mm  # RL y
P02_STREET_MAX_WIDTH = 86.4636 * 0.90 * mm  # banner usable-band width, 10% safety margin

# Quadratic fit (least-squares, residual std <1px) to the banner's own
# top/bottom midline, sampled column-by-column from the source PNG, over
# just the clean central band (excludes the folded tail ends, which
# aren't a smooth parabola) -- this is what the street text curves
# along, not an eyeballed arc.
P02_BANNER_CURVE_COEFFS = (3.36374116e-04, -4.56481049e-01, 7.64104309e+02)




def _p02_banner_mid_px(x_px):
    a, b, cc = P02_BANNER_CURVE_COEFFS
    return a * x_px * x_px + b * x_px + cc


def _draw_curved_text(c, text, cx, baseline, font, size, color,
                       icon_x_left, icon_scale, curve_fn):
    """Draws `text` centred on cx, following curve_fn (an icon-local-px
    -> icon-local-px function). `baseline` is where the centre character
    sits; curvature is a per-character offset relative to that, so this
    degrades gracefully to flat text if curve_fn is ~constant."""
    total_w = stringWidth(text, font, size)
    x_cursor = cx - total_w / 2
    x_img_at_cx = (cx - icon_x_left) / (icon_scale * mm)
    ref_mid_px = curve_fn(x_img_at_cx)
    a, b, _ = P02_BANNER_CURVE_COEFFS

    c.setFillColor(HexColor(color))
    c.setFont(font, size)
    for ch in text:
        cw = stringWidth(ch, font, size)
        x_char = x_cursor + cw / 2
        x_img = (x_char - icon_x_left) / (icon_scale * mm)
        mid_px = curve_fn(x_img)
        delta_y = -(mid_px - ref_mid_px) * icon_scale * mm  # image y-down -> reportlab y-up
        slope_img = 2 * a * x_img + b
        angle_deg = -math.degrees(math.atan(slope_img))
        c.saveState()
        c.translate(x_char, baseline + delta_y)
        c.rotate(angle_deg)
        c.drawCentredString(0, 0, ch)
        c.restoreState()
        x_cursor += cw


def _style_p02_house_banner(c, ox, oy, order):
    """11. Illustrated house + flowers + banner — real Midjourney-sourced
    artwork (not a plain-shape vector like style 5's house silhouette),
    with the house number nested inside the house body and the street
    name curved along the banner ribbon, matching the source art's own
    shape. LANDSCAPE (140x100mm) -- the only style with a different card
    shape than the rest; see STYLE_CARD_SIZE and P02_CARD_W/H. See chat
    history for the full derivation."""
    accent_key = order.get("accent", "navy")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _p02_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P02_ICON["x"], oy + P02_ICON["y"], P02_ICON["w"], P02_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- plain vector
        # house, flat (uncurved) text. NOT a lesser version of the same
        # design -- there's no vector equivalent of "number nested in a
        # hollow illustrated house with a curved banner," so this is
        # really a substitution, not a degradation. Warn loudly so it's
        # never discovered only after looking at the printed output.
        print(
            f"\u26a0 house_banner: master icon not found at "
            f"{_asset_path(P02_ICON_MASTER)!r} -- rendering plain house "
            f"fallback instead of the illustrated P02 design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 10 * mm, 12 * mm, accent_hex, "house", draw_house_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Helvetica-Bold", 44)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.45, order["house_number"])
        c.setFont("Helvetica", 14)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.25, order["street_name"])
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold", 44, 20, P02_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Helvetica-Bold", number_size)
    number_baseline = P02_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Helvetica-Bold", 19, 8, P02_STREET_MAX_WIDTH)
    asc, desc = getAscentDescent("Helvetica-Bold", street_size)
    street_baseline = P02_STREET_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    _draw_curved_text(
        c, street_text, cx, oy + street_baseline, "Helvetica-Bold", street_size, accent_hex,
        ox + P02_ICON_X_LEFT, P02_ICON_SCALE, _p02_banner_mid_px,
    )

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)


STYLES = {
    "classic": _style_classic,
    "minimal": _style_minimal,
    "floral": _style_floral,
    "recycle": _style_recycle,
    "house": _style_house,
    "reverse_block": _style_reverse_block,
    "split_panel": _style_split_panel,
    "vintage": _style_vintage,
    "corner_flourish": _style_corner_flourish,
    "paw": _style_paw,
    "house_banner": _style_p02_house_banner,
}

STYLE_LABELS = {
    "classic": "1. Classic serif + border",
    "minimal": "2. Modern minimalist sans",
    "floral": "3. Floral corner accent",
    "recycle": "4. Recycling-icon informational",
    "house": "5. House silhouette",
    "reverse_block": "6. Bold reverse-block",
    "split_panel": "7. Split panel",
    "vintage": "8. Vintage dashed/postmark",
    "corner_flourish": "9. Four-corner flourish",
    "paw": "10. Paw print accent",
    "house_banner": "11. House + banner illustrated (landscape)",
}

# Card size per style. Every style defaults to the shared portrait
# (CARD_W, CARD_H) EXCEPT house_banner, which is landscape
# (P02_CARD_W, P02_CARD_H) -- the first and so far only style with a
# different card shape. draw_sticker/render_sheet/render_gallery all
# look up a style's real size here rather than assuming CARD_W/CARD_H
# uniformly -- if you add another non-portrait style in future, register
# its size here too, or it will silently get drawn onto a portrait base.
STYLE_CARD_SIZE = {style: (CARD_W, CARD_H) for style in STYLES}
STYLE_CARD_SIZE["house_banner"] = (P02_CARD_W, P02_CARD_H)


def draw_sticker(c, ox, oy, order):
    """
    order = dict:
        house_number str
        street_name  str
        style        key in STYLES              (default "minimal")
        accent       key in ACCENTS              (each style has its own default)
        bin_type     str or None                 (style "recycle" only)
    """
    style = order.get("style", "minimal")
    w, h = STYLE_CARD_SIZE.get(style, (CARD_W, CARD_H))
    _draw_base(c, ox, oy, w, h)
    STYLES[style](c, ox, oy, order)


def _sheet_layout(card_w, card_h, page_w, page_h):
    margin_x = (page_w - 2 * card_w) / 2
    margin_y = (page_h - 2 * card_h) / 2
    return margin_x, margin_y, [
        (margin_x, margin_y + card_h), (margin_x + card_w, margin_y + card_h),
        (margin_x, margin_y), (margin_x + card_w, margin_y),
    ]


def render_sheet(orders, out_path, caption=False):
    """orders: list of up to 4 dicts. Fills one A4 sheet, 2x2. Set
    caption=True to print each design's label in the bottom margin
    (used for the design gallery, not for real customer orders).

    All orders in one call must use styles with the SAME card size --
    this fills one uniform 2x2 grid, so a batch mixing e.g. a portrait
    style with landscape house_banner can't be laid out sensibly in one
    call. Split into separate render_sheet calls per card shape instead;
    mixing raises ValueError rather than silently producing a wrong
    layout (a card drawn at the wrong size/position on a shared grid)."""
    sizes = {STYLE_CARD_SIZE.get(o.get("style", "minimal"), (CARD_W, CARD_H)) for o in orders}
    if len(sizes) > 1:
        raise ValueError(
            f"render_sheet got orders with different card sizes ({sizes}) -- "
            "a single sheet can't mix card shapes in one uniform 2x2 grid. "
            "Split into separate render_sheet calls, one per card shape."
        )
    card_w, card_h = sizes.pop() if sizes else (CARD_W, CARD_H)
    page_size = landscape(A4) if card_w > card_h else A4
    page_w, page_h = page_size
    margin_x, margin_y, positions = _sheet_layout(card_w, card_h, page_w, page_h)

    c = canvas.Canvas(out_path, pagesize=page_size)
    for order, (x, y) in zip(orders, positions):
        draw_sticker(c, x, y, order)
        if caption and "style" in order:
            c.setFont("Helvetica", 6)
            c.setFillColor(HexColor("#888888"))
            c.drawCentredString(x + card_w / 2, 2.5 * mm, STYLE_LABELS.get(order["style"], order["style"]))
    c.showPage()
    c.save()


def render_gallery(style_keys, sample_order, out_path):
    """One sticker per style in style_keys, same sample_order text on all
    of them, paginated 4-up across as many A4 pages as needed.

    Styles are grouped by card shape (portrait vs. landscape) and each
    group gets its own page(s) in the matching orientation -- a page
    can't sensibly mix a 100x140mm portrait card with a 140x100mm
    landscape one in one uniform grid, same reasoning as render_sheet."""
    groups = {}
    for style in style_keys:
        size = STYLE_CARD_SIZE.get(style, (CARD_W, CARD_H))
        groups.setdefault(size, []).append(style)

    c = canvas.Canvas(out_path, pagesize=A4)  # placeholder; first setPageSize call below fixes it
    first_page = True
    for (card_w, card_h), styles_in_group in groups.items():
        page_size = landscape(A4) if card_w > card_h else A4
        page_w, page_h = page_size
        margin_x, margin_y, positions = _sheet_layout(card_w, card_h, page_w, page_h)
        for i, style in enumerate(styles_in_group):
            slot = i % 4
            if not first_page and slot == 0:
                c.showPage()
            first_page = False
            c.setPageSize(page_size)
            x, y = positions[slot]
            order = dict(sample_order)
            order["style"] = style
            draw_sticker(c, x, y, order)
            c.setFont("Helvetica", 6)
            c.setFillColor(HexColor("#888888"))
            c.drawCentredString(x + card_w / 2, 2.5 * mm, STYLE_LABELS.get(style, style))
        c.showPage()
        first_page = True  # next group starts a fresh page regardless
    c.save()


if __name__ == "__main__":
    sample_order = {"house_number": "28", "street_name": "North Avenue"}
    all_styles = list(STYLES.keys())
    render_gallery(all_styles, sample_order, "/mnt/user-data/outputs/bin_sticker_design_gallery.pdf")
    print("done")
