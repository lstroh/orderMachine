"""
silhouette_utils.py — reusable mechanical steps for turning a finished
mockup image (icon + baked-in text/decoration, on a card or plain
background) into a clean, transparent, recolourable silhouette asset.

This module deliberately does NOT try to automate the judgment calls
(which components to keep vs. erase, where a curve fit's clean range
starts/stops, how much to dim a lighter element). Those are the SKILL's
job — this module just makes each mechanical step fast and repeatable so
the judgment calls are the only thing left to do by hand.

Typical workflow (see SKILL.md for the full walkthrough):

    1. inspect(path)                          -- look at what you're working with
    2. mask, method = separate_ink(arr)       -- find "ink" vs "background/card"
    3. labels, report = label_components(mask) -- connected components + a table
       ... look at `report`, decide which labels are icon vs. which are
           baked-in text/decoration to erase ...
    4. build_silhouette(arr, labels, keep={...}, tonal_groups={...})
    5. crop_to_content(out)
    6. verify(out_path)                       -- re-run (2)-(3) on the result,
                                                  confirm component count matches
                                                  what you expected to keep
    7. measure_gap(...) / measure_centroid(...) / fit_curve(...) as needed
       to turn positions into placement constants for whatever's consuming
       this asset (e.g. bin_sticker.py's P02_* constants)

Every function takes/returns plain numpy arrays and plain Python numbers
-- nothing here is tied to any particular downstream renderer.
"""

import numpy as np
from PIL import Image
from scipy import ndimage


# ---------------------------------------------------------------------------
# Step 1: inspect
# ---------------------------------------------------------------------------

def inspect(path):
    """Prints basic facts about an image that tell you which separation
    method (alpha vs. luminance) is likely to work. Returns the RGBA
    array for convenience."""
    img = Image.open(path).convert("RGBA")
    arr = np.array(img).astype(int)
    alpha = arr[:, :, 3]
    print(f"size: {img.size}, mode: {img.mode}")
    print(f"corner pixel (0,0): {tuple(arr[0,0])}")
    print(f"centre pixel: {tuple(arr[arr.shape[0]//2, arr.shape[1]//2])}")
    vals, counts = np.unique(alpha, return_counts=True)
    top = sorted(zip(counts, vals), reverse=True)[:8]
    print("alpha histogram (top 8 values by pixel count):")
    for cnt, v in top:
        print(f"  alpha={v}: {cnt} px")
    return arr


# ---------------------------------------------------------------------------
# Step 2: separate ink (the linework you want) from background/card
# ---------------------------------------------------------------------------

def separate_ink(arr, alpha_threshold=10, luminance_threshold=None):
    """Returns (ink_mask, method_used).

    Tries the simple case first: if there's a clean alpha split between
    "opaque ink" and "transparent background" with nothing in between
    (a proper cutout with no card/background baked in), alpha alone
    works. Otherwise falls back to luminance (dark ink vs. light card),
    which is what you need when the source is a *mockup* (card + ink
    both opaque, only distinguishable by how dark they are).

    If separation looks ambiguous either way, this prints a histogram
    and asks you to pass luminance_threshold explicitly rather than
    guessing -- silently picking a bad threshold produces a mask that's
    subtly wrong in ways that are easy to miss until several steps later.
    """
    alpha = arr[:, :, 3]
    content = alpha > alpha_threshold

    # does alpha alone cleanly separate ink from background within the
    # content area? (i.e. is it a real cutout, not a mockup on a card)
    alpha_in_content = alpha[content]
    high = (alpha_in_content > 250).sum()
    mid = ((alpha_in_content > 50) & (alpha_in_content <= 250)).sum()
    if mid < 0.05 * (high + mid):
        # background pixels are just alpha==0, ink is uniformly near-opaque
        # -- but this alone doesn't prove there's no card. Check RGB
        # variance within the opaque region; a real cutout's ink is
        # usually a narrow colour range, a mockup's card+ink is bimodal.
        lum = arr[:, :, :3].mean(axis=2)
        lum_content = lum[content]
        hist, edges = np.histogram(lum_content, bins=20)
        # crude bimodality check: is there a near-empty valley between
        # two populated regions?
        nonzero_bins = np.where(hist > hist.max() * 0.05)[0]
        gaps = np.diff(nonzero_bins)
        if len(gaps) == 0 or gaps.max() <= 1:
            return content, "alpha"  # single population -> just use alpha/content

    # fall back to luminance. Auto-pick a threshold at the valley between
    # the two largest histogram clusters if not given explicitly.
    lum = arr[:, :, :3].mean(axis=2)
    if luminance_threshold is None:
        lum_content = lum[content]
        hist, edges = np.histogram(lum_content, bins=30)
        # find the deepest valley between the two tallest peaks
        peak1 = hist.argmax()
        masked = hist.copy()
        masked[max(0, peak1 - 2):peak1 + 3] = 0
        peak2 = masked.argmax()
        lo, hi = sorted([peak1, peak2])
        if hi > lo + 1:
            valley = lo + 1 + np.argmin(hist[lo + 1:hi])
            luminance_threshold = (edges[valley] + edges[valley + 1]) / 2
        else:
            luminance_threshold = 150  # no clear valley found -- reasonable default, but VERIFY
            print("WARNING: no clear valley in luminance histogram -- "
                  "using default threshold 150. Check separate_ink()'s "
                  "output mask visually / structurally before trusting it.")
    ink = content & (lum < luminance_threshold)
    return ink, f"luminance<{luminance_threshold:.0f}"


