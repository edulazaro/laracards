<?php

namespace EduLazaro\Laracards\Support;

use RuntimeException;

/** An SVG template plus its placeholder and section substitution. */
class Template
{
    public function __construct(private string $path)
    {
        if (! is_file($this->path)) {
            throw new RuntimeException("Laracards: template not found at {$this->path}.");
        }
    }

    /**
     * Resolves sections, then placeholders.
     *
     * Placeholders come in two forms: {{key}} for text, and __KEY__ for values
     * that are not XML-escapable content, such as data URIs.
     *
     * Sections are `{{#key}}...{{/key}}` and are dropped when the key has no
     * value. They exist because an optional layer has to disappear entirely
     * rather than render empty: an `<image>` with `href=""` makes librsvg
     * abort, so "no background" cannot mean "background with no source".
     *
     * @param  array<string,string|int|float|null>  $data
     */
    public function render(array $data): string
    {
        $svg = (string) file_get_contents($this->path);

        $svg = preg_replace_callback(
            '/\{\{#([a-z0-9_]+)\}\}(.*?)\{\{\/\1\}\}/s',
            fn (array $m) => trim((string) ($data[$m[1]] ?? '')) === '' ? '' : $m[2],
            $svg
        ) ?? $svg;

        foreach ($data as $key => $value) {
            $svg = str_replace(
                ['{{' . $key . '}}', '__' . strtoupper($key) . '__'],
                (string) $value,
                $svg
            );
        }

        // Any placeholder left unfilled would render as literal text.
        return preg_replace('/\{\{[#\/]?[a-z0-9_]+\}\}|__[A-Z0-9_]+__/', '', $svg) ?? $svg;
    }
}
