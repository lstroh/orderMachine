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

# Keep in sync with STYLES in bin_sticker.py. The 10 portrait styles were
# removed Sep 2026 -- this generator is landscape-only now. Portrait
# count is asserted at 0 (rather than deleting the portrait-related
# checks below) so this suite doubles as a regression check that they
# stay gone.
EXPECTED_STYLE_COUNT = 25
EXPECTED_PORTRAIT_COUNT = 0
EXPECTED_LANDSCAPE_COUNT = 25

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
    # D25 P21 (D24 / p09a is text-only — no master icon)
    bs.P21_ICON_MASTER,
)

SAMPLE = {"house_number": "36", "street_name": "Grove Street", "accent": "navy"}
EDGE_LONG = {
    "house_number": "1400",
    "street_name": "Old Winchester Road",
    "accent": "terracotta",
}
EDGE_SHORT = {"house_number": "7", "street_name": "Rye", "accent": "charcoal"}

# All 12 animal-family scenes share _animal_family_text auto-shrink.
ANIMAL_FAMILY_STYLES = (
    "duck_family_father",
    "duck_family_mother",
    "duck_family_playing1",
    "duck_family_playing2",
    "dog_family_1",
    "dog_family_2",
    "dog_family_playing1",
    "dog_family_playing2",
    "cat_family_1",
    "cat_family_2",
    "cat_family_playing1",
    "cat_family_playing2",
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
        f"registry: {EXPECTED_PORTRAIT_COUNT} portrait styles",
        len(PORTRAIT_STYLES) == EXPECTED_PORTRAIT_COUNT,
        f"got {len(PORTRAIT_STYLES)}: {PORTRAIT_STYLES}",
    )
    r.check(
        f"registry: {EXPECTED_LANDSCAPE_COUNT} landscape styles",
        len(LANDSCAPE_STYLES) == EXPECTED_LANDSCAPE_COUNT,
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

    # Catalogued products are landscape-only today (D01–D25).
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

    r.check(
        "registry: p09a_borderless is D24",
        bs.STYLE_PRODUCT_ID.get("p09a_borderless") == "D24",
        f"got {bs.STYLE_PRODUCT_ID.get('p09a_borderless')!r}",
    )
    r.check(
        "registry: p21_paw_trail is D25",
        bs.STYLE_PRODUCT_ID.get("p21_paw_trail") == "D25",
        f"got {bs.STYLE_PRODUCT_ID.get('p21_paw_trail')!r}",
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
    """render_sheet's mixed-card-size guard used to be tested with a real
    portrait style ("minimal") against a landscape one. Since the 10
    portrait styles were removed Sep 2026, every real style is now
    140x100mm and there's no longer a second real size to mix in. Rather
    than delete this regression check, register a temporary synthetic
    style with a different size, confirm the guard still raises, then
    remove it -- this still proves the ValueError path itself works,
    which matters again the moment a second real size (e.g. Medium,
    210x140mm) gets registered."""

    def _mixed() -> None:
        fake_style = "_test_fake_other_size"
        fake_size = (50 * bs.mm, 50 * bs.mm)  # deliberately unlike 140x100mm
        assert fake_style not in bs.STYLES, "fake test style name collided with a real one"
        bs.STYLES[fake_style] = bs.STYLES["house_banner"]
        bs.STYLE_CARD_SIZE[fake_style] = fake_size
        orders = [
            {**SAMPLE, "style": fake_style},
            {**SAMPLE, "style": "house_banner"},
        ]
        out = os.path.join(_SCRIPT_DIR, "_should_not_exist_mixed.pdf")
        try:
            try:
                bs.render_sheet(orders, out)
            except ValueError:
                return
            raise AssertionError("expected ValueError for mixed card sizes")
        finally:
            if os.path.exists(out):
                os.remove(out)
            bs.STYLES.pop(fake_style, None)
            bs.STYLE_CARD_SIZE.pop(fake_style, None)

    r.run("render_sheet: mixed card sizes raises ValueError", _mixed)


def test_p09a_borderless_contracts(r: SuiteResult) -> None:
    """P09a: text-only landscape; underline width tracks fitted street width."""
    from reportlab.pdfbase.pdfmetrics import stringWidth

    r.check(
        "p09a: registered landscape",
        "p09a_borderless" in LANDSCAPE_STYLES,
    )

    short_street = "RYE"
    long_street = "OLD WINCHESTER ROAD"
    short_size = bs._fit_font_size(
        short_street,
        "Helvetica-Bold",
        bs.P09A_STREET_MAX_SIZE,
        bs.P09A_STREET_MIN_SIZE,
        bs.P09A_STREET_MAX_WIDTH,
    )
    long_size = bs._fit_font_size(
        long_street,
        "Helvetica-Bold",
        bs.P09A_STREET_MAX_SIZE,
        bs.P09A_STREET_MIN_SIZE,
        bs.P09A_STREET_MAX_WIDTH,
    )
    short_w = stringWidth(short_street, "Helvetica-Bold", short_size)
    long_w = stringWidth(long_street, "Helvetica-Bold", long_size)
    r.check(
        "p09a: underline width tracks street text length",
        short_w < long_w,
        f"short={short_w:.2f} long={long_w:.2f}",
    )

    # Number auto-fit must shrink when glyphs exceed P09A_NUMBER_MAX_WIDTH.
    huge = bs._fit_font_size(
        "1234567890",
        "Helvetica-Bold",
        bs.P09A_NUMBER_MAX_SIZE,
        bs.P09A_NUMBER_MIN_SIZE,
        bs.P09A_NUMBER_MAX_WIDTH,
    )
    short_num = bs._fit_font_size(
        "7",
        "Helvetica-Bold",
        bs.P09A_NUMBER_MAX_SIZE,
        bs.P09A_NUMBER_MIN_SIZE,
        bs.P09A_NUMBER_MAX_WIDTH,
    )
    r.check(
        "p09a: short house number stays at max size",
        short_num == bs.P09A_NUMBER_MAX_SIZE,
        f"got {short_num}",
    )
    r.check(
        "p09a: oversized house number shrinks below max",
        huge < bs.P09A_NUMBER_MAX_SIZE,
        f"got {huge}",
    )


def test_p21_paw_trail_contracts(r: SuiteResult) -> None:
    """P21: off-centre text beside icon; recolour cache path works."""
    r.check(
        "p21: registered landscape",
        "p21_paw_trail" in LANDSCAPE_STYLES,
    )
    r.check(
        "p21: number X is right of icon box",
        bs.P21_NUMBER_CENTER_X > bs.P21_ICON["x"] + bs.P21_ICON["w"],
        f"number_x={bs.P21_NUMBER_CENTER_X} icon_right="
        f"{bs.P21_ICON['x'] + bs.P21_ICON['w']}",
    )
    r.check(
        "p21: street X is right of icon left edge",
        bs.P21_STREET_CENTER_X > bs.P21_ICON["x"],
        f"street_x={bs.P21_STREET_CENTER_X} icon_x={bs.P21_ICON['x']}",
    )
    r.check(
        "p21: PAD exceeds shared PAD (edge clearance)",
        bs.P21_PAD > bs.PAD,
        f"P21_PAD={bs.P21_PAD} PAD={bs.PAD}",
    )

    def _recolour() -> None:
        path = bs._p21_icon_path("navy")
        if path is None:
            raise AssertionError("expected recolour path, got None")
        if not os.path.isfile(path):
            raise AssertionError(f"recolour cache missing: {path}")
        cache_dir = os.path.abspath(bs._get_icon_cache_dir())
        if os.path.abspath(path).startswith(cache_dir) is False:
            raise AssertionError(
                f"recolour wrote outside temp cache: {path} (cache={cache_dir})"
            )

    r.run("p21: _p21_icon_path caches navy recolour", _recolour)


def test_animal_family_autofit(r: SuiteResult) -> None:
    """Animal families: shared auto-shrink replaces fixed 50pt/16pt text."""
    missing = [s for s in ANIMAL_FAMILY_STYLES if s not in bs.STYLES]
    r.check(
        "animal family: all 12 styles registered",
        not missing and len(ANIMAL_FAMILY_STYLES) == 12,
        f"missing={missing}",
    )

    not_using_helper = [
        s
        for s in ANIMAL_FAMILY_STYLES
        if "_animal_family_text" not in bs.STYLES[s].__code__.co_names
    ]
    r.check(
        "animal family: every style calls _animal_family_text",
        not not_using_helper,
        f"styles without helper: {not_using_helper}",
    )

    r.check(
        "animal family: number max is 63pt (raised from 50 after print test)",
        bs.ANIMAL_NUMBER_MAX_SIZE == 63,
        f"got {bs.ANIMAL_NUMBER_MAX_SIZE}",
    )
    r.check(
        "animal family: street max is 27pt (raised from 16 after print test)",
        bs.ANIMAL_STREET_MAX_SIZE == 27,
        f"got {bs.ANIMAL_STREET_MAX_SIZE}",
    )

    short_num = bs._fit_font_size(
        "36",
        "Helvetica-Bold",
        bs.ANIMAL_NUMBER_MAX_SIZE,
        bs.ANIMAL_NUMBER_MIN_SIZE,
        bs.ANIMAL_NUMBER_MAX_WIDTH,
    )
    long_num = bs._fit_font_size(
        "1400",
        "Helvetica-Bold",
        bs.ANIMAL_NUMBER_MAX_SIZE,
        bs.ANIMAL_NUMBER_MIN_SIZE,
        bs.ANIMAL_NUMBER_MAX_WIDTH,
    )
    # Max widths are deliberately generous; use extreme text to force shrink.
    overflow_num = "9" * 40
    huge_num = bs._fit_font_size(
        overflow_num,
        "Helvetica-Bold",
        bs.ANIMAL_NUMBER_MAX_SIZE,
        bs.ANIMAL_NUMBER_MIN_SIZE,
        bs.ANIMAL_NUMBER_MAX_WIDTH,
    )
    r.check(
        "animal family: short number stays at max",
        short_num == bs.ANIMAL_NUMBER_MAX_SIZE,
        f"got {short_num}",
    )
    r.check(
        "animal family: typical long number stays within max band",
        bs.ANIMAL_NUMBER_MIN_SIZE <= long_num <= bs.ANIMAL_NUMBER_MAX_SIZE,
        f"got {long_num}",
    )
    r.check(
        "animal family: overflowing number shrinks below max",
        huge_num < bs.ANIMAL_NUMBER_MAX_SIZE,
        f"got {huge_num}",
    )
    r.check(
        "animal family: overflowing number stays at/above min",
        huge_num >= bs.ANIMAL_NUMBER_MIN_SIZE,
        f"got {huge_num}",
    )

    short_street = bs._fit_font_size(
        "Grove Street",
        "Helvetica",
        bs.ANIMAL_STREET_MAX_SIZE,
        bs.ANIMAL_STREET_MIN_SIZE,
        bs.ANIMAL_STREET_MAX_WIDTH,
    )
    long_street = bs._fit_font_size(
        "Old Winchester Road",
        "Helvetica",
        bs.ANIMAL_STREET_MAX_SIZE,
        bs.ANIMAL_STREET_MIN_SIZE,
        bs.ANIMAL_STREET_MAX_WIDTH,
    )
    overflow_street = "Winchester " * 12
    huge_street = bs._fit_font_size(
        overflow_street,
        "Helvetica",
        bs.ANIMAL_STREET_MAX_SIZE,
        bs.ANIMAL_STREET_MIN_SIZE,
        bs.ANIMAL_STREET_MAX_WIDTH,
    )
    r.check(
        "animal family: short street stays at max",
        short_street == bs.ANIMAL_STREET_MAX_SIZE,
        f"got {short_street}",
    )
    r.check(
        "animal family: typical long street stays within max band",
        bs.ANIMAL_STREET_MIN_SIZE <= long_street <= bs.ANIMAL_STREET_MAX_SIZE,
        f"got {long_street}",
    )
    r.check(
        "animal family: overflowing street shrinks below max",
        huge_street < bs.ANIMAL_STREET_MAX_SIZE,
        f"got {huge_street}",
    )

    def _helper_smoke() -> None:
        path = os.path.join(_SCRIPT_DIR, "_tmp_animal_family_text.pdf")
        try:
            c = canvas.Canvas(path, pagesize=LANDSCAPE_SIZE)
            bs._animal_family_text(
                c, bs.P02_CARD_W / 2, 0, EDGE_LONG
            )
            c.showPage()
            c.save()
            _assert_pdf(path)
        finally:
            if os.path.exists(path):
                os.remove(path)

    r.run("animal family: _animal_family_text smoke", _helper_smoke)


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

    # No portrait 4-up sheet test -- the 10 portrait styles were removed
    # Sep 2026 and PORTRAIT_STYLES is now expected to be empty (see
    # test_registry). This isn't a gap; it's the point of the removal.

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
        # All 25 remaining styles are landscape now -- no portrait styles
        # left to add on top of LANDSCAPE_STYLES.
        bs.render_gallery(list(LANDSCAPE_STYLES), EDGE_LONG, path)
        _assert_pdf(path)

    r.run("PDF edge-case long text", _edge_long)

    def _edge_short() -> None:
        path = os.path.join(outdir, "edge_short_text.pdf")
        bs.render_gallery(list(LANDSCAPE_STYLES), EDGE_SHORT, path)
        _assert_pdf(path)

    r.run("PDF edge-case short text", _edge_short)

    def _animal_edge_long() -> None:
        # Dedicated regression for the shared animal-family auto-shrink path.
        path = os.path.join(outdir, "edge_animal_family_long_text.pdf")
        bs.render_gallery(list(ANIMAL_FAMILY_STYLES), EDGE_LONG, path)
        _assert_pdf(path)

    r.run("PDF animal-family long-text gallery", _animal_edge_long)

    def _clear_vinyl() -> None:
        # render_gallery doesn't vary accent; draw a custom multi-page
        # sheet. Uses p09a_borderless (text-only, no PNG asset
        # dependency) rather than the old "minimal" -- any style would
        # do here since the point is testing accent colours, not the
        # style itself, but a dependency-free one keeps this smoke test
        # from silently depending on assets/ being present.
        #
        # IMPORTANT: P02_CARD_W/H is a LANDSCAPE card (140x100mm) -- it
        # needs a landscape page, unlike the old portrait CARD_W/H test
        # this replaced (which used plain portrait A4). Using portrait
        # A4 here would make _sheet_layout compute a negative margin and
        # assert (confirmed by actually running this: it did).
        from reportlab.lib.pagesizes import A4, landscape

        path = os.path.join(outdir, "clear_vinyl_accents.pdf")
        accents = list(bs.CLEAR_VINYL_ACCENTS.keys())
        page_size = landscape(A4)
        c = canvas.Canvas(path, pagesize=page_size)
        page_w, page_h = page_size
        _, _, positions = bs._sheet_layout(bs.P02_CARD_W, bs.P02_CARD_H, page_w, page_h)
        for i, accent in enumerate(accents):
            slot = i % 4
            if i > 0 and slot == 0:
                c.showPage()
            x, y = positions[slot]
            order = {
                "house_number": "36",
                "street_name": "Grove Street",
                "style": "p09a_borderless",
                "accent": accent,
            }
            bs.draw_sticker(c, x, y, order)
            c.setFont("Helvetica", 6)
            c.drawCentredString(x + bs.P02_CARD_W / 2, 2.5 * bs.mm, accent)
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

    print("== New styles (P09a / P21) ==")
    test_p09a_borderless_contracts(r)
    test_p21_paw_trail_contracts(r)
    print()

    print("== Animal-family auto-fit ==")
    test_animal_family_autofit(r)
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
