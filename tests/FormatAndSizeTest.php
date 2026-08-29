<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Card;
use EduLazaro\Laracards\CardGenerator;
use EduLazaro\Laracards\Contracts\Renderer;

class FormatAndSizeTest extends TestCase
{
    private FakeRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new FakeRenderer;
        $this->app->instance(Renderer::class, $this->renderer);
        $this->app->forgetInstance(CardGenerator::class);
    }

    private function card(): Card
    {
        return Card::make('post')->template('post')->data(['title' => 'Un titulo']);
    }

    public function test_a_template_can_override_the_global_size(): void
    {
        config()->set('laracards.templates.post.width', 1080);
        config()->set('laracards.templates.post.height', 1080);

        $this->app->make(CardGenerator::class)->generate($this->card());

        $this->assertSame(1080, $this->renderer->calls[0]['width']);
        $this->assertSame(1080, $this->renderer->calls[0]['height']);
    }

    public function test_without_an_override_the_global_size_applies(): void
    {
        $this->app->make(CardGenerator::class)->generate($this->card());

        $this->assertSame(1200, $this->renderer->calls[0]['width']);
        $this->assertSame(630, $this->renderer->calls[0]['height']);
    }

    public function test_the_default_output_extension_follows_the_template_format(): void
    {
        config()->set('laracards.templates.post.format', 'jpg');

        $this->assertStringEndsWith('/post.jpg', $this->card()->outputPath());
    }

    public function test_png_is_the_default(): void
    {
        $this->assertStringEndsWith('/post.png', $this->card()->outputPath());
    }

    public function test_an_explicit_extension_decides_the_format(): void
    {
        config()->set('laracards.templates.post.format', 'png');

        $out = $this->workspace . '/out/explicit.jpg';
        $this->app->make(CardGenerator::class)->generate($this->card()->output($out));

        $this->assertFileExists($out);
        $this->assertSame(IMAGETYPE_JPEG, exif_imagetype($out));
    }

    public function test_it_writes_a_real_webp_when_asked(): void
    {
        if (! (gd_info()['WebP Support'] ?? false)) {
            $this->markTestSkipped('This GD build has no WebP support.');
        }

        $out = $this->workspace . '/out/card.webp';
        $this->app->make(CardGenerator::class)->generate($this->card()->output($out));

        $this->assertFileExists($out);
        $this->assertSame(IMAGETYPE_WEBP, exif_imagetype($out));
    }

    public function test_changing_the_format_makes_the_card_stale(): void
    {
        $generator = $this->app->make(CardGenerator::class);

        $generator->generate($this->card());
        $this->assertNull($generator->generate($this->card()));

        config()->set('laracards.templates.post.height', 900);

        $this->assertNotNull(
            $generator->generate($this->card()),
            'Changing the output geometry must make every card drawn with it stale.'
        );
    }

    public function test_no_temporary_files_are_left_behind(): void
    {
        $this->app->make(CardGenerator::class)->generate($this->card()->output($this->workspace . '/out/x.jpg'));

        $this->assertSame([], glob($this->workspace . '/tmp/*') ?: []);
    }
}
