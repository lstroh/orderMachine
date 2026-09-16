"""
Bin sticker generator — LARGE (287x200mm, landscape) — Kerbside Craft Co.

PILOT, Sep 2026: 3 styles ported from bin_sticker_medium.py (202x140mm) to
validate the scaling method for this size, same pattern as how Medium
itself started. 1-per-A4 sheet, landscape orientation, normal (non-
borderless) print mode.

WHY LANDSCAPE, NOT THE ORIGINALLY-SPEC'D PORTRAIT 200x287mm: Large was
originally planned as a portrait card (200mm wide x 287mm tall, matching
a real competitor benchmark) -- but that shape doesn't match any style in
the existing catalogue (all 25 Small/Medium styles are landscape,
1.4-1.5:1). Porting a landscape composition onto a 0.7:1 portrait frame
would need genuinely new layouts, not a scale-and-retune job the way
Medium was. Deliberately reconsidered (Sep 2026) as landscape instead --
287x200mm, the same two dimensions swapped -- so the existing 25 styles
can be ported the same way Medium was. This departs from matching the
30x20cm competitor benchmark exactly; it's now a genuine "XL" tier of the
same landscape product family rather than a distinct tall-plaque product.

PRINT MODE: confirmed via direct calculation against the same Epson
ET-3950 normal-mode spec used throughout this project (3mm minimum
margin each side) -- 287x200mm fits a landscape A4 page (297x210mm) with
a comfortable 5mm margin on all four sides, well clear of the 3mm floor.
No borderless printing needed, same as Medium after its own width
narrowing. Only 1 card fits per sheet (287mm is nearly the full 297mm
page width -- 2-across would need 574mm).

NO ASYMMETRIC LAMINATION MARGIN ON THE VERTICAL AXIS (unlike the
horizontal axis below): Large only has 10mm of TOTAL slack per axis,
versus Small/Medium's 17mm -- not enough to give one edge a Small/
Medium-scale 14mm allowance without breaking the printer's 3mm minimum
elsewhere. A smaller 7mm/3mm split IS applied on the horizontal axis
(see LARGE_MARGIN_LEFT below) -- scaled down from Small's 14mm/3mm
convention to fit this size's tighter budget, still comfortably clear
of the 3mm floor on the tight side. Whether 7mm is actually enough
slack to matter for real lamination alignment is an open question for
the print-test phase, not something settled here.

SCALING METHODOLOGY: identical rules to the Small-to-Medium port (see
bin_sticker_medium.py's own docstring for the full writeup) -- axis-
consistent scaling, icons scaled uniformly by their own dominant axis,
hollow-gap MAX_WIDTH values follow the icon's axis, independent
MAX_WIDTH values follow the plain width-axis, PAD stays unscaled. ONE
IMPORTANT DIFFERENCE: this scales from MEDIUM's constants, not Small's
directly, and Large's ratio to Medium is nearly uniform (287/202=1.4208
width, 200/140=1.4286 height -- a 0.008 mismatch, vs. 0.05 for Small-to-
Medium). That makes this port meaningfully lower-risk than Medium's own
port was: the icon-distortion and axis-mismatch concerns that drove most
of Medium's methodology barely apply here, since both axes scale almost
identically. Still first-pass, still not print-validated -- treat every
constant the same way as Medium's own pilot was treated.
"""

from reportlab.lib.pagesizes import A4, landscape
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

CARD_W = 280 * mm  # narrowed from 287mm (Sep 2026) to fit a full 14mm/3mm lamination margin matching Small (297-14-3=280) -- see module docstring.
CARD_H = 200 * mm

# Alias so every verbatim-copied style body below (originally written
# against Medium's P02_CARD_W/H) resolves to THIS file's actual card
# size without needing any per-line rewrite -- same convention carried
# from Small through Medium through here.
P02_CARD_W = CARD_W
P02_CARD_H = CARD_H

