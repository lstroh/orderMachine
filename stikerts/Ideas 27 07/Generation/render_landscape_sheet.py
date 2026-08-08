"""
CLI for landscape bin-sticker sheets.

Modes:
  --style KEY   4-up A4 sheet with four copies of one landscape style
  --all         one of each landscape style with generated UK sample
                house numbers / street names; shared --accent (default: black)
  --list        print available landscape style keys + accent keys

Flags:
  --all-accents  with --style: one sticker per accent (both ACCENTS and
                 CLEAR_VINYL_ACCENTS), paginated 4-up with accent captions

Landscape styles are discovered from bin_sticker.STYLE_CARD_SIZE (w > h),
so newly registered landscape designs appear automatically.

Examples:
  python render_landscape_sheet.py --list
  python render_landscape_sheet.py --style p27_landscape_house
  python render_landscape_sheet.py --style house_banner --house-number 36 --street-name "Grove Street" --accent navy
  python render_landscape_sheet.py --style house_banner --all-accents
  python render_landscape_sheet.py --all --accent navy --out landscape_compare.pdf
"""

from __future__ import annotations

import argparse
import os
import sys
from datetime import datetime
from typing import Any

from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.colors import HexColor
from reportlab.pdfgen import canvas

_SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
os.chdir(_SCRIPT_DIR)

import bin_sticker as bs  # noqa: E402

# Zoopla 2024 top-10 most common UK street names, plus extremes for layout tests.
COMMON_UK_STREETS = (
    "High Street",
    "Station Road",
    "Church Street",
    "Church Lane",
    "Church Road",
    "Mill Lane",
    "The Green",
    "Main Street",
    "Green Lane",
    "School Lane",
)
# Bilingual Caernarfon street often cited among Britain's longest street names.
LONG_UK_STREET = "Twll yn y Wal / Hole in the Wall Street"
# Short real street-name form reported in UK address data.
SHORT_UK_STREET = "Ash"
# Highest continuously numbered house commonly cited in UK address research.
LONG_UK_HOUSE_NUMBER = "2679"
TYPICAL_UK_HOUSE_NUMBERS = ("1", "4", "7", "12", "28", "36", "128")


def landscape_styles() -> list[str]:
    """Style keys whose card is landscape (width > height)."""
    return [
        key
        for key, (w, h) in bs.STYLE_CARD_SIZE.items()
        if w > h and key in bs.STYLES
    ]


def accent_keys() -> list[str]:
    """All accent keys: ACCENTS first, then CLEAR_VINYL_ACCENTS."""
    keys = list(bs.ACCENTS.keys())
    keys.extend(k for k in bs.CLEAR_VINYL_ACCENTS if k not in bs.ACCENTS)
    return keys


def build_order(
    house_number: str,
    street_name: str,
    accent: str,
    style: str | None = None,
) -> dict[str, Any]:
    order: dict[str, Any] = {
        "house_number": house_number,
        "street_name": street_name,
        "accent": accent,
    }
    if style is not None:
        order["style"] = style
    return order


def build_same_style_orders(
    style: str,
    house_number: str,
    street_name: str,
    accent: str,
    count: int = 4,
) -> list[dict[str, Any]]:
    order = build_order(house_number, street_name, accent, style=style)
    return [dict(order) for _ in range(count)]


