"""
Full test suite for bin_sticker.py.

Covers registry/helper contracts, required landscape assets, and the
README smoke suite: single card, 4-up sheet, full gallery, edge-case text.

Usage (from this folder):
    python test_bin_sticker.py
    python test_bin_sticker.py --no-pdf
    python test_bin_sticker.py --outdir path/to/out
"""

from __future__ import annotations

import argparse
import os
import sys
import traceback
from typing import Callable

from reportlab.pdfgen import canvas

_SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
os.chdir(_SCRIPT_DIR)

import bin_sticker as bs  # noqa: E402

# Keep in sync with STYLES in bin_sticker.py (10 portrait + 23 landscape).
EXPECTED_STYLE_COUNT = 33

LANDSCAPE_SIZE = (bs.P02_CARD_W, bs.P02_CARD_H)
LANDSCAPE_STYLES = tuple(
    s for s, size in bs.STYLE_CARD_SIZE.items() if size == LANDSCAPE_SIZE
)
PORTRAIT_STYLES = tuple(
    s for s, size in bs.STYLE_CARD_SIZE.items() if size == (bs.CARD_W, bs.CARD_H)
)

REQUIRED_ASSETS = (
    # D01–D05 / P25 family
    bs.P02_ICON_MASTER,
    bs.P25_FLOURISH1_ICON,
    bs.P25_FLOURISH2_ICON,
    bs.P25B_FLOURISH_ICON,
    bs.P25B_CORNER_TL,
    bs.P25B_CORNER_TR,
    bs.P25B_CORNER_BR,
    bs.P25B_CORNER_BL,
    bs.P27_ICON_MASTER,
    bs.P47_ICON_MASTER,
    # D18–D23 wreaths
    bs.P06_ICON_MASTER,
    bs.P30_LAUREL_ICON_MASTER,
    bs.P15_HEART_ICON_MASTER,
    bs.P28_ARROW_ICON_MASTER,
    bs.P31_OLIVE_ICON_MASTER,
    # D06–D17 animal families
    bs.DUCK_FATHER_ICON_MASTER,
    bs.DUCK_MOTHER_ICON_MASTER,
    bs.DUCK_PLAYING1_ICON_MASTER,
    bs.DUCK_PLAYING2_ICON_MASTER,
    bs.DOG_FAMILY_1_ICON_MASTER,
    bs.DOG_FAMILY_2_ICON_MASTER,
    bs.DOG_PLAYING1_ICON_MASTER,
    bs.DOG_PLAYING2_ICON_MASTER,
    bs.CAT_FAMILY_1_ICON_MASTER,
    bs.CAT_FAMILY_2_ICON_MASTER,
    bs.CAT_PLAYING1_ICON_MASTER,
    bs.CAT_PLAYING2_ICON_MASTER,
)

SAMPLE = {"house_number": "36", "street_name": "Grove Street", "accent": "navy"}
EDGE_LONG = {
    "house_number": "1400",
    "street_name": "Old Winchester Road",
    "accent": "terracotta",
}
EDGE_SHORT = {"house_number": "7", "street_name": "Rye", "accent": "charcoal"}


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


def test_registry(r: SuiteResult) -> None:
    r.check(
        f"registry: {EXPECTED_STYLE_COUNT} styles",
        len(bs.STYLES) == EXPECTED_STYLE_COUNT,
        f"got {len(bs.STYLES)}",
    )

    missing_labels = set(bs.STYLES) - set(bs.STYLE_LABELS)
    r.check(
        "registry: every style has STYLE_LABELS",
        not missing_labels,
        f"missing labels: {missing_labels}",
    )

    missing_sizes = set(bs.STYLES) - set(bs.STYLE_CARD_SIZE)
    r.check(
        "registry: every style has STYLE_CARD_SIZE",
        not missing_sizes,
        f"missing sizes: {missing_sizes}",
    )

    # Every style must be classified as exactly one of portrait/landscape.
    classified = set(PORTRAIT_STYLES) | set(LANDSCAPE_STYLES)
    r.check(
        "registry: every style is portrait or landscape",
        classified == set(bs.STYLES),
        f"unclassified: {set(bs.STYLES) - classified}; "
        f"extra: {classified - set(bs.STYLES)}",
    )
    r.check(
        "registry: 10 portrait styles",
        len(PORTRAIT_STYLES) == 10,
        f"got {len(PORTRAIT_STYLES)}: {PORTRAIT_STYLES}",
    )
    r.check(
        "registry: 23 landscape styles",
        len(LANDSCAPE_STYLES) == 23,
        f"got {len(LANDSCAPE_STYLES)}: {LANDSCAPE_STYLES}",
    )

    portrait_ok = all(
        bs.STYLE_CARD_SIZE[s] == (bs.CARD_W, bs.CARD_H) for s in PORTRAIT_STYLES
    )
    r.check("registry: portrait styles use CARD_W/H", portrait_ok)

    landscape_ok = all(
        bs.STYLE_CARD_SIZE[s] == LANDSCAPE_SIZE for s in LANDSCAPE_STYLES
    )
    r.check("registry: landscape styles use P02_CARD_W/H", landscape_ok)

    # Catalogued products are landscape-only today (D01–D23).
    product_ok = set(bs.STYLE_PRODUCT_ID).issubset(set(LANDSCAPE_STYLES))
    r.check(
        "registry: STYLE_PRODUCT_ID keys subset of landscape",
        product_ok,
        f"unexpected: {set(bs.STYLE_PRODUCT_ID) - set(LANDSCAPE_STYLES)}",
    )
    r.check(
        "registry: every landscape style has STYLE_PRODUCT_ID",
        set(LANDSCAPE_STYLES) == set(bs.STYLE_PRODUCT_ID),
        f"missing: {set(LANDSCAPE_STYLES) - set(bs.STYLE_PRODUCT_ID)}; "
        f"extra: {set(bs.STYLE_PRODUCT_ID) - set(LANDSCAPE_STYLES)}",
    )


