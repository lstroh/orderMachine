"""
Thank-you card generator — Doorstep Studio
Draws one A6 (105 x 148.5mm) card per call, 4-up on an A4 sheet.
This is the reusable core the future skill will call per order.
"""

import os
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor
from reportlab.pdfgen import canvas
from reportlab.pdfbase.pdfmetrics import stringWidth

ASSET_DIR = os.path.join(os.path.dirname(__file__), "assets", "icons")
LOGO_MARK_PATH = os.path.join(ASSET_DIR, "doorstep_studio_mark.png")

# ---- paper themes -------------------------------------------------------
# NOTE: these are INK colours only. The card background is deliberately left
# unfilled (transparent) — the paper's own colour (white or kraft cardstock)
# shows through underneath. We never print a fake coloured background; that
# would waste ink and defeat the point of buying coloured stock in the first
# place. Paper choice only changes which ink colours stay legible.
PAPER = {
    "white": {"text": "#2C2C2A", "muted": "#888780", "guide": "#D3D1C7"},
    "kraft": {"text": "#3D2015", "muted": "#5C4326", "guide": "#8A6F3F"},
}

# Same 5-colour palette as before (was FLOWER_COLORS) — now used to recolour
# the confetti scatter instead of the old 4-petal flower. Kept as one hex
# per name (not petal/center pairs) since confetti has no "centre" concept.
ACCENT_COLORS = {
    "blush":      "#D4537E",
    "sage":       "#639922",
    "dusty_blue": "#378ADD",
    "terracotta": "#D85A30",
    "lavender":   "#7F77DD",
}

# ---- confetti art ---------------------------------------------------------
# Source: a Midjourney render (69 individual confetti shapes: circles, small
# rectangular flecks, stars) recoloured via the icon-silhouette-extraction
# skill's separate_ink()/label_components() pipeline -- luminance-separated
# from the white background, each of the 69 shapes labelled as its own
# connected component, then recoloured either:
#   - "multi": each shape gets one of the 5 ACCENT_COLORS in rotation
#     (assets/icons/confetti_multi.png, pre-baked, same file for every order)
#   - a single accent name: every shape recoloured to that one colour
#     (assets/icons/confetti_{name}.png, pre-baked per ACCENT_COLORS entry)
# Both modes are pre-generated and committed as static assets rather than
# recoloured at render time, since (unlike the old single-shape flower,
# which was cheap to redraw as vectors) this is a 69-component raster
# recolour -- not worth repeating per PDF render when the same 6 outputs
# (1 multi + 5 single) cover every order.
#
# order["confetti_color"] selects the file: "multi" or any ACCENT_COLORS key.
# Also used to tint the numbered-list digits on the back of the card --
# see draw_card().
CONFETTI_MULTI_PATH = os.path.join(ASSET_DIR, "confetti_multi.png")


def confetti_path(confetti_color):
    if confetti_color == "multi":
        return CONFETTI_MULTI_PATH
    if confetti_color not in ACCENT_COLORS:
        raise ValueError(
            f"confetti_color must be 'multi' or one of {list(ACCENT_COLORS)}, got {confetti_color!r}"
        )
    return os.path.join(ASSET_DIR, f"confetti_{confetti_color}.png")


CARD_W, CARD_H = 105 * mm, 148.5 * mm  # A6, exactly 1/4 of A4 -- default/original size

# ---- card sizes ---------------------------------------------------------
# C4/C5 are the ISO envelopes for A4/A5 respectively (same relationship the
# original C6 envelope already has with this A6 card) -- so rather than
# picking arbitrary bigger dimensions, bigger orders just get the matching
# larger A-series card. Everything below scales proportionally off the
# original A6 design (SCALE = this size's height / A6's height) so a bigger
# card reads as a deliberately bigger version of the same design, not the
# same small card stretched onto more empty paper.
CARD_SIZES = {
    "A6": (105 * mm, 148.5 * mm),  # -> C6 envelope (current default)
    "A5": (148 * mm, 210 * mm),    # -> C5 envelope
    "A4": (210 * mm, 297 * mm),    # -> C4 envelope
}
BASE_CARD_H = CARD_SIZES["A6"][1]

