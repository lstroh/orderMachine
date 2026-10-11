# Update Package 8 — Cursor kickoff prompt

*Use when starting UP8-S1 / S2 / S3.*

## Locked model

- Trays on the **order**; shared by **all** make + pack workflows on that order.  
- Assign at first step **or** order detail; **1+** free trays; site **1…max**.  
- Required if **any** involved template has `uses_trays`.  
- Release trays on **order complete** or **manual**. No make→pack transfer.  
- **Unblock step** always on current step: bypass **any** gate; Mark done still required; log who/when.  

## Rules

- Read `01`, `03`, `05`, `02` if schema, **`Update-8-Sprint-Plan.md`**.  
- Do not reintroduce per-line trays or transfer.  
- Unblock ≠ auto Mark done.  
- SemVer `0.x.y`; plain PHP admin.  

## Sprints

| Sprint | Focus |
|---|---|
| UP8-S1 | Schema + order tray assign/release |
| UP8-S2 | Trays require/complete + Unblock all gates + boards |
| UP8-S3 | Docs |