def test_resolve_accent(r: SuiteResult) -> None:
    for key, hex_val in bs.ACCENTS.items():
        r.check(
            f"_resolve_accent: ACCENTS[{key!r}]",
            bs._resolve_accent(key) == hex_val,
            f"got {bs._resolve_accent(key)!r}",
        )
    for key, hex_val in bs.CLEAR_VINYL_ACCENTS.items():
        r.check(
            f"_resolve_accent: CLEAR_VINYL[{key!r}]",
            bs._resolve_accent(key) == hex_val,
            f"got {bs._resolve_accent(key)!r}",
        )
    r.check(
        "_resolve_accent: unknown falls back to navy",
        bs._resolve_accent("not_a_real_colour") == bs.ACCENTS["navy"],
        f"got {bs._resolve_accent('not_a_real_colour')!r}",
    )


def test_fit_font_size(r: SuiteResult) -> None:
    short = bs._fit_font_size("36", "Helvetica-Bold", 44, 20, 50 * bs.mm)
    r.check(
        "_fit_font_size: short text near max",
        short >= 40,
        f"got {short}",
    )
    long = bs._fit_font_size(
        "OLD WINCHESTER ROAD", "Helvetica-Bold", 19, 8, 40 * bs.mm
    )
    r.check(
        "_fit_font_size: long text shrinks",
        8 <= long < 19,
        f"got {long}",
    )
    r.check(
        "_fit_font_size: long text >= min",
        long >= 8,
        f"got {long}",
    )


def test_draw_curved_text_uses_passed_coeffs(r: SuiteResult) -> None:
    """Regression: rotation must use curve_coeffs, not a hardcoded P02 curve."""

    def _smoke() -> None:
        path = os.path.join(_SCRIPT_DIR, "_tmp_curved_text.pdf")
        try:
            c = canvas.Canvas(path, pagesize=(bs.P02_CARD_W, bs.P02_CARD_H))
            # Flat curve (a=0, b=0) — if coeffs were ignored, P02's steep
            # banner slope would still rotate glyphs; with correct coeffs
            # this is a no-op rotation smoke path.
            flat_coeffs = (0.0, 0.0, 0.0)

            def flat_fn(_x_px: float) -> float:
                return 0.0

            bs._draw_curved_text(
                c,
                "GROVE",
                bs.P02_CARD_W / 2,
                40 * bs.mm,
                "Helvetica-Bold",
                14,
                bs.INK,
                10 * bs.mm,
                0.1,
                flat_fn,
                flat_coeffs,
            )
            c.showPage()
            c.save()
            _assert_pdf(path)
        finally:
            if os.path.exists(path):
                os.remove(path)

    r.run("_draw_curved_text: accepts explicit curve_coeffs", _smoke)


def test_mixed_sheet_raises(r: SuiteResult) -> None:
    def _mixed() -> None:
        orders = [
            {**SAMPLE, "style": "minimal"},
            {**SAMPLE, "style": "house_banner"},
        ]
        out = os.path.join(_SCRIPT_DIR, "_should_not_exist_mixed.pdf")
        try:
            bs.render_sheet(orders, out)
        except ValueError:
            return
        finally:
            if os.path.exists(out):
                os.remove(out)
        raise AssertionError("expected ValueError for mixed card sizes")

    r.run("render_sheet: mixed portrait+landscape raises ValueError", _mixed)


def test_required_assets(r: SuiteResult) -> None:
    for rel in REQUIRED_ASSETS:
        path = bs._asset_path(rel)
        r.check(
            f"asset present: {rel}",
            os.path.isfile(path),
            f"missing {path}",
        )