def build_varied_landscape_orders(
    styles: list[str],
    accent: str = "black",
) -> list[dict[str, Any]]:
    """One order per landscape style with varied UK sample text.

    - Streets cycle the top-10 common UK names
    - Exactly one card uses the long bilingual street name
    - When there are 3+ styles, one card uses the short street name
    - Some common-street cards use a 4-digit house number (2679)
    - Every card uses the same accent (caller-provided; default black)
    """
    if not styles:
        return []

    n = len(styles)
    streets: list[str] = [COMMON_UK_STREETS[i % len(COMMON_UK_STREETS)] for i in range(n)]

    long_idx = 1 if n > 1 else 0
    streets[long_idx] = LONG_UK_STREET
    if n >= 3:
        short_idx = n - 1
        if short_idx == long_idx:
            short_idx = 0
        streets[short_idx] = SHORT_UK_STREET

    orders: list[dict[str, Any]] = []
    four_digit_assigned = False
    for i, style in enumerate(styles):
        street = streets[i]
        if street == LONG_UK_STREET:
            number = "36"
        elif street == SHORT_UK_STREET:
            number = "1"
        elif not four_digit_assigned or i % 2 == 0:
            number = LONG_UK_HOUSE_NUMBER
            four_digit_assigned = True
        else:
            number = TYPICAL_UK_HOUSE_NUMBERS[i % len(TYPICAL_UK_HOUSE_NUMBERS)]

        orders.append(build_order(number, street, accent, style=style))

    # Guarantee at least one 4-digit number on a common street when possible
    if not any(
        o["house_number"] == LONG_UK_HOUSE_NUMBER
        and o["street_name"] in COMMON_UK_STREETS
        for o in orders
    ):
        for o in orders:
            if o["street_name"] in COMMON_UK_STREETS:
                o["house_number"] = LONG_UK_HOUSE_NUMBER
                break

    return orders


def render_orders_paginated(orders: list[dict[str, Any]], out_path: str) -> None:
    """Paginate full orders 4-up (same card size required), captioned."""
    if not orders:
        raise ValueError("no orders to render")

    sizes = {
        bs.STYLE_CARD_SIZE.get(o.get("style", "minimal"), (bs.CARD_W, bs.CARD_H))
        for o in orders
    }
    if len(sizes) > 1:
        raise ValueError(
            f"render_orders_paginated got mixed card sizes ({sizes}); "
            "split by shape first."
        )

    card_w, card_h = sizes.pop()
    page_size = landscape(A4) if card_w > card_h else A4
    page_w, page_h = page_size
    _, _, positions = bs._sheet_layout(card_w, card_h, page_w, page_h)

    c = canvas.Canvas(out_path, pagesize=page_size)
    for i, order in enumerate(orders):
        slot = i % 4
        if i > 0 and slot == 0:
            c.showPage()
        x, y = positions[slot]
        bs.draw_sticker(c, x, y, order)
        style = order.get("style", "")
        accent = order.get("accent", "")
        label = bs.STYLE_LABELS.get(style, style)
        caption = f"{label} | {accent}" if accent else label
        c.setFont("Helvetica", 5)
        c.setFillColor(HexColor("#888888"))
        c.drawCentredString(x + card_w / 2, 2.5 * bs.mm, caption[:70])
    c.showPage()
    c.save()


def render_style_all_accents(
    style: str,
    house_number: str,
    street_name: str,
    out_path: str,
    accents: list[str] | None = None,
) -> None:
    """One card per accent for a single landscape style, paginated 4-up.

    Captions each slot with the accent key (like render_gallery captions).
    """
    accents = accents if accents is not None else accent_keys()
    if not accents:
        raise ValueError("no accents to render")

    orders = [
        build_order(house_number, street_name, accent, style=style)
        for accent in accents
    ]
    render_orders_paginated(orders, out_path)


def _write_with_lock_fallback(render_fn, out_path: str) -> str:
    """Call render_fn(out_path); on PermissionError write a timestamped file."""
    try:
        render_fn(out_path)
        return out_path
    except PermissionError:
        base, ext = os.path.splitext(out_path)
        alt = f"{base}_{datetime.now():%Y%m%d_%H%M%S}{ext or '.pdf'}"
        render_fn(alt)
        print(f"{out_path} is locked (close it to overwrite). Wrote {alt}")
        return alt


