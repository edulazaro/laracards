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
    /** @param array<string,string> $env Extra environment for the process. */
    public function __construct(
        private string $binary = 'rsvg-convert',
        private array $env = [],
    ) {
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
        ], null, $this->env);

        $process->setTimeout(60);
        $process->run();

        // A crashed renderer is signaled, not merely unsuccessful, and asking
        // Symfony for the exit code of a signaled process throws. Convert it
        // here so callers only ever have to handle RenderFailed.
        if ($process->hasBeenSignaled()) {
            @unlink($outputPath);

            throw new RenderFailed(sprintf(
                '%s crashed on %s (signal %d). %s',
                $this->binary,
                basename($svgPath),
                $process->getTermSignal(),
                trim($process->getErrorOutput()) ?: 'No error output.'
            ));
        }

        if (! $process->isSuccessful() || ! is_file($outputPath)) {
            @unlink($outputPath);

            throw new RenderFailed(sprintf(
                'rsvg-convert failed for %s: %s',
                basename($svgPath),
                trim($process->getErrorOutput()) ?: 'no error output'
            ));
        }
    }

    public function available(): bool
    {
        // ExecutableFinder only searches PATH by name, so a configured absolute
        // path (which is how the binary is usually pinned in production) comes
        // back as null even when the file is perfectly executable.
        if (str_contains($this->binary, DIRECTORY_SEPARATOR)) {
            return is_file($this->binary) && is_executable($this->binary);
        }

        return (new ExecutableFinder)->find($this->binary) !== null;
    }
}
