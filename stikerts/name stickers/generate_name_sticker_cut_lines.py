"""
Cut-line SVG generator for name_sticker.py's labels -- rounded rectangle,
matching CARD_W/CARD_H/CORNER_RADIUS and the grid layout in name_sticker.py
EXACTLY. This is the file that actually gets imported into Cricut Design
Space as a CUT ONLY job (not Print Then Cut) -- see the top-of-file note
in name_sticker.py for the full workflow and why cut-only applies here.

SVG, not DXF: unlike the bin_sticker.py pair (which generates both SVG and
DXF because Silhouette Studio's free tier can't import SVG), this label
only ever needs to go into Cricut Design Space, which imports SVG for
free with no paid tier -- so DXF isn't needed here.

Usage:
    python3 generate_name_sticker_cut_lines.py
Output: cut_lines.svg

IMPORTANT: if you change CARD_W, CARD_H, CORNER_RADIUS, GAP, or MARGIN in
name_sticker.py, re-run this script too. The printed sheet and the cut
file are two separate outputs that only work together because they share
the same geometry -- there's no automatic link between them, so a change
in one without the other means the machine cuts in the wrong place
relative to what's printed.
"""

from name_sticker import CARD_W, CARD_H, CORNER_RADIUS, GAP, MARGIN, _grid_positions
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm

positions, cols, rows = _grid_positions()

page_w_mm = A4[0] / mm
page_h_mm = A4[1] / mm
card_w_mm = CARD_W / mm
card_h_mm = CARD_H / mm
radius_mm = CORNER_RADIUS / mm


def rounded_rect_path(x, y, w, h, r):
    """SVG path for a rounded rectangle, top-left origin (x, y = top-left
    corner in SVG's y-down coordinate system). Explicit path rather than
    SVG's native <rect rx=".."> element -- a plain <path> is the more
    universally reliable thing for Design Space to import cleanly."""
    return (
        f"M {x + r},{y} "
        f"H {x + w - r} "
        f"A {r},{r} 0 0 1 {x + w},{y + r} "
        f"V {y + h - r} "
        f"A {r},{r} 0 0 1 {x + w - r},{y + h} "
        f"H {x + r} "
        f"A {r},{r} 0 0 1 {x},{y + h - r} "
        f"V {y + r} "
        f"A {r},{r} 0 0 1 {x + r},{y} "
        f"Z"
    )


paths = []
for x_pt, y_pt in positions:
    # name_sticker.py's positions are in points, bottom-left origin
    # (reportlab convention). Convert to mm and flip to SVG's top-left/
    # y-down convention for the path.
    x_mm = x_pt / mm
    y_from_bottom_mm = y_pt / mm
    y_mm = page_h_mm - y_from_bottom_mm - card_h_mm
    paths.append(rounded_rect_path(x_mm, y_mm, card_w_mm, card_h_mm, radius_mm))

svg_paths = "\n  ".join(
    f'<path d="{p}" fill="none" stroke="#000000" stroke-width="0.1"/>' for p in paths
)

svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{page_w_mm}mm" height="{page_h_mm}mm" viewBox="0 0 {page_w_mm} {page_h_mm}">
  {svg_paths}
</svg>
'''

with open("cut_lines.svg", "w") as f:
    f.write(svg)

print(f"saved cut_lines.svg -- {len(positions)} rounded-rect labels "
      f"({card_w_mm:.0f}x{card_h_mm:.0f}mm, {radius_mm:.0f}mm radius), "
      f"{cols}x{rows} grid, matches name_sticker.py's layout")
print("ALWAYS verify the imported shapes read exactly "
      f"{card_w_mm:.0f}x{card_h_mm:.0f}mm in Design Space before cutting "
      "anything -- same check bin_sticker.py's cut-line scripts recommend.")
