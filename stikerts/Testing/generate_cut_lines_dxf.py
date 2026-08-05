"""
Cut-line DXF generator — same layout as generate_cut_lines_svg.py, for
Silhouette Studio's free Basic Edition (which can't import SVG but can
import DXF). Matches bin_sticker.py's render_sheet() grid exactly.

Usage:
    pip install ezdxf --break-system-packages
    python3 generate_cut_lines_dxf.py

Output: sticker_cut_lines.dxf

DXF has no inherent "unit" the way SVG mm/in attributes do -- the numbers
in the file are just numbers, and the importing software decides what
unit they mean (often controlled by an $INSUNITS header, which this
script sets to millimeters, code 4). Even so, ALWAYS verify the imported
shape reads exactly 100mm x 140mm in Silhouette Studio's properties
panel before cutting anything -- this is the main way DXF imports go
wrong, not path complexity (our shapes are plain rectangles, about as
simple as DXF gets).
"""

import ezdxf

CARD_W_MM = 100
CARD_H_MM = 140
PAGE_W_MM = 210  # A4
PAGE_H_MM = 297

margin_x = (PAGE_W_MM - 2 * CARD_W_MM) / 2
margin_y = (PAGE_H_MM - 2 * CARD_H_MM) / 2

# Same bottom-left-origin positions as bin_sticker.py's _sheet_layout()
# and generate_cut_lines_svg.py -- DXF's default coordinate system is
# also bottom-left/y-up, so no axis flip needed here (unlike the SVG
# version, which needed to flip y for SVG's top-left/y-down convention).
positions_bottom_left_origin = [
    (margin_x, margin_y + CARD_H_MM),               # top-left
    (margin_x + CARD_W_MM, margin_y + CARD_H_MM),    # top-right
    (margin_x, margin_y),                            # bottom-left
    (margin_x + CARD_W_MM, margin_y),                # bottom-right
]

doc = ezdxf.new(setup=True)
doc.units = ezdxf.units.MM  # sets $INSUNITS = 4 (millimeters) in the header
msp = doc.modelspace()

for ox, oy in positions_bottom_left_origin:
    msp.add_lwpolyline(
        [
            (ox, oy),
            (ox + CARD_W_MM, oy),
            (ox + CARD_W_MM, oy + CARD_H_MM),
            (ox, oy + CARD_H_MM),
        ],
        close=True,
        dxfattribs={"layer": "CUT"},
    )

doc.saveas("sticker_cut_lines.dxf")
print("saved sticker_cut_lines.dxf")
print(f"4 rectangles, {CARD_W_MM}x{CARD_H_MM}mm each, on a {PAGE_W_MM}x{PAGE_H_MM}mm A4 page")