# ---------------------------------------------------------------------------
# Step 3: connected components + a human-readable report
# ---------------------------------------------------------------------------

def label_components(mask, min_size=20):
    """Returns (labels_array, report) where report is a list of dicts
    (sorted largest-first) with label id, size, bbox, and centre --
    everything you need to decide by eye which labels to keep vs. erase.
    Print the report (or just look at it) before deciding."""
    labels, n = ndimage.label(mask, structure=np.ones((3, 3)))
    sizes = ndimage.sum(mask, labels, range(1, n + 1))
    report = []
    for i, s in enumerate(sizes):
        if s < min_size:
            continue
        lbl = i + 1
        ys, xs = np.where(labels == lbl)
        report.append({
            "label": lbl,
            "size": int(s),
            "bbox_x": (int(xs.min()), int(xs.max())),
            "bbox_y": (int(ys.min()), int(ys.max())),
            "center": (float((xs.min() + xs.max()) / 2), float((ys.min() + ys.max()) / 2)),
        })
    report.sort(key=lambda r: -r["size"])
    return labels, report


def print_report(report):
    for r in report:
        print(f"label {r['label']:>4}  size={r['size']:>7}  "
              f"bbox_x={r['bbox_x']}  bbox_y={r['bbox_y']}  center={r['center']}")


# ---------------------------------------------------------------------------
# Step 4: build the output silhouette from your keep/erase decision
# ---------------------------------------------------------------------------

def build_silhouette(arr, labels, keep_labels, tonal_groups=None, fill_rgb=(20, 20, 20)):
    """Builds a new RGBA array containing only `keep_labels`, alpha=0
    everywhere else (erased text/decoration, original background, and
    anything else you didn't list).

    tonal_groups (optional): dict of {label: dim_factor} to preserve a
    visual hierarchy (e.g. a lighter/thinner element) through a
    recolour-by-alpha-mask pipeline, which otherwise flattens everything
    to one solid tone. dim_factor multiplies that label's alpha (e.g.
    0.76 to make it print ~25% lighter once recoloured). Labels not
    listed keep their original alpha.

    fill_rgb is arbitrary -- if whatever consumes this asset recolours
    by alpha mask (overwriting RGB, using alpha as opacity), the actual
    colour here doesn't matter. If not, set this to the real ink colour.
    """
    alpha = arr[:, :, 3]
    tonal_groups = tonal_groups or {}
    new_alpha = np.zeros(alpha.shape, dtype=np.uint8)
    for lbl in keep_labels:
        m = labels == lbl
        factor = tonal_groups.get(lbl, 1.0)
        new_alpha[m] = np.clip(alpha[m] * factor, 0, 255).astype(np.uint8)

    out = np.zeros((*alpha.shape, 4), dtype=np.uint8)
    out[:, :, 0] = fill_rgb[0]
    out[:, :, 1] = fill_rgb[1]
    out[:, :, 2] = fill_rgb[2]
    out[:, :, 3] = new_alpha
    return out


def crop_to_content(rgba_arr, padding_pct=0.03):
    """Crops to the bounding box of all non-transparent pixels, with a
    small padding margin. Returns (cropped_array, (x0, y0)) -- keep the
    offset, you'll need it to convert any coordinates measured in the
    ORIGINAL image into the cropped asset's coordinate space."""
    alpha = rgba_arr[:, :, 3]
    ys, xs = np.where(alpha > 0)
    x0, x1, y0, y1 = xs.min(), xs.max(), ys.min(), ys.max()
    pad_x = int((x1 - x0) * padding_pct)
    pad_y = int((y1 - y0) * padding_pct)
    x0, x1 = max(0, x0 - pad_x), min(rgba_arr.shape[1] - 1, x1 + pad_x)
    y0, y1 = max(0, y0 - pad_y), min(rgba_arr.shape[0] - 1, y1 + pad_y)
    return rgba_arr[y0:y1 + 1, x0:x1 + 1], (int(x0), int(y0))


