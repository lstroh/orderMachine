"""
Bin sticker generator — MEDIUM (210x140mm) — Kerbside Craft Co.

PILOT, Sep 2026: 3 styles ported from bin_sticker_small.py (140x100mm) to
validate a repeatable scaling method before porting the rest of the
catalogue. Matches a real competitor's size exactly (House Number &
Address Decals shop, 4.7★/3.1k reviews — see Competitor Research), 2-per
A4 sheet, STACKED vertically (not the small size's 2x2 grid — this card
is the full physical A4 page width, so 2-per-sheet is 1 column x 2 rows).

Print margins confirmed via a real paper print test (Sep 2026, Epson
EcoTank ET-3950): card width (210mm) equals the full A4 page width, so
the left/right edges need BORDERLESS printing -- measured dead zone was
~2-4mm (asymmetric, likely paper-feed skew). Top/bottom use a normal,
non-borderless 8.5mm margin. See chat history, "Custom bin sticker sheet
layout specifications" follow-up, for the full test writeup.

SCALING METHODOLOGY (read this before porting another style):
Medium is 1.5x the width and 1.4x the height of Small (210/140=1.5,
140/100=1.4) -- NOT a uniform scale, since the two sizes have slightly
different aspect ratios (Small 1.4:1, Medium 1.5:1). Every constant
below was scaled axis-consistently from its Small equivalent:
  - Horizontal quantities (X positions, MAX_WIDTH values) x1.5
  - Vertical quantities (Y positions) x1.4
  - Font sizes (MAX_SIZE/MIN_SIZE) x1.4 -- treated as a vertical
    quantity (font size fundamentally relates to cap-height)
  - A RASTER icon's own w/h (not its position) is scaled UNIFORMLY by
    ONE factor -- the axis the icon's bounding box is actually bound by
    on Small's card (computed per-icon: whichever of w/card_w or
    h/card_h is the larger fraction). Never stretched non-uniformly --
    stretching the actual artwork w x1.5 / h x1.4 independently would
    visibly distort it (circular paw pads becoming ovoid, etc.). The
    icon's ORIGIN (x, y) still uses the normal per-axis position
    scaling, since a position isn't a shape.
  - MAX_WIDTH values that measure a HOLLOW ICON'S OWN interior gap
    (number/street nested inside a wreath/house outline) scale by that
    icon's dominant axis (same factor as the icon's own w/h above) --
    the gap is literally part of the icon's geometry being uniformly
    scaled, not an independent card-width measurement. MAX_WIDTH values
    that are genuinely independent of any icon (measured text bands,
    "distance to the card edge") use the plain width-axis (1.5) instead.
    A few styles (P21) have icon and text side-by-side rather than
    nested -- there, the true constraint is "distance to whichever edge
    is closer," which can't be scaled by a single ratio at all (the
    icon's position and its own size use different axes), so those were
    recomputed geometrically from the already-scaled icon box rather
    than scaling the old final mm value.
  - PAD-type cutting-tolerance margins (P21_PAD) are NOT scaled -- these
    represent a physical cutting/lamination tolerance, not a
    compositional proportion, so the same absolute mm value applies
    regardless of card size.

THIS IS A FIRST-PASS DERIVATION, NOT YET PRINT-VALIDATED. Every
Small-size constant this was derived from came from real pixel
measurements against a specific mockup image at Small's specific aspect
ratio; scaling those numbers preserves proportion but does NOT re-verify
that the result actually looks right at the new size (e.g. whether P21's
paw trail, now occupying more vertical space, still leaves comfortable
clearance to the number/street text). Treat every constant here the same
way this project already treats a first pixel-measurement pass elsewhere
in the codebase: print it, check it, expect to revise the *_CENTER_Y/
*_MAX_WIDTH/*_MAX_SIZE values, and update this comment with real
print-test results once done (same convention as every Small-size style's
own history of print-test corrections).
"""

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase.pdfmetrics import stringWidth, getAscentDescent
import os

import bin_sticker_core as core
from bin_sticker_core import (
    asset_path as _asset_path,
    cached_icon_path as _cached_icon_path,
    recolour_silhouette,
    resolve_accent as _resolve_accent,
    draw_icon as _draw_icon,
    draw_base as _draw_base,
    draw_border as _draw_border,
    draw_flower_icon,
    draw_house_icon,
    draw_paw_icon,
    draw_center_flourish,
    draw_curved_text as _draw_curved_text,
    fit_font_size as _fit_font_size,
    TIMES_BOLD_CAP_HEIGHT_RATIO,
    HELVETICA_BOLD_CAP_HEIGHT_RATIO,
    INK,
    PAD,
)

CARD_W = 210 * mm
CARD_H = 140 * mm

# Alias so every verbatim-copied style body below (originally written
# against Small's P02_CARD_W/H) resolves to THIS file's actual card size
# without needing any per-line rewrite -- P02 was the first landscape
# style in the original single-file design, and every subsequent style
# just reused its card-size constant by convention, portrait styles
# included their own CARD_W/H. That convention carries over here.
P02_CARD_W = CARD_W
P02_CARD_H = CARD_H

# ---------------------------------------------------------------------------
# P09a -- borderless minimal. Pure text + one underline rule, no icon, no
# raster asset -- the simplest of the 3 pilot styles to scale, since
# there's no artwork aspect ratio to protect. Every constant here is the
# Small-size value multiplied by the matching axis ratio (see module
# docstring): Y-positions x1.4, MAX_WIDTH x1.5, font sizes x1.4.
# ---------------------------------------------------------------------------
P09A_NUMBER_CENTER_Y = 96.0306 * mm      # 68.5933 x1.4
P09A_UNDERLINE_CENTER_Y = 69.9026 * mm   # 49.9304 x1.4
P09A_STREET_CENTER_Y = 49.8190 * mm      # 35.5850 x1.4

P09A_NUMBER_MAX_WIDTH = 165.0 * mm       # 110 x1.5
P09A_NUMBER_MAX_SIZE = 196               # pt -- 140 x1.4
P09A_NUMBER_MIN_SIZE = 28                # pt -- 20 x1.4

P09A_STREET_MAX_WIDTH = 180.0 * mm       # 120 x1.5
P09A_STREET_MAX_SIZE = 92                # pt -- 66 x1.4 (92.4 rounded)
P09A_STREET_MIN_SIZE = 22                # pt -- 16 x1.4 (22.4 rounded)

