<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\LaracardsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir() . '/laracards-' . bin2hex(random_bytes(4));
        mkdir($this->workspace . '/templates', 0775, true);

        copy(__DIR__ . '/../resources/templates/post.svg', $this->workspace . '/templates/post.svg');

        config()->set('laracards.paths.templates', $this->workspace . '/templates');
        config()->set('laracards.paths.output', $this->workspace . '/out');
        config()->set('laracards.paths.temp', $this->workspace . '/tmp');
        config()->set('laracards.paths.manifest', $this->workspace . '/manifest.json');
        config()->set('laracards.fonts.default', $this->font());
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->workspace);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [LaracardsServiceProvider::class];
    }

    protected function font(): string
    {
        $candidates = array_filter([
            getenv('LARACARDS_TEST_FONT') ?: null,
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        ]);

        foreach ($candidates as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        $this->markTestSkipped('No TrueType font found. Set LARACARDS_TEST_FONT to run these.');
    }

    protected function rmrf(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            is_dir($full) ? $this->rmrf($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
