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
    draw_paw_icon,
    fit_font_size as _fit_font_size,
    TIMES_BOLD_CAP_HEIGHT_RATIO,
    HELVETICA_BOLD_CAP_HEIGHT_RATIO,
    INK,
    PAD,
)

CARD_W = 287 * mm
CARD_H = 200 * mm

# ---------------------------------------------------------------------------
# P09a -- borderless minimal. Pure text, no icon -- simplest of the 3
# pilot styles, scaled straight from Medium with no icon-distortion
# concerns at all.
# ---------------------------------------------------------------------------
P09A_NUMBER_CENTER_Y = 137.1866 * mm
P09A_UNDERLINE_CENTER_Y = 99.8609 * mm
P09A_STREET_CENTER_Y = 71.17 * mm

P09A_NUMBER_MAX_WIDTH = 225.5 * mm
P09A_NUMBER_MAX_SIZE = 280
P09A_NUMBER_MIN_SIZE = 40

P09A_STREET_MAX_WIDTH = 246.0 * mm
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
P21_ICON = dict(x=15.3126 * mm, y=15.5171 * mm, w=115.6709 * mm, h=171.5519 * mm)

P21_NUMBER_CENTER_X = 203.458 * mm
P21_NUMBER_CENTER_Y = 124.6319 * mm
P21_NUMBER_MAX_WIDTH = 130.4541 * mm  # geometric recompute, see module docstring
P21_NUMBER_MAX_SIZE = 254
P21_NUMBER_MIN_SIZE = 40

P21_STREET_CENTER_X = 203.458 * mm
P21_STREET_CENTER_Y = 49.5154 * mm
P21_STREET_MAX_WIDTH = 139.4 * mm  # independent (distance to card edge), width-axis
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
P31_OLIVE_ICON = dict(x=50.3803 * mm, y=2.8401 * mm, w=182.396 * mm, h=190.7691 * mm)

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
STYLES = {
    "p09a_borderless": _style_p09a_borderless,
    "p21_paw_trail": _style_p21_paw_trail,
    "p31_olive_wreath": _style_p31_olive_wreath,
}

STYLE_LABELS = {
    "p09a_borderless": "P09a — Borderless minimal (Large pilot, DRAFT)",
    "p21_paw_trail": "P21 — Paw trail (Large pilot, DRAFT)",
    "p31_olive_wreath": "P31 — Olive branch wreath (Large pilot, DRAFT)",
}

# No STYLE_PRODUCT_ID entries yet -- none of these have shipped as a
# catalogued Large product; this is a pre-print-test pilot.

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
LARGE_MARGIN_LEFT = 7 * mm


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