# ---------------------------------------------------------------------------
# P09a -- borderless minimal. Pure text, no icon -- simplest of the 3
# pilot styles, scaled straight from Medium with no icon-distortion
# concerns at all.
# ---------------------------------------------------------------------------
P09A_NUMBER_CENTER_Y = 137.1866 * mm
P09A_UNDERLINE_CENTER_Y = 99.8609 * mm
P09A_STREET_CENTER_Y = 71.17 * mm

P09A_NUMBER_MAX_WIDTH = 220.0 * mm  # 225.5 x(280/287)
P09A_NUMBER_MAX_SIZE = 280
P09A_NUMBER_MIN_SIZE = 40

P09A_STREET_MAX_WIDTH = 240.0 * mm  # 246.0 x(280/287)
P09A_STREET_MAX_SIZE = 131
P09A_STREET_MIN_SIZE = 31

P09A_UNDERLINE_WEIGHT = 2.0


def _style_p09a_borderless(c, ox, oy, order):
    """P09a -- LARGE (287x200mm). Bold house number, thin underline rule
    sized to the street name's own width, street name in caps below it.
    No icon, no border. Scaled from Medium -- see module docstring."""
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


# ---------------------------------------------------------------------------
# P21 -- paw trail. Icon is height-dominant (same as at every size so
# far) -- uniform-scaled by the height ratio. NUMBER_MAX_WIDTH is NOT a
# hollow gap (icon sits beside the text, not enclosing it) -- recomputed
# geometrically from the already-scaled icon box + number position,
# same reasoning as the Medium port's own P21 fix.
# ---------------------------------------------------------------------------
P21_ICON_MASTER = "assets/icons/p21_paw_trail_icon.png"
P21_ICON = dict(x=14.9391 * mm, y=15.5171 * mm, w=115.6709 * mm, h=171.5519 * mm)  # x narrowed x(280/287)

P21_NUMBER_CENTER_X = 198.4956 * mm  # 203.458 x(280/287)
P21_NUMBER_CENTER_Y = 124.6319 * mm
P21_NUMBER_MAX_WIDTH = 122.1941 * mm  # re-recomputed for the 280mm-wide card, see module docstring
P21_NUMBER_MAX_SIZE = 254
P21_NUMBER_MIN_SIZE = 40

P21_STREET_CENTER_X = 198.4956 * mm  # matches NUMBER_CENTER_X
P21_STREET_CENTER_Y = 49.5154 * mm
P21_STREET_MAX_WIDTH = 136.0 * mm  # 139.4 x(280/287)
P21_STREET_MAX_SIZE = 89
P21_STREET_MIN_SIZE = 31

# NOT scaled -- physical cutting/lamination tolerance, same reasoning as
# every other size in this project.
P21_PAD = 3.4 * mm


def _p21_icon_path(accent_key):
    master = _asset_path(P21_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p21_paw_trail_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p21_paw_trail(c, ox, oy, order):
    """P21 -- LARGE (287x200mm). Diagonal trail of 5 paw prints beside a
    large house number, street name below it. Scaled from Medium -- see
    module docstring."""
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
        print(
            f"WARNING: p21_paw_trail (large): master icon not found at "
            f"{_asset_path(P21_ICON_MASTER)!r} -- rendering plain single-"
            f"paw fallback instead of the extracted P21 trail design for "
            f"house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, ox + P21_ICON["x"] + P21_ICON["w"] / 2, oy + P21_ICON["y"] + P21_ICON["h"] / 2,
                   38 * mm, accent_hex, "paw", draw_paw_icon)  # 26.6mm x1.4286

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold",
                                  P21_NUMBER_MAX_SIZE, P21_NUMBER_MIN_SIZE, P21_NUMBER_MAX_WIDTH)
    cap_height = number_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    number_baseline = P21_NUMBER_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    c.setFillColor(HexColor(accent_hex))
    c.setFont("Helvetica-Bold", number_size)
    c.drawCentredString(ox + P21_NUMBER_CENTER_X, oy + number_baseline, order["house_number"])

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
# P31 -- olive branch wreath. Height-dominant icon, same pattern as
# above. Number/street text centres on the ICON's own midpoint (not the
# card's) -- this is the fix found during Medium's print review, carried
# forward here from the start rather than needing to be rediscovered.
# ---------------------------------------------------------------------------
P31_OLIVE_ICON_MASTER = "assets/icons/p31_olive_icon.png"
P31_OLIVE_ICON = dict(x=49.1515 * mm, y=2.8401 * mm, w=182.396 * mm, h=190.7691 * mm)  # x narrowed x(280/287)

