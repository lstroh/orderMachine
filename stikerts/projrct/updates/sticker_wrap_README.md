# Personal wrap + order script — `sticker_wrap.py` & `make_order.py`

Doorstep Studio · small bin stickers (140 × 100mm) only · Oct 2026

This file is the reference for both scripts. The first half is for
running them; the second half ("For Cursor / developers") explains how
they're built and how they sit alongside the bin sticker scripts, so code
changes don't break anything.

---

## 1. What they do

**`sticker_wrap.py`** makes a personal paper wrap for each order. It is a
cross-shaped net that folds round a pack of small bin stickers on all four
sides and closes with a tab-and-slot, with no glue or tape. Each wrap is
personal to the order:

- **Front:** "Made especially for", the customer's address (house number
  and street), a half-size preview of the exact design they ordered,
  "Your set of 4 · made by hand", and the Doorstep Studio logo beside the
  name.
- **Back, upper flap:** the "Handmade Just For You" badge, then "Pull the
  tab to open" next to the tongue.
- **Back, lower flap:** "Thank you, {name}!", the care line, the handle
  and "Recycle me with your paper".

**`make_order.py`** is the one script you run per batch. From one list of
orders it makes the sticker sheet(s) and a wrap for each order, and
optionally the Cricut image for each wrap.

## 2. Folder layout

Everything sits in one folder:

```
your-folder/
  bin_sticker_core.py
  bin_sticker_small.py
  sticker_wrap.py
  make_order.py
  assets/
    icons/
      doorstep_studio_mark.png                       <- logo (front)
      doorstep_handmade_sticker_40mm_transparent.png <- badge (back)
      ... the existing sticker icons ...
```

One-time install for the Cricut image: `pip install pymupdf pillow`.

## 3. Running an order

1. Open `make_order.py` and edit the `ORDERS` list.
2. Set `CRICUT = True` to also get the Cricut image, or `False` to print
   and cut by hand.
3. Run `python3 make_order.py`.

Output goes into a dated folder, for example `orders_2026-10-04/`:

- `sheet_1.pdf`, `sheet_2.pdf` … sticker sheets, 4 per A4 (unchanged
  from `bin_sticker_small.render_sheet`)
- `wrap_36_Grove_Street.pdf` … one wrap per order, for hand cutting
- `wrap_36_Grove_Street_cricut.png` … one per order when `CRICUT = True`

### The ORDERS list

Each entry is **one sticker slot** on the sheet. A customer's 4-pack of
the same design is the same entry listed 4 times, with `"wrap": False`
on 3 of them so only one wrap is made.

| Key | Used by | Required | Notes |
|---|---|---|---|
| `house_number` | stickers + wrap | yes | e.g. `"36"` |
| `street_name` | stickers + wrap | yes | e.g. `"Grove Street"` |
| `style` | stickers + wrap | yes | a key from `bin_sticker_small.STYLES` |
| `accent` | stickers + wrap | no | a key from the accent palette |
| `buyer_name` | wrap | yes | first name, for "Thank you, …!" |
| `paper` | wrap | no | `"white"` (default) or `"kraft"`, sets ink colours only |
| `care_line` | wrap | no | defaults to "Laminated to stand up to rain, sun and bin-day knocks." |
| `quantity` | wrap | no | number in "Your set of …", default 4 |
| `wrap` | make_order | no | `False` = no wrap for this entry |

`BRAND` at the top of `make_order.py` adds `brand_name`, `handle` and
`logo_style` to every wrap. `logo_style` is `"both"` (logo on the front,
badge on the back), which is the chosen design. `"mark"` and `"badge"`
also exist (see section 7). The sticker script ignores the wrap-only
keys, so one list feeds both.

## 4. Printing

- **Paper:** plain 100–120gsm, white or kraft.
- **Epson ET-3950 driver:** paper type *Plain Paper / Bright White Paper*
  for uncoated paper, or *Presentation Paper Matte* for matte-coated
  inkjet paper. A4, quality Standard (High if the logo colours look
  patchy).
- **Scale:** 100% / Actual size for the PDF. Never "Fit to page".
- **Drying:** leave 5–10 minutes before cutting or folding, and don't
  stack fresh prints.

## 5. Cutting and folding

**By hand (PDF):** scissors along the printed outline, craft knife for
the slot line.

**Cricut (PNG, Print Then Cut):**
1. Upload the PNG, choose **Complex**, save as a **Print Then Cut image**.
2. Lock the ratio and set **W = 180mm**. H should read **232mm**
   (7.09 × 9.13in). If it doesn't, re-upload.
3. Check the preview shows the outline **and** the slot as cuts.
4. In the print setup, turn on **Use System Dialog** to choose the paper
   type in the Epson driver.