P09A_UNDERLINE_WEIGHT = 1.4              # pt -- 1.0 x1.4, judgment call same as Small


def _style_p09a_borderless(c, ox, oy, order):
    """P09a -- MEDIUM (210x140mm). Bold house number, thin underline rule
    sized to the street name's own width, street name in caps below it.
    No icon, no border -- deliberately, same as the Small version this
    was scaled from. See module docstring for scaling methodology and
    the "not yet print-validated" caveat."""
    cx = ox + CARD_W / 2

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold",
                                  P09A_NUMBER_MAX_SIZE, P09A_NUMBER_MIN_SIZE, P09A_NUMBER_MAX_WIDTH)
    cap_height = number_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P09A_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(INK))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Helvetica-Bold",
                                  P09A_STREET_MAX_SIZE, P09A_STREET_MIN_SIZE, P09A_STREET_MAX_WIDTH)
    cap_height2 = street_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    street_baseline = P09A_STREET_CENTER_Y - cap_height2 / 2 * (25.4 / 72) * mm
    c.setFont("Helvetica-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    street_width = stringWidth(street_text, "Helvetica-Bold", street_size)
    c.setStrokeColor(HexColor(INK))
    c.setLineWidth(P09A_UNDERLINE_WEIGHT)
    underline_y = oy + P09A_UNDERLINE_CENTER_Y
    c.line(cx - street_width / 2, underline_y, cx + street_width / 2, underline_y)

    # Deliberately NO _draw_border call, same as the Small version.


# ---------------------------------------------------------------------------
# P21 -- paw trail. Icon is a RASTER asset (5-paw diagonal trail), so its
# own w/h are scaled UNIFORMLY by the height ratio (1.4) rather than
# stretched -- the icon's bounding box height (85.7759mm) is the
# dominant constraint on the Small card (85.78/100 = 85.8% of card
# height), so scaling both w and h by the same height-ratio preserves
# BOTH the icon's own aspect ratio AND how much of the card's vertical
# space it fills (120.09/140 = 85.8%, identical proportion). The icon's
# origin (x, y) uses normal per-axis position scaling.
# ---------------------------------------------------------------------------
P21_ICON_MASTER = "assets/icons/p21_paw_trail_icon.png"
P21_ICON = dict(x=11.2043 * mm, y=10.8620 * mm, w=80.9696 * mm, h=120.0863 * mm)

P21_NUMBER_CENTER_X = 148.8717 * mm    # 99.2478 x1.5
P21_NUMBER_CENTER_Y = 87.2423 * mm     # 62.3159 x1.4
P21_NUMBER_MAX_WIDTH = 102.056 * mm    # CORRECTED (was 91.665mm x1.5) -- not a hollow
# gap, but a "distance to the nearest binding edge" measurement, since
# P21's icon sits BESIDE the text, not enclosing it (see module comment).
# Naively scaling the old final mm value by one axis ratio doesn't work
# here because the icon's position (x1.5) and its own size (x1.4,
# height-dominant) use DIFFERENT axes -- so the icon's right edge moves
# by neither ratio cleanly. Recomputed geometrically instead: icon right
# edge = P21_ICON["x"] + P21_ICON["w"] = 11.2043+80.9696 = 92.1739mm;
# number centre = P21_NUMBER_CENTER_X = 148.8717mm; distance to icon
# edge (56.6978mm) is still shorter than distance to the card's right
# edge (61.1283mm), same "icon side binds" conclusion as Small -- new
# ceiling = 2 x 56.6978 x 0.9 safety margin.
P21_NUMBER_MAX_SIZE = 178              # pt -- 127 x1.4 (177.8 rounded)
P21_NUMBER_MIN_SIZE = 28               # pt -- 20 x1.4

P21_STREET_CENTER_X = 148.8717 * mm    # matches NUMBER_CENTER_X, same as Small
P21_STREET_CENTER_Y = 34.6608 * mm     # 24.7577 x1.4
P21_STREET_MAX_WIDTH = 102.0 * mm      # 68 x1.5
P21_STREET_MAX_SIZE = 62               # pt -- 44 x1.4 (61.6 rounded)
P21_STREET_MIN_SIZE = 22               # pt -- 16 x1.4 (22.4 rounded)

# NOT scaled -- physical cutting/lamination tolerance, not a
# compositional proportion (same reasoning as the shared PAD default in
# bin_sticker_core.py, which is also a flat constant regardless of card
# size). Same 3.4mm value as the Small version.
P21_PAD = 3.4 * mm


def _p21_icon_path(accent_key):
    """Same recolour-and-cache pattern as every hollow/asset-based icon
    style. Returns None if the master art isn't present."""
    master = _asset_path(P21_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p21_paw_trail_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p21_paw_trail(c, ox, oy, order):
    """P21 -- MEDIUM (210x140mm). Diagonal trail of 5 paw prints beside a
    large house number, street name below it. Scaled from the Small
    (140x100mm) version -- see module docstring for methodology and the
    "not yet print-validated" caveat, and P21_ICON's comment above for
    why the icon is scaled uniformly rather than stretched."""
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
        # Graceful fallback -- same reasoning as the Small version: no
        # vector equivalent of a 5-print diagonal trail exists, so this
        # falls back to a single paw icon instead of faking a trail.
        print(
            f"WARNING: p21_paw_trail (medium): master icon not found at "
            f"{_asset_path(P21_ICON_MASTER)!r} -- rendering plain single-"
            f"paw fallback instead of the extracted P21 trail design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, ox + P21_ICON["x"] + P21_ICON["w"] / 2, oy + P21_ICON["y"] + P21_ICON["h"] / 2,
                   26.6 * mm, accent_hex, "paw", draw_paw_icon)  # 19mm x1.4

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold",
                                  P21_NUMBER_MAX_SIZE, P21_NUMBER_MIN_SIZE, P21_NUMBER_MAX_WIDTH)
    cap_height = number_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P21_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(ox + P21_NUMBER_CENTER_X, oy + number_baseline, order["house_number"])

    # street_text NOT .upper()'d -- same as Small, since P21 displays
    # street names in original case (can have real descenders), so this
    # uses ascent/descent centring, not the cap-height shortcut.
    street_text = order["street_name"]
    street_size = _fit_font_size(street_text, "Helvetica-Bold",
                                  P21_STREET_MAX_SIZE, P21_STREET_MIN_SIZE, P21_STREET_MAX_WIDTH)
    asc2, desc2 = getAscentDescent("Helvetica-Bold", street_size)
    street_baseline = P21_STREET_CENTER_Y - (asc2 + desc2) / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", street_size)
    c.drawCentredString(ox + P21_STREET_CENTER_X, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=CARD_W, h=CARD_H, pad=P21_PAD)


# ---------------------------------------------------------------------------
# P31 -- olive branch wreath. Same uniform-scale-the-icon reasoning as
# P21: the icon's bounding box height (95.3846mm) is the dominant
# constraint on the Small card (95.4% of card height), so w/h are scaled
# uniformly by the height ratio to preserve aspect ratio and vertical
# fill proportion (133.5384/140 = 95.4%, identical).
# ---------------------------------------------------------------------------
P31_OLIVE_ICON_MASTER = "assets/icons/p31_olive_icon.png"
P31_OLIVE_ICON = dict(x=36.8636 * mm, y=1.9881 * mm, w=127.6772 * mm, h=133.5384 * mm)

P31_OLIVE_NUMBER_CENTER_Y = 74.7417 * mm   # 53.3869 x1.4
P31_OLIVE_NUMBER_MAX_WIDTH = 63.6919 * mm  # CORRECTED (was 68.2413mm x1.5) --
# this IS a true hollow-interior-gap measurement (number sits INSIDE the
# wreath), so it must scale by the icon's OWN dominant axis (height,
# x1.4) since the gap is literally part of the icon's geometry being
# uniformly scaled -- not by the generic width-axis. (50.5491*0.90) x1.4.
P31_OLIVE_NUMBER_MAX_SIZE = 126            # pt -- 90 x1.4
P31_OLIVE_NUMBER_MIN_SIZE = 28             # pt -- 20 x1.4

P31_OLIVE_STREET_CENTER_Y = 49.7605 * mm   # 35.5432 x1.4
P31_OLIVE_STREET_MAX_WIDTH = 66.7737 * mm  # CORRECTED (was 71.5433mm x1.5) --
# same hollow-gap reasoning as NUMBER_MAX_WIDTH above -- street also sits
# inside the wreath, so it scales by the icon's own dominant axis (height,
# x1.4), not the generic width-axis. (52.9950*0.90) x1.4.
P31_OLIVE_STREET_MAX_SIZE = 31             # pt -- 22 x1.4 (30.8 rounded)
P31_OLIVE_STREET_MIN_SIZE = 11             # pt -- 8 x1.4 (11.2 rounded)


def _p31_olive_icon_path(accent_key):
    """Same recolour-and-cache pattern as every hollow-icon style.
    Returns None if the master art isn't present (caller falls back to
    the plain vector floral icon)."""
    master = _asset_path(P31_OLIVE_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p31_olive_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p31_olive_wreath(c, ox, oy, order):
    """P31 olive branch wreath -- MEDIUM (210x140mm). House number nested
    in the upper interior, street name in flat text below it. Scaled
    from the Small (140x100mm) version -- see module docstring for
    methodology and the "not yet print-validated" caveat."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    cx = ox + CARD_W / 2

    icon_path = _p31_olive_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P31_OLIVE_ICON["x"], oy + P31_OLIVE_ICON["y"],
            P31_OLIVE_ICON["w"], P31_OLIVE_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        # Graceful fallback -- plain vector floral icon, flat text. Font
        # sizes and icon size scaled x1.4 from Small's fallback (44pt,
        # 14pt, 12mm); the 0.52/0.32 vertical fractions need no scaling,
        # since a fraction of card height is already size-invariant.
        print(
            f"WARNING: p31_olive_wreath (medium): master icon not found at "
            f"{_asset_path(P31_OLIVE_ICON_MASTER)!r} -- rendering plain "
            f"floral fallback instead of the extracted olive wreath "
            f"design for house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + CARD_H - PAD - 14 * mm, 16.8 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 62)
        c.drawCentredString(cx, oy + CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 20)
        c.drawCentredString(cx, oy + CARD_H * 0.32, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=CARD_W, h=CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P31_OLIVE_NUMBER_MAX_SIZE, P31_OLIVE_NUMBER_MIN_SIZE,
                                  P31_OLIVE_NUMBER_MAX_WIDTH)
    cap_height = number_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P31_OLIVE_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold",
                                  P31_OLIVE_STREET_MAX_SIZE, P31_OLIVE_STREET_MIN_SIZE,
                                  P31_OLIVE_STREET_MAX_WIDTH)
    cap_height = street_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    street_baseline = P31_OLIVE_STREET_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=CARD_W, h=CARD_H)


# ---------------------------------------------------------------------------
# Registry -- pilot subset only (3 of Small's 25 styles). Add more here
# as they're scaled and print-validated, following the same pattern.
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# P25b helper icon-caching pattern reused verbatim from Small (no card-size
# dependency in the fraction-based logic itself -- see P25/P25b constants
# block below for how the actual scaling was applied).
# ---------------------------------------------------------------------------
_P25_SRC_H_PX = 928  # source PNG height in px -- same source image at any card size

def _p25_frac(row_px):
    """Convert a measured source-image row (0 = top) to a fraction of
    P02_CARD_H measured from the card's bottom -- same convention as the
    oy + CARD_H * frac pattern used by every other style in this file."""
    return 1 - row_px / _P25_SRC_H_PX


# ---------------------------------------------------------------------------
# P02 -- house + flowers + banner illustrated icon, curved banner text.
# Icon is width-dominant (87.4% of Small's card width vs 84.1% of its
# height), so uniformly scaled by the WIDTH ratio (1.5) -- both the
# icon's own w/h AND its hollow-gap-derived MAX_WIDTH values use 1.5.
# ICON_SCALE (mm per source-icon px) scales by the SAME factor as the
# icon itself, since it's a direct px->mm conversion for that icon.
# P02_BANNER_CURVE_COEFFS is NOT scaled -- it's a quadratic fit in
# ICON-LOCAL PIXEL space against the master image's own unchanged
# pixels; only the px<->mm conversion (ICON_SCALE) and position
# (ICON_X_LEFT) change, not the curve shape itself.
# ---------------------------------------------------------------------------
P02_ICON_MASTER = "assets/icons/house_banner_master.png"


def _p02_icon_path(accent_key):
    """Same recolour-and-cache pattern as every hollow-icon style."""
    master = _asset_path(P02_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"house_banner_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


P02_ICON = dict(x=13.2 * mm, y=11.11474 * mm, w=183.6 * mm, h=126.18285 * mm)
P02_ICON_SCALE = 0.135099  # mm per source-icon px -- 0.090066 x1.5 (icon's own uniform scale)
P02_ICON_X_LEFT = 13.2 * mm
P02_ICON_Y_TOP = 11.11474 * mm  # not used in drawing, kept for reference (unused in Small too)

P02_NUMBER_CENTER_Y = 83.62438 * mm      # 59.7317 x1.4
P02_NUMBER_MAX_WIDTH = 49.790835 * mm    # (36.8821*0.90) x1.5 (hollow gap, icon's own dominant axis)

P02_STREET_CENTER_Y = 51.52 * mm         # 36.8 x1.4
P02_STREET_MAX_WIDTH = 116.72586 * mm    # (86.4636*0.90) x1.5 (banner hollow gap, same axis)

P02_BANNER_CURVE_COEFFS = (3.36374116e-04, -4.56481049e-01, 7.64104309e+02)  # unchanged, see module note above


def _p02_banner_mid_px(x_px):
    a, b, cc = P02_BANNER_CURVE_COEFFS
    return a * x_px * x_px + b * x_px + cc



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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 14 * mm, 16.8 * mm, accent_hex, "house", draw_house_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Helvetica-Bold", 62)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.45, order["house_number"])
        c.setFont("Helvetica", 20)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.25, order["street_name"])
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold", 62, 28, P02_NUMBER_MAX_WIDTH)
    cap_height = number_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P02_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    # MAX size raised 19 -> 22pt (Aug 2026): real print showed short street
    # names ("RYE", "MILL LANE") pinned at the old 19pt ceiling with the
    # ribbon's width budget barely touched -- the ceiling, not the width,
    # was the binding constraint. Pixel-measured against the same scan:
    # text height was using ~13.3mm of the ribbon's ~16.7mm interior
    # height, so 22pt leaves ~1mm total clearance (was ~3.3mm at 19pt).
    street_size = _fit_font_size(street_text, "Helvetica-Bold", 31, 11, P02_STREET_MAX_WIDTH)
    cap_height = street_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    street_baseline = P02_STREET_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    _draw_curved_text(
        c, street_text, cx, oy + street_baseline, "Helvetica-Bold", street_size, accent_hex,
        ox + P02_ICON_X_LEFT, P02_ICON_SCALE, _p02_banner_mid_px, P02_BANNER_CURVE_COEFFS,
    )

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)



# ---------------------------------------------------------------------------
# P25 / P25b -- not hollow-nested icons (flourish/corner ornaments sit
# beside/around the text, not enclosing it), so their MAX_WIDTH values
# are independent text-band measurements and use the plain width-axis
# (1.5) rather than an icon's dominant-axis rule. The *_FRAC values (P25
# only) are fractions of card height and need NO scaling at all -- a
# fraction is size-invariant by definition; P02_CARD_H is aliased to
# CARD_H at the top of this file, so P25_STREET_CENTER_Y (a computed
# expression using P02_CARD_H * frac) is correct automatically.
# ---------------------------------------------------------------------------
P25_NUMBER_BASELINE_FRAC = _p25_frac(367)
P25_FLOURISH1_Y_FRAC = _p25_frac(440)
P25_STREET_BASELINE_FRAC = _p25_frac(602)  # unused, see Small's own comment
P25_FLOURISH2_Y_FRAC = _p25_frac(660)
P25_STREET_CENTER_Y = P02_CARD_H * (P25_FLOURISH1_Y_FRAC + P25_FLOURISH2_Y_FRAC) / 2

P25_NUMBER_SIZE = 126            # pt -- 90 x1.4
P25_NUMBER_MIN_SIZE = 56         # pt -- 40 x1.4
P25_NUMBER_MAX_WIDTH = 183 * mm  # 122 x1.5
P25_STREET_MAX_WIDTH = 183 * mm  # 122 x1.5
P25_BORDER_WEIGHT = 6.3          # pt -- 4.5 x1.4
P25_BORDER_RADIUS = 7 * mm       # 5 x1.4

P25_FLOURISH1_ICON = "assets/icons/p25_flourish1.png"
P25_FLOURISH2_ICON = "assets/icons/p25_flourish2.png"
P25_FLOURISH_WIDTH = 174 * mm    # 116 x1.5 (draw_center_flourish auto-preserves the asset's own aspect)

P25B_FLOURISH_ICON = "assets/icons/p25b_flourish.png"
P25B_FLOURISH_WIDTH = 184.5 * mm  # 123 x1.5

P25B_STREET_MAX_WIDTH = 172.5 * mm  # 115 x1.5
P25B_NUMBER_MAX_WIDTH = 172.5 * mm  # 115 x1.5

P25B_NUMBER_BASELINE_FRAC = _p25_frac(380)
P25B_FLOURISH_Y_FRAC = _p25_frac(470)
P25B_STREET_BASELINE_FRAC = _p25_frac(660)

P25B_CORNER_TL = "assets/icons/p25b_corner_tl.png"
P25B_CORNER_TR = "assets/icons/p25b_corner_tr.png"
P25B_CORNER_BR = "assets/icons/p25b_corner_br.png"
P25B_CORNER_BL = "assets/icons/p25b_corner_bl.png"

# Corner ornament -- scaled per-axis (W by width-ratio, H by height-ratio)
# rather than uniformly, unlike the PRIMARY illustrated icons elsewhere in
# this file. Reasoning: this is a small structural border element whose
# geometric consistency with the border LINES (which must meet it exactly
# at each corner) matters more than protecting it from a ~7% aspect
# distortion -- run_x/run_y below are computed directly from these same
# W/H values, so keeping them on the same per-axis convention as the line
# placement keeps the whole border self-consistent.
P25B_CORNER_W = 26.4101 * mm  # (165/(1312/140)) x1.5
P25B_CORNER_H = 24.8922 * mm  # (165/(928/100)) x1.4

# Per-edge spec, same per-axis convention: top/bottom use the height
# ratio (they're vertical measurements), left/right use the width ratio.
P25B_EDGE_SPEC = {
    "top":    (7.8442, 3.3194, 12.5216, 1.358),
    "bottom": (10.2592, 3.3194, 14.9352, 1.358),
    "left":   (7.5225, 3.681, 12.645, 1.44),
    "right":  (7.8435, 3.681, 12.9645, 1.44),
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
        coloured_path = _cached_icon_path(f"{name}_{color.lstrip('#')}.png")
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
    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P25_NUMBER_SIZE, P25_NUMBER_MIN_SIZE, P25_NUMBER_MAX_WIDTH)
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + h * P25_NUMBER_BASELINE_FRAC, order["house_number"])

    draw_center_flourish(c, cx, oy + h * P25_FLOURISH1_Y_FRAC, P25_FLOURISH1_ICON, P25_FLOURISH_WIDTH)

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold", 70, 22, P25_STREET_MAX_WIDTH)
    cap_height = street_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    street_baseline = P25_STREET_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

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
    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P25_NUMBER_SIZE, P25_NUMBER_MIN_SIZE, P25B_NUMBER_MAX_WIDTH)
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + h * P25B_NUMBER_BASELINE_FRAC, order["house_number"])

    draw_center_flourish(c, cx, oy + h * P25B_FLOURISH_Y_FRAC, P25B_FLOURISH_ICON, P25B_FLOURISH_WIDTH)

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold", 70, 22, P25B_STREET_MAX_WIDTH)
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + h * P25B_STREET_BASELINE_FRAC, street_text)



# ---------------------------------------------------------------------------
# P27 -- house-outline + chimney, number nested inside. Height-dominant
# icon (71.5% of Small's card height vs 58.5% of its width) -- uniform
# scale x1.4, and NUMBER_MAX_WIDTH (a true hollow-gap measurement) uses
# the same 1.4 axis. STREET_MAX_WIDTH is explicitly NOT icon-bounded
# (Small's own comment: "the street band isn't bounded by the icon at
# all") -- independent measurement, uses the plain width-axis (1.5).
# ---------------------------------------------------------------------------
P27_ICON_MASTER = "assets/icons/p27_house_icon.png"
P27_ICON = dict(x=43.7535 * mm, y=34.8587 * mm, w=114.5651 * mm, h=100.1384 * mm)

P27_NUMBER_CENTER_Y = 73.2277 * mm   # 52.3055 x1.4
P27_NUMBER_MAX_WIDTH = 59.2052 * mm  # 42.2894 x1.4 (hollow gap, icon's dominant axis)
P27_NUMBER_MAX_SIZE = 196            # pt -- 140 x1.4
P27_NUMBER_MIN_SIZE = 28             # pt -- 20 x1.4

P27_STREET_CENTER_Y = 19.487 * mm     # 13.9193 x1.4
P27_STREET_MAX_WIDTH = 144.4889 * mm  # 96.3259 x1.5 (independent measurement, not icon-bounded)
P27_STREET_MAX_SIZE = 92              # pt -- 66 x1.4 (92.4 rounded)
P27_STREET_MIN_SIZE = 22              # pt -- 16 x1.4 (22.4 rounded)

P27_PAD = 3 * mm  # unscaled, physical cutting tolerance


def _p27_icon_path(accent_key):
    """Same recolour-and-cache pattern as _p02_icon_path -- generates the
    accent-coloured icon from the master silhouette on first use, caches
    to disk. Returns None if the master art isn't present."""
    master = _asset_path(P27_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p27_house_{accent_key}.png")
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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 14 * mm, 16.8 * mm, accent_hex, "house", draw_house_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Helvetica-Bold", 62)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.45, order["house_number"])
        c.setFont("Helvetica-Bold", 20)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.25, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=P27_PAD)
        return

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold",
                                  P27_NUMBER_MAX_SIZE, P27_NUMBER_MIN_SIZE, P27_NUMBER_MAX_WIDTH)
    cap_height = number_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P27_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Helvetica-Bold",
                                  P27_STREET_MAX_SIZE, P27_STREET_MIN_SIZE, P27_STREET_MAX_WIDTH)
    cap_height = street_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    street_baseline = P27_STREET_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=P27_PAD)



