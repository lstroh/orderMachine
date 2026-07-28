# Implementation Roadmap — Phased Build Order

*Detailed design, part 5 of 5 · Ties together `Order-Management-Requirements.md`, `01-Data-Model.md`, `02-API-Integration.md`, `03-Workflow-Engine.md`, `04-WordPress-Integration.md`.*

---

## Front-end approach

**Start with plain PHP admin screens (as designed in `04-WordPress-Integration.md` §3), not a custom front-end.** The architecture already separates business logic into PHP service classes and a REST API (`04-WordPress-Integration.md` §4) — the UI layer is disposable by design. When/if the plain admin screens are outgrown, a richer front-end (React, or WP's own `wp-scripts` tooling) can be pointed at the same REST endpoints later with no backend rewrite. Don't build the better front-end now — it's wasted effort before you know which screens actually need it in practice.

## Phased build order

Each phase produces something usable or directly unblocks the next — not one big build with nothing working until the end.

| Phase | What | Depends on | Payoff |
|---|---|---|---|
| **1. Foundation** | DB schema + activation hook, plugin skeleton | — | Nothing user-facing yet — required groundwork |
| **2. Channel connection** | eBay + Etsy OAuth flows, settings page | Phase 1 | Nothing useful alone, but unblocks everything channel-related |
| **3. Order sync** | Polling, de-dup, order/item storage | Phase 2 | Orders now exist in one place in the DB |
| **4. Orders list + detail (simple UI)** | Plain table + detail view, personalisation + address surfaced | Phase 3 | **Delivers the original goal** — one place instead of four dashboards. Worth pausing here and using it for real before continuing. |
| **5. Products + materials** | Catalogue, material recipes | Phase 1 (independent of 2–4) | Groundwork — not much of a feature on its own |
| **6. Workflow templates + step editor** | Simple form-based config screens | Phase 5 | Lets you define workflows, nothing executes yet |
| **7. Workflow engine** | State machine, cron tick, timers, manual-confirm actions | Phases 4 + 6 | Orders now actually move through defined steps |
| **8. Material auto-decrement** | Hook into order creation | Phases 3 + 5 | Stock tracking becomes live |
| **9. Script/API steps** | Local actions, thank-you card wrapper, n8n calls | Phase 7 | Highest payoff-per-effort once the engine exists — automates the thank-you card and review-reminder steps |
| **10. Listings view + price/qty push** | View + edit synced listings | Phase 2 | Useful but doesn't block anything else — lowest priority |
| **11. External order-creation endpoint** | REST route for future Amazon workaround | Phase 3 | Cheap, do whenever convenient — no urgency |
| **12. MCP read-only integration** | Register Abilities (orders, products, materials, listings, media) + install WordPress MCP Adapter; expose locally for Cursor and via HTTPS/OAuth 2.1 for Claude on the live site | Phases 3, 5, 10 | Both Cursor and Claude can query real data directly — see `07-MCP-Integration.md`. Lowest urgency of all phases; do once the core data (orders/products/materials/listings) is stable and worth querying. |

## Suggested pause point

**Stop after Phase 4 and actually use it for a while before building the workflow engine.** Running real orders through the simple list/detail view first will surface quirks — actual personalisation field locations, real address formats, how order items really match to products — that make Phases 5–9 easier and more accurate than trying to design them all up front from assumptions.

## Handoff checklist for Cursor

When ready to build, the files together are:

1. `Order-Management-Requirements.md` — the "why", all decisions made
2. `01-Data-Model.md` — DB schema
3. `02-API-Integration.md` — eBay/Etsy specifics
4. `03-Workflow-Engine.md` — state machine design
5. `04-WordPress-Integration.md` — plugin architecture
6. `07-MCP-Integration.md` — read-only AI/MCP querying for Cursor + Claude (lowest-priority phase, but part of the full spec)
7. `05-Implementation-Roadmap.md` (this file) — build order

Each design file's own "Open items" section lists the handful of decisions still worth making *during* build rather than before (e.g. multi-workflow orders, `thankyou_card.py`'s call contract, review-reminder modelling) — flag these to Cursor explicitly as known open questions rather than letting it guess silently.