# ---- the card ---------------------------------------------------------
# Rewritten Aug 2026: bin stickers are effectively the whole business
# right now, so this is deliberately ONE fixed card for every order --
# no per-product template switch, no per-client personalisation (name,
# house number). It replaces the earlier multi-product/personalised
# version (product_type + detail + name/detail highlighting), which is
# still worth reviving as a separate card if/when other product lines
# (name labels, decals, packaging labels) pick up real volume -- see
# git history / prior conversation for that version rather than trying
# to keep both live at once.
APPLICATION_STEPS = [
    # Written for the peel-tab back cut: a slit in the backing near the top
    # long edge, so the customer sticks that edge first as a hinge, then
    # pulls the rest of the backing out from underneath (Sep 2026).
    "Clean the bin with soapy water and dry it fully. Best above about 10\u00b0C.",
    "Bend the sticker at the back cut and peel off the narrow strip of backing.",
    "Line up that edge and press the exposed strip onto the bin.",
    "Pull the rest of the backing away slowly, smoothing the sticker down from the stuck edge as you go.",
    "Press firmly along all edges and corners.",
    "Leave it 3 days before washing or scrubbing. Ordinary rain is fine.",
]


def wrap_text(text, font, size, max_width):
    words, lines, current = text.split(), [], ""
    for w in words:
        trial = (current + " " + w).strip()
        if stringWidth(trial, font, size) <= max_width:
            current = trial
        else:
            lines.append(current)
            current = w
    if current:
        lines.append(current)
    return lines


def draw_numbered_list(c, x, top_y, width, items, font, size, line_h, number_color, text_color):
    """Draws a numbered list with a hanging indent -- wrapped continuation
    lines align under the text, not under the number, same convention as
    any normal numbered list. Returns the y coordinate just below the
    last line, so the caller knows where it's safe to draw next."""
    indent = stringWidth("9. ", font, size)  # widest single-digit prefix
    y = top_y
    for i, item in enumerate(items, start=1):
        lines = wrap_text(item, font, size, width - indent)
        c.setFillColor(HexColor(number_color))
        c.setFont(f"{font}-Bold", size)
        c.drawString(x, y, f"{i}.")
        c.setFillColor(HexColor(text_color))
        c.setFont(font, size)
        for j, line in enumerate(lines):
            c.drawString(x + indent, y, line)
            if j < len(lines) - 1:
                y -= line_h
        y -= line_h * 1.15  # gap between steps
    return y


