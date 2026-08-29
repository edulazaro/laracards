<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Contracts\Renderer;

/** Captures the composed SVG instead of shelling out to a binary. */
class FakeRenderer implements Renderer
{
    public array $calls = [];

    public function render(string $svgPath, string $outputPath, int $width, int $height): void
    {
        $this->calls[] = [
            'svg' => (string) file_get_contents($svgPath),
            'output' => $outputPath,
            'width' => $width,
            'height' => $height,
        ];

        // A real PNG of the requested size: anything downstream that converts
        // the render has to be exercised for real, not handed a placeholder.
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 10, 10));
        imagepng($image, $outputPath);
        imagedestroy($image);
    }

    public function available(): bool
    {
        return true;
    }

    public function lastSvg(): string
    {
        return end($this->calls)['svg'] ?? '';
    }
}