# ---------------------------------------------------------------------------
# P47 -- house-outline, number nested inside, no street field.
# Width-dominant icon (78.6% vs 75.2%) -- uniform scale x1.5, and
# NUMBER_MAX_WIDTH (hollow gap) uses the same 1.5 axis.
# ---------------------------------------------------------------------------
P47_ICON_MASTER = "assets/icons/p47_house_icon.png"
P47_ICON = dict(x=22.5 * mm, y=17.3317 * mm, w=165.0 * mm, h=112.8606 * mm)

P47_NUMBER_CENTER_Y = 60.2522 * mm    # 43.0373 x1.4
P47_NUMBER_MAX_WIDTH = 104.4892 * mm  # 69.6595 x1.5 (hollow gap, icon's dominant axis)
P47_NUMBER_MAX_SIZE = 196             # pt -- 140 x1.4
P47_NUMBER_MIN_SIZE = 28              # pt -- 20 x1.4


def _p47_icon_path(accent_key):
    """Same recolour-and-cache pattern as _p02_icon_path/_p27_icon_path --
    generates the accent-coloured icon from the master silhouette on
    first use, caches to disk. Returns None if the master art isn't
    present (caller falls back to the plain vector house)."""
    master = _asset_path(P47_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p47_house_{accent_key}.png")
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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "house", draw_house_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Helvetica-Bold", 84)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.42, order["house_number"])
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold",
                                  P47_NUMBER_MAX_SIZE, P47_NUMBER_MIN_SIZE, P47_NUMBER_MAX_WIDTH)
    cap_height = number_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P47_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)