P31_OLIVE_NUMBER_CENTER_Y = 106.7739 * mm
P31_OLIVE_NUMBER_MAX_WIDTH = 90.9884 * mm  # hollow gap, icon's dominant axis
P31_OLIVE_NUMBER_MAX_SIZE = 180
P31_OLIVE_NUMBER_MIN_SIZE = 40

P31_OLIVE_STREET_CENTER_Y = 71.0864 * mm
P31_OLIVE_STREET_MAX_WIDTH = 95.391 * mm  # hollow gap, icon's dominant axis
P31_OLIVE_STREET_MAX_SIZE = 44
P31_OLIVE_STREET_MIN_SIZE = 16


def _p31_olive_icon_path(accent_key):
    master = _asset_path(P31_OLIVE_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"p31_olive_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


def _style_p31_olive_wreath(c, ox, oy, order):
    """P31 olive branch wreath -- LARGE (287x200mm). House number nested
    in the upper interior, street name in flat text below it. Scaled
    from Medium -- see module docstring."""
    accent_key = order.get("accent", "charcoal")
    accent_hex = _resolve_accent(accent_key)
    # Centre on the ICON's own midpoint, not the card's -- same fix
    # already applied at Medium size, carried forward here.
    cx = ox + P31_OLIVE_ICON["x"] + P31_OLIVE_ICON["w"] / 2

    icon_path = _p31_olive_icon_path(accent_key)
    if icon_path:
        img = ImageReader(icon_path)
        c.drawImage(
            img, ox + P31_OLIVE_ICON["x"], oy + P31_OLIVE_ICON["y"],
            P31_OLIVE_ICON["w"], P31_OLIVE_ICON["h"],
            mask="auto", preserveAspectRatio=True, anchor="c",
        )
    else:
        print(
            f"WARNING: p31_olive_wreath (large): master icon not found at "
            f"{_asset_path(P31_OLIVE_ICON_MASTER)!r} -- rendering plain "
            f"floral fallback instead of the extracted olive wreath "
            f"design for house_number={order.get('house_number')!r}."
        )
        _draw_icon(c, cx, oy + CARD_H - PAD - 20 * mm, 24 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 89)
        c.drawCentredString(cx, oy + CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 29)
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
# Registry -- pilot subset only (3 of 25 styles), same 3 as Medium's own
# pilot for a clean apples-to-apples comparison.
# ---------------------------------------------------------------------------

_P25_SRC_H_PX = 928

def _p25_frac(row_px):
    """Convert a measured source-image row (0 = top) to a fraction of
    P02_CARD_H measured from the card's bottom -- same convention as the
    oy + CARD_H * frac pattern used by every other style in this file."""
    return 1 - row_px / _P25_SRC_H_PX


# ---------------------------------------------------------------------------
# P02 -- house + flowers + banner. Height-dominant THIS TIME (unlike at
# Medium, where it was width-dominant) -- see module docstring for why:
# the dominant-axis check uses Medium's CURRENT actual icon proportions
# (90.1% of Medium's height vs 87.4% of its width), not a stale
# classification carried over from the original Small-based determination.
# Curve coefficients unchanged (same master image, same pixel-space fit);
# only ICON_SCALE (px<->mm) and position change.
# ---------------------------------------------------------------------------
P02_ICON_MASTER = "assets/icons/house_banner_master.png"


def _p02_icon_path(accent_key):
    master = _asset_path(P02_ICON_MASTER)
    if not os.path.exists(master):
        return None
    path = _cached_icon_path(f"house_banner_{accent_key}.png")
    if not os.path.exists(path):
        recolour_silhouette(master, path, _resolve_accent(accent_key))
    return path


P02_ICON = dict(x=17.5999 * mm, y=15.8781 * mm, w=252.2939 * mm, h=180.2613 * mm)
P02_ICON_SCALE = 0.185646
P02_ICON_X_LEFT = 17.5999 * mm
P02_ICON_Y_TOP = 15.8781 * mm

P02_NUMBER_CENTER_Y = 119.4634 * mm
P02_NUMBER_MAX_WIDTH = 68.42 * mm

P02_STREET_CENTER_Y = 73.6 * mm
P02_STREET_MAX_WIDTH = 160.3989 * mm

P02_BANNER_CURVE_COEFFS = (3.36374116e-04, -4.56481049e-01, 7.64104309e+02)


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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 20 * mm, 24 * mm, accent_hex, "house", draw_house_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Helvetica-Bold", 89)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.45, order["house_number"])
        c.setFont("Helvetica", 29)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.25, order["street_name"])
        _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)
        return

    number_size = _fit_font_size(order["house_number"], "Helvetica-Bold", 89, 40, P02_NUMBER_MAX_WIDTH)
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
    street_size = _fit_font_size(street_text, "Helvetica-Bold", 44, 16, P02_STREET_MAX_WIDTH)
    cap_height = street_size * HELVETICA_BOLD_CAP_HEIGHT_RATIO
    street_baseline = P02_STREET_CENTER_Y - cap_height / 2 * (25.4 / 72) * mm
    _draw_curved_text(
        c, street_text, cx, oy + street_baseline, "Helvetica-Bold", street_size, accent_hex,
        ox + P02_ICON_X_LEFT, P02_ICON_SCALE, _p02_banner_mid_px, P02_BANNER_CURVE_COEFFS,
    )

    _draw_border(c, ox, oy, order, "single", w=P02_CARD_W, h=P02_CARD_H)



