"""
make_order.py -- Doorstep Studio
Edit the order lists below, then run:   python3 make_order.py

Each order_number is written to G:\\My Drive\\orders\\<order_number>\\
  SMALL (140 x 100mm, 4-pack) -- live
  - sheet_1.pdf, sheet_2.pdf ...          sticker sheets, 4 per A4
  - wrap_36_Grove_Street.pdf ...          one personal wrap per order
                                          (QR code on the back flap)
  MEDIUM (2-pack) -- TO BE TESTED (10 Oct 2026)
  - medium_sheet_1.pdf ...                sticker sheets, 2 per A4
  - medium_wrap_36_Grove_Street.pdf ...   one A4 sleeve per order
  LARGE (single) -- TO BE TESTED (10 Oct 2026)
  - large_cover_36_Grove_Street.pdf ...   one A4 cover sheet per order
                                          (goes in a 220 x 310mm glassine bag);
                                          this runner still writes the cover only.
                                          bin_sticker_large.py is in this folder
                                          for the sticker sheet itself.

The instructions card is NOT made here: every card is identical, so it is
printed in batches with instructions_card.py (see instructions_card_README.md).
Every wrap, sleeve and cover uses that file's VIDEO_URL for its QR code, so
change the link there only.

Keep this file in the same folder as bin_sticker_small.py,
bin_sticker_core.py, sticker_wrap.py, instructions_card.py and the assets/
folder. For Medium/Large also: bin_sticker_medium.py, sticker_wrap_medium.py,
sticker_cover_large.py and bin_sticker_large.py.

The Medium and Large scripts are only imported when their lists have
orders in them, so a problem in either can never stop a Small batch.
"""

import embedded_fonts  # embeds the fonts in every PDF (12 Oct 2026, see embedded_fonts.py)
import os
import re

import bin_sticker_small as bs   # small 140x100mm stickers
import sticker_wrap

OUTPUT_ROOT = r"G:\My Drive\orders"
_ORDER_NUMBER_RE = re.compile(r"^[A-Za-z0-9][A-Za-z0-9 _-]{0,80}$")

# True = also save a Cricut Print Then Cut PNG for each SMALL wrap
# (the Medium sleeve and Large cover need no cutting)
CRICUT = False

# handle matches the instructions card footer and the YouTube channel
BRAND = {"brand_name": "Doorstep Studio", "handle": "doorstepstudiouk",
         "logo_style": "both"}

# SMALL: one entry per sticker. A 4-pack of the same design = the same order
# listed 4 times with "wrap": False on 3 of them (one wrap per customer).
# Add "video_url": None to an entry to leave the QR code off its wrap.
ORDERS = [
    {"order_number": "sample", "house_number": "36", "street_name": "Grove Street",
     "style": "p25_landscape_flourish", "accent": "navy",
     "buyer_name": "Priya", "paper": "white"},
]

# MEDIUM (TO BE TESTED): one entry per sticker, 2 per sheet. A 2-pack of the
# same design = listed twice with "wrap": False on one of them.
# "style" is a key from bin_sticker_medium.STYLES.
ORDERS_MEDIUM = [
]

# LARGE (TO BE TESTED): one entry per customer; makes the cover sheet only.
# "style" is not drawn on the cover. The Large sticker sheet is bin_sticker_large.py.
ORDERS_LARGE = [
]


def _name(o):
    return f"{o['house_number']}_{o['street_name'].replace(' ', '_')}"


def _order_key(entry, list_name):
    number = entry.get("order_number")
    if number is None or str(number).strip() == "":
        raise SystemExit(f"Every {list_name} entry needs order_number.")
    key = str(number).strip()
    if not _ORDER_NUMBER_RE.match(key):
        raise SystemExit(
            f"Invalid order_number {number!r} in {list_name}. "
            "Use letters, digits, spaces, hyphens, or underscores."
        )
    return key


def _collect():
    """Entries that share an order_number, in first-seen order, per size."""
    grouped = {}
    sequence = []

    def add(entries, list_name, size):
        for entry in entries:
            key = _order_key(entry, list_name)
            if key not in grouped:
                grouped[key] = {"small": [], "medium": [], "large": []}
                sequence.append(key)
            grouped[key][size].append(entry)

    add(ORDERS, "ORDERS", "small")
    add(ORDERS_MEDIUM, "ORDERS_MEDIUM", "medium")
    add(ORDERS_LARGE, "ORDERS_LARGE", "large")
    if not sequence:
        raise SystemExit("ORDERS, ORDERS_MEDIUM, and ORDERS_LARGE are empty.")
    return [(key, grouped[key]) for key in sequence]


def _write_order(order_number, sizes):
    out_dir = os.path.join(OUTPUT_ROOT, order_number)
    os.makedirs(out_dir, exist_ok=True)
    small = sizes["small"]
    medium = sizes["medium"]
    large = sizes["large"]

    for i in range(0, len(small), 4):
        path = os.path.join(out_dir, f"sheet_{i // 4 + 1}.pdf")
        bs.render_sheet(small[i:i + 4], path)
        print("sticker sheet:", path)

    for o in small:
        if not o.get("wrap", True):
            continue
        path = os.path.join(out_dir, f"wrap_{_name(o)}.pdf")
        sticker_wrap.render_sheet({**BRAND, **o}, path)
        print("wrap:", path)
        if CRICUT:
            png = path.replace(".pdf", "_cricut.png")
            sticker_wrap.export_png({**BRAND, **o}, png)
            print("cricut image:", png)

    if medium:
        import bin_sticker_medium as bm
        import sticker_wrap_medium
        for i in range(0, len(medium), 2):
            path = os.path.join(out_dir, f"medium_sheet_{i // 2 + 1}.pdf")
            bm.render_sheet(medium[i:i + 2], path)
            print("medium sticker sheet:", path)
        for o in medium:
            if not o.get("wrap", True):
                continue
            path = os.path.join(out_dir, f"medium_wrap_{_name(o)}.pdf")
            sticker_wrap_medium.render_sheet({**BRAND, **o}, path)
            print("medium sleeve:", path)

    if large:
        import sticker_cover_large
        for o in large:
            path = os.path.join(out_dir, f"large_cover_{_name(o)}.pdf")
            sticker_cover_large.render_sheet({**BRAND, **o}, path)
            print("large cover sheet:", path)


def main():
    for order_number, sizes in _collect():
        print("order:", os.path.join(OUTPUT_ROOT, order_number))
        _write_order(order_number, sizes)


if __name__ == "__main__":
    main()
