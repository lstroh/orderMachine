# Lovart AI Guide for Kerbside Craft Co. — Beginner Walkthrough

*Written for someone with no Lovart account and no prior experience, to run alongside `Midjourney-Beginner-Guide.md` for a side-by-side comparison. Checked July 2026.*

---

## 1. What Lovart AI is, and why you're trying it

Lovart AI calls itself an **"AI Design Agent"** rather than a plain image generator. The practical difference from Midjourney: instead of typing a prompt and getting 4 static images, you work in a conversational canvas — you describe what you want, it generates a result, and you can then say things like "make this flat, single colour" or "make the flowers smaller" and it edits the existing design in place, rather than you rewriting the whole prompt from scratch.

It's built on top of several existing AI models rather than one of its own, and its marketed strengths lean toward structured business assets (price lists, menus, brand kits, packaging) more than single isolated icons — which is exactly the gap we're testing with this comparison.

---

## 2. Signing up

1. Go to **lovart.ai**
2. Sign up for an account (free tier available)
3. You'll receive a starter pool of **free credits** to try the platform before paying anything

### Important: check commercial rights before using anything for real
Commercial usage rights on Lovart are typically tied to **paid tiers (Pro and above)**, not the free tier — the free credits are positioned as a trial. **Before using any Lovart-generated asset in an actual product, check Lovart's own Terms of Use / pricing page to confirm what your specific plan covers.** Treat this first round as an evaluation of quality and workflow, not as sourcing a production-ready asset, unless you've confirmed otherwise.

---

## 3. Generating your first image

Lovart works more like a chat conversation than a one-shot prompt:

1. Start a **New Project** (or use a template, if offered — skip templates for this task, you want a blank canvas)
2. Find the prompt/chat box and type your description in plain English (see §5 for the exact P02 prompt)
3. Press Enter and wait for Lovart to generate a result on the canvas
4. **This is the key difference from Midjourney:** if it's not right, don't rewrite the whole prompt — click on the result and type a follow-up instruction in the same conversation, e.g. *"make this flat, single colour only, no shading"*. Lovart should adjust the existing design rather than starting over.
5. Once you're happy, use the **Download/Export** option (Lovart supports PNG/JPG/SVG/PSD export)

---

## 4. Understanding the prompt style

Unlike Midjourney's parameter-based format (`--style raw --stylize 50`), Lovart is designed for **natural conversational language** — describe what you want, then refine through follow-up messages rather than parameters. There's no direct equivalent of Midjourney's `--style raw` flag; instead, you get flatness/simplicity by explicitly asking for it in plain English, and by correcting it in the follow-up turn if the first result comes back shaded or overly "designed."

---

## 5. Prompt for P02 (same brief as the Midjourney test, for a fair comparison)

**Initial prompt:**

```
Design a minimalist house icon flanked by two small flowers, with a ribbon banner shape below for text. I need this as a solid single-colour flat silhouette — black only, no shading, no gradients, no colour variation. Plain white background. Clean vector-style line art, isolated icon, no text anywhere in the image.
```

**If the result comes back shaded, multi-coloured, or "designed" rather than flat (likely, given Lovart's marketed strengths lean elsewhere), send this as a follow-up in the same conversation:**

```
Make this flat, single colour only — solid black, no shading, no gradients, no highlights. Keep the same composition.
```

This follow-up step is itself part of the test — it's exactly the kind of real-time refinement Lovart claims to be good at, so it's a fair way to see whether that claim holds up in practice.

---

## 6. After Lovart: getting it ready for the script

Same as the Midjourney pipeline:

1. Download the final image
2. Crop if needed
3. Upload to **vectorizer.ai** to get a transparent-background version
4. Send the resulting PNG back for the WC/CV comparison and the recolour test

---

## 7. Quick troubleshooting

| Problem | Fix |
|---|---|
| Result is shaded/multi-colour despite the prompt | Use the follow-up refinement message from §5 rather than rewriting the whole prompt |
| Can't find the chat/prompt box | Look for a "New Project" or blank canvas option first — templates may hide the free-form prompt entry |
| Unsure if commercial rights apply | Check Lovart's Terms of Use / pricing page directly — don't assume the free tier includes them |
| Free credits run out mid-comparison | That's useful data on its own — note how many attempts it took to get a usable flat result, since that's part of what you're comparing |

---

## 8. Midjourney vs. Lovart — comparison

| | **Midjourney** | **Lovart AI** |
|---|---|---|
| **Interaction style** | Type a prompt, get 4 static image options, re-roll or rewrite to iterate | Conversational canvas — describe, then refine the same result through follow-up messages |
| **Best suited for** | Single isolated icons/illustrations, precise style control via parameters (`--style raw`, `--stylize`) | Structured multi-asset business design — price lists, menus, brand kits, packaging, campaigns |
| **Getting flat single-colour output** | Reliable, well-established via `--style raw --stylize 50` | Untested for this specific narrow use case — may need explicit follow-up correction, marketed strengths lean toward richer "designed" results |
| **Pricing** | Basic plan $10/month, flat rate, commercial rights included under $1M revenue | Free tier for trial; commercial rights typically require a paid tier — check current terms |
| **Cost model** | Fixed monthly subscription | Credit-based consumption — can be less predictable |
| **Track record for this project** | Already tested and working (icon-set workflow, recolour pipeline built around its output) | New, unproven for flat vector icon work specifically |
| **Reported downsides** | None significant found for this use case | Some user reports of billing/support issues (as of late 2025 reviews); steep learning curve for the full "agent" workflow |

**Bottom line going into the test:** Midjourney has the established track record for exactly this task (flat single-colour icons feeding into the recolour pipeline). Lovart is worth the free-credit trial specifically to see whether its conversational refinement can match that flatness requirement with less prompt-engineering effort — but going in, the prior expectation is that Midjourney remains the better fit for this narrow job, even if Lovart turns out to be genuinely useful for other future assets (shop banners, price lists, packaging).

---

*Once you've generated a P02 result from both tools, send both files back — the recolour test and WC/CV comparison will make the difference between them concrete rather than a guess.*
