<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Card;
use EduLazaro\Laracards\CardGenerator;
use EduLazaro\Laracards\Contracts\Renderer;

class CardGeneratorTest extends TestCase
{
    private FakeRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new FakeRenderer;
        $this->app->instance(Renderer::class, $this->renderer);
        $this->app->forgetInstance(CardGenerator::class);
    }

    private function generator(): CardGenerator
    {
        return $this->app->make(CardGenerator::class);
    }

    private function card(array $overrides = []): Card
    {
        return Card::make('post')
            ->template('post')
            ->data(array_merge([
                'category_label' => 'DESARROLLO',
                'title' => 'Por qué un agente de IA es mucho más que llamar a un modelo',
                'author_name' => 'Edu Lazaro',
                'date_formatted' => '7 de agosto, 2026',
                'brand_label' => 'ANDORRADEV.COM',
            ], $overrides));
    }

    public function test_it_writes_the_card_at_the_configured_size(): void
    {
        $path = $this->generator()->generate($this->card());

        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertSame(1200, $this->renderer->calls[0]['width']);
        $this->assertSame(630, $this->renderer->calls[0]['height']);
    }

    public function test_the_composed_svg_is_well_formed_even_with_hostile_titles(): void
    {
        $this->generator()->generate($this->card([
            'title' => 'Deudas & "recargos" <urgente> por impago',
        ]));

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($this->renderer->lastSvg());
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($document, 'The composed SVG is not valid XML.');
    }

    public function test_the_title_is_split_into_tspans_with_a_fitted_size(): void
    {
        $this->generator()->generate($this->card());

        $svg = $this->renderer->lastSvg();

        $this->assertMatchesRegularExpression('/<tspan x="80" dy="0">/', $svg);
        $this->assertGreaterThan(1, substr_count($svg, '<tspan'));
        $this->assertStringNotContainsString('{{title_font_size}}', $svg);
    }

    public function test_the_background_is_embedded_as_a_data_uri(): void
    {
        $photo = $this->workspace . '/photo.png';
        imagepng(imagecreatetruecolor(8, 8), $photo);

        $this->generator()->generate($this->card()->background($photo));

        $this->assertStringContainsString('href="data:image/png;base64,', $this->renderer->lastSvg());
    }

    public function test_without_a_background_no_data_uri_is_left_behind(): void
    {
        $this->generator()->generate($this->card());

        $svg = $this->renderer->lastSvg();

        $this->assertStringNotContainsString('__BACKGROUND_URI__', $svg);
        $this->assertStringNotContainsString('base64', $svg);
    }

    /**
     * Regression: an <image> with an empty href makes librsvg abort with
     * signal 6, which only showed up in CI because no renderer was installed
     * locally. No source means no element at all.
     */
    public function test_no_image_element_is_emitted_without_a_source(): void
    {
        $this->generator()->generate($this->card());

        $svg = $this->renderer->lastSvg();

        $this->assertStringNotContainsString('href=""', $svg);
        $this->assertStringNotContainsString('<image', $svg);
    }

    public function test_the_background_layer_appears_only_when_there_is_one(): void
    {
        $photo = $this->workspace . '/photo.png';
        imagepng(imagecreatetruecolor(8, 8), $photo);

        $this->generator()->generate($this->card()->background($photo));

        $svg = $this->renderer->lastSvg();

        $this->assertStringContainsString('<image', $svg);
        $this->assertStringNotContainsString('href=""', $svg);
    }

    public function test_an_unchanged_card_is_not_rendered_twice(): void
    {
        $generator = $this->generator();

        $this->assertNotNull($generator->generate($this->card()));
        $this->assertNull($generator->generate($this->card()));
        $this->assertCount(1, $this->renderer->calls);
    }

    public function test_moving_the_publish_date_regenerates_the_card(): void
    {
        $generator = $this->generator();

        $generator->generate($this->card());
        $result = $generator->generate($this->card(['date_formatted' => '9 de agosto, 2026']));

        $this->assertNotNull($result, 'A card with the date printed on it must regenerate when the date moves.');
        $this->assertCount(2, $this->renderer->calls);
    }

    public function test_force_rerenders_an_unchanged_card(): void
    {
        $generator = $this->generator();

        $generator->generate($this->card());
        $generator->generate($this->card(), force: true);

        $this->assertCount(2, $this->renderer->calls);
    }

    public function test_it_cleans_up_the_temporary_svg(): void
    {
        $this->generator()->generate($this->card());

        $this->assertSame([], glob($this->workspace . '/tmp/*.svg') ?: []);
    }

    public function test_an_unknown_template_fails_loudly(): void
    {
        $this->expectExceptionMessageMatches("/template 'nope' is not configured/");

        $this->generator()->generate($this->card()->template('nope'));
    }
}
