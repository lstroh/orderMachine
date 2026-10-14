# Update Package 12 — Cursor kickoff prompt

*Use when the operator asks to start UP12-S1 / S2 (or later appended sprints). Do **not** implement from planning alone.*

## Locked model

- **BUG-004:** Produce N has real item make progress; Orders list must not show false **No workflow**; order detail must show Make UI for Internal-channel jobs.  
- Customer-order internal lines stay **Pack-ready (internal)** with no make progress (Package 6 — locked).  
- No Pack/Post-ship bind on Internal. No schema for BUG-004.  
- **Instructions toolbar (UP12-S3):** Markdown-inserting buttons only (Bold, Italic, Bullet, Numbered, H2, H3, Link, Inline code, Preview) on workflow step editor + product overrides. No WYSIWYG, no shortcuts, no render/allowlist changes.  
- **Copy command picker (UP12-S4):** “Insert placeholder…” dropdown over the existing 8 keys; inserts at cursor. No new placeholders, no example-template button.  
- **Post-ship arrival rule (UP12-S5 / BUG-005):** hard-block saving a Post-ship template without a Package arrived step; validate before any write; warnings elsewhere are warn-only. No Pack Ship-step rule.  
- Plain PHP admin; SemVer `0.x.y` alpha; custom `wp_som_*` tables.  

## Rules

- Read `01-Update-Overview.md`, **`Update-12-Sprint-Plan.md`**, plus the sprint’s spec: `03-Update-Produce-N-Workflow-Visibility.md` + `stikerts/wordpress/BUGS.md` (BUG-004) for S1/S2; `05-Update-Instructions-Toolbar.md` for S3; `06-Update-Copy-Command-Placeholder-Picker.md` for S4; `07-Update-Post-Ship-Arrival-Required.md` + BUG-005 for S5.  
- Honor Package 6 make/pack separation; do not silently overturn Packages 6–11 settled decisions.  
- One sprint per chat unless the operator says otherwise.  
- After merge: update `Update-12-Sprint-Progress.md` + move BUG-004 toward Fixed when both parts land.  

## Sprints

| Sprint | Focus |
|---|---|
| UP12-S1 | List / filter / status honesty |
| UP12-S2 | Order detail Produce N Make UI + docs |
| UP12-S3 | Instructions Markdown toolbar + docs |
| UP12-S4 | Copy command placeholder dropdown + docs |
| UP12-S5 | Post-ship arrival step required + warnings + docs |
| UP12-S6+ | Only after new issues are planned into this package |

## Kickoff snippet (copy into implementation chat)

```
Implement Order Machine UP12-S1 only (Package 12).

Read:
- stikerts/wordpress v13/01-Update-Overview.md
- stikerts/wordpress v13/03-Update-Produce-N-Workflow-Visibility.md
- stikerts/wordpress v13/Update-12-Sprint-Plan.md (UP12-S1 section)
- stikerts/wordpress/BUGS.md → BUG-004
- .cursor/rules/order-machine-architecture.mdc

Do not start UP12-S2. No Pack bind on Internal. No schema unless proven necessary.
Done when: Produce N list row is not false “No workflow”; needs_workflow filter agrees; smoke tests/sprint-up12-s1-smoke.php passes.
```
