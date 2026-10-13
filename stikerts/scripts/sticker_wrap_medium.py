"""
Personal sleeve wrap — MEDIUM (2-pack, 140mm tall) — Doorstep Studio
STATUS: TO BE TESTED (added 10 Oct 2026). Approved as a design, not yet
print-tested. See sticker_wrap_medium_README.md for the test checklist.

One whole, uncut A4 sheet wrapped round a Medium 2-pack the short way
(around the 140mm height). The two ends overlap by about 13mm on the back
and the top flap's end tucks under the bottom flap, so it opens and
recloses with no glue, tape or cutting. The two short sides stay open
(a sleeve, not a sealed parcel); the C5 envelope keeps everything in.

Personal to each order, like the Small wrap: "Made especially for" + the
address and a half-size preview of the exact design (drawn live by
bin_sticker_medium.draw_sticker) on the front; the how-to video QR code,
thank-you and care line on one back flap; the "Handmade Just For You"
badge on the other.

Separate from sticker_wrap.py on purpose (one script per size, so a change
here can't affect the Small wrap). It shares only the QR helpers and video
link from instructions_card.py, so every wrap and card points at the same
video.

Why no slit-lock like the Small band: the overlap sits in the first and
last 13mm of the sheet, inside the printer's 8.5mm top/bottom no-print
margin, so slit guides can't be printed there. The tuck needs no guide.
If a test shows it springs open, close it with the 40mm seal sticker.

Sheet layout (mm, A4 portrait, origin bottom-left; whole sheet = the wrap):
    y   0 –  76.5   bottom flap (back): QR + thank-you, care line
    y  76.5 – 78.5  bottom edge of the stack
    y  78.5 – 218.5 FRONT panel, full 210mm width
    y 218.5 – 220.5 top edge of the stack
    y 220.5 – 297   top flap (back): badge + "lift / tuck" line; its last
                    13mm tucks under the bottom flap
Small fold ticks at the side edges only; no cut lines (nothing to cut).
"""

import embedded_fonts  # embeds the fonts in every PDF (12 Oct 2026, see embedded_fonts.py)
import os

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor
from reportlab.lib.utils import simpleSplit, ImageReader
from reportlab.pdfgen import canvas
from reportlab.pdfbase.pdfmetrics import stringWidth

import bin_sticker_medium as bm   # MEDIUM only
from instructions_card import (VIDEO_URL, QR_DARK, QR_MIN_MODULE_MM,
                               normalise_video_url, qr_matrix, qr_quiet_zone)

HERE = os.path.dirname(os.path.abspath(__file__))
LOGO_MARK_PATH = os.path.join(HERE, "assets", "icons", "doorstep_studio_mark.png")
BADGE_PATH = os.path.join(HERE, "assets", "icons", "doorstep_handmade_sticker_40mm_transparent.png")

# all text near-black, as on the Small wrap; "guide" only for the fold ticks
PAPER = {
    "white": {"text": "#2C2C2A", "guide": "#5F5E5A"},
    "kraft": {"text": "#3D2015", "guide": "#5C4326"},
}

SHEET_W, SHEET_H = 210, 297
STACK = 2
PANEL_H = 140                                  # pack height the sleeve wraps round
FLAP = (SHEET_H - PANEL_H - 2 * STACK) / 2     # 76.5 each
FRONT_Y0 = FLAP + STACK                        # 78.5
FRONT_Y1 = FRONT_Y0 + PANEL_H                  # 218.5
TOP_FLAP_Y0 = FRONT_Y1 + STACK                 # 220.5
OVERLAP = 2 * FLAP - PANEL_H                   # 13
PRINT_MARGIN = 8.5                             # ET-3950 top/bottom no-print band

PREVIEW_SCALE = 0.5                            # 105 x 70mm preview of 210 x 140mm
CX = SHEET_W / 2

