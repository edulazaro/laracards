<?php

namespace EduLazaro\Laracards\Support;

/** Escapes a string so it is safe inside SVG/XML markup. */
class Text
{
    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
