<?php

namespace EduLazaro\Laracards;

use EduLazaro\Laracards\Console\Commands\GenerateCardsCommand;
use EduLazaro\Laracards\Contracts\Renderer;
use EduLazaro\Laracards\Renderers\ResvgRenderer;
use EduLazaro\Laracards\Renderers\RsvgRenderer;
use EduLazaro\Laracards\Support\Manifest;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class LaracardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laracards.php', 'laracards');

        $this->app->singleton(Renderer::class, function ($app) {
            $driver = (string) config('laracards.renderer', 'rsvg');
            $options = (array) config("laracards.renderers.{$driver}", []);

            return match ($driver) {
                'rsvg' => new RsvgRenderer((string) ($options['binary'] ?? 'rsvg-convert')),
                'resvg' => new ResvgRenderer(
                    (string) ($options['binary'] ?? 'resvg'),
                    (array) ($options['font_files'] ?? []),
                ),
                default => throw new InvalidArgumentException("Laracards: unknown renderer [{$driver}]."),
            };
        });

        $this->app->singleton(Manifest::class, fn () => new Manifest(
            (string) config('laracards.paths.manifest', storage_path('app/laracards/manifest.json'))
        ));

        $this->app->singleton(CardGenerator::class, fn ($app) => new CardGenerator(
            $app->make(Renderer::class),
            $app->make(Manifest::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GenerateCardsCommand::class]);

            $this->publishes([
                __DIR__ . '/../config/laracards.php' => config_path('laracards.php'),
            ], 'laracards-config');

            $this->publishes([
                __DIR__ . '/../resources/templates' => resource_path('cards'),
            ], 'laracards-templates');
        }
    }
}
