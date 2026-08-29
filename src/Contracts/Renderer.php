<?php

namespace EduLazaro\Laracards\Contracts;

/** Turns a composed SVG file into a raster image on disk. */
interface Renderer
{
    /**
     * Renders the SVG at $svgPath into $outputPath at the given size.
     *
     * @throws \EduLazaro\Laracards\Exceptions\RenderFailed
     */
    public function render(string $svgPath, string $outputPath, int $width, int $height): void;

    /** Whether the underlying binary is available on this machine. */
    public function available(): bool;
}
