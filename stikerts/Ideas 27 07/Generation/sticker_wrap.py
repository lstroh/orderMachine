"""
Personal full wrap generator — Doorstep Studio
A cross-shaped paper wrap that encloses a 4-pack of 140 x 100mm bin
stickers on all four sides. One wrap per A4 sheet.

Personal to each order: the front shows the customer's own address and a
half-size preview of the exact sticker design they ordered, drawn live by
bin_sticker_small.draw_sticker() -- so it always matches the stickers inside.
Deliberately NOT a copy of the instructions card; it only shares the paper
themes (white / kraft) and the how-to video QR code. Needs
bin_sticker_small.py + bin_sticker_core.py + instructions_card.py (and the
assets/ folder for the real icons) in the same folder.

QR code (10 Oct 2026): the back lower flap carries the same how-to video QR
code as the instructions card, so the link stays with the spare stickers
after the card is thrown away. It is built with instructions_card.py's own
helpers (normalise_video_url, qr_matrix, qr_quiet_zone) and its VIDEO_URL,
at the A6 card's size (19mm, 0.66mm modules, level Q, version 3), so the
wrap and the card can never point at different videos or follow different
rules. Only YouTube links are accepted. Set order["video_url"] = None to
leave the code off (the flap then prints its original centred text).

SMALL SIZE ONLY: the net is built around a 140 x 100mm front panel. Medium
(210 x 140mm) would need a different net that doesn't fit A4 or Cricut
Print Then Cut as this one does.

How it folds (back of the pack):
  1. Fold the two short SIDE flaps in (18mm each, plain, they sit underneath).
  2. Fold the LOWER flap up (58mm). It has a 42mm slot near its tip.
  3. Fold the UPPER flap down (50mm + 20mm tongue) and push the tongue
     into the slot. Pull the tongue out to open; push it back to reclose.
No glue or tape, 100% paper.

Net layout (mm, origin bottom-left, A4 portrait, long axis vertical):
    y   0 -  58  lower flap   (slot at y=12; QR code + thank-you text y 18-56)
    y  58 -  60  bottom edge of stack
    y  60 - 160  FRONT panel 140 x 100, x 20 - 160
    y 160 - 162  top edge of stack
    y 162 - 212  upper flap
    y 212 - 232  tongue, 20mm long x 40mm wide, chamfered corners
    side flaps x 0-18 and 162-180, alongside the front panel
Net: 180 x 232mm.
"""

import embedded_fonts  # embeds the fonts in every PDF (12 Oct 2026, see embedded_fonts.py)
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor
from reportlab.pdfgen import canvas
from reportlab.pdfbase.pdfmetrics import stringWidth

from reportlab.lib.utils import simpleSplit

import os

import bin_sticker_small as bs   # SMALL (140x100mm) only -- see module docstring

# How-to video QR code: the instructions card's own helpers and link, never a
# copy, so the wrap and the card always agree (see module docstring)
from instructions_card import (VIDEO_URL, QR_DARK, QR_MIN_MODULE_MM,
                               normalise_video_url, qr_matrix, qr_quiet_zone)

LOGO_MARK_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                              "assets", "icons", "doorstep_studio_mark.png")
BADGE_PATH = os.path.join(os.path.dirname(LOGO_MARK_PATH),
                          "doorstep_handmade_sticker_40mm_transparent.png")

# Ink themes, kept local so the wrap and the instructions card can change
# independently. Since 10 Oct 2026 every line of wrap text prints in the full
# text colour ("muted" = "text"): the old light grey (#888780) was hard to read
# at 7-7.5pt on inkjet paper. Hierarchy now comes from bold and size only.
# "guide" (the cut outline) stays light on purpose.
PAPER = {
    "white": {"text": "#2C2C2A", "muted": "#2C2C2A", "guide": "#D3D1C7"},
    "kraft": {"text": "#3D2015", "muted": "#3D2015", "guide": "#8A6F3F"},
}
PREVIEW_SCALE = 0.5          # 70 x 50mm preview of the 140 x 100mm sticker


