# Update Package 11 — Cursor kickoff prompt

*Use when starting UP11-S1 / S2.*

## Locked model

- Workflow kind **`post_ship`**; same steps as make/pack.  
- Auto and/or manual start; site default + per-product override.  
- Order complete = shipping details + **arrived** confirm (not Pack Ship alone).  
- Separate Post-ship board; no trays/package materials.  

## Rules

- Read `01`, `03`, `02`, **`Update-11-Sprint-Plan.md`**.  
- Do not set `is_complete` on Pack Ship for customer orders after this package.  
- SemVer `0.x.y`; plain PHP admin.  

## Sprints

| Sprint | Focus |
|---|---|
| UP11-S1 | Schema + bind + complete rules |
| UP11-S2 | Board + seed + docs |
