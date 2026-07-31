---
name: icon-silhouette-extraction
description: Extracts a clean, transparent, recolourable silhouette icon asset from a finished mockup image (e.g. a Midjourney/AI-generated product photo, a rendered design preview, or any image where an icon or illustration has decoration or example text baked into the pixels rather than existing as a reusable template). Use this whenever the user uploads a "finished-looking" design image and wants to turn it into a reusable asset — especially if they want to remove baked-in text, recolour it, nest new dynamic text inside empty/hollow areas of the icon, or otherwise reuse the artwork programmatically. Trigger this for phrases like "turn this into an icon", "extract the icon from this", "remove the text from this image so I can reuse it", "make this recolourable", "I have a new image for [design]", or any request to derive placement/sizing constants (position, curve, hollow width) from an image for use in a script. Also relevant for any "hollow icon with nested text" pattern (an icon with an empty interior meant to contain a number, name, or other dynamic text) even if the source image doesn't have baked-in text to remove — the same hollow-measurement and curve-fitting steps apply.
---

# Icon Silhouette Extraction

Turns a finished-looking mockup image into a clean, transparent, single-purpose
silhouette asset — and, if the icon has hollow areas meant to hold dynamic
text (a nested number, a name, a curved banner label, etc.), derives the
placement constants a renderer needs to put that text in the right place.

## Why this needs a skill, not just a script

Roughly half of this workflow is fully mechanical (separating ink from
background, connected-component labelling, cropping, curve fitting) and
is handled by `scripts/silhouette_utils.py` — call these functions, don't
reimplement them. But a few steps are genuine judgment calls that change
per image and would silently produce wrong results if hard-coded:

- **Which connected component is icon artwork vs. baked-in text/decoration
  to erase.** Nothing about component size/position generalizes across
  different icon designs — you have to look at the actual report and reason
  about it each time.
- **Where a curve fit's "clean" range starts and stops.** Ribbons, banners,
  and folded shapes are usually a smooth curve in the middle and flare/fold
  unpredictably at the ends. The fit needs a range that excludes the flare,
  chosen by looking at the traced data, not assumed.
- **How much tonal hierarchy to preserve, and how.** If the downstream
  consumer recolours by using alpha as an opacity mask (common — see
  `references/recolour-by-alpha.md`), any visual weight difference in the
  source (a lighter/thinner element vs. a bold one) has to be re-encoded
  as an alpha difference, or it's lost the moment the asset gets recoloured.

Do these steps by hand, in this order, using the helper functions listed —
don't skip straight to writing output.

## Workflow

### 1. Inspect the source image

```python
import silhouette_utils as su
arr = su.inspect(path)
```

Look at the corner pixel (transparent background?) vs. centre pixel, and
the alpha histogram. This tells you what you're dealing with before you
write a line of separation logic.

### 2. Separate ink (icon linework) from background/card

```python
mask, method = su.separate_ink(arr)
```

This tries alpha first (works for a true cutout with nothing baked onto a
card), and falls back to a luminance threshold (works for a mockup where
the "card" and the "ink" are both opaque, distinguishable only by how
dark they are — which is the common case for AI-generated product mockups).
It auto-picks a luminance threshold at the valley between the two biggest
histogram clusters, and **prints a warning if it can't find a clean
valley** — if you see that warning, don't trust the default; look at the
histogram yourself (`inspect()`'s output, or plot `arr[:,:,:3].mean(axis=2)`
directly) and pass `luminance_threshold=` explicitly.

### 3. Label components and classify them by hand

```python
labels, report = su.label_components(mask)
su.print_report(report)
```

Now look at the table. For each significant component, ask: is this part
of the icon's permanent artwork, or is it baked-in text/decoration that
needs to become part of the hollow interior instead? Rules of thumb that
held true in practice (not guarantees — verify against the actual report):

- The icon's structural outline pieces (a house shape, a wreath ring, a
  ribbon/banner outline) tend to be among the **largest** components and
  span a meaningfully large bounding box.
- Baked-in text tends to show up as **several similarly-sized components in
  a row** (individual letters/digits, each a few thousand px, roughly
  evenly spaced along one line) — a visual "comb" pattern in the bbox
  centres if you sort by x-position.
- Don't assume text and icon-outline never touch. If a digit's stroke
  overlaps or touches a wall/outline stroke, connected-component labelling
  will merge them into one blob, and you can't cleanly separate them this
  way — you'd need a targeted spatial erase (blank a bounding region) with
  padding instead. Check whether your keep/erase decision at this step
  actually produces separate components, or if you're seeing fewer
  components than expected because two things merged.

