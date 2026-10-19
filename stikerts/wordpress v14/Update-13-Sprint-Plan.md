# Update Package 13 — Manual orders and CSV import

*Planning only. Not implemented. Start only after the operator confirms the open items in §1 and says to begin. Package 12 can finish first.*

**Why:** eBay sandbox checkout is too unreliable to prove order import, and Amazon has no API in this plugin. Sellers already download order and listing files from Seller Hub and Seller Central. This package lets a paid eBay or Amazon order enter Order Machine without a live API call, and lets house number, street, and style be typed when the file does not contain them.

**Already shipped (do not rebuild):** on an existing order line, **House number**, **Street name**, **Style**, and personalisation are editable (`SOM_Orders::update_item_structured_fields`). Copy command already reads them. The test-order button stays as it is: channel `external`, fake London address, no marketplace order id.

**Create path:** manual entry and CSV both call `SOM_Order_Sync::create_from_external()`. That already accepts channel, external order id, buyer, address, and line fields (`external_listing_id`, `sku`, `quantity`, `unit_price`, `house_number`, `street_name`, `style`). A new order gets a workflow, stock reservation, and budget funding, the same as **Sync now**. It does not use the Import history path.

**Duplicate rule:** `UNIQUE (channel_id, external_order_id)`. A second import of the same id is skipped and reported. Existing line fields are not rewritten, so a later file cannot wipe a house number you typed. A later live eBay **Sync now** of that same id updates the header and `raw_payload` only (current sync rule).

**No marketplace writes.** No Amazon API. No eBay tracking or listing push. Read-only eBay testing is unchanged.

---

## How sellers usually export this

| Source | Where | File | Shape |
|---|---|---|---|
| eBay orders | Seller Hub → Orders → **Download report** (or Reports → Download → Orders) | `.csv`, comma-separated | One row per line. Same **Order Number** repeats for multi-line orders. |
| Amazon orders | Seller Central → Orders → Order Reports → unshipped or all orders | `.txt`, **tab-separated** | One row per line. Header names use dashes (`order-id`). `item-price` is the **line total**, not the unit price. |
| eBay listings | Seller Hub → Reports → Listings → Active listings | `.csv` | One row per listing. **Item number**, **Custom label**, title, price, quantity. |
| Amazon listings | Seller Central → Inventory report | `.txt`, tab-separated | `seller-sku`, `asin`, `item-name`, `price`, `quantity`. |

Neither order file contains house number or street as their own columns. eBay sometimes has **Variation Details** (`Design: Flourish; House Number: 36`). Amazon puts personalisation behind `customized-url` (a zip link), which this package does not download. Those two fields are typed on the form, or added as extra columns on the Order Machine template.

Detect the file from the header row, not from the extension. Accept comma or tab. Strip a UTF-8 BOM. A preview lists orders, lines, and row errors before anything is saved.

---

## 1. Open items

| # | Item | Default if you do not change it |
|---|---|---|
| O1 | Manual and CSV creates run workflow, stock, and budget | **Yes** (same as Sync now) |
| O2 | Re-import of an existing order id | **Skip** and say “already imported” |
| O3 | Listing CSV in this package | **Yes** — eBay active listings and Amazon inventory, so item id / SKU can match a product |
| O4 | Fee CSV (eBay payments report, Amazon settlement) | **No** — those files are wide and a poor match for the fee table. Later package if you want it |
| O5 | Parse eBay **Variation Details** into house / street / style | **Yes**, using the same names as the API (`House Number`, `Street Name`, `Design`, `Style`, and the existing aliases) |
| O6 | Amazon `customized-url` | **Do not fetch.** Type house number and street on the order |

---

## 2. Spec ↔ codebase

| Area | Today | Package 13 |
|---|---|---|
| Channels | `ebay`, `etsy`, `external`, `internal`. Data model reserved `amazon` as unused | Add `amazon` row. No credentials, no sync |
| New marketplace order | eBay/Etsy API, or a test order on `external` | **Add order** form for eBay and Amazon |
| House / street / style | Editable on an existing line | Also on the Add order form and as optional CSV columns |
| CSV | None | Orders page: upload, preview, commit. Settings or Listings: listing-map upload |
| Listing map | One at a time | Bulk from the listing reports. Match `products.sku` to Custom label / seller-sku. No SKU match → row listed as unmatched, not inserted |
| `raw_payload` | API JSON | For these orders, the form fields or the CSV row. Still shown under **Raw payload (debug)** |

---

## 3. Field map

### Add order form

| Field | Required |
|---|---|
| Channel | eBay or Amazon |
| Order number | Yes. eBay `12-34567-89012`. Amazon `123-1234567-1234567` |
| Order date | No. Default today |
| Buyer name | No |
| Ship-to name, address 1, address 2, city, county, postcode, country | No. Country default `GB` |
| Lines (at least one, up to 10) | Product **or** item number **or** SKU. Quantity default 1. Unit price optional |
| Per line: house number, street name, style | No |

Item number is stored as `external_listing_id`. SKU and item number match an existing listing map the same way sync does. If none match, the line is unmatched and the order still saves.

### eBay orders report → order

