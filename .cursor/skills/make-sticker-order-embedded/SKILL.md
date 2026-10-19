---
name: make-sticker-order-embedded
description: >-
  Build a Doorstep Studio bin-sticker order with the embedded-font scripts in
  stikerts/scripts: write ORDERS, ORDERS_MEDIUM, or ORDERS_LARGE in make_order.py
  and run it so sticker sheets and one personal wrap or sleeve per pack are
  produced, with Arial, Times New Roman, and Courier New embedded. Use when the
  user asks to make a sticker order, fill ORDERS, run make_order.py, or produce
  Affinity-safe PDFs. This is the only order skill for current work.
disable-model-invocation: true
---

# Make a sticker order (embedded fonts)

Scripts live in `stikerts/scripts/`. Run from that folder. Do not edit `bin_sticker_small.py`, `bin_sticker_medium.py`, `bin_sticker_large.py`, `bin_sticker_core.py`, `sticker_wrap.py`, `sticker_wrap_medium.py`, or `sticker_cover_large.py` to place an order. Edit only the order lists in `make_order.py`.

These scripts embed fonts. On this PC they use Arial, Times New Roman, and Courier New from `C:\Windows\Fonts`. If those are missing, they use the TeX Gyre fonts in `assets/fonts/`. Do not remove `import embedded_fonts`.

Do not run `stikerts/Ideas 27 07/Generation/` for an order. That folder still has copies for its gallery tools. The backup scripts, which do not embed fonts, are in `stikerts/Ideas 27 07/Generation-backup/` and are run only by the make-sticker-order skill when the user asks for the old scripts.

## What to collect

One pack is one design. Ask for anything missing. Do not guess a house number, street, buyer name, eBay design name, or size.

The user gives the **eBay design name**, not the script key. Look it up below and write the `style` key into the order list. Match ignoring case, and treat `-`, `–`, and `—` as the same. A name may include its D-number (`D24 Modern Minimal`) or not (`Modern Minimal`). If the name is not in this table, stop and ask. Do not invent a key.

| eBay design name | D-number | `style` key |
|---|---|---|
| Modern Minimal | D24 | `p09a_borderless` |
| Classic Scrolls | D02 | `p25_landscape_flourish` |
| Classic Frame | D03 | `p25b_landscape_flourish` |
| House Outline | D04 | `p27_landscape_house` |
| House Outline – Number Only | D05 | `p47_house` |
| Cottage & Flowers | D01 | `house_banner` |
| Arrow Wreath | D22 | `p28_arrow_wreath` |
| Flower Wreath | D18 | `p06_wreath` |
| Flower Wreath – Number Only | D19 | `p06_wreath_numbers` |
| Heart Vine Wreath | D21 | `p15_heart_wreath` |
| Olive Branch Wreath | D23 | `p31_olive_wreath` |
| Laurel Wreath – Number Only | D20 | `p30_laurel_numbers` |
| Paw Prints | D25 | `p21_paw_trail` |

These are the 13 designs on the eBay listing (Oct 2026). Animal-family styles are not in this list.