# ---------------------------------------------------------------------------
# P06 -- floral vine wreath, FLAT (uncurved) street text -- curved text
# was tried and rejected even at Small's own scale (see Small's own
# comment history), so this stays flat here too, same reasoning.
# Height-dominant icon (90% vs 65.6%) -- uniform scale x1.4, and both
# NUMBER_MAX_WIDTH/STREET_MAX_WIDTH (true hollow gaps) use the same 1.4.
# ---------------------------------------------------------------------------
P06_ICON_MASTER = "assets/icons/p06_wreath_icon.png"
P06_ICON = dict(x=36.5697 * mm, y=5.5633 * mm, w=128.5227 * mm, h=126.0 * mm)

P06_NUMBER_CENTER_Y = 73.5017 * mm    # 52.5012 x1.4
P06_NUMBER_MAX_WIDTH = 72.776 * mm    # (57.7587*0.90) x1.4
P06_NUMBER_MAX_SIZE = 146             # pt -- 104 x1.4 (145.6 rounded)
P06_NUMBER_MIN_SIZE = 28              # pt -- 20 x1.4

P06_STREET_CENTER_Y = 44.1438 * mm    # 31.5313 x1.4
P06_STREET_MAX_WIDTH = 52.6943 * mm   # (41.8209*0.90) x1.4
P06_STREET_MAX_SIZE = 95              # pt -- 68 x1.4 (95.2 rounded)
P06_STREET_MIN_SIZE = 17              # pt -- 12 x1.4 (16.8 rounded)