# ---------------------------------------------------------------------------
# P25 / P25b -- not hollow-nested, independent width-axis MAX_WIDTH.
# _FRAC values need no scaling (size-invariant fractions), same as at
# every previous size.
# ---------------------------------------------------------------------------
P25_NUMBER_BASELINE_FRAC = _p25_frac(367)
P25_FLOURISH1_Y_FRAC = _p25_frac(440)
P25_STREET_BASELINE_FRAC = _p25_frac(602)
P25_FLOURISH2_Y_FRAC = _p25_frac(660)
P25_STREET_CENTER_Y = P02_CARD_H * (P25_FLOURISH1_Y_FRAC + P25_FLOURISH2_Y_FRAC) / 2

P25_NUMBER_SIZE = 180
P25_NUMBER_MIN_SIZE = 80
P25_NUMBER_MAX_WIDTH = 244.0 * mm
P25_STREET_MAX_WIDTH = 244.0 * mm
P25_BORDER_WEIGHT = 9.0
P25_BORDER_RADIUS = 10.0 * mm

P25_FLOURISH1_ICON = "assets/icons/p25_flourish1.png"
P25_FLOURISH2_ICON = "assets/icons/p25_flourish2.png"
P25_FLOURISH_WIDTH = 232.0 * mm

P25B_FLOURISH_ICON = "assets/icons/p25b_flourish.png"
P25B_FLOURISH_WIDTH = 246.0 * mm

P25B_STREET_MAX_WIDTH = 230.0 * mm
P25B_NUMBER_MAX_WIDTH = 230.0 * mm

P25B_NUMBER_BASELINE_FRAC = _p25_frac(380)
P25B_FLOURISH_Y_FRAC = _p25_frac(470)
P25B_STREET_BASELINE_FRAC = _p25_frac(660)

P25B_CORNER_TL = "assets/icons/p25b_corner_tl.png"
P25B_CORNER_TR = "assets/icons/p25b_corner_tr.png"
P25B_CORNER_BR = "assets/icons/p25b_corner_br.png"
P25B_CORNER_BL = "assets/icons/p25b_corner_bl.png"

P25B_CORNER_W = 35.2135 * mm
P25B_CORNER_H = 35.5603 * mm

