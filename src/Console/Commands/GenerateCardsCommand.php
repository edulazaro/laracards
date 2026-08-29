<?php

namespace EduLazaro\Laracards\Console\Commands;

use EduLazaro\Laracards\Card;
use EduLazaro\Laracards\CardGenerator;
use EduLazaro\Laracards\Contracts\CardSource;
use EduLazaro\Laracards\Contracts\Renderer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Generates every card of every registered source.
 *
 * One command for all content types: what used to be a command per kind of
 * content is now a CardSource per kind of content.
 */
class GenerateCardsCommand extends Command
{
    protected $signature = 'cards:generate
                            {--source=* : Only these sources (defaults to all registered)}
                            {--only=* : Only cards whose key matches one of these}
                            {--force : Regenerate even when nothing changed}
                            {--dry-run : List what would be generated, write nothing}';

    protected $description = 'Generate social cards and blog covers from SVG templates';

    public function handle(CardGenerator $generator, Renderer $renderer): int
    {
        if (! $renderer->available()) {
            $this->error('The configured renderer binary was not found. Install librsvg2-bin (rsvg-convert) or resvg.');

            return self::FAILURE;
        }

        $sources = $this->resolveSources();

        if ($sources === []) {
            $this->warn('No card sources registered. Add one under `sources` in config/laracards.php.');

            return self::SUCCESS;
        }

        $only = (array) $this->option('only');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($sources as $name => $source) {
            $this->line("<fg=cyan>{$name}</>");

            foreach ($source->cards() as $card) {
                if (! $card instanceof Card) {
                    continue;
                }

                if ($only !== [] && ! in_array($card->key(), $only, true)) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("  <fg=gray>[DRY]</>   {$card->key()}");
                    continue;
                }

                try {
                    $path = $generator->generate($card, $force);
                } catch (Throwable $e) {
                    $this->line("  <fg=red>[FAIL]</>  {$card->key()} — {$e->getMessage()}");
                    $failed++;
                    continue;
                }

                if ($path === null) {
                    $this->line("  <fg=gray>[SKIP]</>  {$card->key()} (up to date)");
                    $skipped++;
                    continue;
                }

                $size = number_format(filesize($path) / 1024, 1);
                $this->line("  <fg=green>[OK]</>    {$card->key()} — {$size} KB");
                $generated++;
            }
        }

        if (! $dryRun) {
            $generator->manifest()->save();
        }

        $this->newLine();
        $this->line("Generated {$generated}, skipped {$skipped}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string,CardSource> */
    private function resolveSources(): array
    {
        $registered = (array) config('laracards.sources', []);
        $requested = (array) $this->option('source');

        if ($requested !== []) {
            $registered = array_intersect_key($registered, array_flip($requested));
        }

        $sources = [];

        foreach ($registered as $name => $class) {
            $instance = app($class);

            if ($instance instanceof CardSource) {
                $sources[$name] = $instance;
            }
        }

        return $sources;
    }
}
