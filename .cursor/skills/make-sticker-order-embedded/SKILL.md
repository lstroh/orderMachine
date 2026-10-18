---
name: make-sticker-order-embedded
description: >-
  Build a Doorstep Studio small bin-sticker order (140x100mm) with the embedded-font
  scripts: write the ORDERS list in make_order.py and run it so sticker sheets and
  one personal wrap per pack are produced, with Arial and Times New Roman embedded
  so Affinity keeps the letter widths. Use when the user names this skill and asks
  to make a sticker order, fill ORDERS, run make_order.py, or produce Affinity-safe
  PDFs with the new scripts.
disable-model-invocation: true
---

# Make a sticker order (embedded fonts)

Small stickers only (140 x 100mm). Scripts live in `stikerts/Ideas 27 07/Generation/`. Run from that folder. Do not edit `bin_sticker_small.py`, `bin_sticker_core.py`, or `sticker_wrap.py` to place an order.

These scripts embed fonts. On this PC they use Arial and Times New Roman from `C:\Windows\Fonts`. If those are missing, they use the TeX Gyre fonts in `assets/fonts/`. Do not remove `import embedded_fonts`. The backup scripts, which do not embed fonts, are in `stikerts/Ideas 27 07/Generation-backup/` and are run by the make-sticker-order skill.

## What to collect

One pack is one design. Ask for anything missing. Do not guess a house number, street, buyer name, or eBay design name.

The user gives the **eBay design name**, not the script key. Look it up below and write the `style` key into `ORDERS`. Match ignoring case, and treat `-`, `–`, and `—` as the same. A name may include its D-number (`D24 Modern Minimal`) or not (`Modern Minimal`). If the name is not in this table, stop and ask. Do not invent a key.

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
| `order_number` | yes | Folder name under `G:\My Drive\orders\`. Letters, digits, spaces, hyphens, or underscores. One folder per number. |
| `house_number` | yes | e.g. `"36"` |
| `street_name` | yes | e.g. `"Grove Street"` |
| eBay design name | yes | A name from the table above. Write the mapped `style` key into `ORDERS`, not the eBay name. |
| `buyer_name` | yes | First name. Wrap only: "Thank you, {name}!" |
| `accent` | fixed | Always `"black"`. Do not ask. `p25_landscape_flourish` and `p25b_landscape_flourish` are already black and ignore this key. |
| `paper` | no | `"white"` (default) or `"kraft"`. Ink colours on the wrap only. |
| `care_line` | no | Wrap only. Default is already in `sticker_wrap.py`. |
| `quantity` | no | Stickers in the pack. Default **4**. This is how many sheet slots to write, and the number printed as "Your set of …". |
| Cricut | fixed | Leave `CRICUT = True`. It writes a Print Then Cut PNG of each wrap. It does not cut the sticker sheet. |

`BRAND` at the top of `make_order.py` (`brand_name`, `handle`, `logo_style`) applies to every wrap. Leave it unless the user asks to change it. `logo_style` `"both"` is the chosen design.

## Write ORDERS

`quantity` is not expanded by the script. Expand it yourself: one dict per sticker slot. The first slot of a pack is the wrap. The other slots of that pack are the same sticker fields with `"wrap": False`.

A 4-pack:

```python
ORDERS = [
    {"order_number": "12-34567-89012", "house_number": "36",
     "street_name": "Grove Street", "style": "p25_landscape_flourish",
     "accent": "black", "buyer_name": "Priya", "paper": "kraft",
     "quantity": 4},
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

Put `order_number` on every slot, including `"wrap": False`. Replace the whole `ORDERS` list. Leave `CRICUT = True`. Packs that share an `order_number` go in one folder, and their sheets are filled 4 slots per page inside that folder.

Two wraps with the same house number and street inside one order number share one wrap filename and the second overwrites the first. Say so before running.

## Run

From `stikerts/Ideas 27 07/Generation/`:

```bash
python make_order.py
```

Use `python3` if `python` is not the interpreter that has reportlab. The wrap QR needs the `qrcode` package. Cricut PNGs also need pymupdf and pillow.

Output for each order number is `G:\My Drive\orders\<order_number>\`:

- `sheet_1.pdf`, `sheet_2.pdf`, … — landscape A4, up to 4 stickers
- `wrap_<house>_<street>.pdf` — one wrap per pack
- `wrap_<house>_<street>_cricut.png` — Cricut image of that wrap

Re-running the same order number overwrites files with the same names in that folder. Tell the user the folder path and the files written. Google Drive must be mounted as `G:`.
