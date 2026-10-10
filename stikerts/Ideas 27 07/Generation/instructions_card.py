"""
Instructions card — Doorstep Studio (Oct 2026)

One two-sided card in every bin sticker order, on every channel. Replaces
the old thank-you card (thankyou_card.py).

  Thank-you side      confetti, "Thank you!", the Doorstep Studio logo and
                      the footer (@handle · Made by hand in the UK).
  Instructions side   "How to apply your sticker": each of the six pictures
                      with its full step text beside it, then the QR code to
                      the unlisted how-to video and a neutral support line.

Sizes (render_sheet size=...):
  "A6"            105 x 148.5mm portrait, 4 per A4 -- Small (C6 envelope).
                  Instructions in one column of six rows.
  "A5_landscape"  210 x 148.5mm, 2 per A4 (stacked) -- Medium and Large
                  (C5 / C4 envelopes). Instructions in two columns of three.

No buyer name, no discount, no review request, no shop link: safe on eBay,
Etsy, Amazon and the own website. The personal thank-you (name, address,
design preview) is on the wrap (sticker_wrap.py, Small only).

QR code (9 Oct 2026): built with the `qrcode` package and drawn as solid black
squares (no image, so it stays sharp at any printer resolution). Error
correction Q (25% damage tolerance), QR version 3 (29 x 29 modules). Every
https://youtu.be/<11-character id> link is the same length, so any video gives
the same size code. The code area is 19mm on A6 (0.66mm per module) and 22mm on
A5 (0.76mm), with a clear 4-module quiet zone kept free of print on all sides.
Only YouTube links are accepted (see normalise_video_url) -- never the own
website while selling on eBay.

Text colour (10 Oct 2026): the secondary text (brand name, footer, support
line, QR placeholder) was a light grey (#888780, contrast 3.6:1 on white) and
was hard to read on the printed card. It is now a dark grey (#4D4C48, 8.6:1),
still lighter than the main text (#2C2C2A, 14:1) so the layout keeps its
hierarchy. Cut lines and the divider rules stay light on purpose.

Printing: render_sheet() writes a 2-page A4 PDF. Page 1 = thank-you side
(with dashed cut lines), page 2 = instructions side, laid out to line up when you
flip the sheet on its LONG edge. Every card is identical, so print in batches.

    python3 instructions_card.py                        sample sheets (dashed QR box)
    python3 instructions_card.py --url https://youtu.be/XXXXXXXXXXX
                                                        print PDFs with the real code
"""

import os
import re
import sys
from urllib.parse import urlparse, parse_qs

import qrcode
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor
from reportlab.pdfgen import canvas
from reportlab.pdfbase.pdfmetrics import stringWidth

HERE = os.path.dirname(os.path.abspath(__file__))
ICONS = os.path.join(HERE, "assets", "icons")
LOGO_MARK_PATH = os.path.join(ICONS, "doorstep_studio_mark.png")
CONFETTI_PATH = os.path.join(ICONS, "confetti_multi.png")
# A5 layer: confetti_multi.png tiled to 210 x 148.5mm with whole pieces removed
# from a central oval and the printer margins (made by make_confetti_a5.py)
CONFETTI_A5_PATH = os.path.join(ICONS, "confetti_a5_scatter.png")
STEP_IMG = [os.path.join(HERE, "assets", "instructions", f"step{i}.png") for i in range(1, 7)]

# The unlisted YouTube how-to video. Paste the link from YouTube Studio here
# (any form is fine: youtu.be, watch?v=, shorts/, with or without ?si=...;
# it is cleaned to https://youtu.be/<id> before encoding).
# While it is None the card prints a dashed "QR code goes here" box instead.
VIDEO_URL = "https://youtu.be/iQp-sMKErJk"  # unlisted Short on the Doorstep Studio channel, 10 Oct 2026, reviewed before printing

PAPER = {  # ink colours only; the card stock shows through
    # muted was #888780 (3.6:1 on white, too faint in print) -> #4D4C48 (8.6:1), 10 Oct 2026
    "white": {"text": "#2C2C2A", "muted": "#4D4C48", "guide": "#D3D1C7"},
    "kraft": {"text": "#3D2015", "muted": "#4A2E1A", "guide": "#8A6F3F"},
}
MAGENTA = "#E6007E"  # same as the PEEL THIS FIRST marker