Write down your decision as two Python sets: `keep_labels` and (implicitly)
everything else gets erased.

### 4. Check for a tonal hierarchy

If some components are visually lighter/thinner than others (check with
`arr[:,:,:3].mean(axis=2)` on each component's pixels — compare mean
luminance across your keep_labels), and the downstream consumer recolours
by alpha-mask (see `references/recolour-by-alpha.md`), decide a per-label
dim factor to preserve that hierarchy:

```python
tonal_groups = {banner_label: 0.76}  # example: banner ~25% dimmer than the rest
```

Skip this if everything's meant to be one uniform weight, or if the
consumer doesn't recolour by alpha mask (in which case just keep real RGB
colours instead of flattening to a single fill colour).

### 5. Build and crop the output

```python
out = su.build_silhouette(arr, labels, keep_labels, tonal_groups=tonal_groups)
cropped, offset = su.crop_to_content(out)
Image.fromarray(cropped, "RGBA").save(output_path)
```

**Save `offset`** — anything you measured in the original image's
coordinates needs that offset subtracted to convert into the cropped
asset's local coordinates, which is the space any downstream renderer will
actually use.

### 6. Verify structurally

```python
su.verify(output_path, expected_component_count=len(keep_labels))
```

Confirms the saved file actually has the number of pieces you intended —
catches accidental merges, stray fragments from a sloppy erase boundary,
or a crop that clipped part of the artwork. Don't skip this because the
build step "should have worked" — verify what actually got written to disk.

### 7. Measure placement constants (only if there's text/content to nest)

If the icon has hollow interior areas meant to hold dynamic text:

- **Text centre position**: if the source had real baked-in text you
  erased, use its actual centroid (`su.component_centroid` on the
  erased labels, working in *original* coordinates, then subtract the
  crop `offset`) as ground truth — this is more accurate than inferring
  a hollow's centroid, because it's literally where a human placed it.
  If there was no baked-in text (you're deriving a hollow icon from
  scratch), use `component_centroid` on the hollow's own background
  region instead, and prefer the pixel centroid over the bounding-box
  midpoint whenever the hollow isn't a simple rectangle (see
  `references/centroid-vs-bbox.md`).
- **Available width**: `su.measure_gap()` for a straight gap between two
  strokes (e.g. between walls), or the x-range from `su.trace_band()`
  where the gap stays consistently large for a curved band. Apply a
  safety margin (~10%) when converting to a max-width constraint for
  auto-fitting text later — see `references/deriving-placement-constants.md`.
- **Curve** (if text should follow a curved element like a ribbon):
  `su.trace_band()` then `su.fit_curve()`. **Check `residual_std` before
  trusting the fit.** Under ~1px means clean; tens of px means your
  x_range still includes a non-smooth region (a folded/flared end) —
  look at `trace_band`'s raw output, find where the `mid` column stops
  changing smoothly, and narrow the range.

Full worked example, including how these measurements become renderer-
specific constants (with the actual bin_sticker.py case as a concrete
reference): see `references/deriving-placement-constants.md`.

## Common mistakes (from doing this the first time)

- **Trusting the alpha channel's "opaque" vs "transparent" split as the
  ink/background separator without checking.** A finished mockup often has
  alpha ≈254 for the card and ≈255 for the ink — a 1-unit difference that
  looks like "both fully opaque" and tells you nothing. Luminance is what
  actually separates them.
- **Fitting a curve over the component's full bounding-box width.** Folded
  ribbon ends and other flared shapes break the smooth-curve assumption
  right at the edges. Always inspect `trace_band`'s output before deciding
  the fit range — don't assume the full width is usable.
- **Measuring a hollow's available width as its ink component's outer
  bounding-box span.** That's the outside edge of the strokes, not the
  clear space between them. Use `measure_gap`, which finds the actual
  empty span between ink segments.
- **Forgetting the crop offset.** Every position measured before
  `crop_to_content()` is in the wrong coordinate system for the saved
  asset unless you subtract the offset.
- **Forgetting to invalidate any downstream cache.** If whatever consumes
  this asset caches derived/recoloured versions on disk (e.g. one
  recoloured PNG per accent colour), replacing the master source image
  without clearing that cache means stale derived files silently keep
  getting served. Not this skill's job to fix, but check for it.
