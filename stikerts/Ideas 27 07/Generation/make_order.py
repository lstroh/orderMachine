"""
make_order.py -- Doorstep Studio
Edit the ORDERS list below, then run:   python make_order.py

Each order_number is written to:
  G:\\My Drive\\orders\\<order_number>\\
    sheet_1.pdf, sheet_2.pdf ...   sticker sheets, 4 stickers per A4
    wrap_36_Grove_Street.pdf       one personal wrap per pack
    wrap_36_Grove_Street_cricut.png   Cricut Print Then Cut image of that wrap

Keep this file in the same folder as bin_sticker_small.py,
bin_sticker_core.py, sticker_wrap.py and the assets/ folder.
Small (140 x 100mm) bin stickers only for now.
"""

import embedded_fonts  # embeds the fonts in every PDF (12 Oct 2026, see embedded_fonts.py)
import os
import re

import bin_sticker_small as bs   # small 140x100mm stickers
import sticker_wrap

# True = also save a Cricut Print Then Cut PNG for each wrap (not the sticker sheet)
CRICUT = True

OUTPUT_ROOT = r"G:\My Drive\orders"

BRAND = {"brand_name": "Doorstep Studio", "handle": "doorstepstudio",
         "logo_style": "both"}

# One entry per sticker. A 4-pack of the same design = the same order
# listed 4 times, with "wrap": False on 3 of them. Every entry needs
# order_number; entries that share one are written into one folder.
ORDERS = [
    {"order_number": "test2", "house_number": "40", "street_name": "OAK AVENUE",
     "style": "p25_landscape_flourish", "accent": "black",
     "buyer_name": "Liron", "paper": "white", "quantity": 4},
    {"order_number": "test2", "house_number": "40", "street_name": "OAK AVENUE",
     "style": "p25_landscape_flourish", "accent": "black", "wrap": False},
    {"order_number": "test2", "house_number": "40", "street_name": "OAK AVENUE",
     "style": "p25_landscape_flourish", "accent": "black", "wrap": False},
    {"order_number": "test2", "house_number": "40", "street_name": "OAK AVENUE",
     "style": "p25_landscape_flourish", "accent": "black", "wrap": False},
]

_ORDER_NUMBER_RE = re.compile(r"^[A-Za-z0-9][A-Za-z0-9 _-]{0,80}$")


def _groups(orders):
    """Entries that share an order_number, in first-seen order."""
    grouped = {}
    sequence = []
    for entry in orders:
        number = entry.get("order_number")
        if number is None or str(number).strip() == "":
            raise SystemExit("Every ORDERS entry needs order_number.")
        key = str(number).strip()
        if not _ORDER_NUMBER_RE.match(key):
            raise SystemExit(
                f"Invalid order_number {number!r}. "
                "Use letters, digits, spaces, hyphens, or underscores."
            )
        if key not in grouped:
            grouped[key] = []
            sequence.append(key)
        grouped[key].append(entry)
    return [(key, grouped[key]) for key in sequence]


def _write_order(order_number, entries):
    out_dir = os.path.join(OUTPUT_ROOT, order_number)
    os.makedirs(out_dir, exist_ok=True)

    for i in range(0, len(entries), 4):
        path = os.path.join(out_dir, f"sheet_{i // 4 + 1}.pdf")
        bs.render_sheet(entries[i:i + 4], path)
        print("sticker sheet:", path)

    for o in entries:
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


def main():
    if not ORDERS:
        raise SystemExit("ORDERS is empty.")
    for order_number, entries in _groups(ORDERS):
        print("order:", os.path.join(OUTPUT_ROOT, order_number))
        _write_order(order_number, entries)


if __name__ == "__main__":
    main()