P25B_EDGE_SPEC = {
    "top":    (11.206, 4.742, 17.888, 1.94),
    "bottom": (14.656, 4.742, 21.336, 1.94),
    "left":   (10.03, 4.908, 16.86, 1.9199),
    "right":  (10.458, 4.908, 17.286, 1.9199),
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
    street_size = _fit_font_size(street_text, "Times-Bold", 100, 31, P25_STREET_MAX_WIDTH)
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
    street_size = _fit_font_size(street_text, "Times-Bold", 100, 31, P25B_STREET_MAX_WIDTH)
    c.setFont("Times-Bold", street_size)
    c.drawCentredString(cx, oy + h * P25B_STREET_BASELINE_FRAC, street_text)



# ---------------------------------------------------------------------------
# P27 -- house-outline + chimney. Height-dominant (same as at every size
# so far -- this one didn't flip). NUMBER_MAX_WIDTH follows the icon's
# axis (hollow gap); STREET_MAX_WIDTH is independent (not icon-bounded),
# width-axis.
# ---------------------------------------------------------------------------
P27_ICON_MASTER = "assets/icons/p27_house_icon.png"
P27_ICON = dict(x=58.338 * mm, y=49.7981 * mm, w=163.6644 * mm, h=143.0549 * mm)

P27_NUMBER_CENTER_Y = 104.611 * mm
P27_NUMBER_MAX_WIDTH = 84.5789 * mm
P27_NUMBER_MAX_SIZE = 280
P27_NUMBER_MIN_SIZE = 40

P27_STREET_CENTER_Y = 27.8386 * mm
P27_STREET_MAX_WIDTH = 192.6519 * mm
P27_STREET_MAX_SIZE = 131
P27_STREET_MIN_SIZE = 31

P27_PAD = 3.0 * mm


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
    # Centre on the ICON's own midpoint, not the card's -- fixes a real bug
    # found via print review (Sep 2026): this icon's x/w were scaled by
    # different axis ratios (position by width-axis, size by height-axis,
    # since this icon is height-dominant), which amplified a small
    # pre-existing off-centre asymmetry in the Small-size source art into
    # a visually noticeable offset at Medium size. Centring on the icon's
    # own midpoint makes the text track wherever the actual artwork sits,
    # regardless of any such asymmetry.
    cx = ox + P27_ICON["x"] + P27_ICON["w"] / 2

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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 20 * mm, 24 * mm, accent_hex, "house", draw_house_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Helvetica-Bold", 89)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.45, order["house_number"])
        c.setFont("Helvetica-Bold", 29)
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
# P47 -- house-outline, numbers only. Height-dominant THIS TIME (was
# width-dominant at Medium) -- same reasoning as P02, see module
# docstring.
# ---------------------------------------------------------------------------
P47_ICON_MASTER = "assets/icons/p47_house_icon.png"
P47_ICON = dict(x=30.0001 * mm, y=24.7596 * mm, w=226.7347 * mm, h=161.2294 * mm)

P47_NUMBER_CENTER_Y = 86.0746 * mm
P47_NUMBER_MAX_WIDTH = 143.5839 * mm
P47_NUMBER_MAX_SIZE = 280
P47_NUMBER_MIN_SIZE = 40


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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "house", draw_house_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Helvetica-Bold", 120)
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
# P06 -- floral vine wreath, flat street text. Height-dominant.
# ---------------------------------------------------------------------------
P06_ICON_MASTER = "assets/icons/p06_wreath_icon.png"
P06_ICON = dict(x=48.7596 * mm, y=7.9476 * mm, w=183.6039 * mm, h=180.0 * mm)

P06_NUMBER_CENTER_Y = 105.0024 * mm
P06_NUMBER_MAX_WIDTH = 103.9657 * mm
P06_NUMBER_MAX_SIZE = 209
P06_NUMBER_MIN_SIZE = 40

P06_STREET_CENTER_Y = 63.0626 * mm
P06_STREET_MAX_WIDTH = 75.2776 * mm
P06_STREET_MAX_SIZE = 136
P06_STREET_MIN_SIZE = 24


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
    # Centre on the ICON's own midpoint, not the card's -- fixes a real bug
    # found via print review (Sep 2026): this icon's x/w were scaled by
    # different axis ratios (position by width-axis, size by height-axis,
    # since this icon is height-dominant), which amplified a small
    # pre-existing off-centre asymmetry in the Small-size source art into
    # a visually noticeable offset at Medium size. Centring on the icon's
    # own midpoint makes the text track wherever the actual artwork sits,
    # regardless of any such asymmetry.
    cx = ox + P06_ICON["x"] + P06_ICON["w"] / 2

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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 20 * mm, 24 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 89)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 29)
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



