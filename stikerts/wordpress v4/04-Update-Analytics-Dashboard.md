# Update — Analytics Dashboard

*Update set 4 of 4 (specs) · No schema changes needed — pure read/aggregate UI layer over existing data. Self-contained — no need to reference the original planning files.*

---

## 1. What this adds

A charts page — standard practice for this kind of data confirms the approach: revenue and profit are time series, best shown as line charts, while comparisons (e.g. orders by channel) suit bar charts. Implemented with **Chart.js** via CDN script tag — no build step, consistent with the existing plugin's plain-PHP admin approach and the SortableJS pattern already used for the Order Board.

## 2. Charts

### Core three (as requested)

| Chart | Type | Data source |
|---|---|---|
| **Sales over time** | Line | `orders`/`order_items` — revenue by date, using actual sold price where available, else `target_selling_price` |
| **Profit over time** | Line | Revenue minus material cost (weighted-average, from existing costing) minus platform fees (actual where synced, else estimated — see `03-Update-Platform-Fees.md` §5, if that update is built; falls back to material-cost-only profit if not) |
| **Material stock over time** | Line, per material (selectable) | Reconstructed from `material_stock_log`'s running balance per date — the existing plugin already logs every stock change with a timestamp, so this is a read/aggregate query, not new tracking |

### Recommended companions (standard for this kind of dashboard, not strictly requested — flagging as a suggestion)

| Chart | Type | Why |
|---|---|---|
| **Orders by channel** | Bar | Quick read on eBay vs. Etsy (vs. Amazon once built) volume split |
| **Average order value** | Line | Complements sales-over-time — a revenue trend can hide an AOV trend moving the opposite direction |

Both are cheap to add given the same underlying order data — happy to drop either if they're not wanted; flagged in §5 as an open item rather than assumed.

## 3. Filters

- **Date range:** last 7/30/90 days, this year, custom range
- **Granularity:** daily / weekly / monthly (affects sales, profit, and AOV charts)
- **Channel filter:** where relevant (sales, profit, orders-by-channel)
- **Material selector:** for the material-stock-over-time chart, since showing every material at once would be unreadable — pick one or a small set to compare

## 4. UI requirements

| Page | Purpose |
|---|---|
| **Analytics** | The charts described above, with the filters in §3. Single page, not split across multiple admin screens, since the filter state (date range especially) is more useful shared across charts than reset per page. |

## 5. Open items to resolve before/during build

1. **Companion charts** (§2) — confirm whether "orders by channel" and "average order value" should be included, or whether you'd rather keep this tight to exactly the three originally requested.
2. **Query performance:** charts are built from live aggregation queries against `orders`, `order_items`, `material_stock_log`, rather than a pre-computed summary/reporting table. Reasonable at solo-seller order volumes; flag if this becomes noticeably slow once real data accumulates, at which point a periodic summary-table job would be the fix — not needed to build now.
3. **Profit chart's dependency on Platform Fees:** if Update Package 3's Platform Fees section (`03-Update-Platform-Fees.md`) isn't built yet when this is implemented, the profit chart should degrade gracefully to material-cost-only profit rather than blocking — confirm that's an acceptable interim state.
