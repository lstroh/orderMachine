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


def _draw_border(c, ox, oy, order, weight="single", w=CARD_W, h=CARD_H, pad=None):
    """pad overrides the global PAD cutting-tolerance inset for this call
    only -- used when one style needs a different cut-to-border margin
    than the rest (e.g. D04's P27_PAD), without touching the shared PAD
    constant every other style still relies on. Defaults to the global
    PAD if not given."""
    if pad is None:
        pad = PAD
    accent = HexColor(_resolve_accent(order.get("accent", "charcoal")))
    c.setStrokeColor(accent)
    c.setLineWidth(1.1)
    c.setDash()
    c.rect(ox + pad, oy + pad, w - 2 * pad, h - 2 * pad, fill=0, stroke=1)
    if weight == "double":
        c.setLineWidth(0.5)
        inset = pad + 2.2 * mm
        c.rect(ox + inset, oy + inset, w - 2 * inset, h - 2 * inset, fill=0, stroke=1)
    elif weight == "dashed":
        c.setDash(2, 2)
        c.setLineWidth(0.7)
        inset = pad + 2.0 * mm
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


def draw_center_flourish(c, cx, y, icon_rel_path, width, color=INK):
    """Draws a flourish as a real image asset (extracted from the
    reference PNG via icon-silhouette-extraction, then recoloured/cached
    exactly like the P02 house icon -- see recolour_silhouette /
    _p02_icon_path above for the pattern this follows), rather than a
    hand-drawn or hand-traced vector approximation. width is the desired
    real-world width in points/mm; height is derived from the source
    PNG's own aspect ratio so it isn't stretched."""
    master = _asset_path(icon_rel_path)
    if not os.path.exists(master):
        return  # asset missing -- draw nothing rather than a wrong placeholder
    name = os.path.splitext(os.path.basename(icon_rel_path))[0]
    coloured_path = _asset_path(f"assets/icons/{name}_{color.lstrip('#')}.png")
    if not os.path.exists(coloured_path):
        recolour_silhouette(master, coloured_path, color)
    img = ImageReader(coloured_path)
    iw, ih = img.getSize()
    height = width * ih / iw
    c.drawImage(img, cx - width / 2, y - height / 2, width=width, height=height, mask="auto")


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

# ---------------------------------------------------------------------------
# P25 landscape flourish -- constants measured from the Midjourney reference
# PNG (chat "Image 1", 1312x928px, uploaded as
# u3898592314_..._2.png), by pixel row/column band detection, not eyeballed.
# Reuses P02_CARD_W/H (140x100mm landscape) -- see that style's comment for
# why a second card shape needs registering in STYLE_CARD_SIZE.
#
# IMPORTANT: P25 in the gallery/idea board has fits_spec=Yes for the
# standard 100x140mm PORTRAIT card. This style is the landscape mockup
# variant explored in chat instead (flagged as a deliberate departure at
# the time) -- treat this as its own exploratory style, not "P25 built
# to spec", unless a landscape product line is adopted.
# ---------------------------------------------------------------------------
_P25_SRC_H_PX = 928  # source PNG height in px, used as the frac-from-bottom basis

def _p25_frac(row_px):
    """Convert a measured source-image row (0 = top) to a fraction of
    P02_CARD_H measured from the card's bottom -- same convention as the
    oy + CARD_H * frac pattern used by every other style in this file."""
    return 1 - row_px / _P25_SRC_H_PX

P25_NUMBER_BASELINE_FRAC = _p25_frac(367)     # bottom of the "36" glyphs
P25_FLOURISH1_Y_FRAC = _p25_frac(440)         # line between number and street
P25_STREET_BASELINE_FRAC = _p25_frac(602)     # bottom of "GROVE STREET"
P25_FLOURISH2_Y_FRAC = _p25_frac(660)         # line below street name

P25_NUMBER_SIZE = 90          # pt, Times-Bold -- matches measured ~21.2mm cap height
P25_STREET_MAX_WIDTH = 122 * mm  # measured text band was ~116mm; small safety margin
P25_FLOURISH_HALF_WIDTH = 58 * mm  # measured flourish/street band was ~116mm wide
P25_BORDER_WEIGHT = 4.5       # pt -- "solid thick" per user request, vs. 1.1pt elsewhere
P25_BORDER_RADIUS = 5 * mm    # measured corner rounding

# Flourish assets -- unlike the vector icons above, these are extracted
# directly from the reference PNG's own ink (icon-silhouette-extraction
# skill: luminance-separated, connected-component-cleaned, cropped to
# content), not hand-drawn or hand-traced polygons. That approach was
# tried first and rejected (chat) -- the double lines and scroll are one
# continuous fused stroke in the source art with no gap to cut a vector
# path along, so hand-approximating it as separate polygons kept coming
# out visually wrong. Using the real extracted pixels sidesteps that.
# The two are genuinely different shapes (each extracted independently),
# not a mirror of one another -- see chat.
P25_FLOURISH1_ICON = "assets/icons/p25_flourish1.png"  # aspect 22.23:1 (w:h)
P25_FLOURISH2_ICON = "assets/icons/p25_flourish2.png"  # aspect 21.74:1 (w:h)
P25_FLOURISH_WIDTH = 116 * mm  # measured span of the line+swirl assembly

# ---------------------------------------------------------------------------
# P25b -- second Midjourney render (chat "Image 2": double-line border with
# corner caps, single flourish between number and street, not above+below).
# Same extraction method as P25 (icon-silhouette-extraction skill), applied
# with the crop-clipping check from the start this time -- see chat.
#
# Corner caps NOT extracted/replicated yet -- that's a separate ornament
# from the flourish (attached to the border, not a finite self-contained
# shape the same way), and doing it justice needs its own crop/extraction
# pass. Border here uses the existing plain double-line style as a
# placeholder; swap in a real corner-cap asset later if wanted.
# ---------------------------------------------------------------------------
P25B_FLOURISH_ICON = "assets/icons/p25b_flourish.png"  # aspect 18.22:1 (w:h)
P25B_FLOURISH_WIDTH = 123 * mm  # measured span of the line+swirl assembly

P25B_NUMBER_BASELINE_FRAC = _p25_frac(380)   # bottom of the "36" glyphs
P25B_FLOURISH_Y_FRAC = _p25_frac(470)        # the flourish's own line row
P25B_STREET_BASELINE_FRAC = _p25_frac(660)   # bottom of "GROVE STREET"

# Corner-cap ornament -- extracted the same way as the flourishes (each
# corner cropped generously, icon-silhouette-extraction, checked against
# check_crop_clipping), BUT this time each of the 4 corners was cropped
# and extracted INDEPENDENTLY from its own actual pixels, not derived by
# rotating one master copy. A direct pixel comparison (chat) showed the
# source art is NOT symmetric under 90-degree rotation -- 3 of the 4
# corners differed from a rotated top-left by 8-18% of their pixels --
# so the first version's rotated copies were visibly wrong for most
# corners. Same reasoning applies to the straight-line border spec below:
# measured per edge (top/bottom/left/right independently), not assumed
# uniform, since the source's insets differ edge to edge by up to ~1.7mm
# (top and bottom in particular).
P25B_CORNER_TL = "assets/icons/p25b_corner_tl.png"
P25B_CORNER_TR = "assets/icons/p25b_corner_tr.png"
P25B_CORNER_BR = "assets/icons/p25b_corner_br.png"
P25B_CORNER_BL = "assets/icons/p25b_corner_bl.png"

# Axis-correct px/mm -- the source PNG maps 1312x928px to a 140x100mm
# landscape card, and those two ratios are NOT quite identical
# (9.3714 vs 9.28 px/mm), so row-based (vertical) measurements and
# column-based (horizontal) measurements need their own conversion
# factor, not one shared constant, or the two axes end up ~1% off from
# each other -- small on its own, but part of what compounded into the
# corner/edge misalignment (see chat).
P25B_PXMM_X = 1312 / 140.0
P25B_PXMM_Y = 928 / 100.0
P25B_CORNER_W = 165 / P25B_PXMM_X * mm  # corner crop was 165x165px, NOT square in real mm
P25B_CORNER_H = 165 / P25B_PXMM_Y * mm

# Per-edge straight double-line spec: (outer_inset_mm, outer_width_mm,
# inner_inset_mm, inner_width_mm), insets measured from the true card
# edge. top/bottom use P25B_PXMM_Y (row-based), left/right use
# P25B_PXMM_X (column-based) -- see chat for the raw pixel measurements.
P25B_EDGE_SPEC = {
    "top":    (5.603, 2.371, 8.944, 0.970),
    "bottom": (7.328, 2.371, 10.668, 0.970),
    "left":   (5.015, 2.454, 8.430, 0.960),
    "right":  (5.229, 2.454, 8.643, 0.960),
}


def draw_corner_bracket(c, ox, oy, w, h, color=INK):
    """Places the 4 INDEPENDENTLY-extracted corner-bracket assets so
    each one's anchor pixel (the corner of its own crop, which is the
    card's true physical corner) lands exactly on the matching card
    corner -- no rotation needed, since each was cropped directly from
    its own true corner in the source (see P25B_CORNER_* comment above
    for why rotating one copy was tried first and rejected).

    reportlab's drawImage places a PIL image's row 0 (top) at the TOP of
    its target box and column 0 (left) at the box's LEFT."""
    paths = {"tl": P25B_CORNER_TL, "tr": P25B_CORNER_TR,
             "br": P25B_CORNER_BR, "bl": P25B_CORNER_BL}
    boxes = {
        "tl": (ox, oy + h - P25B_CORNER_H),
        "tr": (ox + w - P25B_CORNER_W, oy + h - P25B_CORNER_H),
        "br": (ox + w - P25B_CORNER_W, oy),
        "bl": (ox, oy),
    }
    for key, rel_path in paths.items():
        master = _asset_path(rel_path)
        if not os.path.exists(master):
            continue
        name = os.path.splitext(os.path.basename(rel_path))[0]
        coloured_path = _asset_path(f"assets/icons/{name}_{color.lstrip('#')}.png")
        if not os.path.exists(coloured_path):
            recolour_silhouette(master, coloured_path, color)
        img = ImageReader(coloured_path)
        x, y = boxes[key]
        c.drawImage(img, x, y, width=P25B_CORNER_W, height=P25B_CORNER_H, mask="auto")


def draw_p25b_border(c, ox, oy, w, h, color=INK):
    """Straight double-line segments between the 4 corner brackets, one
    per edge using that edge's OWN measured spec (P25B_EDGE_SPEC) rather
    than one shared spec -- see the constants' comment for why."""
    c.saveState()
    c.setFillColor(HexColor(color))
    to, tw, ti, tiw = P25B_EDGE_SPEC["top"]
    bo, bw, bi, biw = P25B_EDGE_SPEC["bottom"]
    lo, lw, li, liw = P25B_EDGE_SPEC["left"]
    ro, rw, ri, riw = P25B_EDGE_SPEC["right"]
    run_x = w - P25B_CORNER_W - P25B_CORNER_W
    run_y = h - P25B_CORNER_H - P25B_CORNER_H
    # top edge (outer then inner), inset downward from the top edge
    c.rect(ox + P25B_CORNER_W, oy + h - (to + tw) * mm, run_x, tw * mm, fill=1, stroke=0)
    c.rect(ox + P25B_CORNER_W, oy + h - (ti + tiw) * mm, run_x, tiw * mm, fill=1, stroke=0)
    # bottom edge, inset upward from the bottom edge
    c.rect(ox + P25B_CORNER_W, oy + bo * mm, run_x, bw * mm, fill=1, stroke=0)
    c.rect(ox + P25B_CORNER_W, oy + bi * mm, run_x, biw * mm, fill=1, stroke=0)
    # left edge, inset rightward from the left edge
    c.rect(ox + lo * mm, oy + P25B_CORNER_H, lw * mm, run_y, fill=1, stroke=0)
    c.rect(ox + li * mm, oy + P25B_CORNER_H, liw * mm, run_y, fill=1, stroke=0)
    # right edge, inset leftward from the right edge
    c.rect(ox + w - (ro + rw) * mm, oy + P25B_CORNER_H, rw * mm, run_y, fill=1, stroke=0)
    c.rect(ox + w - (ri + riw) * mm, oy + P25B_CORNER_H, riw * mm, run_y, fill=1, stroke=0)
    c.restoreState()




def _p02_banner_mid_px(x_px):
    a, b, cc = P02_BANNER_CURVE_COEFFS
    return a * x_px * x_px + b * x_px + cc


def _draw_curved_text(c, text, cx, baseline, font, size, color,
                       icon_x_left, icon_scale, curve_fn, curve_coeffs):
    """Draws `text` centred on cx, following curve_fn (an icon-local-px
    -> icon-local-px function). `baseline` is where the centre character
    sits; curvature is a per-character offset relative to that, so this
    degrades gracefully to flat text if curve_fn is ~constant.

    `curve_coeffs` (a, b, c of the same quadratic curve_fn implements) is
    used ONLY for the per-character rotation angle (the fit's slope at
    that x). Passed explicitly rather than read from a module-level
    constant -- this used to hardcode P02_BANNER_CURVE_COEFFS here
    regardless of which curve_fn was passed in, which would have made
    any second caller (e.g. P06's own wreath curve) silently rotate its
    letters to match P02's curve shape instead of its own. Bug found and
    fixed while adding the first non-P02 caller of this function."""
    total_w = stringWidth(text, font, size)
    x_cursor = cx - total_w / 2
    x_img_at_cx = (cx - icon_x_left) / (icon_scale * mm)
    ref_mid_px = curve_fn(x_img_at_cx)
    a, b, _ = curve_coeffs

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
    """11. D01 (Cottage Bloom Banner) -- Illustrated house + flowers +
    banner — real Midjourney-sourced artwork (not a plain-shape vector
    like style 5's house silhouette), with the house number nested
    inside the house body and the street name curved along the banner
    ribbon, matching the source art's own shape. LANDSCAPE (140x100mm)
    -- the only style with a different card shape than the rest; see
    STYLE_CARD_SIZE and P02_CARD_W/H. See chat history for the full
    derivation, and STYLE_PRODUCT_ID / bin_sticker_products_gallery_data.md
    for how this maps to the D01 catalogue entry."""
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
            f"WARNING: house_banner: master icon not found at "
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
        ox + P02_ICON_X_LEFT, P02_ICON_SCALE, _p02_banner_mid_px, P02_BANNER_CURVE_COEFFS,
    )

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)


