<?php

namespace EduLazaro\Laracards;

use EduLazaro\Laracards\Backgrounds\LocalBackground;
use EduLazaro\Laracards\Backgrounds\NullBackground;
use EduLazaro\Laracards\Backgrounds\UnsplashBackground;
use EduLazaro\Laracards\Contracts\Background;

/**
 * A card waiting to be generated.
 *
 * Everything a card needs is declared here and nothing is resolved until
 * generate() runs, so a CardSource can hand back thousands of these cheaply
 * and the command decides which ones are actually stale.
 */
class Card
{
    private string $template = 'post';

    /** @var array<string,string|int|float|null> */
    private array $data = [];

    private ?Background $background = null;

    private ?string $output = null;

    private function __construct(private string $key)
    {
    }

    /** $key identifies the card in the manifest, usually the content slug. */
    public static function make(string $key): self
    {
        return new self($key);
    }

    public function template(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    /** @param array<string,string|int|float|null> $data */
    public function data(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /** Accepts a Background, a local path, or null for no background layer. */
    public function background(Background|string|null $background): self
    {
        $this->background = match (true) {
            $background instanceof Background => $background,
            is_string($background) => new LocalBackground($background),
            default => new NullBackground,
        };

        return $this;
    }

    /** Convenience for the Unsplash driver, wired from config. */
    public function unsplash(string $query, string $orientation = 'landscape'): self
    {
        $config = (array) config('laracards.backgrounds.unsplash', []);

        return $this->background(new UnsplashBackground(
            query: $query,
            cachePath: (string) ($config['cache'] ?? storage_path('app/laracards/backgrounds')),
            accessKey: $config['access_key'] ?? null,
            orientation: $orientation,
        ));
    }

    public function output(string $path): self
    {
        $this->output = $path;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function templateName(): string
    {
        return $this->template;
    }

    /** @return array<string,string|int|float|null> */
    public function payload(): array
    {
        return $this->data;
    }

    public function backgroundDriver(): Background
    {
        return $this->background ??= new NullBackground;
    }

    public function outputPath(): string
    {
        return $this->output ??= rtrim((string) config('laracards.paths.output'), '/') . '/' . $this->key . '.png';
    }

    /**
     * Everything that, if changed, should produce a different image.
     *
     * This is what removes the "remember to delete the PNG by hand when the
     * date changes" step: the date is part of the payload, so a changed date
     * is a changed fingerprint and the card regenerates on its own.
     */
    public function fingerprint(): string
    {
        return sha1(json_encode([
            'template' => $this->template,
            'data' => $this->data,
            'background' => $this->backgroundDriver()->fingerprint(),
        ], JSON_THROW_ON_ERROR));
    }

    public function generate(bool $force = false): ?string
    {
        return app(CardGenerator::class)->generate($this, $force);
    }
}
