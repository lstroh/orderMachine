# Update — Sticky order header

*Package 15 · UP15-S2 · No schema. Operator request, soft defaults 2026-10-24.*

---

## 1. What this is

On order detail, a header stays on screen while the rest of the page scrolls. Each important value has its own Copy button. House number, street name, and design can be corrected when the import stored them wrong.

This is the personalisation the operator pastes into the make step. It is stored per order line. The ship-to address is a different block on the same header.

Fulfillment screens that do this well keep a short identity bar pinned, and copy the address as plain text with a short “Copied” confirmation (WooCommerce’s order screen, SKU.io’s condensed sticky bar). Royal Mail still wants the ship-to address as separate lines: name, house and street, town, postcode. A UK address can also be a house name with no number, so an empty house number is allowed.

## 2. Two different addresses

| Block | Stored as | Edited here? |
|---|---|---|
| Ship-to | `orders.shipping_address` JSON: `full_name`, `line1`, `line2`, `city`, `state`, `postcode`, `country` | No. A later sync overwrites this JSON. |
| Personalisation | `order_items.house_number`, `street_name`, `style` (design) | Yes. Sync inserts these on create and does not update them later (`upsert_order` leaves line items immutable). |

Do not split `line1` into a house number and a street name. The sticker text can differ from the address the parcel is sent to. eBay already maps variation aspects onto the line fields (`House Number`, `Street Name`, `Design`). When Design is missing, style falls back to the item title, which is a common bad import.

## 3. What stays on screen

The bar is the first block inside `.som-order-detail-wrap`, after the back link. `position: sticky`. `top` is `32px` so it sits under the WordPress admin bar, and `46px` below `782px`, where that bar is taller. White background, its own border, a z-index above the panels and below the admin menu.

The page title, channel badges, order date, and held trays stay in the bar. Trays are order-level: one order can hold more than one, and every make line on that order shares them. The Personalisation and Shipping address panels under the bar stay, so the full address can still be read as a block. They do not grow a second editor. Assign and release stay on the Trays panel.

More than three order lines: the line list inside the bar scrolls (`max-height`), and the identity row stays visible. The bar must not cover the whole viewport.

Internal orders use the same bar. An empty ship-to address shows the muted line that already exists. The line fields still show.

## 4. Copy buttons

Reuse `initCopyText()` in `admin/assets/js/admin.js`. A button on a text input copies that input’s current value, including a correction that has not been saved yet. A button on a read-only value copies the rendered text. Empty value: the button does nothing.

The existing helper already sets the label to **Copied** for 1.5 seconds, uses `navigator.clipboard.writeText` when the page is a secure context, and falls back to a prompt. Local `http://*.local` is often not a secure context, so the prompt fallback stays.

Each of these has its own button:

| Field | Copies | Notes |
|---|---|---|
| Order id | `external_order_id` | The marketplace number. This is the id the operator pastes. |
| Order Machine id | `orders.id` | Second button, smaller label. |
| Buyer | `buyer_name` | Full name, not the first word. |
| Name, Address line 1, Address line 2, Town, County, Postcode, Country | The matching ship-to key | Hide a button when that key is empty. County is the stored `state`. |
| Copy address | `SOM_Orders::format_address()` | One plain-text block, line breaks kept. |
| Product | Product name | Per line. |
| Quantity | The line quantity | Per line. |
| House number | The input | Per line. |
| Street name | The input | Per line. |
| Design | The input | The existing `style` column. Label is **Design**. |
| Copy line | House number, street name, and design | Three lines, skipping empties. Per line. |
| Tray | Each held tray number | Order-level. One chip per number from `SOM_Trays::held_numbers_for_order()`, ascending. The button copies the number only, such as `4`. |

Also show one copy-only chip for each other key in that line’s `personalisation_json`, using the stored aspect name as the label. Skip keys that are already house number, street name, or design (the same aliases `SOM_Channel_Ebay::extract_structured_fields()` uses). These chips are not inputs. A line with no extra keys shows none.

When the order holds no tray, the bar says **No tray**. If a tray is still required, that text links to `#som-trays`, the same place the Make warning already links. It does not include the assign or release form.

Do not put price, fees, tracking, the current step, notes, or the raw payload in the bar. The step Copy command stays on the make step. It still copies the filled command template. The header copies the field itself.

## 5. Correcting house number, street name, and design

Each line in the bar has three inputs and **Save line**:

| Input | Column | Max |
|---|---|---|
| House number | `house_number` | 64 |
| Street name | `street_name` | 191 |
| Design | `style` | 191 |

Save posts the existing `som_save_line_fields` nonce and calls `SOM_Orders::update_item_structured_fields()`. Blank clears that column to null. Do not change `personalisation_text` from this form. Do not write `personalisation_json`.

Remove the Make disclosure **Line fields (house / street / style)** and its form. One editor. The make line still shows the personalisation snippet it shows today.

An order with several lines has one form per line. A line with all three empty still shows the inputs, so a missed import can be typed in. There is no per-product personalisation type in this sprint. A later package can add a field such as a child’s name without this bar having to be redesigned: extra JSON chips already have a place to appear.

## 6. Out of this sprint

- Parsing the ship-to `line1` into house number and street name.
- Editing the ship-to address. Re-sync would overwrite it.
- A product setting for which personalisation fields exist.
- New columns, and any change to eBay aspect aliases.
- Copy buttons on the boards or the orders list.
- Replacing the make-step Copy command.

## 7. Smoke and docs

`tests/sprint-up15-s2-smoke.php`, in the style of `tests/sprint-up14-s12-smoke.php`.

- Render the order detail for an order with one line, a ship-to address, house number, street name, and style.
- The sticky bar markup is present and contains a copy control for the external order id, postcode, house number, street name, design, and each held tray number.
- The Make markup no longer contains `som_line[house_number]`.
- Saving a new house number and street name through `update_item_structured_fields()` stores them.
- An order with two lines renders two line blocks in the bar.
- An order holding trays 2 and 5 renders two tray chips. An order holding none renders **No tray** and no tray copy button.

`php -l` on changed PHP. `node --check` on `admin/assets/js/admin.js`.

CHANGELOG Unreleased line. USER-REFERENCE order detail: the header is where house, street, and design are corrected. FEATURES-AND-TESTING order-detail row: sticky header and per-field copy.

No Version bump. Schema stays **1.26.0**.
