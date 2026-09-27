<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Card;
use EduLazaro\Laracards\CardGenerator;
use EduLazaro\Laracards\Contracts\Renderer;
use RuntimeException;

class ImageTemplateTest extends TestCase
{
    private FakeRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new FakeRenderer;
        $this->app->instance(Renderer::class, $this->renderer);
        $this->app->forgetInstance(CardGenerator::class);

        $this->makeTemplate('og.png', [10, 10, 10, 0]);

        config()->set('laracards.templates.og', [
            'file' => 'og.png',
            'fit' => [
                'title' => [
                    'font' => 'default',
                    'x' => 600,
                    'max_width' => 1000,
                    'max_lines' => 3,
                    'sizes' => [44],
                    'line_height' => 1.2,
                    'baseline' => 300,
                    'color' => '#ffffff',
                    'align' => 'center',
                ],
            ],
        ]);
    }

    public function test_an_image_template_is_drawn_without_the_renderer(): void
    {
        $path = $this->generate(['title' => 'Una tarjeta sin SVG']);

        $this->assertSame([], $this->renderer->calls);
        $this->assertSame(IMAGETYPE_PNG, exif_imagetype($path));
        $this->assertSame([1200, 630], array_slice(getimagesize($path), 0, 2));
    }

    public function test_the_text_is_drawn_in_its_colour_and_centred_on_x(): void
    {
        $box = $this->inkBox($this->generate(['title' => 'Centrado']));

        $this->assertNotNull($box, 'No text was drawn.');
        $this->assertEqualsWithDelta(600, ($box['left'] + $box['right']) / 2, 4);
    }

    public function test_left_alignment_starts_the_line_at_x(): void
    {
        config()->set('laracards.templates.og.fit.title.align', 'left');
        config()->set('laracards.templates.og.fit.title.x', 100);

        $box = $this->inkBox($this->generate(['title' => 'Izquierda']));

        $this->assertEqualsWithDelta(100, $box['left'], 6);
    }

    public function test_a_stack_centres_the_whole_group_vertically(): void
    {
        config()->set('laracards.templates.og.fit.title.baseline', null);
        config()->set('laracards.templates.og.fit.subtitle', [
            'font' => 'default', 'x' => 600, 'max_width' => 1000, 'max_lines' => 1,
            'sizes' => [26], 'line_height' => 1.0, 'color' => '#ffffff', 'align' => 'center',
        ]);
        config()->set('laracards.templates.og.stack', ['fields' => ['title', 'subtitle'], 'center_y' => 315, 'gap' => 34]);

        $box = $this->inkBox($this->generate([
            'title' => 'Un titulo bastante largo que ocupa mas de una linea en la tarjeta de prueba',
            'subtitle' => 'Y un subtitulo',
        ]));

        $this->assertEqualsWithDelta(315, ($box['top'] + $box['bottom']) / 2, 12);
    }

    public function test_an_empty_field_leaves_the_rest_of_the_stack_centred(): void
    {
        config()->set('laracards.templates.og.fit.title.baseline', null);
        config()->set('laracards.templates.og.fit.subtitle', [
            'font' => 'default', 'x' => 600, 'sizes' => [26], 'color' => '#ffffff', 'align' => 'center',
        ]);
        config()->set('laracards.templates.og.stack', ['fields' => ['title', 'subtitle'], 'center_y' => 315, 'gap' => 34]);

        $box = $this->inkBox($this->generate(['title' => 'Solo titulo']));

        $this->assertEqualsWithDelta(315, ($box['top'] + $box['bottom']) / 2, 10);
    }

    public function test_a_block_without_a_baseline_or_stack_is_an_error(): void
    {
        config()->set('laracards.templates.og.fit.title.baseline', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("needs a 'baseline'");

        $this->generate(['title' => 'Sin posicion']);
    }

    public function test_the_card_background_shows_through_a_transparent_template(): void
    {
        $this->makeTemplate('overlay.png', [0, 0, 0, 127]);
        config()->set('laracards.templates.og.file', 'overlay.png');

        $photo = $this->workspace . '/photo.png';
        $image = imagecreatetruecolor(400, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
        imagepng($image, $photo);
        imagedestroy($image);

        $path = $this->generate(['title' => 'Con fondo'], fn (Card $card) => $card->background($photo));

        $image = imagecreatefrompng($path);
        $rgb = imagecolorat($image, 5, 5);
        imagedestroy($image);

        $this->assertSame([200, 30, 30], [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF]);
    }

    public function test_changing_the_template_config_makes_the_card_stale(): void
    {
        $generator = $this->app->make(CardGenerator::class);
        $card = Card::make('og-card')->template('og')->data(['title' => 'Estable']);

        $this->assertNotNull($generator->generate($card));
        $this->assertNull($generator->generate($card));

        config()->set('laracards.templates.og.fit.title.color', '#ff0000');

        $this->assertNotNull($generator->generate($card));
    }

    public function test_it_converts_to_jpg_like_the_svg_path(): void
    {
        $out = $this->workspace . '/out/og.jpg';
        $this->app->make(CardGenerator::class)->generate(
            Card::make('og-card')->template('og')->data(['title' => 'En JPG'])->output($out)
        );

        $this->assertSame(IMAGETYPE_JPEG, exif_imagetype($out));
    }

    public function test_it_works_when_no_renderer_binary_is_installed(): void
    {
        $this->app->instance(Renderer::class, new class implements Renderer {
            public function render(string $svgPath, string $outputPath, int $width, int $height): void
            {
                throw new RuntimeException('Should not be called.');
            }

            public function available(): bool
            {
                return false;
            }
        });
        $this->app->forgetInstance(CardGenerator::class);

        $this->assertFileExists($this->generate(['title' => 'Sin binario']));
    }

    public function test_an_svg_template_still_needs_the_binary(): void
    {
        $this->app->instance(Renderer::class, new class implements Renderer {
            public function render(string $svgPath, string $outputPath, int $width, int $height): void
            {
            }

            public function available(): bool
            {
                return false;
            }
        });
        $this->app->forgetInstance(CardGenerator::class);

        $this->expectExceptionMessage('renderer binary was not found');

        $this->app->make(CardGenerator::class)->generate(Card::make('post')->template('post')->data(['title' => 'SVG']));
    }

    public function test_the_command_runs_without_a_binary_when_every_template_is_an_image(): void
    {
        config()->set('laracards.templates', ['og' => config('laracards.templates.og')]);
        config()->set('laracards.sources', []);

        $this->app->instance(Renderer::class, new class implements Renderer {
            public function render(string $svgPath, string $outputPath, int $width, int $height): void
            {
            }

            public function available(): bool
            {
                return false;
            }
        });
        $this->app->forgetInstance(CardGenerator::class);

        $this->artisan('cards:generate')->assertSuccessful();
    }

    /** @param array<string,string> $data */
    private function generate(array $data, ?callable $tap = null): string
    {
        $card = Card::make('og-card')->template('og')->data($data);

        if ($tap) {
            $tap($card);
        }

        return (string) $this->app->make(CardGenerator::class)->generate($card, true);
    }

    /** @param array{0:int,1:int,2:int,3:int} $rgba */
    private function makeTemplate(string $name, array $rgba): void
    {
        $image = imagecreatetruecolor(1200, 630);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, ...$rgba));
        imagepng($image, $this->workspace . '/templates/' . $name);
        imagedestroy($image);
    }

    /**
     * Bounding box of the near-white pixels, i.e. where the text landed.
     *
     * @return array{left:int,right:int,top:int,bottom:int}|null
     */
    private function inkBox(string $path): ?array
    {
        $image = imagecreatefrompng($path);
        $box = null;

        for ($y = 0; $y < imagesy($image); $y += 1) {
            for ($x = 0; $x < imagesx($image); $x += 1) {
                $rgb = imagecolorat($image, $x, $y);

                if ((($rgb >> 16) & 0xFF) > 200 && (($rgb >> 8) & 0xFF) > 200 && ($rgb & 0xFF) > 200) {
                    $box = [
                        'left' => min($box['left'] ?? $x, $x),
                        'right' => max($box['right'] ?? $x, $x),
                        'top' => min($box['top'] ?? $y, $y),
                        'bottom' => max($box['bottom'] ?? $y, $y),
                    ];
                }
            }
        }

        imagedestroy($image);

        return $box;
    }
}