def _p06_icon_path(accent_key):
    """Same recolour-and-cache pattern as _p02_icon_path/_p27_icon_path/
    _p47_icon_path -- generates the accent-coloured icon from the master
    silhouette on first use, caches to disk. Returns None if the master
    art isn't present (caller falls back to the plain vector house as a
    substitution, same reasoning as the other hollow-icon styles)."""
    master = _asset_path(P06_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p06_wreath_{accent_key}.png")
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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 14 * mm, 16.8 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 62)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 20)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.32, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P06_NUMBER_MAX_SIZE, P06_NUMBER_MIN_SIZE, P06_NUMBER_MAX_WIDTH)
    cap_height = number_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P06_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold",
                                  P06_STREET_MAX_SIZE, P06_STREET_MIN_SIZE, P06_STREET_MAX_WIDTH)
    cap_height = street_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    street_baseline = P06_STREET_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)



P06_NUM_ONLY_CENTER_Y = 68.6431 * mm    # 49.0308 x1.4
P06_NUM_ONLY_MAX_WIDTH = 66.9144 * mm   # (53.1067*0.90) x1.4
P06_NUM_ONLY_MAX_SIZE = 210             # pt -- 150 x1.4
P06_NUM_ONLY_MIN_SIZE = 34              # pt -- 24 x1.4 (33.6 rounded)


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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 14 * mm, 16.8 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 98)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.46, order["house_number"])
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P06_NUM_ONLY_MAX_SIZE, P06_NUM_ONLY_MIN_SIZE, P06_NUM_ONLY_MAX_WIDTH)
    cap_height = number_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P06_NUM_ONLY_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)



