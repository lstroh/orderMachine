# Update — Markdown Step Instructions

*Package 9 · No schema change required (still TEXT). Self-contained.*

---

## 1. What this adds

Workflow step instructions (template defaults + product overrides) support **Markdown** and render with clearer typography on order detail (and a simple editor preview).

Today they are plain text only (`esc_html` on display).

## 2. Settled rules

| Topic | Decision |
|---|---|
| Format | **Markdown** (not TinyMCE / free HTML) |
| Where authored | Workflow step editor + product step-instruction overrides |
| Where shown | Order detail make/pack progress (same effective-resolution rules as today) |
| Safety | Parse Markdown → HTML, then **`wp_kses_post`** (or tighter allowlist) |
| Legacy content | Existing plain text keeps working (paragraphs / line breaks) |

## 3. Allowed Markdown (v1)

Soft default support:

- Paragraphs, line breaks  
- **Bold** / *italic*  
- Bullet and numbered lists  
- Links (`[label](url)`)  
- Inline `code`  
- Simple headings (`##` / `###`) — optional; style modestly  

Disallow: raw `<script>`, iframes, images remote unless already in `wp_kses_post` policy — prefer **no images** in v1 (**default: no images**).

## 4. UI

| Surface | Change |
|---|---|
| Step editor textarea | Label/help: “Markdown supported”; short cheatsheet |
| Product overrides | Same help text |
| Order detail `.som-step-instructions` | Rendered HTML; improved spacing, list indent, code style (CSS) |
| Optional | Collapsed “Preview” under editor textarea |

## 5. Implementation notes

- Add a small helper e.g. `SOM_Step_Instructions::render_html( $markdown )`.  
- Prefer a lightweight parser already acceptable in WP ecosystem, or `Parsedown`/similar vendored if needed — **open: use WP core if sufficient, else one small lib**.  
- Preserve `MAX_LENGTH` (5000) unless raised — **default: keep 5000**.  
- Do not run `esc_html` on the final HTML body; kses instead.

## 6. Out of scope

- TinyMCE / block editor  
- Per-product different Markdown dialect  
- Embedding the copy-command block inside instructions (copy-command stays its own gate)  

## 7. Open items

1. Parser choice (core vs small library).  
2. Allow images? **Default: no.**  
3. Preview in editor in v1? **Default: yes, simple.**  
