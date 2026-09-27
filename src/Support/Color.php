<?php

namespace EduLazaro\Laracards\Support;

use InvalidArgumentException;

/**
 * Parses the CSS colour notations a config is likely to carry into the
 * red, green, blue and GD alpha that imagecolorallocatealpha() expects.
 */
class Color
{
    /** @return array{0:int,1:int,2:int,3:int} r, g, b and GD alpha (0 opaque, 127 transparent) */
    public static function parse(string $color): array
    {
        $color = strtolower(trim($color));

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $color, $m)) {
            $hex = $m[1];

            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            $opacity = strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;

            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), self::alpha($opacity)];
        }

        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([\d.]+)\s*)?\)$/', $color, $m)) {
            $opacity = isset($m[4]) ? (float) $m[4] : 1.0;

            return [min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]), self::alpha($opacity)];
        }

        throw new InvalidArgumentException("Laracards: unsupported colour [{$color}]. Use #rgb, #rrggbb, #rrggbbaa, rgb() or rgba().");
    }

    /** GD counts alpha backwards and in 0..127: 0 is opaque. */
    private static function alpha(float $opacity): int
    {
        return (int) round((1 - max(0.0, min(1.0, $opacity))) * 127);
    }
}
