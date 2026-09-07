"""
Gallery of landscape designs captioned with the style KEY used in scripts.

Each sticker has a clear label under it, e.g.:
  #10  p28_arrow_wreath  (D22)

Copy the middle token into:
  python render_landscape_sheet.py --style p28_arrow_wreath ...

Portrait styles (classic…paw) are excluded.

Examples:
  python render_design_key_gallery.py
  python render_design_key_gallery.py --number 4 --street "Parkleigh Road"
  python render_design_key_gallery.py --list
"""

from __future__ import annotations

import argparse
import os
import sys
from datetime import datetime

from reportlab.lib.pagesizes import A4, landscape as landscape_page
from reportlab.lib.colors import HexColor, white
from reportlab.pdfgen import canvas

_SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
os.chdir(_SCRIPT_DIR)

import bin_sticker as bs  # noqa: E402

# Space under each card for the style-key caption (full-size cards leave
# almost no margin on landscape A4, so captions were invisible before).
CAPTION_BAND = 10 * bs.mm
PAGE_PAD = 5 * bs.mm


def landscape_styles() -> list[str]:
    return [
        key
        for key, (w, h) in bs.STYLE_CARD_SIZE.items()
        if w > h and key in bs.STYLES
    ]


def _captioned_positions(card_w: float, card_h: float, page_w: float, page_h: float):
    """2x2 layout with a caption band under every card.

    Cards are scaled down just enough to fit two rows + two caption bands
    on landscape A4. Returns (scale, [(x, y), ...]) where (x, y) is the
    bottom-left of each *drawn* (scaled) card.
    """
    avail_w = page_w - 2 * PAGE_PAD
    avail_h = page_h - 2 * PAGE_PAD - 2 * CAPTION_BAND
    scale = min(avail_w / (2 * card_w), avail_h / (2 * card_h))
    draw_w = card_w * scale
    draw_h = card_h * scale

    gap_x = (avail_w - 2 * draw_w) / 3
    # Vertical: pad | card | caption | card | caption | pad
    # Bottom card sits on PAGE_PAD + CAPTION_BAND; top card above that + draw_h + CAPTION_BAND
    x0 = PAGE_PAD + gap_x
    x1 = x0 + draw_w + gap_x
    y_bottom = PAGE_PAD + CAPTION_BAND
    y_top = y_bottom + draw_h + CAPTION_BAND

    # Slot order matches _sheet_layout: TL, TR, BL, BR
    return scale, [
        (x0, y_top),
        (x1, y_top),
        (x0, y_bottom),
        (x1, y_bottom),
    ]


def render_key_gallery(
    styles: list[str],
    house_number: str,
    street_name: str,
    accent: str,
    out_path: str,
) -> None:
    if not styles:
        raise ValueError("no styles to render")

    card_w, card_h = bs.STYLE_CARD_SIZE[styles[0]]
    page_size = landscape_page(A4)
    page_w, page_h = page_size
    scale, positions = _captioned_positions(card_w, card_h, page_w, page_h)
    draw_w = card_w * scale

    c = canvas.Canvas(out_path, pagesize=page_size)
    for i, style in enumerate(styles):
        slot = i % 4
        if i > 0 and slot == 0:
            c.showPage()
        x, y = positions[slot]
        order = {
            "house_number": house_number,
            "street_name": street_name,
            "style": style,
            "accent": accent,
        }

        c.saveState()
        c.translate(x, y)
        c.scale(scale, scale)
        bs.draw_sticker(c, 0, 0, order)
        c.restoreState()

        product = bs.STYLE_PRODUCT_ID.get(style, "")
        # Style KEY is what --style expects.
        key_line = f"#{i + 1}  {style}"
        if product:
            key_line += f"  ({product})"

        # White band under the card so the key is always readable.
        band_y = y - CAPTION_BAND
        c.setFillColor(white)
        c.rect(x, band_y, draw_w, CAPTION_BAND - 0.5 * bs.mm, fill=1, stroke=0)

        c.setFillColor(HexColor("#111111"))
        c.setFont("Helvetica-Bold", 8)
        c.drawCentredString(x + draw_w / 2, band_y + 5.2 * bs.mm, key_line[:70])

        usage = f'--style {style}'
        c.setFont("Courier", 6.5)
        c.setFillColor(HexColor("#333333"))
        c.drawCentredString(x + draw_w / 2, band_y + 1.8 * bs.mm, usage[:75])

    c.showPage()
    c.save()


def _write_with_lock_fallback(render_fn, out_path: str) -> str:
    try:
        render_fn(out_path)
        return out_path
    except PermissionError:
        base, ext = os.path.splitext(out_path)
        alt = f"{base}_{datetime.now():%Y%m%d_%H%M%S}{ext or '.pdf'}"
        render_fn(alt)
        print(f"{out_path} is locked (close it to overwrite). Wrote {alt}")
        return alt


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Landscape design gallery captioned with style keys for CLI use.",
    )
    parser.add_argument(
        "--list",
        action="store_true",
        help="Print style keys (and product IDs) then exit",
    )
    parser.add_argument("--number", "--house-number", dest="house_number", default="40")
    parser.add_argument("--street", "--street-name", dest="street_name", default="Oak Avenue")
    parser.add_argument("--accent", default="black")
    parser.add_argument(
        "--out",
        default="design_keys_gallery.pdf",
        help="Output PDF (default: design_keys_gallery.pdf)",
    )
    args = parser.parse_args(argv)

    styles = landscape_styles()
    if args.list:
        width = max(len(k) for k in styles) if styles else 0
        for i, key in enumerate(styles, 1):
            product = bs.STYLE_PRODUCT_ID.get(key, "")
            print(f"{i:2d}. {key:<{width}}  {product}")
        return 0

    def _render(path: str) -> None:
        render_key_gallery(
            styles, args.house_number, args.street_name, args.accent, path
        )

    written = _write_with_lock_fallback(_render, args.out)
    if written == args.out:
        print(
            f"Wrote {written} ({len(styles)} landscape designs, "
            f"{args.house_number} / {args.street_name} / {args.accent})"
        )
    for i, key in enumerate(styles, 1):
        product = bs.STYLE_PRODUCT_ID.get(key, "")
        print(f"  #{i:2d}  --style {key}" + (f"  ({product})" if product else ""))
    return 0


if __name__ == "__main__":
    sys.exit(main())
