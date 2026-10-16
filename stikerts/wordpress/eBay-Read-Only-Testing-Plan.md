# eBay read-only testing — Plan

*Planning only. Not implemented. Builds on `02-API-Integration.md`. Open items settled — see §5.*

**Goal:** connect a real eBay account (sandbox first, then production) and test every eBay **read** without Order Machine changing anything on eBay. Writes stay off until read-only testing is signed off.

---

## 1. eBay calls today

| Feature | Call | Type | Trigger |
|---|---|---|---|
| Connect / token refresh | `auth.ebay.com/oauth2/authorize`, `POST /identity/v1/oauth2/token` | Auth | Connect button; cron |
| Order sync | `GET /sell/fulfillment/v1/order` | Read | Cron, Sync now, Import history |
| Listing refresh | `GET /sell/inventory/v1/inventory_item/{sku}`, `GET /sell/inventory/v1/offer?sku=` | Read | Listings → Refresh |
| Fee sync | `GET /sell/finances/v1/transaction` | Read | Cron, Sync fees now |
| **Tracking push** | `GET /sell/fulfillment/v1/order/{id}` then `POST …/order/{id}/shipping_fulfillment` | **Write** | Ship step done with tracking (`SOM_Shipments::maybe_push_after_ship`); order detail **Push tracking to channel** |
| **Listing push** | `PUT /sell/inventory/v1/inventory_item/{sku}`, `PUT /sell/inventory/v1/offer/{offerId}` | **Write** | Listings edit → Push (`SOM_Listings::push`) |

REST API and MCP Abilities do not call either write.

---

## 2. Proposed change — eBay writes switch

| Area | Detail |
|---|---|
| Setting | **Settings → eBay → “Allow writes to eBay”** (tracking + listing push). Default **off**. Stored in `som_settings` under `ebay.writes_enabled`. |
| Override | Optional `define( 'SOM_EBAY_WRITES_ENABLED', false );` in `wp-config.php` forces off regardless of the setting (hard stop on a live site). |
| Gate location | Top of `SOM_Channel_Ebay::create_shipping_fulfillment()` and `SOM_Channel_Ebay::push_listing()`, **before any HTTP call** (including the GETs those methods make first). Single helper `SOM_Channel_Ebay::writes_enabled()`. |
| Dummy mode | Gate does not apply to dummy credentials (fixture writes stay local in options). |
| Tracking when off | Return a distinct `WP_Error` code `som_ebay_writes_disabled`. Ship step still completes locally. Shipment keeps `tracking_pushed_at` NULL so it can be pushed after writes are turned on. `tracking_push_error` shows “eBay writes are off (read-only testing)”. |
| Tracking button | Order detail **Push tracking to channel** disabled for eBay orders with the same note. |
| Listing push when off | Push button disabled on eBay listings with the note; Refresh stays enabled. Server-side gate still rejects a forced POST. |
| Etsy | Unchanged. |
| OAuth scopes | Unchanged (still request `sell.fulfillment` / `sell.inventory` write scopes) so turning writes on later does not need a reconnect (O2). |
| Settings badge | Show **Read-only** next to the eBay connection status while writes are off. |

---

## 3. Read-only test checklist (sandbox, then production)

| # | Test | Pass when |
|---|---|---|
| R1 | Connect eBay (sandbox seller) | Status **Connected**, Read-only badge, no Finances warning |
| R2 | Token refresh | After ~2 h (or forced), sync still works; no reconnect |
| R3 | Listing map before sync | Listings → Add listing map with sandbox item ID / SKU |
| R4 | Sync now | Paid sandbox order imported: buyer, address, item ID, SKU, qty, price; matched line gets workflow + stock + budget |
| R5 | Structured fields | Design / House Number / Street Name variations populate line fields; otherwise style = title |
| R6 | Repeat sync | No duplicates; order updated not re-created |
| R7 | Import history (30 days) | Older orders imported without workflow / stock / budget |
| R8 | Listing refresh | Inventory API listing returns title, qty, price (only for SKUs with an Inventory API offer) |
| R9 | Fee sync | Runs without error; empty result acceptable in sandbox |
| R10 | Cancel in sandbox → Sync now | Order status updates; save raw payload for open item A3/D3 |
| R11 | Ship with tracking (writes off) | Ship completes locally; tracking not sent; Read-only note shown; sandbox order still unshipped on eBay |
| R12 | Listing push (writes off) | Button disabled; forced request rejected; eBay listing unchanged |
| R13 | Production connect + Sync now | Real account connects read-only; real orders import |

Report issues in `BUGS.md` with the raw payload where relevant.

---

## 4. Turning writes on (later)

1. Sign off R1–R13.
2. Enable **Allow writes to eBay** (sandbox first).
3. Test tracking push and listing push in sandbox, then on one real order / listing.
4. Pending shipments from read-only testing can be pushed with **Push tracking to channel**.

---

## 5. Locked decisions (operator, 16 Oct 2026)

| # | Item | Decision |
|---|---|---|
| O1 | Setting + `wp-config` override, or `wp-config` only | **Both** |
| O2 | Request read-only scopes while writes are off | **No** — avoids a reconnect later; the gate is the control |
| O3 | Backlog tracking from read-only period: auto-push on enable, or manual only | **Manual only** |
| O4 | Apply the same switch to Etsy | **No** (eBay only for now) |

---

## 6. Suggested sprint

**ebay-ro-S1** — setting + override, gate in both write methods, UI disabling + notes, Read-only badge, smoke test `tests/ebay-writes-gate-smoke.php` (writes off → both methods return `som_ebay_writes_disabled` with no HTTP; dummy still works; writes on → previous behaviour). Docs: `USER-REFERENCE.md` Settings section. Patch release.

### Checklist
- [ ] Setting + `SOM_EBAY_WRITES_ENABLED` override
- [ ] Gate in `create_shipping_fulfillment` + `push_listing`
- [ ] Order detail + Listings UI disabled with note
- [ ] Settings Read-only badge
- [ ] Smoke test
- [ ] Docs
