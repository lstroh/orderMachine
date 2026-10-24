# Name labels — Print Then Cut workflow

*Created 23 Oct 2026. Status: **ready to test, not yet tried on the Explore 5.** Replaces the cut-only sheet + `cut_lines.svg` route for name labels once the test passes.*

## What the script makes

`name_sticker.py` exports every label as its own PNG:

- 50 × 25mm, 300dpi (591 × 295 pixels)
- white rounded rectangle (3mm corners) with the icon and name; **transparent outside the label**, so Design Space cuts exactly around the rounded shape
- sorted into **one folder per child, then per category** inside `labels_png/` (e.g. `labels_png/lucas/sports/`), numbered in order: `01_lucas_soccer_ball.png`, `02_lucas_basketball.png` …

Commands (run in the scripts folder):

| You want | Command |
|---|---|
| **One sheet image, labels already in a grid (recommended)** | `python3 name_sticker.py --png-sheet "Lucas" "Sports" "#2F6FA8"` |
| Two packs on one sheet | `python3 name_sticker.py --png-sheet "Lucas" "Sports" + "Mia" "Ocean" "#B0532E"` |
| A standard 12-label pack for one child, one PNG per label | `python3 name_sticker.py --png-pack "Lucas" "Sports" "#2F6FA8"` |
| Every icon in a category for one child (backups included, for custom orders) | `python3 name_sticker.py --png-pack "Lucas" "Sports" --all` |
| Every icon once, all categories (test set) | `python3 name_sticker.py --png-all` |
| A proof PDF of all standard packs | `python3 name_sticker.py --packs-proof` |

Theme names: Sports, Cute animals, Dinosaurs, Vehicles, Pirates, Ocean, Knights & castles, Dragons, Wizards, Space, Music, Garden & bugs, Farm & wild. The colour is optional (default blue `#2F6FA8`).

**Standard packs (23 Oct 2026):** 12 labels per pack. Packs are set for Sports, Cute animals, Vehicles, Pirates, Ocean, Knights & castles, Wizards, Music and Garden & bugs. Dinosaurs, Dragons, Space and Farm & wild have fewer than 12 icons and aren't sold as packs yet (the script warns and uses all their icons). Icons not in a pack are kept as backups.

Each child gets their own folder, so orders don't mix. Still clear out `labels_png/` now and then so old orders don't pile up.

## Sheet image route (recommended, added 24 Oct 2026)

`--png-sheet` puts every label of the order on **one** transparent PNG, in a grid with 3mm gaps, in `labels_png/sheet_<name>-<category>….png`. Design Space cuts around each separate shape in a Print Then Cut image, so one upload still cuts every label on its own.

- **Fits one sheet:** up to 27 labels lying flat (3 across) or 30 standing up (the script turns them automatically). Two 12-label packs = 24, lying flat, 156 × 221mm. Add `--colour` for the colour line. More than 30 labels: the script stops; split the order into two sheets.
- **In Design Space:** Upload → the sheet PNG → **Print Then Cut image** → add to canvas → set the **W and H the script printed** (lock on) → check one label measures 50 × 25mm → Make It with **1 copy**. Then print, laminate and cut as in steps 5–7 below.
- **Don't** use Flatten, Attach or a white background box: those would join the labels into one shape.
- **To check on the first test:** each label gets its own cut line, and Design Space doesn't shrink the sheet.

## In Design Space (one PNG per label)

*Menu names below follow Cricut's current upload flow; if a button is named differently on your version, the step is the same. Use this route for single replacement labels, or if the sheet route fails the test.*

1. **Settings:** switch units to metric once, so sizes show in mm.
2. **Upload:** New project → Upload → Upload Image → choose the first PNG. The corners should show the checkerboard (transparent). Pick **Print Then Cut image** → Upload. Repeat for every PNG in the theme folder.
3. **Size:** add the uploads to the canvas, select each one and set **W 50mm × H 25mm** with the lock on. Check this every time: Design Space doesn't always read the file's 300dpi.
4. **Make It:** set how many copies of each label you want on the mat (e.g. a 27-label Sports pack = 9 icons × 3 copies). Design Space arranges the labels itself, inside the Print Then Cut area (about 18.3 × 27cm on A4).
5. **Print** from Design Space, never from a saved PDF. Highest quality, no scaling, no edge-to-edge. Leave **bleed on**: it only extends the white edge.
   - Material: **Cricut Printable Waterproof Sticker Set, White** (the safe choice for the sensor).
6. **Laminate** the printed sheet with the set's laminate before loading.
7. **Cut:** Light Grip mat, choose the laminated sticker-set material setting.
   - **Kiss cut** → a peelable sheet of labels to sell as is (no guillotine, no corner punch).
   - **Die cut** → loose labels.

## First test — record the results here

| Check | Result |
|---|---|
| Upload shows transparent corners and offers Print Then Cut | |
| Sheet PNG: every label gets its own cut line, size as the script printed | |
| PNG imports at 50 × 25mm without resizing (or needed resizing) | |
| Cut follows the rounded corners cleanly | |
| How many labels Design Space fits on one A4 mat | |
| Kiss cut leaves the backing intact on the laminated set | |
| Small detail readable (knight on horse, spell book, treasure map, pirate flag) | |
| Wide icons (car, bus, truck, skateboard) big enough | |

## Colour line

A separate set of 195 full-colour icons (Noto Emoji and Fluent Emoji Flat), picked 23 Oct 2026, sits alongside the black icons. Keys start with `c_`, files are in `assets/icons_colour/`, and the list is `ICON_THEMES_COLOUR` in `name_sticker.py`. Colour icons print as they are; the accent colour only affects black icons. The name stays black.

- Proof of every colour icon (Adam/Tom): `python3 name_sticker.py --colour-proof` -> `name_labels_colour_proof.pdf`
- PNGs for one child, every colour icon in a category: `python3 name_sticker.py --png-pack "Lucas" "Ocean" --colour` -> `labels_png/lucas/ocean-colour/`
- Colour packs of 12 aren't set yet, so `--colour` uses every colour icon in the category.
- Colour printing uses much more ink than black, so check the ink cost per sheet in the costs spreadsheet before pricing.

## Notes

- One upload per **unique** label. Repeats are done with copies in Make It, not extra uploads.
- Icons sit about 2.4mm from the label edge. Print Then Cut is more accurate than lining up by eye, but check nothing is clipped.
- Credits for Game Icons go in the listing: see `claude/Name-Label-Icon-Credits.md`.
- The old sheet PDF (`python3 name_sticker.py`) and `generate_name_sticker_cut_lines.py` still work for the cut-only route until the test is done.
