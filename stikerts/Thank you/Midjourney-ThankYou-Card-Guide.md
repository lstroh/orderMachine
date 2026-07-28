# Midjourney Guide for Kerbside Craft Co. — Thank-You Card Flower Motif

*Companion to Midjourney-Beginner-Guide.md — same account, same workflow, same prompt format. This one covers the flower icon used on the thank-you card (see thankyou_card.py). Checked July 2026.*

---

## 1. What we're generating, and why

The thank-you card currently uses a hand-coded flower (4 ellipse petals + a centre dot, drawn directly in Python via reportlab) — functional, but plain. The goal here is the same one used for the bin sticker icons: get Midjourney to design a nicer flower **once**, as a flat single-colour silhouette, then recolour that same design in code for each colour variant (blush, sage, dusty blue, terracotta, lavender — see `FLOWER_COLORS` in the script).

**Important — same rule as the sticker guide:** Midjourney generates the artwork only, never the personalised text (buyer name, product, etc.). That's still added by the Python script afterwards, same as always.

You already have a Midjourney account and subscription from the sticker workflow — no need to sign up again, just go to **midjourney.com** and log in.

---

## 2. Understanding the prompt format

Same pattern as every other prompt in this project:

```
[description of the subject and style] --style raw --stylize 50
```

- **`--style raw`** — follows the description closely rather than adding artistic embellishment
- **`--stylize 50`** — low creativity setting, keeps the result close to a clean icon rather than a loose artistic interpretation

Same reasoning as before: this keeps results predictable and recolourable, which matters more here than looking "arty."

---

## 3. Full prompt for the thank-you card flower

Single flat colour (not shaded/multi-tone), same reasoning as P02 — so it can be recoloured in the script per order rather than locking in one colour now:

```
A simple minimalist flat botanical flower icon, five rounded petals evenly spaced around a small circular centre, solid single-colour flat silhouette, black on plain white background, clean vector line art, no shading, no gradients, isolated design element, no text --style raw --stylize 50
```

**Paste that exactly as one block into the prompt bar.**

What to expect: a black flower silhouette (5 rounded petals + centre) on a white background, flat and unshaded — intentional, same as P02. If Midjourney gives you shading, gradients, or a photographic/watercolour look despite the prompt, add `flat 2D icon, single fill colour only` to the end and rerun.

**One design decision already made for you:** the prompt asks for 5 petals rather than the current 4, since a 5-petal shape reads more clearly as "flower" at small print size (a 4-petal shape can look like a generic cross/plus at 16mm). If you'd rather keep 4 petals to match the current look exactly, just change "five rounded petals" to "four rounded petals" in the prompt before running it.

---

## 4. Generating the image

Same steps as the sticker workflow:

1. **Create** in the left sidebar → paste the prompt above into the prompt bar → **Enter**
2. Wait for the 2×2 grid of 4 options
3. Click one to view it larger
4. **Upscale** the one you like
5. Right-click → **Save Image As...**

**If none of the 4 look right:** rerun for 4 new versions, or tweak the wording (e.g. "petals more rounded and plump" or "petals slightly pointed") and resubmit. Expect a couple of tries, same as with P02.

---

## 5. After Midjourney: getting it ready for the script

Same process as the sticker icons:

1. **Crop** to just the flower if there's extra white space around it
2. Go to **vectorizer.ai**
3. Upload your downloaded image
4. Download the result as a **PNG** (not SVG — same reasoning as before, the script places raster images and a ~2000px PNG is sharper than a 16mm-tall print motif will ever need)

Send me that final PNG and I'll wire it into `thankyou_card.py` in place of the current hand-drawn `draw_flower()` function, using the same recolour approach already built and tested for the sticker icons — so it still works with all five colour variants and both paper options without you needing to generate five separate images.

---

## 6. Quick troubleshooting

| Problem | Fix |
|---|---|
| Image comes out shaded, gradient, or photographic | Add "flat 2D icon, single fill colour only, no shading" to the prompt and rerun |
| Image includes a shadow, texture, or background pattern | Add "plain flat white background, no shadow" to the prompt |
| Flower looks lopsided or petals uneven | Add "perfectly symmetrical, evenly spaced petals" and rerun |
| Flower reads as a different shape at small size (e.g. looks like a star or pinwheel) | Try "rounded petal tips, soft botanical shape" instead of "pointed" |
| Can't find the Upscale button | Click into a single image first — the option only appears once you're viewing one image, not the 2×2 grid |

---

*Once you're happy with the flower and have the vectorized PNG, send it over and I'll update the script and regenerate a sample sheet so you can see all five colours + both papers with the new design before we finalise anything.*
