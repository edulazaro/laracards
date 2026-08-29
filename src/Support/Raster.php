<?php

namespace EduLazaro\Laracards\Support;

use EduLazaro\Laracards\Exceptions\RenderFailed;

/**
 * Converts the renderer's output to the requested file format.
 *
 * Both rsvg-convert and resvg only write PNG, so anything else is a second
 * step. GD is already a requirement for measuring text, which is why the
 * conversion lives here instead of pulling in an image library.
 */
class Raster
{
    public const FORMATS = ['png', 'jpg', 'jpeg', 'webp'];

    /** Normalises an extension or format name, or null if unsupported. */
    public static function normalise(?string $format): ?string
    {
        $format = strtolower(trim((string) $format));
        $format = $format === 'jpeg' ? 'jpg' : $format;

        return in_array($format, ['png', 'jpg', 'webp'], true) ? $format : null;
    }

    /**
     * Writes $pngPath to $target in $format, replacing it when the format is
     * already PNG. Returns the path actually written.
     */
    public static function write(string $pngPath, string $target, string $format, int $quality = 85): string
    {
        if ($format === 'png') {
            if ($pngPath !== $target) {
                rename($pngPath, $target);
            }

            return $target;
        }

        $image = @imagecreatefrompng($pngPath);

        if ($image === false) {
            throw new RenderFailed("Laracards: could not read the rendered PNG at {$pngPath}.");
        }

        try {
            if ($format === 'webp') {
                imagepalettetotruecolor($image);
                $ok = imagewebp($image, $target, $quality);
            } else {
                // A JPEG has no alpha, so anything transparent has to land on
                // something. White is the least surprising choice for a card.
                $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
                imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
                imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                $ok = imagejpeg($flat, $target, $quality);
                imagedestroy($flat);
            }
        } finally {
            imagedestroy($image);
            @unlink($pngPath);
        }

        if (! $ok) {
            throw new RenderFailed("Laracards: could not write {$format} to {$target}.");
        }

        return $target;
    }
}