5. If Design Space says it's too large, use **Change Page Size**, never
   Auto-Resize.
6. Test cut first on the setting closest to your paper weight.

**Folding** (there are no printed fold lines):
1. Put the stack face down behind the front panel, centred under the
   address and preview.
2. Fold the two side flaps in.
3. Fold the lower flap up.
4. Fold the upper flap down and push the tongue into the slot.

Pull the tongue to open; push it back to reclose.

## 6. Numbers to keep in sync with other project files

| Item | Value |
|---|---|
| Net size | 180 × 232mm (also the Design Space size) |
| Weight per wrap | ~4.1g at 120gsm, ~3.4g at 100gsm |
| Wraps per A4 sheet | 1 |
| Added thickness | 1 layer on the front, 2–3 on the back; measure the packed envelope against the 5mm Letter limit |

Costed in `StickerBinStickersCosts_v13.xlsx` (Bin Stickers 100x140 4pk,
rows 99–112): £0.13 per wrap cut by hand, £0.42 cut on the Cricut. The
Shipping Plan and Business Plan §6, §7, §9 and §10 include it from 4 Oct
2026. With the wrap, the AKAR box is capped at 3 packs until 4 wrapped
packs are weighed under 100g.

---

## 7. For Cursor / developers

### 7.1 How the scripts fit together

```
make_order.py
 ├─ bin_sticker_small.render_sheet(orders, path)    -> sticker sheets
 └─ sticker_wrap.render_sheet(order, path)          -> wrap PDF
    sticker_wrap.export_png(order, path)            -> Cricut PNG

sticker_wrap.py
 ├─ bin_sticker_small.draw_sticker(c, 0, 0, order)  -> design preview
 ├─ bin_sticker_small.P02_CARD_W / P02_CARD_H       -> 140 x 100mm card
 └─ assets/icons/doorstep_*.png                     -> logo + badge

bin_sticker_small.py -> bin_sticker_core.py (unchanged)
```

- The wrap and the order script **only call** the bin sticker scripts.
  They never modify them, and nothing in the bin sticker scripts depends
  on the wrap. A change to the wrap can't break sticker output.
- The preview calls the real `draw_sticker` with the same order dict, so
  it can never drift from the printed sticker. Don't replace it with a
  re-drawn approximation.
- Import `bin_sticker_small`, **not** the legacy single-file
  `bin_sticker.py`. `test_bin_sticker.py` and the products-gallery skill
  still use `bin_sticker.py`; that's a separate clean-up.
- `sticker_wrap.py` deliberately does **not** import `thankyou_card.py`.
  The paper themes (`PAPER`) are copied locally so the card can be
  redesigned without touching the wrap.

### 7.2 Coordinate system and net geometry

All geometry is in mm, origin at the bottom-left of the net's bounding
box, A4 portrait, long axis vertical. Constants at the top of
`sticker_wrap.py`:

| Constant | Value | Meaning |
|---|---|---|
| `W, H` | 140, 100 | sticker / front panel |
| `STACK` | 2 | thickness allowance round each edge of the stack |
| `SIDE` | 18 | side tuck flaps |
| `LOWER` | 58 | lower flap (carries the slot) |
| `UPPER` | 50 | upper flap |
| `TONGUE`, `TONGUE_W`, `CHAMFER` | 20, 40, 5 | tongue length, width, corner chamfer |
| `SLOT_Y`, `SLOT_W` | 12, 42 | slot height from the net bottom, slot length |
| `NET_W`, `NET_H` | 180, 232 | derived |

```
y   0 –  58   lower flap        (slot at y = 12)
y  58 –  60   bottom edge of stack
y  60 – 160   FRONT panel        x 20 – 160
y 160 – 162   top edge of stack
y 162 – 212   upper flap
y 212 – 232   tongue, 40mm wide, centred
side flaps:   x 0 – 18 and 162 – 180, beside the front panel
```

**Back mapping.** On the back, "back height" h runs 0 (bottom fold) to
100 (top fold):

- lower flap: `y = 58 − h`
- upper flap: `y = 162 + (100 − h)`

The lower flap covers h 0–58 and the upper flap h 50–100, so they overlap
at h 50–58. The slot is at h = 46, and the tongue runs from h 50 down to
h 30, through the slot and under the lower flap.

**Back text is rotated 180°** (`_back_text`, `_badge` inside a rotated
state) because both flaps turn over the top and bottom edges. That makes
"up" on the sheet point down on the back.

**Visible text zones** (outside the overlap): lower flap y ≈ 18–56,
upper flap y ≈ 164–210. Don't put content in y 0–16 (slot/overlap) or on
the side flaps (hidden).