def wrap_text(text, font, size, max_width):
    return simpleSplit(text, font, size, max_width)


def _fit(text, font, max_size, min_size, max_width):
    size = max_size
    while size > min_size and stringWidth(text, font, size) > max_width:
        size -= 0.5
    return size

STACK = 2
W, H = 140, 100                  # sticker
SIDE = 18                        # side tuck flaps -- 18mm keeps the net 180mm wide,
                                 # inside Cricut Print Then Cut's ~183mm A4 width limit
LOWER, UPPER, TONGUE = 58, 50, 20     # tongue 20mm (was 12) -- easier to grip
TONGUE_W, CHAMFER = 40, 5
SLOT_Y, SLOT_W = 12, 42

FX0 = SIDE + STACK               # front panel x0 = 20
FX1 = FX0 + W                    # 160
FY0 = LOWER + STACK              # 60
FY1 = FY0 + H                    # 160
NET_W = 2 * SIDE + 2 * STACK + W                      # 180
NET_H = LOWER + 2 * STACK + H + UPPER + TONGUE        # 232
CX = NET_W / 2

# Back lower flap: visible zone (clear of the slot, the upper-flap overlap
# and the fold) is sheet y 18-56, so centred on y 37 with 19mm either side.
LOWER_VIS_Y0, LOWER_VIS_Y1 = 18, 56
LOWER_CY = (LOWER_VIS_Y0 + LOWER_VIS_Y1) / 2           # 37
QR_SIZE_MM = 19              # same code area as the A6 instructions card (0.66mm modules)
QR_EDGE_MM = 8               # code's outer edge from the flap's side edge (quiet zone 2.6mm inside that)
QR_TEXT_GAP_MM = 5           # code to text; must be at least the 2.6mm quiet zone
TEXT_EDGE_MM = 6             # text block's right margin from the flap's side edge


def _outline_pts():
    """Cut outline of the net, clockwise from bottom-left of lower flap."""
    top = FY1 + STACK + UPPER          # 212
    t0, t1 = CX - TONGUE_W / 2, CX + TONGUE_W / 2
    return [
        (FX0, 0), (FX1, 0),                        # lower flap tip
        (FX1, FY0 - STACK), (FX1 + STACK, FY0),    # step out to side flap
        (NET_W, FY0), (NET_W, FY1),                # right side flap
        (FX1 + STACK, FY1), (FX1, FY1 + STACK),
        (FX1, top),                                # upper flap right edge
        (t1, top), (t1, NET_H - CHAMFER), (t1 - CHAMFER, NET_H),
        (t0 + CHAMFER, NET_H), (t0, NET_H - CHAMFER), (t0, top),   # tongue
        (FX0, top), (FX0, FY1 + STACK),
        (FX0 - STACK, FY1), (0, FY1), (0, FY0),    # left side flap
        (FX0 - STACK, FY0), (FX0, FY0 - STACK),
    ]


def _back_text(c, ox, oy, cx_mm, cy_mm, lines, theme, size=7.5):
    """Back-flap text, rotated 180 so it reads upright when turned over."""
    c.saveState()
    c.translate(ox + cx_mm * mm, oy + cy_mm * mm)
    c.rotate(180)
    lead = size * 0.5 * mm
    y = (len(lines) - 1) * lead / 2
    for font, colour, text in lines:
        c.setFont(font, size)
        c.setFillColor(HexColor(theme[colour]))
        c.drawCentredString(0, y, text)
        y -= lead
    c.restoreState()


def draw_wrap(c, ox, oy, order, cricut=False):
    """cricut=True leaves out the printed cut outline and slot line -- the
    Cricut cuts those, and a printed line would show if the cut is a hair off."""
    theme = PAPER[order.get("paper", "white")]
    P = lambda x, y: (ox + x * mm, oy + y * mm)

    if not cricut:
        _cut_lines(c, ox, oy, theme, P)
    # no printed fold lines -- folding round the stack sets the folds
    _content(c, ox, oy, order, theme)


