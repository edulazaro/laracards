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

        file_put_contents($outputPath, 'fake-png');
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
