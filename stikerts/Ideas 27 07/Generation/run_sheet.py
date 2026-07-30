"""Render one A4 sheet of bin stickers via bin_sticker.render_sheet."""

import os

# Asset paths in bin_sticker.py are relative to this folder.
os.chdir(os.path.dirname(os.path.abspath(__file__)))

import bin_sticker as bs

orders = [
    {"house_number": "36", "street_name": "Grove Street", "style": "house_banner", "accent": "navy"},
    {"house_number": "36", "street_name": "Grove Street", "style": "house_banner", "accent": "navy"},
    {"house_number": "36", "street_name": "Grove Street", "style": "house_banner", "accent": "navy"},
    {"house_number": "36", "street_name": "Grove Street", "style": "house_banner", "accent": "navy"},
]

bs.render_sheet(orders, "sheet.pdf")
print("Wrote sheet.pdf")