P06_NUM_ONLY_CENTER_Y = 98.0616 * mm
P06_NUM_ONLY_MAX_WIDTH = 95.592 * mm
P06_NUM_ONLY_MAX_SIZE = 300
P06_NUM_ONLY_MIN_SIZE = 49


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
    # Centre on the ICON's own midpoint, not the card's -- fixes a real bug
    # found via print review (Sep 2026): this icon's x/w were scaled by
    # different axis ratios (position by width-axis, size by height-axis,
    # since this icon is height-dominant), which amplified a small
    # pre-existing off-centre asymmetry in the Small-size source art into
    # a visually noticeable offset at Medium size. Centring on the icon's
    # own midpoint makes the text track wherever the actual artwork sits,
    # regardless of any such asymmetry.
    cx = ox + P06_ICON["x"] + P06_ICON["w"] / 2

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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 20 * mm, 24 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 140)
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
# P30 -- laurel wreath, numbers only. Height-dominant.
# ---------------------------------------------------------------------------
P30_LAUREL_ICON_MASTER = "assets/icons/p30_laurel_icon.png"
P30_LAUREL_ICON = dict(x=52.9023 * mm, y=5.5 * mm, w=173.2426 * mm, h=190.25 * mm)

P30_LAUREL_NUMBER_CENTER_Y = 105.313 * mm
P30_LAUREL_NUMBER_MAX_WIDTH = 86.436 * mm
P30_LAUREL_NUMBER_MAX_SIZE = 300
P30_LAUREL_NUMBER_MIN_SIZE = 49


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
    # Centre on the ICON's own midpoint, not the card's -- fixes a real bug
    # found via print review (Sep 2026): this icon's x/w were scaled by
    # different axis ratios (position by width-axis, size by height-axis,
    # since this icon is height-dominant), which amplified a small
    # pre-existing off-centre asymmetry in the Small-size source art into
    # a visually noticeable offset at Medium size. Centring on the icon's
    # own midpoint makes the text track wherever the actual artwork sits,
    # regardless of any such asymmetry.
    cx = ox + P30_LAUREL_ICON["x"] + P30_LAUREL_ICON["w"] / 2

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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 20 * mm, 24 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 140)
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
# P15 -- heart-vine wreath, flat street text. Height-dominant.
# ---------------------------------------------------------------------------
P15_HEART_ICON_MASTER = "assets/icons/p15_heart_icon.png"
P15_HEART_ICON = dict(x=46.6267 * mm, y=4.5 * mm, w=185.0644 * mm, h=191.25 * mm)

P15_HEART_NUMBER_CENTER_Y = 107.5489 * mm
P15_HEART_NUMBER_MAX_WIDTH = 124.38 * mm
P15_HEART_NUMBER_MAX_SIZE = 176
P15_HEART_NUMBER_MIN_SIZE = 40

P15_HEART_STREET_CENTER_Y = 69.17 * mm
P15_HEART_STREET_MAX_WIDTH = 113.13 * mm
P15_HEART_STREET_MAX_SIZE = 51
P15_HEART_STREET_MIN_SIZE = 16


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
    # Centre on the ICON's own midpoint, not the card's -- fixes a real bug
    # found via print review (Sep 2026): this icon's x/w were scaled by
    # different axis ratios (position by width-axis, size by height-axis,
    # since this icon is height-dominant), which amplified a small
    # pre-existing off-centre asymmetry in the Small-size source art into
    # a visually noticeable offset at Medium size. Centring on the icon's
    # own midpoint makes the text track wherever the actual artwork sits,
    # regardless of any such asymmetry.
    cx = ox + P15_HEART_ICON["x"] + P15_HEART_ICON["w"] / 2

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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 20 * mm, 24 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 89)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 29)
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
# P28 -- arrow/fletching wreath, flat street text. Height-dominant.
# ---------------------------------------------------------------------------
P28_ARROW_ICON_MASTER = "assets/icons/p28_arrow_icon.png"
P28_ARROW_ICON = dict(x=45.8536 * mm, y=6.3366 * mm, w=188.0976 * mm, h=186.3366 * mm)

