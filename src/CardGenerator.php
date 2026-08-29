<?php

namespace EduLazaro\Laracards;

use EduLazaro\Laracards\Contracts\Renderer;
use EduLazaro\Laracards\Support\DataUri;
use EduLazaro\Laracards\Support\Manifest;
use EduLazaro\Laracards\Support\Raster;
use EduLazaro\Laracards\Support\Template;
use EduLazaro\Laracards\Support\Text;
use EduLazaro\Laracards\Support\TextFitter;
use RuntimeException;

/**
 * Composes a card: fits the text, embeds the background, fills the template
 * and hands the resulting SVG to the renderer.
 */
class CardGenerator
{
    /** @var array<string,TextFitter> */
    private array $fitters = [];

    public function __construct(
        private Renderer $renderer,
        private Manifest $manifest,
    ) {
    }

    /** Returns the output path, or null when the card was already up to date. */
    public function generate(Card $card, bool $force = false): ?string
    {
        $output = $card->outputPath();
        $config = $this->templateConfig($card->templateName());
        $templatePath = rtrim((string) config('laracards.paths.templates'), '/') . '/' . $config['file'];

        [$width, $height] = $this->size($config);
        $format = $this->format($card, $config);

        // The template and the output geometry are inputs like any other:
        // editing the SVG, or asking for a different size or format, has to
        // make every card drawn with it stale, or the change never lands.
        $fingerprint = sha1(implode('|', [
            $card->fingerprint(),
            is_file($templatePath) ? hash_file('xxh128', $templatePath) : '',
            $width, $height, $format,
        ]));

        if (! $force && ! $this->manifest->isStale($card->key(), $fingerprint, $output)) {
            return null;
        }

        $data = $this->escape($card->payload());
        $data += $this->fitBlocks($config, $card->payload());
        $data['background_uri'] = DataUri::fromFile($card->backgroundDriver()->resolve()) ?? '';

        $svg = (new Template($templatePath))->render($data);

        $this->ensureDirectory(dirname($output));
        $tempDir = (string) config('laracards.paths.temp');
        $this->ensureDirectory($tempDir);

        $tempSvg = rtrim($tempDir, '/') . '/' . $card->key() . '.svg';
        file_put_contents($tempSvg, $svg);

        // The renderers only write PNG, so anything else is converted after.
        $tempPng = $format === 'png' ? $output : $tempDir . '/' . $card->key() . '.png';

        try {
            $this->renderer->render($tempSvg, $tempPng, $width, $height);
            Raster::write($tempPng, $output, $format, (int) config('laracards.quality', 85));
        } finally {
            @unlink($tempSvg);
        }

        $this->manifest->put($card->key(), $fingerprint);

        return $output;
    }

    /**
     * Output size, per template with the global values as the fallback.
     *
     * @param  array<string,mixed>  $config
     * @return array{0:int,1:int}
     */
    private function size(array $config): array
    {
        return [
            (int) ($config['width'] ?? config('laracards.width', 1200)),
            (int) ($config['height'] ?? config('laracards.height', 630)),
        ];
    }

    /**
     * Output format. The extension of an explicit output path wins, because
     * asking for a .jpg and getting a PNG named .jpg would be worse than any
     * configuration precedence rule.
     *
     * @param  array<string,mixed>  $config
     */
    private function format(Card $card, array $config): string
    {
        return Raster::normalise(pathinfo($card->outputPath(), PATHINFO_EXTENSION))
            ?? Raster::normalise($config['format'] ?? null)
            ?? Raster::normalise(config('laracards.format'))
            ?? 'png';
    }

    public function manifest(): Manifest
    {
        return $this->manifest;
    }

    /**
     * Turns every configured fit block into {{key_tspans}} and {{key_font_size}}.
     *
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $payload
     * @return array<string,string>
     */
    private function fitBlocks(array $config, array $payload): array
    {
        $out = [];

        foreach ((array) ($config['fit'] ?? []) as $field => $rules) {
            $value = (string) ($payload[$field] ?? '');

            $fitter = $this->fitter((string) ($rules['font'] ?? 'default'));

            $result = $fitter->fit(
                $value,
                (int) ($rules['max_width'] ?? 1040),
                (int) ($rules['max_lines'] ?? 3),
                (array) ($rules['sizes'] ?? [72, 64, 56, 48]),
            );

            $x = (int) ($rules['x'] ?? 80);
            $lineHeight = (float) ($rules['line_height'] ?? 1.17);
            $dy = (int) round($result['size'] * $lineHeight);

            $tspans = [];

            foreach ($result['lines'] as $index => $line) {
                $tspans[] = sprintf(
                    '<tspan x="%d" dy="%d">%s</tspan>',
                    $x,
                    $index === 0 ? 0 : $dy,
                    Text::escape($line)
                );
            }

            $out[$field . '_tspans'] = implode("\n      ", $tspans);
            $out[$field . '_font_size'] = (string) $result['size'];
            $out[$field . '_line_count'] = (string) count($result['lines']);

            // SVG cannot do arithmetic, so the baseline of the first line is
            // computed here. Anchoring at the bottom keeps a one-line title and
            // a three-line one sitting on the same rule, which is the only way
            // a variable-length headline stays visually stable on the card.
            if (isset($rules['baseline'])) {
                $anchor = (string) ($rules['anchor'] ?? 'top');
                $lastOffset = (count($result['lines']) - 1) * $dy;
                $first = (int) $rules['baseline'] - ($anchor === 'bottom' ? $lastOffset : 0);

                $out[$field . '_baseline'] = (string) $first;

                // Where the block ends. Lets a template hang the next element
                // off the real bottom of a variable-height block, instead of
                // guessing a fixed position that only looks right sometimes.
                $out[$field . '_bottom'] = (string) ($first + $lastOffset);
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $payload @return array<string,string> */
    private function escape(array $payload): array
    {
        $out = [];

        foreach ($payload as $key => $value) {
            $out[$key] = Text::escape(is_scalar($value) ? (string) $value : '');
        }

        return $out;
    }

    private function fitter(string $font): TextFitter
    {
        if (isset($this->fitters[$font])) {
            return $this->fitters[$font];
        }

        $path = config("laracards.fonts.{$font}");

        if (! $path) {
            throw new RuntimeException("Laracards: no font configured under laracards.fonts.{$font}.");
        }

        return $this->fitters[$font] = new TextFitter((string) $path);
    }

    /** @return array<string,mixed> */
    private function templateConfig(string $name): array
    {
        $config = config("laracards.templates.{$name}");

        if (! is_array($config) || ! isset($config['file'])) {
            throw new RuntimeException("Laracards: template '{$name}' is not configured.");
        }

        return $config;
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
