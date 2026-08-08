"""
Tests for render_landscape_sheet.py.

Usage (from this folder):
    python test_render_landscape_sheet.py
    python test_render_landscape_sheet.py --no-pdf
"""

from __future__ import annotations

import argparse
import io
import os
import sys
import traceback
from contextlib import redirect_stderr, redirect_stdout
from typing import Callable

_SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
os.chdir(_SCRIPT_DIR)

import render_landscape_sheet as rls  # noqa: E402


KNOWN_LANDSCAPE = (
    "house_banner",
    "p25_landscape_flourish",
    "p25b_landscape_flourish",
    "p27_landscape_house",
)


class SuiteResult:
    def __init__(self) -> None:
        self.passed: list[str] = []
        self.failed: list[tuple[str, str]] = []

    def ok(self, name: str) -> None:
        self.passed.append(name)
        print(f"  PASS  {name}")

    def fail(self, name: str, detail: str) -> None:
        self.failed.append((name, detail))
        print(f"  FAIL  {name}: {detail}")

    def check(self, name: str, condition: bool, detail: str = "") -> None:
        if condition:
            self.ok(name)
        else:
            self.fail(name, detail or "assertion failed")

    def run(self, name: str, fn: Callable[[], None]) -> None:
        try:
            fn()
            self.ok(name)
        except Exception as exc:
            self.fail(name, f"{type(exc).__name__}: {exc}")
            traceback.print_exc()


def _assert_pdf(path: str) -> None:
    if not os.path.isfile(path):
        raise AssertionError(f"PDF not created: {path}")
    if os.path.getsize(path) <= 0:
        raise AssertionError(f"PDF is empty: {path}")


def _run_main(argv: list[str]) -> tuple[int, str, str]:
    """Call main(argv); return (exit_code, stdout, stderr). Handles argparse SystemExit."""
    out = io.StringIO()
    err = io.StringIO()
    try:
        with redirect_stdout(out), redirect_stderr(err):
            code = rls.main(argv)
    except SystemExit as exc:
        code = int(exc.code) if exc.code is not None else 0
    return code, out.getvalue(), err.getvalue()


def test_discovery(r: SuiteResult) -> None:
    styles = rls.landscape_styles()
    r.check(
        "discovery: returns a non-empty list",
        len(styles) > 0,
        f"got {styles!r}",
    )
    r.check(
        "discovery: includes known landscape keys",
        all(k in styles for k in KNOWN_LANDSCAPE),
        f"missing from {styles}",
    )
    r.check(
        "discovery: excludes portrait minimal",
        "minimal" not in styles,
        f"got {styles}",
    )

    import bin_sticker as bs

    all_landscape = all(
        bs.STYLE_CARD_SIZE[k][0] > bs.STYLE_CARD_SIZE[k][1] for k in styles
    )
    r.check("discovery: every key has w > h", all_landscape)

    accents = rls.accent_keys()
    r.check(
        "discovery: accent_keys includes ACCENTS",
        all(k in accents for k in bs.ACCENTS),
        f"got {accents}",
    )
    r.check(
        "discovery: accent_keys includes CLEAR_VINYL",
        all(k in accents for k in bs.CLEAR_VINYL_ACCENTS),
        f"got {accents}",
    )
    r.check(
        "discovery: accent_keys ACCENTS before clear-vinyl",
        accents.index(next(iter(bs.ACCENTS)))
        < accents.index(next(iter(bs.CLEAR_VINYL_ACCENTS))),
        f"got {accents}",
    )