P28_ARROW_NUMBER_CENTER_Y = 111.3 * mm
P28_ARROW_NUMBER_MAX_WIDTH = 109.0537 * mm
P28_ARROW_NUMBER_MAX_SIZE = 240
P28_ARROW_NUMBER_MIN_SIZE = 40

P28_ARROW_STREET_CENTER_Y = 66.22 * mm
P28_ARROW_STREET_MAX_WIDTH = 124.8586 * mm
P28_ARROW_STREET_MAX_SIZE = 56
P28_ARROW_STREET_MIN_SIZE = 16


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
    # Centre on the ICON's own midpoint, not the card's -- fixes a real bug
    # found via print review (Sep 2026): this icon's x/w were scaled by
    # different axis ratios (position by width-axis, size by height-axis,
    # since this icon is height-dominant), which amplified a small
    # pre-existing off-centre asymmetry in the Small-size source art into
    # a visually noticeable offset at Medium size. Centring on the icon's
    # own midpoint makes the text track wherever the actual artwork sits,
    # regardless of any such asymmetry.
    cx = ox + P28_ARROW_ICON["x"] + P28_ARROW_ICON["w"] / 2

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
        _draw_icon(c, cx, oy + P02_CARD_H - PAD - 20 * mm, 24 * mm, accent_hex, "floral", draw_flower_icon)
        c.setFillColor(HexColor(INK))
        c.setFont("Times-Bold", 89)
        c.drawCentredString(cx, oy + P02_CARD_H * 0.52, order["house_number"])
        c.setFont("Times-Bold", 29)
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
# Animal family (12 scenes) -- width-dominant, shared icon box.
# ---------------------------------------------------------------------------
_ANIMAL_ICON_BOX = dict(x=20.0 * mm, y=68.0 * mm, w=240.0 * mm, h=120.5941 * mm)

DUCK_FATHER_ICON_MASTER = "assets/icons/duck_family_father_icon.png"
DUCK_FATHER_ICON = dict(_ANIMAL_ICON_BOX)

DUCK_FATHER_NUMBER_CENTER_Y = 34.0 * mm
DUCK_FATHER_STREET_CENTER_Y = 14.0 * mm

DUCK_FATHER_PAD = 3.4 * mm

ANIMAL_NUMBER_MAX_SIZE = 126
ANIMAL_NUMBER_MIN_SIZE = 49
ANIMAL_NUMBER_MAX_WIDTH = 220.0 * mm

ANIMAL_STREET_MAX_SIZE = 54
ANIMAL_STREET_MIN_SIZE = 19
ANIMAL_STREET_MAX_WIDTH = 244.0 * mm


def _animal_family_text(c, cx, oy, order):
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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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
        _draw_icon(c, cx, oy + P02_CARD_H * 0.62, 60 * mm, accent_hex, "paw", draw_paw_icon)

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