def draw_card(c, ox, oy, order, card_w=CARD_W, card_h=CARD_H):
    """
    ox, oy = bottom-left corner of this card's cell on the page (points)
    card_w, card_h = this card's size -- see CARD_SIZES. Every measurement
        below is scaled by SCALE = card_h / BASE_CARD_H, so A5/A4 cards are
        a proportional scale-up of the original A6 design, not the same
        fixed-size elements just spaced further apart on a bigger sheet.
    order  = dict with keys:
        buyer_name      str
        channel         "ebay" | "etsy" | "website"
        paper           "white" | "kraft"  -- selects INK colours only;
                        load the matching physical cardstock in the printer
        confetti_color  "multi" or a key into ACCENT_COLORS -- see confetti
                        art note above
        brand_name      str
        handle          str
    """
    theme = PAPER[order["paper"]]
    scale = card_h / BASE_CARD_H

    # No fill here — the card sits on whatever cardstock is loaded in the
    # printer (white or kraft). Only a thin cut-guide outline is printed.
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.4)
    c.roundRect(ox, oy, card_w, card_h, 3 * mm * scale, fill=0, stroke=1)

    # cut marks (small ticks just outside the card corners)
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.3)
    tick = 3 * mm * scale
    for cx, cy, dx, dy in [
        (ox, oy, -tick, -tick), (ox + card_w, oy, tick, -tick),
        (ox, oy + card_h, -tick, tick), (ox + card_w, oy + card_h, tick, tick),
    ]:
        c.line(cx, cy, cx + dx, cy)
        c.line(cx, cy, cx, cy + dy)

    pad = 8 * mm * scale
    cx_center = ox + card_w / 2

    # -- front half (top) --------------------------------------------------
    # Confetti sits in its own band ABOVE the text, not directly behind it.
    # An earlier version placed a full-width confetti square right behind
    # "Thank you!" and individual shapes ended up sitting on top of/right
    # next to letters in the rendered preview (a small dot landed between
    # "Thank" and "you!", a circle overlapped the "!") -- readable since
    # text draws on top, but messy. Stacking a clean confetti band above a
    # clear text zone avoids that without needing to mask the art itself.
    mid_y = oy + card_h / 2
    top_edge_y = oy + card_h - pad
    text_zone_h = 19 * mm * scale  # "Thank you!" line + gap + brand line + clearance above divider
    gap_below_confetti = 4 * mm * scale
    available_w = card_w - 2 * pad
    available_h = top_edge_y - (mid_y + 2 * mm * scale) - text_zone_h - gap_below_confetti
    confetti_span = min(available_w, available_h)
    confetti_x = cx_center - confetti_span / 2
    confetti_y = top_edge_y - confetti_span
    c.drawImage(
        confetti_path(order["confetti_color"]),
        confetti_x, confetti_y, width=confetti_span, height=confetti_span,
        mask="auto", preserveAspectRatio=True,
    )

    text_top_y = confetti_y - gap_below_confetti
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica-Bold", 16 * scale)
    c.drawCentredString(cx_center, text_top_y - 5 * mm * scale, "Thank you!")

    # Compact brand lockup: icon beside the name, not the full stacked
    # logo (icon above name) used on the round sticker. Side-by-side reads
    # fine at this small a size and keeps the front-of-card brand line to
    # one row rather than growing the confetti-to-divider layout that was
    # already tuned for a single line of muted text.
    c.setFillColor(HexColor(theme["muted"]))
    brand_font_size = 8 * scale
    c.setFont("Helvetica", brand_font_size)
    brand_text = order["brand_name"]
    brand_y = text_top_y - 12 * mm * scale
    icon_size = 3.2 * mm * scale
    icon_gap = 1.3 * mm * scale
    text_w_brand = stringWidth(brand_text, "Helvetica", brand_font_size)
    lockup_w = icon_size + icon_gap + text_w_brand
    lockup_x = cx_center - lockup_w / 2
    # icon's own vertical centre lines up with the text's cap-height centre,
    # not its baseline -- text sits a touch lower than the icon without this
    c.drawImage(
        LOGO_MARK_PATH, lockup_x, brand_y - 0.3 * mm * scale, width=icon_size, height=icon_size,
        mask="auto", preserveAspectRatio=True,
    )
    c.drawString(lockup_x + icon_size + icon_gap, brand_y, brand_text)

    # divider between front and back halves
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.setDash(2, 2)
    c.line(ox + pad, mid_y, ox + card_w - pad, mid_y)
    c.setDash()

    # -- back half (bottom) -------------------------------------------------
    text_x = ox + pad
    text_w = card_w - 2 * pad
    top_y = mid_y - 8 * mm * scale

    accent_hex = ACCENT_COLORS.get(order["confetti_color"], ACCENT_COLORS["terracotta"])

    c.setFillColor(HexColor(theme["text"]))
    heading_size = 12 * scale
    c.setFont("Helvetica-Bold", heading_size)
    c.drawString(text_x, top_y, "How to apply your sticker")

    list_top_y = top_y - 7 * mm * scale
    draw_numbered_list(
        c, text_x, list_top_y, text_w, APPLICATION_STEPS,
        "Helvetica", 7.6 * scale, 3.4 * mm * scale, accent_hex, theme["text"],
    )

    footer_y = oy + pad + 10 * mm * scale
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.line(text_x, footer_y + 6 * mm * scale, text_x + text_w, footer_y + 6 * mm * scale)

    c.setFillColor(HexColor(theme["muted"]))
    c.setFont("Helvetica", 7.5 * scale)
    c.drawString(text_x, footer_y, f"@{order['handle']}  \u00b7  Made by hand in the UK")


