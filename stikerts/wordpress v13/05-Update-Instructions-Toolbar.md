# Update — Step instructions formatting toolbar

*Package 12 · No schema. Driven by operator request (easier styling of workflow step instructions).*

---

## 1. What operators asked for

More obvious ways to style step instructions: a **bold** button, **numbered** lists, bullet lists, and similar — instead of remembering Markdown syntax.

## 2. Today (Package 9 — locked)

| Topic | Today |
|---|---|
| Format | **Markdown** stored as TEXT; rendered via vendored Parsedown (safe mode) + tight `wp_kses` allowlist (`SOM_Step_Instructions::render_html`) |
| Supported output | Paragraphs, line breaks, bold, italic, bullet + numbered lists, links, inline code, `##` / `###` |
| Not supported | Images, raw HTML, TinyMCE / block editor |
| Editor help | “Markdown supported” line, collapsed **Markdown cheatsheet**, AJAX **Preview** / Hide preview (`SOM_Step_Instructions::render_editor_help`, `initMarkdownPreview` in `admin/assets/js/admin.js`) |
| Surfaces | Workflow step editor (`admin/views/workflow-step-editor.php`) + product step-instruction overrides (`admin/views/product-edit.php`); both wrap the textarea in `[data-som-md-editor]` with `.som-md-source` |

This package **keeps** the Markdown-only decision. The toolbar is a typing aid that inserts Markdown into the existing textarea — not a rich-text / WYSIWYG editor.

## 3. Settled decisions (operator, 2026-10-14)

| # | Topic | Decision |
|---|---|---|
| T1 | Editor model | **Markdown toolbar** above the existing textarea; Preview stays. **No** TinyMCE / WYSIWYG |
| T2 | Buttons | **Bold**, *Italic*, Bullet list, Numbered list, Heading H2, Heading H3, Link, Inline code, Preview toggle |
| T3 | New formatting | **None** — only what the renderer already supports (no underline, colour, tables, callouts, code blocks, etc.) |
| T4 | Where | **Both** workflow step editor and product step-instruction overrides |
| T5 | Keyboard shortcuts | **Not needed** |
| T6 | Multi-line lists | Selecting several lines + Numbered list → `1.` `2.` `3.` …; Bullet list → `- ` on each line |

## 4. Behaviour

### Toolbar

- One toolbar per `[data-som-md-editor]` block, rendered by the shared PHP helper (so both surfaces get it from one place).  
- Plain WP admin buttons (`button` / `button-small` style), each with `aria-label` + `title`; group in a `role="toolbar"` container with an accessible label (e.g. “Formatting”).  
- Works for step cards added dynamically in the workflow editor (delegated click handling, like the current Preview).  
- After an action: focus returns to the textarea; selection is kept around the inserted/wrapped text where sensible; textarea `input` event fires so any length/preview logic stays in sync.  
- Respect `maxlength` (5000): if an insertion would exceed it, do nothing (soft default: no partial insert) — server-side `sanitize` already rejects over-length.

### Button actions

| Button | With selection | Without selection |
|---|---|---|
| Bold | Wrap `**…**` | Insert `**bold text**` with the placeholder selected |
| Italic | Wrap `*…*` | Insert `*italic text*` with placeholder selected |
| Inline code | Wrap `` `…` `` | Insert `` `code` `` with placeholder selected |
| Link | Wrap as `[selection](https://)` with `https://` selected | Insert `[link text](https://)` with link text selected |
| Bullet list | Prefix every selected line with `- ` | Prefix current line with `- ` |
| Numbered list | Prefix selected lines `1. ` `2. ` … (sequential) | Prefix current line with `1. ` |
| Heading H2 / H3 | Prefix the line(s) with `## ` / `### ` (replace an existing `#`-prefix rather than stacking) | Same on current line |
| Preview | Existing toggle behaviour (moved into / aligned with the toolbar) | — |

Soft defaults:

| Topic | Default |
|---|---|
| Toggle off | Clicking Bullet / Numbered / Heading on lines that already have **that same** prefix removes it (simple toggle). Bold/italic/code do **not** try to unwrap (keep v1 simple) |
| Mixed list prefixes | Numbered on lines that already start with `- ` replaces the bullet with the number (and vice versa) |
| Blank lines in selection | Skip numbering/prefixing blank lines; numbering continues on the next non-blank line |
| Cheatsheet | Keep (collapsed) — still useful for links / headings reference |
| Help copy | Keep “Markdown supported. No images; no HTML editor. Max 5000 characters.” |

### Rendering

Unchanged. Output still goes through `render_html` → kses allowlist. No allowlist changes in this sprint.

## 5. Work items (detail)

1. Extend `SOM_Step_Instructions::render_editor_help()` (or a sibling `render_editor_toolbar()` called from the same places) to output the toolbar **before** the textarea, or restructure the two surfaces so the toolbar sits directly above `.som-md-source`.  
2. Add small vanilla JS in `admin/assets/js/admin.js` (`initMarkdownToolbar`): delegated handler on `[data-som-md-action]`, pure helpers for wrap / line-prefix / numbered-list so they can be reasoned about in isolation.  
3. CSS in `admin/assets/css/admin.css` for toolbar spacing / button group.  
4. i18n strings for button labels via `wp_localize_script` (`somAdmin.i18n`) or PHP-rendered labels.  
5. Smoke `tests/sprint-up12-s3-smoke.php`: helper outputs toolbar markup + each `data-som-md-action` on both surfaces; `render_html` still produces `<strong>`, `<ol>`, `<ul>`, `<h2>`, `<h3>`, `<a>`, `<code>` for toolbar-shaped Markdown (regression); no new allowed tags.  
6. Docs: USER-REFERENCE (step instructions bullet) + USER-GUIDE glossary line + FEATURES-AND-TESTING manual check (toolbar on workflow editor + product override; numbered multi-line).

## 6. Out of scope

- WYSIWYG / TinyMCE / block editor (would overturn Package 9)  
- Keyboard shortcuts (T5)  
- New rendered formatting: underline, colour, highlight, tables, callouts, code blocks, dividers, images (T3)  
- Toolbar on other textareas (order notes, copy command, tasks)  
- Raising `MAX_LENGTH`  