def _render_single(style: str, outdir: str, order_base: dict) -> str:
    w, h = bs.STYLE_CARD_SIZE[style]
    path = os.path.join(outdir, f"single_{style}.pdf")
    order = dict(order_base)
    order["style"] = style
    c = canvas.Canvas(path, pagesize=(w, h))
    bs.draw_sticker(c, 0, 0, order)
    c.showPage()
    c.save()
    _assert_pdf(path)
    return path


def test_pdf_renders(r: SuiteResult, outdir: str) -> None:
    os.makedirs(outdir, exist_ok=True)

    for style in bs.STYLES:
        r.run(
            f"PDF single card: {style}",
            lambda s=style: _render_single(s, outdir, SAMPLE),
        )

    def _portrait_sheet() -> None:
        styles = list(PORTRAIT_STYLES)[:4]
        orders = [{**SAMPLE, "style": s} for s in styles]
        path = os.path.join(outdir, "sheet_portrait_4up.pdf")
        bs.render_sheet(orders, path)
        _assert_pdf(path)

    r.run("PDF 4-up portrait sheet", _portrait_sheet)

    for style in LANDSCAPE_STYLES:
        def _landscape_sheet(s: str = style) -> None:
            orders = [{**SAMPLE, "style": s} for _ in range(4)]
            path = os.path.join(outdir, f"sheet_{s}_4up.pdf")
            bs.render_sheet(orders, path)
            _assert_pdf(path)

        r.run(f"PDF 4-up landscape sheet: {style}", _landscape_sheet)

    def _gallery() -> None:
        path = os.path.join(outdir, "gallery_all_styles.pdf")
        bs.render_gallery(list(bs.STYLES.keys()), SAMPLE, path)
        _assert_pdf(path)

    r.run("PDF full gallery all styles", _gallery)

    def _edge_long() -> None:
        path = os.path.join(outdir, "edge_long_text.pdf")
        # Auto-fit landscape styles + a couple of portrait styles
        keys = list(LANDSCAPE_STYLES) + ["classic", "minimal"]
        bs.render_gallery(keys, EDGE_LONG, path)
        _assert_pdf(path)

    r.run("PDF edge-case long text", _edge_long)

    def _edge_short() -> None:
        path = os.path.join(outdir, "edge_short_text.pdf")
        keys = list(LANDSCAPE_STYLES) + ["classic", "minimal"]
        bs.render_gallery(keys, EDGE_SHORT, path)
        _assert_pdf(path)

    r.run("PDF edge-case short text", _edge_short)

    def _clear_vinyl() -> None:
        # render_gallery doesn't vary accent; draw a custom multi-page sheet
        from reportlab.lib.pagesizes import A4

        path = os.path.join(outdir, "clear_vinyl_accents.pdf")
        accents = list(bs.CLEAR_VINYL_ACCENTS.keys())
        c = canvas.Canvas(path, pagesize=A4)
        page_w, page_h = A4
        _, _, positions = bs._sheet_layout(bs.CARD_W, bs.CARD_H, page_w, page_h)
        for i, accent in enumerate(accents):
            slot = i % 4
            if i > 0 and slot == 0:
                c.showPage()
            x, y = positions[slot]
            order = {
                "house_number": "36",
                "street_name": "Grove Street",
                "style": "minimal",
                "accent": accent,
            }
            bs.draw_sticker(c, x, y, order)
            c.setFont("Helvetica", 6)
            c.drawCentredString(x + bs.CARD_W / 2, 2.5 * bs.mm, accent)
        c.showPage()
        c.save()
        _assert_pdf(path)

    r.run("PDF clear-vinyl accent smoke", _clear_vinyl)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Full test suite for bin_sticker.py")
    parser.add_argument(
        "--no-pdf",
        action="store_true",
        help="Run unit/asset checks only (skip PDF smoke renders)",
    )
    parser.add_argument(
        "--outdir",
        default=os.path.join(_SCRIPT_DIR, "test_output"),
        help="Directory for PDF smoke outputs (default: ./test_output)",
    )
    args = parser.parse_args(argv)

    print("bin_sticker full test suite")
    print(f"  script dir: {_SCRIPT_DIR}")
    print(f"  outdir:     {args.outdir}")
    print()

    r = SuiteResult()

    print("== Registry ==")
    test_registry(r)
    print()

    print("== Helpers ==")
    test_resolve_accent(r)
    test_fit_font_size(r)
    test_draw_curved_text_uses_passed_coeffs(r)
    test_mixed_sheet_raises(r)
    print()

    print("== Assets ==")
    test_required_assets(r)
    print()

    if not args.no_pdf:
        print("== PDF smoke renders ==")
        test_pdf_renders(r, args.outdir)
        print()
    else:
        print("== PDF smoke renders skipped (--no-pdf) ==")
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