def draw_smoothing_diagram(c, cx, cy, size, max_width, sticker_color, line_color, label_color):
    """Pictogram of the hinge method (step 4): a landscape sticker with its
    top edge already stuck down (darker band), and three arrows running
    down from that edge -- the direction the customer smooths while pulling
    the backing out. Replaces the earlier centre-outward version, which
    matched the old peel-from-a-corner steps.
    Plain vector shapes, so it recolours per order and stays crisp.
    cx, cy    = centre of the sticker shape
    size      = sticker height (width is 1.4x, matching a 140x100mm card)
    max_width = wrap width for the caption, independent of size
    """
    h = size
    w = size * 1.4
    left, bottom = cx - w / 2, cy - h / 2
    top = bottom + h

    c.setFillColor(HexColor(sticker_color))
    c.roundRect(left, bottom, w, h, h * 0.08, fill=1, stroke=0)

    # stuck top edge -- the hinge strip
    band_h = h * 0.16
    c.saveState()
    clip = c.beginPath()
    clip.roundRect(left, bottom, w, h, h * 0.08)
    c.clipPath(clip, stroke=0, fill=0)  # keep the band inside the rounded corners
    c.setFillColor(HexColor(line_color))
    c.setFillAlpha(0.35)
    c.rect(left, top - band_h, w, band_h, fill=1, stroke=0)
    c.restoreState()

    c.setStrokeColor(HexColor(line_color))
    c.setFillColor(HexColor(line_color))
    c.setLineWidth(1.3)
    head = h * 0.1
    for fx in (-0.28, 0, 0.28):
        x = cx + fx * w
        y1 = top - band_h - h * 0.08
        y2 = bottom + h * 0.1
        c.line(x, y1, x, y2 + head * 0.6)
        path = c.beginPath()
        path.moveTo(x, y2)
        path.lineTo(x - head * 0.55, y2 + head)
        path.lineTo(x + head * 0.55, y2 + head)
        path.close()
        c.drawPath(path, fill=1, stroke=0)

    label_font, label_size = "Helvetica-Oblique", 7.2
    c.setFillColor(HexColor(label_color))
    c.setFont(label_font, label_size)
    y = bottom - label_size * 1.6
    for line in wrap_text("stick the top edge, then smooth down", label_font, label_size, max_width):
        c.drawCentredString(cx, y, line)
        y -= label_size * 1.25