def _style_p25_landscape_flourish(c, ox, oy, order):
    """12. D02 (Regency Double Flourish) -- bold serif number + wide
    street name flanked by a scroll flourish both above AND below the
    street name, inside a solid thick rounded-corner border. Derived
    from the chat's Midjourney render ("Image 1") via the P25_*
    constants above, measured from the source PNG's pixel rows/columns
    -- not redrawn from memory.

    Pure black-on-white per explicit request: no accent colour anywhere,
    unlike every other style here which takes order["accent"].

    LANDSCAPE, 140x100mm (P02_CARD_W/H) -- NOT the portrait 100x140mm
    spec that P25's fits_spec=Yes in the idea board refers to. This is
    the off-spec mockup variant, kept as the user's deliberate choice
    after being flagged; don't treat it as "P25 built to spec". Now a
    catalogued product regardless (D02) -- see STYLE_PRODUCT_ID and
    bin_sticker_products_gallery_data.md."""
    w, h = P02_CARD_W, P02_CARD_H
    cx = ox + w / 2

    c.saveState()
    c.setStrokeColor(HexColor(INK))
    c.setLineWidth(P25_BORDER_WEIGHT)
    c.roundRect(ox + PAD, oy + PAD, w - 2 * PAD, h - 2 * PAD, P25_BORDER_RADIUS, fill=0, stroke=1)
    c.restoreState()

    c.setFillColor(HexColor(INK))
    c.setFont("Times-Bold", P25_NUMBER_SIZE)
    c.drawCentredString(cx, oy + h * P25_NUMBER_BASELINE_FRAC, order["house_number"])

    draw_center_flourish(c, cx, oy + h * P25_FLOURISH1_Y_FRAC, P25_FLOURISH1_ICON, P25_FLOURISH_WIDTH)

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold", 50, 16, P25_STREET_MAX_WIDTH)
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + h * P25_STREET_BASELINE_FRAC, street_text)

    draw_center_flourish(c, cx, oy + h * P25_FLOURISH2_Y_FRAC, P25_FLOURISH2_ICON, P25_FLOURISH_WIDTH)


def _style_p25b_landscape_flourish(c, ox, oy, order):
    """13. D03 (Manor Frame Classic) -- second Midjourney render (chat
    "Image 2"): bold serif number + wide street name with a SINGLE
    scroll flourish between them (not above+below like p25_landscape_
    flourish/D02), inside a double-line border. Extracted via the same
    icon-silhouette-extraction skill as P25's flourishes, this time
    checking check_crop_clipping() from the start (the skill was
    updated after P25's first extraction silently clipped most of the
    curl detail -- see chat/skill history).

    Border is the real double-line-with-corner-bracket ornament from
    Image 2, not a placeholder -- see draw_corner_bracket() and the
    P25B_CORNER_*/P25B_*_LINE_* constants above for the extraction and
    measurement approach.

    Pure black-on-white, no accent colour, per the same request as P25.
    LANDSCAPE 140x100mm -- same off-spec-vs-idea-board caveat as
    p25_landscape_flourish applies here too. Now a catalogued product
    regardless (D03) -- see STYLE_PRODUCT_ID and
    bin_sticker_products_gallery_data.md."""
    w, h = P02_CARD_W, P02_CARD_H
    cx = ox + w / 2

    draw_corner_bracket(c, ox, oy, w, h)
    draw_p25b_border(c, ox, oy, w, h)

    c.setFillColor(HexColor(INK))
    c.setFont("Times-Bold", P25_NUMBER_SIZE)
    c.drawCentredString(cx, oy + h * P25B_NUMBER_BASELINE_FRAC, order["house_number"])

    draw_center_flourish(c, cx, oy + h * P25B_FLOURISH_Y_FRAC, P25B_FLOURISH_ICON, P25B_FLOURISH_WIDTH)

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold", 50, 16, P25_STREET_MAX_WIDTH)
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + h * P25B_STREET_BASELINE_FRAC, street_text)


# ---------------------------------------------------------------------------
# P27 -- house-outline + chimney icon with the number nested inside and the
# street name printed below. Constants measured from the Midjourney/Editor
# reference PNG (chat upload, "...fe_d9da91e8-a576-4562-8f39-8cdda32a87d3.png",
# 2624x1856px) via icon-silhouette-extraction: house+chimney kept as the
# only real component; the placeholder "36" digits and "GROVE STREET"
# letters were identified by connected-component analysis (exact match --
# 2 digit components, 11 letter components for G-R-O-V-E-S-T-R-E-E-T) and
# erased back to a hollow, matching the same "measure the erased text's
# real position" approach as P02. Their erased positions are the ACTUAL
# placement constants below, not re-derived hollow centroids.
#
# Px/mm scale: derived from the mockup's own outer border bbox, ASSUMED to
# represent the true 140x100mm card edges (border bbox 2467x1735px) --
# flag this assumption if the border position changes once this design is
# finalised. Axis-correct (px_mm_x=17.6214, px_mm_y=17.3500 -- not quite
# identical, ~1.6% apart, same small axis mismatch P25b hit) -- x and y
# measurements use their own factor, not one blended constant.
#
# check_crop_clipping() equivalent (function isn't in silhouette_utils.py
# despite being referenced in bin_sticker_README.md -- see chat): house
# component bbox has clear margin on all 4 canvas edges, not clipped.
# check_symmetry_assumption() equivalent: doesn't apply -- the icon is
# kept as its own real extracted pixels, not built by mirroring one
# measured half.
#
# BORDER STYLE: the source render came out with a double-line border
# despite the prompt asking for a single line (flagged in chat at review
# time). Deliberately using SINGLE here to match the original prompt
# intent, not what the render happened to show -- change to "double" if
# that's not the right call.
#
# LANDSCAPE 140x100mm (reuses P02_CARD_W/H) -- same off-spec-vs-idea-board
# caveat as p25_landscape_flourish/p25b_landscape_flourish: P27's actual
# idea-board entry has fits_spec=Yes for the standard 100x140mm PORTRAIT
# card. This is a landscape variant explored deliberately per user
# request, not "P27 built to spec". Catalogued as D04 -- see
# STYLE_PRODUCT_ID and bin_sticker_products_gallery_data.md.
# ---------------------------------------------------------------------------
P27_ICON_MASTER = "assets/icons/p27_house_icon.png"

# Icon placement box (x, y, w, h), same convention as P02_ICON: x/y are
# the distance from the card's LEFT and BOTTOM edges to the drawn image
# box's left/bottom edges (reportlab y-up). w/h are the box to draw the
# (already-cropped, ~3%-padded) icon PNG into.
P27_ICON = dict(x=29.1690 * mm, y=24.8991 * mm, w=81.8322 * mm, h=71.5274 * mm)

P27_NUMBER_CENTER_Y = 52.3055 * mm  # RL y, vertical centre of the nested number
P27_NUMBER_MAX_WIDTH = 42.2894 * mm  # true wall-to-wall hollow width (46.99mm), 10% safety margin
P27_NUMBER_MAX_SIZE = 140  # pt -- measured cap-height implies ~129pt at Helvetica-Bold; generous ceiling, auto-fit shrinks as needed
P27_NUMBER_MIN_SIZE = 20

P27_STREET_CENTER_Y = 13.9193 * mm  # RL y
# NOTE: unlike P27_NUMBER_MAX_WIDTH (measured against the true house-wall
# gap), this is derived from how much width the SOURCE mockup's own
# placeholder text used (107.03mm), since the street band isn't bounded
# by the icon at all (it's wider than the icon itself) and no border
# style is finalised yet to measure an independent quiet-zone against.
# Revisit once the border style (see above) is settled.
P27_STREET_MAX_WIDTH = 96.3259 * mm  # measured placeholder width, 10% safety margin
P27_STREET_MAX_SIZE = 66  # pt -- measured cap-height implies ~65pt; generous ceiling
P27_STREET_MIN_SIZE = 16

# D04-specific cutting-tolerance margin, 3mm instead of the shared 2mm
# PAD -- deliberately scoped to this style only (not a global PAD change,
# per user request). Safe to apply as a pure border-inset change with no
# icon/text rescaling needed: the icon sits 3.57-29mm clear of the card
# edges on every side already (see P27_ICON comment above), so a 1mm
# border shift has no risk of colliding with it -- unlike a change large
# enough to need the full "rescale from original pixel measurements"
# recipe in bin_sticker_README.md SS6.
P27_PAD = 3 * mm

# KNOWN LIMITATION, confirmed by test-render: the source mockup's font is
# narrower per unit of cap-height than Helvetica-Bold (this file's only
# available bold sans -- no condensed weight in reportlab's base-14 set).
# For an 11-char string like "GROVE STREET" that mismatch compounds badly:
# _fit_font_size's width constraint forces the auto-fit down to ~34.5pt to
# stay inside P27_STREET_MAX_WIDTH, well under the ~65pt the measured
# cap-height implies -- so the street name prints noticeably smaller/
# lighter than the mockup's proportions. The number is affected less
# (~107.5pt vs. ~129pt implied, digits are shorter strings). Kept the
# width-safe behaviour (same priority as every other _fit_font_size use
# in this file, e.g. P02) rather than risking overflow past the border --
# revisit with a real condensed bold TTF (registerFont) if matching the
# mockup's street-text weight matters more than the safety margin.


def _p27_icon_path(accent_key):
    """Same recolour-and-cache pattern as _p02_icon_path -- generates the
    accent-coloured icon from the master silhouette on first use, caches
    to disk. Returns None if the master art isn't present."""
    master = _asset_path(P27_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _asset_path(f"assets/icons/p27_house_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p27_landscape_house(c, ox, oy, order):
    """14. D04 (Homestead Silhouette) -- house-outline icon with a
    chimney, thick line, no fill, containing the house number nested
    inside the hollow interior, street name printed below. Real
    Midjourney-sourced artwork (not a plain-shape vector like style 5's
    house silhouette), extracted and measured via icon-silhouette-
    extraction -- see the P27_* constants above for the full derivation.

    LANDSCAPE (140x100mm) -- see P27_* constants comment for the
    off-spec-vs-idea-board caveat. Catalogued as D04 -- see
    STYLE_PRODUCT_ID and bin_sticker_products_gallery_data.md."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _p27_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P27_ICON["x"], oy + P27_ICON["y"], P27_ICON["w"], P27_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- plain vector
        # house, same reasoning as house_banner's fallback: this is a
        # substitution (there's no vector equivalent of the hollow
        # extracted outline), not a lesser version of the same design.
        print(
            f"WARNING: p27_landscape_house: master icon not found at "
            f"{_asset_path(P27_ICON_MASTER)!r} -- rendering plain house "
            f"fallback instead of the extracted P27 design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 10 * mm, 12 * mm, accent_hex, "house", draw_house_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Helvetica-Bold", 44)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.45, order["house_number"])
        c.setFont("Helvetica-Bold", 14)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.25, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=P27_PAD)
        return

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold",
                                  P27_NUMBER_MAX_SIZE, P27_NUMBER_MIN_SIZE, P27_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Helvetica-Bold", number_size)
    number_baseline = P27_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Helvetica-Bold",
                                  P27_STREET_MAX_SIZE, P27_STREET_MIN_SIZE, P27_STREET_MAX_WIDTH)
    asc, desc = getAscentDescent("Helvetica-Bold", street_size)
    street_baseline = P27_STREET_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=P27_PAD)


# ---------------------------------------------------------------------------
# P47 -- house-outline icon with the number nested inside, black-only line
# art, LANDSCAPE (140x100mm, reuses P02_CARD_W/H). Numbers-only design (no
# street-name field) -- gallery entry P47 is set="Numbers", not a house-
# number+street combo like P02/P27.
#
# Source: Midjourney "Image 1" from the P47 mockup-prompt run (black-only,
# flat/no-perspective variant). That source had a genuine ~2.618deg tilt on
# the house's base wall -- NOT the ~0.44deg first estimated by averaging
# the bottom-edge slope over the full icon width, which was distorted by
# including the rounded corners' curvature in the fit. The real tilt was
# only found by isolating the straight base-wall segment (away from both
# corners) and re-fitting; residual after that fit was <0.6px, i.e. clean.
# De-rotated (-2.618deg, bicubic, transparent fill) and re-cropped BEFORE
# extraction/measurement -- every constant below is measured from that
# already-leveled asset, not the original tilted mockup.
#
# Icon-silhouette-extraction: 3 connected components in the source (house
# outline + the two placeholder digits '3'/'6' sitting side-by-side inside
# it). Kept the house outline, erased both digits -- verified the saved
# silhouette PNG has exactly 1 significant component (no leftover digit
# fragments). Full workflow: icon-silhouette-extraction skill.
#
# NUMBER_CENTER_Y is the erased digits' own combined centroid (component
# 2+3, in the ORIGINAL source image), carried through the same crop ->
# de-rotate -> re-crop chain applied to the image itself (via a marker-
# pixel transform, not hand-derived trig) -- this is ground truth for
# where a human/AI actually centred the placeholder text, same reasoning
# as P02/P27's number placement.
#
# NUMBER_MAX_WIDTH is the TRUE wall-to-wall clear gap between the house's
# interior walls (measure_gap at the digit-centroid row, confirmed stable
# within 1-2px across the whole body height -- the walls are near-
# vertical), not the digits' own bounding-box span. 10% safety margin
# applied, same convention as P02/P27.
#
# P47_ICON's box size is a DESIGN CHOICE (15mm side margins, vertically
# centred), not a measurement -- unlike P02/P27 whose icon boxes were
# constrained by needing room for a street-name band below. Since P47 has
# no street name, the icon can be centred on the full card. One uniform
# mm-per-px scale is used for both axes deliberately (this preserves the
# source's true aspect ratio -- it's the *opposite* of the "one blended
# scale distorts the image" mistake, which happens when x/y target
# dimensions are independently constrained and DON'T match the source
# aspect ratio; here the box's own aspect ratio IS the source's, by
# construction, so one scale is correct, not a shortcut).
#
# Sanity-checked, not yet a real test render: interior vertical clearance
# at the icon's horizontal centre is ~60mm (measure_gap, axis="col"),
# comfortably more than a 140pt Helvetica-Bold cap-height (~49.4mm) even
# before width-based auto-fit shrinks it further for longer numbers -- so
# width, not height, is expected to be the binding constraint, same as
# P02/P27. Icon sits 15mm clear of the card's left/right edges and
# ~12.38mm clear top/bottom -- run su.check_edge_clearance() on a real
# render before treating any of this as final (per the skill's step 9).
# ---------------------------------------------------------------------------
P47_ICON_MASTER = "assets/icons/p47_house_icon.png"

P47_ICON = dict(x=15.0 * mm, y=12.3798 * mm, w=110.0 * mm, h=75.2404 * mm)

P47_NUMBER_CENTER_Y = 43.0373 * mm  # RL y, from the erased digits' own centroid
P47_NUMBER_MAX_WIDTH = 69.6595 * mm  # true wall-to-wall hollow width (77.40mm), 10% safety margin
P47_NUMBER_MAX_SIZE = 140  # pt -- generous ceiling, auto-fit shrinks as needed (same as P27)
P47_NUMBER_MIN_SIZE = 20


def _p47_icon_path(accent_key):
    """Same recolour-and-cache pattern as _p02_icon_path/_p27_icon_path --
    generates the accent-coloured icon from the master silhouette on
    first use, caches to disk. Returns None if the master art isn't
    present (caller falls back to the plain vector house)."""
    master = _asset_path(P47_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _asset_path(f"assets/icons/p47_house_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p47_house(c, ox, oy, order):
    """15. P47 -- house-outline icon (black line art only, no colour
    accents in the source) with the house number nested inside the
    hollow interior. Numbers-only -- no street-name field, unlike
    P02/P27's house+banner designs. LANDSCAPE (140x100mm, reuses
    P02_CARD_W/H) per explicit request; P47's idea-board entry itself is
    fits_spec=No against the standard 100x140mm portrait card (its
    source was pinned at 140x150mm) -- this landscape build is a
    deliberate departure to match the shared 140x100mm card, same
    off-spec-vs-idea-board caveat as p25/p25b/p27's landscape variants.
    Catalogued as D05 -- see STYLE_PRODUCT_ID and
    bin_sticker_products_gallery_data.md. Status there is "pending", not
    approved -- render_proof_thumbnail() measured real ink-to-card-edge
    clearance under the 3mm minimum on all 4 sides (~1.7-2.0mm), same
    border-stroke-vs-PAD issue as D01/D02 originally shipped with. This
    comes from the shared global PAD, not something specific to this
    style's own icon/number placement (those clear 14-18mm)."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _p47_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P47_ICON["x"], oy + P47_ICON["y"], P47_ICON["w"], P47_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- plain vector
        # house, same reasoning as P02/P27's fallback: there's no vector
        # equivalent of the hollow extracted outline, so this is a
        # substitution, not a lesser version of the same design.
        print(
            f"WARNING: p47_house: master icon not found at "
            f"{_asset_path(P47_ICON_MASTER)!r} -- rendering plain house "
            f"fallback instead of the extracted P47 design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "house", draw_house_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Helvetica-Bold", 60)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.42, order["house_number"])
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold",
                                  P47_NUMBER_MAX_SIZE, P47_NUMBER_MIN_SIZE, P47_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Helvetica-Bold", number_size)
    number_baseline = P47_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)


