"""
Thank-you card generator — Kerbside Craft Co.
Draws one A6 (105 x 148.5mm) card per call, 4-up on an A4 sheet.
This is the reusable core the future skill will call per order.
"""

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor
from reportlab.pdfgen import canvas
from reportlab.pdfbase.pdfmetrics import stringWidth

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

# starter flower colour palette — add more any time, no new art needed
FLOWER_COLORS = {
    "blush":      {"petal": "#D4537E", "center": "#993556"},
    "sage":       {"petal": "#639922", "center": "#3B6D11"},
    "dusty_blue": {"petal": "#378ADD", "center": "#185FA5"},
    "terracotta": {"petal": "#D85A30", "center": "#993C1D"},
    "lavender":   {"petal": "#7F77DD", "center": "#534AB7"},
}

CARD_W, CARD_H = 105 * mm, 148.5 * mm  # A6, exactly 1/4 of A4


def draw_flower(c, cx, cy, size, petal_hex, center_hex):
    """Simple 4-petal flower, fully vector, no external assets."""
    c.saveState()
    c.setFillColor(HexColor(petal_hex))
    pw, ph = size * 0.35, size * 0.5
    c.ellipse(cx - pw / 2, cy + size * 0.1, cx + pw / 2, cy + size * 0.1 + ph, fill=1, stroke=0)
    c.ellipse(cx - pw / 2, cy - size * 0.1 - ph, cx + pw / 2, cy - size * 0.1, fill=1, stroke=0)
    c.ellipse(cx - size * 0.1 - ph, cy - pw / 2, cx - size * 0.1, cy + pw / 2, fill=1, stroke=0)
    c.ellipse(cx + size * 0.1, cy - pw / 2, cx + size * 0.1 + ph, cy + pw / 2, fill=1, stroke=0)
    c.setFillColor(HexColor(center_hex))
    r = size * 0.14
    c.ellipse(cx - r, cy - r, cx + r, cy + r, fill=1, stroke=0)
    c.restoreState()


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


def draw_card(c, ox, oy, order):
    """
    ox, oy = bottom-left corner of this card's cell on the page (points)
    order  = dict with keys:
        buyer_name      str
        product         str   e.g. "bin sticker set"
        care_line       str   e.g. "laminated for weatherproof durability"
        channel         "ebay" | "etsy" | "website"
        paper           "white" | "kraft"  -- selects INK colours only;
                        load the matching physical cardstock in the printer
        flower_color    key into FLOWER_COLORS
        brand_name      str
        handle          str
        discount_code   str or None (only ever set when channel == "website")
    """
    theme = PAPER[order["paper"]]
    flower = FLOWER_COLORS[order["flower_color"]]

    # No fill here — the card sits on whatever cardstock is loaded in the
    # printer (white or kraft). Only a thin cut-guide outline is printed.
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.4)
    c.roundRect(ox, oy, CARD_W, CARD_H, 3 * mm, fill=0, stroke=1)

    # cut marks (small ticks just outside the card corners)
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.3)
    tick = 3 * mm
    for cx, cy, dx, dy in [
        (ox, oy, -tick, -tick), (ox + CARD_W, oy, tick, -tick),
        (ox, oy + CARD_H, -tick, tick), (ox + CARD_W, oy + CARD_H, tick, tick),
    ]:
        c.line(cx, cy, cx + dx, cy)
        c.line(cx, cy, cx, cy + dy)

    pad = 8 * mm
    cx_center = ox + CARD_W / 2

    # -- front half (top) --------------------------------------------------
    front_center_y = oy + CARD_H * 0.75
    draw_flower(c, cx_center, front_center_y + 6 * mm, 16 * mm, flower["petal"], flower["center"])
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica", 15)
    c.drawCentredString(cx_center, front_center_y - 14 * mm, "Thank you")
    c.setFillColor(HexColor(theme["muted"]))
    c.setFont("Helvetica", 8)
    c.drawCentredString(cx_center, front_center_y - 20 * mm, order["brand_name"])

    # divider between front and back halves
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.setDash(2, 2)
    mid_y = oy + CARD_H / 2
    c.line(ox + pad, mid_y, ox + CARD_W - pad, mid_y)
    c.setDash()

    # -- back half (bottom) -------------------------------------------------
    text_x = ox + pad
    text_w = CARD_W - 2 * pad
    top_y = mid_y - 10 * mm

    greeting = f"Thanks, {order['buyer_name']} \u2014 your {order['product']} is {order['care_line']}."
    c.setFillColor(HexColor(theme["text"]))
    c.setFont("Helvetica", 9)
    lines = wrap_text(greeting, "Helvetica", 9, text_w)
    y = top_y
    for line in lines:
        c.drawString(text_x, y, line)
        y -= 4.2 * mm

    footer_y = oy + pad + 10 * mm
    c.setStrokeColor(HexColor(theme["guide"]))
    c.setLineWidth(0.5)
    c.line(text_x, footer_y + 6 * mm, text_x + text_w, footer_y + 6 * mm)

    c.setFillColor(HexColor(theme["muted"]))
    c.setFont("Helvetica", 7.5)
    c.drawString(text_x, footer_y, f"@{order['handle']}  \u00b7  Made by hand in the UK")

    if order.get("discount_code") and order["channel"] == "website":
        c.setFillColor(HexColor(flower["petal"]))
        c.roundRect(text_x, oy + pad, text_w, 8 * mm, 1.5 * mm, fill=1, stroke=0)
        c.setFillColor(HexColor("#FFFFFF"))
        c.setFont("Helvetica-Bold", 7.5)
        c.drawCentredString(
            ox + CARD_W / 2, oy + pad + 3 * mm,
            f"10% off next order \u2014 code {order['discount_code']}"
        )


