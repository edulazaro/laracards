<?php

namespace EduLazaro\Laracards\Support;

/** Embeds a local file into an SVG as a data URI. */
class DataUri
{
    /** Returns a data: URI for the file, or null when it does not exist. */
    public static function fromFile(?string $path): ?string
    {
        if (! $path || ! is_file($path)) {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'application/octet-stream';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}
