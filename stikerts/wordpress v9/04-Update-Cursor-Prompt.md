# Update Package 8 — Cursor kickoff prompt

*Use when starting UP8-S1 / S2 / S3. Planning docs in this folder.*

## Locked model

- Site tray pool **1 … max** (default max 10).  
- Workflow template **`uses_trays`** (make and pack).  
- Assign **one or more free** trays at **first step**; no double-booking.  
- Release on workflow complete or manual; **transfer** make → pack optional.  
- **Release timer** always on `waiting_timer` steps; log who/when (+ order note); Mark done still required.  

## Rules

- Read `01-Update-Overview.md`, `03-Update-Trays.md`, `02` if schema, and **`Update-8-Sprint-Plan.md`**.  
- Do not invent barcode/auto-assign.  
- SemVer stays `0.x.y`. Plain PHP admin UI.  

## Sprint entry points

| Sprint | Start with |
|---|---|
| UP8-S1 | Schema + Settings + order detail assign/release |
| UP8-S2 | First-step gate + transfer + boards |
| UP8-S3 | Docs / polish |

## Done discipline

One sprint at a time; smoke `tests/sprint-up8-sN-smoke.php`; progress doc; commit/push/PR.  