QR_DARK = "#000000"      # pure black on white card: best contrast for phone cameras
QR_VERSION = 3           # 29 x 29 modules; fits any https://youtu.be/<id> at level Q
QR_QUIET = 4             # modules of clear space on every side (QR standard)
QR_MIN_MODULE_MM = 0.5   # refuse to print a code with smaller modules than this

A6_W, A6_H = 105 * mm, 148.5 * mm
A5L_W, A5L_H = 210 * mm, 148.5 * mm

# (short title, full text) — must match How-To-Apply-Instructions.md and the video
STEPS = [
    ("Clean and dry the bin", "Wash with soapy water and dry it fully. Best above about 10°C."),
    ("Peel the narrow strip", "Bend the sticker at the back cut and peel off the strip marked “PEEL THIS FIRST”."),
    ("Line it up and stick the top edge", "Press the exposed strip onto the bin."),
    ("Peel and smooth down", "Pull the rest of the backing away slowly, smoothing from the stuck edge downwards as you go."),
    ("Press the edges", "Press firmly along all edges and corners."),
    ("Wait 3 days before washing", "No washing or scrubbing for 3 days. Ordinary rain is fine."),
]
SUPPORT_LINE = "Any problems? Message us through the site you ordered from and we’ll put it right."


# ---- video link --------------------------------------------------------------

_YT_ID = re.compile(r"^[A-Za-z0-9_-]{11}$")


def normalise_video_url(url):
    """Return https://youtu.be/<id> for any YouTube link form, or None for None.

    Strips tracking parameters (the ?si=... that YouTube's Share button adds,
    which also ties the link to your account) and rejects anything that is not
    a YouTube video, so the card can never point at a shop or website."""
    if url is None:
        return None
    u = urlparse(url.strip())
    host = (u.hostname or "").lower()
    if host.startswith("www.") or host.startswith("m."):
        host = host.split(".", 1)[1]
    vid = None
    if host == "youtu.be":
        vid = u.path.strip("/").split("/")[0]
    elif host == "youtube.com":
        parts = [p for p in u.path.split("/") if p]
        if parts[:1] == ["watch"]:
            vid = parse_qs(u.query).get("v", [None])[0]
        elif len(parts) >= 2 and parts[0] in ("shorts", "embed", "live"):
            vid = parts[1]
    if not vid or not _YT_ID.match(vid):
        raise ValueError(f"Not a YouTube video link: {url!r}. The card QR code may only "
                         "point at the unlisted YouTube how-to video.")
    return f"https://youtu.be/{vid}"


def qr_matrix(url):
    """Boolean module matrix (no border) for the cleaned link, at level Q, version 3."""
    q = qrcode.QRCode(version=QR_VERSION, error_correction=qrcode.constants.ERROR_CORRECT_Q,
                      border=0)
    q.add_data(url)
    q.make(fit=False)  # raises if the link no longer fits version 3
    return q.get_matrix()


# ---- helpers ---------------------------------------------------------------

def wrap_text(text, font, size, max_width):
    words, lines, cur = text.split(), [], ""
    for w in words:
        trial = (cur + " " + w).strip()
        if stringWidth(trial, font, size) <= max_width:
            cur = trial
        else:
            lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines


def _rich_lines(title, body, size, width):
    """Bold title + regular body flowed together -> list of [(word, font), ...] lines."""
    words = [(w, "Helvetica-Bold") for w in (title + ".").split()] + \
            [(w, "Helvetica") for w in body.split()]
    space = stringWidth(" ", "Helvetica", size)
    lines, line, lw = [], [], 0
    for w, f in words:
        ww = stringWidth(w, f, size)
        if line and lw + space + ww > width:
            lines.append(line)
            line, lw = [], 0
        line.append((w, f))
        lw += (space if lw else 0) + ww
    lines.append(line)
    return lines


def _draw_rich(c, x, y, lines, size, lh, color):
    space = stringWidth(" ", "Helvetica", size)
    c.setFillColor(HexColor(color))
    for ln in lines:
        xx = x
        for w, f in ln:
            c.setFont(f, size)
            c.drawString(xx, y, w)
            xx += stringWidth(w, f, size) + space
        y -= lh


def _badge(c, x, y, r, n):
    """Magenta number disc centred on (x, y)."""
    c.setFillColor(HexColor(MAGENTA))
    c.circle(x, y, r, fill=1, stroke=0)
    c.setFillColor(HexColor("#FFFFFF"))
    c.setFont("Helvetica-Bold", r * 1.25)
    c.drawCentredString(x, y - r * 0.43, str(n))