| Field | Required | Notes |
|---|---|---|
| size | yes | `small` (140 x 100mm, 4-pack), `medium` (202 x 140mm, 2-pack), or `large` (cover sheet only). |
| `order_number` | yes | Folder name under `G:\My Drive\orders\`. Letters, digits, spaces, hyphens, or underscores. One folder per number. Put it on every slot, including `"wrap": False`. |
| `house_number` | yes | e.g. `"36"` |
| `street_name` | yes | e.g. `"Grove Street"` |
| eBay design name | yes | A name from the table above. Write the mapped `style` key, not the eBay name. Large covers do not draw the design. |
| `buyer_name` | yes | First name. Wrap, sleeve, and cover: "Thank you, {name}!" |
| `accent` | fixed | Always `"black"`. Do not ask. `p25_landscape_flourish` and `p25b_landscape_flourish` are already black and ignore this key. |
| `paper` | no | `"white"` (default) or `"kraft"`. Ink colours on the wrap, sleeve, or cover only. |
| `care_line` | no | Wrap, sleeve, or cover only. Default is already in the script. |
| `quantity` | no | Printed as "Your set of …". Small default **4**. Medium default **2**. Set it on the wrap entry. |
| Cricut | fixed | Leave `CRICUT = False` unless the user asks for a Print Then Cut PNG of a **small** wrap. Medium sleeves and large covers are not cut. |

`BRAND` at the top of `make_order.py` (`brand_name`, `handle`, `logo_style`) applies to every wrap, sleeve, and cover. Leave it unless the user asks to change it. `logo_style` `"both"` is the chosen design. `handle` is `doorstepstudiouk`.

Packs that share an `order_number` go in one folder, including when a small pack, a medium pack, and a large cover share that number.

## Write the lists

`quantity` is not expanded by the script. Expand it yourself: one dict per sticker slot. The first slot of a pack is the wrap or sleeve. The other slots of that pack are the same sticker fields with `"wrap": False`.

Replace the whole list you are filling. Leave the other size lists empty unless the user asked for those sizes too.

A small 4-pack goes in `ORDERS`:

```python
ORDERS = [
    {"order_number": "12-34567-89012", "house_number": "36",
     "street_name": "Grove Street", "style": "p25_landscape_flourish",
     "accent": "black", "buyer_name": "Priya", "paper": "white", "quantity": 4},
    {"order_number": "12-34567-89012", "house_number": "36",
     "street_name": "Grove Street", "style": "p25_landscape_flourish",
     "accent": "black", "wrap": False},
    {"order_number": "12-34567-89012", "house_number": "36",
     "street_name": "Grove Street", "style": "p25_landscape_flourish",
     "accent": "black", "wrap": False},
    {"order_number": "12-34567-89012", "house_number": "36",
     "street_name": "Grove Street", "style": "p25_landscape_flourish",
     "accent": "black", "wrap": False},
]
```

A medium 2-pack goes in `ORDERS_MEDIUM`. `"style"` is a key from `bin_sticker_medium.STYLES`.

```python
ORDERS_MEDIUM = [
    {"order_number": "12-34567-89012", "house_number": "40",
     "street_name": "OAK AVENUE", "style": "p09a_borderless",
     "accent": "black", "buyer_name": "Liron", "paper": "white", "quantity": 2},
    {"order_number": "12-34567-89012", "house_number": "40",
     "street_name": "OAK AVENUE", "style": "p09a_borderless",
     "accent": "black", "wrap": False},
]
```

A large cover goes in `ORDERS_LARGE`. One entry per customer. This writes the cover sheet only.

```python
ORDERS_LARGE = [
    {"order_number": "12-34567-89012", "house_number": "36",
     "street_name": "Grove Street", "buyer_name": "Priya", "paper": "white"},
]
```

Leave `CRICUT = False`. Packs are filled 4 stickers per small sheet and 2 stickers per medium sheet, in list order.

Two wraps, sleeves, or covers with the same house number and street share one filename and the second overwrites the first. Say so before running. If several designs share one address, the user must know only the last wrap for that address will remain.

## Run

From `stikerts/scripts/`:

```bash
python make_order.py
```

Use `python3` if `python` is not the interpreter that has reportlab. The wrap QR needs the `qrcode` package. Cricut PNGs also need pymupdf and pillow.

Output for each order number is `G:\My Drive\orders\<order_number>\`. Google Drive must be mounted as `G:`.

- `sheet_1.pdf`, `sheet_2.pdf`, … — small stickers, landscape A4, up to 4 per page
- `wrap_<house>_<street>.pdf` — one small wrap per pack
- `wrap_<house>_<street>_cricut.png` — only if `CRICUT` is True
- `medium_sheet_1.pdf`, … — medium stickers, portrait A4, up to 2 per page
- `medium_wrap_<house>_<street>.pdf` — one medium sleeve per pack
- `large_cover_<house>_<street>.pdf` — one large cover per entry

Re-running the same order number overwrites files with the same names in that folder. Tell the user the folder path and the files written.