# back clear zones (outside the overlap and the no-print band)
BOTTOM_VIS = (OVERLAP + 4, FLAP - 4)           # y 17 – 72.5
TOP_VIS = (TOP_FLAP_Y0 + 4, SHEET_H - OVERLAP - 3)   # y 224.5 – 281
QR_SIZE_MM = 19
QR_EDGE_MM = 14
QR_TEXT_GAP_MM = 6


def _fit(text, font, max_size, min_size, max_width):
    size = max_size
    while size > min_size and stringWidth(text, font, size) > max_width:
        size -= 0.5
    return size


def _qr(c, x, y, size, url):
    m = qr_matrix(url)
    n = len(m)
    mod = size / n
    if mod < QR_MIN_MODULE_MM * mm:
        raise ValueError(f"QR modules {mod / mm:.2f}mm < {QR_MIN_MODULE_MM}mm minimum")
    c.saveState()
    c.setFillColor(HexColor(QR_DARK))
    for r, row in enumerate(m):
        yy = y + size - (r + 1) * mod
        col = 0
        while col < n:
            if row[col]:
                start = col
                while col < n and row[col]:
                    col += 1
                c.rect(x + start * mod, yy, (col - start) * mod, mod, fill=1, stroke=0)
            else:
                col += 1
    c.restoreState()


def _logo_lockup(c, cx, cy, theme, brand_name, mark_h=9 * mm):
    name = brand_name.upper()
    font, size = "Helvetica-Bold", 9
    gap = 2.5 * mm
    has_logo = os.path.exists(LOGO_MARK_PATH)
    total = stringWidth(name, font, size) + (mark_h + gap if has_logo else 0)
    x = cx - total / 2
    if has_logo:
        c.drawImage(LOGO_MARK_PATH, x, cy - mark_h / 2, width=mark_h, height=mark_h,
                    mask="auto", preserveAspectRatio=True)
        x += mark_h + gap
    c.setFillColor(HexColor(theme["text"]))
    c.setFont(font, size)
    c.drawString(x, cy - size * 0.36, name)


def _badge(c, cx, top, bottom):
    from PIL import Image
    im = Image.open(BADGE_PATH).convert("RGBA")
    im = im.crop(im.split()[-1].getbbox())
    h = top - bottom
    w = h * im.width / im.height
    c.drawImage(ImageReader(im), cx - w / 2, bottom, width=w, height=h, mask="auto")


def _fold_ticks(c, theme):
    """Short ticks at the side edges marking the four folds -- inside the
    printer's ~3mm side margin is too tight, so they run 4-14mm in, dark
    grey and 0.8pt so they show clearly on the print."""
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.8)
    for y in (FLAP, FRONT_Y0, FRONT_Y1, TOP_FLAP_Y0):
        c.line(4 * mm, y * mm, 14 * mm, y * mm)
        c.line((SHEET_W - 14) * mm, y * mm, (SHEET_W - 4) * mm, y * mm)


def _front(c, order, theme):
    top = FRONT_Y1 * mm
    fcx = CX * mm
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica", 9)
    c.drawCentredString(fcx, top - 10 * mm, "Made especially for")
    address = f"{order['house_number']} {order['street_name']}"
    c.setFont("Times-Bold", _fit(address, "Times-Bold", 20, 11, 170 * mm))
    c.drawCentredString(fcx, top - 19 * mm, address)

    pw, ph = bm.CARD_W * PREVIEW_SCALE, bm.CARD_H * PREVIEW_SCALE
    px, py = fcx - pw / 2, top - 25 * mm - ph
    c.saveState()
    clip = c.beginPath()
    clip.rect(px, py, pw, ph)
    c.clipPath(clip, stroke=0, fill=0)
    c.translate(px, py)
    c.scale(PREVIEW_SCALE, PREVIEW_SCALE)
    bm.draw_sticker(c, 0, 0, order)
    c.restoreState()
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.4)
    c.rect(px, py, pw, ph, fill=0, stroke=1)

    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica", 8.5)
    c.drawCentredString(fcx, py - 6.5 * mm,
                        f"Your set of {order.get('quantity', 2)}  ·  made by hand")
    _logo_lockup(c, fcx, py - 20 * mm, theme, order["brand_name"])


