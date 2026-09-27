<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Card;
use EduLazaro\Laracards\CardGenerator;
use EduLazaro\Laracards\Contracts\Renderer;

/**
 * Upgrading must not regenerate the cards a project already committed: an SVG
 * card has to keep the exact fingerprint 1.0.2 gave it.
 */
class UpgradeCompatibilityTest extends TestCase
{
    public function test_an_svg_card_keeps_the_fingerprint_of_1_0_2(): void
    {
        $this->app->instance(Renderer::class, new FakeRenderer);
        $this->app->forgetInstance(CardGenerator::class);

        $card = Card::make('post')->template('post')->data(['title' => 'Un titulo']);
        $template = $this->workspace . '/templates/post.svg';

        $this->app->make(CardGenerator::class)->generate($card);
        $this->app->make(CardGenerator::class)->manifest()->save();

        $legacy = sha1(implode('|', [$card->fingerprint(), hash_file('xxh128', $template), 1200, 630, 'png']));
        $manifest = json_decode((string) file_get_contents($this->workspace . '/manifest.json'), true);

        $this->assertSame($legacy, $manifest['post'] ?? null);
    }
}