### 7.3 Invariants: don't break these

1. **`NET_W` ≤ ~182mm.** Print Then Cut's A4 limit is about 7.2in
   (~183mm) wide. This is why `SIDE` is 18. Never widen without
   rechecking the current Design Space limit.
2. **`NET_H` stays well under ~255mm** (the A4 Print Then Cut height
   limit) and fits A4 with margin.
3. **The tongue must pass the slot.** The slot (h = 46) must lie between
   the upper flap's tip (h = 50) and the tongue's end (h = 30), with at
   least ~4mm of tongue past the slot, and `SLOT_W` (42) > `TONGUE_W`
   (40).
4. **If `TONGUE`, `UPPER`, `LOWER`, `SIDE` or `STACK` change,**
   `NET_W`/`NET_H` change. Update the Design Space size in this README
   (sections 5 and 6) and the weight figure, and rerun a physical test.
5. **No printed fold lines.** They were removed on purpose; folds are set
   by the stack.
6. **The Cricut PNG has no printed cut lines** (`cricut=True` skips
   `_cut_lines`), so a slightly-off cut doesn't show a stray line.
7. **The PNG must stay true-size:** 300dpi, cropped exactly to the net,
   transparent outside the outline and in the slot.

### 7.4 Function reference (`sticker_wrap.py`)

| Function | Purpose |
|---|---|
| `render_sheet(order, out_path, cricut=False)` | One wrap centred on A4 → PDF. `out_path` can be a path or a file-like object. |
| `export_png(order, out_path, dpi=300)` | Renders `render_sheet(cricut=True)` in memory, rasterises it with PyMuPDF, crops to the net, applies the alpha mask (outline polygon minus a 1mm-tall slot hole) → PNG |
| `draw_wrap(c, ox, oy, order, cricut=False)` | Draws the wrap at (ox, oy) on any ReportLab canvas. Use this to put a wrap on a custom page. |
| `_outline_pts()` | The cut polygon in mm, shared by the PDF outline and the PNG mask. Edit the shape **here only**. |
| `_cut_lines` | Printed outline and slot (PDF only) |
| `_content` | Everything printed: front panel, back flaps |
| `_logo_lockup` | Mark + "DOORSTEP STUDIO"; prints the name only, with a warning, if the PNG is missing |
| `_badge` | Badge PNG cropped to its alpha bounding box and fitted between two y values |
| `_back_text` | Centred lines rotated 180° for the back |
| `_fit` | Shrinks the address font (17 → 10pt) to fit 120mm |

`logo_style` values: `"mark"` puts the logo lockup on the front only;
`"badge"` puts the badge on the front (cramped, ~24mm tall); `"both"` puts
the logo on the front and the badge (32mm) on the back upper flap. The
badge falls back to `"mark"` behaviour if its PNG is missing.

### 7.5 `make_order.py`

- `ORDERS` is sliced into groups of 4 for `bin_sticker_small.render_sheet`.
- Each entry, unless `"wrap": False`, gets `{**BRAND, **entry}`, so
  per-order keys override `BRAND`. That gives one wrap PDF, plus a PNG
  when `CRICUT = True`.
- Output folder is `orders_<YYYY-MM-DD>/`. Re-running on the same day
  overwrites files with the same names.
- Wrap file name: `wrap_<house_number>_<street_name with _>.pdf`. Two
  orders to the same address on the same day would overwrite each other
  (see TODO).

### 7.6 Suggested next steps (not done yet)

- **`quantity` handling in `make_order.py`:** one entry with
  `"quantity": 4` instead of listing it 4 times with `"wrap": False`.
- **A unique wrap file name,** for example including `buyer_name` or an
  order number.
- **`test_sticker_wrap.py`:** check that `NET_W` ≤ 182, the
  tongue/slot invariant, the PNG size (2127 × 2742px at 300dpi), and
  that all 25 small styles render in the preview without errors.
- **Medium size:** a 210 × 140mm front can't use this net (too wide for
  A4 and Print Then Cut). It would need its own design.
- **Branding inconsistency:** the bin sticker script docstrings still
  say "Kerbside Craft Co."; the brand is Doorstep Studio.

### 7.7 Quick checks after any change

```
python3 sticker_wrap.py     # writes sticker_wrap_sample.pdf + _cricut.png
python3 make_order.py       # full batch: sheet + wrap (+ PNG)
```

Open the sample PDF and check:
1. The address fits on one line.
2. The preview matches the sticker sheet.
3. Back text is upside down on the flat sheet. That's correct: it reads
   upright on the folded pack.
4. The PNG is 2127 × 2742px with a transparent slot.
