# eBay Launch Plan — Summary

*Snapshot of decisions made and open items, covering Listing A (solo designs) and Listing B (animal families). Companion to `First-eBay-Listing-Checklist.md` and `eBay-Competitor-Research.md`.*

---

## ✅ Decided

**Product structure**
- Listing A: 13 solo designs, one listing, **Design** variation. Designs grouped into 3 style clusters (Wreaths & Florals / Classic & Formal / Minimal & Modern) for main image and description organisation — not split into separate listings at launch.
- Listing B: Animal Family packs (Duck/Dog/Cat), separate listing, **Species** variation. Fixed set of 4 different scenes per family (not buyer-configurable).
- Both listings adding **Size** as a second variation attribute: Small (4 stickers) / Medium (2 stickers) / Large (1 sticker) — fixed pairing, not independent size+quantity dropdowns like some competitors use.
- Base size stays 140×100mm (deliberate — 6 of 7 logged competitors use 150×100mm, flagged once, decision made to stay differentiated).

**Pricing & shipping**
- Small (4-pack) target price: **£3.50–4**, contribution before overhead ≈£0.51–0.93/pack.
- Free shipping built into item price (not charged separately) — matches all 7 competitors, gets a Cassini ranking boost, fees calculated on total price either way so no fee difference.
- Multi-order discount planned via eBay's native **Order Size Discount** (Seller Hub → Marketing → Promotions), percentage to be set once real combined-pack weights are confirmed.
- Dispatch: 1 business day (same-day production, next-day ship), Sunday/bank holidays excluded from handling time.
- Default shipping: Royal Mail 2nd Class Letter, untracked, absorbed into price. Tracked 48 offered as a paid buyer-funded upgrade.