def render_sheet(orders, out_path):
    """orders: list of up to 4 dicts (see draw_card). Fills a single A4 sheet, 2x2."""
    c = canvas.Canvas(out_path, pagesize=A4)
    page_w, page_h = A4
    margin_x = (page_w - 2 * CARD_W) / 2
    margin_y = (page_h - 2 * CARD_H) / 2
    positions = [
        (margin_x, margin_y + CARD_H), (margin_x + CARD_W, margin_y + CARD_H),
        (margin_x, margin_y), (margin_x + CARD_W, margin_y),
    ]
    for order, (x, y) in zip(orders, positions):
        draw_card(c, x, y, order)
    c.showPage()
    c.save()


if __name__ == "__main__":
    sample_orders = [
        {"buyer_name": "Priya", "product": "bin sticker set", "care_line": "laminated for weatherproof durability",
         "channel": "etsy", "paper": "white", "flower_color": "blush",
         "brand_name": "Kerbside Craft Co.", "handle": "kerbsidecraftco", "discount_code": None},
        {"buyer_name": "Tom", "product": "name label sheet", "care_line": "dishwasher and wash safe",
         "channel": "website", "paper": "white", "flower_color": "dusty_blue",
         "brand_name": "Kerbside Craft Co.", "handle": "kerbsidecraftco", "discount_code": "WELCOME10"},
        {"buyer_name": "Amara", "product": "bin sticker set", "care_line": "laminated for weatherproof durability",
         "channel": "ebay", "paper": "kraft", "flower_color": "sage",
         "brand_name": "Kerbside Craft Co.", "handle": "kerbsidecraftco", "discount_code": None},
        {"buyer_name": "Jack", "product": "water bottle decal", "care_line": "scratch and water resistant",
         "channel": "website", "paper": "kraft", "flower_color": "terracotta",
         "brand_name": "Kerbside Craft Co.", "handle": "kerbsidecraftco", "discount_code": "WELCOME10"},
    ]
    render_sheet(sample_orders, "/mnt/user-data/outputs/thankyou_cards_sample_sheet.pdf")
    print("done")
