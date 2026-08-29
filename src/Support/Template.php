<?php

namespace EduLazaro\Laracards\Support;

use RuntimeException;

/** An SVG template plus its placeholder substitution. */
class Template
{
    public function __construct(private string $path)
    {
        if (! is_file($this->path)) {
            throw new RuntimeException("Laracards: template not found at {$this->path}.");
        }
    }

    /**
     * Replaces both {{key}} and __KEY__ placeholders.
     *
     * The __KEY__ form exists because data URIs are not XML-escapable content
     * and read badly inside curly braces; it is what the logo and background
     * layers use.
     *
     * @param  array<string,string|int|float|null>  $data
     */
    public function render(array $data): string
    {
        $svg = (string) file_get_contents($this->path);

        foreach ($data as $key => $value) {
            $svg = str_replace(
                ['{{' . $key . '}}', '__' . strtoupper($key) . '__'],
                (string) $value,
                $svg
            );
        }

        // Any placeholder left unfilled would render as literal text.
        return preg_replace('/\{\{[a-z0-9_]+\}\}|__[A-Z0-9_]+__/', '', $svg) ?? $svg;
    }
}