def _cut_lines(c, ox, oy, theme, P):
    # cut outline
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.4)
    path = c.beginPath()
    pts = _outline_pts()
    path.moveTo(*P(*pts[0]))
    for p in pts[1:]:
        path.lineTo(*P(*p))
    path.close()
    c.drawPath(path, stroke=1, fill=0)

    # slot (cut with a craft knife)
    c.setStrokeColor(HexColor(theme["muted"]))
    c.setLineWidth(0.5)
    c.line(*P(CX - SLOT_W / 2, SLOT_Y), *P(CX + SLOT_W / 2, SLOT_Y))


def _logo_lockup(c, cx, cy, theme, brand_name, mark_h=8 * mm):
    """Logo mark beside the name in bold caps, centred on (cx, cy). If the
    logo file is missing, prints the name on its own so nothing breaks."""
    name = brand_name.upper()
    font, size = "Helvetica-Bold", 8
    gap = 2.5 * mm
    text_w = stringWidth(name, font, size)
    has_logo = os.path.exists(LOGO_MARK_PATH)
    total = text_w + (mark_h + gap if has_logo else 0)
    x = cx - total / 2
    if has_logo:
        c.drawImage(LOGO_MARK_PATH, x, cy - mark_h / 2, width=mark_h,
                    height=mark_h, mask="auto", preserveAspectRatio=True)
        x += mark_h + gap
    else:
        print(f"WARNING: logo not found at {LOGO_MARK_PATH} -- name only")
    c.setFillColor(HexColor(theme["text"]))
    c.setFont(font, size)
    c.drawString(x, cy - size * 0.36, name)


def _badge(c, cx, top, bottom):
    """The round-sticker artwork (tagline + mark + name), cropped to its
    visible content and fitted between top and bottom."""
    from PIL import Image
    from reportlab.lib.utils import ImageReader
    im = Image.open(BADGE_PATH).convert("RGBA")
    im = im.crop(im.split()[-1].getbbox())
    h = top - bottom
    w = h * im.width / im.height
    c.drawImage(ImageReader(im), cx - w / 2, bottom, width=w, height=h,
                mask="auto")


def _qr(c, x, y, size, url):
    """QR code filling the square (x, y, size), as solid black runs (same
    drawing method as the instructions card). The quiet zone lies OUTSIDE
    this square; the caller keeps qr_quiet_zone(size) clear."""
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
        while col < n:  # one rectangle per horizontal run: no hairline gaps
            if row[col]:
                start = col
                while col < n and row[col]:
                    col += 1
                c.rect(x + start * mod, yy, (col - start) * mod, mod, fill=1, stroke=0)
            else:
                col += 1
    c.restoreState()


