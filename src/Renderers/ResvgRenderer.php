<?php

namespace EduLazaro\Laracards\Renderers;

use EduLazaro\Laracards\Contracts\Renderer;
use EduLazaro\Laracards\Exceptions\RenderFailed;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Renders through resvg, a self-contained Rust binary.
 *
 * Worth switching to when librsvg chokes on a template, or when the card has
 * to use a font that is not installed system-wide: resvg takes --font-file, so
 * the same TTF your site ships in public/fonts also renders the card, and the
 * output is identical on your laptop and on the server.
 */
class ResvgRenderer implements Renderer
{
    /**
     * @param  string[]  $fontFiles
     * @param  array<string,string>  $env  Extra environment for the process.
     */
    public function __construct(
        private string $binary = 'resvg',
        private array $fontFiles = [],
        private array $env = [],
    ) {
    }

    public function render(string $svgPath, string $outputPath, int $width, int $height): void
    {
        $command = [
            $this->binary,
            '--width', (string) $width,
            '--height', (string) $height,
        ];

        foreach ($this->fontFiles as $fontFile) {
            if (is_file($fontFile)) {
                $command[] = '--use-font-file';
                $command[] = $fontFile;
            }
        }

        $command[] = $svgPath;
        $command[] = $outputPath;

        $process = new Process($command, null, $this->env);
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
                'resvg failed for %s: %s',
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