def draw_card_landscape(c, ox, oy, order, card_w, card_h):
    """Landscape layout: confetti/greeting on the left half, application
    instructions on the right half, split by a vertical divider instead of
    draw_card()'s horizontal one. Built for A5 landscape (210 x 148.5mm) --
    exactly two of the portrait design's 105 x 148.5mm halves side by side,
    which is also the only way two A5s tile cleanly onto one A4 sheet (see
    render_sheet's docstring). Each half gets the FULL card height to
    itself here, rather than sharing it with the other half the way
    draw_card()'s top/bottom split does -- more room for the confetti and
    the list to breathe, not just the same content sideways."""
    theme = PAPER[order["paper"]]
    scale = card_h / BASE_CARD_H
    half_w = card_w / 2

    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.4)
    c.roundRect(ox, oy, card_w, card_h, 3 * mm * scale, fill=0, stroke=1)

    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.3)
    tick = 3 * mm * scale
    for cx, cy, dx, dy in [
        (ox, oy, -tick, -tick), (ox + card_w, oy, tick, -tick),
        (ox, oy + card_h, -tick, tick), (ox + card_w, oy + card_h, tick, tick),
    ]:
        c.line(cx, cy, cx + dx, cy)
        c.line(cx, cy, cx, cy + dy)

    pad = 8 * mm * scale

    # -- left half: confetti + greeting, vertically centred as one block --
    # (draw_card()'s portrait version pins this to the top of its half,
    # since the back half's content sits right below it; here the two
    # halves are independent side by side, so centring the block reads
    # better than leaving it pinned high with empty space below.)
    left_cx = ox + half_w / 2

    confetti_span = min(half_w - 2 * pad, card_h - 2 * pad - 30 * mm * scale)
    text_block_h = 26 * mm * scale  # "Thank you!" line + gap + brand line (bigger text now)
    gap_below_confetti = 4 * mm * scale
    block_h = confetti_span + gap_below_confetti + text_block_h
    block_top = oy + card_h / 2 + block_h / 2

    confetti_y = block_top - confetti_span
    c.drawImage(
        confetti_path(order["confetti_color"]),
        left_cx - confetti_span / 2, confetti_y, width=confetti_span, height=confetti_span,
        mask="auto", preserveAspectRatio=True,
    )

    text_top_y = confetti_y - gap_below_confetti
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica-Bold", 20 * scale)
    c.drawCentredString(left_cx, text_top_y - 6 * mm * scale, "Thank you!")

    c.setFillColor(HexColor(theme["muted"]))
    brand_font_size = 9.5 * scale
    c.setFont("Helvetica", brand_font_size)
    brand_text = order["brand_name"]
    brand_y = text_top_y - 15 * mm * scale
    icon_size = 3.8 * mm * scale
    icon_gap = 1.5 * mm * scale
    text_w_brand = stringWidth(brand_text, "Helvetica", brand_font_size)
    lockup_w = icon_size + icon_gap + text_w_brand
    lockup_x = left_cx - lockup_w / 2
    c.drawImage(
        LOGO_MARK_PATH, lockup_x, brand_y - 0.3 * mm * scale, width=icon_size, height=icon_size,
        mask="auto", preserveAspectRatio=True,
    )
    c.drawString(lockup_x + icon_size + icon_gap, brand_y, brand_text)

    # divider between the two halves -- vertical, not horizontal
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.setDash(2, 2)
    c.line(ox + half_w, oy + pad, ox + half_w, oy + card_h - pad)
    c.setDash()

    # -- right half: instructions, full card height available --------------
    text_x = ox + half_w + pad
    text_w = half_w - 2 * pad
    top_y = oy + card_h - pad - 8 * mm * scale

    accent_hex = ACCENT_COLORS.get(order["confetti_color"], ACCENT_COLORS["terracotta"])

    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica-Bold", 12 * scale)
    c.drawString(text_x, top_y, "How to apply your sticker")

    list_top_y = top_y - 7 * mm * scale
    list_bottom_y = draw_numbered_list(
        c, text_x, list_top_y, text_w, APPLICATION_STEPS,
        "Helvetica", 7.6 * scale, 3.4 * mm * scale, accent_hex, theme["text"],
    )

    footer_y = oy + pad + 4 * mm * scale

    # Fills the gap between the list and the footer -- the one place this
    # layout had unused space once the instructions column got its own
    # full card height rather than sharing it with the confetti side.
    diagram_available_h = list_bottom_y - (footer_y + 10 * mm * scale)
    if diagram_available_h > 20 * mm * scale:  # skip it if a shorter card leaves no real room
        diagram_size = min(text_w * 0.34, diagram_available_h * 0.5)
        diagram_cx = text_x + text_w / 2
        diagram_cy = footer_y + 10 * mm * scale + diagram_available_h / 2
        draw_smoothing_diagram(c, diagram_cx, diagram_cy, diagram_size, text_w, accent_hex, theme["text"], theme["muted"])

    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.line(text_x, footer_y + 6 * mm * scale, text_x + text_w, footer_y + 6 * mm * scale)

    c.setFillColor(HexColor(theme["muted"]))
    c.setFont("Helvetica", 7.5 * scale)
    c.drawString(text_x, footer_y, f"@{order['handle']}  \u00b7  Made by hand in the UK")


