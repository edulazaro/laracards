<?php

namespace EduLazaro\Laracards\Support;

use EduLazaro\Laracards\Exceptions\RenderFailed;

/**
 * Draws a card straight onto a raster template with GD, for templates that
 * are an image instead of an SVG.
 *
 * There is no SVG to say where the text goes, so every layer arrives already
 * laid out: lines, size, position and baseline come from the same fit rules
 * the SVG path uses, plus the colour and alignment an SVG would carry itself.
 * No binary is involved, which is the point on a server without librsvg.
 */
class ImageComposer
{
    public const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    public static function handles(string $templatePath): bool
    {
        return in_array(strtolower(pathinfo($templatePath, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * @param  array<int,array{lines:string[],size:int,x:int,dy:int,baseline:int,font:string,color:string,align:string}>  $layers
     */
    public function compose(string $templatePath, int $width, int $height, ?string $background, array $layers, string $outputPath): void
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        try {
            // The card background goes under the template, so a template with
            // transparent areas works as an overlay on a photo, the same way
            // the SVG path layers __BACKGROUND_URI__ beneath the design.
            if ($background !== null) {
                $this->cover($canvas, $this->open($background), $width, $height);
            }

            $this->cover($canvas, $this->open($templatePath), $width, $height);

            foreach ($layers as $layer) {
                $this->text($canvas, $layer);
            }

            if (! imagepng($canvas, $outputPath)) {
                throw new RenderFailed("Laracards: could not write the card to {$outputPath}.");
            }
        } finally {
            imagedestroy($canvas);
        }
    }

    /** @param array{lines:string[],size:int,x:int,dy:int,baseline:int,font:string,color:string,align:string} $layer */
    private function text(\GdImage $canvas, array $layer): void
    {
        if ($layer['lines'] === []) {
            return;
        }

        [$r, $g, $b, $a] = Color::parse($layer['color']);
        $color = imagecolorallocatealpha($canvas, $r, $g, $b, $a);
        $points = TextFitter::points($layer['size']);

        foreach ($layer['lines'] as $index => $line) {
            $x = $layer['x'];

            if ($layer['align'] !== 'left') {
                $box = imagettfbbox($points, 0, $layer['font'], $line);
                $lineWidth = $box === false ? 0 : abs($box[2] - $box[0]);
                $x -= $layer['align'] === 'center' ? (int) round($lineWidth / 2) : $lineWidth;
            }

            imagettftext($canvas, $points, 0, $x, $layer['baseline'] + $index * $layer['dy'], $color, $layer['font'], $line);
        }
    }

    /** Scales $image to fill the canvas, cropping the overflow from the centre. */
    private function cover(\GdImage $canvas, \GdImage $image, int $width, int $height): void
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);

        imagecopyresampled(
            $canvas, $image,
            0, 0,
            (int) round(($sourceWidth - $cropWidth) / 2), (int) round(($sourceHeight - $cropHeight) / 2),
            $width, $height,
            $cropWidth, $cropHeight,
        );

        imagedestroy($image);
    }

    private function open(string $path): \GdImage
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RenderFailed("Laracards: could not read the image at {$path}.");
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }
}
