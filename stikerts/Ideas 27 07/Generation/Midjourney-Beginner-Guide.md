# Midjourney Guide for Kerbside Craft Co. — Beginner Walkthrough

*Written for someone with no Midjourney account and no prior experience. Covers signing up, generating your first image, understanding the prompt format used in this project, and the full prompt for P02. Checked July 2026.*

---

## 1. What Midjourney is, and why we're using it

Midjourney is an AI image generator: you type a text description ("a prompt"), and it generates 4 image options for you to choose from. We're using it to create the **artwork only** — icons, illustrations, decorative elements — never the personalised text (house number, street name). That text is always added afterwards by the Python script, so it's guaranteed accurate. Midjourney's job is just to make the art look better than the hand-drawn vector version.

---

## 2. Signing up (no Discord needed)

Midjourney used to require a Discord account. **It doesn't anymore** — there's a proper website now.

1. Go to **midjourney.com**
2. Click **Sign Up**
3. Choose **Continue with Google** (simplest option — use your existing Google/Gmail account, no new password to remember)
4. You'll land on the Midjourney web app

**New accounts get 25 free image generations** to try it out before paying anything.

### Paying for it (once you're past the free trial)
- Click your account icon → **Subscribe**
- Choose the **Basic plan** — $10/month (or $96/year, cheaper per month if paid annually)
- This includes full **commercial usage rights** for a business under $1 million/year revenue, which comfortably covers this project — confirmed in our earlier research, still accurate
- You don't need Standard or Pro — those are for much higher usage volumes than a handful of sticker icons

---

## 3. Generating your first image

Once logged in:

1. Look at the **left-hand sidebar** — click **Create**
2. At the top of the page you'll see a text box — this is the **prompt bar**. Click into it.
3. **Paste the full prompt** (see §5 below for P02's exact prompt) and press **Enter**
4. Wait about 30–60 seconds. Midjourney will generate **4 versions** of the image, shown as a 2×2 grid
5. Click on any one of the 4 to see it larger
6. If you like one, hover over it and click **Upscale** (sometimes shown as an arrow icon) to get a higher-resolution version
7. Right-click the final image → **Save Image As...** to download it to your computer

**If none of the 4 look right:** click the 🔄 **Rerun** button (sometimes shown as circular arrows) to generate 4 new versions with the same prompt, or tweak the wording slightly and submit again. This is normal — expect to run a prompt 2–4 times before getting something you like.

---

## 4. Understanding the prompt format

Every prompt in this project follows the same pattern:

```
[description of the subject and style] --style raw --stylize 50
```

- **The description** — plain English, describing exactly what you want. Be specific about colour, style, and background.
- **`--style raw`** — tells Midjourney to follow your description closely rather than adding its own artistic embellishment. Important for icon-style work where we need a clean, predictable result.
- **`--stylize 50`** — a low "creativity" setting (Midjourney's default is much higher). Keeps the output closer to a clean icon than an artistic interpretation.

You don't need to understand these deeply — just include them at the end of every prompt in this project, exactly as written, so results stay consistent.

---

## 5. Full prompt for P02

Per our last conversation: **single flat colour** (not shaded/multi-tone), so the artwork can be recoloured later in the Python script rather than locking in a colour now.

```
A minimalist house icon flanked by two small flowers, with a ribbon banner shape below for text, solid single-colour flat silhouette, black on plain white background, clean vector line art, no shading, no gradients, isolated design element, no text --style raw --stylize 50
```

**Paste that exactly as one block into the prompt bar.**

What to expect: a black silhouette (house + flowers + banner shape) on a white background, no colour, no shading — this is intentional, so it stays recolourable. If Midjourney gives you a shaded/coloured version despite the prompt, add `flat 2D icon, single fill colour only` to the end of the description and try again.

---

## 6. After Midjourney: getting it ready for the script

Once you've downloaded an image you're happy with:

1. **Crop it** to just the house+flowers+banner design if there's extra white space around it (any basic photo app or even Preview/Paint will do — a tight crop isn't essential but helps)
2. Go to **vectorizer.ai**
3. Upload your downloaded image
4. Vectorizer.ai will trace it and give you a clean file with a **transparent background** — this is the step that turns "black shape on a white background" into "black shape on nothing," which is what the script needs
5. Download the result as a **PNG** (not SVG — remember, the script places raster images, and a ~2000px PNG is already sharper than this sticker size needs)

Send me that final PNG and I'll drop it into the script and generate the WC/CV comparison, using the recolour tool we already built and tested.

---

## 7. Quick troubleshooting

| Problem | Fix |
|---|---|
| Image comes out with colour/shading despite the prompt | Add "flat 2D icon, single fill colour only, no shading" to the prompt and rerun |
| Image includes a shadow or background texture | Add "plain flat white background, no shadow" to the prompt |
| Proportions look off (e.g. house too small vs. flowers) | Add a note like "house icon larger and centred, flowers smaller" and rerun |
| Can't find the Upscale button | Click directly on one of the 4 grid images first — the option appears once you're viewing a single image, not the grid |
| Ran out of the 25 free generations | You'll be prompted to subscribe — Basic plan ($10/month) as described in §2 |

---

## 8. Full-layout mockup prompt (composition reference only — never final art)

Separate from the icon-only prompts above, this generates a **whole sticker layout** with placeholder text baked in, purely to explore composition — where the number sits relative to the icon, how big the banner should be, whether the flowers feel too close or too far out. **The text in this image will be blurry/wrong and is never used as final art or copied into the script** — only the layout/proportions are useful. The real text is always added afterwards in Python, exactly as described in §1.

**Prompt for a P02-style full layout:**

```
A house address sticker design for a wheelie bin, featuring a minimalist house icon flanked by two small flowers, with the house number '36' displayed prominently near the roofline, and a ribbon banner below containing the street name 'GROVE STREET' in bold caps text, clean flat vector illustration, single accent colour with black text, on a plain white card with a thin border, portrait orientation --style raw --stylize 50 --ar 5:7
```

Note the `--ar 5:7` — that matches your 100×140mm sticker's actual proportions, so the mockup shows a realistic layout rather than a square or landscape composition that wouldn't transfer.

**What to do with the result:** don't try to extract the text or trace it directly. Instead, look at where things sit — icon size relative to the card, gap between icon and banner, banner width vs. card width — and describe that back to me (or just send the image) so I can translate it into exact reportlab coordinates with your real font and guaranteed-correct text.

---

*Once P02 is done and you're happy with the WC/CV comparison, the same process (steps 3, 5, and 6) applies to any other icon from the idea board — just swap in a new prompt each time.*
