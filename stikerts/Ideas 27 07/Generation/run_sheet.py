"""Render one A4 sheet of bin stickers via bin_sticker.render_sheet."""

import os
from datetime import datetime

# Asset paths in bin_sticker.py are relative to this folder.
os.chdir(os.path.dirname(os.path.abspath(__file__)))

import bin_sticker as bs

orders = [
    #{"house_number": "4", "street_name": "Parkleigh Road", "style": "house_banner", "accent": "navy"},
    #{"house_number": "4", "street_name": "Parkleigh Road", "style": "p25_landscape_flourish", "accent": "black"},
    #{"house_number": "4", "street_name": "Parkleigh Road", "style": "p25b_landscape_flourish", "accent": "black"},
    {"house_number": "4", "street_name": "Parkleigh Road", "style": "p27_landscape_house", "accent": "black"},
    {"house_number": "4", "street_name": "Parkleigh Road", "style": "p27_landscape_house", "accent": "black"},
    {"house_number": "4", "street_name": "Parkleigh Road", "style": "p27_landscape_house", "accent": "black"},
    {"house_number": "4", "street_name": "Parkleigh Road", "style": "p27_landscape_house", "accent": "black"},
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
