# Update — Pack / Order-Level Materials

*Package 7 · Schema in `02-Update-Data-Model.md` §A–C. Self-contained.*

---

## 1. What this adds

Operators can define **materials needed for packing and shipping** that are **not** tied to product unit qty:

1. **Pack workflow materials** — e.g. 1 thank-you card per **order pack**.  
2. **Shipping package materials** — e.g. 1 envelope when that package is selected.  
3. **Materials used** on the order — planned vs actual for those lines (same increase-only overuse behaviour as product recipes).

Product recipes remain the make BOM (vinyl × qty, etc.).

## 2. Settled rules (from operator feedback)

| Topic | Decision |
|---|---|
| Thank-you cardstock | **One per pack / order**, not per product line qty |
| Product recipe | Do **not** put 1× thank-you on the sellable SKU recipe for this reason |
| Actual usage | Must be editable on the order (raise Actual when more used) |
| Pack checklist “thank-you included” | Stays a **process** confirmation; stock is separate |

## 3. Soft defaults (overturn before kickoff if needed)

| Topic | Default |
|---|---|
| Where to edit pack BOM | Pack **workflow template** editor (new section, parallel to Material cost goals) |
| Where to edit package BOM | Shipping **package** edit screen |
| When to reserve pack materials | On **pack bind** (create / repair) for non-Internal orders — same moment Pack progress starts |
| When to reserve package materials | When `shipping_package_id` is **first set or changed** on the order |
| History import | Skip reserves (mirror recipe / `new_order` skip rules) |
| Internal Produce N | No pack/package reserves |
| Materials used UI | Extend existing panel; add **Source** (Recipe / Pack / Package) |
| Overuse direction | Increase-only (cannot go below planned) |
| Budget funding | Fund active material budgets on pack/package reserve; extras via existing `fund_usage_extras` |
| Product Costing | **Leave recipe-only** in S2; optional packaging line in S3 |

## 4. Behaviour

### Pack template materials

- CRUD rows: material + `quantity_per_pack`.  
- Only on templates with `kind=pack`.  
- On pack bind: for each row, write negative stock log `pack_reserve` (qty = `quantity_per_pack`, **not** × order line qty).  
- Idempotent per order (re-bind / repair must not double-reserve).

### Shipping package materials

- CRUD rows on package: material + `quantity_per_package`.  
- When operator selects/changes package on Pack panel: reserve that package’s materials (`package_reserve`).  
- If package changes: reverse previous package reserve (stock back) then reserve new (or net-adjust — open).  
- If package cleared: reverse previous reserve.

### Materials used panel

| Column | Content |
|---|---|
| Source | Recipe / Pack / Package |
| Material | Name + unit |
| Planned | From matching reserve reason(s) |
| Actual | Planned + extras (≥ planned) |
| Extra | Actual − Planned |

- Save raises Actual only; stock ↓, COGS ↑, Extra material usage funding.  
- Empty when nothing reserved (history / unmatched / Internal).

### Interaction with make recipe

Same material can appear in recipe and pack (e.g. seal sticker). Show **separate rows by source** so planned amounts stay understandable.

## 5. UI requirements

| Page | Purpose |
|---|---|
| Workflows → Pack template | Pack materials editor |
| Shipping packages → edit | Package materials editor |
| Order detail → Materials used | Source column + pack/package lines |
| (Optional) Product Costing | Packaging cost line (S3) |
| Seed | Demo thank-you cardstock on Pack template; envelope on sample package |

## 6. Out of scope

- Decrease below planned  
- Per-line-item pack material split  
- Auto PDF / thank-you script consuming stock  
- Multi-box packs  

## 7. Open items

1. Package change reversal semantics (full reverse + re-reserve vs net).  
2. Combined vs separate Materials used rows when same material has multiple sources.  
3. Whether Product Costing includes default packaging in this package.  
4. Whether pack reserve waits until make-ready instead of bind (default: bind).  
5. Budget workflow-scope: pack materials use Pack template scope vs primary product make template (recommend Pack template / global material budgets).  
