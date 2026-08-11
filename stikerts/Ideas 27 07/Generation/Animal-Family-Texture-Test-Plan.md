# Animal Family Texture Test Plan — Flat Silhouette vs. Fur Texture

*Prepared August 2026 · Goal: settle whether fine fur-texture detail survives
print + laminate + guillotine trim at actual card size, before committing to
one style across the rest of the duck family set (D06-D09) and any future
animal-family lines (dogs, cats, squirrels, etc.).*

---

## 1. Why this needs testing

The 4-scene duck family set currently has a style split:

| ID | Style key | Treatment |
|---|---|---|
| D06 | `duck_family_father` | Flat silhouette, solid fill |
| D07 | `duck_family_mother` | Flat silhouette, solid fill |
| D08 | `duck_family_playing1` | Fur-textured (spiky detail on backs) |
| D09 | `duck_family_playing2` | Fur-textured (spiky detail on backs) |

This wasn't a deliberate style decision — D08/D09 came from a Midjourney
batch where the textured options were picked over flatter alternatives in
the same batch, without a print test to confirm the texture holds up.

**The concern:** fur texture is made of many thin, closely-spaced spikes.
At actual card size these render small (the duckling-scene icons are
roughly 42-58mm tall on a 100×140mm card), and fine linework at that scale
is exactly where your existing Material Test Plan notes ink bleeding and
detail loss tend to show up first. Lamination adds another layer on top of
the ink, and the guillotine trim pass is a further chance for edge detail
to blur. Flat silhouette has none of this risk — no fine detail to lose.

**Why it's not just a paper decision:** your own competitor evidence
(P03, P14, P22 on the idea board — the pattern behind picking ducks in the
first place) is entirely flat silhouettes. That's proven demand. Fur
texture is a style choice made mid-generation, not something backed by a
bestselling comparable yet. A quick physical test settles this with
evidence rather than a guess either way.

---

## 2. Test setup

- [ ] Render actual-size print-ready proofs of **one flat design** (D06)
  and **one textured design** (D08 or D09) — see Prompt A below
- [ ] Print both using your actual Epson EcoTank settings for real orders
  (same paper/quality mode as your Material Test Plan uses)
- [ ] Laminate using your actual laminator/method
- [ ] Trim using your actual guillotine + corner punch workflow
- [ ] Once trimmed, inspect both samples:
  - Fine detail up close (hold at normal reading distance, ~30cm)
  - From typical viewing distance (a few metres — how someone walking
    past a bin would actually see it)
  - Check specifically: do the fur spikes stay crisp, or do they blur
    into a fuzzy/muddy grey edge? Any ink bleed at the spike tips?
    Any detail lost or filled-in after lamination?

---

## 3. Decision criteria

- **If the textured sample stays crisp at both distances:** fur texture is
  a genuinely viable option — the D06/D07 vs. D08/D09 style split is fine
  to keep (parents flat, kids textured is a defensible creative choice),
  or you could extend texture to D06/D07 for full-set consistency.
- **If the textured sample blurs/muddies, especially up close:** that
  settles it in flat's favour — plan to redo D08/D09 as flat silhouettes
  to match D06/D07, and default to flat for any future animal-family line
  (dogs, cats, squirrels) rather than repeating the same print risk.
- **If it's borderline (holds up at a distance but rough up close):**
  worth a judgement call on whether close-up handling (a customer holding
  their new sticker before applying it) matters enough to still choose
  flat, even if from-a-distance it reads fine either way.

---

## 4. Results log

| Date | Sample | Up-close (30cm) | Distance (few metres) | Verdict |
|---|---|---|---|---|
| | D06 (flat) | | | |
| | D08 or D09 (textured) | | | |

*(Add rows here as you test — no fixed schedule needed, this isn't a
multi-week durability test like the main Material Test Plan, just a
one-time print fidelity check.)*

---

## 5. Reusable prompts

Paste these back to me later, as-is or lightly edited, to move this test
along without re-explaining the context each time.

### Prompt A — Render the test proofs
> Render two actual-size, print-ready PDFs for the texture test: D06
> (`duck_family_father`) and D08 or D09 (your pick between the two
> textured scenes) — house_number "36", street_name "Grove Street",
> accent "charcoal", same as their existing proofs. I'll print these
> myself on the real material.

### Prompt B — Log results
> Here's what I found printing/laminating/trimming the two texture-test
> samples: [describe what you saw — crisp/blurry, up close and at
> distance, any ink bleed]. Update Animal-Family-Texture-Test-Plan.md's
> results log and give me your read on the decision criteria.

### Prompt C — If texture wins: bring D06/D07 up to match
> The texture test passed. Let's redo D06 and D07 with matching fur
> texture so the full duck family set is visually consistent. Give me a
> Midjourney prompt for a textured father-duck-and-duckling scene, same
> composition as the current D06, ready to regenerate.

### Prompt D — If flat wins: redo D08/D09 as flat
> The texture test failed / came back borderline in flat's favour. Let's
> redo D08 and D09 as flat silhouettes to match D06/D07. Give me
> Midjourney prompts for both "ducklings playing" scenes (determined
> march and energetic/tumbling) in flat silhouette style, same
> compositions as the current D08/D09, ready to regenerate.

### Prompt E — Applying the decision to a new animal line
> We've decided on [flat / textured] for the animal family sets based on
> the duck texture test. Let's start the [dogs/cats/squirrels/etc.] family
> using that same style from the first prompt, so we don't repeat this
> test for every new animal.

---

*This test is a one-time print-fidelity check, not a durability test —
it doesn't need repeating on a schedule the way the UV/submersion tests in
Bin-Sticker-Material-Test-Plan.md do. Once flat-vs-textured is decided
here, that decision applies to all future animal-family designs, not just
the duck set.*