def render_sheet(orders, out_path, size="A6"):
    """orders: list of dicts (see draw_card).
    size: "A6" (default) -- 4-up (2x2) on a single A4 sheet, as before.
    size: "A5" -- one card per page, page size == card size (portrait).
    size: "A5_landscape" -- 2-up on a single A4 sheet (two landscape A5s
        stacked, 210 x 148.5mm each = 210 x 297mm = one A4 -- the only
        orientation that tiles cleanly, since portrait A5 doesn't divide
        evenly into A4 either side-by-side (2x148mm=296mm > 210mm page
        width) or stacked (2x210mm=420mm > 297mm page height)). Uses
        draw_card_landscape(), not draw_card().
    size: "A4" -- one card per page, page size == card size.
    """
    if size not in CARD_SIZES and size != "A5_landscape":
        raise ValueError(f"size must be one of {list(CARD_SIZES) + ['A5_landscape']}, got {size!r}")

    if size == "A6":
        card_w, card_h = CARD_SIZES["A6"]
        c = canvas.Canvas(out_path, pagesize=A4)
        page_w, page_h = A4
        margin_x = (page_w - 2 * card_w) / 2
        margin_y = (page_h - 2 * card_h) / 2
        positions = [
            (margin_x, margin_y + card_h), (margin_x + card_w, margin_y + card_h),
            (margin_x, margin_y), (margin_x + card_w, margin_y),
        ]
        for order, (x, y) in zip(orders, positions):
            draw_card(c, x, y, order, card_w, card_h)
        c.showPage()
        c.save()
    elif size == "A5_landscape":
        card_w, card_h = 210 * mm, 148.5 * mm  # landscape A5
        c = canvas.Canvas(out_path, pagesize=A4)
        page_w, page_h = A4
        positions = [(0, page_h - card_h), (0, 0)]  # stacked top/bottom, full width
        for order, (x, y) in zip(orders, positions):
            draw_card_landscape(c, x, y, order, card_w, card_h)
        c.showPage()
        c.save()
    else:
        card_w, card_h = CARD_SIZES[size]
        c = canvas.Canvas(out_path, pagesize=(card_w, card_h))
        for order in orders:
            draw_card(c, 0, 0, order, card_w, card_h)
            c.showPage()
        c.save()


if __name__ == "__main__":
    # No personalisation fields left (buyer_name / product_type / detail) --
    # every order gets the identical card. The 4 samples below only vary
    # paper + confetti_color + channel, to show the card looks right across
    # every combination that still actually varies in practice.
    sample_orders = [
        {"channel": "etsy", "paper": "white", "confetti_color": "multi",
         "brand_name": "Doorstep Studio", "handle": "doorstepstudiouk"},
        {"channel": "website", "paper": "white", "confetti_color": "dusty_blue",
         "brand_name": "Doorstep Studio", "handle": "doorstepstudiouk"},
        {"channel": "ebay", "paper": "kraft", "confetti_color": "sage",
         "brand_name": "Doorstep Studio", "handle": "doorstepstudiouk"},
        {"channel": "website", "paper": "kraft", "confetti_color": "multi",
         "brand_name": "Doorstep Studio", "handle": "doorstepstudiouk"},
    ]
    render_sheet(sample_orders, "/mnt/user-data/outputs/thankyou_cards_sample_sheet.pdf", size="A6")
    render_sheet(sample_orders[:2], "/mnt/user-data/outputs/thankyou_cards_A5_sample.pdf", size="A5")
    render_sheet(sample_orders[:2], "/mnt/user-data/outputs/thankyou_cards_A5_landscape_sample.pdf", size="A5_landscape")
    render_sheet(sample_orders[:2], "/mnt/user-data/outputs/thankyou_cards_A4_sample.pdf", size="A4")
    print("done")
