<?php

namespace EduLazaro\Laracards;

use EduLazaro\Laracards\Contracts\Renderer;
use EduLazaro\Laracards\Exceptions\RenderFailed;
use EduLazaro\Laracards\Support\DataUri;
use EduLazaro\Laracards\Support\ImageComposer;
use EduLazaro\Laracards\Support\Manifest;
use EduLazaro\Laracards\Support\Raster;
use EduLazaro\Laracards\Support\Template;
use EduLazaro\Laracards\Support\Text;
use EduLazaro\Laracards\Support\TextFitter;
use RuntimeException;

/**
 * Composes a card: fits the text, embeds the background, fills the template
 * and hands the resulting SVG to the renderer.
 *
 * A template that is an image instead of an SVG takes the other road: the
 * same fitted text is drawn onto it with GD, with no renderer binary at all.
 */
class CardGenerator
{
    /** @var array<string,TextFitter> */
    private array $fitters = [];

    private ?bool $rendererAvailable = null;

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
        $templatePath = $this->templatePath($config);
        $isImage = ImageComposer::handles($templatePath);

        [$width, $height] = $this->size($config);
        $format = $this->format($card, $config);

        // The template and the output geometry are inputs like any other:
        // editing the SVG, or asking for a different size or format, has to
        // make every card drawn with it stale, or the change never lands.
        // An image template keeps its colours and positions in the config
        // rather than in the file, so for those the config is an input too.
        $parts = [
            $card->fingerprint(),
            is_file($templatePath) ? hash_file('xxh128', $templatePath) : '',
            $width, $height, $format,
        ];

        // Appended only for image templates: an SVG card keeps the exact
        // fingerprint it had before, so upgrading regenerates nothing.
        if ($isImage) {
            $parts[] = sha1((string) json_encode($config));
        }

        $fingerprint = sha1(implode('|', $parts));

        if (! $force && ! $this->manifest->isStale($card->key(), $fingerprint, $output)) {
            return null;
        }

        $layout = $this->layout($config, $card->payload());

        $this->ensureDirectory(dirname($output));
        $tempDir = (string) config('laracards.paths.temp');
        $this->ensureDirectory($tempDir);

        // Both roads end in a PNG, so anything else is converted after.
        $tempPng = $format === 'png' ? $output : $tempDir . '/' . $card->key() . '.png';

        if ($isImage) {
            (new ImageComposer)->compose(
                $templatePath,
                $width,
                $height,
                $card->backgroundDriver()->resolve(),
                $this->layers($card->templateName(), $layout),
                $tempPng,
            );
        } else {
            $this->renderSvg($card, $templatePath, $layout, $tempDir, $tempPng, $width, $height);
        }

        Raster::write($tempPng, $output, $format, (int) config('laracards.quality', 85));

        $this->manifest->put($card->key(), $fingerprint);

