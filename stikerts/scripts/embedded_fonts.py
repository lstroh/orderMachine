"""
embedded_fonts.py -- Doorstep Studio (12 Oct 2026, v2: Windows fonts first;
18 Oct 2026, v3: adds Courier, used by render_design_key_gallery.py)

Makes every PDF the scripts write carry its own fonts, WITH the letter widths
inside the file. The original PDFs leave both out for Helvetica and Times
(the PDF rules allow that for those two fonts). Print and PDF viewers cope,
but Affinity then gives every letter zero width and stacks the text into
blobs, whatever replacement font you pick.

HOW IT WORKS
The scripts draw text with ReportLab's built-in names "Helvetica",
"Helvetica-Bold", "Times-Bold" and so on. Importing this module re-registers
those same names as real TrueType fonts, which ReportLab embeds (subset to
the letters used, widths included) in every PDF. No setFont / stringWidth
call in any script has to change.

WHICH FONTS (Font-Policy.md: free Windows fonts first)
1. On Windows: Arial for Helvetica, Times New Roman for Times, Courier New
   for Courier, straight from
   C:\\Windows\\Fonts. Affinity has these installed, so it opens the PDFs
   with no dialog, and they look the same as Windows prints today. They are
   designed with the same letter widths as Helvetica and Times. The check below
   confirms that every time it runs.
2. Anywhere else (e.g. a Claude cloud session), or if a Windows font is
   missing: TeX Gyre Heros / Termes / Cursor from assets/fonts/ (free, same widths,
   GUST Font License).

WIDTH CHECK
At import, each font's letter widths are compared with the built-in ones for
every character the scripts use. If a letter, digit or common punctuation
mark differs, that font is not used (the next choice is tried), so text
auto-fit, line breaks and centring can't shift. The one character allowed
to differ is the middle dot "·" in the footers (Arial and Times New Roman
draw it slightly wider), which moves those footer lines by under 0.2mm.

getAscentDescent() is kept at the built-in values, so the P21 street name
(the one place that centres on ascent/descent) sits where it did.

To go back to the old behaviour, remove the "import embedded_fonts" line from
a script (or delete this file).
"""

import os
import string

from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

HERE = os.path.dirname(os.path.abspath(__file__))
BUNDLED_DIR = os.path.join(HERE, "assets", "fonts")
WINDOWS_DIR = os.environ.get("EMBEDDED_FONTS_WINDOWS_DIR") or os.path.join(
    os.environ.get("WINDIR", r"C:\Windows"), "Fonts")

# built-in name -> (Windows font file, bundled fallback file)
FONTS = {
    "Helvetica":             ("arial.ttf",   "texgyreheros-ttf-regular.ttf"),
    "Helvetica-Bold":        ("arialbd.ttf", "texgyreheros-ttf-bold.ttf"),
    "Helvetica-Oblique":     ("ariali.ttf",  "texgyreheros-ttf-italic.ttf"),
    "Helvetica-BoldOblique": ("arialbi.ttf", "texgyreheros-ttf-bolditalic.ttf"),
    "Times-Roman":           ("times.ttf",   "texgyretermes-ttf-regular.ttf"),
    "Times-Bold":            ("timesbd.ttf", "texgyretermes-ttf-bold.ttf"),
    "Times-Italic":          ("timesi.ttf",  "texgyretermes-ttf-italic.ttf"),
    "Times-BoldItalic":      ("timesbi.ttf", "texgyretermes-ttf-bolditalic.ttf"),
    "Courier":               ("cour.ttf",    "texgyrecursor-ttf-regular.ttf"),
    "Courier-Bold":          ("courbd.ttf",  "texgyrecursor-ttf-bold.ttf"),
    "Courier-Oblique":       ("couri.ttf",   "texgyrecursor-ttf-italic.ttf"),
    "Courier-BoldOblique":   ("courbi.ttf",  "texgyrecursor-ttf-bolditalic.ttf"),
}

# characters whose width must match the built-in font exactly
MUST_MATCH = string.ascii_letters + string.digits + " .,:;!?'\"-()&@/#%+°–—’“”"
ALLOWED_TO_DIFFER = "·"

USED = {}   # built-in name -> file actually embedded (for checking/reporting)


def _width_problems(font, builtin):
    """Characters in MUST_MATCH whose width differs from the built-in font
    (in 1/1000 em), or that the font doesn't have."""
    bad = []
    for ch in MUST_MATCH:
        if ord(ch) not in font.face.charToGlyph:
            bad.append(ch)
        elif abs(font.stringWidth(ch, 1000) - builtin.stringWidth(ch, 1000)) > 0.6:
            bad.append(ch)
    return bad


def register(verbose=False):
    """Register every name in FONTS. Safe to call twice. Fails loudly if no
    usable font is found, so a PDF never silently goes back to non-embedded
    fonts."""
    notes = []
    for name, (win_file, bundled_file) in FONTS.items():
        if name in USED:
            continue
        builtin = pdfmetrics.getFont(name)   # the built-in Adobe metrics
        chosen = None
        for path in (os.path.join(WINDOWS_DIR, win_file), os.path.join(BUNDLED_DIR, bundled_file)):
            if not os.path.exists(path):
                continue
            try:
                font = TTFont(name, path)
            except Exception as e:   # e.g. a font that forbids embedding
                notes.append(f"{name}: skipped {os.path.basename(path)} ({e})")
                continue
            bad = _width_problems(font, builtin)
            if bad:
                notes.append(f"{name}: skipped {os.path.basename(path)} (width differs for {''.join(bad)!r})")
                continue
            chosen = (font, path)
            break
        if chosen is None:
            raise FileNotFoundError(
                f"embedded_fonts: no usable font for {name}. Looked in {WINDOWS_DIR} for "
                f"{win_file} and in {BUNDLED_DIR} for {bundled_file}. Copy the assets/fonts "
                "folder next to the scripts, or remove 'import embedded_fonts'.\n" + "\n".join(notes))
        font, path = chosen
        # keep the built-in ascent/descent so getAscentDescent() is unchanged
        font.face.ascent, font.face.descent = builtin.face.ascent, builtin.face.descent
        # registerFont() won't replace a name that's already loaded, so drop
        # the built-in entry first
        pdfmetrics._fonts.pop(name, None)
        pdfmetrics.registerFont(font)
        USED[name] = path
    if verbose or notes:
        for n in notes:
            print("embedded_fonts:", n)
    if verbose:
        for name, path in USED.items():
            print(f"embedded_fonts: {name:22} -> {path}")


register()


if __name__ == "__main__":
    # python embedded_fonts.py  -> shows which font file each name uses
    register(verbose=True)
    for name, path in USED.items():
        print(f"{name:22} -> {path}")
