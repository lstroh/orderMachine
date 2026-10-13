"""
make_order.py -- Doorstep Studio
Edit the order lists below, then run:   python3 make_order.py

Makes, in a dated folder (e.g. orders_2026-10-10/):
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
                                          no Large sticker sheet yet -- there is
                                          no bin_sticker_large.py in the project

The instructions card is NOT made here: every card is identical, so it is
printed in batches with instructions_card.py (see instructions_card_README.md).
Every wrap, sleeve and cover uses that file's VIDEO_URL for its QR code, so
change the link there only.

Keep this file in the same folder as bin_sticker_small.py,
bin_sticker_core.py, sticker_wrap.py, instructions_card.py and the assets/
folder. For Medium/Large also: bin_sticker_medium.py, sticker_wrap_medium.py,
sticker_cover_large.py (and bin_sticker_large.py once it exists).

The Medium and Large scripts are only imported when their lists have
orders in them, so a problem in either can never stop a Small batch.
"""

import embedded_fonts  # embeds the fonts in every PDF (12 Oct 2026, see embedded_fonts.py)
import os
from datetime import date

import bin_sticker_small as bs   # small 140x100mm stickers
import sticker_wrap

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
    {"house_number": "36", "street_name": "Grove Street",
     "style": "p25_landscape_flourish", "accent": "navy",
     "buyer_name": "Priya", "paper": "white"},
]

# MEDIUM (TO BE TESTED): one entry per sticker, 2 per sheet. A 2-pack of the
# same design = listed twice with "wrap": False on one of them.
# "style" is a key from bin_sticker_medium.STYLES.
ORDERS_MEDIUM = [
]

# LARGE (TO BE TESTED): one entry per customer; makes the cover sheet only.
# "style" is ignored until bin_sticker_large.py exists (plain preview meanwhile).
ORDERS_LARGE = [
]


def _name(o):
    return f"{o['house_number']}_{o['street_name'].replace(' ', '_')}"


def main():
    out_dir = f"orders_{date.today().isoformat()}"
    os.makedirs(out_dir, exist_ok=True)

    # ---- SMALL ---------------------------------------------------------
    for i in range(0, len(ORDERS), 4):
        path = os.path.join(out_dir, f"sheet_{i // 4 + 1}.pdf")
        bs.render_sheet(ORDERS[i:i + 4], path)
        print("sticker sheet:", path)

    for o in ORDERS:
        if not o.get("wrap", True):
            continue
        path = os.path.join(out_dir, f"wrap_{_name(o)}.pdf")
        sticker_wrap.render_sheet({**BRAND, **o}, path)
        print("wrap:", path)
        if CRICUT:
            png = path.replace(".pdf", "_cricut.png")
            sticker_wrap.export_png({**BRAND, **o}, png)
            print("cricut image:", png)

    # ---- MEDIUM (to be tested) -----------------------------------------
    if ORDERS_MEDIUM:
        import bin_sticker_medium as bm
        import sticker_wrap_medium
        for i in range(0, len(ORDERS_MEDIUM), 2):
            path = os.path.join(out_dir, f"medium_sheet_{i // 2 + 1}.pdf")
            bm.render_sheet(ORDERS_MEDIUM[i:i + 2], path)
            print("medium sticker sheet:", path)
        for o in ORDERS_MEDIUM:
            if not o.get("wrap", True):
                continue
            path = os.path.join(out_dir, f"medium_wrap_{_name(o)}.pdf")
            sticker_wrap_medium.render_sheet({**BRAND, **o}, path)
            print("medium sleeve:", path)

    # ---- LARGE (to be tested) ------------------------------------------
    if ORDERS_LARGE:
        import sticker_cover_large
        for o in ORDERS_LARGE:
            path = os.path.join(out_dir, f"large_cover_{_name(o)}.pdf")
            sticker_cover_large.render_sheet({**BRAND, **o}, path)
            print("large cover sheet:", path)


if __name__ == "__main__":
    main()