def _guides(c, ox, oy, w, h, theme):
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.4)
    c.roundRect(ox, oy, w, h, 3 * mm, fill=0, stroke=1)
    tick = 3 * mm
    c.setLineWidth(0.3)
    for cx, cy, dx, dy in [(ox, oy, -tick, -tick), (ox + w, oy, tick, -tick),
                           (ox, oy + h, -tick, tick), (ox + w, oy + h, tick, tick)]:
        c.line(cx, cy, cx + dx, cy)
        c.line(cx, cy, cx, cy + dy)


def _cut_line(c, x1, y1, x2, y2):
    """Dashed grey cut line (printed on the thank-you side only)."""
    c.saveState()
    c.setStrokeColor(HexColor("#B4B2A9"))
    c.setLineWidth(0.5)
    c.setDash(3, 2.5)
    c.line(x1, y1, x2, y2)
    c.restoreState()


def _logo_lockup(c, cx, y, theme, size_pt=8, brand="Doorstep Studio"):
    icon = size_pt * 1.15
    gap = size_pt * 0.45
    w = icon + gap + stringWidth(brand, "Helvetica", size_pt)
    x = cx - w / 2
    if os.path.exists(LOGO_MARK_PATH):
        c.drawImage(LOGO_MARK_PATH, x, y - size_pt * 0.22, width=icon, height=icon,
                    mask="auto", preserveAspectRatio=True)
    c.setFillColor(HexColor(theme["muted"]))
    c.setFont("Helvetica", size_pt)
    c.drawString(x + icon + gap, y, brand)


def _confetti(c, x, y, size):
    if os.path.exists(CONFETTI_PATH):
        c.drawImage(CONFETTI_PATH, x, y, width=size, height=size, mask="auto", preserveAspectRatio=True)


def qr_quiet_zone(size):
    """Width of the 4-module quiet zone for a code `size` wide."""
    return QR_QUIET * size / (17 + 4 * QR_VERSION)


def _qr(c, x, y, size, url, theme):
    """QR code filling the square (x, y, size). The quiet zone lies OUTSIDE
    this square; callers keep qr_quiet_zone(size) clear on every side."""
    url = normalise_video_url(url)
    if url:
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
            while col < n:  # one rectangle per horizontal run: no hairline gaps between modules
                if row[col]:
                    start = col
                    while col < n and row[col]:
                        col += 1
                    c.rect(x + start * mod, yy, (col - start) * mod, mod, fill=1, stroke=0)
                else:
                    col += 1
        c.restoreState()
    else:
        c.setStrokeColor(HexColor(theme["muted"]))
        c.setDash(2, 2)
        c.setLineWidth(0.6)
        c.rect(x, y, size, size, fill=0, stroke=1)
        c.setDash()
        c.setFillColor(HexColor(theme["muted"]))
        c.setFont("Helvetica", 6)
        c.drawCentredString(x + size / 2, y + size / 2 + 1, "QR code")
        c.drawCentredString(x + size / 2, y + size / 2 - 6, "goes here")


def _check_clear(qr_size, gaps):
    """Fail loudly if anything printed would sit inside the quiet zone."""
    qz = qr_quiet_zone(qr_size)
    for name, gap in gaps.items():
        if gap < qz:
            raise AssertionError(f"QR quiet zone: {name} gap {gap / mm:.2f}mm < {qz / mm:.2f}mm")


def _footer(c, cx, y, order, theme, size=7):
    c.setFillColor(HexColor(theme["muted"]))
    c.setFont("Helvetica", size)
    c.drawCentredString(cx, y, f"@{order.get('handle', 'doorstepstudiouk')}  ·  Made by hand in the UK")


def _step_row(c, x, top, width, row_h, i, img, size, lh, theme):
    """Picture on the left, number + bold title + text on the right, centred in the row."""
    title, body = STEPS[i]
    c.drawImage(STEP_IMG[i], x, top - (row_h + img) / 2, width=img, height=img,
                mask="auto", preserveAspectRatio=True)
    r = size * 0.62
    tx = x + img + 3 * mm
    text_x = tx + 2 * r + 1.6 * mm
    lines = _rich_lines(title, body, size, x + width - text_x)
    block_h = (len(lines) - 1) * lh + size * 0.72
    first = top - (row_h - block_h) / 2 - size * 0.72
    _badge(c, tx + r, first + size * 0.36, r, i + 1)
    _draw_rich(c, text_x, first, lines, size, lh, theme["text"])