def _bottom_flap(c, order, theme):
    """QR + text, rotated 180 so it reads upright on the back (same as Small)."""
    url = normalise_video_url(order.get("video_url", VIDEO_URL))
    y0, y1 = BOTTOM_VIS
    cy = (y0 + y1) / 2
    half_w, half_h = SHEET_W / 2 * mm, (y1 - y0) / 2 * mm
    care = order.get("care_line", "Laminated to stand up to rain, sun and bin-day knocks.")
    footer = f"@{order['handle']}  ·  Recycle me with your paper"

    c.saveState()
    c.translate(CX * mm, cy * mm)
    c.rotate(180)
    if url:
        q = QR_SIZE_MM * mm
        qz = qr_quiet_zone(q)
        qx, qy = -half_w + QR_EDGE_MM * mm, -q / 2
        tx = qx + q + QR_TEXT_GAP_MM * mm
        for name, gap in {"edge": qx - qz + half_w, "top": half_h - (qy + q + qz),
                          "bottom": qy - qz + half_h, "text": tx - (qx + q) - qz}.items():
            if gap < 0:
                raise AssertionError(f"QR quiet zone overlaps the {name}")
        _qr(c, qx, qy, q, url)
    else:
        tx = -half_w + QR_EDGE_MM * mm
    tw = half_w - 12 * mm - tx
    lines = [("Helvetica-Bold", 10, f"Thank you, {order['buyer_name']}!"), None]
    lines += [("Helvetica", 8.5, l) for l in simpleSplit(care, "Helvetica", 8.5, tw)]
    if url:
        lines += [None, ("Helvetica-Bold", 8.5, "Need a hand putting them on?"),
                  ("Helvetica", 8.5, "Scan for the 40-second how-to video.")]
    lines += [None, ("Helvetica", 8, footer)]
    lead = lambda ln: (ln[1] if ln else 5) * 0.5 * mm
    y = sum(lead(ln) for ln in lines[1:]) / 2 - 1.2 * mm
    c.setFillColor(HexColor(theme["text"]))
    for i, ln in enumerate(lines):
        if i:
            y -= lead(ln)
        if ln:
            c.setFont(ln[0], ln[1])
            c.drawString(tx, y, ln[2])
    c.restoreState()


def _top_flap(c, order, theme):
    y0, y1 = TOP_VIS
    c.saveState()
    c.translate(CX * mm, (y0 + y1) / 2 * mm)
    c.rotate(180)
    half_h = (y1 - y0) / 2 * mm
    if os.path.exists(BADGE_PATH):
        _badge(c, 0, half_h - 4 * mm, -half_h + 14 * mm)
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica-Bold", 8.5)
    c.drawCentredString(0, -half_h + 7 * mm, "Lift this flap to open")
    c.setFont("Helvetica", 8)
    c.drawCentredString(0, -half_h + 3 * mm, "Tuck it back in to keep your spare sticker safe")
    c.restoreState()


def render_sheet(order, out_path):
    theme = PAPER[order.get("paper", "white")]
    c = canvas.Canvas(out_path, pagesize=A4)
    _fold_ticks(c, theme)
    _front(c, order, theme)
    _bottom_flap(c, order, theme)
    _top_flap(c, order, theme)
    c.showPage()
    c.save()


if __name__ == "__main__":
    render_sheet({"buyer_name": "Priya", "house_number": "36",
                  "street_name": "Grove Street", "style": "p09a_borderless",
                  "paper": "white", "brand_name": "Doorstep Studio",
                  "handle": "doorstepstudiouk"},
                 "sticker_wrap_medium_sample.pdf")
    print("overlap", OVERLAP, "mm; flaps", FLAP, "mm")
