"""
CLI for landscape layout-test sheets (short/long number + street samples).

Packs:
  flourish  D02/D03 flourish frames (default)
  d01_d18   D01 Cottage Bloom Banner + D18 Floral Vine Wreath
  d04_d25   D04–D05, D19–D25 remaining landscape styles
  animal    D06/D08/D11/D13/D14/D16 animal-family short/long pairs

Examples:
  python render_flourish_layout_sheet.py
  python render_flourish_layout_sheet.py --pack d01_d18
  python render_flourish_layout_sheet.py --pack d04_d25
  python render_flourish_layout_sheet.py --pack animal
  python render_flourish_layout_sheet.py --pack d01_d18 --style house_banner
  python render_flourish_layout_sheet.py --list
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

def _case(case_id: str, house_number: str, street_name: str) -> dict[str, str]:
    return {
        "id": case_id,
        "house_number": house_number,
        "street_name": street_name,
    }


# Shared four-anchor set used by D01 and D04+ street-capable styles.
ANCHOR_CASES: tuple[dict[str, str], ...] = (
    _case("1", "7", "Rye"),
    _case("2", "36", "Mill Lane"),
    _case("3", "2679", "High Street"),
    _case("4", "12A", "Amersham-on-the-Hill Road"),
)
NUMBER_ONLY_CASES: tuple[dict[str, str], ...] = (
    _case("short", "7", ""),
    _case("long", "2679", ""),
)
# Short/long pair for animal-family styles (extremes only).
ANIMAL_CASES: tuple[dict[str, str], ...] = (
    _case("short", "7", "Rye"),
    _case("long", "2679", "Amersham-on-the-Hill Road"),
)

# Flourish stress set: short/short, long number (incl. 12A + 5-digit shrink),
# long street, both long at once.
FLOURISH_CASES: tuple[dict[str, str], ...] = (
    _case("short_short", "7", "Rye"),
    _case("long_number_2679", "2679", "High Street"),
    _case("long_number_12A", "12A", "High Street"),
    _case("five_digit", "12345", "High Street"),
    _case("long_street", "36", "Amersham-on-the-Hill Road"),
    _case("both_long", "12345", "Amersham-on-the-Hill Road"),
)

PACKS: dict[str, dict[str, Any]] = {
    "flourish": {
        "out": "flourish_layout_test.pdf",
        "styles": {
            "p25_landscape_flourish": FLOURISH_CASES,
            "p25b_landscape_flourish": FLOURISH_CASES,
        },
    },
    "d01_d18": {
        "out": "d01_d18_layout_test.pdf",
        "styles": {
            "house_banner": ANCHOR_CASES,
            "p06_wreath": (
                _case("5", "7", "Rye"),
                _case("6", "36", "High Street"),
                _case("7", "2679", "Amersham-on-the-Hill Road"),
                _case("8", "12A", "Amersham-on-the-Hill Road"),
            ),
        },
    },
    "d04_d25": {
        "out": "d04_d25_layout_test.pdf",
        "styles": {
            "p27_landscape_house": ANCHOR_CASES,
            "p47_house": NUMBER_ONLY_CASES,
            "p06_wreath_numbers": NUMBER_ONLY_CASES,
            "p30_laurel_numbers": NUMBER_ONLY_CASES,
            "p15_heart_wreath": ANCHOR_CASES,
            "p28_arrow_wreath": ANCHOR_CASES,
            "p31_olive_wreath": ANCHOR_CASES,
            "p09a_borderless": ANCHOR_CASES,
            "p21_paw_trail": ANCHOR_CASES,
        },
    },
    # D11/D14 keys are dog_family_2 / cat_family_1 (no mother/father naming).
    "animal": {
        "out": "animal_layout_test.pdf",
        "styles": {
            "duck_family_father": ANIMAL_CASES,      # D06
            "duck_family_playing1": ANIMAL_CASES,    # D08
            "dog_family_2": ANIMAL_CASES,            # D11
            "dog_family_playing2": ANIMAL_CASES,     # D13
            "cat_family_1": ANIMAL_CASES,            # D14
            "cat_family_playing1": ANIMAL_CASES,     # D16
        },
    },
}

FLOURISH_STYLES = tuple(PACKS["flourish"]["styles"])
LAYOUT_CASES = FLOURISH_CASES


def build_pack_orders(
    pack_name: str,
    styles: list[str] | None = None,
) -> list[dict[str, Any]]:
    """One order per (style, layout case) in the named pack."""
    pack = PACKS[pack_name]
    style_cases: dict[str, tuple[dict[str, str], ...]] = pack["styles"]
    chosen = list(styles) if styles is not None else list(style_cases)
    orders: list[dict[str, Any]] = []
    n = 1
    for style in chosen:
        for case in style_cases[style]:
            order = rls.build_order(
                case["house_number"],
                case["street_name"],
                "black",
                style=style,
            )
            order["case_id"] = str(n)
            n += 1
            orders.append(order)
    return orders


def build_flourish_layout_orders(
    styles: list[str] | None = None,
) -> list[dict[str, Any]]:
    """One order per (style, layout case). Accent is unused on flourish styles."""
    return build_pack_orders("flourish", styles)


def render_flourish_layout(orders: list[dict[str, Any]], out_path: str) -> None:
    """Paginate 4-up; caption with case + sample text (not just style|accent)."""
    if not orders:
        raise ValueError("no orders to render")

    card_w, card_h = bs.STYLE_CARD_SIZE[orders[0]["style"]]
    page_w, page_h = landscape_page(A4)
    _, _, positions = bs._sheet_layout(card_w, card_h, page_w, page_h)

    c = canvas.Canvas(out_path, pagesize=landscape_page(A4))
    slot = 0
    prev_style: str | None = None
    for order in orders:
        style = order.get("style", "")
        if prev_style is not None and (slot == 4 or style != prev_style):
            c.showPage()
            slot = 0
        x, y = positions[slot]
        bs.draw_sticker(c, x, y, order)
        label = bs.STYLE_LABELS.get(style, style)
        case_id = order.get("case_id", "")
        street = order.get("street_name") or "(no street)"
        sample = f"{order['house_number']} / {street}"
        caption = f"{label} | #{case_id} | {sample}"
        prev_style = style
        slot += 1
        c.setFont("Helvetica", 5)
        c.setFillColor(HexColor("#888888"))
        c.drawCentredString(x + card_w / 2, 2.5 * bs.mm, caption[:90])
    c.showPage()
    c.save()


def _parse_args(argv: list[str] | None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Render landscape layout-test sheets with short/long "
            "number and street samples."
        ),
    )
    parser.add_argument(
        "--pack",
        choices=tuple(PACKS),
        default="flourish",
        help="Sample pack (default: flourish)",
    )
    parser.add_argument(
        "--style",
        metavar="KEY",
        help="One style from the pack (default: all styles in the pack)",
    )
    parser.add_argument(
        "--list",
        action="store_true",
        help="List packs, styles, and layout cases, then exit",
    )
    parser.add_argument(
        "--out",
        default=None,
        help="Output PDF path (defaults depend on --pack)",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = _parse_args(argv)

    if args.list:
        for pack_name, pack in PACKS.items():
            print(f"Pack {pack_name!r} (default out: {pack['out']}):")
            for style, cases in pack["styles"].items():
                label = bs.STYLE_LABELS.get(style, style)
                print(f"  {style}  {label}")
                for case in cases:
                    print(
                        f"    {case['id']:<20}  "
                        f"{case['house_number']!r} / {case['street_name']!r}"
                    )
            print()
        return 0

    pack = PACKS[args.pack]
    pack_styles = list(pack["styles"])
    if args.style:
        if args.style not in pack["styles"]:
            print(
                f"Unknown style {args.style!r} for pack {args.pack!r}. "
                f"Available: {', '.join(pack_styles)}",
                file=sys.stderr,
            )
            return 1
        styles = [args.style]
    else:
        styles = pack_styles

    for style in styles:
        if style not in bs.STYLES:
            print(f"Unknown style: {style!r}", file=sys.stderr)
            return 1

    orders = build_pack_orders(args.pack, styles)
    out = args.out or pack["out"]

    def _render(path: str) -> None:
        render_flourish_layout(orders, path)

    written = rls._write_with_lock_fallback(_render, out)
    if written == out:
        print(f"Wrote {written} ({len(orders)} stickers, {len(styles)} style(s))")
    for o in orders:
        print(
            f"  {o['style']}: {o['case_id']}: "
            f"{o['house_number']} {o['street_name']}"
        )
    return 0


if __name__ == "__main__":
    sys.exit(main())
