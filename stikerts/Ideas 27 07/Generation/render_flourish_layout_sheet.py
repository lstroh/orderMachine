"""
CLI for flourish/frame landscape layout tests (D02 + D03).

Renders p25_landscape_flourish and p25b_landscape_flourish with a
fixed set of short/long house-number and street-name samples so auto-fit
shrink is easy to judge on the page.

Examples:
  python render_flourish_layout_sheet.py
  python render_flourish_layout_sheet.py --style p25_landscape_flourish
  python render_flourish_layout_sheet.py --list
  python render_flourish_layout_sheet.py --out flourish_layout_test.pdf
"""

from __future__ import annotations

import argparse
import os
import sys
from typing import Any

from reportlab.lib.pagesizes import landscape as landscape_page
from reportlab.lib.pagesizes import A4
from reportlab.lib.colors import HexColor
from reportlab.pdfgen import canvas

_SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
os.chdir(_SCRIPT_DIR)

import bin_sticker as bs  # noqa: E402
import render_landscape_sheet as rls  # noqa: E402

FLOURISH_STYLES = (
    "p25_landscape_flourish",
    "p25b_landscape_flourish",
)

# Layout stress set: short/short, long number (incl. 12A + 5-digit shrink),
# long street, both long at once.
LAYOUT_CASES: tuple[dict[str, str], ...] = (
    {
        "id": "short_short",
        "house_number": "7",
        "street_name": "Rye",
    },
    {
        "id": "long_number_2679",
        "house_number": "2679",
        "street_name": "High Street",
    },
    {
        "id": "long_number_12A",
        "house_number": "12A",
        "street_name": "High Street",
    },
    {
        "id": "five_digit",
        "house_number": "12345",
        "street_name": "High Street",
    },
    {
        "id": "long_street",
        "house_number": "36",
        "street_name": "Amersham-on-the-Hill Road",
    },
    {
        "id": "both_long",
        "house_number": "12345",
        "street_name": "Amersham-on-the-Hill Road",
    },
)


def build_flourish_layout_orders(
    styles: list[str] | None = None,
) -> list[dict[str, Any]]:
    """One order per (style, layout case). Accent is unused on these styles."""
    styles = list(styles) if styles is not None else list(FLOURISH_STYLES)
    orders: list[dict[str, Any]] = []
    for style in styles:
        for case in LAYOUT_CASES:
            order = rls.build_order(
                case["house_number"],
                case["street_name"],
                "black",
                style=style,
            )
            order["case_id"] = case["id"]
            orders.append(order)
    return orders


def render_flourish_layout(orders: list[dict[str, Any]], out_path: str) -> None:
    """Paginate 4-up; caption with case + sample text (not just style|accent)."""
    if not orders:
        raise ValueError("no orders to render")

    card_w, card_h = bs.STYLE_CARD_SIZE[orders[0]["style"]]
    page_w, page_h = landscape_page(A4)
    _, _, positions = bs._sheet_layout(card_w, card_h, page_w, page_h)

    c = canvas.Canvas(out_path, pagesize=landscape_page(A4))
    for i, order in enumerate(orders):
        slot = i % 4
        if i > 0 and slot == 0:
            c.showPage()
        x, y = positions[slot]
        bs.draw_sticker(c, x, y, order)
        style = order.get("style", "")
        label = bs.STYLE_LABELS.get(style, style)
        case_id = order.get("case_id", "")
        sample = f"{order['house_number']} / {order['street_name']}"
        caption = f"{label} | {case_id} | {sample}"
        c.setFont("Helvetica", 5)
        c.setFillColor(HexColor("#888888"))
        c.drawCentredString(x + card_w / 2, 2.5 * bs.mm, caption[:90])
    c.showPage()
    c.save()


def _parse_args(argv: list[str] | None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Render p25/p25b flourish landscape stickers with short/long "
            "number and street layout samples."
        ),
    )
    parser.add_argument(
        "--style",
        metavar="KEY",
        choices=FLOURISH_STYLES,
        help="One flourish style (default: both p25 and p25b)",
    )
    parser.add_argument(
        "--list",
        action="store_true",
        help="List flourish styles and layout cases, then exit",
    )
    parser.add_argument(
        "--out",
        default="flourish_layout_test.pdf",
        help="Output PDF path (default: flourish_layout_test.pdf)",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = _parse_args(argv)

    if args.list:
        print("Flourish styles:")
        width = max(len(k) for k in FLOURISH_STYLES)
        for key in FLOURISH_STYLES:
            print(f"  {key:<{width}}  {bs.STYLE_LABELS.get(key, key)}")
        print()
        print("Layout cases:")
        for case in LAYOUT_CASES:
            print(
                f"  {case['id']:<20}  {case['house_number']!r} / {case['street_name']!r}"
            )
        return 0

    styles = [args.style] if args.style else list(FLOURISH_STYLES)
    for style in styles:
        if style not in bs.STYLES:
            print(f"Unknown style: {style!r}", file=sys.stderr)
            return 1

    orders = build_flourish_layout_orders(styles)

    def _render(path: str) -> None:
        render_flourish_layout(orders, path)

    written = rls._write_with_lock_fallback(_render, args.out)
    if written == args.out:
        print(f"Wrote {written} ({len(orders)} stickers, {len(styles)} style(s))")
    for o in orders:
        print(
            f"  {o['style']}: {o['case_id']}: "
            f"{o['house_number']} {o['street_name']}"
        )
    return 0


if __name__ == "__main__":
    sys.exit(main())
