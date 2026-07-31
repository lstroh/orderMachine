"""Render one A4 sheet of bin stickers via bin_sticker.render_sheet."""

import os
from datetime import datetime

# Asset paths in bin_sticker.py are relative to this folder.
os.chdir(os.path.dirname(os.path.abspath(__file__)))

import bin_sticker as bs

orders = [
    {"house_number": "36", "street_name": "Grove Street", "style": "house_banner", "accent": "navy"},
    {"house_number": "36", "street_name": "Grove Street", "style": "house_banner", "accent": "navy"},
    {"house_number": "36", "street_name": "Grove Street", "style": "house_banner", "accent": "navy"},
    {"house_number": "36", "street_name": "Grove Street", "style": "house_banner", "accent": "navy"},
]

out = "sheet.pdf"
try:
    bs.render_sheet(orders, out)
except PermissionError:
    # sheet.pdf is usually open in a viewer — write a fresh file instead.
    out = f"sheet_{datetime.now():%Y%m%d_%H%M%S}.pdf"
    bs.render_sheet(orders, out)
    print(f"sheet.pdf is locked (close it to overwrite). Wrote {out}")
else:
    print(f"Wrote {out}")