# ---------------------------------------------------------------------------
# P06 -- floral vine wreath with the house number nested inside and the
# street name curved along the wreath's own lower-inner arc. LANDSCAPE
# (140x100mm, reuses P02_CARD_W/H) -- adapted from the idea board's pinned
# 15/20/30cm CIRCULAR die-cut sizes to fit the standard card by printing
# the wreath as ink inside the rectangle rather than cutting it as a
# separate circular shape (Technique A, Idea-Board-Solutions-Reference.md).
#
# Source: Midjourney "Image 1" from the P06 mockup-prompt run (black-only,
# thin vine-and-flower line art, chosen over 3 alternatives specifically
# for being true line art rather than filled/painterly silhouettes -- see
# chat). Extracted via icon-silhouette-extraction.
#
# The source PNG had a dark vignette photo-frame border baked in around
# the actual card -- that was cropped off BEFORE ink separation (33,29)-
# (1280,906) of the 1312x928px original, leaving a clean 1247x877px card
# region with no border-line touching the crop edge. That 1247x877px
# region is what all the px/mm constants below are measured against, on
# the assumption it represents the full 140x100mm target card.
#
# separate_ink()'s automatic alpha/luminance choice did NOT work here --
# alpha was uniformly 255 (a mockup, not a real cutout) and the auto
# bimodality check found no valley because ink is a thin fraction of
# total pixels (fine line art, not a bold filled shape) -- it defaulted
# to treating the WHOLE image as ink. Used a manually-chosen luminance
# threshold (<220) instead, confirmed by checking the histogram directly
# and verifying the resulting mask cleared the crop boundary by a wide
# margin (nearest ink pixel to any edge: 119px / ~13mm).
#
# 15 connected components total: the wreath (vine + all flowers, one
# fused blob since the vine physically connects them) + one detached
# flower bud + one 22px stray speck -- all 3 kept as icon artwork; 2
# components for "36"; 10 components for "GROVE STREET" (11 letters, one
# adjacent pair fused by the tight curve). Kept={1,3,21}, both text
# fields erased back to transparent. verify() reports 2 "significant"
# components because its own min_size=200 filter drops the 22px speck
# from the printed report -- pixel-count cross-check (kept-label pixel
# sum vs. saved file's nonzero-alpha count) confirms all 3 are actually
# in the file; this is not a lost fragment.
#
# NUMBER/STREET_CENTER_Y are the erased text's own combined centroid
# (ground truth for where the mockup actually placed it), same
# convention as P02/P27/P47. MAX_WIDTH values are the TRUE minimum clear
# gap between wreath strokes, sampled every 4px across each field's full
# glyph-height range (not just the centroid row) since the wreath is a
# circular ring -- unlike P47's near-vertical house walls, the interior
# width here genuinely varies with height, so a single centroid-row
# sample would understate the real constraint. 10% safety margin applied
# on top of the true minimum, same convention as every other style.
#
# Street curve: quadratic fit to the 10 erased letters' own centroids
# (can't trace a continuous band midline here -- there's no continuous
# ink band, just discrete letter shapes). residual_std ~6.1px -- NOT a
# clean sub-1px fit like P02's banner curve; letter centroids are a
# noisier proxy than a traced continuous edge. Treat this curve as a
# reasonable starting point, not a precise fit -- worth re-deriving
# against a real test render (step 9 of icon-silhouette-extraction)
# before treating it as final.
#
# Icon box position/size is NOT a design choice like P47's -- it's the
# measured position of the extracted wreath's own crop_to_content bbox
# (720x695px incl. 3% padding) placed at the SAME scale/offset it had in
# the source card, which is why it comes out symmetric (29.86mm left
# margin vs. 140-29.86-80.83=29.31mm right; 9.35mm bottom vs.
# 100-9.35-79.25=11.40mm top -- both pairs close but not identical,
# reflecting that the wreath itself isn't perfectly centred in the
# source render either).
#
# ---------------------------------------------------------------------------
P06_ICON_MASTER = "assets/icons/p06_wreath_icon.png"

P06_ICON = dict(x=29.8637 * mm, y=9.3501 * mm, w=80.8340 * mm, h=79.2474 * mm)

P06_NUMBER_CENTER_Y = 52.0798 * mm  # RL y, erased "36" centroid
P06_NUMBER_MAX_WIDTH = 50.8581 * 0.90 * mm  # true min wreath-interior gap across digit height, 10% margin
# MAX_SIZE is a MEASURED ceiling, not a generous one -- unlike P47 (whose
# number sits alone with no other field close by, so an oversized
# ceiling only ever gets trimmed by width), P06 has the street name
# sitting close beneath the number, so leaving width as the only
# constraint let the first test render auto-fit "36" up past 100pt and
# visually collide with "GROVE STREET" below (P06_NUMBER_CENTER_Y and
# P06_STREET_CENTER_Y are only 18.5mm apart). Ceiling derived from the
# erased digits' own bbox height (22.12mm, ~62.7pt cap-height) divided
# by Times-Bold's ~0.662 cap-height-to-nominal-size ratio -> ~94.7pt,
# rounded down slightly for a small safety margin against the same
# collision recurring for a 1-digit or unusually tall house number.
P06_NUMBER_MAX_SIZE = 92  # pt -- measured from source glyph height, see comment above
P06_NUMBER_MIN_SIZE = 20

P06_STREET_CENTER_Y = 33.6152 * mm  # RL y, erased "GROVE STREET" centroid
P06_STREET_MAX_WIDTH = 36.8244 * 0.90 * mm  # true min wreath-interior gap across street-text height, 10% margin
P06_STREET_MAX_SIZE = 60  # pt -- below the ~75.7pt measured ceiling; width constraint dominates anyway
# MIN_SIZE=14 (the original value) turned out to be a BROKEN floor, not a
# style choice -- _fit_font_size's loop only decrements while size is
# still above min_size, so once it reaches the floor it stops even if
# the text is STILL wider than max_width at that size. Checked directly:
# "GROVE STREET" needs ~10pt to actually fit inside P06_STREET_MAX_WIDTH,
# and "12 CENTRAL AVENUE" needs ~8pt -- both below the old 14pt floor, so
# both were silently rendering oversized and overlapping the wreath's
# flowers (caught on a real test render with "FLAT B" / "12 Central
# Avenue", not visible with the shorter names tested earlier). Lowered
# to 8pt so auto-fit can actually reach a size that fits for realistic
# street-name lengths; very long names will render small as a result --
# that's the honest trade-off for this wreath's narrow interior, not a
# bug to hide by raising the floor back up.
P06_STREET_MIN_SIZE = 8

# Quadratic fit (icon-local px, y-down) to the 10 erased street-letter
# centroids -- (-3.34512697e-03, 2.38274801e+00, 1.08664515e+02),
# residual_std ~6.1px. NOT USED for rendering (see below) -- kept here
# only as a reference for revisiting curved text later.
#
# TRIED AND REJECTED: applying this curve via _draw_curved_text produced
# a visibly broken test render -- letters near the ends of "GROVE
# STREET" were rotated up to ~50deg and shifted up to ~12mm off their
# baseline, overlapping the house number above. This isn't just fit
# noise: the wreath is a full circle (unlike P02's shallow banner arc),
# so the true vertical rise across an 11-character span this close to
# the ring's sides is genuinely large relative to the ~18.5mm gap
# between P06_NUMBER_CENTER_Y and P06_STREET_CENTER_Y. Went with FLAT
# (straight) street text instead -- same choice P27 already made for a
# similarly round icon in this file. Revisit only with a curve re-fit
# against a real test render (icon-silhouette-extraction skill's step 9),
# not by re-applying these coefficients as-is.


