# Changelog

## 1.0.2

Three bugs that only surfaced once the package was driving a real site.

- **Text was measured a third too wide.** GD takes a point size and renders at 96 dpi, while an SVG `font-size` is in user units, so `imagettfbbox` reported 940px for a string that rasterised to 705px. Every headline wrapped earlier and set smaller than it needed to, and anything positioned from a measured width landed in the wrong place. Measuring now converts px to pt: the same sample reports 704px against 705px rendered.
- **A configured absolute path to the binary was treated as missing.** `ExecutableFinder` only searches `PATH` by name, so pinning the renderer to `/usr/bin/rsvg-convert`, which is how it is usually pinned in production, made `available()` return false and the command refuse to run. A binary whose name contains a separator is now checked directly on disk.
- **Editing a template regenerated nothing.** The fingerprint covered the payload and the background but not the SVG the card is drawn with, so a design change only landed with `--force`. The template hash is now part of the fingerprint.

Added:

- **Per-template size and format.** `width`, `height` and `format` can be set on a template, falling back to the values at the root of the config, so one project can serve a 1200x630 open graph card and a square social card from the same command. `png` (the default), `jpg` and `webp` are supported; the renderers only write PNG, so anything else is converted with GD. The extension of an explicit output path wins over the configured format, and both size and format are part of the fingerprint.
- `anchor` and `baseline` on a fit rule, exposed to the template as `{key}_baseline`. Anchoring at the bottom keeps a one-line headline and a three-line one sitting on the same rule.
- `{key}_bottom`, where a fitted block actually ends, so a template can hang the next element off a variable-height block instead of guessing a fixed position.
- `env` on a renderer, passed through to the process. Pointing `FONTCONFIG_FILE` at your own config is how you render with the fonts your project ships without rebuilding the container image.

## 1.0.1

- Lowered the framework floor from 11 to 10. Nothing in the package needed 11, and CI now builds its matrix from Laravel 10 up.
- Templates take `{{#key}}...{{/key}}` sections, dropped when the key has no value. An optional layer has to disappear rather than render empty: an `<image>` with `href=""` makes librsvg abort.
- A renderer that crashes now raises `RenderFailed` with the signal number instead of letting Symfony's `ProcessSignaledException` escape, and removes the partial file.

## 1.0.0

First release.
