"""
Personal cover sheet — LARGE (single 280 x 200mm sticker) — Doorstep Studio
STATUS: TO BE TESTED (added 10 Oct 2026). Approved as a design, not yet
print-tested. See sticker_cover_large_README.md for the test checklist.

Packing (chosen 10 Oct 2026): the sticker and this cover sheet go together
into a 220 x 310mm peel-and-seal GLASSINE bag (paper, recyclable), cover
sheet on top and facing out, so it shows through the bag. No label. The
bag then goes into the C4 board-backed envelope with the A5 card.

One A4 sheet, portrait, printed one side. Nothing to cut or fold. It covers
the sticker's printed face (the sticker sits 200 wide x 280 tall, inside
A4's 210 x 297). Personal to each order, like the Small and Medium wraps:
"Made especially for" + the address and a preview of the exact design;
then the thank-you, care line, the how-to video QR code (from
instructions_card.py, so it matches every card and wrap) and the
"Handmade Just For You" badge.

Preview: drawn by bin_sticker_large.draw_sticker when a Large sticker
script exists. There isn't one in the project yet (10 Oct 2026), so until
then the preview falls back to a plain number-and-street box and prints a
warning. Everything printed stays at least 10mm inside the sheet, clear of
the printer's 8.5mm top/bottom margin.
"""

import embedded_fonts  # embeds the fonts in every PDF (12 Oct 2026, see embedded_fonts.py)
import os

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor
from reportlab.lib.utils import simpleSplit, ImageReader
from reportlab.pdfgen import canvas
from reportlab.pdfbase.pdfmetrics import stringWidth

try:
    import bin_sticker_large as bl   # LARGE only -- not in the project yet
except ImportError:
    bl = None
from instructions_card import (VIDEO_URL, QR_DARK, QR_MIN_MODULE_MM,
                               normalise_video_url, qr_matrix, qr_quiet_zone)

HERE = os.path.dirname(os.path.abspath(__file__))
LOGO_MARK_PATH = os.path.join(HERE, "assets", "icons", "doorstep_studio_mark.png")
BADGE_PATH = os.path.join(HERE, "assets", "icons", "doorstep_handmade_sticker_40mm_transparent.png")

PAPER = {
    "white": {"text": "#2C2C2A", "guide": "#B4B2A9"},
    "kraft": {"text": "#3D2015", "guide": "#8A6F3F"},
}
W, H = 210, 297
CX = W / 2
PREVIEW_SCALE = 0.4      # 80 x 112mm preview of the 200 x 280mm sticker
LARGE_W, LARGE_H = 200 * mm, 280 * mm    # used when bin_sticker_large.py is missing
QR_SIZE_MM = 22          # same as the A5 card that Large orders get (0.76mm modules)


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


def _logo_lockup(c, cx, cy, theme, brand_name, mark_h=10 * mm):
    name = brand_name.upper()
    font, size = "Helvetica-Bold", 10
    gap = 3 * mm
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


def _plain_preview(c, pw, ph, order, ink):
    """Stand-in preview until a Large sticker script exists: rounded box,
    house number, street name."""
    c.setStrokeColor(ink)
    c.setLineWidth(1.5)
    c.roundRect(3 * mm, 3 * mm, pw - 6 * mm, ph - 6 * mm, 4 * mm, fill=0, stroke=1)
    c.setFillColor(ink)
    c.setFont("Times-Bold", _fit(order["house_number"], "Times-Bold", 100, 30, pw - 14 * mm))
    c.drawCentredString(pw / 2, ph * 0.46, order["house_number"])
    street = order["street_name"].upper()
    c.setFont("Times-Bold", _fit(street, "Times-Bold", 24, 8, pw - 14 * mm))
    c.drawCentredString(pw / 2, ph * 0.25, street)


