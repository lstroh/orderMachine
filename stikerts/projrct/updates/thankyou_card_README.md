# Thank-you card generator — `thankyou_card.py`

Makes print-ready thank-you cards for Doorstep Studio bin sticker orders, as a PDF.
Rewritten Oct 2026: **one card for every order and channel.** No buyer name, no
discount code and no review request, so the same card is safe on eBay, Etsy,
Amazon and the own website. The personal touch (the customer's name) is on the
personal wrap instead (`sticker_wrap.py`).

## What's on the card

- **Front:** a confetti scatter, "Thank you!" and the Doorstep Studio logo with the
  brand name.
- **Back:** "How to apply your sticker", six numbered steps written for the
  peel-tab back cut:
  1. Clean the bin with soapy water and dry it fully. Best above about 10°C.
  2. Bend the sticker at the back cut and peel off the narrow strip of backing.
  3. Line up that edge and press the exposed strip onto the bin.
  4. Pull the rest of the backing away slowly, smoothing the sticker down from the stuck edge as you go.
  5. Press firmly along all edges and corners.
  6. Leave it 3 days before washing or scrubbing. Ordinary rain is fine.
- **Footer:** `@doorstepstudiouk · Made by hand in the UK`.

Step 6 deliberately asks customers not to *wash or scrub* rather than to avoid
rain: they can't control the weather, and vinyl guidance treats deliberate water
contact, not ordinary rain, as the risk while the adhesive cures.

To change the wording, edit `APPLICATION_STEPS` near the top of the script, then
re-render and check that nothing wraps onto a single-word line or reaches the footer.

## Sizes (match the envelope)

| `size=` | Card | Envelope | Per A4 sheet | Layout |
|---|---|---|---|---|
| `"A6"` (default) | 105 × 148.5mm portrait | C6 | 4 (2 × 2) | Front on top, steps below |
| `"A5_landscape"` | 210 × 148.5mm | C5 | 2 (stacked) | Confetti left, steps right, plus a diagram of step 4 |
| `"A5"` | 148 × 210mm portrait | C5 | 1 (page = card) | Scaled-up A6 |
| `"A4"` | 210 × 297mm portrait | C4 | 1 (page = card) | Scaled-up A6 |

A5 landscape is the one to use for C5: two cards tile onto A4 exactly, so it's one
guillotine cut. Portrait A5 doesn't divide evenly into A4.

## How to call it

```python
from thankyou_card import render_sheet

order = {
    "channel": "ebay",            # kept for records; the card no longer varies by channel
    "paper": "white",             # "white" or "kraft" — ink colours only; load matching cardstock
    "confetti_color": "multi",    # "multi" or one of: blush, sage, dusty_blue, terracotta, lavender
    "brand_name": "Doorstep Studio",
    "handle": "doorstepstudiouk",
}
render_sheet([order] * 4, "cards_A6.pdf", size="A6")
render_sheet([order] * 2, "cards_A5.pdf", size="A5_landscape")
```

`confetti_color` also colours the step numbers and the diagram.

## Files it needs

```
thankyou_card.py
assets/icons/confetti_multi.png        (and confetti_<colour>.png for each accent)
assets/icons/doorstep_studio_mark.png
```

The confetti PNGs were made from a Midjourney render: 69 shapes separated and
recoloured with the icon-silhouette-extraction tools, pre-baked once per colour.

## Printing

- Print from the PDF at **100% / actual size**, on 250gsm white or kraft cardstock.
- The card background is never printed. The paper colour shows through, and `paper`
  only switches the ink colours so they stay legible on kraft.
- Thin cut guides and corner ticks are printed for the guillotine.

## Planned

- Hand-drawn Midjourney illustrations for each step may replace the vector
  diagram on the A5 landscape card (prompts in the chat history, Oct 2026).