def _p06_icon_path(accent_key):
    """Same recolour-and-cache pattern as _p02_icon_path/_p27_icon_path/
    _p47_icon_path -- generates the accent-coloured icon from the master
    silhouette on first use, caches to disk. Returns None if the master
    art isn't present (caller falls back to the plain vector house as a
    substitution, same reasoning as the other hollow-icon styles)."""
    master = _asset_path(P06_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _asset_path(f"assets/icons/p06_wreath_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p06_wreath(c, ox, oy, order):
    """16. P06 -- floral vine wreath, black line art, house number
    nested in the wreath's upper interior with the street name curved
    along its lower-inner arc, matching the source mockup's own layout.
    LANDSCAPE (140x100mm, reuses P02_CARD_W/H) -- P06's idea-board entry
    itself is fits_spec=No against BOTH the standard 100x140mm portrait
    card and this landscape card (it was pinned as a circular die-cut at
    15/20/30cm); this build adapts it per Technique A (printed ink inside
    a rectangle, not a physical circular cut) rather than building it to
    any of the pinned sizes. See the P06_* constants block above for the
    full extraction/derivation writeup."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _p06_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P06_ICON["x"], oy + P06_ICON["y"], P06_ICON["w"], P06_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- plain vector
        # floral icon, flat (uncurved) text. Not a lesser version of the
        # same design -- there's no vector equivalent of "number+street
        # nested in a hollow extracted wreath," so this is a
        # substitution, same reasoning as P02/P27/P47's fallbacks. Warn
        # loudly so it's never discovered only after looking at printed
        # output.
        print(
            f"WARNING: p06_wreath: master icon not found at "
            f"{_asset_path(P06_ICON_MASTER)!r} -- rendering plain floral "
            f"fallback instead of the extracted P06 wreath design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 10 * mm, 12 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 44)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 14)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.32, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P06_NUMBER_MAX_SIZE, P06_NUMBER_MIN_SIZE, P06_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", number_size)
    number_baseline = P06_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold",
                                  P06_STREET_MAX_SIZE, P06_STREET_MIN_SIZE, P06_STREET_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", street_size)
    street_baseline = P06_STREET_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)


# ---------------------------------------------------------------------------
# P06 numbers-only variant -- same extracted wreath asset as p06_wreath,
# no street-name field, number recentred to sit in the wreath's true
# geometric middle rather than the upper half. Direct response to real
# market evidence (Etsy's own "Circle Design ... House Number" bestseller
# listing, multiple shops advertising "number only" as a standard
# checkout option, and the idea board's own P30a/P30b -- "56"/"64" in a
# leaf wreath, no street) rather than a guess.
#
# NOT a free "leave street_name blank" toggle -- checked before building
# this: every style in this file except p47_house reads
# order["street_name"] unconditionally, so an empty string would leave
# an awkward gap rather than a properly recentred number. This is a
# dedicated style function for that reason, same pattern as p47_house.
#
# Placement re-measured from the SAME wreath_mask as p06_wreath (not
# reusing p06_wreath's number constants, which were deliberately
# constrained to the upper half to leave room for street text below):
# - Vertical centre = the wreath's own bbox vertical midpoint (card_crop2
#   y=447px of 877, i.e. RL y=49.03mm) -- the intuitive "dead centre"
#   placement matching how P30a/P30b's real photos are composed.
# - Max width = TRUE min interior gap sampled every 2px from y=390-508
#   (card_crop2 coords), the band actually surrounding that centre point.
#   One value in that band (577px, a flat 8px-wide plateau at y=402-416)
#   was excluded as a measurement artifact -- it breaks discontinuously
#   from the smooth 500->533 trend on either side of it, consistent with
#   the "biggest gap" scan picking up a gap BETWEEN two flower clusters
#   rather than the true vine-to-vine interior span at those rows. True
#   min after excluding it: 473px (y=476) -> 53.11mm raw, 47.80mm with
#   the usual 10% margin.
# - Vertical room is NOT the binding constraint here (62mm clear at the
#   card's own centre column, measured separately) -- width dominates,
#   same as every other hollow-wreath/house style in this file.
# ---------------------------------------------------------------------------
P06_NUM_ONLY_CENTER_Y = 49.0308 * mm  # RL y, wreath's own bbox vertical midpoint
P06_NUM_ONLY_MAX_WIDTH = 53.1067 * 0.90 * mm  # true min gap in the surrounding band, 10% margin
P06_NUM_ONLY_MAX_SIZE = 150  # pt -- generous; width is the real constraint (vertical room is ~62mm clear)
P06_NUM_ONLY_MIN_SIZE = 24


def _style_p06_wreath_numbers(c, ox, oy, order):
    """17. P06 numbers-only -- same wreath asset as p06_wreath (16), no
    street-name field, a single larger number centred in the wreath's
    true middle. See the P06_NUM_ONLY_* constants above for how the
    placement differs from p06_wreath's own (deliberately upper-half)
    number position. LANDSCAPE (140x100mm, reuses P02_CARD_W/H), same
    card shape as p06_wreath -- these two styles are meant to be offered
    as a pair (with/without street name) on the same wreath artwork, not
    as unrelated designs."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _p06_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P06_ICON["x"], oy + P06_ICON["y"], P06_ICON["w"], P06_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- same
        # reasoning as p06_wreath's fallback, just without the street
        # line.
        print(
            f"WARNING: p06_wreath_numbers: master icon not found at "
            f"{_asset_path(P06_ICON_MASTER)!r} -- rendering plain floral "
            f"fallback instead of the extracted P06 wreath design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 10 * mm, 12 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 70)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.46, order["house_number"])
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P06_NUM_ONLY_MAX_SIZE, P06_NUM_ONLY_MIN_SIZE, P06_NUM_ONLY_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", number_size)
    number_baseline = P06_NUM_ONLY_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)


# ---------------------------------------------------------------------------
# P30 -- laurel leaf wreath (open at top, two symmetrical branches meeting
# at a small stem at the bottom), numbers only, no street name. LANDSCAPE
# (140x100mm, reuses P02_CARD_W/H). Distinct asset from p06_wreath -- this
# is a genuinely different source image (simple leaf-only linework, open
# top), not a numbers-only crop of the closed floral vine ring used by
# p06_wreath/p06_wreath_numbers. Source idea: P30a/P30b ("56"/"64" in a
# small floral leaf wreath, number only) -- real reference photo used as
# a Midjourney image-prompt (--iw 1.5) alongside the text prompt, not
# text-only like p06's source.
#
# Source: Midjourney "Image 3" from this run (5a933fd7-..._3.png) -- chosen
# over 7 alternatives for matching the reference photo's straight-stem
# bottom join (not the crossing-branch or curved-join variants some of the
# other outputs had) and for being true black ink on white (several others
# came out in dark navy on an off-white/grey tint, drifting from the
# black-only brief).
#
# The source PNG had a DOUBLE-line border baked in, not a single line like
# P06's -- caught only after the first crop still had a thin unbroken
# vertical line of "ink" spanning the full canvas height (col=4 of a first
# attempt), which is what a leftover border sliver looks like structurally
# (real artwork doesn't run edge-to-edge as an unbroken line). Re-cropped
# inside BOTH border lines (70,65)-(1245,865) of the 1312x928px original
# before ink separation.
#
# 5 connected components: main wreath body (both branches + stem, fused
# into one blob) + 2 detached leaf tips (the topmost leaf on each branch
# has a small linework gap from the branch below it) + the '5'/'6' digits.
# Kept={main body, both leaf tips}, digits erased. verify() confirms 3/3,
# no leftover fragments.
#
# check_symmetry_assumption() run on the two branches (mirrored across the
# vertical centreline): 7.6% pixel mismatch -- confirms the source is NOT
# symmetric enough to build from one mirrored half, consistent with every
# other symmetry check done on AI-generated art in this file. Both
# branches were already being extracted as their own real pixels (not
# built by mirroring), so no rework was needed -- this check just
# confirmed that was the right call rather than catching a mistake.
#
# Vertical margin: initially looked alarmingly tight (~2.1-2.75mm) when
# computed from the padded icon FILE's own bounding box -- that's just
# crop_to_content()'s automatic 3% transparent padding baked into the
# file, not real print clearance. The actual raw ink sits 4.75mm clear of
# the card top and 5.5mm clear of the bottom, both comfortably over the
# 3mm minimum -- don't re-flag this as a problem without re-checking the
# raw (unpadded) ink position first.
#
# NUMBER_MAX_WIDTH: true min interior gap sampled every 2px across the
# full digit height (300-456px of the source) -- unlike P06's wreath,
# this one was very stable throughout (403-417px, no anomalous jumps),
# consistent with the leaf edges being straighter/less densely detailed
# than the floral version.
#
# Vertical room is NOT a binding constraint -- the wreath is open at the
# top, so there's no wreath ink bounding the number's height at all in
# the upper portion (measure_gap on that column returns a near-zero
# result, since there's nothing to find a gap between); the number's
# actual ceiling is the card/border, not the artwork. Width dominates,
# same conclusion as every other wreath/house style in this file.
# ---------------------------------------------------------------------------
P30_LAUREL_ICON_MASTER = "assets/icons/p30_laurel_icon.png"

P30_LAUREL_ICON = dict(x=26.4511 * mm, y=2.7500 * mm, w=86.6213 * mm, h=95.1250 * mm)

P30_LAUREL_NUMBER_CENTER_Y = 52.6565 * mm  # RL y, erased "56" centroid
P30_LAUREL_NUMBER_MAX_WIDTH = 48.02 * 0.90 * mm  # true min interior gap, 10% margin
P30_LAUREL_NUMBER_MAX_SIZE = 150  # pt -- generous; width is the real constraint, vertical room is open
P30_LAUREL_NUMBER_MIN_SIZE = 24


def _p30_laurel_icon_path(accent_key):
    """Same recolour-and-cache pattern as _p06_icon_path/_p47_icon_path --
    generates the accent-coloured icon from the master silhouette on
    first use, caches to disk. Returns None if the master art isn't
    present (caller falls back to the plain vector floral icon)."""
    master = _asset_path(P30_LAUREL_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _asset_path(f"assets/icons/p30_laurel_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p30_laurel_numbers(c, ox, oy, order):
    """18. P30 laurel wreath, numbers only -- open-top laurel leaf wreath
    (two symmetrical branches meeting at a small stem at the bottom), a
    single large number centred inside. No street-name field, matching
    the P30a/P30b source pins. LANDSCAPE (140x100mm, reuses
    P02_CARD_W/H) -- see the P30_LAUREL_* constants block above for the
    full extraction/derivation writeup, including the border-cropping
    and symmetry-check findings specific to this source image."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _p30_laurel_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P30_LAUREL_ICON["x"], oy + P30_LAUREL_ICON["y"],
            P30_LAUREL_ICON["w"], P30_LAUREL_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- plain vector
        # floral icon, same reasoning as every other hollow-icon style's
        # fallback in this file: a substitution, not a lesser version of
        # the same design (there's no vector equivalent of the extracted
        # laurel outline).
        print(
            f"WARNING: p30_laurel_numbers: master icon not found at "
            f"{_asset_path(P30_LAUREL_ICON_MASTER)!r} -- rendering plain "
            f"floral fallback instead of the extracted laurel wreath "
            f"design for house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 10 * mm, 12 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 70)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.46, order["house_number"])
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P30_LAUREL_NUMBER_MAX_SIZE, P30_LAUREL_NUMBER_MIN_SIZE,
                                  P30_LAUREL_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", number_size)
    number_baseline = P30_LAUREL_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)


# ---------------------------------------------------------------------------
# P15 -- heart-vine wreath (thin vine ring with small heart-shaped leaves),
# house number + FLAT street name (not curved -- see below for why),
# LANDSCAPE (140x100mm, reuses P02_CARD_W/H). Source idea: P15 (the blue
# bin's heart-vine badge, "46 Fishpond Lane", one of 3 designs in that
# pin -- the other two, laurel-adjacent butterfly wreath and hexagon+bee,
# aren't built from this source).
#
# Source: Midjourney "Image 3" from this run (bddc4e62-..._1.png), chosen
# from 8 alternatives across 2 seeds. Seed 2 (Images 5-8) was rejected
# outright -- came back in navy ink on an off-white/grey tint despite the
# "black line art only, plain white background" text instruction, most
# likely --iw pulling too hard on the blue-bin reference photo's colour.
# Of seed 1's 4 usable black-on-white options, 3 had CURVED street text
# (matching the reference photo's own curved layout) -- deliberately
# passed over in favour of this one's FLAT text, because curved text on a
# wreath this size already broke once (see P06_WREATH_CURVE_COEFFS'S
# "TRIED AND REJECTED" comment -- ~12mm baseline shift, letters colliding
# with the number). Rather than re-attempt curved text on a second wreath
# and risk the same failure, went with the one source image that already
# matches what P27/P06/P30 all settled on: flat text.
#
# The source PNG had a soft drop-shadow vignette AND a double-line card
# border baked in (same combination as P06's original source) -- cropped
# past both in two passes: first past the vignette down to the border
# rectangle, then a second pass after a leftover border sliver was still
# visible as a thin unbroken line at the crop edges (same tell as P30's
# extraction: a real design's linework doesn't run edge-to-edge as an
# unbroken straight line).
#
# 19 connected components: wreath body (fused vine+hearts) + 4 detached
# heart fragments (small linework gaps, same pattern as P06's detached
# bud and P30's detached leaf tips) + 2 digits ("46") + 12 letter
# components ("FISHPOND LANE" -- F,I,S,H,P,O,N,D,L,A,N,E, no fusions this
# time, unlike P06's "GROVE STREET" which had one). Kept={wreath body +
# 4 fragments}, digits+letters erased. verify() reports 4 (its own
# min_size=200 filter drops the smallest 112px fragment from the printed
# report) -- pixel-count cross-check confirms all 5 kept components are
# actually in the saved file, same non-issue as P06/P30's extractions.
#
# NUMBER/STREET_CENTER_Y are the erased text's own combined centroids,
# same ground-truth convention as every other hollow-icon style. Widths
# are the true min interior gap sampled across each field's full
# glyph-height range -- this wreath's ring is more open than P06's dense
# floral version (single thin vine vs. a packed flower cluster at every
# node), so both budgets came out noticeably more generous (62mm number,
# 56mm street vs. P06's 51mm/37mm).
#
# NUMBER_MAX_SIZE is a MEASURED ceiling (from the erased digits' own
# bbox height, ~92pt implied), not a generous guess -- learned directly
# from D18's number-oversizing bug (a permissive ceiling let auto-fit
# balloon "36" up past 100pt and collide with the street text 18.5mm
# below; this wreath has the same ~19mm number-to-street gap, so the
# same risk applies here without a measured cap).
#
# STREET_MAX_SIZE is ALSO a measured ceiling (~19pt implied from the
# erased letters' own bbox height, notably smaller relative to the
# number than P06's proportions -- this source image's street text really
# is rendered small and delicate under a much bigger number, not a
# measurement error). MIN_SIZE=8 (not 14) from the start, applying the
# same fix D18 needed after the fact rather than reintroducing the same
# broken-floor bug on a second style.
# ---------------------------------------------------------------------------
P15_HEART_ICON_MASTER = "assets/icons/p15_heart_icon.png"

P15_HEART_ICON = dict(x=23.3133 * mm, y=2.2500 * mm, w=92.5322 * mm, h=95.6250 * mm)

P15_HEART_NUMBER_CENTER_Y = 53.7744 * mm  # RL y, erased "46" centroid
P15_HEART_NUMBER_MAX_WIDTH = 69.10 * 0.90 * mm  # true min interior gap, 10% margin
P15_HEART_NUMBER_MAX_SIZE = 88  # pt -- measured from source digit height (~92pt implied), small trim for safety
P15_HEART_NUMBER_MIN_SIZE = 20

P15_HEART_STREET_CENTER_Y = 34.5850 * mm  # RL y, erased "FISHPOND LANE" centroid
P15_HEART_STREET_MAX_WIDTH = 62.85 * 0.90 * mm  # true min interior gap, 10% margin
P15_HEART_STREET_MAX_SIZE = 20  # pt -- measured from source letter height (~19pt implied)
P15_HEART_STREET_MIN_SIZE = 8  # not 14 -- see D18's broken-floor bug in the block comment above


def _p15_heart_icon_path(accent_key):
    """Same recolour-and-cache pattern as every other hollow-icon style
    in this file. Returns None if the master art isn't present (caller
    falls back to the plain vector floral icon)."""
    master = _asset_path(P15_HEART_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _asset_path(f"assets/icons/p15_heart_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p15_heart_wreath(c, ox, oy, order):
    """19. P15 heart-vine wreath -- thin vine ring with small heart-
    shaped leaves, house number nested in the upper interior with the
    street name in FLAT (not curved) text below it -- see the P15_HEART_*
    constants block above for why flat text was chosen over the source's
    own curved layout. LANDSCAPE (140x100mm, reuses P02_CARD_W/H)."""
    accent_key = order.get("accent", "berry")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _p15_heart_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P15_HEART_ICON["x"], oy + P15_HEART_ICON["y"],
            P15_HEART_ICON["w"], P15_HEART_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- plain vector
        # floral icon, flat text. Same reasoning as every other
        # hollow-icon style's fallback: a substitution, not a lesser
        # version of the same design.
        print(
            f"WARNING: p15_heart_wreath: master icon not found at "
            f"{_asset_path(P15_HEART_ICON_MASTER)!r} -- rendering plain "
            f"floral fallback instead of the extracted heart-vine wreath "
            f"design for house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 10 * mm, 12 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 44)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 14)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.32, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P15_HEART_NUMBER_MAX_SIZE, P15_HEART_NUMBER_MIN_SIZE,
                                  P15_HEART_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", number_size)
    number_baseline = P15_HEART_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold",
                                  P15_HEART_STREET_MAX_SIZE, P15_HEART_STREET_MIN_SIZE,
                                  P15_HEART_STREET_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", street_size)
    street_baseline = P15_HEART_STREET_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)


# ---------------------------------------------------------------------------
# P28 -- arrow/fletching wreath (alternating arrowhead and hatched-fletching
# shapes forming a ring), house number + FLAT street name. LANDSCAPE
# (140x100mm, reuses P02_CARD_W/H). Source idea: P28 (TheVinylStudioGB's
# "36 Central Avenue" listing, real pricing on record: £2.95+, 4.5 stars,
# "Various Colours, Various Sizes").
#
# REGENERATED (v2) -- the original source (07ef6d68-..._1.png, see prior
# git history/chat for that derivation) had one arrowhead node that came
# out visibly pinched/asymmetric compared to the other three -- not a
# tangled glitch, but a real, user-caught inconsistency. Tried a manual
# raster splice first (copy a clean arrowhead, rotate/flip it onto the
# bad one's position) -- didn't work: tested both a 90-degree rotation
# and a horizontal-flip pairing, neither produced a seamless join, and a
# forced splice would've left a visible kink where the pasted vine met
# the original. Raster copy/rotate can't substitute for redrawing a
# matching vector stroke. Re-ran Midjourney instead with explicit
# uniformity language added to the prompt ("all arrowheads and fletching
# clusters drawn consistently the same size and shape as each other") --
# this source is the result, and every node was individually checked
# (same 4-quadrant zoom sweep as the original glitch-catch) before
# accepting it: all 9 arrow/fletching nodes measured within ~10% of each
# other by pixel area (4448-4906px), no pinching, no glitches.
#
# The source card border was a single line (not double like P06/P30's),
# and separate_ink's automatic method worked cleanly with no manual
# threshold needed -- a much easier extraction than the original.
#
# 24 connected components: 9 arrow/fletching nodes (each its OWN
# component this time, not one fused blob like v1 -- this source's vine
# has small dashed gaps between segments rather than one continuous
# line) + 2 digits ("36") + 13 street letters ("CENTRAL AVENUE", no
# fusions). Kept={all 9 arrow/fletching nodes}.
#
# This source's "CENTRAL AVENUE" was FLAT already (not curved like v1's
# and every other wreath's source) -- all 13 letters share the same
# bbox_y range, confirmed directly rather than assumed, so no
# curve-amplitude trap here: the erased letters' own bbox height is a
# valid cap-height reference this time.
#
# Interior-gap measurement needed a different approach than every prior
# wreath: the dashed vine produced spurious ~1px "gaps" on plain
# row-by-row measure_gap() scans (a row crossing a dash-gap in the vine
# reads as a tiny artefact, not the true interior span). Used a
# left-half/right-half split instead -- take the rightmost ink point in
# the left half of the row and the leftmost ink point in the right half,
# the gap between them -- which is robust to small dash gaps within
# either half. Re-verify this approach (not the plain measure_gap) if
# re-deriving constants for any future dashed-vine source.
#
# Edge clearance: raw ink margin 5.8mm top / 6.2mm bottom, comfortably
# over the 3mm minimum -- no safety scale-down needed this time (v1's
# was a real, source-specific problem, not a recurring one).
# ---------------------------------------------------------------------------
P28_ARROW_ICON_MASTER = "assets/icons/p28_arrow_icon.png"

P28_ARROW_ICON = dict(x=22.9268 * mm, y=3.1683 * mm, w=94.0488 * mm, h=93.1683 * mm)

P28_ARROW_NUMBER_CENTER_Y = 54.1521 * mm  # RL y, erased "36" centroid
P28_ARROW_NUMBER_MAX_WIDTH = 60.5854 * 0.90 * mm  # true min interior gap (left/right-half method), 10% margin
P28_ARROW_NUMBER_MAX_SIZE = 120  # pt -- measured from source digit height (~126pt implied), small trim for safety
P28_ARROW_NUMBER_MIN_SIZE = 20

P28_ARROW_STREET_CENTER_Y = 34.6100 * mm  # RL y, erased "CENTRAL AVENUE" centroid
P28_ARROW_STREET_MAX_WIDTH = 69.3659 * 0.90 * mm  # true min interior gap (left/right-half method), 10% margin
P28_ARROW_STREET_MAX_SIZE = 19  # pt -- measured from the (this time genuinely flat) erased letters' own bbox height
P28_ARROW_STREET_MIN_SIZE = 8  # established floor (see D18's broken-14pt-floor bug), not 14


def _p28_arrow_icon_path(accent_key):
    """Same recolour-and-cache pattern as every other hollow-icon style
    in this file. Returns None if the master art isn't present (caller
    falls back to the plain vector floral icon)."""
    master = _asset_path(P28_ARROW_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _asset_path(f"assets/icons/p28_arrow_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p28_arrow_wreath(c, ox, oy, order):
    """20. P28 arrow/fletching wreath -- alternating arrowhead and
    hatched-fletching shapes forming a ring, house number nested in the
    upper interior with the street name in FLAT (not curved) text below
    it. LANDSCAPE (140x100mm, reuses P02_CARD_W/H). v2 source (see the
    P28_* constants block above) -- regenerated after the original had
    one visibly inconsistent arrowhead node; no safety scale-down needed
    this time, raw margins were already comfortably clear."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _p28_arrow_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P28_ARROW_ICON["x"], oy + P28_ARROW_ICON["y"],
            P28_ARROW_ICON["w"], P28_ARROW_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- plain vector
        # floral icon, flat text. Same reasoning as every other
        # hollow-icon style's fallback: a substitution, not a lesser
        # version of the same design.
        print(
            f"WARNING: p28_arrow_wreath: master icon not found at "
            f"{_asset_path(P28_ARROW_ICON_MASTER)!r} -- rendering plain "
            f"floral fallback instead of the extracted arrow-wreath "
            f"design for house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 10 * mm, 12 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 44)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 14)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.32, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P28_ARROW_NUMBER_MAX_SIZE, P28_ARROW_NUMBER_MIN_SIZE,
                                  P28_ARROW_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", number_size)
    number_baseline = P28_ARROW_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold",
                                  P28_ARROW_STREET_MAX_SIZE, P28_ARROW_STREET_MIN_SIZE,
                                  P28_ARROW_STREET_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", street_size)
    street_baseline = P28_ARROW_STREET_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)


# ---------------------------------------------------------------------------
# P31 -- olive branch wreath (loose, rounded leaves in irregular alternating
# pairs, not the tight symmetrical laurel style), house number + FLAT
# street name. LANDSCAPE (140x100mm, reuses P02_CARD_W/H). No P## precursor
# -- unlike every other wreath in this file, this one has NO catalogued
# competitor bin-sticker sighting behind it. Searched directly for olive/
# eucalyptus/wheat bin stickers before building this; found only general
# home-decor door wreaths (Michaels, West Elm, Etsy's general wreath
# category), no bin-sticker precedent. Built as a deliberate experiment
# rather than a market-validated pattern -- flag this honestly if asked
# why it doesn't have the same "real listing" backing as D18-D22.
#
# Source: Midjourney "Image 1" of 4 (c4e14b61-..._0.png), no image
# reference used for generation (text-only prompt) -- there was no clean
# line-art reference to draw from, only photographed physical door
# wreaths (twig base, full colour), which would have fought the
# black-line-art brief the same way the heart-wreath's colour-drifted
# batch did. Chose Image 1 over 3 alternatives for being the cleanest
# across all 4 quadrants (checked the same way as every wreath since the
# arrow-wreath inconsistency) and for reading as genuinely LOOSE/ROUNDED
# olive-style leaves -- Image 4 in particular had narrower, more pointed
# leaves that drifted toward looking like a duplicate of P30's laurel
# wreath, which defeated the point of choosing olive over laurel-adjacent
# motifs in the first place.
#
# 21 connected components: main wreath body (fused) + 6 detached leaf
# fragments (small linework gaps, same recurring pattern as every other
# wreath's extraction) + 2 digits ("12") + 12 street components
# ("MAPLE DRIVE" -- M,A,P,L,E,D,R,I,V,E is 10 letters, but "I" produced 2
# components -- dot separated from stem -- and a small 12px fragment
# near "DRIVE" that reads as a broken-off serif, not a stray mark).
# Kept={main body + 6 leaf fragments}.
#
# This source's "MAPLE DRIVE" was FLAT already (confirmed directly --
# all 12 components share the same y=532-559 range, not a curve-amplitude
# spread), so no P28-style curved-text trap here.
#
# Edge clearance: raw ink margin 5.8mm top / 4.1mm bottom, comfortably
# over the 3mm minimum -- no safety scale-down needed.
#
# Interior gaps measured with the left-half/right-half method (not plain
# row-by-row measure_gap()) as a precaution -- this wreath's leaf spacing
# is deliberately irregular (that's the "loose olive branch" look), so
# the same spurious-small-gap risk that showed up on P28's dashed vine
# seemed worth guarding against here too, even though this vine turned
# out to be continuous, not dashed.
# ---------------------------------------------------------------------------
P31_OLIVE_ICON_MASTER = "assets/icons/p31_olive_icon.png"

P31_OLIVE_ICON = dict(x=24.5757 * mm, y=1.4201 * mm, w=91.1980 * mm, h=95.3846 * mm)

P31_OLIVE_NUMBER_CENTER_Y = 53.3869 * mm  # RL y, erased "12" centroid
P31_OLIVE_NUMBER_MAX_WIDTH = 50.5491 * 0.90 * mm  # true min interior gap (left/right-half method), 10% margin
P31_OLIVE_NUMBER_MAX_SIZE = 90  # pt -- measured from source digit height (~95pt implied), small trim for safety
P31_OLIVE_NUMBER_MIN_SIZE = 20

P31_OLIVE_STREET_CENTER_Y = 35.5432 * mm  # RL y, erased "MAPLE DRIVE" centroid
P31_OLIVE_STREET_MAX_WIDTH = 52.9950 * 0.90 * mm  # true min interior gap (left/right-half method), 10% margin
P31_OLIVE_STREET_MAX_SIZE = 12  # pt -- measured from the (genuinely flat) erased letters' own bbox height (~12.7pt implied)
P31_OLIVE_STREET_MIN_SIZE = 8  # established floor (see D18's broken-14pt-floor bug), not 14


def _p31_olive_icon_path(accent_key):
    """Same recolour-and-cache pattern as every other hollow-icon style
    in this file. Returns None if the master art isn't present (caller
    falls back to the plain vector floral icon)."""
    master = _asset_path(P31_OLIVE_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _asset_path(f"assets/icons/p31_olive_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p31_olive_wreath(c, ox, oy, order):
    """21. P31 olive branch wreath -- loose, rounded leaves in irregular
    alternating pairs, house number nested in the upper interior with the
    street name in flat text below it. LANDSCAPE (140x100mm, reuses
    P02_CARD_W/H). No catalogued source idea -- an experimental design
    with no confirmed bin-sticker market precedent, unlike every other
    wreath in this file; see the P31_* constants block above for the
    full derivation and that caveat."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _p31_olive_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P31_OLIVE_ICON["x"], oy + P31_OLIVE_ICON["y"],
            P31_OLIVE_ICON["w"], P31_OLIVE_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- plain vector
        # floral icon, flat text. Same reasoning as every other
        # hollow-icon style's fallback: a substitution, not a lesser
        # version of the same design.
        print(
            f"WARNING: p31_olive_wreath: master icon not found at "
            f"{_asset_path(P31_OLIVE_ICON_MASTER)!r} -- rendering plain "
            f"floral fallback instead of the extracted olive wreath "
            f"design for house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 10 * mm, 12 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 44)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 14)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.32, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P31_OLIVE_NUMBER_MAX_SIZE, P31_OLIVE_NUMBER_MIN_SIZE,
                                  P31_OLIVE_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", number_size)
    number_baseline = P31_OLIVE_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold",
                                  P31_OLIVE_STREET_MAX_SIZE, P31_OLIVE_STREET_MIN_SIZE,
                                  P31_OLIVE_STREET_MAX_WIDTH)
    asc, desc = getAscentDescent("Times-Bold", street_size)
    street_baseline = P31_OLIVE_STREET_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)


# ---------------------------------------------------------------------------
# Duck family, scene 1 of 4 (father duck + duckling) -- first of a planned
# "animal family" series (see chat history): 4 related scenes (father+young,
# mother+young, 2x young playing) sold as a matching set, same underlying
# concept as the illustrated-pet pattern already validated on the idea board
# (P03/P14/P22 in Idea-Board-Solutions-Reference.md) but built as an
# intentional set rather than one standalone icon. No P## precursor -- this
# is an original concept developed directly in chat, not a catalogued
# competitor pin. LANDSCAPE 140x100mm (P02_CARD_W/H), matching the shared
# landscape card size used by the other full-scene/D0x designs.
#
# Unlike every other silhouette icon in this file, this asset is
# deliberately NOT accent-recolourable: extracted as solid black
# (fill_rgb=(0,0,0) at extraction time, via icon-silhouette-extraction) and
# always rendered black, matching the black-on-white convention of the
# bestselling silhouette-pet designs it's modelled on, rather than this
# file's usual accent-tinted-silhouette pattern (P02/P27/P47). The "accent"
# order field still controls the border colour only, same as every style.
#
# Dad vs. mum is signalled by a real anatomical cue, not an invented
# accessory: a small curled tail feather, present on drakes (male mallards)
# and absent on hens -- reads clearly even in flat black silhouette. Scene 2
# (mother duck, no tail feather) should reuse this same layout/placement,
# just swapping the icon asset, once built.
# ---------------------------------------------------------------------------
DUCK_FATHER_ICON_MASTER = "assets/icons/duck_family_father_icon.png"

# Icon placement box: generous width so preserveAspectRatio's height
# constraint binds and the image auto-centres horizontally -- real
# extracted aspect ratio is 647:390 px (~1.66:1 w:h), so at box
# height=58mm the rendered width comes out ~96.3mm, comfortably inside
# the 120mm box width given here.
DUCK_FATHER_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)

DUCK_FATHER_NUMBER_CENTER_Y = 22 * mm  # RL y, baseline (not true centre --
# same simple convention as style 10/paw, not P27/P47's asc/desc centring,
# since there's no hollow interior to centre precisely against)
DUCK_FATHER_STREET_CENTER_Y = 10 * mm  # RL y, baseline

# Same fix as D04/P27_PAD: the shared global PAD (2mm) puts the border
# stroke itself under the 3mm minimum ink-to-card-edge clearance
# render_proof_thumbnail checks for -- this is what actually bound
# clearance on all 4 sides on the first render here (1.71-1.96mm,
# uniform regardless of icon/text placement), not this style's own
# icon/number/street placement. Scoped to this style only, not a global
# PAD change.
DUCK_FATHER_PAD = 3.4 * mm

# ---------------------------------------------------------------------------
# ANIMAL-FAMILY AUTO-SHRINK CONSTANTS -- added to close the "22 fixed-size
# styles have zero overflow protection" gap identified when auditing all 35
# styles against the 13 that already auto-fit (P02/P25/P25b/P27/P47/P06
# family/P09a/P21). Shared across all 12 animal-family scenes (duck/dog/cat
# x4 each), since they all use the identical fixed-size text block this
# replaces -- see chat history for the full audit.
#
# MAX sizes are set to the PREVIOUS fixed sizes (50pt number / 16pt street)
# so short text (e.g. "36"/"Grove Street") renders identically to the old
# proofs -- nothing changes until text is long enough to need shrinking.
#
# MIN sizes and MAX widths below are a PROVISIONAL starting point, not yet
# validated against a real print -- these scenes sit in a fixed-height band
# under the icon (unlike P02/P27's hollow-interior nesting), so there's no
# hard geometric constraint forcing a particular floor the way there is for
# those styles. Revisit once the physical print test (short/long/edge-case
# house numbers and street names) comes back; tighten MAX_WIDTH or raise
# MIN_SIZE if anything reads as touching the icon above or the border below.
ANIMAL_NUMBER_MAX_SIZE = 50
ANIMAL_NUMBER_MIN_SIZE = 24
ANIMAL_NUMBER_MAX_WIDTH = 110 * mm  # card is 140mm wide; leaves margin inside PAD

ANIMAL_STREET_MAX_SIZE = 16
ANIMAL_STREET_MIN_SIZE = 9
ANIMAL_STREET_MAX_WIDTH = 122 * mm  # matches the icon box width (120mm) plus a hair


def _animal_family_text(c, cx, oy, order):
    """Shared auto-shrink number/street drawing for all 12 animal-family
    scenes -- factored out so the fix lives in one place instead of being
    pasted 12 times (the original fixed-size version WAS pasted 12 times
    identically, which is how this gap went unnoticed for this long)."""
    c.setFillColor(HexColor(INK))
    number_size = _fit_font_size(
        order["house_number"], "Helvetica-Bold",
        ANIMAL_NUMBER_MAX_SIZE, ANIMAL_NUMBER_MIN_SIZE, ANIMAL_NUMBER_MAX_WIDTH,
    )
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(cx, oy + DUCK_FATHER_NUMBER_CENTER_Y, order["house_number"])

    street_size = _fit_font_size(
        order["street_name"], "Helvetica",
        ANIMAL_STREET_MAX_SIZE, ANIMAL_STREET_MIN_SIZE, ANIMAL_STREET_MAX_WIDTH,
    )
    c.setFont("Helvetica", street_size)
    c.drawCentredString(cx, oy + DUCK_FATHER_STREET_CENTER_Y, order["street_name"])


def _style_duck_family_father(c, ox, oy, order):
    """16. Duck family, scene 1 of 4 -- father mallard (identifiable by a
    small curled tail feather, the real anatomical dad cue used instead of
    an invented accessory -- see chat history) walking with one duckling
    trailing behind. Number + street name printed below the scene, same
    layout convention as style 10 (paw) rather than P27/P47's nested-in-
    icon approach, since this icon has no interior hollow to nest text
    into. LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid black, not
    accent-recolourable -- see DUCK_FATHER_ICON_MASTER comment above.
    Scenes 2-4 (mother duck, ducklings playing x2) planned as a matching
    set, same style/seed, not yet built."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(DUCK_FATHER_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + DUCK_FATHER_ICON["x"], oy + DUCK_FATHER_ICON["y"],
            DUCK_FATHER_ICON["w"], DUCK_FATHER_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- same reasoning
        # as P02/P27/P47's fallback: there's no vector equivalent of this
        # illustrated scene, so this is a substitution, not a lesser
        # version of the same design.
        print(
            f"WARNING: duck_family_father: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted duck design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Duck family, scene 2 of 4 (mother duck + duckling) -- same set as
# duck_family_father above, see that style's comment for the full concept
# writeup. Reuses the SAME icon placement box, PAD, and number/street
# positions as scene 1 so the 4-scene set shares one consistent footprint
# -- only the icon asset and the fallback vector call differ.
#
# Dad/mum distinction: no curled tail feather here (the mother's tail is a
# plain layered/jagged shape, not the small curl on the father's), same
# real anatomical drake-vs-hen cue used instead of an invented accessory.
# Duckling is positioned close beside her rather than trailing behind, per
# the source render chosen for extraction (source image 2 of 4 generated
# -- see chat history).
# ---------------------------------------------------------------------------
DUCK_MOTHER_ICON_MASTER = "assets/icons/duck_family_mother_icon.png"

# Same box as DUCK_FATHER_ICON -- deliberately shared so the 4-scene set
# has one consistent icon footprint; preserveAspectRatio centres each
# scene's own (slightly different) real aspect ratio within it. This
# scene's extracted aspect is ~1.45:1 w:h (vs. father's ~1.66:1, narrower
# because the duckling sits tucked close rather than trailing further
# out), so it renders a bit less wide within the same box -- expected,
# not a bug.
DUCK_MOTHER_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_duck_family_mother(c, ox, oy, order):
    """17. Duck family, scene 2 of 4 -- mother mallard (no curled tail
    feather, unlike scene 1's father) with one duckling close beside her.
    Same layout convention and placement constants as
    duck_family_father (style 16) -- see that style's docstring for the
    full set concept. LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid
    black, not accent-recolourable, same as scene 1."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(DUCK_MOTHER_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + DUCK_MOTHER_ICON["x"], oy + DUCK_MOTHER_ICON["y"],
            DUCK_MOTHER_ICON["w"], DUCK_MOTHER_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- same reasoning
        # as scene 1's fallback.
        print(
            f"WARNING: duck_family_mother: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted duck design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Duck family, scene 3 of 4 (ducklings playing, no adult) -- same set as
# duck_family_father/mother above. Three ducklings splashing/playing near
# a wavy water line, no parent duck present. Source render has visible fur
# texture on the ducklings' backs (unlike scenes 1-2's plain flat fill) --
# a deliberate user choice (image 4 of 4 generated, picked over 3 flatter
# alternatives) that's a style departure from D06/D07, flagged in chat at
# selection time. Splash droplets under the feet are kept as part of the
# icon (real artwork, not baked-in text) -- see chat history for the
# component-keep list.
# ---------------------------------------------------------------------------
DUCK_PLAYING1_ICON_MASTER = "assets/icons/duck_family_playing1_icon.png"

# Same box as DUCK_FATHER_ICON/DUCK_MOTHER_ICON. This scene's extracted
# aspect is ~2.82:1 w:h (much wider/flatter than scenes 1-2, since 3
# ducklings side-by-side span more width than a parent+duckling pair) --
# box WIDTH binds here rather than height, so the rendered icon comes out
# ~120mm wide x ~42.6mm tall, shorter than scenes 1-2's ~58mm. Expected,
# not a bug -- preserveAspectRatio centres it within the same box either way.
DUCK_PLAYING1_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_duck_family_playing1(c, ox, oy, order):
    """18. Duck family, scene 3 of 4 -- three ducklings playing/splashing
    near a wavy water line, no adult duck present. Same layout convention
    and placement constants as duck_family_father/mother (styles 16-17)
    -- see duck_family_father's docstring for the full set concept.
    LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid black, not
    accent-recolourable, same as scenes 1-2. NOTE: source art has visible
    fur texture, a style departure from scenes 1-2's flat fill -- see
    DUCK_PLAYING1_ICON_MASTER comment above."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(DUCK_PLAYING1_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + DUCK_PLAYING1_ICON["x"], oy + DUCK_PLAYING1_ICON["y"],
            DUCK_PLAYING1_ICON["w"], DUCK_PLAYING1_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: duck_family_playing1: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted duck design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Duck family, scene 4 of 4 (ducklings playing, more energetic variant) --
# final scene of the set started with duck_family_father. Three ducklings
# mid-hop/tumbling, wings flared, bigger splashes than duck_family_playing1
# -- deliberately more dynamic per user request, distinct enough from
# scene 3 that the two "ducklings only" scenes don't read as near-
# duplicates sitting side by side. Same fur-texture style as
# duck_family_playing1 (both scenes share that departure from scenes 1-2's
# flat fill, so the "ducklings only" pair reads as its own matched look).
# ---------------------------------------------------------------------------
DUCK_PLAYING2_ICON_MASTER = "assets/icons/duck_family_playing2_icon.png"

# Same box as the other 3 duck-family scenes. Extracted aspect ~2.61:1
# w:h (wide/flat like scene 3, width binds within the shared box) --
# renders ~120mm wide x ~46mm tall.
DUCK_PLAYING2_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_duck_family_playing2(c, ox, oy, order):
    """19. Duck family, scene 4 of 4 (final) -- three ducklings mid-hop/
    tumbling with bigger splashes, more energetic than duck_family_playing1
    (style 18). Same layout convention and placement constants as the
    other 3 duck-family scenes (16-18) -- see duck_family_father's
    docstring for the full set concept. LANDSCAPE 140x100mm (P02_CARD_W/H).
    Icon is solid black, not accent-recolourable. Fur-texture style,
    matching scene 3 not scenes 1-2."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(DUCK_PLAYING2_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + DUCK_PLAYING2_ICON["x"], oy + DUCK_PLAYING2_ICON["y"],
            DUCK_PLAYING2_ICON["w"], DUCK_PLAYING2_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: duck_family_playing2: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted duck design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Dog family, scene 1 of 4 (adult dog + puppy, walking together) -- second
# animal-family line after ducks (D06-D09), same underlying concept. Unlike
# the ducks, dogs have no reliable visual dad/mum cue in silhouette, so per
# explicit user decision this set skips gendering entirely: 2 adult+pup
# scenes (differentiated by composition, not implied parentage) + 2
# pups-playing scenes, mirroring the ducks' 4-scene structure without the
# father/mother framing. FLAT SILHOUETTE, no fur texture -- the duck
# texture question (see Animal-Family-Texture-Test-Plan.md) hadn't been
# physically tested yet when this line started, so flat was used as the
# proven-safe default (matches P03/P14/P22 bestseller evidence) rather than
# repeating an untested print risk on a second animal line.
# ---------------------------------------------------------------------------
DOG_FAMILY_1_ICON_MASTER = "assets/icons/dog_family_1_icon.png"

# Same box convention as the duck family scenes. Extracted aspect ~2.63:1
# w:h -- width binds within the shared box, renders ~120mm wide x ~45.6mm
# tall.
DOG_FAMILY_1_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_dog_family_1(c, ox, oy, order):
    """20. Dog family, scene 1 of 4 -- adult dog with a puppy trailing
    behind, both walking in the same direction. No gendering (see module
    comment above) -- differentiated from scene 2 by composition only.
    Same layout convention and placement constants as the duck family set
    (styles 16-19). LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid
    black, not accent-recolourable, flat silhouette (no fur texture)."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(DOG_FAMILY_1_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + DOG_FAMILY_1_ICON["x"], oy + DOG_FAMILY_1_ICON["y"],
            DOG_FAMILY_1_ICON["w"], DOG_FAMILY_1_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: dog_family_1: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted dog design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Dog family, scene 2 of 4 (adult dog + puppy, close beside) -- see
# dog_family_1's module comment for the full set concept (no gendering,
# flat silhouette). Differentiated from scene 1 by composition (puppy
# tucked close beside the adult, not trailing at a distance) rather than
# implied parentage.
# ---------------------------------------------------------------------------
DOG_FAMILY_2_ICON_MASTER = "assets/icons/dog_family_2_icon.png"

# Same box convention as the rest of the animal-family scenes. Extracted
# aspect ~1.76:1 w:h -- height binds within the shared box, renders
# ~58mm tall x ~102mm... wait, box width caps at 120mm, so width binds
# instead: renders ~120mm wide x ~68mm tall would exceed box height (58mm)
# -- preserveAspectRatio's height constraint actually binds here since
# 120/58=2.07 > 1.76, so real output is ~58mm tall x ~102mm wide,
# comfortably inside the 120mm box width.
DOG_FAMILY_2_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_dog_family_2(c, ox, oy, order):
    """21. Dog family, scene 2 of 4 -- adult dog with a puppy close
    beside it (not trailing, unlike scene 1). No gendering, same layout
    convention and placement constants as the rest of the animal-family
    set. LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid black, not
    accent-recolourable, flat silhouette (no fur texture)."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(DOG_FAMILY_2_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + DOG_FAMILY_2_ICON["x"], oy + DOG_FAMILY_2_ICON["y"],
            DOG_FAMILY_2_ICON["w"], DOG_FAMILY_2_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: dog_family_2: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted dog design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Dog family, scene 3 of 4 (puppies playing, calmer -- nose-to-nose nuzzle,
# no adult) -- see dog_family_1's module comment for the full set concept.
# Companion to duck_family_playing1 (D08): same "calmer" energy level
# within its own animal-family set.
# ---------------------------------------------------------------------------
DOG_PLAYING1_ICON_MASTER = "assets/icons/dog_family_playing1_icon.png"

# Same box convention as the rest of the animal-family scenes. Extracted
# aspect ~2.52:1 w:h -- width binds within the shared box, renders
# ~120mm wide x ~47.6mm tall.
DOG_PLAYING1_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_dog_family_playing1(c, ox, oy, order):
    """22. Dog family, scene 3 of 4 -- two puppies nose-to-nose, calmer
    energy (companion to duck_family_playing1/D08), no adult dog. Same
    layout convention and placement constants as the rest of the
    animal-family set. LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid
    black, not accent-recolourable, flat silhouette (no fur texture)."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(DOG_PLAYING1_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + DOG_PLAYING1_ICON["x"], oy + DOG_PLAYING1_ICON["y"],
            DOG_PLAYING1_ICON["w"], DOG_PLAYING1_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: dog_family_playing1: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted dog design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Dog family, scene 4 of 4 (final) -- puppies playing, more energetic:
# a low crouch/pounce, a puppy rolled onto its back, and one mid-leap. No
# adult dog. Companion to duck_family_playing2 (D09) at the more energetic
# end, matching dog_family_playing1 (D12) as the calmer scene. See
# dog_family_1's module comment for the full set concept.
# ---------------------------------------------------------------------------
DOG_PLAYING2_ICON_MASTER = "assets/icons/dog_family_playing2_icon.png"

# Same box convention as the rest of the animal-family scenes. Extracted
# aspect ~2.37:1 w:h -- width binds within the shared box, renders
# ~120mm wide x ~50.6mm tall.
DOG_PLAYING2_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_dog_family_playing2(c, ox, oy, order):
    """23. Dog family, scene 4 of 4 (final) -- three puppies playing: a
    low crouch/pounce, one rolled onto its back, one mid-leap. More
    energetic than dog_family_playing1 (style 22), same pairing as
    duck_family_playing1/playing2 (D08/D09). No adult dog. Same layout
    convention and placement constants as the rest of the animal-family
    set. LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid black, not
    accent-recolourable, flat silhouette (no fur texture)."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(DOG_PLAYING2_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + DOG_PLAYING2_ICON["x"], oy + DOG_PLAYING2_ICON["y"],
            DOG_PLAYING2_ICON["w"], DOG_PLAYING2_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: dog_family_playing2: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted dog design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Cat family, scene 1 of 4 (adult cat + kitten, walking together) -- third
# animal-family line after ducks (D06-D09) and dogs (D10-D13), same
# underlying concept. Like dogs, cats have no reliable visual dad/mum cue
# in silhouette, so this set also skips gendering (per the precedent set
# for dogs): 2 adult+kitten scenes (differentiated by composition) + 2
# kittens-playing scenes. FLAT SILHOUETTE, no fur texture -- same
# proven-safe default as the dog set, pending the duck texture test
# (Animal-Family-Texture-Test-Plan.md).
# ---------------------------------------------------------------------------
CAT_FAMILY_1_ICON_MASTER = "assets/icons/cat_family_1_icon.png"

# Same box convention as the rest of the animal-family scenes. Extracted
# aspect ~1.88:1 w:h -- height binds within the shared box, renders
# ~58mm tall x ~109mm wide, comfortably inside the 120mm box width.
CAT_FAMILY_1_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_cat_family_1(c, ox, oy, order):
    """24. Cat family, scene 1 of 4 -- adult cat with a kitten trailing
    behind, both walking, tails naturally curved. No gendering, same
    layout convention and placement constants as the duck/dog family
    sets (styles 16-23). LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is
    solid black, not accent-recolourable, flat silhouette (no fur
    texture)."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(CAT_FAMILY_1_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + CAT_FAMILY_1_ICON["x"], oy + CAT_FAMILY_1_ICON["y"],
            CAT_FAMILY_1_ICON["w"], CAT_FAMILY_1_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: cat_family_1: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted cat design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Cat family, scene 2 of 4 (adult cat + kitten, close beside) -- see
# cat_family_1's module comment for the full set concept (no gendering,
# flat silhouette). Differentiated from scene 1 by composition (kitten
# tucked close beside the adult, not trailing at a distance) rather than
# implied parentage, same pattern as the dog set's scene 1/2 split.
# ---------------------------------------------------------------------------
CAT_FAMILY_2_ICON_MASTER = "assets/icons/cat_family_2_icon.png"

# Same box convention as the rest of the animal-family scenes. Extracted
# aspect ~1.63:1 w:h -- height binds within the shared box, renders
# ~58mm tall x ~94.6mm wide, comfortably inside the 120mm box width.
CAT_FAMILY_2_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_cat_family_2(c, ox, oy, order):
    """25. Cat family, scene 2 of 4 -- adult cat with a kitten close
    beside it (not trailing, unlike scene 1). No gendering, same layout
    convention and placement constants as the rest of the animal-family
    set. LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid black, not
    accent-recolourable, flat silhouette (no fur texture, no whiskers --
    kept consistent with scene 1 rather than the whiskered alternative
    generated in the same batch)."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(CAT_FAMILY_2_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + CAT_FAMILY_2_ICON["x"], oy + CAT_FAMILY_2_ICON["y"],
            CAT_FAMILY_2_ICON["w"], CAT_FAMILY_2_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: cat_family_2: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted cat design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Cat family, scene 3 of 4 (kittens playing, calmer -- gentle nuzzle, no
# adult) -- see cat_family_1's module comment for the full set concept.
# Companion to duck_family_playing1 (D08) and dog_family_playing1 (D12):
# same "calmer" energy level within its own animal-family set.
#
# NOTE -- genuine style departures from the rest of the set, both flagged
# to the user at selection time and kept anyway (explicit choice):
# 1. REAR/THREE-QUARTER VIEW, not the strict side profile every other
#    design in this file (and every idea-board reference) uses. This is
#    the first composition break in the whole catalogue. Several
#    generation attempts only produced this angle for the "kittens
#    nuzzling" pose; a side-on version was not achieved.
# 2. Flat fill, no fur texture -- this one IS consistent with D14/D15.
# Do not treat this rear-view angle as the new default for future
# animal-family "calm playing" scenes -- it was accepted here as the best
# of a limited batch, not chosen as a style direction.
# ---------------------------------------------------------------------------
CAT_PLAYING1_ICON_MASTER = "assets/icons/cat_family_playing1_icon.png"

# Same box convention as the rest of the animal-family scenes. Extracted
# aspect ~1.97:1 w:h -- height binds within the shared box, renders
# ~58mm tall x ~114.4mm wide, comfortably inside the 120mm box width.
CAT_PLAYING1_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_cat_family_playing1(c, ox, oy, order):
    """26. Cat family, scene 3 of 4 -- two kittens nuzzling gently, no
    adult cat. REAR/THREE-QUARTER VIEW, not side profile -- a deliberate
    exception to this file's usual convention, see
    CAT_PLAYING1_ICON_MASTER comment above for why. Flat silhouette (no
    fur texture), consistent with cat_family_1/2. Same layout convention
    and placement constants as the rest of the animal-family set.
    LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid black, not
    accent-recolourable."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(CAT_PLAYING1_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + CAT_PLAYING1_ICON["x"], oy + CAT_PLAYING1_ICON["y"],
            CAT_PLAYING1_ICON["w"], CAT_PLAYING1_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: cat_family_playing1: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted cat design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# Cat family, scene 4 of 4 (final) -- kittens playing, more energetic:
# one pouncing low, two batting paws mid-leap. No adult cat. Companion to
# duck_family_playing2 (D09) and dog_family_playing2 (D13) at the more
# energetic end, matching cat_family_playing1 (D16) as the calmer scene
# -- though note D16 is a rear-view exception while this one is genuine
# side profile throughout (the side-profile prompt fix that failed for
# D16's batch worked cleanly here). See cat_family_1's module comment for
# the full set concept.
# ---------------------------------------------------------------------------
CAT_PLAYING2_ICON_MASTER = "assets/icons/cat_family_playing2_icon.png"

# Same box convention as the rest of the animal-family scenes. Extracted
# aspect ~2.49:1 w:h -- width binds within the shared box, renders
# ~120mm wide x ~48.2mm tall.
CAT_PLAYING2_ICON = dict(x=10 * mm, y=34 * mm, w=120 * mm, h=58 * mm)


def _style_cat_family_playing2(c, ox, oy, order):
    """27. Cat family, scene 4 of 4 (final) -- three kittens playing: one
    pouncing low, two batting paws mid-leap. More energetic than
    cat_family_playing1 (style 26), same pairing as
    duck_family_playing1/2 and dog_family_playing1/2. No adult cat.
    Genuine side profile (unlike style 26's rear-view exception). Same
    layout convention and placement constants as the rest of the
    animal-family set. LANDSCAPE 140x100mm (P02_CARD_W/H). Icon is solid
    black, not accent-recolourable, flat silhouette (no fur texture)."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + P02_CARD_W / 2

    icon_path = _asset_path(CAT_PLAYING2_ICON_MASTER)
    if os.path.exists(icon_path):
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + CAT_PLAYING2_ICON["x"], oy + CAT_PLAYING2_ICON["y"],
            CAT_PLAYING2_ICON["w"], CAT_PLAYING2_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: cat_family_playing2: master icon not found at "
            f"{icon_path!r} -- rendering plain paw fallback instead of "
            f"the extracted cat design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 30 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)


# ---------------------------------------------------------------------------
# P09a -- '64 Oakley Close' borderless minimal: bold number, thin underline
# rule sized to match the street name's own width, street name in caps
# below it. No icon, no border -- deliberately, per the idea board entry
# (this is the borderless counterpart to style #2 "minimal", which is the
# same composition plus a border). DRAFT, not yet a catalogued product --
# no STYLE_PRODUCT_ID entry until it ships.
#
# Constants measured via the icon-silhouette-extraction skill's text-only
# workflow (see SKILL.md's "Text-only layouts" section, added for this
# design) against the Midjourney mockup render (chat upload,
# "u3898592314_...73b8a125-1702-415b-a12a-2d4c194766a5_0.png", 1312x928px).
# No icon/border ink to anchor a scale against, so the white card
# rectangle itself was isolated from the grey backdrop by luminance
# (card ~255, backdrop ~245-249) -- bbox x=143-1167 (1024px), y=104-822
# (718px) -- and ASSUMED to represent the true 140x100mm landscape card
# edges (per user's explicit choice of orientation for this mockup). Flag
# this assumption if a future re-render changes the card's position in
# frame, same as every other icon-border-bbox assumption in this file.
#
# Px/mm scale: px_mm_x=7.3143 (1024/140), px_mm_y=7.18 (718/100) -- 1.9%
# axis mismatch, same small gap seen elsewhere in this file. Each axis
# uses its own factor below, not one blended constant.
#
# Component check (label_components): 14 components, no crop-clipping
# warning. 2 largest (digits '3'/'6', combined bbox x=520-789 y=240-419),
# 1 thin full-width rule (label 3, x=263-1048 y=461-466 -- only 5px tall),
# and exactly 11 further components (x=263-1048 y=524-609) matching
# G-R-O-V-E-S-T-R-E-E-T letter-for-letter -- confirms nothing merged.
#
# Notable composition fact this measurement surfaced: the underline's
# width (785px) is NOT a fixed decorative length -- it exactly matches
# GROVE STREET's own rendered width (same x=263-1048 span). Reproduced
# below as a DYNAMIC underline (stringWidth of the actual street text at
# its fitted size), not a fixed mm constant, so it still matches
# correctly for street names of different lengths/widths than the
# mockup's placeholder.
#
# Underline thickness: measured ~0.70mm (5px/7.18), but bitmap
# anti-aliasing at this render resolution inflates apparent stroke width
# versus a true vector line -- treated as a judgment call, not a literal
# constant. Using 1.0pt (~0.35mm) here, a similar weight to the existing
# "minimal" style's own 0.6pt accent line, just slightly bolder to read
# clearly at the wider ~100mm+ span this rule spans. Revisit against a
# real test print if it looks too thin/thick.
#
# Font: mockup uses a bold geometric sans (not a Helvetica look-alike
# exactly) -- Helvetica-Bold used here as the nearest built-in reportlab
# font, same substitution reasoning as every other style in this file
# that doesn't have a licensed matching font.
#
# NUMBER_MAX_WIDTH/STREET_MAX_WIDTH: no bounding element (no icon, no
# border) to measure an exact available gap against, unlike P02/P27/P47's
# hollow-width measurements -- these are generous ceilings sized off the
# card width with margins, auto-fit shrinks as needed, same pattern as
# every other MAX_WIDTH constant in this file.
# ---------------------------------------------------------------------------
P09A_NUMBER_CENTER_Y = 68.5933 * mm  # RL y, from digits' own measured centroid
P09A_UNDERLINE_CENTER_Y = 49.9304 * mm  # RL y -- almost exactly the card's vertical midpoint
P09A_STREET_CENTER_Y = 35.5850 * mm  # RL y, from street-text's own measured centroid

P09A_NUMBER_MAX_WIDTH = 110 * mm  # generous ceiling (card width minus 2x15mm margin)
P09A_NUMBER_MAX_SIZE = 140  # pt -- auto-fit shrinks as needed
P09A_NUMBER_MIN_SIZE = 20

P09A_STREET_MAX_WIDTH = 96.5867 * mm  # true measured street-text width (107.32mm), 10% safety margin
P09A_STREET_MAX_SIZE = 66  # pt
P09A_STREET_MIN_SIZE = 16

P09A_UNDERLINE_WEIGHT = 1.0  # pt -- judgment call, see note above (not a literal px measurement)


def _style_p09a_borderless(c, ox, oy, order):
    """34. P09a -- borderless minimal: bold house number, thin underline
    rule sized to the street name's own width, street name in caps below
    it. No icon, no border. LANDSCAPE (140x100mm, reuses P02_CARD_W/H) --
    this specific orientation was an explicit user choice for the mockup
    (source pinned size was ~150x110mm, itself ambiguous vs. this design's
    OTHER intended build path as a borderless variant of #2 "minimal",
    which is the shared PORTRAIT CARD_W/CARD_H) -- if this ends up built
    as that portrait variant instead, re-derive the P09A_* constants
    against CARD_W/CARD_H rather than reusing these landscape mm values
    directly (the underlying fractions of card width/height should carry
    over; the absolute mm won't). DRAFT status -- not yet in
    bin_sticker_products_gallery.html/.md, no STYLE_PRODUCT_ID entry."""
    cx = ox + P02_CARD_W / 2

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold",
                                  P09A_NUMBER_MAX_SIZE, P09A_NUMBER_MIN_SIZE, P09A_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Helvetica-Bold", number_size)
    number_baseline = P09A_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(INK))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Helvetica-Bold",
                                  P09A_STREET_MAX_SIZE, P09A_STREET_MIN_SIZE, P09A_STREET_MAX_WIDTH)
    asc2, desc2 = getAscentDescent("Helvetica-Bold", street_size)
    street_baseline = P09A_STREET_CENTER_Y - (asc2 + desc2) / 2 * (25.4 / 72) * mm
    c.setFont("Helvetica-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    # Underline width matches the ACTUAL rendered street text width, not a
    # fixed constant -- reproduces the mockup's own composition fact that
    # the rule spans exactly as wide as the street name, whatever that
    # name's length turns out to be.
    street_width = stringWidth(street_text, "Helvetica-Bold", street_size)
    c.setStrokeColor(HexColor(INK))
    c.setLineWidth(P09A_UNDERLINE_WEIGHT)
    underline_y = oy + P09A_UNDERLINE_CENTER_Y
    c.line(cx - street_width / 2, underline_y, cx + street_width / 2, underline_y)

    # Deliberately NO _draw_border call -- this design is borderless per
    # the idea board entry, unlike every other style in this file.


# ---------------------------------------------------------------------------
# P21 -- EDSG 'Design 5': diagonal trail of 5 dog paw prints (largest at
# bottom-left, shrinking toward top-right) beside a large house number,
# street name below it. Real Midjourney-sourced artwork (not the plain
# vector single-paw of style 10), extracted and measured via
# icon-silhouette-extraction -- see the P21_* constants below for the
# full derivation. Source: chosen from 6 renders across 2 Midjourney
# seed batches (chat upload "...daaf5f81-..._2.png", 1312x928px,
# landscape --ar 7:5), user-selected for cleanest linework/no drop-shadow.
#
# LANDSCAPE 140x100mm (reuses P02_CARD_W/H) -- explicit user choice for
# this build, same off-spec-vs-idea-board caveat as p25/p27/p09a: P21's
# actual idea-board entry has fits_spec=Yes for the standard 100x140mm
# PORTRAIT card (matching how the design was originally pinned/sold by
# EDSG). This is a deliberate landscape variant, not "P21 built to spec".
# CATALOGUED as D25 -- see STYLE_PRODUCT_ID and
# bin_sticker_products_gallery_data.md. Proof approved after fixing the
# PAD (see P21_PAD below): the default 2mm PAD measured only ~1.7-1.96mm
# real edge clearance on products_io.py's render_proof_thumbnail check,
# same failure D01/D02 shipped with -- fixed before cataloguing, not
# after.
#
# NOT a nested-hollow design (unlike P02/P27/P47's house icons) -- the
# paw trail and the number/street sit side-by-side on the card, same
# layout family as style 10 (paw) and the duck/dog/cat family scenes,
# so there's no interior hollow width/curve to measure. What WAS
# measured: the real baked-in "76"/"North Avenue" placeholder text's own
# centroid position before erasing it -- ground truth for where a
# human/AI actually placed it, same reasoning as every other placement
# constant in this file.
#
# Px/mm scale: px_mm_x=9.3714 (1312/140), px_mm_y=9.28 (928/100) -- same
# small axis mismatch seen elsewhere in this file; each axis uses its
# own factor throughout, not one blended constant.
#
# Component check (label_components): 39 components total, no
# crop-clipping warning. 25 kept (5 paw prints x [1 pad + 4 toes] each),
# 14 erased (2 digit components '7'/'6', 11 letter components spelling
# N-o-r-t-h-A-v-e-n-u-e letter-for-letter, and the card's own outer
# border). su.verify() confirms exactly 25 components survived the crop
# -- nothing merged, nothing left behind. All 25 kept components measured
# near-uniform luminance (2-10 out of 255) -- no tonal hierarchy to
# preserve, single flat fill colour throughout.
#
# RESOLVED LIMITATION (checked against a real render, not just the
# constants): P21_ICON's box is a single rectangle bounding a DIAGONAL
# shape, so it's conservative at the top (smallest paw reaches furthest
# right) but loose at the bottom (where the street name actually sits).
# Because of this, P21_STREET_MAX_WIDTH below is taken directly from the
# source mockup's own measured text width (same reasoning as
# P27_STREET_MAX_WIDTH: "the street band isn't bounded by the icon at
# all"), NOT clamped against P21_ICON's bounding box. su.check_edge_
# clearance() referenced by the skill doesn't actually exist in
# silhouette_utils.py (same gap already hit elsewhere in this project) --
# checked by hand instead against a real render (Helvetica-Bold,
# "76"/"North Avenue"): real ink gap between the paw pad and the street
# text at the street's own row is ~20mm, and all four design-to-border
# clearances (icon left 5.47mm, icon bottom 5.76mm, icon top 4.47mm,
# number/street right 12-14mm) clear the project's 3mm minimum
# comfortably. Confirmed safe, not just assumed.
# ---------------------------------------------------------------------------
P21_ICON_MASTER = "assets/icons/p21_paw_trail_icon.png"

# Icon placement box (x, y, w, h), same convention as P02_ICON/P27_ICON:
# x/y are the distance from the card's LEFT and BOTTOM edges to the drawn
# image box's left/bottom edges (reportlab y-up). w/h are the box to draw
# the (already-cropped, ~3%-padded) icon PNG into.
P21_ICON = dict(x=7.4695 * mm, y=7.7586 * mm, w=57.8354 * mm, h=85.7759 * mm)

# Number/street sit beside the icon, NOT centred on the card -- explicit
# X centres below, unlike every card-centred style in this file.
P21_NUMBER_CENTER_X = 99.2478 * mm  # RL x, from erased "76"'s own measured centroid
P21_NUMBER_CENTER_Y = 62.3159 * mm  # RL y, from erased "76"'s own measured centroid
# Available half-width is limited by the SHORTER of (card right edge) and
# (icon's right edge, 65.3049mm) from the centre above -- the icon side
# binds (33.95mm vs. 38.75mm to the card edge), so max width uses that
# with a 10% safety margin: 33.95mm * 2 * 0.9.
P21_NUMBER_MAX_WIDTH = 61.11 * mm
P21_NUMBER_MAX_SIZE = 127  # pt -- measured cap-height implies ~127.1pt at Helvetica-Bold
P21_NUMBER_MIN_SIZE = 20

P21_STREET_CENTER_X = 87.2456 * mm  # RL x, from erased "North Avenue"'s own measured centroid
P21_STREET_CENTER_Y = 24.7577 * mm  # RL y, from erased "North Avenue"'s own measured centroid
# See KNOWN LIMITATION note above -- this is the mockup's own measured
# text width with a 10% safety margin, not clamped against P21_ICON's
# (overly conservative at this height) bounding box.
P21_STREET_MAX_WIDTH = 76.9253 * mm
P21_STREET_MAX_SIZE = 34  # pt -- measured cap-height implies ~30.5pt; small ceiling above that
P21_STREET_MIN_SIZE = 16

# Cutting-tolerance margin, larger than the shared 2mm PAD -- same fix
# already applied to P27_PAD (3mm) and DUCK_FATHER_PAD (3.4mm) after
# D01/D02 shipped with only ~1.2-1.9mm of real clearance. The default
# 2mm PAD measured only ~1.7-1.96mm real ink-to-card-edge clearance on
# a real render (products_io.py's render_proof_thumbnail check) --
# below the 3mm minimum. 3.4mm matches DUCK_FATHER_PAD's precedent and
# was re-verified against a real render below.
P21_PAD = 3.4 * mm


def _p21_icon_path(accent_key):
    """Same recolour-and-cache pattern as _p02_icon_path/_p27_icon_path --
    generates the accent-coloured paw-trail icon from the master
    silhouette on first use, caches to disk. Returns None if the master
    art isn't present."""
    master = _asset_path(P21_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _asset_path(f"assets/icons/p21_paw_trail_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p21_paw_trail(c, ox, oy, order):
    """35. P21 -- diagonal trail of 5 paw prints (illustrated, extracted
    via icon-silhouette-extraction -- not the plain vector single-paw of
    style 10), house number and street name printed beside it rather
    than nested inside (no interior hollow on this icon). LANDSCAPE
    (140x100mm) -- see P21_* constants above for the off-spec-vs-idea-
    board caveat and the RESOLVED street-width limitation. Catalogued as
    D25 -- see STYLE_PRODUCT_ID and bin_sticker_products_gallery_data.md."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)

    icon_path = _p21_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P21_ICON["x"], oy + P21_ICON["y"], P21_ICON["w"], P21_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback if the master art is missing -- there's no
        # vector equivalent of a 5-print diagonal trail, so this falls
        # back to the existing single-paw vector icon (style 10's
        # draw_paw_icon) rather than attempting to fake a trail, same
        # "substitution, not a lesser version" reasoning as every other
        # fallback in this file.
        print(
            f"WARNING: p21_paw_trail: master icon not found at "
            f"{_asset_path(P21_ICON_MASTER)!r} -- rendering plain single-"
            f"paw fallback instead of the extracted P21 trail design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, ox + P21_ICON["x"] + P21_ICON["w"] / 2, oy + P21_ICON["y"] + P21_ICON["h"] / 2,
                   19 * mm, accent_hex, "paw", draw_paw_icon)

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold",
                                  P21_NUMBER_MAX_SIZE, P21_NUMBER_MIN_SIZE, P21_NUMBER_MAX_WIDTH)
    asc, desc = getAscentDescent("Helvetica-Bold", number_size)
    number_baseline = P21_NUMBER_CENTER_Y - (asc + desc) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(INK))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(ox + P21_NUMBER_CENTER_X, oy + number_baseline, order["house_number"])

    street_text = order["street_name"]
    street_size = _fit_font_size(street_text, "Helvetica-Bold",
                                  P21_STREET_MAX_SIZE, P21_STREET_MIN_SIZE, P21_STREET_MAX_WIDTH)
    asc2, desc2 = getAscentDescent("Helvetica-Bold", street_size)
    street_baseline = P21_STREET_CENTER_Y - (asc2 + desc2) / 2 * (25.4 / 72) * mm
    c.setFont("Helvetica-Bold", street_size)
    c.drawCentredString(ox + P21_STREET_CENTER_X, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=P21_PAD)


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
    "p25_landscape_flourish": _style_p25_landscape_flourish,
    "p25b_landscape_flourish": _style_p25b_landscape_flourish,
    "p27_landscape_house": _style_p27_landscape_house,
    "p47_house": _style_p47_house,
    "p06_wreath": _style_p06_wreath,
    "p06_wreath_numbers": _style_p06_wreath_numbers,
    "p30_laurel_numbers": _style_p30_laurel_numbers,
    "p15_heart_wreath": _style_p15_heart_wreath,
    "p28_arrow_wreath": _style_p28_arrow_wreath,
    "p31_olive_wreath": _style_p31_olive_wreath,
    "duck_family_father": _style_duck_family_father,
    "duck_family_mother": _style_duck_family_mother,
    "duck_family_playing1": _style_duck_family_playing1,
    "duck_family_playing2": _style_duck_family_playing2,
    "dog_family_1": _style_dog_family_1,
    "dog_family_2": _style_dog_family_2,
    "dog_family_playing1": _style_dog_family_playing1,
    "dog_family_playing2": _style_dog_family_playing2,
    "cat_family_1": _style_cat_family_1,
    "cat_family_2": _style_cat_family_2,
    "cat_family_playing1": _style_cat_family_playing1,
    "cat_family_playing2": _style_cat_family_playing2,
    "p09a_borderless": _style_p09a_borderless,
    "p21_paw_trail": _style_p21_paw_trail,
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
    "house_banner": "11. D01 — Cottage Bloom Banner (landscape)",
    "p25_landscape_flourish": "12. D02 — Regency Double Flourish (landscape)",
    "p25b_landscape_flourish": "13. D03 — Manor Frame Classic (landscape)",
    "p27_landscape_house": "14. D04 — Homestead Silhouette (landscape)",
    "p47_house": "15. P47 — House-outline + number, black-only (landscape)",
    "p06_wreath": "16. P06 — Floral vine wreath, number + curved street name (landscape)",
    "p06_wreath_numbers": "17. P06 numbers-only — Floral vine wreath, number only, no street (landscape)",
    "p30_laurel_numbers": "18. P30 laurel wreath — Open-top laurel leaves, number only, no street (landscape)",
    "p15_heart_wreath": "19. P15 heart-vine wreath — Number + flat street name (landscape)",
    "p28_arrow_wreath": "20. P28 arrow/fletching wreath — Number + flat street name (landscape)",
    "p31_olive_wreath": "21. P31 olive branch wreath — Number + flat street name (landscape, experimental)",
    "duck_family_father": "22. Duck Family, Scene 1 — Father Duck & Duckling (landscape)",
    "duck_family_mother": "23. Duck Family, Scene 2 — Mother Duck & Duckling (landscape)",
    "duck_family_playing1": "24. Duck Family, Scene 3 — Ducklings Playing (landscape)",
    "duck_family_playing2": "25. Duck Family, Scene 4 — Ducklings Playing, Energetic (landscape)",
    "dog_family_1": "26. Dog Family, Scene 1 — Adult Dog & Puppy, Walking (landscape)",
    "dog_family_2": "27. Dog Family, Scene 2 — Adult Dog & Puppy, Close Beside (landscape)",
    "dog_family_playing1": "28. Dog Family, Scene 3 — Puppies Playing (landscape)",
    "dog_family_playing2": "29. Dog Family, Scene 4 — Puppies Playing, Energetic (landscape)",
    "cat_family_1": "30. Cat Family, Scene 1 — Adult Cat & Kitten, Walking (landscape)",
    "cat_family_2": "31. Cat Family, Scene 2 — Adult Cat & Kitten, Close Beside (landscape)",
    "cat_family_playing1": "32. Cat Family, Scene 3 — Kittens Playing (landscape, rear view)",
    "cat_family_playing2": "33. Cat Family, Scene 4 — Kittens Playing, Energetic (landscape)",
    "p09a_borderless": "34. P09a — Borderless minimal, number + underline + street (landscape, DRAFT)",
    "p21_paw_trail": "35. P21 — Paw trail (5 paws), number + street beside (landscape)",
}

# Cross-reference from a style key to its internal product ID in
# bin_sticker_products_gallery.html / bin_sticker_products_gallery_data.md
# -- only styles that have actually shipped as a catalogued product get
# an entry here (currently all 5 of the landscape styles; none of
# styles 1-10 have been added to the products gallery yet). This is the
# SAME direction of lookup products_io.py's next_product_id()/
# insert_product_card() expect an entry dict to already know when
# adding a new one -- if you build another finished design, add it here
# too, or STYLE_LABELS and the products gallery will drift apart the
# same way the idea board's HTML/.md drifted before insert_md_fields
# existed (and the same way THIS dict and the products gallery had
# already drifted by the time D05/p47_house was added -- the gallery's
# live HTML had D04 registered but the only .md companion available
# didn't, and had to be backfilled from the HTML's own content before
# D05 could be added on top of a consistent pair).
STYLE_PRODUCT_ID = {
    "house_banner": "D01",
    "p25_landscape_flourish": "D02",
    "p25b_landscape_flourish": "D03",
    "p27_landscape_house": "D04",
    "p47_house": "D05",
    "duck_family_father": "D06",
    "duck_family_mother": "D07",
    "duck_family_playing1": "D08",
    "duck_family_playing2": "D09",
    "dog_family_1": "D10",
    "dog_family_2": "D11",
    "dog_family_playing1": "D12",
    "dog_family_playing2": "D13",
    "cat_family_1": "D14",
    "cat_family_2": "D15",
    "cat_family_playing1": "D16",
    "cat_family_playing2": "D17",
    "p06_wreath": "D18",
    "p06_wreath_numbers": "D19",
    "p30_laurel_numbers": "D20",
    "p15_heart_wreath": "D21",
    "p28_arrow_wreath": "D22",
    "p31_olive_wreath": "D23",
    "p09a_borderless": "D24",
    "p21_paw_trail": "D25",
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
STYLE_CARD_SIZE["p25_landscape_flourish"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p25b_landscape_flourish"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p27_landscape_house"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p47_house"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p06_wreath"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p06_wreath_numbers"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p30_laurel_numbers"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p15_heart_wreath"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p28_arrow_wreath"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p31_olive_wreath"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["duck_family_father"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["duck_family_mother"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["duck_family_playing1"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["duck_family_playing2"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["dog_family_1"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["dog_family_2"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["dog_family_playing1"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["dog_family_playing2"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["cat_family_1"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["cat_family_2"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["cat_family_playing1"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["cat_family_playing2"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p09a_borderless"] = (P02_CARD_W, P02_CARD_H)
STYLE_CARD_SIZE["p21_paw_trail"] = (P02_CARD_W, P02_CARD_H)


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