def render_sheet(order, out_path):
    theme = PAPER[order.get("paper", "white")]
    ink = HexColor(theme["text"])
    c = canvas.Canvas(out_path, pagesize=A4)
    fcx = CX * mm

    # -- top: who it's for + the design ---------------------------------
    c.setFillColor(ink)
    c.setFont("Helvetica", 10)
    c.drawCentredString(fcx, 276 * mm, "Made especially for")
    address = f"{order['house_number']} {order['street_name']}"
    c.setFont("Times-Bold", _fit(address, "Times-Bold", 26, 14, 180 * mm))
    c.drawCentredString(fcx, 264 * mm, address)

    card_w, card_h = (bl.CARD_W, bl.CARD_H) if bl else (LARGE_W, LARGE_H)
    pw, ph = card_w * PREVIEW_SCALE, card_h * PREVIEW_SCALE
    px, py = fcx - pw / 2, 254 * mm - ph
    c.saveState()
    clip = c.beginPath()
    clip.rect(px, py, pw, ph)
    c.clipPath(clip, stroke=0, fill=0)
    c.translate(px, py)
    if bl:
        c.scale(PREVIEW_SCALE, PREVIEW_SCALE)
        bl.draw_sticker(c, 0, 0, order)
    else:
        print("WARNING: bin_sticker_large.py not found -- cover sheet shows a "
              "plain number-and-street preview, not the real design")
        _plain_preview(c, pw, ph, order, ink)
    c.restoreState()
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.rect(px, py, pw, ph, fill=0, stroke=1)

    c.setFillColor(ink)
    c.setFont("Helvetica", 9.5)
    c.drawCentredString(fcx, py - 7 * mm, "Your sticker  ·  made by hand")
    _logo_lockup(c, fcx, py - 18 * mm, theme, order["brand_name"])
    c.setFillColor(ink)

    # divider
    rule_y = py - 28 * mm
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.line(30 * mm, rule_y, 180 * mm, rule_y)

    # -- middle: QR + thank-you -----------------------------------------
    url = normalise_video_url(order.get("video_url", VIDEO_URL))
    q = QR_SIZE_MM * mm
    qx, qtop = 30 * mm, rule_y - 9 * mm
    tx = qx + (q + 8 * mm if url else 0)
    if url:
        if 9 * mm < qr_quiet_zone(q) or 8 * mm < qr_quiet_zone(q):
            raise AssertionError("QR quiet zone overlaps the divider or text")
        _qr(c, qx, qtop - q, q, url)
    care = order.get("care_line", "Laminated to stand up to rain, sun and bin-day knocks.")
    lines = [("Helvetica-Bold", 13, f"Thank you, {order['buyer_name']}!"), None]
    lines += [("Helvetica", 10, l) for l in simpleSplit(care, "Helvetica", 10, 180 * mm - tx)]
    if url:
        lines += [None, ("Helvetica-Bold", 10, "Need a hand putting it on?"),
                  ("Helvetica", 10, "Scan for the 40-second how-to video.")]
    y = qtop - 13 * 0.36 * mm * 2
    for i, ln in enumerate(lines):
        if i:
            y -= (ln[1] if ln else 6) * 0.5 * mm
        if ln:
            c.setFont(ln[0], ln[1])
            c.drawString(tx, y, ln[2])

    # -- bottom: badge + footer -----------------------------------------
    if os.path.exists(BADGE_PATH):
        _badge(c, fcx, 50 * mm, 22 * mm)
    c.setFillColor(ink)
    c.setFont("Helvetica", 9)
    c.drawCentredString(fcx, 14 * mm,
                        f"@{order['handle']}  ·  Recycle me with your paper")
    c.showPage()
    c.save()


if __name__ == "__main__":
    render_sheet({"buyer_name": "Priya", "house_number": "36",
                  "street_name": "Grove Street", "paper": "white",
                  "brand_name": "Doorstep Studio", "handle": "doorstepstudiouk"},
                 "sticker_cover_large_sample.pdf")
    print("ok")