def test_order_wiring(r: SuiteResult) -> None:
    order = rls.build_order("36", "Grove Street", "terracotta", style="house_banner")
    r.check(
        "build_order: fields wired",
        order
        == {
            "house_number": "36",
            "street_name": "Grove Street",
            "accent": "terracotta",
            "style": "house_banner",
        },
        f"got {order!r}",
    )
    sample = rls.build_order("36", "Grove Street", "navy")
    r.check(
        "build_order: style omitted when not passed",
        "style" not in sample and sample["house_number"] == "36",
        f"got {sample!r}",
    )
    orders = rls.build_same_style_orders(
        "p27_landscape_house", "4", "Parkleigh Road", "navy"
    )
    r.check("build_same_style_orders: length 4", len(orders) == 4, f"got {len(orders)}")
    r.check(
        "build_same_style_orders: all same style/number",
        all(
            o["style"] == "p27_landscape_house"
            and o["house_number"] == "4"
            and o["street_name"] == "Parkleigh Road"
            and o["accent"] == "navy"
            for o in orders
        ),
        f"got {orders!r}",
    )

    styles = rls.landscape_styles()
    varied = rls.build_varied_landscape_orders(styles)
    r.check(
        "varied orders: one per landscape style",
        len(varied) == len(styles),
        f"got {len(varied)} for {len(styles)} styles",
    )
    r.check(
        "varied orders: distinct styles",
        {o["style"] for o in varied} == set(styles),
        f"got {[o['style'] for o in varied]}",
    )
    r.check(
        "varied orders: includes long street name",
        any(o["street_name"] == rls.LONG_UK_STREET for o in varied),
        f"streets={[o['street_name'] for o in varied]}",
    )
    r.check(
        "varied orders: includes short street when 3+ styles",
        len(styles) < 3
        or any(o["street_name"] == rls.SHORT_UK_STREET for o in varied),
        f"streets={[o['street_name'] for o in varied]}",
    )
    r.check(
        "varied orders: 4-digit number on a common street",
        any(
            o["house_number"] == rls.LONG_UK_HOUSE_NUMBER
            and o["street_name"] in rls.COMMON_UK_STREETS
            for o in varied
        ),
        f"orders={[(o['house_number'], o['street_name']) for o in varied]}",
    )
    r.check(
        "varied orders: default accent is black for all",
        all(o["accent"] == "black" for o in varied) and len(varied) > 0,
        f"accents={[o['accent'] for o in varied]}",
    )
    navy_orders = rls.build_varied_landscape_orders(styles, accent="navy")
    r.check(
        "varied orders: shared caller accent applied to all",
        all(o["accent"] == "navy" for o in navy_orders) and len(navy_orders) > 0,
        f"accents={[o['accent'] for o in navy_orders]}",
    )


def test_cli_order_flags(r: SuiteResult) -> None:
    """CLI --house-number / --street-name / --accent map into build helpers."""
    args = rls._parse_args(
        [
            "--style",
            "house_banner",
            "--house-number",
            "128",
            "--street-name",
            "Kings Cross Ave",
            "--accent",
            "terracotta",
        ]
    )
    r.check(
        "CLI flags: house_number/street_name/accent parsed",
        args.house_number == "128"
        and args.street_name == "Kings Cross Ave"
        and args.accent == "terracotta",
        f"got house_number={args.house_number!r} street_name={args.street_name!r} accent={args.accent!r}",
    )
    # Short aliases still work
    args2 = rls._parse_args(
        ["--all", "--number", "7", "--street", "Rye", "--accent", "berry"]
    )
    r.check(
        "CLI aliases: --number/--street still work",
        args2.house_number == "7"
        and args2.street_name == "Rye"
        and args2.accent == "berry",
        f"got house_number={args2.house_number!r} street_name={args2.street_name!r}",
    )


def test_cli_list(r: SuiteResult) -> None:
    code, out, err = _run_main(["--list"])
    r.check("CLI --list: exit 0", code == 0, f"code={code} err={err!r}")
    for key in rls.landscape_styles():
        r.check(f"CLI --list: stdout contains {key}", key in out, f"stdout={out!r}")
    for key in rls.accent_keys():
        r.check(f"CLI --list: stdout contains accent {key}", key in out, f"stdout={out!r}")


def test_cli_validation(r: SuiteResult) -> None:
    code, out, err = _run_main(["--style", "not_a_real_style"])
    r.check(
        "CLI unknown --style: non-zero exit",
        code != 0,
        f"code={code} err={err!r}",
    )

    code, out, err = _run_main([])
    r.check(
        "CLI missing mode: non-zero exit",
        code != 0,
        f"code={code} err={err!r}",
    )

    code, out, err = _run_main(
        ["--style", "house_banner", "--all"]
    )
    r.check(
        "CLI --style and --all together: non-zero exit",
        code != 0,
        f"code={code} err={err!r}",
    )

    code, out, err = _run_main(["--all", "--all-accents"])
    r.check(
        "CLI --all-accents without --style: non-zero exit",
        code != 0,
        f"code={code} err={err!r}",
    )

    code, out, err = _run_main(
        ["--style", "house_banner", "--accent", "navy", "--all-accents"]
    )
    r.check(
        "CLI --accent and --all-accents together: non-zero exit",
        code != 0,
        f"code={code} err={err!r}",
    )