# ---------------------------------------------------------------------------
# Step 6: verify -- re-run the same analysis on your OWN output
# ---------------------------------------------------------------------------

def verify(path, expected_component_count=None):
    """Re-runs label_components on a saved file so you can sanity-check
    your own output: right number of pieces, no stray leftover fragments
    from a mis-drawn erase boundary. Prints the report either way;
    returns True/False only if expected_component_count is given."""
    arr = np.array(Image.open(path).convert("RGBA"))
    mask = arr[:, :, 3] > 10
    labels, report = label_components(mask, min_size=200)
    print(f"verify({path}): {len(report)} significant component(s)")
    print_report(report)
    if expected_component_count is not None:
        ok = len(report) == expected_component_count
        print("OK" if ok else f"MISMATCH -- expected {expected_component_count}")
        return ok
    return None


# ---------------------------------------------------------------------------
# Step 7: measurement utilities for deriving placement constants
# ---------------------------------------------------------------------------

def component_centroid(labels, labels_to_include):
    """Pixel-mass centroid (not bbox midpoint!) of one or more labels --
    use this instead of a bbox centre for any hollow/shape that isn't a
    simple rectangle (a roof-shaped opening, an asymmetric blob, etc.),
    where the bbox midpoint and the visual "middle" are different points.
    `labels` is the label ARRAY from label_components(), not the mask."""
    m = np.isin(labels, list(labels_to_include))
    ys, xs = np.where(m)
    return float(xs.mean()), float(ys.mean())


def measure_gap(mask, fixed_index, index_range, axis="row"):
    """Finds the biggest empty gap between ink segments along one row
    (axis='row', fixed_index=y, scans x within index_range) or one
    column (axis='col', fixed_index=x, scans y within index_range).
    This is how you measure the actual CLEAR interior width/height
    between two ink strokes (e.g. the gap between a house's two walls) --
    NOT the same as the ink's outer bounding box span, which includes
    the strokes themselves.

    Returns (gap_size, seg1_edge, seg2_edge) or None if fewer than 2
    ink points exist along that line.
    """
    if axis == "row":
        line = mask[fixed_index, index_range[0]:index_range[1]]
    else:
        line = mask[index_range[0]:index_range[1], fixed_index]
    positions = np.where(line)[0]
    if len(positions) < 2:
        return None
    diffs = np.diff(positions)
    idx = np.argmax(diffs)
    return int(diffs[idx]), int(positions[idx] + index_range[0]), int(positions[idx + 1] + index_range[0])


def trace_band(mask, x_range, min_gap=1):
    """For each column in x_range, finds the biggest vertical gap in
    `mask` (the interior of a curved band/ribbon shape) and returns an
    array of (x, top, bottom, mid, gap_size). This is the raw material
    for fit_curve() -- inspect the `gap` column before fitting to spot
    where a shape's flared/folded ends break the clean curve (see
    SKILL.md's curve-fitting section for how to read this)."""
    rows = []
    for x in range(x_range[0], x_range[1]):
        col = mask[:, x]
        ys = np.where(col)[0]
        if len(ys) < 2:
            continue
        diffs = np.diff(ys)
        idx = np.argmax(diffs)
        gap = diffs[idx]
        if gap < min_gap:
            continue
        top, bot = ys[idx], ys[idx + 1]
        rows.append((x, top, bot, (top + bot) / 2, gap))
    return np.array(rows)


def fit_curve(band_data, x_range=None, degree=2):
    """Fits a polynomial to a trace_band() result's mid-column. Pass a
    tighter x_range than the full band if you've identified (by eye,
    from trace_band's output) where the clean/smooth region ends and
    flared or folded artifacts begin. Returns (coeffs, residual_std) --
    residual_std is your fit-quality signal: under ~1px is a clean fit
    (trustworthy), tens of px means you're probably still including a
    non-smooth region and should narrow x_range further."""
    data = band_data
    if x_range is not None:
        data = data[(data[:, 0] >= x_range[0]) & (data[:, 0] <= x_range[1])]
    x, mid = data[:, 0], data[:, 3]
    coeffs = np.polyfit(x, mid, degree)
    resid = mid - np.poly1d(coeffs)(x)
    return coeffs, float(resid.std())