def _parse_args(argv: list[str] | None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Render landscape bin-sticker sheets (4x one style, or one of each).",
    )
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument(
        "--style",
        metavar="KEY",
        help="Landscape style key; write a 4-up sheet of four identical stickers",
    )
    mode.add_argument(
        "--all",
        action="store_true",
        help=(
            "One sticker per landscape style with generated UK sample "
            "numbers/streets; shared --accent (default: black)"
        ),
    )
    mode.add_argument(
        "--list",
        action="store_true",
        help="List available landscape styles and accent keys, then exit",
    )
    parser.add_argument(
        "--house-number",
        "--number",
        dest="house_number",
        default="4",
        metavar="TEXT",
        help="House number for --style / --all-accents (ignored by --all generation)",
    )
    parser.add_argument(
        "--street-name",
        "--street",
        dest="street_name",
        default="Parkleigh Road",
        metavar="TEXT",
        help="Street name for --style / --all-accents (ignored by --all generation)",
    )
    accent_group = parser.add_mutually_exclusive_group()
    accent_group.add_argument(
        "--accent",
        default=None,
        metavar="KEY",
        help=(
            "Accent colour key (ACCENTS or CLEAR_VINYL_ACCENTS). "
            "Used by --style (default: navy) and --all (default: black)"
        ),
    )
    accent_group.add_argument(
        "--all-accents",
        action="store_true",
        help="With --style: one sticker per accent (ACCENTS + CLEAR_VINYL_ACCENTS)",
    )
    parser.add_argument(
        "--out",
        default=None,
        help="Output PDF path (defaults depend on mode)",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = _parse_args(argv)
    styles = landscape_styles()
    accents = accent_keys()

    if args.list:
        print("Landscape styles:")
        if not styles:
            print("  (none)")
        else:
            width = max(len(k) for k in styles)
            for key in styles:
                label = bs.STYLE_LABELS.get(key, key)
                print(f"  {key:<{width}}  {label}")
        print()
        print("Accents (ACCENTS):")
        for key in bs.ACCENTS:
            print(f"  {key:<16}  {bs.ACCENTS[key]}")
        print("Accents (CLEAR_VINYL_ACCENTS):")
        for key in bs.CLEAR_VINYL_ACCENTS:
            print(f"  {key:<16}  {bs.CLEAR_VINYL_ACCENTS[key]}")
        return 0

    if args.all_accents and not args.style:
        print(
            "--all-accents requires --style KEY (one style, every accent).",
            file=sys.stderr,
        )
        return 1

    if args.style:
        if args.style not in styles:
            print(
                f"Unknown or non-landscape style: {args.style!r}\n"
                f"Available landscape styles: {', '.join(styles) or '(none)'}\n"
                "Use --list to see labels.",
                file=sys.stderr,
            )
            return 1

        if args.all_accents:
            out = args.out or f"sheet_{args.style}_accents.pdf"

            def _render(path: str) -> None:
                render_style_all_accents(
                    args.style,
                    args.house_number,
                    args.street_name,
                    path,
                    accents=accents,
                )

            written = _write_with_lock_fallback(_render, out)
            if written == out:
                print(f"Wrote {written} ({len(accents)} accents)")
            return 0

        out = args.out or "sheet.pdf"
        style_accent = args.accent if args.accent is not None else "navy"
        orders = build_same_style_orders(
            args.style, args.house_number, args.street_name, style_accent
        )

        def _render_same(path: str) -> None:
            bs.render_sheet(orders, path)

        written = _write_with_lock_fallback(_render_same, out)
        if written == out:
            print(f"Wrote {written}")
        return 0

    # --all: generated UK sample text; one shared accent (default black)
    out = args.out or "landscape_all.pdf"
    all_accent = args.accent if args.accent is not None else "black"
    orders = build_varied_landscape_orders(styles, accent=all_accent)

    def _render_all(path: str) -> None:
        render_orders_paginated(orders, path)

    written = _write_with_lock_fallback(_render_all, out)
    if written == out:
        print(f"Wrote {written} ({len(orders)} styles, accent={all_accent})")
        for o in orders:
            print(
                f"  {o['style']}: {o['house_number']} {o['street_name']} [{o['accent']}]"
            )
    return 0


if __name__ == "__main__":
    sys.exit(main())