| CSV column | Order Machine |
|---|---|
| Order Number | `external_order_id` |
| Sale Date | `order_date` |
| Buyer Name, else Post To Name | `buyer_name` |
| Post To Address 1 / 2, City, State, Postal Code, Country | `shipping_address` |
| Item Number | `external_listing_id` |
| Custom Label | `sku` |
| Quantity | `quantity` |
| Sold For | `unit_price` (already per item on this report) |
| Variation Details | house / street / style when O5 is on |
| Item Title | style, only when Variation Details has no design |

Rows that share an Order Number become one order. Buyer Address columns are ignored; Post To is the ship-to address.

### Amazon order report → order

| Flat-file column | Order Machine |
|---|---|
| order-id | `external_order_id` |
| purchase-date | `order_date` |
| buyer-name, else recipient-name | `buyer_name` |
| ship-address-1 / 2 / 3, ship-city, ship-state, ship-postal-code, ship-country | `shipping_address` |
| sku | `sku` and, if it matches a listing map, the listing id |
| quantity-purchased | `quantity` |
| item-price ÷ quantity-purchased | `unit_price` |
| product-name | style, only when the style column is empty |

### Order Machine order template

A small CSV for rows you type yourself, so house number and street can travel in the file. Headers:

`channel,order_number,order_date,buyer_name,address_1,address_2,city,county,postcode,country,item_number,sku,quantity,unit_price,house_number,street_name,style`

`channel` is `ebay` or `amazon`. Same order number on several rows means several lines. The importer accepts this template as well as the two marketplace files.

### Listing files → listing map

| File | External listing id | SKU used to find the product |
|---|---|---|
| eBay active listings | Item number | Custom label |
| Amazon inventory | ASIN, else seller-sku | seller-sku |

Price and quantity are stored on the listing row. They are not pushed back.

---

## 4. Sprints

### UP13-S1 — Amazon channel and Add order

| Work | Detail |
|---|---|
| Channel | `amazon` in `SOM_Channels::known()`, active, no OAuth. `ensure_rows()` inserts it |
| UI | Orders → **Add order**. Fields in §3. Save via `create_from_external()` |
| Lines | House number, street name, style on each new line |
| Duplicate | Existing id shows the error and does not create a second order |
| Tests | `tests/sprint-up13-s1-smoke.php` — eBay and Amazon orders created, matched and unmatched lines, duplicate rejected, house/street stored |
| Docs | USER-GUIDE short “Add an order by hand” |

**Done when:** an eBay order number and an Amazon order number can be typed in, with house number and street, and they show on the order page with workflow and stock when the line matches a product.

---

### UP13-S2 — Order CSV / flat file

| Work | Detail |
|---|---|
| Upload | Orders → **Import orders**. File input, nonce, `manage_options` |
| Detect | Header row selects eBay orders report, Amazon order report, or the Order Machine template |
| Preview | Counts and a table of errors (missing order number, bad quantity, unknown channel). Nothing saved until **Import** |
| Group | Rows with the same order number → one order, many lines |
| Amazon price | `item-price / quantity` |
| Variation Details | O5 aliases |
| Result | Created N, skipped N already imported, failed N. Failed rows do not roll back the ones that succeeded |
| Tests | `tests/sprint-up13-s2-smoke.php` plus fixtures: a small eBay CSV, a tab Amazon file, and the template. Assert grouping, unit price, variation parse, skip-on-duplicate |
| Docs | Where to download each file, and a copy of the template headers |

**Done when:** a Seller Hub orders CSV and an Amazon order `.txt` import into the right channel without a duplicate on a second upload.

---

### UP13-S3 — Listing CSV

| Work | Detail |
|---|---|
| Upload | Listings → **Import listing report** |
| Detect | eBay active-listings header vs Amazon inventory header |
| Match | Product by SKU. Insert listing map when the product exists and that external id is not already mapped |
| Unmatched | Listed on the result screen. No empty listing row |
| Tests | `tests/sprint-up13-s3-smoke.php` |
| Docs | USER-GUIDE listing import note |

**Done when:** an eBay item number and an Amazon SKU from a report become listing maps for products that already use that SKU.

---

## 5. Out of scope

- Amazon Selling Partner API, and downloading `customized-url`
- eBay or Etsy API changes, including the read-only writes switch
- Fee, settlement, or payments CSV
- Updating lines on an order that already exists
- Pushing price, quantity, or tracking back to either marketplace
- Changing the test-order form

---

## 6. Checklist

### UP13-S1
- [ ] `amazon` channel row
- [ ] Add order form (eBay and Amazon), including house, street, style
- [ ] Duplicate order number rejected
- [ ] Smoke + USER-GUIDE

### UP13-S2
- [ ] Header detection, comma and tab, BOM
- [ ] Preview then commit
- [ ] eBay, Amazon, and Order Machine template
- [ ] Variation Details parse; Amazon unit price
- [ ] Skip existing ids
- [ ] Fixtures + smoke + docs

### UP13-S3
- [ ] eBay active listings and Amazon inventory
- [ ] SKU match; unmatched reported
- [ ] Smoke + docs