STYLE_LABELS = {
    "house_banner": "1. D01 — Cottage Bloom Banner (Large, DRAFT)",
    "p25_landscape_flourish": "2. D02 — Regency Double Flourish (Large, DRAFT)",
    "p25b_landscape_flourish": "3. D03 — Manor Frame Classic (Large, DRAFT)",
    "p27_landscape_house": "4. D04 — Homestead Silhouette (Large, DRAFT)",
    "p47_house": "5. P47 — House-outline + number, black-only (Large, DRAFT)",
    "p06_wreath": "6. P06 — Floral vine wreath, number + street (Large, DRAFT)",
    "p06_wreath_numbers": "7. P06 numbers-only (Large, DRAFT)",
    "p30_laurel_numbers": "8. P30 laurel wreath, numbers only (Large, DRAFT)",
    "p15_heart_wreath": "9. P15 heart-vine wreath (Large, DRAFT)",
    "p28_arrow_wreath": "10. P28 arrow/fletching wreath (Large, DRAFT)",
    "p31_olive_wreath": "11. P31 olive branch wreath (Large pilot, DRAFT)",
    "duck_family_father": "12. Duck Family, Scene 1 (Large, DRAFT)",
    "duck_family_mother": "13. Duck Family, Scene 2 (Large, DRAFT)",
    "duck_family_playing1": "14. Duck Family, Scene 3 (Large, DRAFT)",
    "duck_family_playing2": "15. Duck Family, Scene 4 (Large, DRAFT)",
    "dog_family_1": "16. Dog Family, Scene 1 (Large, DRAFT)",
    "dog_family_2": "17. Dog Family, Scene 2 (Large, DRAFT)",
    "dog_family_playing1": "18. Dog Family, Scene 3 (Large, DRAFT)",
    "dog_family_playing2": "19. Dog Family, Scene 4 (Large, DRAFT)",
    "cat_family_1": "20. Cat Family, Scene 1 (Large, DRAFT)",
    "cat_family_2": "21. Cat Family, Scene 2 (Large, DRAFT)",
    "cat_family_playing1": "22. Cat Family, Scene 3 (Large, DRAFT)",
    "cat_family_playing2": "23. Cat Family, Scene 4 (Large, DRAFT)",
    "p09a_borderless": "24. P09a — Borderless minimal (Large pilot, DRAFT)",
    "p21_paw_trail": "25. P21 — Paw trail (Large pilot, DRAFT)",
}

# No STYLE_PRODUCT_ID entries yet -- none of these have shipped as a
# catalogued Large product; every one is still pre-print-test.

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


# This size's real print layout: 1-per-A4, landscape page. Asymmetric
# 7mm/3mm horizontal margin (7mm left, 3mm right) -- same lamination-
# ease idea as Small's 14mm/3mm split, scaled down to fit Large's much
# smaller 10mm total horizontal slack (297-287=10mm, vs Small/Medium's
# 17mm). Applied on the LEFT/RIGHT axis, matching Small's own convention
# directly (both are single landscape cards, unlike Medium's vertical
# 2-up stack, which is why Medium's asymmetric margin went top/bottom
# instead). Vertical stays symmetric (margin_y=None -> auto-centred,
# 5mm top/bottom on a 210mm page) -- no lamination-direction reasoning
# applies to that axis, same as Small.
_PAGE_SIZE = landscape(A4)
LARGE_MARGIN_LEFT = 14 * mm  # matches Small exactly (297-14-3=280mm card width)


def render_sheet(orders, out_path, caption=False):
    """Fills one landscape A4 sheet, 1 card only (this size doesn't fit
    2-up in any orientation). Set caption=True to print the design's
    label in the bottom margin."""
    core.render_sheet_grid(
        orders, out_path, CARD_W, CARD_H, cols=1, rows=1,
        draw_fn=draw_sticker, page_size=_PAGE_SIZE,
        margin_x=LARGE_MARGIN_LEFT, caption=caption, style_labels=STYLE_LABELS,
    )


def render_gallery(style_keys, sample_order, out_path):
    """One sticker per style in style_keys, one per landscape A4 page."""
    core.render_gallery_grid(
        style_keys, sample_order, out_path, CARD_W, CARD_H,
        cols=1, rows=1, draw_fn=draw_sticker, page_size=_PAGE_SIZE,
        margin_x=LARGE_MARGIN_LEFT, style_labels=STYLE_LABELS,
    )


if __name__ == "__main__":
    sample_order = {"house_number": "28", "street_name": "North Avenue"}
    all_styles = list(STYLES.keys())
    out_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "bin_sticker_large_gallery.pdf")
    render_gallery(all_styles, sample_order, out_path)
    print(f"Wrote {out_path}")
