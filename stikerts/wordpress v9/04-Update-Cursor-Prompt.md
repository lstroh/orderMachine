# Update Package 8 — Cursor kickoff prompt

*Use when starting UP8-S1 / S2 / S3.*

## Locked model

- Trays on the **order**; shared by **all** make + pack workflows on that order.  
- Assign at first step **or** order detail; **1+** free trays; site **1…max**.  
- Required if **any** involved template has `uses_trays`.  
- Release on **order complete** or **manual**. No make→pack transfer.  
- **Release timer** always; log who/when (+ order note).  

## Rules

- Read `01`, `03`, `02` if schema, **`Update-8-Sprint-Plan.md`**.  
- Do not reintroduce per-line trays or transfer.  
- SemVer `0.x.y`; plain PHP admin.  

## Sprints

| Sprint | Focus |
|---|---|
| UP8-S1 | Schema + order assign/release |
| UP8-S2 | Require-when-any + complete release + timer release + boards |
| UP8-S3 | Docs |