**Listing mechanics**
- Single listing per product line beats splitting — backed by real evidence (28% conversion-rate difference cited by one seller; eBay's algorithm rewards consolidated history).
- ~~Personalisation fallback: if the box is left blank, default to the buyer's checkout delivery address~~ — **dropped (Sep 2026)**. Reviewer feedback was split (2 of 3 said keep with safeguards, 1 said always message the buyer instead, given a wrong personalised item is unusable and unreturnable). Decision: no automatic fallback for now — message the buyer if the box is left blank. Revisit once there's real order volume to see how often this actually happens.
- eBay's per-variation pricing supports the Size tiers without needing separate listings.
- Revisit whether to split any design cluster into its own listing **4–8 weeks post-launch**, once ~20–30 sales exist, using Seller Hub Traffic reports (own-listing data only, not marketplace-wide).

**Tools**
- eBay's free Listings Assistant for a first-draft title/description/item specifics.
- Canva (free tier) for image composites, size-comparison graphics, personalisation explainer.
- Photopea (free) for perspective-warp compositing and Clone Stamp/Spot Healing touch-ups.
- eBay's native AI background-enhancement tool (iOS) for flat-lay clean-up.
- 3Dsellers / Frooition — cross-sell template tools, worth revisiting once both listings are live and can cross-promote each other; not needed at launch.
- Rejected: Kittl (wrong product category), Zeely.ai (billing complaint red flags, wrong use case).

**Competitor research**
- 7 competitors logged in `eBay-Competitor-Research.md` (C1–C7), images saved and named consistently in `/competitor-images/ebay/`. File uploaded to project knowledge.

---

## ⚠️ Still open / missing

**Sizing & pricing gaps**
- [ ] Exact dimensions for Medium and Large sizes not yet set.
- [ ] Whether "Large" means a physically bigger sticker, or the same size with just 1 in the pack — asked, not yet answered.
- [ ] Medium and Large prices not yet calculated — fixed costs (packaging, card, postage) don't scale down linearly with fewer units, needs real cost-sheet work once dimensions are set.
- [ ] Multi-order discount % — needs real combined-pack weight check (does 2× Small together stay under 100g, or tip into Large Letter?) before setting a number.
- [ ] Packaging final decision (envelope+card vs box) and a real packed weight on a kitchen scale — still outstanding from the original launch checklist.

**Production & photos**
- [ ] Sticker placement on the black bin still needs adjusting (currently leaves Merton/Sutton logo partially visible) — reposition physically before next reshoot rather than repeatedly editing leftovers.
- [ ] Real bin hero shot not yet finalised — in progress.
- [ ] Remaining 12 of 13 solo designs not yet photographed (flat/hand shots) — only Central Avenue Arrow Circlet done and approved.
- [ ] Overhead phone stand not yet purchased (recommended fix for angle/perspective issues).
- [ ] Privacy: confirm all final listing images are cropped tight to the bin, with no house front, door, or doorbell camera in frame.

**Account & operations**
- [ ] HMRC sole trader registration — status not confirmed.
- [ ] eBay business seller account registration — status not confirmed.
- [ ] Seller Hub Business Policies (postage/returns/payment) — not confirmed set up.
- [ ] Personalisation mechanism — eBay native Personalisation field vs. checkout message — not finally confirmed.
- [ ] Certificate of Posting habit — recommended, not yet confirmed as a routine.

**Listing content**
- [ ] Full description copy not yet finalised for either listing — needs updating for: Size variation, sharper "prevent bin mix-ups/theft" hook (from C7's real reviews), branded header graphic, personalisation fallback wording, application instructions.
- [ ] Literal item-specific field values — to be filled once eBay's actual category picker is open in front of you (only field *names* are usable in advance).
- [ ] Title wording needs revisiting now "Pack of 4" no longer applies to all size options.

---

---

## 🔍 External review findings (3 agents, cross-checked Sept 2026)

**Confirmed correct — action needed:**
- [x] ~~**20% VAT applies to all eBay business-seller fees**~~ — **applied Sep 2026** via Claude in Excel to the "Ebay costs" sheet (new VAT-rate cell, all fee formulas updated, Break-Even Calculator recalculates live from it). Real corrected figures:
  - **£4.00, Letter postage: £0.60 profit/pack before overhead** (was £0.80 pre-VAT-fix)
  - **£4.00, Large Letter postage: −£0.04 (a LOSS before overhead)** — this is new and critical: at Large Letter, £4.00 no longer clears even direct costs
  - £3.50, Letter: £0.21 profit/pack. £3.50, Large Letter: −£0.43 (a loss)
  - **Test 6 (confirming Letter vs Large Letter postage) is now the highest-priority open item in the whole project** — the difference between a viable and a loss-making price point hinges on it.
- [x] Medium (202×140mm, 2-pack, £1.59/sticker) and Large (280×200mm, 1-pack, £3.19/sticker) dimensions and costs **already exist in the Business Plan §12** — this file's earlier "not yet set" note was stale, now corrected above.
- [ ] **New-seller selling limits (~10 items/$500/month) are real and confirmed via eBay's own help pages.** Whether each variation counts separately against this (would affect the 48-variation plan) is not independently confirmed — check Seller Hub directly once the account exists, before assuming all variations can launch simultaneously.

**Contested — needs a deliberate decision, not fact-checking:**
- Personalisation fallback (blank → delivery address): 2 of 3 reviewers say keep with safeguards, 1 says drop entirely in favour of always messaging the buyer. Real trade-off between dispatch speed and personalisation-error risk.
- Splitting Listing A into 3 style-based listings: 1 of 3 recommends this now; 2 of 3 plus our own prior research favour staying consolidated at launch.

**Worth checking before building further:**
- [ ] Canva text-overlay photo composites may violate eBay's Picture Policy (no added text/artwork on listing images) — read the policy directly before finalising the photo plan.
- [ ] Order Size Discount may require a paid Shop subscription; Bundle Discounts may be the correct no-Shop tool — verify in Seller Hub.
- [ ] HMRC registration trigger is the £1,000 trading allowance, not the £90k VAT threshold — separate rules, don't conflate them.
- [ ] Choose the eBay category deliberately before finalising price — it sets the fee rate, the per-order fee band, and whether the Personalisation field is available at all.

**Noted for calibration:** the "28% conversion" and "free postage = Cassini boost" claims used earlier in this plan were flagged by reviewers as weaker-sourced than presented (single-seller anecdote; overstated mechanism). The underlying recommendations (consolidate listings, keep free postage) still hold for other, better-supported reasons.

---

*Update this file as items get resolved — it's meant to be the one place that shows the current state at a glance.*
