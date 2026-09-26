# Update Package 5 — Sprint Plan

*Planning only — no plugin code in this pass. Specs: `01`–`06` in this folder. Baseline: plugin **v0.27.0**, schema **1.12.0**.*

**Sequencing:** Order Notes → Shipping packages / planned postage (+ Product Costing) → Multipack conventions/docs. Matches `01-Update-Overview.md` recommended order. Multipack has **no engine** — docs only, can ship with or immediately after shipping.

Settled product decisions in `01-Update-Overview.md` are **locked** (threaded notes admin-only on order detail; flat planned postage; goods weight + package catalogue; actual = shipment; multipacks = separate SKUs; no carrier APIs). This plan does **not** reopen them.

Open items: soft defaults below apply unless overturned before the relevant sprint kickoff.

---

## 1. Consolidated open items

| # | Source | Item | Blocks | Status / decision |
|---|---|---|---|---|
| O1 | `03` §6.1 | Hard max length for note body | UP5-S1 save validation | **Default:** 5000 chars (same as step instructions) |
| O2 | `03` §6.2 | Newest-first vs oldest-first note list | UP5-S1 UI | **Default:** chronological ascending (oldest top, newest bottom + form below) |
| O3 | `02`§A / `03` §6.3 | Edit/delete notes | UP5-S1 | **Default:** **append-only** (no edit/delete; no `updated_at`) |
| O4 | `03` §6.4 | Note count on orders list | UP5-S1 | **Default:** skip v1 |
| O5 | `02`§B3 / `04` §7.1 | Multi-line planned shipping seed | UP5-S2 create path | **Default:** **sum** of `product.planned_shipping_gbp × line qty` for matched lines |
| O6 | `02`§B3 / `04` §7.2 | Re-sync fill planned when null | UP5-S2 | **Default:** set **only on create**; never overwrite on update/re-sync |
| O7 | `02`§B2 / `04` §7.4 | Shipping fields on internal products | UP5-S2 product UI | **Default:** **hide** on internal products; leave columns null |
| O8 | `02`§B3 | Internal orders planned shipping | UP5-S2 create | **Default:** leave **null** for `channel = internal` |
| O9 | `02`§B5 / `04` §7.3 | Postage in order profit / Analytics | UP5-S2 | **Default:** **order-detail variance only** in this package; do **not** change `order_profit` / Analytics series yet |
| O10 | `04` §7.5 | Package delete vs deactivate | UP5-S2 | **Default:** deactivate; block hard-delete if any product references |
| O11 | `04` §7.6 | Domestic vs international planned | UP5-S2 schema | **Default:** **single** `planned_shipping_gbp` field |
| O12 | `02`§B open 5 | Display units | UP5-S2 UI | **Default:** store **g** + **mm**; labels show grams / mm (optional cm hint in description) |
| O13 | `02`§B open 6 | Default package uniqueness | UP5-S2 | **Default:** enforce **one** `is_default` in application code (clear others on save) |
| O14 | `05` §6.1 | Second seeded pack SKU | UP5-S3 | **Default:** **no** new seed SKU |
| O15 | Code | REST `order_to_rest` must not gain notes | UP5-S1 | **Must** keep notes off REST/MCP serializers |
| O16 | Code | Admin menu page allowlist for new Packages screen | UP5-S2 | Register `som-shipping-packages` (or similar) in menu + asset/notice allowlists |

---

## 2. Clarifying questions (defaults unless overturned)

Answers already locked in overview are not re-asked. Soft defaults:

### Notes (UP5-S1)

1. **Max length 5000?** — default yes.  
2. **Oldest→newest + form at bottom?** — default yes.  
3. **Append-only?** — default yes.  
4. **No list badge?** — default yes.

### Shipping (UP5-S2)

5. **Seed planned = sum(line planned × qty)?** — default yes.  
6. **Create-only seed (never re-sync overwrite)?** — default yes.  
7. **Hide shipping on internal products; null on Internal orders?** — default yes.  
8. **Product Costing includes planned shipping; order profit/Analytics unchanged?** — default yes (variance on order detail only).  
9. **Single flat planned £ (not domestic/international split)?** — default yes.

### Multipacks (UP5-S3)

10. **Skip extra seed SKU; docs only?** — default yes.

Overturn any of these before kickoff if needed.

---

## 3. Spec ↔ codebase discrepancies

Treat **existing plugin code as ground truth**. None block planning.

| Spec assumption | Actual code today | Plan impact |
|---|---|---|
| Actual postage exists | [`som_shipments.postage_paid`](../../includes/class-som-db.php) + [`SOM_Shipments`](../../includes/class-som-shipments.php) validate/save | Reuse; show planned vs actual on order detail; do not auto-fill postage from planned |
| Order create hook for seed | [`SOM_Order_Sync::create_from_external`](../../includes/class-som-order-sync.php) / `upsert_order` create path | After items inserted, compute planned shipping (skip internal channel) |
| Product Costing | [`SOM_Products::recipe_costing`](../../includes/class-som-products.php) materials + [`SOM_Platform_Fees::product_fee_costing`](../../includes/class-som-platform-fees.php) | Add planned shipping into cost/margin math when set |
| Order profit | [`SOM_Analytics::order_profit`](../../includes/class-som-analytics.php) = revenue − material − fees | **Leave unchanged** in Package 5 (O9); postage variance UI only |
| REST orders | `order_to_rest` on order payloads | Ensure notes table never wired into REST/MCP (O15) |
| New admin screen | Menu + `admin_enqueue` / `som_admin_notices` page allowlists in [`SOM_Admin_Menu`](../../admin/class-som-admin-menu.php) / `orderMachine.php` | Must add package page slug everywhere page gates exist (O16) |
| Multipack engine | Products already have independent recipes | No BOM multiplier; docs only |

