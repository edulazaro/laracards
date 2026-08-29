<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Card;
use EduLazaro\Laracards\CardGenerator;
use EduLazaro\Laracards\Exceptions\RenderFailed;
use EduLazaro\Laracards\Renderers\RsvgRenderer;

/**
 * The only tests that actually shell out. They skip when no binary is present,
 * so a laptop without librsvg still gets a green suite; CI installs it.
 */
class RendererTest extends TestCase
{
    private RsvgRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new RsvgRenderer;

        if (! $this->renderer->available()) {
            $this->markTestSkipped('rsvg-convert is not installed on this machine.');
        }
    }

    public function test_it_renders_a_png_at_the_requested_size(): void
    {
        $svg = $this->workspace . '/in.svg';
        $png = $this->workspace . '/out.png';

        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630"><rect width="1200" height="630" fill="#0a0a0a"/></svg>');

        $this->renderer->render($svg, $png, 1200, 630);

        $this->assertFileExists($png);
        [$width, $height] = getimagesize($png);
        $this->assertSame([1200, 630], [$width, $height]);
    }

    public function test_broken_svg_raises_instead_of_writing_a_corrupt_file(): void
    {
        $svg = $this->workspace . '/broken.svg';
        file_put_contents($svg, '<svg><unclosed>');

        $this->expectException(RenderFailed::class);

        $this->renderer->render($svg, $this->workspace . '/broken.png', 1200, 630);
    }

    public function test_the_bundled_template_renders_end_to_end(): void
    {
        $path = $this->app->make(CardGenerator::class)->generate(
            Card::make('post')->template('post')->data([
                'category_label' => 'DESARROLLO',
                'title' => 'Por qué un agente de IA es mucho más que llamar a un modelo',
                'author_name' => 'Edu Lazaro',
                'date_formatted' => '7 de agosto, 2026',
                'brand_label' => 'ANDORRADEV.COM',
            ])
        );

        $this->assertFileExists($path);
        $this->assertSame([1200, 630], array_slice(getimagesize($path), 0, 2));
    }
}