def test_pdf_renders(r: SuiteResult, outdir: str) -> None:
    os.makedirs(outdir, exist_ok=True)

    style = rls.landscape_styles()[0]
    style_path = os.path.join(outdir, "cli_style_4up.pdf")

    def _style() -> None:
        code, out, err = _run_main(
            [
                "--style",
                style,
                "--house-number",
                "36",
                "--street-name",
                "Grove Street",
                "--accent",
                "navy",
                "--out",
                style_path,
            ]
        )
        if code != 0:
            raise AssertionError(f"exit {code}: {err or out}")
        _assert_pdf(style_path)

    r.run(f"PDF --style {style}", _style)

    all_path = os.path.join(outdir, "cli_all_landscape.pdf")

    def _all() -> None:
        code, out, err = _run_main(
            [
                "--all",
                "--out",
                all_path,
            ]
        )
        if code != 0:
            raise AssertionError(f"exit {code}: {err or out}")
        _assert_pdf(all_path)
        if "accent=black" not in out:
            raise AssertionError(f"expected default accent=black, got: {out!r}")
        if rls.LONG_UK_STREET not in out and "Twll" not in out:
            raise AssertionError(f"expected long street in summary, got: {out!r}")

    r.run("PDF --all landscape gallery", _all)

    all_accent_path = os.path.join(outdir, "cli_all_landscape_navy.pdf")

    def _all_with_accent() -> None:
        code, out, err = _run_main(
            [
                "--all",
                "--accent",
                "navy",
                "--out",
                all_accent_path,
            ]
        )
        if code != 0:
            raise AssertionError(f"exit {code}: {err or out}")
        _assert_pdf(all_accent_path)
        if "accent=navy" not in out:
            raise AssertionError(f"expected accent=navy, got: {out!r}")

    r.run("PDF --all with --accent navy", _all_with_accent)

    accents_path = os.path.join(outdir, "cli_style_all_accents.pdf")

    def _all_accents() -> None:
        code, out, err = _run_main(
            [
                "--style",
                style,
                "--house-number",
                "36",
                "--street-name",
                "Grove Street",
                "--all-accents",
                "--out",
                accents_path,
            ]
        )
        if code != 0:
            raise AssertionError(f"exit {code}: {err or out}")
        _assert_pdf(accents_path)

    r.run(f"PDF --style {style} --all-accents", _all_accents)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Tests for render_landscape_sheet.py"
    )
    parser.add_argument(
        "--no-pdf",
        action="store_true",
        help="Skip PDF smoke renders",
    )
    parser.add_argument(
        "--outdir",
        default=os.path.join(_SCRIPT_DIR, "test_output", "landscape_cli"),
        help="Directory for PDF smoke outputs",
    )
    args = parser.parse_args(argv)

    print("render_landscape_sheet test suite")
    print(f"  outdir: {args.outdir}")
    print()

    r = SuiteResult()

    print("== Discovery ==")
    test_discovery(r)
    print()

    print("== Order wiring ==")
    test_order_wiring(r)
    print()

    print("== CLI ==")
    test_cli_order_flags(r)
    test_cli_list(r)
    test_cli_validation(r)
    print()

    if not args.no_pdf:
        print("== PDF smoke ==")
        test_pdf_renders(r, args.outdir)
        print()
    else:
        print("== PDF smoke skipped (--no-pdf) ==")
        print()

    n_pass, n_fail = len(r.passed), len(r.failed)
    print("=" * 60)
    print(f"Results: {n_pass} passed, {n_fail} failed")
    if r.failed:
        print("Failures:")
        for name, detail in r.failed:
            print(f"  - {name}: {detail}")
        return 1
    print("All checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