---

## 4. Sprints

### UP5-S1 — Order notes

- **Covers:** `03-Update-Order-Notes.md` + `02` §A  
- **Open items first:** O1–O4, O15  
- **DDL:** `order_notes` table; bump **1.13.0**

**Files (expected):**

| File | Change |
|---|---|
| `includes/class-som-db.php` | `som_order_notes`; DB **1.13.0** |
| `includes/class-som-order-notes.php` | **New** — list / add (append-only) |
| `admin/views/order-detail.php` | Notes thread + add form |
| `admin/class-som-admin-menu.php` | Save handler in orders actions |
| `orderMachine.php` | Require class; version bump with sprint |
| `tests/sprint-up5-s1-smoke.php` | **New** — add note, list, empty reject, not in REST payload |
| Docs | FEATURES / USER short note |

**Done when:**

- Admin can add notes on order detail; thread shows author + time + body.
- Empty body rejected; length capped (O1).
- REST/MCP order reads do not include notes.
- wp-env smoke passes.

---

### UP5-S2 — Shipping packages & planned postage

- **Covers:** `04-Update-Shipping-Packages.md` + `02` §B  
- **Open items first:** O5–O13, O16  
- **DDL:** `shipping_packages`; product + order columns; bump **1.14.0**

**Files (expected):**

| File | Change |
|---|---|
| `includes/class-som-db.php` | packages table; `products.weight_grams`, `package_id`, `planned_shipping_gbp`; `orders.planned_shipping_gbp`; DB **1.14.0** |
| `includes/class-som-shipping-packages.php` | **New** — CRUD, default flag, list active |
| `includes/class-som-products.php` | Persist weight/package/planned; hide for internal (O7); costing includes planned shipping |
| `includes/class-som-orders.php` | Load/save planned shipping; attach shipment actual for detail |
| `includes/class-som-order-sync.php` | Seed planned on create (O5/O6/O8) |
| `admin/views/shipping-packages-list.php` (+ edit) | Catalogue UI |
| `admin/views/product-edit.php` | Weight, package select, planned £ (hidden if internal) |
| `admin/views/order-detail.php` | Planned shipping edit; actual + variance |
| `admin/views/product-costing` panel (in product-edit) | Planned shipping cost line |
| `admin/class-som-admin-menu.php` | Menu + handlers; allowlists |
| `orderMachine.php` | Require + version |
| `tests/sprint-up5-s2-smoke.php` | **New** — package CRUD, product fields, create order seeds sum×qty, internal null, costing line |
| Docs | FEATURES / USER shipping notes |

**Done when:**

- Can manage packages; assign weight/package/planned £ on sellable products.
- New non-internal order gets planned shipping = sum(line planned × qty); editable afterward; re-sync does not clobber.
- Internal orders stay null; internal product edit hides shipping fields.
- Product Costing shows planned shipping when set.
- Order detail shows planned vs shipment actual variance.
- `order_profit` / Analytics unchanged (O9).
- wp-env smoke passes.

---

### UP5-S3 — Multipack conventions / docs polish

- **Covers:** `05-Update-Multipacks.md`  
- **Open items first:** O14  
- **DDL:** none  

**Files (expected):**

| File | Change |
|---|---|
| `stikerts/wordpress/USER-GUIDE.md` | Pack sizes = separate products; shipping defaults per SKU |
| `stikerts/wordpress/USER-REFERENCE.md` | Same + notes + packages pointers |
| `stikerts/wordpress/FEATURES-AND-TESTING.md` | Package 5 feature rows + brief verify notes |
| `stikerts/wordpress/Sprint-Progress.md` | Point at Update-5 progress when S1–S2 land |
| Seed | **Skip** second pack SKU (O14) |

**Done when:**

- Operator docs state multipack = separate SKUs with own recipe/shipping.
- No schema or behaviour change required beyond docs (unless tiny copy nits found in S2 UI).

---

## 5. Suggested SemVer (alpha 0.x)

| After sprint | Plugin (approx) | Schema |
|---|---|---|
| UP5-S1 | 0.28.0 | 1.13.0 |
| UP5-S2 | 0.29.0 | 1.14.0 |
| UP5-S3 | 0.29.x or fold into S2 release notes | unchanged |

Exact bumps follow `RELEASE.md` when cutting tags.

---

## 6. Out of scope (this package)

- Live eBay Logistics / Etsy calculator / carrier APIs  
- Buyer-paid shipping from channel payloads  
- Postage inside Analytics profit charts (deferred; O9)  
- Bin-packing / multi-parcel  
- Pack → base unit inventory explosion  
- Note attachments, @mentions, channel message sync  
- Board/list note snippets  

---

## 7. Progress tracking

After each implemented sprint, record verification in **`Update-5-Sprint-Progress.md`** (create on first implementation sprint).

---

## 8. Explicit scope of this document

This file is the Package 5 **sprint plan** only. It does not implement features. Implementation starts when you explicitly ask to implement **UP5-S1** (or a later sprint). Soft defaults above apply unless overturned before that sprint.