# ---------------------------------------------------------------------------
# P30 -- laurel leaf wreath, numbers only. Height-dominant icon (95.1%
# vs 61.9%) -- uniform scale x1.4, NUMBER_MAX_WIDTH (hollow gap) same axis.
# ---------------------------------------------------------------------------
P30_LAUREL_ICON_MASTER = "assets/icons/p30_laurel_icon.png"
P30_LAUREL_ICON = dict(x=39.6767 * mm, y=3.85 * mm, w=121.2698 * mm, h=133.175 * mm)

P30_LAUREL_NUMBER_CENTER_Y = 73.7191 * mm   # 52.6565 x1.4
P30_LAUREL_NUMBER_MAX_WIDTH = 60.5052 * mm  # (48.02*0.90) x1.4
P30_LAUREL_NUMBER_MAX_SIZE = 210            # pt -- 150 x1.4
P30_LAUREL_NUMBER_MIN_SIZE = 34             # pt -- 24 x1.4 (33.6 rounded)


def _p30_laurel_icon_path(accent_key):
    """Same recolour-and-cache pattern as _p06_icon_path/_p47_icon_path --
    generates the accent-coloured icon from the master silhouette on
    first use, caches to disk. Returns None if the master art isn't
    present (caller falls back to the plain vector floral icon)."""
    master = _asset_path(P30_LAUREL_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p30_laurel_{accent_key}.png")
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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 14 * mm, 16.8 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 98)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.46, order["house_number"])
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P30_LAUREL_NUMBER_MAX_SIZE, P30_LAUREL_NUMBER_MIN_SIZE,
                                  P30_LAUREL_NUMBER_MAX_WIDTH)
    cap_height = number_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P30_LAUREL_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)



# ---------------------------------------------------------------------------
# P15 -- heart-vine wreath, FLAT street text (same "curved text broke
# once already" reasoning as P06/P28). Height-dominant icon (95.6% vs
# 66.1%) -- uniform scale x1.4, both MAX_WIDTH values (true hollow gaps)
# use the same axis.
# ---------------------------------------------------------------------------
P15_HEART_ICON_MASTER = "assets/icons/p15_heart_icon.png"
P15_HEART_ICON = dict(x=34.97 * mm, y=3.15 * mm, w=129.5451 * mm, h=133.875 * mm)

P15_HEART_NUMBER_CENTER_Y = 75.2842 * mm   # 53.7744 x1.4
P15_HEART_NUMBER_MAX_WIDTH = 87.066 * mm   # (69.10*0.90) x1.4
P15_HEART_NUMBER_MAX_SIZE = 123            # pt -- 88 x1.4 (123.2 rounded)
P15_HEART_NUMBER_MIN_SIZE = 28             # pt -- 20 x1.4

P15_HEART_STREET_CENTER_Y = 48.419 * mm    # 34.5850 x1.4
P15_HEART_STREET_MAX_WIDTH = 79.191 * mm   # (62.85*0.90) x1.4
P15_HEART_STREET_MAX_SIZE = 36             # pt -- 26 x1.4 (36.4 rounded)
P15_HEART_STREET_MIN_SIZE = 11             # pt -- 8 x1.4 (11.2 rounded)


