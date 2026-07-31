#!/usr/bin/env python3
"""
CLI wrapper for thankyou_card.render_sheet.

Usage:
  python thankyou_card_cli.py --json orders.json --out cards.pdf

JSON file is either a list of order dicts, or {"orders": [...]}.
Exit 0 on success; non-zero on error (message on stderr).
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

from thankyou_card import render_sheet


def load_orders(path: Path) -> list:
    raw = json.loads(path.read_text(encoding="utf-8"))
    if isinstance(raw, list):
        return raw
    if isinstance(raw, dict) and isinstance(raw.get("orders"), list):
        return raw["orders"]
    raise ValueError("JSON must be a list of order dicts or an object with an 'orders' list")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Generate thank-you card PDF sheet")
    parser.add_argument("--json", required=True, help="Path to JSON file with order dict(s)")
    parser.add_argument("--out", required=True, help="Output PDF path")
    args = parser.parse_args(argv)

    json_path = Path(args.json)
    out_path = Path(args.out)

    if not json_path.is_file():
        print(f"JSON file not found: {json_path}", file=sys.stderr)
        return 2

    try:
        orders = load_orders(json_path)
    except (OSError, json.JSONDecodeError, ValueError) as exc:
        print(f"Failed to read orders JSON: {exc}", file=sys.stderr)
        return 2

    if not orders:
        print("Orders list is empty", file=sys.stderr)
        return 2

    if len(orders) > 4:
        print("At most 4 orders per sheet; got %d" % len(orders), file=sys.stderr)
        return 2

    try:
        out_path.parent.mkdir(parents=True, exist_ok=True)
        render_sheet(orders, str(out_path))
    except Exception as exc:  # noqa: BLE001 — surface any render failure to PHP
        print(f"render_sheet failed: {exc}", file=sys.stderr)
        return 1

    print(str(out_path.resolve()))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
