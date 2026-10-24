---
name: make-name-label-order
description: >-
  Build a kids' name-label order with stikerts/name stickers/name_sticker.py:
  ask for the order number, the child's name, the category, black or colour
  icons, and a 12-pack or the full set, then run --png-sheet and copy the
  sheet into G:\My Drive\orders\<order number>\. Use when the user asks for
  name labels, name stickers, a name-label order, or to run name_sticker.py
  for an order.
disable-model-invocation: true
---

# Make a name-label order

Script: `stikerts/name stickers/name_sticker.py`. Run from that folder. Do not edit the script to place an order. Do not run `--png-all`, `--packs-proof`, `--colour-proof`, or the sample PDF for an order.

This writes one Print Then Cut sheet per run. The Explore 5 test in `stikerts/name stickers/Name-Labels-Print-Then-Cut.md` is still open. Report the file and the size the script prints. Machine steps stay in that file.

## What to collect

One pack is one child and one category. Ask for anything missing. Do not guess an order number, a child's name, or a category.

Ask these two every time, even when the rest of the order is already complete:

- **Black icons or colour icons?** Colour means the colour icon line (`--colour`). The accent is a separate tint on the silhouette icons. The default accent is `#000000`. A colour name on its own is an accent, so ask for the hex.
- **Standard 12-pack, or every icon in the category?**

| Field | Required | Notes |
|---|---|---|
| `order_number` | yes | Folder name under `G:\My Drive\orders\`. Letters, digits, spaces, hyphens, or underscores. One folder per number. |
| child name | yes | Printed on every label in that pack. Keep the user's spelling. |
| category | yes | A theme name from the script. See below. |
| icon line | yes | Ask every time. Black, or colour (`--colour`). |
| set | yes | Ask every time. 12-pack, or every icon (`--all` on the black line). |
| accent | no | Hex `#RRGGBB`. Default `#000000` when they don't give one. This tints the silhouette icons. The printed name is `#000000` on every sheet. If they name a colour and don't give a hex, ask for the hex. Do not invent one. On the colour line the accent is ignored and the name stays `#000000`. |
| copies | no | How many times Design Space should cut the sheet. Default **1**. Do not duplicate labels inside the PNG. |

Several children can share one order number. They can share one sheet only when every pack on that sheet uses the same icon line and the same set, and the label count is 30 or under. A different line or set is a second sheet in the same folder.

## Categories

Use the theme name as it appears in `name_sticker.py`. Match ignoring case, and treat `and` as `&`. If the name is not a theme, stop and ask. Do not invent one.

Read `PACKS`, `PACKS_NOT_READY`, `ICON_THEMES`, and `ICON_THEMES_COLOUR` from the script when counting. Do not rely on a remembered count.

Black 12-packs exist only for themes in `PACKS`. Themes in `PACKS_NOT_READY` are not 12-packs. The colour line has no 12-packs: `--colour` always uses every icon in that category, and `--all` does not change it. The accent is ignored.

Before running:

- They asked for a black 12-pack and the theme is only in `PACKS_NOT_READY`: say how many icons it has, and that it is not a 12-pack. Ask if they want all of them. Wait.
- They asked for a 12-pack and the colour line: say there is no colour 12-pack, give the colour icon count, and ask them to pick the colour line (all of those icons) or the black 12-pack. Wait.
- More than 30 labels on one sheet: split into two sheets in the same order folder. The script will refuse a single sheet.
- More than 27 labels and 30 or under: the script turns every label on its side and the name reads from the bottom. Say so and wait for a yes.

A black 12-pack is 12 labels. Two of them are 24 labels, lying flat, and fit one sheet.

## Run

From `stikerts/name stickers/`:

```bash
python name_sticker.py --png-sheet "Lucas" "Sports" "#000000"
```

Use `python3` if `python` is not the interpreter that has reportlab and pillow.

| Order | Command |
|---|---|
| Black 12-pack | `python name_sticker.py --png-sheet "Lucas" "Sports" "#000000"` |
| Black, every icon | add `--all` |
| Colour line, every icon | `python name_sticker.py --png-sheet "Lucas" "Ocean" --colour` |
| Two packs, one sheet | `python name_sticker.py --png-sheet "Lucas" "Sports" "#000000" + "Mia" "Ocean" "#B0532E"` |

Put the accent after the child it belongs to. An accent after only the second name does not colour the first. Omit the accent on a colour-line run. `--colour` and `--all` apply to the whole command, not to one pack.

The script writes `labels_png/sheet_<child>-<category>.png` next to itself (packs joined with `_`, plus `_colour` on the colour line). `--all` is not part of the filename. A 12-pack and a full set for the same child and category write the same file. Copy the first sheet into the order folder under a distinct name (add `_pack` or `_all`) before the next run.

If the script prints the missing-font warning, stop. Do not copy that sheet into the order folder.

## Copy into the order folder

Google Drive must be mounted as `G:`. If it is not, stop. Do not treat `labels_png` as the finished order.

Copy the PNG whose path the script printed to:

`G:\My Drive\orders\<order_number>\`

Create the folder if it is not there. Re-running the same order number overwrites a file with the same name. Tell the user the folder path, the file name, and the W × H line the script printed.

Also tell them:

- label count, and whether the script said the labels are standing up
- copies to set in Design Space (default 1)
- Upload as a **Print Then Cut image**. Set the W and H the script printed, lock on. Check one label is 50 × 25 mm. Do not Flatten, Attach, or add a white background. Make It with that copy count.

## Separate PNGs

Use this only when the user asks for one PNG per label, or for a single replacement label:

```bash
python name_sticker.py --png-pack "Lucas" "Sports" "#000000"
```

Add `--all` or `--colour` the same way as a sheet. Copy that child-and-category folder into the order folder. Each file is set to 50 × 25 mm in Design Space.
