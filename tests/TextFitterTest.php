<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Support\TextFitter;
use PHPUnit\Framework\TestCase;

class TextFitterTest extends TestCase
{
    private function fitter(): TextFitter
    {
        $candidates = array_filter([
            getenv('LARACARDS_TEST_FONT') ?: null,
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        ]);

        foreach ($candidates as $font) {
            if (is_file($font)) {
                return new TextFitter($font);
            }
        }

        $this->markTestSkipped('No TrueType font found. Set LARACARDS_TEST_FONT to run these.');
    }

    public function test_it_keeps_every_line_within_the_column(): void
    {
        $fitter = $this->fitter();
        $result = $fitter->fit('MMMMMMMM MMMMMMMM MMMMMMMM', 1040, 3, [82, 72, 64, 56, 48]);

        foreach ($result['lines'] as $line) {
            $this->assertLessThanOrEqual(1040, $fitter->width($line, $result['size']));
        }
    }

    public function test_it_prefers_the_largest_size_that_fits(): void
    {
        $result = $this->fitter()->fit('Corto', 1040, 3, [82, 72, 64]);

        $this->assertSame(82, $result['size']);
        $this->assertCount(1, $result['lines']);
    }

    public function test_it_never_exceeds_the_line_budget(): void
    {
        $result = $this->fitter()->fit(str_repeat('palabra ', 80), 1040, 3, [82, 72, 64, 56, 48]);

        $this->assertLessThanOrEqual(3, count($result['lines']));
        $this->assertStringEndsWith('...', end($result['lines']));
    }

    public function test_empty_text_produces_no_lines(): void
    {
        $this->assertSame([], $this->fitter()->fit('   ', 1040, 3, [82, 48])['lines']);
    }
}
