# Order Management System — High-Level Requirements Spec (v1.0)

*Prepared July 2026 · Status: SETTLED — ready for detailed design · Companion to Sticker-Business-Plan.md and Bin-Sticker-Shipping-Plan.md*

---

## 1. Problem statement

Orders currently arrive across up to four separate channels (eBay, Etsy, own WordPress/WooCommerce site, possibly Amazon), each with its own dashboard. There is no single place to see what's outstanding, what stage each order is at, or what personalisation details need actioning. Royal Mail Click & Drop, the production workflow (design → print → laminate → cut → package), and the thank-you card step are already defined elsewhere and are treated as downstream of this system, not rebuilt by it.

## 2. Goal

A single place to see every open order, across all connected channels, with enough detail to action it (personalisation text, product, shipping address) — replacing the need to check four dashboards separately.

## 3. Out of scope (v1)

- Replacing Click & Drop — label creation/postage stays a manual step in Click & Drop's own UI.
- Replacing the thank-you card generator — stays a separate script, possibly triggered *from* this system later, not rebuilt into it.
- New product/listing creation on a marketplace from scratch — v1 covers updating *existing* listings (stock levels, and price/description edits), not building a listing-creation wizard.
- Amazon integration on day one — gated behind the decision to actually sell on Amazon and upgrade to a Professional selling plan (required for API access).
- Accounting/bookkeeping, VAT tracking, profit reporting — StickerBinStickersCosts.xlsx remains the source of truth for that.
- Customer-facing anything — this is an internal tool for one user.

## 4. Actors

- **You** — sole user, sole operator. No multi-user/permissions requirement.

## 5. Channels in scope

**Correction from v0.1:** there is no WooCommerce storefront. WordPress is only the *host* for this internal admin tool (a page/plugin you log into), not a sales channel in its own right. Selling channels are marketplaces only.

