# Personal wrap + order script — `sticker_wrap.py` & `make_order.py`

Doorstep Studio · small bin stickers (140 × 100mm) only · Oct 2026 ·
how-to video QR code added to the back flap 10 Oct 2026

This file is the reference for both scripts. The first half is for
running them; the second half ("For Cursor / developers") explains how
they're built and how they sit alongside the bin sticker scripts and the
instructions card, so code changes don't break anything.

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
- **Back, lower flap:** the **how-to video QR code** on the left, and beside
  it "Thank you, {name}!", the care line, "Need a hand putting it on? Scan
  for the 40-second how-to video.", the handle and "Recycle me with your
  paper".

The QR code is the same code, link and size as on the A6 instructions card
(19mm). The card is still in every order; the code on the wrap is there
because the wrap is where the spare stickers are kept ("Tuck it back in to
keep your spares safe"), so the video stays with them after the card has
gone.

**`make_order.py`** is the one script you run per batch. From one list of
orders it makes the sticker sheet(s) and a wrap for each order, and
optionally the Cricut image for each wrap. The instructions card is not
made here: every card is identical, so it is printed in batches with
`instructions_card.py`.

## 2. Folder layout

Everything sits in one folder:

```
your-folder/
  bin_sticker_core.py
  bin_sticker_small.py
  instructions_card.py        <- the wrap takes the QR code and video link from here
  sticker_wrap.py
  make_order.py
  assets/
    icons/
      doorstep_studio_mark.png                       <- logo (front)
      doorstep_handmade_sticker_40mm_transparent.png <- badge (back)
      ... the existing sticker icons ...
```

One-time installs: `pip install qrcode` (QR code) and
`pip install pymupdf pillow` (Cricut image).

## 3. Running an order

1. Open `make_order.py` and edit the `ORDERS` list. Every entry needs
   `order_number`.
2. `CRICUT` is `True`: each wrap also gets a Cricut Print Then Cut PNG.
   The sticker sheets stay PDFs.
3. Run `python make_order.py`.

Output for each order number goes to `G:\My Drive\orders\<order_number>\`:

- `sheet_1.pdf`, `sheet_2.pdf` … sticker sheets, 4 per A4 (unchanged
  from `bin_sticker_small.render_sheet`)
- `wrap_36_Grove_Street.pdf` … one wrap per pack, for hand cutting
- `wrap_36_Grove_Street_cricut.png` … one per wrap

### The ORDERS list

Each entry is **one sticker slot** on the sheet. A customer's 4-pack of
the same design is the same entry listed 4 times, with `"wrap": False`
on 3 of them so only one wrap is made.

| Key | Used by | Required | Notes |
|---|---|---|---|
| `order_number` | make_order | yes | folder name under `G:\My Drive\orders\` |
| `house_number` | stickers + wrap | yes | e.g. `"36"` |
| `street_name` | stickers + wrap | yes | e.g. `"Grove Street"` |
| `style` | stickers + wrap | yes | a key from `bin_sticker_small.STYLES` |
| `accent` | stickers + wrap | no | a key from the accent palette |
| `buyer_name` | wrap | yes | first name, for "Thank you, …!" |
| `paper` | wrap | no | `"white"` (default) or `"kraft"`, sets ink colours only |
| `care_line` | wrap | no | defaults to "Laminated to stand up to rain, sun and bin-day knocks." |
| `quantity` | wrap | no | number in "Your set of …", default 4 |
| `video_url` | wrap | no | leave out to use `instructions_card.VIDEO_URL`; `None` = no QR code on this wrap |
| `wrap` | make_order | no | `False` = no wrap for this entry |

`BRAND` at the top of `make_order.py` adds `brand_name`, `handle`
(`doorstepstudiouk`, matching the card footer and the YouTube channel)
and `logo_style` to every wrap. `logo_style` is `"both"` (logo on the
front, badge on the back), which is the chosen design. `"mark"` and
`"badge"` also exist (see section 7). The sticker script ignores the
wrap-only keys, so one list feeds both.

**The video link lives in one place:** `VIDEO_URL` at the top of
`instructions_card.py`. The wrap reads it from there, so the card and the
wrap can't point at different videos.

## 4. Printing

- **Text colour:** all wrap text prints near-black (#2C2C2A on white,
  #3D2015 on kraft); the light grey was too faint at this size.
- **Paper:** plain 100–120gsm, white or kraft. The QR code is pure black
  on either; white gives the best scan, so test-scan kraft before using it.
- **Epson ET-3950 driver:** paper type *Plain Paper / Bright White Paper*
  for uncoated paper, or *Presentation Paper Matte* for matte-coated
  inkjet paper. A4, quality Standard (High if the logo colours or the QR
  code look patchy).
- **Scale:** 100% / Actual size for the PDF. Never "Fit to page"; the QR
  code's black area should measure **19mm**.
- **Drying:** leave 5–10 minutes before cutting or folding, and don't
  stack fresh prints.
- **First print with the QR code:** fold one wrap and scan the back flap
  with an iPhone and an Android phone, the same checklist as the card
  (`YouTube-Upload-Kit.md` §6).

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

The PNG carries the QR code at 300dpi (it decodes from the PNG at full,
half and third size). Scan a Cricut-printed wrap once too, since Design
Space does its own printing.

**Folding** (there are no printed fold lines):
1. Put the stack face down behind the front panel, centred under the
   address and preview.
2. Fold the two side flaps in.
3. Fold the lower flap up.
4. Fold the upper flap down and push the tongue into the slot.

Pull the tongue to open; push it back to reclose.

**Packing (Small, C6):** the instructions card goes at the back of the
envelope, thank-you side out; the wrapped pack in front, personalised
side out. The card stays outside the wrap.

## 6. Numbers to keep in sync with other project files

| Item | Value |
|---|---|
| Net size | 180 × 232mm (also the Design Space size) |
| Weight per wrap | ~4.1g at 120gsm, ~3.4g at 100gsm |
| Wraps per A4 sheet | 1 |
| Added thickness | 1 layer on the front, 2–3 on the back; measure the packed envelope against the 5mm Letter limit |
| QR code | 19mm, 0.66mm modules, level Q, version 3, 2.6mm quiet zone (same as the A6 card) |

Costed in `StickerBinStickersCosts_v14.xlsx` (Bin Stickers 100x140 4pk,
rows 60–71): £0.13 per wrap cut by hand (paper £0.03 + ink £0.10), £0.22
cut on the Cricut (+ £0.09 mat and blade). The QR code changes no cost:
ink is already a full-page placeholder. The Shipping Plan and Business
Plan §6, §7, §9 and §10 include the wrap. With the wrap, the AKAR box is
capped at 3 packs until 4 wrapped packs are weighed under 100g.

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
 ├─ instructions_card.VIDEO_URL, normalise_video_url,
 │  qr_matrix, qr_quiet_zone, QR_DARK,
 │  QR_MIN_MODULE_MM                                -> QR code on the back flap
 └─ assets/icons/doorstep_*.png                     -> logo + badge

bin_sticker_small.py -> bin_sticker_core.py (unchanged)
instructions_card.py (unchanged; the wrap only imports from it)
```

- The wrap and the order script **only call** the bin sticker scripts and
  the instructions card. They never modify them, and nothing in those
  files depends on the wrap. A change to the wrap can't break sticker or
  card output.
- The preview calls the real `draw_sticker` with the same order dict, so
  it can never drift from the printed sticker. Don't replace it with a
  re-drawn approximation.
- **The QR code is never re-implemented in the wrap.** Link cleaning and
  checking (`normalise_video_url`: YouTube links only, `?si=` stripped),
  the module matrix (`qr_matrix`: level Q, version 3) and the quiet-zone
  width (`qr_quiet_zone`) all come from `instructions_card.py`. Only the
  drawing (`_qr`, solid black runs, same method as the card) lives in the
  wrap. If any of those names change in `instructions_card.py`, update
  the import in `sticker_wrap.py`.
- Import `bin_sticker_small`, **not** the legacy single-file
  `bin_sticker.py`. `test_bin_sticker.py` and the products-gallery skill
  still use `bin_sticker.py`; that's a separate clean-up.
- The paper themes (`PAPER`) are copied locally, not imported, so the card
  can be redesigned without touching the wrap. On the wrap, `"muted"` is set
  to the same colour as `"text"` (10 Oct 2026): all wrap text prints
  near-black for readability, with bold and size doing the hierarchy. Only
  `"guide"` (the hand-cut outline) is light.

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
| `LOWER_VIS_Y0`, `LOWER_VIS_Y1` | 18, 56 | clear zone on the lower flap (centre `LOWER_CY` = 37) |
| `QR_SIZE_MM` | 19 | code area, same as the A6 card |
| `QR_EDGE_MM` | 8 | code's outer edge from the flap's side edge |
| `QR_TEXT_GAP_MM` | 5 | code to text (must be ≥ the 2.6mm quiet zone) |
| `TEXT_EDGE_MM` | 6 | text block's margin from the other side edge |

```
y   0 –  58   lower flap        (slot at y = 12; QR + text in y 18 – 56)
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

**Back content is rotated 180°** (`_back_text`, `_lower_flap` and `_badge`
inside a rotated state) because both flaps turn over the top and bottom
edges. That makes "up" on the sheet point down on the back. In
`_lower_flap`, the local frame's origin is the centre of the clear zone,
and x/y run as the customer reads the back: the code sits on the left,
text on the right.

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
8. **QR quiet zone stays clear.** `_lower_flap` raises an error if the
   2.6mm quiet zone would cross the flap edge, leave the y 18–56 clear
   zone or touch the text. Keep the code at 19mm (0.66mm modules); never
   below 0.5mm per module (`QR_MIN_MODULE_MM`, enforced).
9. **QR links only to the unlisted YouTube how-to video**, via
   `normalise_video_url`. Never a shop, website or redirect link (eBay
   insert rules; see `YouTube-Upload-Kit.md` §4).

### 7.4 Function reference (`sticker_wrap.py`)

| Function | Purpose |
|---|---|
| `render_sheet(order, out_path, cricut=False)` | One wrap centred on A4 → PDF. `out_path` can be a path or a file-like object. |
| `export_png(order, out_path, dpi=300)` | Renders `render_sheet(cricut=True)` in memory, rasterises it with PyMuPDF, crops to the net, applies the alpha mask (outline polygon minus a 1mm-tall slot hole) → PNG |
| `draw_wrap(c, ox, oy, order, cricut=False)` | Draws the wrap at (ox, oy) on any ReportLab canvas. Use this to put a wrap on a custom page. |
| `_outline_pts()` | The cut polygon in mm, shared by the PDF outline and the PNG mask. Edit the shape **here only**. |
| `_cut_lines` | Printed outline and slot (PDF only) |
| `_content` | Everything printed: front panel, back flaps |
| `_lower_flap` | Back lower flap: QR code + thank-you, care line, video prompt, footer; checks the quiet zone. Falls back to centred text when `video_url` is `None` |
| `_qr` | Draws the code as solid black runs from `instructions_card.qr_matrix` |
| `_logo_lockup` | Mark + "DOORSTEP STUDIO"; prints the name only, with a warning, if the PNG is missing |
| `_badge` | Badge PNG cropped to its alpha bounding box and fitted between two y values |
| `_back_text` | Centred lines rotated 180° for the back |
| `_fit` | Shrinks the address font (17 → 10pt) to fit 120mm |

`logo_style` values: `"mark"` puts the logo lockup on the front only;
`"badge"` puts the badge on the front (cramped, ~24mm tall); `"both"` puts
the logo on the front and the badge (32mm) on the back upper flap. The
badge falls back to `"mark"` behaviour if its PNG is missing.

### 7.5 `make_order.py`

- Entries are grouped by `order_number`. Each group is sliced into
  pages of 4 for `bin_sticker_small.render_sheet`.
- Each entry, unless `"wrap": False`, gets `{**BRAND, **entry}`, so
  per-order keys override `BRAND`. That gives one wrap PDF and, because
  `CRICUT` is `True`, one Print Then Cut PNG. The PNG is the wrap, not
  the sticker sheet.
- Output folder is `G:\My Drive\orders\<order_number>\`. Re-running that
  order number overwrites files with the same names.
- Wrap file name: `wrap_<house_number>_<street_name with _>.pdf`. Two
  wraps for the same address inside one order number overwrite each
  other (see TODO).

### 7.6 Suggested next steps (not done yet)

- **`quantity` handling in `make_order.py`:** one entry with
  `"quantity": 4` instead of listing it 4 times with `"wrap": False`.
- **A unique wrap file name,** for example including `buyer_name` or an
  order number.
- **`test_sticker_wrap.py`:** check that `NET_W` ≤ 182, the
  tongue/slot invariant, the PNG size (2127 × 2742px at 300dpi), that the
  back-flap QR code decodes to `instructions_card.VIDEO_URL`, and that all
  25 small styles render in the preview without errors.
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
5. The back-flap QR code scans to the how-to video (checked 10 Oct 2026:
   decodes from the PDF at 72–300dpi and from the PNG at full, half and
   third size; code measures 19.0mm).
