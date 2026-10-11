# Update Package 7 — Cursor kickoff prompt

*Use when starting UP7-S1 / S2 / S3. Planning docs in this folder.*

## Locked model

- **Shipping package** owns packaging materials (like **product** owns make recipe).  
- **Workflows** = steps only — do **not** add materials to Pack templates.  
- Order selects a package (1 pack vs 2+); Materials used reports actuals like today.  

## Rules

- Read `01-Update-Overview.md`, the sprint’s feature doc, `02` if schema, and **`Update-7-Sprint-Plan.md`**.  
- Do not put thank-you / envelope on product recipes as the packaging solution.  
- Do not reintroduce `workflow_pack_materials`.  
- SemVer stays `0.x.y`. Plain PHP admin UI.

## Sprint entry points

| Sprint | Start with |
|---|---|
| UP7-S1 | `04-Update-Make-Step-Gate-UX.md` + BUG-003 |
| UP7-S2 | `03-Update-Package-Materials.md` (materials + Materials used) |
| UP7-S3 | `03` §4 package suggestion (1 vs 2+ / mixed) + multi-line test orders + seed/docs |

## Done discipline

- One sprint at a time; smoke `tests/sprint-up7-sN-smoke.php`; update progress doc; commit/push/PR.  