def _p15_heart_icon_path(accent_key):
    """Same recolour-and-cache pattern as every other hollow-icon style
    in this file. Returns None if the master art isn't present (caller
    falls back to the plain vector floral icon)."""
    master = _asset_path(P15_HEART_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p15_heart_{accent_key}.png")
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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 14 * mm, 16.8 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 62)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 20)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.32, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P15_HEART_NUMBER_MAX_SIZE, P15_HEART_NUMBER_MIN_SIZE,
                                  P15_HEART_NUMBER_MAX_WIDTH)
    cap_height = number_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P15_HEART_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold",
                                  P15_HEART_STREET_MAX_SIZE, P15_HEART_STREET_MIN_SIZE,
                                  P15_HEART_STREET_MAX_WIDTH)
    cap_height = street_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    street_baseline = P15_HEART_STREET_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)



# ---------------------------------------------------------------------------
# P28 -- arrow/fletching wreath, FLAT street text (this source's text was
# already flat, per Small's own comment -- not a fallback choice).
# Height-dominant icon (93.2% vs 67.2%) -- uniform scale x1.4, both
# MAX_WIDTH values (true hollow gaps) use the same axis.
# ---------------------------------------------------------------------------
P28_ARROW_ICON_MASTER = "assets/icons/p28_arrow_icon.png"
P28_ARROW_ICON = dict(x=34.3902 * mm, y=4.4356 * mm, w=131.6683 * mm, h=130.4356 * mm)

P28_ARROW_NUMBER_CENTER_Y = 77.91 * mm      # 55.65 x1.4
P28_ARROW_NUMBER_MAX_WIDTH = 76.3376 * mm   # (60.5854*0.90) x1.4
P28_ARROW_NUMBER_MAX_SIZE = 168             # pt -- 120 x1.4
P28_ARROW_NUMBER_MIN_SIZE = 28              # pt -- 20 x1.4

P28_ARROW_STREET_CENTER_Y = 46.354 * mm     # 33.11 x1.4
P28_ARROW_STREET_MAX_WIDTH = 87.401 * mm    # (69.3659*0.90) x1.4
P28_ARROW_STREET_MAX_SIZE = 39              # pt -- 28 x1.4 (39.2 rounded)
P28_ARROW_STREET_MIN_SIZE = 11              # pt -- 8 x1.4 (11.2 rounded)


def _p28_arrow_icon_path(accent_key):
    """Same recolour-and-cache pattern as every other hollow-icon style
    in this file. Returns None if the master art isn't present (caller
    falls back to the plain vector floral icon)."""
    master = _asset_path(P28_ARROW_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p28_arrow_{accent_key}.png")
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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 14 * mm, 16.8 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 62)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 20)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.32, order["street_name"].upper())
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Times-Bold",
                                  P28_ARROW_NUMBER_MAX_SIZE, P28_ARROW_NUMBER_MIN_SIZE,
                                  P28_ARROW_NUMBER_MAX_WIDTH)
    cap_height = number_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P28_ARROW_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", number_size)
    c.drawCentredString(cx, oy + number_baseline, order["house_number"])

    street_text = order["street_name"].upper()
    street_size = _fit_font_size(street_text, "Times-Bold",
                                  P28_ARROW_STREET_MAX_SIZE, P28_ARROW_STREET_MIN_SIZE,
                                  P28_ARROW_STREET_MAX_WIDTH)
    cap_height = street_size * TIMES_BOLD_CAP_HEIGHT_RATIO
    street_baseline = P28_ARROW_STREET_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + street_baseline, street_text)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)



# ---------------------------------------------------------------------------
# Animal family (12 scenes: duck/dog/cat x4 each) -- all 12 share an
# IDENTICAL icon box in Small (x=10,y=34,w=120,h=58), width-dominant
# (85.7% of Small's card width vs 58% of its height) -- uniform scale
# x1.5. Since every scene reuses the same box, only one scaled box is
# derived here and reused for all 12, matching Small's own convention.
# ANIMAL_*_MAX_WIDTH values are independent card-width measurements (not
# tied to any specific icon's hollow -- these icons sit beside the text,
# not nested inside it), so they use the plain width-axis. Font sizes use
# the height-axis, per the general rule.
# ---------------------------------------------------------------------------
_ANIMAL_ICON_BOX = dict(x=15.0 * mm, y=47.6 * mm, w=180.0 * mm, h=87.0 * mm)

DUCK_FATHER_ICON_MASTER = "assets/icons/duck_family_father_icon.png"
DUCK_FATHER_ICON = dict(_ANIMAL_ICON_BOX)

DUCK_FATHER_NUMBER_CENTER_Y = 23.8 * mm  # 17 x1.4
DUCK_FATHER_STREET_CENTER_Y = 9.8 * mm   # 7 x1.4

DUCK_FATHER_PAD = 3.4 * mm  # unscaled, physical cutting tolerance -- shared by all 12 scenes' _draw_border pad

ANIMAL_NUMBER_MAX_SIZE = 88   # pt -- 63 x1.4 (88.2 rounded)
ANIMAL_NUMBER_MIN_SIZE = 34   # pt -- 24 x1.4 (33.6 rounded)
ANIMAL_NUMBER_MAX_WIDTH = 165 * mm  # 110 x1.5 (independent, card-width-based)

ANIMAL_STREET_MAX_SIZE = 38   # pt -- 27 x1.4 (37.8 rounded)
ANIMAL_STREET_MIN_SIZE = 13   # pt -- 9 x1.4 (12.6 rounded)
ANIMAL_STREET_MAX_WIDTH = 183 * mm  # 122 x1.5


def _animal_family_text(c, cx, oy, order):
    """Shared auto-shrink number/street drawing for all 12 animal-family
    scenes -- verbatim logic from Small, just referencing this file's own
    scaled constants above."""
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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



DUCK_MOTHER_ICON_MASTER = "assets/icons/duck_family_mother_icon.png"
DUCK_MOTHER_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



DUCK_PLAYING1_ICON_MASTER = "assets/icons/duck_family_playing1_icon.png"
DUCK_PLAYING1_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



DUCK_PLAYING2_ICON_MASTER = "assets/icons/duck_family_playing2_icon.png"
DUCK_PLAYING2_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



DOG_FAMILY_1_ICON_MASTER = "assets/icons/dog_family_1_icon.png"
DOG_FAMILY_1_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



DOG_FAMILY_2_ICON_MASTER = "assets/icons/dog_family_2_icon.png"
DOG_FAMILY_2_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



DOG_PLAYING1_ICON_MASTER = "assets/icons/dog_family_playing1_icon.png"
DOG_PLAYING1_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



