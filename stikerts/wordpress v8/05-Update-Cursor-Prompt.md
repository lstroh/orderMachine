# Update Package 7 — Cursor kickoff prompt

*Use when starting implementation of UP7-S1 / S2 / S3. Planning docs live in this folder.*

## Context

Order Machine has make/pack split (Package 6), product recipes + Materials used overuse (Package 4), and shipping packages without stock (Package 5). Operators need **per-pack** materials (thank-you, packaging) and a fix for Make step **zero-gate / checklist UX** (BUG-003).

## Rules

- Read `01-Update-Overview.md`, the feature doc for the sprint, `02-Update-Data-Model.md` if schema, and **`Update-7-Sprint-Plan.md`** settled defaults.  
- Do **not** silently overturn open items — follow soft defaults or ask.  
- Plugin SemVer stays `0.x.y`; bump Version + `SOM_VERSION` together when releasing.  
- Plain PHP admin UI; no React.  
- No eval of DB strings; pack material qty is data only.  
- Do not put pack materials on product recipes as the solution.

## Sprint entry points

| Sprint | Start with |
|---|---|
| UP7-S1 | `04-Update-Make-Step-Gate-UX.md` + BUG-003 |
| UP7-S2 | `03-Update-Pack-Order-Materials.md` pack-template half + `02` §A |
| UP7-S3 | Package materials half + seed/docs |

## Done discipline

- Implement only the requested sprint.  
- Add/adjust smoke under `tests/sprint-up7-sN-smoke.php`.  
- Update `Update-7-Sprint-Progress.md` when verifying.  
- Commit, push, open/update PR per cloud agent rules.  