| Channel | Priority | Notes |
|---|---|---|
| Etsy | v1 | Seller App API access, approved fast, own-shop-only |
| eBay | v1 | Sell APIs, free, self-serve dev account |
| Amazon | v2 (later) | Blocked on Professional plan decision + SP-API registration |
| Own website | Deferred, undecided | Possible future channel, not shelved — not WooCommerce though, so platform/build approach TBD when it becomes real. Not designed against in v1; architecture should just avoid assuming exactly two channels forever (i.e. channel list shouldn't be hardcoded in a way that makes adding a third painful later). |

## 6. High-level functional requirements

1. **Order aggregation** — pull new/updated orders from each connected channel into one list.
2. **Unified order view** — see, per order: channel, order date, buyer name, product(s), personalisation details (bin number/name/text), shipping address, order status.
3. **Status tracking — user-defined, reusable workflow templates.** Rather than a hardcoded status list, you define workflows yourself as reusable templates (add/remove/reorder steps); each product is assigned one workflow template, and multiple products can share the same template rather than needing a new one each time. Every order of a given product follows its assigned workflow in the same fixed order — no per-order skipping or reordering of steps. Each step can optionally require one or more of the following before an order can move on:
   - **Manual confirmation** — a simple "done" checkbox (e.g. "cut", "packed").
   - **Timer/wait — hard gate.** A defined wait period attached to a step (e.g. "ink drying — 15 min") that actively blocks the order moving to the next step until the time has elapsed, not just a reminder.
   - **Script/API trigger** — the step runs something external as part of advancing: a local script/action (e.g. sending a print job, running `thankyou_card.py`), an external API call (e.g. pushing tracking to eBay/Etsy), or triggering an **n8n workflow** via webhook. On failure (printer offline, API down, webhook timeout), the tool auto-retries a couple of times before falling back to sitting stuck at that step with a visible error for manual retry — exact retry count/backoff to be pinned down in detailed design.
   A simple settings/config screen (not a visual drag-and-drop builder) is sufficient for v1 to build and edit these templates.
   Two steps are confirmed to belong *inside* this workflow rather than being bolted on separately: a **thank-you card step** (script trigger, e.g. auto-running `thankyou_card.py`), and a **review-request reminder step** (surfaces at an appropriate point after shipping — exact trigger/delay TBD in detailed design).
4. **Personalisation capture** — surface whatever free-text/variation data the buyer entered (bin number, child's name, etc.) clearly, since this is the detail most likely to cause a production mistake if missed.
5. **Manual handoff points stay manual** — the tool tells you what to do next (e.g. "these 3 need packing"), it doesn't attempt to auto-generate Click & Drop labels or auto-print anything, at least in v1.
6. **Search/filter** — by status, channel, date, roughly.
7. **No duplicate orders** — an order pulled from a channel should never appear twice even if the sync runs repeatedly.
8. **Inventory/stock level view** — see current stock/quantity-available per listing, per channel, in one place.
9. **Material stock tracking** — separately track raw material stock on hand (vinyl sheets, laminate sheets, etc. — see Equipment Guide/Costs xlsx for the material list), decremented as orders are fulfilled.
10. **Linked stock model** — per-listing stock and material stock are connected: each *product* (not each listing variant) maps to a fixed set of materials and quantities consumed per unit (e.g. "1 bin sticker set = 1 A4 vinyl sheet + 1 A4 laminate sheet"). This mapping is editable over time (materials/quantities can change), but is defined once per product rather than varying by size/colour variant.
11. **Material stock — automatic decrement on order receipt.** Material stock decreases automatically as soon as an order is pulled in (the "New order" point), not at some later production step — the reservation happens the moment the order exists, on the assumption that materials will be consumed to fulfil it. Detailed design needs to account for what happens if an order is later cancelled/refunded (stock presumably needs to be added back — flagged for detailed design, not decided here).
12. **Inventory updates (push)** — push a stock level change to eBay and Etsy listings from this tool (both viewing current state and editing it live, not read-only).
13. **Listing edits (existing listings only, push)** — update price and/or description/quantity on an existing eBay or Etsy listing from this tool, with changes actually pushed to the platform via API, plus the ability to just view current listing state without editing. Does not include creating brand-new listings from scratch in v1 (see Section 3).
14. **Read-only AI/MCP query access** — both Cursor (during development) and Claude (against the live site) can query orders, products, materials, listings, and media through a standard MCP connection — read-only in v1, expandable to write access later without redesign. See `07-MCP-Integration.md` for the full design (built on WordPress's own Abilities API + MCP Adapter, not a bespoke API).

## 7. Non-functional requirements / constraints

- Runs within/alongside your existing WordPress install as an internal admin tool (a page/plugin only you log into) — WordPress is the *host*, not a storefront; there's no WooCommerce shop or public-facing product catalogue to sync against.
- Etsy's API has no webhook support — new-order detection must be done by polling on a schedule, not real-time push.
- Amazon API access requires a Professional Amazon Seller account (~£25+/month) — cannot be built against until that account exists. Noted for later: a possible interim workaround is an email-based automation (e.g. via n8n) that detects a new Amazon order confirmation email and calls an API endpoint on this system to create the order record, avoiding a full SP-API build for v1-equivalent Amazon support. Not built now — captured here so the system's "create order" path is designed generically enough (e.g. a documented internal API endpoint) that this becomes a light lift later rather than a rebuild.
- The workflow engine needs to call out to **n8n** (self-hosted or cloud) as one of its script/API step types, alongside local scripts and direct API calls — this should be treated as a first-class integration target, not an afterthought.
- Must not require paying for a third-party SaaS multichannel tool as a hard dependency (evaluate free tiers, but this spec assumes a custom-built or free-tier path per your preference to keep options on the table).
- Should degrade gracefully if one channel's API is temporarily down — shouldn't block seeing orders from the others.

## 8. Open questions for your review

All resolved. Summary of the full set of decisions across this review:

- Channels: eBay + Etsy for v1; Amazon deferred (possible future email→API workaround noted, not built); own website deferred, not shelved.
- WordPress hosts this tool only — no storefront/WooCommerce involved.
- Listing/inventory: in scope — view + push edits for price/description/quantity on existing listings (no new-listing creation in v1).
- Material stock: tracked per product (fixed material list per product, editable over time, not per variant), auto-decremented the moment a new order is pulled in, with cancellation/refund stock-reversal flagged for detailed design.
- Status tracking: reusable workflow templates, one per product (shareable across products), fixed step order per order (no per-order skipping). Step types: manual confirmation, hard-gated timers, and script/API triggers (local scripts, external APIs, or n8n webhooks) with auto-retry before falling back to a manual-retry error state. Thank-you card and review-request reminder are both steps inside the workflow. A simple config screen is sufficient for v1 (no visual builder).
- Click & Drop stays fully manual.

**Status: this spec is ready to move to the next stage.**

---

*Next step once this is agreed: break section 6 into a data model + per-channel API mapping + WordPress integration approach.*
