# Parsedown (vendored)

**Choice (UP9-S3 / O9):** WordPress has no core Markdown parser. This plugin vendors [Parsedown](https://github.com/erusev/parsedown) **1.7.4** (MIT) for step-instruction Markdown.

- File: `Parsedown.php` (class `Parsedown`)
- License: `LICENSE.txt`
- Used only via `SOM_Step_Instructions::render_html()`.

Do not add Composer here — keep a single small file under `includes/` so release zips (allowlist `includes/`) ship the parser.