def _qr_row(c, x, y, right, qr_size, video_url, theme, big=False):
    """QR code with 'Watch the video', the scan line and the support line."""
    _qr(c, x, y, qr_size, video_url, theme)
    tx = x + qr_size + (5 if big else 3.5) * mm
    tw = right - tx
    s1, s2, s3 = (12, 9, 8.2) if big else (9.5, 7.4, 6.8)
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica-Bold", s1)
    top = y + qr_size
    c.drawString(tx, top - s1 * 1.0, "Watch the video")
    c.setFont("Helvetica", s2)
    c.drawString(tx, top - s1 * 1.0 - s2 * 1.6, "Scan for the 40-second guide.")
    c.setFillColor(HexColor(theme["muted"]))
    c.setFont("Helvetica", s3)
    yy = top - s1 * 1.0 - s2 * 1.6 - s3 * 2.2
    for ln in wrap_text(SUPPORT_LINE, "Helvetica", s3, tw):
        c.drawString(tx, yy, ln)
        yy -= s3 * 1.25
    return tx - (x + qr_size)


# ---- A6 portrait (Small) ---------------------------------------------------

def draw_thanks_a6(c, ox, oy, order):
    theme = PAPER[order.get("paper", "white")]
    W, H = A6_W, A6_H
    cx = ox + W / 2
    pad = 8 * mm
    span = W - 2 * pad
    _confetti(c, cx - span / 2, oy + H - pad - span, span)
    y = oy + H - pad - span - 13 * mm
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica-Bold", 26)
    c.drawCentredString(cx, y, "Thank you!")
    _logo_lockup(c, cx, y - 10 * mm, theme, size_pt=9.5)
    _footer(c, cx, oy + pad, order, theme)


def draw_instructions_a6(c, ox, oy, order, video_url=VIDEO_URL):
    theme = PAPER[order.get("paper", "white")]
    W, H = A6_W, A6_H
    pad = 6 * mm
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica-Bold", 12)
    c.drawCentredString(ox + W / 2, oy + H - pad - 4 * mm, "How to apply your sticker")

    qr_size = 19 * mm                      # code area; 0.66mm modules
    qr_y = oy + pad
    rule_y = qr_y + qr_size + 3.0 * mm     # divider clears the 2.6mm quiet zone
    rows_top = oy + H - pad - 7.5 * mm
    rows_bottom = rule_y + 1.5 * mm
    row_h = (rows_top - rows_bottom) / 6
    img = row_h - 1.2 * mm
    x, width = ox + pad, W - 2 * pad
    for i in range(6):
        _step_row(c, x, rows_top - i * row_h, width, row_h, i, img, 7.1, 3.0 * mm, theme)

    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.line(x, rule_y, x + width, rule_y)
    text_gap = _qr_row(c, x, qr_y, x + width, qr_size, video_url, theme)
    _check_clear(qr_size, {"left (card edge)": pad, "bottom (card edge)": pad,
                           "top (divider)": rule_y - (qr_y + qr_size), "right (text)": text_gap})


# ---- A5 landscape (Medium and Large) ---------------------------------------

def draw_thanks_a5(c, ox, oy, order):
    """Confetti all over (pre-built layer with a clear oval), words centred."""
    theme = PAPER[order.get("paper", "white")]
    W, H = A5L_W, A5L_H
    if os.path.exists(CONFETTI_A5_PATH):
        c.drawImage(CONFETTI_A5_PATH, ox, oy, width=W, height=H, mask="auto")
    cx, mid = ox + W / 2, oy + H / 2
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica-Bold", 34)
    c.drawCentredString(cx, mid + 3 * mm, "Thank you!")
    _logo_lockup(c, cx, mid - 9 * mm, theme, size_pt=11)
    _footer(c, cx, oy + 44 * mm, order, theme, size=8)


