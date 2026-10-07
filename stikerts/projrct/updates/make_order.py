"""
make_order.py -- Doorstep Studio
Edit the ORDERS list below, then run:   python3 make_order.py

Makes, in a dated folder (e.g. orders_2026-10-03/):
  - sheet_1.pdf, sheet_2.pdf ...   the sticker sheets, 4 stickers per A4
  - wrap_36_Grove_Street.pdf ...   one personal wrap per order

Keep this file in the same folder as bin_sticker_small.py,
bin_sticker_core.py, sticker_wrap.py and the assets/ folder.
Small (140 x 100mm) bin stickers only for now.
"""

import os
from datetime import date

import bin_sticker_small as bs   # small 140x100mm stickers
import sticker_wrap

# True = also save a Cricut Print Then Cut PNG for each wrap
CRICUT = False

BRAND = {"brand_name": "Doorstep Studio", "handle": "doorstepstudio",
         "logo_style": "both"}

# One entry per sticker. A 4-pack of the same design = one entry here;
# the sheet prints that design in one slot per entry, so for 4 identical
# stickers list the same order 4 times with "wrap": False on 3 of them.
ORDERS = [
    {"house_number": "36", "street_name": "Grove Street",
     "style": "p25_landscape_flourish", "accent": "navy",
     "buyer_name": "Priya", "paper": "kraft"},
]


def main():
    out_dir = f"orders_{date.today().isoformat()}"
    os.makedirs(out_dir, exist_ok=True)

    # sticker sheets, 4 per page
    for i in range(0, len(ORDERS), 4):
        path = os.path.join(out_dir, f"sheet_{i // 4 + 1}.pdf")
        bs.render_sheet(ORDERS[i:i + 4], path)
        print("sticker sheet:", path)

    # one wrap per order (skip entries marked "wrap": False)
    for o in ORDERS:
        if not o.get("wrap", True):
            continue
        name = f"wrap_{o['house_number']}_{o['street_name'].replace(' ', '_')}.pdf"
        path = os.path.join(out_dir, name)
        sticker_wrap.render_sheet({**BRAND, **o}, path)
        print("wrap:", path)
        if CRICUT:
            png = path.replace(".pdf", "_cricut.png")
            sticker_wrap.export_png({**BRAND, **o}, png)
            print("cricut image:", png)


if __name__ == "__main__":
    main()
