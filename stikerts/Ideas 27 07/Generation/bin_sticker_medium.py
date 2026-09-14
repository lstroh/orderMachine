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
    ONE factor (the axis the icon's bounding box is bound by -- always
    height for P21/P31 below, since both icons nearly fill the card's
    full height), never stretched non-uniformly -- stretching the actual
    artwork w x1.5 / h x1.4 independently would visibly distort it
    (circular paw pads becoming ovoid, etc.). The icon's ORIGIN (x, y)
    still uses the normal per-axis position scaling, since a position
    isn't a shape.
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
    draw_paw_icon,
    fit_font_size as _fit_font_size,
    TIMES_BOLD_CAP_HEIGHT_RATIO,
    HELVETICA_BOLD_CAP_HEIGHT_RATIO,
    INK,
    PAD,
)

CARD_W = 210 * mm
CARD_H = 140 * mm

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
P21_NUMBER_MAX_WIDTH = 91.665 * mm     # 61.11 x1.5
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
P31_OLIVE_NUMBER_MAX_WIDTH = 68.2413 * mm  # (50.5491*0.90) x1.5
P31_OLIVE_NUMBER_MAX_SIZE = 126            # pt -- 90 x1.4
P31_OLIVE_NUMBER_MIN_SIZE = 28             # pt -- 20 x1.4

P31_OLIVE_STREET_CENTER_Y = 49.7605 * mm   # 35.5432 x1.4
P31_OLIVE_STREET_MAX_WIDTH = 71.5433 * mm  # (52.9950*0.90) x1.5
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
STYLES = {
    "p09a_borderless": _style_p09a_borderless,
    "p21_paw_trail": _style_p21_paw_trail,
    "p31_olive_wreath": _style_p31_olive_wreath,
}

STYLE_LABELS = {
    "p09a_borderless": "P09a — Borderless minimal (Medium pilot, DRAFT)",
    "p21_paw_trail": "P21 — Paw trail (Medium pilot, DRAFT)",
    "p31_olive_wreath": "P31 — Olive branch wreath (Medium pilot, DRAFT)",
}

# No STYLE_PRODUCT_ID entries yet -- none of these have shipped as a
# catalogued Medium product; this is a pre-print-test pilot.

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