def draw_instructions_a5(c, ox, oy, order, video_url=VIDEO_URL):
    theme = PAPER[order.get("paper", "white")]
    W, H = A5L_W, A5L_H
    padx, pady = 10 * mm, 10 * mm
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica-Bold", 16)
    c.drawCentredString(ox + W / 2, oy + H - pady - 5 * mm, "How to apply your sticker")

    qr_size = 22 * mm                      # code area; 0.76mm modules
    qr_y = oy + pady
    rule_y = qr_y + qr_size + 3.5 * mm     # divider clears the 3.0mm quiet zone
    rows_top = oy + H - pady - 9.5 * mm
    rows_bottom = rule_y + 2 * mm
    row_h = (rows_top - rows_bottom) / 3
    img = row_h - 1.5 * mm
    gutter = 8 * mm
    col_w = (W - 2 * padx - gutter) / 2
    for col in range(2):
        x = ox + padx + col * (col_w + gutter)
        for k in range(3):
            _step_row(c, x, rows_top - k * row_h, col_w, row_h, col * 3 + k, img, 9.2, 3.9 * mm, theme)

    x = ox + padx
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.line(x, rule_y, ox + W - padx, rule_y)
    text_gap = _qr_row(c, x, qr_y, ox + W - padx, qr_size, video_url, theme, big=True)
    _check_clear(qr_size, {"left (card edge)": padx, "bottom (card edge)": pady,
                           "top (divider)": rule_y - (qr_y + qr_size), "right (text)": text_gap})


# ---- sheets ----------------------------------------------------------------

def render_sheet(orders, out_path, video_url=VIDEO_URL, size="A6"):
    """orders: dicts ({"paper": "white"|"kraft", "handle": str}).
    size "A6": 4 per A4 (Small). size "A5_landscape": 2 per A4, stacked
    (Medium and Large). Page 1 = thank-you side, page 2 = instructions side,
    for a long-edge flip."""
    video_url = normalise_video_url(video_url)  # fail before drawing anything
    c = canvas.Canvas(out_path, pagesize=A4)
    pw, ph = A4
    if size == "A6":
        orders = (list(orders) * 4)[:4] if orders else [{"paper": "white"}] * 4
        mx, my = (pw - 2 * A6_W) / 2, (ph - 2 * A6_H) / 2
        pos = [(mx, my + A6_H), (mx + A6_W, my + A6_H), (mx, my), (mx + A6_W, my)]
        for o, (x, y) in zip(orders, pos):
            draw_thanks_a6(c, x, y, o)
        # the 4 cards fill the sheet exactly, so the cuts are one cross through the middle
        _cut_line(c, mx + A6_W, 0, mx + A6_W, ph)
        _cut_line(c, 0, my + A6_H, pw, my + A6_H)
        c.showPage()
        for o, (x, y) in zip(orders, pos):
            draw_instructions_a6(c, pw - x - A6_W, y, o, video_url)  # columns swapped for the flip
        c.showPage()
    elif size == "A5_landscape":
        orders = (list(orders) * 2)[:2] if orders else [{"paper": "white"}] * 2
        pos = [(0, A5L_H), (0, 0)]  # two full-width cards; card edges are the paper edges
        for o, (x, y) in zip(orders, pos):
            draw_thanks_a5(c, x, y, o)
        _cut_line(c, 0, A5L_H, pw, A5L_H)  # the one cut: across the middle
        c.showPage()
        for o, (x, y) in zip(orders, pos):  # full width, so the flip keeps the same position
            draw_instructions_a5(c, x, y, o, video_url)
        c.showPage()
    else:
        raise ValueError('size must be "A6" or "A5_landscape"')
    c.save()


if __name__ == "__main__":
    url = sys.argv[sys.argv.index("--url") + 1] if "--url" in sys.argv else VIDEO_URL
    if url:
        # print-ready batch sheets, white card, real QR code
        a6 = os.path.join(HERE, "instructions_card_A6_print.pdf")
        a5 = os.path.join(HERE, "instructions_card_A5_print.pdf")
        render_sheet([{"paper": "white"}] * 4, a6, video_url=url)
        render_sheet([{"paper": "white"}] * 2, a5, video_url=url, size="A5_landscape")
        print("QR ->", normalise_video_url(url))
        print("wrote", a6, "and", a5)
    else:
        out = os.path.join(HERE, "instructions_card_sample.pdf")
        render_sheet([{"paper": "white"}, {"paper": "white"}, {"paper": "kraft"}, {"paper": "kraft"}], out)
        out5 = os.path.join(HERE, "instructions_card_A5_landscape_sample.pdf")
        render_sheet([{"paper": "white"}, {"paper": "kraft"}], out5, size="A5_landscape")
        print("wrote", out, "and", out5)
