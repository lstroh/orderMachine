# Embedded fonts: copy of the PDF scripts (v2, 12 Oct 2026)

> **Status: in testing.** Use this copy when you want to open or edit the PDFs in Affinity. The original scripts are unchanged and still print correctly at home. See `claude/Font-Policy.md`.

## The problem it fixes

The original scripts use ReportLab's built-in **Helvetica** and **Times**. For those two fonts, the PDF carries neither the font nor its letter widths (the PDF rules allow this).

- **Printing and PDF viewers are fine.** They have the widths built in.
- **Affinity breaks.** It gives every letter zero width, so the house number and street name stack into blobs, whichever replacement font you choose in its dialog.

## What this copy does

Every PDF it writes carries the font and its widths.

**Which fonts it uses:**
1. **On your Windows PC:** **Arial** for Helvetica and **Times New Roman** for Times, straight from `C:\Windows\Fonts`, following the font policy. Affinity already has them installed, so it opens the PDFs with no dialog. They look the same as Windows prints today.
2. **Anywhere else** (e.g. a Claude cloud session), or if a Windows font is missing: **TeX Gyre Heros / Termes** from `assets/fonts/`. These are free, with the same widths.

**Width check:** every time it runs, the module compares each font's letter widths with the originals, for all letters, digits and the punctuation the scripts use. A font that doesn't match is skipped, so text can't shift.

## What changed

| | |
|---|---|
| `embedded_fonts.py` (new) | Picks the fonts as above and registers them under the names the scripts already use |
| `assets/fonts/` (new) | TeX Gyre fallback fonts plus their licence |
| Each script | One added line: `import embedded_fonts`. Nothing else changed. |
| `test_bin_sticker.py` | Unchanged |

Scripts in this copy:

- `bin_sticker.py`, `bin_sticker_core.py`, `bin_sticker_small.py`, `bin_sticker_medium.py`
- `sticker_wrap.py`, `sticker_wrap_medium.py`, `sticker_cover_large.py`
- `instructions_card.py`, `thankyou_card.py`
- `make_order.py`

**Built from the project copies.** If you've changed a script in Cursor since, use your version and add `import embedded_fonts` above its first `import`.

## Checked

19 PDFs were compared against the originals, rendered from the same orders: every bin sticker style, all wraps and the cover, the instruction cards, the thank-you cards and the name labels.

| | Fallback fonts (TeX Gyre) | Windows fonts (tested with metric-identical stand-ins) |
|---|---|---|
| Text | identical, 17,000+ characters | identical |
| Letter sizes | identical | identical |
| Positions | 0.0000mm shift | 0.0000–0.02mm, except lines containing "·" (≤0.2mm) |
| Fonts embedded with widths | all | all |
| `test_bin_sticker.py` | 134 / 134 passed | 134 / 134 passed |

**Why "·" moves slightly:** Arial and Times New Roman draw the middle dot wider than Helvetica and Times. Footer lines like "@doorstepstudiouk · Made by hand in the UK" therefore move by under 0.2mm. Nothing else moves.

The real Arial and Times New Roman files can't be tested in the cloud. Your PC runs the same width check every time, so if they ever differ, the module switches to the fallback fonts automatically.

## How to use it

1. **Make a new folder** and copy your current scripts folder into it, including `assets/icons` and `assets/instructions`.
2. **Unzip this package over it.** It adds the scripts, `embedded_fonts.py` and `assets/fonts/`.
3. **Check which fonts it picked.** In that folder, run `python embedded_fonts.py`. Each line should end in `C:\Windows\Fonts\arial...` or `...times...`.
4. **Make a test sheet** with `python make_order.py` and open `sheet_1.pdf` in Affinity. Expect no dialog, the number above the flourishes, and the street name centred between them.
5. **Print one sheet** and compare it with one made by the original scripts.

**To undo:** delete the `import embedded_fonts` line, or go back to your original folder.

## Licences

- **Arial / Times New Roman:** come with Windows. They may be embedded in PDFs and used on printed products you sell. The font files themselves are not shared, since the script reads them from Windows.
- **TeX Gyre:** GUST Font License (LPPL 1.3c), free for commercial use and embedding. Converted to TrueType and renamed "TeX Gyre ... TTF", as the licence requests.
