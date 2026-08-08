"""
CLI for landscape bin-sticker sheets.

Modes:
  --style KEY   4-up A4 sheet with four copies of one landscape style
  --all         one of each landscape style (paginated 4-up via render_gallery)
  --list        print available landscape style keys + labels

Landscape styles are discovered from bin_sticker.STYLE_CARD_SIZE (w > h),
so newly registered landscape designs appear automatically.

Examples:
  python render_landscape_sheet.py --list
  python render_landscape_sheet.py --style p27_landscape_house
  python render_landscape_sheet.py --all --out landscape_compare.pdf
"""

from __future__ import annotations

import argparse
import os
import sys
from datetime import datetime
from typing import Any

_SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
os.chdir(_SCRIPT_DIR)

import bin_sticker as bs  # noqa: E402


def landscape_styles() -> list[str]:
    """Style keys whose card is landscape (width > height)."""
    return [
        key
        for key, (w, h) in bs.STYLE_CARD_SIZE.items()
        if w > h and key in bs.STYLES
    ]


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
        help="One sticker per landscape style (paginated 4-up gallery)",
    )
    mode.add_argument(
        "--list",
        action="store_true",
        help="List available landscape style keys and exit",
    )
    parser.add_argument("--number", default="4", help="House number (default: 4)")
    parser.add_argument(
        "--street",
        default="Parkleigh Road",
        help='Street name (default: "Parkleigh Road")',
    )
    parser.add_argument("--accent", default="navy", help="Accent key (default: navy)")
    parser.add_argument(
        "--out",
        default=None,
        help="Output PDF path (default: sheet.pdf for --style, landscape_all.pdf for --all)",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = _parse_args(argv)
    styles = landscape_styles()

    if args.list:
        if not styles:
            print("No landscape styles registered.")
            return 0
        width = max(len(k) for k in styles)
        for key in styles:
            label = bs.STYLE_LABELS.get(key, key)
            print(f"  {key:<{width}}  {label}")
        return 0

    if args.style:
        if args.style not in styles:
            print(
                f"Unknown or non-landscape style: {args.style!r}\n"
                f"Available landscape styles: {', '.join(styles) or '(none)'}\n"
                "Use --list to see labels.",
                file=sys.stderr,
            )
            return 1
        out = args.out or "sheet.pdf"
        orders = build_same_style_orders(
            args.style, args.number, args.street, args.accent
        )

        def _render(path: str) -> None:
            bs.render_sheet(orders, path)

        written = _write_with_lock_fallback(_render, out)
        if written == out:
            print(f"Wrote {written}")
        return 0

    # --all
    out = args.out or "landscape_all.pdf"
    sample = build_order(args.number, args.street, args.accent)

    def _render_all(path: str) -> None:
        bs.render_gallery(styles, sample, path)

    written = _write_with_lock_fallback(_render_all, out)
    if written == out:
        print(f"Wrote {written}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