DOG_PLAYING2_ICON_MASTER = "assets/icons/dog_family_playing2_icon.png"
DOG_PLAYING2_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



CAT_FAMILY_1_ICON_MASTER = "assets/icons/cat_family_1_icon.png"
CAT_FAMILY_1_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



CAT_FAMILY_2_ICON_MASTER = "assets/icons/cat_family_2_icon.png"
CAT_FAMILY_2_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



CAT_PLAYING1_ICON_MASTER = "assets/icons/cat_family_playing1_icon.png"
CAT_PLAYING1_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)



CAT_PLAYING2_ICON_MASTER = "assets/icons/cat_family_playing2_icon.png"
CAT_PLAYING2_ICON = dict(_ANIMAL_ICON_BOX)


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 42 * mm, accent_hex, "paw", draw_paw_icon)

    _animal_family_text(c, cx, oy, order)

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H, pad=DUCK_FATHER_PAD)




STYLES = {
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

# All 25 styles ported from Small (Sep 2026) -- same key set as
# bin_sticker_small.STYLES, so any order valid for Small is also valid
# here (just pick a different STYLE_CARD_SIZE/render layout). Every one
# of these is a FIRST-PASS scaled derivation -- see the module docstring
# for the scaling methodology and its "not yet print-validated" caveat,
# which applies to all 25, not just the original 3-style pilot.
STYLE_LABELS = {
    "house_banner": "1. D01 — Cottage Bloom Banner (Medium, DRAFT)",
    "p25_landscape_flourish": "2. D02 — Regency Double Flourish (Medium, DRAFT)",
    "p25b_landscape_flourish": "3. D03 — Manor Frame Classic (Medium, DRAFT)",
    "p27_landscape_house": "4. D04 — Homestead Silhouette (Medium, DRAFT)",
    "p47_house": "5. P47 — House-outline + number, black-only (Medium, DRAFT)",
    "p06_wreath": "6. P06 — Floral vine wreath, number + street (Medium, DRAFT)",
    "p06_wreath_numbers": "7. P06 numbers-only (Medium, DRAFT)",
    "p30_laurel_numbers": "8. P30 laurel wreath, numbers only (Medium, DRAFT)",
    "p15_heart_wreath": "9. P15 heart-vine wreath (Medium, DRAFT)",
    "p28_arrow_wreath": "10. P28 arrow/fletching wreath (Medium, DRAFT)",
    "p31_olive_wreath": "11. P31 olive branch wreath (Medium pilot, DRAFT)",
    "duck_family_father": "12. Duck Family, Scene 1 (Medium, DRAFT)",
    "duck_family_mother": "13. Duck Family, Scene 2 (Medium, DRAFT)",
    "duck_family_playing1": "14. Duck Family, Scene 3 (Medium, DRAFT)",
    "duck_family_playing2": "15. Duck Family, Scene 4 (Medium, DRAFT)",
    "dog_family_1": "16. Dog Family, Scene 1 (Medium, DRAFT)",
    "dog_family_2": "17. Dog Family, Scene 2 (Medium, DRAFT)",
    "dog_family_playing1": "18. Dog Family, Scene 3 (Medium, DRAFT)",
    "dog_family_playing2": "19. Dog Family, Scene 4 (Medium, DRAFT)",
    "cat_family_1": "20. Cat Family, Scene 1 (Medium, DRAFT)",
    "cat_family_2": "21. Cat Family, Scene 2 (Medium, DRAFT)",
    "cat_family_playing1": "22. Cat Family, Scene 3 (Medium, DRAFT)",
    "cat_family_playing2": "23. Cat Family, Scene 4 (Medium, DRAFT)",
    "p09a_borderless": "24. P09a — Borderless minimal (Medium pilot, DRAFT)",
    "p21_paw_trail": "25. P21 — Paw trail (Medium pilot, DRAFT)",
}

# No STYLE_PRODUCT_ID entries yet -- none of these have shipped as a
# catalogued Medium product; every one is still pre-print-test.

STYLE_CARD_SIZE = {style: (CARD_W, CARD_H) for style in STYLES}


def draw_sticker(c, ox, oy, order):
    """
    order = dict:
        house_number str
        street_name  str
        style        key in STYLES              (default "p09a_borderless")
        accent       key in ACCENTS              (each style has its own default)
    """
    style = order.get("style", "p09a_borderless")
    _draw_base(c, ox, oy, CARD_W, CARD_H)
    STYLES[style](c, ox, oy, order)


# This size's real print layout: 2-per-A4, STACKED VERTICALLY (1 column x
# 2 rows) on a PORTRAIT A4 page -- NOT the small size's 2x2 grid. The
# card is 210mm wide, exactly the full physical A4 page width, so
# margin_x=0 (confirmed via real print test: borderless on left/right,
# ~2-4mm measured dead zone -- see module docstring). Vertical margin is
# None (auto-centred), which computes to 8.5mm top/bottom -- normal,
# non-borderless margin, confirmed comfortably clear of the printer's
# 3mm minimum.
_PAGE_SIZE = A4  # portrait, NOT landscape -- see core.render_sheet_grid's
# docstring for why page orientation can't be auto-derived from whether
# the card itself is wider than tall.


def render_sheet(orders, out_path, caption=False):
    """Fills one portrait A4 sheet, 1 column x 2 rows (up to 2 cards).
    Set caption=True to print each design's label in the bottom margin."""
    core.render_sheet_grid(
        orders, out_path, CARD_W, CARD_H, cols=1, rows=2,
        draw_fn=draw_sticker, page_size=_PAGE_SIZE,
        margin_x=0, caption=caption, style_labels=STYLE_LABELS,
    )


def render_gallery(style_keys, sample_order, out_path):
    """One sticker per style in style_keys, paginated 2-up across as many
    portrait A4 pages as needed."""
    core.render_gallery_grid(
        style_keys, sample_order, out_path, CARD_W, CARD_H,
        cols=1, rows=2, draw_fn=draw_sticker, page_size=_PAGE_SIZE,
        style_labels=STYLE_LABELS,
    )


if __name__ == "__main__":
    sample_order = {"house_number": "28", "street_name": "North Avenue"}
    all_styles = list(STYLES.keys())
    out_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "bin_sticker_medium_gallery.pdf")
    render_gallery(all_styles, sample_order, out_path)
    print(f"Wrote {out_path}")