def _lower_flap(c, ox, oy, order, theme):
    """Back lower flap: QR code on the left (as read on the back), thank-you,
    care line and the video prompt beside it. Without a link, the original
    centred text."""
    care_text = order.get("care_line",
                          "Laminated to stand up to rain, sun and bin-day knocks.")
    footer = f"@{order['handle']}  ·  Recycle me with your paper"
    url = normalise_video_url(order.get("video_url", VIDEO_URL))

    if not url:
        care = wrap_text(care_text, "Helvetica", 7.5, 110 * mm)
        _back_text(c, ox, oy, CX, LOWER_CY,
                   [("Helvetica-Bold", "text", f"Thank you, {order['buyer_name']}!")] +
                   [("Helvetica", "muted", "")] +
                   [("Helvetica", "muted", l) for l in care] +
                   [("Helvetica", "muted", ""), ("Helvetica", "muted", footer)],
                   theme)
        return

    # local frame: origin at the flap's visible centre, rotated 180 so x/y
    # run as the customer reads the back of the pack
    half_w, half_h = W / 2 * mm, (LOWER_VIS_Y1 - LOWER_VIS_Y0) / 2 * mm
    q = QR_SIZE_MM * mm
    qz = qr_quiet_zone(q)
    qx, qy = -half_w + QR_EDGE_MM * mm, -q / 2
    tx = qx + q + QR_TEXT_GAP_MM * mm
    tw = half_w - TEXT_EDGE_MM * mm - tx

    # fail loudly if the code's quiet zone would leave the clear zone or touch text
    gaps = {"flap edge": qx - qz + half_w, "top of clear zone": half_h - (qy + q + qz),
            "bottom of clear zone": (qy - qz) + half_h, "text": tx - (qx + q) - qz}
    for name, gap in gaps.items():
        if gap < 0:
            raise AssertionError(f"wrap QR quiet zone overlaps the {name} by {-gap / mm:.2f}mm")

    lines = [("Helvetica-Bold", 8.5, "text", f"Thank you, {order['buyer_name']}!"),
             None]
    lines += [("Helvetica", 7.5, "muted", l) for l in wrap_text(care_text, "Helvetica", 7.5, tw)]
    lines += [None,
              ("Helvetica-Bold", 7.5, "text", "Need a hand putting it on?"),
              ("Helvetica", 7.5, "text", "Scan for the 40-second how-to video."),
              None,
              ("Helvetica", 7, "muted", footer)]
    lead = lambda ln: (ln[1] if ln else 5) * 0.5 * mm
    block_h = sum(lead(ln) for ln in lines[1:])

    c.saveState()
    c.translate(ox + CX * mm, oy + LOWER_CY * mm)
    c.rotate(180)
    _qr(c, qx, qy, q, url)
    y = block_h / 2 - 1 * mm
    for i, ln in enumerate(lines):
        if i:
            y -= lead(ln)
        if ln:
            font, size, colour, text = ln
            c.setFont(font, size)
            c.setFillColor(HexColor(theme[colour]))
            c.drawString(tx, y, text)
    c.restoreState()


def _content(c, ox, oy, order, theme):
    # -- front panel: their address + a preview of their design -----------
    fcx = ox + CX * mm
    top = oy + FY1 * mm
    c.setFillColor(HexColor(theme["muted"]))
    c.setFont("Helvetica", 8)
    c.drawCentredString(fcx, top - 9 * mm, "Made especially for")

    address = f"{order['house_number']} {order['street_name']}"
    size = _fit(address, "Times-Bold", 17, 10, 120 * mm)
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Times-Bold", size)
    c.drawCentredString(fcx, top - 17 * mm, address)

    pw, ph = bs.P02_CARD_W * PREVIEW_SCALE, bs.P02_CARD_H * PREVIEW_SCALE
    px, py = fcx - pw / 2, top - 24 * mm - ph
    c.saveState()
    clip = c.beginPath()
    clip.rect(px, py, pw, ph)
    c.clipPath(clip, stroke=0, fill=0)
    c.translate(px, py)
    c.scale(PREVIEW_SCALE, PREVIEW_SCALE)
    bs.draw_sticker(c, 0, 0, order)
    c.restoreState()
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.4)
    c.rect(px, py, pw, ph, fill=0, stroke=1)

    # brand area under the preview: order["logo_style"]
    #   "mark"  (default) -- "Your set of 4 . made by hand" + logo beside name
    #   "badge" -- your stacked "Handmade Just For You" sticker artwork
    #   "both"  -- mark on the front, badge large on the back upper flap
    if order.get("logo_style", "mark") == "badge" and os.path.exists(BADGE_PATH):
        _badge(c, fcx, py - 2 * mm, oy + (FY0 + 2) * mm)
    else:
        c.setFillColor(HexColor(theme["muted"]))
        c.setFont("Helvetica", 7.5)
        c.drawCentredString(fcx, py - 6 * mm,
                            f"Your set of {order.get('quantity', 4)}  ·  made by hand")
        _logo_lockup(c, fcx, oy + (FY0 + 9) * mm, theme, order["brand_name"])

    # -- back: lower flap, visible area y 18-56 -----------------------------
    _lower_flap(c, ox, oy, order, theme)

    # -- back: upper flap, visible area y 164-210 ---------------------------
    if order.get("logo_style") == "both" and os.path.exists(BADGE_PATH):
        # badge 32mm tall, rotated to read upright on the back; the tab
        # instructions sit just above the tongue
        c.saveState()
        c.translate(ox + CX * mm, oy + 183 * mm)
        c.rotate(180)
        _badge(c, 0, 16 * mm, -16 * mm)
        c.restoreState()
        _back_text(c, ox, oy, CX, 206,
                   [("Helvetica-Bold", "text", "Pull the tab to open"),
                    ("Helvetica", "muted", "Tuck it back in to keep your spares safe")],
                   theme, size=7)
    else:
        _back_text(c, ox, oy, CX, 196,
                   [("Helvetica-Bold", "text", "Pull the tab to open"),
                    ("Helvetica", "muted", "Tuck it back in to keep your spares safe")], theme)


