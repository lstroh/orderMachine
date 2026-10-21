# Update — Instruction lists and editor preview

*Package 14 · UP14-S7 · BUG-006 · No schema. Operator report, 2026-10-19.*

---

## 1. What the screenshots show

Quality check instructions are Markdown, including `**Good sheet:**` and lines that start with `- `.

On the order, **Good sheet** is bold and the lines sit one under another with no bullets.

On the workflow editor, the source box shows the `- ` lines. A **Preview** button is in the toolbar. The rendered box is below the help text and the cheatsheet, and it starts hidden.

## 2. Cause

`render_html()` already returns a `<ul>`. WordPress admin sets `ul { list-style: none }`. `.som-step-instructions-body ul` sets margin and padding and does not set `list-style`, so the bullets disappear. The editor preview uses that same class, so a successful preview would look the same.

Numbered lists still show numbers, because admin CSS sets `ol { list-style-type: decimal }`.

This does not add a Markdown format. Package 12 already allows lists. The allowlist stays as it is.

## 3. Behaviour

| Topic | Rule |
|---|---|
| Bullets | `.som-step-instructions-body ul` uses `list-style: disc` and enough left inset that the marker is visible inside the step card |
| Numbers | `.som-step-instructions-body ol` uses `list-style: decimal` the same way |
| Where | Order detail (Make, Pack, Post-ship) and the editor preview, because they share the class |
| Preview | Stays a button. Clicking **Preview** opens the rendered box **directly under the textarea**, above the help line and the cheatsheet. **Hide preview** closes it. It starts closed on each visit |
| Toolbar | Unchanged buttons. No new formats |

## 4. Tests and docs

`tests/sprint-up14-s7-smoke.php`

- Rendered HTML for a `- ` list contains `<ul>` and `<li>`.
- `admin.css` sets a disc list style on `.som-step-instructions-body ul`.
- The preview markup in `render_editor_help()` is the first block under the textarea (before the cheatsheet).

`USER-REFERENCE.md`: instruction bullets show on the order; Preview sits under the instruction box. `FEATURES-AND-TESTING.md`: the same note, plus BUG-006 stays open until the wp-env pass.
