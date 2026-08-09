# Update — Platform Selling Fees

*Update set 3 of 4 · Schema referenced here is defined in `02-Update-Data-Model.md`. Self-contained — no need to reference the original planning files.*

---

## 1. What this adds

Visibility into what eBay and Etsy actually take out of every sale, per product — not manual entry, since both platforms expose this via API:

- **eBay:** the Finances API returns the actual per-order fee breakdown (final value fee, per-order fixed fee, international fee where applicable, etc.) as part of its transaction data.
- **Etsy:** the Open API v3's Payments and Shop Payment Account Ledger endpoints are read-only and return fee, shipping, and tax details tied to each payment/receipt — plus, notably, the ledger endpoint returns *all* debits/credits to the payment account, including the per-listing fee, which isn't tied to any specific sale.

Amazon is deliberately out of scope for this update — stays eBay/Etsy-only until Amazon is actually built as a channel, per your answer.

## 2. Two kinds of fee data

### Order-linked fees (the normal case)
Fees genuinely tied to a specific sale — eBay's final value fee, Etsy's transaction fee and payment processing fee. Synced into `order_platform_fees`, one row per fee component per order.

### Non-order-linked fees (Etsy's listing fee)
Etsy charges £0.16 per listing whether or not it sells — on initial listing, on 4-month renewal, and again per extra unit sold on a multi-quantity listing. This doesn't fit the "per-order fee" model, so per your answer it's tracked separately in `recurring_platform_expenses`, dated by when it was incurred rather than linked to an order.

**Both come from the same sync process** — Etsy's ledger endpoint returns all entries regardless of type; the sync logic classifies each entry by whether it's linked to a receipt/order ID (→ `order_platform_fees`) or not (→ `recurring_platform_expenses`).

## 3. Sync process

A **new, separate cron job** (`som_sync_platform_fees`), not folded into the existing order-sync cron, because fee/transaction data can lag behind order creation — eBay's own API documentation notes there can be a delay before a payment's transactions become queryable after the sale itself is visible via the Fulfillment API.

- Polls eBay's Finances API and Etsy's Ledger API on a schedule (suggest every 30-60 min — less urgent than order sync itself, since fee data is retrospective, not needed to action an order).
- Matches each fee/transaction entry to an existing `orders` row via the order/receipt ID.
- Writes matched entries to `order_platform_fees`; writes unmatched Etsy entries (listing fees) to `recurring_platform_expenses`.
- Idempotent — safe to re-run without duplicating rows (same pattern as order sync's de-duplication).

## 4. Estimated fees, for pricing before real data exists

Per your answer: a manually-editable estimated fee rate per channel, broken into components (not one blended number), stored in `channel_fee_estimates` and seeded with the figures already worked out in your `eBay-Marketing-Guide.md` / `Etsy-Marketing-Guide.md` (see `02-Update-Data-Model.md` for the actual seed values). This feeds the Product Costing view's "resulting profit" figure for a product before any real orders have synced in for it.

## 5. Estimate vs. actual comparison

Once real orders exist for a product, the Product Costing view shows, per channel:

- **Estimated fee %** (from `channel_fee_estimates`, blended into an effective rate for comparison purposes)
- **Actual fee %** (computed from `order_platform_fees` ÷ order revenue, across however many real orders exist for that product on that channel — worth showing the order count alongside, e.g. "Actual: 14.2% (n=8 orders)", so a low sample size is visible rather than misleadingly precise)
- **Variance** — the difference, flagged visually if it's meaningfully off (e.g. actual running noticeably higher than estimated, which would mean margin is worse than planned)

This directly extends the profit calculation from the existing Product Costing view: `profit = effective_sold_price − material_cost − platform_fees`, where `platform_fees` uses actual data once available, falling back to the estimate otherwise — so the same profit figure smoothly transitions from estimate-based to reality-based as real sales accumulate, without you needing to manually switch anything.

## 6. UI requirements

| Page | Purpose |
|---|---|
| **Channel Fee Estimates (settings)** | Per channel, editable fee-component rows (§4), pre-seeded with the guide figures |
| **Product Costing (enhanced)** | Existing page gains: actual vs. estimated fee % per channel with variance (§5), and platform fees folded into the profit calculation |
| **Order detail (enhanced)** | Shows the actual fee breakdown for that order once synced, itemized by component |
| **Recurring Platform Expenses** | Simple list of non-order-linked charges (Etsy listing fees), dated, filterable by listing |

## 7. Open items to resolve before/during build

1. **eBay's per-order fee tiering:** the seeded estimate (£0.30) is a simplification — the actual fee varies by order value band (per the existing marketing guide, £0.30 under £10, £0.40 at/above). Confirm whether the estimate config should support tiered fixed fees, or whether a single flat estimate is good enough for the *estimate* (since actuals will be exact once real data syncs regardless).
2. **Blending fee components into one "effective rate"** for the estimate-vs-actual comparison (§5) needs a concrete formula — e.g. applying each component's rate/fixed-amount against a representative order value. Worth pinning down against a real example order once you're building this, rather than guessing at an abstract formula now.
3. **Optional components** (Promoted Listings, Offsite Ads) aren't always active — should the blended estimate include them by default (conservative, assumes ads are on) or exclude them (only mandatory fees, ads modelled separately)? Recommend excluding by default, since ad spend is a choice, not a mandatory cost of selling — flag if you'd rather include it.
4. **Currency:** eBay/Etsy transactions may occasionally be in USD (e.g. Etsy's fee figures are USD-denominated in places) — confirm whether conversion to GBP is needed at sync time, or whether this is a non-issue given a UK-based shop with GBP payouts.