        return $output;
    }

    /** Whether a template is drawn with GD instead of the renderer binary. */
    public function usesImage(string $template): bool
    {
        return ImageComposer::handles($this->templatePath($this->templateConfig($template)));
    }

    /**
     * @param  array<string,array<string,mixed>>  $layout
     */
    private function renderSvg(Card $card, string $templatePath, array $layout, string $tempDir, string $tempPng, int $width, int $height): void
    {
        if (! ($this->rendererAvailable ??= $this->renderer->available())) {
            throw new RenderFailed('Laracards: the renderer binary was not found. Install librsvg2-bin (rsvg-convert) or resvg, or use an image template.');
        }

        $data = $this->escape($card->payload());
        $data += $this->placeholders($layout);
        $data['background_uri'] = DataUri::fromFile($card->backgroundDriver()->resolve()) ?? '';

        $svg = (new Template($templatePath))->render($data);

        $tempSvg = rtrim($tempDir, '/') . '/' . $card->key() . '.svg';
        file_put_contents($tempSvg, $svg);

        try {
            $this->renderer->render($tempSvg, $tempPng, $width, $height);
        } finally {
            @unlink($tempSvg);
        }
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
     * Fits every configured block and works out where it sits.
     *
     * Shared by both roads: the SVG path turns it into placeholders and the
     * image path draws it, so a card lays out the same way either way.
     *
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $payload
     * @return array<string,array<string,mixed>>
     */
    private function layout(array $config, array $payload): array
    {
        $layout = [];

        foreach ((array) ($config['fit'] ?? []) as $field => $rules) {
            $rules = (array) $rules;
            $fitter = $this->fitter((string) ($rules['font'] ?? 'default'));

            $result = $fitter->fit(
                (string) ($payload[$field] ?? ''),
                (int) ($rules['max_width'] ?? 1040),
                (int) ($rules['max_lines'] ?? 3),
                (array) ($rules['sizes'] ?? [72, 64, 56, 48]),
            );

            $dy = (int) round($result['size'] * (float) ($rules['line_height'] ?? 1.17));
            $lastOffset = max(0, count($result['lines']) - 1) * $dy;
            $baseline = null;

            // SVG cannot do arithmetic, so the baseline of the first line is
            // computed here. Anchoring at the bottom keeps a one-line title and
            // a three-line one sitting on the same rule, which is the only way
            // a variable-length headline stays visually stable on the card.
            if (isset($rules['baseline'])) {
                $anchor = (string) ($rules['anchor'] ?? 'top');
                $baseline = (int) $rules['baseline'] - ($anchor === 'bottom' ? $lastOffset : 0);
            }

            $layout[$field] = [
                'lines' => $result['lines'],
                'size' => $result['size'],
                'x' => (int) ($rules['x'] ?? 80),
                'dy' => $dy,
                'baseline' => $baseline,
                'last_offset' => $lastOffset,
                'fitter' => $fitter,
                'rules' => $rules,
            ];
        }

        return $this->stack($config, $layout);
    }

    /**
     * Centres a group of blocks as one unit, each below the previous.
     *
     * A title that runs to one line or three, with a subtitle that may or may
     * not be there, only stays centred if the whole group is measured first.
     * A stacked block's baseline is computed here and wins over its own.
     *
     * @param  array<string,mixed>  $config
     * @param  array<string,array<string,mixed>>  $layout
     * @return array<string,array<string,mixed>>
     */
    private function stack(array $config, array $layout): array
    {
        $stack = (array) ($config['stack'] ?? []);
        $fields = array_values(array_filter(
            (array) ($stack['fields'] ?? []),
            fn ($field) => isset($layout[$field]) && $layout[$field]['lines'] !== [],
        ));

        if ($fields === []) {
            return $layout;
        }

        $gap = (int) ($stack['gap'] ?? 24);
        $total = $gap * (count($fields) - 1);

        foreach ($fields as $field) {
            $total += count($layout[$field]['lines']) * $layout[$field]['dy'];
        }

        $top = (int) round((float) ($stack['center_y'] ?? 315) - $total / 2);

        foreach ($fields as $field) {
            $block = $layout[$field];
            $metrics = $block['fitter']->metrics($block['size']);

            // The first baseline sits inside the first line box so the glyphs,
            // not the box, are what end up centred.
            $offset = (int) round(($block['dy'] - $metrics['ascent'] - $metrics['descent']) / 2) + $metrics['ascent'];

            $layout[$field]['baseline'] = $top + $offset;
            $top += count($block['lines']) * $block['dy'] + $gap;
        }

        return $layout;
    }

    /**
     * Turns the layout into {{key_tspans}}, {{key_font_size}} and friends.
     *
     * @param  array<string,array<string,mixed>>  $layout
     * @return array<string,string>
     */
    private function placeholders(array $layout): array
    {
        $out = [];

        foreach ($layout as $field => $block) {
            $tspans = [];

            foreach ($block['lines'] as $index => $line) {
                $tspans[] = sprintf(
                    '<tspan x="%d" dy="%d">%s</tspan>',
                    $block['x'],
                    $index === 0 ? 0 : $block['dy'],
                    Text::escape($line)
                );
            }

            $out[$field . '_tspans'] = implode("\n      ", $tspans);
            $out[$field . '_font_size'] = (string) $block['size'];
            $out[$field . '_line_count'] = (string) count($block['lines']);

            if ($block['baseline'] !== null) {
                $out[$field . '_baseline'] = (string) $block['baseline'];

                // Where the block ends. Lets a template hang the next element
                // off the real bottom of a variable-height block, instead of
                // guessing a fixed position that only looks right sometimes.
                $out[$field . '_bottom'] = (string) ($block['baseline'] + $block['last_offset']);
            }
        }

        return $out;
    }

    /**
     * Turns the layout into drawable layers for an image template, which has
     * no SVG to carry colour, alignment or position.
     *
     * @param  array<string,array<string,mixed>>  $layout
     * @return array<int,array{lines:string[],size:int,x:int,dy:int,baseline:int,font:string,color:string,align:string}>
     */
    private function layers(string $template, array $layout): array
    {
        $layers = [];

        foreach ($layout as $field => $block) {
            if ($block['lines'] === []) {
                continue;
            }

            if ($block['baseline'] === null) {
                throw new RuntimeException("Laracards: '{$field}' in the image template '{$template}' needs a 'baseline', or to be listed under 'stack'.");
            }

            $layers[] = [
                'lines' => $block['lines'],
                'size' => $block['size'],
                'x' => $block['x'],
                'dy' => $block['dy'],
                'baseline' => $block['baseline'],
                'font' => $block['fitter']->fontPath(),
                'color' => (string) ($block['rules']['color'] ?? '#ffffff'),
                'align' => in_array($block['rules']['align'] ?? 'left', ['left', 'center', 'right'], true)
                    ? (string) ($block['rules']['align'] ?? 'left')
                    : 'left',
            ];
        }

        return $layers;
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

    /** @param array<string,mixed> $config */
    private function templatePath(array $config): string
    {
        return rtrim((string) config('laracards.paths.templates'), '/') . '/' . $config['file'];
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
