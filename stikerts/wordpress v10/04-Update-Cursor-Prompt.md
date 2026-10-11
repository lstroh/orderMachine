# Update Package 9 — Cursor kickoff prompt

*Use when starting UP9-S1 / S2.*

## Locked model

- **Copy command** step: editable template + `{{placeholders}}`; Copy on make line; no execution.  
- Remove **local / api / n8n** from the product.  
- Fields from **eBay order**; **per make line**.  
- Step **instructions**: **Markdown** + safer HTML render + better CSS.  

## Rules

- Read `01`, `03`, `02`, `05`, **`Update-9-Sprint-Plan.md`**.  
- Never `eval` / shell user templates — **display only**.  
- Instructions: Markdown → kses only; no TinyMCE.  
- Confirm eBay aspect labels (O1) before hard-coding aliases.  
- SemVer `0.x.y`; plain PHP admin.  

## Sprints

| Sprint | Focus |
|---|---|
| UP9-S1 | Fields + copy-command UI |
| UP9-S2 | Remove script execution + docs |
| UP9-S3 | Markdown instructions + styling |
