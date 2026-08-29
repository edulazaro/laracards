<?php

namespace EduLazaro\Laracards\Renderers;

use EduLazaro\Laracards\Contracts\Renderer;
use EduLazaro\Laracards\Exceptions\RenderFailed;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Renders through rsvg-convert (librsvg2-bin on Debian and Ubuntu).
 *
 * Note that librsvg resolves fonts through fontconfig, so a template that
 * declares a brand face renders with it only if that face is installed on the
 * machine. When that is a problem, switch to ResvgRenderer and point it at the
 * font file instead.
 */
class RsvgRenderer implements Renderer
{
    public function __construct(private string $binary = 'rsvg-convert')
    {
    }

    public function render(string $svgPath, string $outputPath, int $width, int $height): void
    {
        $process = new Process([
            $this->binary,
            '--width', (string) $width,
            '--height', (string) $height,
            '--format', 'png',
            '--output', $outputPath,
            $svgPath,
        ]);

        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($outputPath)) {
            throw new RenderFailed(sprintf(
                'rsvg-convert failed for %s: %s',
                basename($svgPath),
                trim($process->getErrorOutput()) ?: 'no error output'
            ));
        }
    }

    public function available(): bool
    {
        return (new ExecutableFinder)->find($this->binary) !== null;
    }
}