def _offset():
    pw, ph = A4
    return (pw - NET_W * mm) / 2, (ph - NET_H * mm) / 2


def render_sheet(order, out_path, cricut=False):
    """One wrap, centred on a portrait A4 sheet, for printing and cutting
    by hand. cricut=True drops the printed cut lines (see export_png)."""
    c = canvas.Canvas(out_path, pagesize=A4)
    draw_wrap(c, *_offset(), order, cricut=cricut)
    c.showPage()
    c.save()


def export_png(order, out_path, dpi=300):
    """Print Then Cut image for Cricut Design Space: the wrap cropped to its
    exact outline, transparent outside it and in the slot, so Design Space
    cuts the outline AND the slot. 180 x 232mm -- set exactly that size
    after uploading. Needs: pip install pymupdf pillow"""
    import io
    import pymupdf
    from PIL import Image, ImageDraw

    buf = io.BytesIO()
    render_sheet(order, buf, cricut=True)
    page = pymupdf.open(stream=buf.getvalue(), filetype="pdf")[0]
    ox, oy = _offset()
    ph = A4[1]
    clip = pymupdf.Rect(ox, ph - oy - NET_H * mm, ox + NET_W * mm, ph - oy)
    pix = page.get_pixmap(dpi=dpi, clip=clip)
    img = Image.open(io.BytesIO(pix.tobytes("png"))).convert("RGBA")

    px_per_mm = img.width / NET_W
    to_px = lambda x, y: (x * px_per_mm, (NET_H - y) * px_per_mm)
    mask = Image.new("L", img.size, 0)
    d = ImageDraw.Draw(mask)
    d.polygon([to_px(*p) for p in _outline_pts()], fill=255)
    # slot as a 1mm-tall hole so it becomes its own cut
    d.rectangle([to_px(CX - SLOT_W / 2, SLOT_Y + 0.5),
                 to_px(CX + SLOT_W / 2, SLOT_Y - 0.5)], fill=0)
    img.putalpha(mask)
    img.save(out_path, dpi=(dpi, dpi))
    return img.size


if __name__ == "__main__":
    order = {"buyer_name": "Priya", "house_number": "36",
             "street_name": "Grove Street", "style": "p25_landscape_flourish",
             "paper": "white", "brand_name": "Doorstep Studio",
             "handle": "doorstepstudiouk", "logo_style": "both"}
    render_sheet(order, "sticker_wrap_sample.pdf")
    export_png(order, "sticker_wrap_sample_cricut.png")
    print("net", NET_W, "x", NET_H, "mm  QR ->", normalise_video_url(VIDEO_URL))
