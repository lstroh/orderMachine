# Skills Reference — Kerbside Craft Co.

*What each available skill does, and when it actually fires. Skills are
loaded automatically based on what you ask for — you never need to name
one by name — but knowing they exist helps you phrase requests so the
right one triggers, and helps you spot when I *should* have used one but
didn't.*

---

## Project-specific skills (built for this business)

These five live in the assistant's user-skills folder and only make
sense in the context of this project. Four are about the two galleries;
one (`cursor-prompt-generator`) is **not** for this project at all.

### `gallery-idea-intake`
**Use for:** adding a new *inspiration/research* idea to the 66-entry
idea board.
**Triggers on:** sharing a design image and saying "add this to the
gallery," "is this doable," "new idea for the board," "what would it
take to make this" — or uploading a competitor/Pinterest screenshot with
no further instruction. Also fires for "how many ideas do we have" /
"check the gallery."
**Touches:** `bin_sticker_idea_gallery.html` only (the .md companion
isn't this skill's job — see `gallery-mockup-prompt` below for how that
stays in sync).
**Won't do:** write actual code (`_style_*` functions in `bin_sticker.py`)
— that's a separate, code-focused task this skill deliberately stays out
of.

### `gallery-mockup-prompt`
**Use for:** generating a Midjourney/Lovart prompt pair to visualise a
gallery idea's *composition* (icon placement, spacing, proportions)
before committing to real code — with placeholder text that's explicitly
thrown away, never traced into the final design.
**Triggers on:** "mockup prompt," "layout prompt," "full-layout" image
prompt, or wanting to see composition/proportions for a specific P-number
before finalising placement.
**Touches:** both `bin_sticker_idea_gallery.html` *and*
`bin_sticker_idea_board_data.md` together (extended to cover the .md
this session — previously HTML-only, which let the two drift apart).
**Won't do:** the *icon-only* art-generation prompt (a different, simpler
style meant to produce final reusable artwork, not a composition
reference) — that's a different task entirely.

### `icon-silhouette-extraction`
**Use for:** turning a finished-looking mockup image (AI-generated
product photo, a competitor's rendered design) into a clean, transparent,
recolourable icon asset — especially removing baked-in placeholder text,
or deriving placement constants (position, curve, hollow width) for
nesting real text inside it.
**Triggers on:** "turn this into an icon," "extract the icon from this,"
"remove the text from this image," "make this recolourable," or any
"derive placement constants from this image" request.
**Includes:** `check_crop_clipping()` and `check_symmetry_assumption()`
— both added *because* of real mistakes made this session (a flourish
silently missing 3/4 of its curl detail; 3 of 4 border corners built by
wrongly assuming rotational symmetry). Worth knowing these exist before
extracting a new asset by hand.

### `products-gallery-add`
**Use for:** cataloguing a *finished, working* design — one that already
has a real `_style_*` function in `bin_sticker.py` — as a numbered
product (`D01`, `D02`, ...) with an auto-rendered real proof thumbnail.
**Triggers on:** saying a design is "done"/"finished"/"ready," asking to
"add it to the products gallery," or asking for an "internal ID" for
something just built.
**Touches:** `bin_sticker_products_gallery.html` and
`bin_sticker_products_gallery_data.md` together, plus (as of this
session) `bin_sticker.py`'s `STYLE_PRODUCT_ID`/`STYLE_LABELS` should be
kept in sync by hand if this skill doesn't yet touch code directly.
**Won't do:** touch the idea board — different schema, different ID
space (`P##` vs `D0N`), a design only gets a `D0N` once real code exists
for it.

### `cursor-prompt-generator` — **not for this project**
This one exists in the same skills folder but generates implementation
prompts for a completely different codebase (a WordPress booking plugin
called Bookit). It won't fire for anything sticker-business-related, and
you can ignore it entirely here.

---

## General-purpose skills (relevant when they come up)

These aren't specific to this business, but are likely to fire during
normal work on it:

| Skill | Fires for |
|---|---|
| `xlsx` | Any spreadsheet work — e.g. `StickerBinStickersCosts_v2.xlsx` edits, cost/pricing models with live formulas |
| `pdf` | Creating or filling PDFs — e.g. `thankyou_cards_sample_sheet.pdf`-style outputs, watermarking, merging |
| `pdf-reading` | Reading/extracting content from an uploaded PDF that isn't already visible in context |
| `docx` | Producing a Word document — only if you explicitly want a `.docx`, not for ordinary written answers |
| `pptx` | Slide decks/presentations, if this business ever needs pitch materials |
| `frontend-design` | Building or restyling actual UI (not really used here yet — the two galleries are simple static HTML, not built via this skill) |
| `file-reading` | Routing an uploaded file to the right reading approach when its content isn't already visible |
| `product-self-knowledge` | Any question about Claude/Anthropic's own products, pricing, or features — not sticker-business-related, but keeps answers about *this assistant* accurate rather than guessed |

---

## Meta: `skill-creator`

Not project-specific, but worth knowing about: this is the tool used to
actually build `products-gallery-add` and extend `gallery-mockup-prompt`
this session. If a new recurring pattern shows up in this project later
(a 4th gallery, a new file format to sync, etc.), this is what turns
"we keep doing this by hand" into a reusable skill the same way these
were built.

---

## Other example skills that exist but don't apply here

The environment ships with a large set of generic personal-assistant
example skills — grocery shopping, expense filing, meal delivery,
prescription refills, event planning, financial calculators, and similar.
None of these are relevant to running a sticker business and they're
omitted from this doc rather than listed individually; they won't fire
unless you explicitly ask for something in their domain (e.g. "help me
file an expense report").
