<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Card;

class CardTest extends TestCase
{
    private function card(array $overrides = []): Card
    {
        return Card::make('post')
            ->template('post')
            ->data(array_merge([
                'title' => 'Un titulo',
                'date_formatted' => '5 de abril, 2026',
            ], $overrides));
    }

    public function test_the_same_content_yields_the_same_fingerprint(): void
    {
        $this->assertSame($this->card()->fingerprint(), $this->card()->fingerprint());
    }

    public function test_changing_the_printed_date_changes_the_fingerprint(): void
    {
        $this->assertNotSame(
            $this->card()->fingerprint(),
            $this->card(['date_formatted' => '6 de abril, 2026'])->fingerprint(),
        );
    }

    public function test_changing_the_background_changes_the_fingerprint(): void
    {
        $photo = $this->workspace . '/photo.png';
        imagepng(imagecreatetruecolor(4, 4), $photo);

        $this->assertNotSame(
            $this->card()->fingerprint(),
            $this->card()->background($photo)->fingerprint(),
        );
    }

    public function test_a_background_whose_bytes_changed_changes_the_fingerprint(): void
    {
        $photo = $this->workspace . '/photo.png';

        imagepng(imagecreatetruecolor(4, 4), $photo);
        $before = $this->card()->background($photo)->fingerprint();

        imagepng(imagecreatetruecolor(8, 8), $photo);
        $after = $this->card()->background($photo)->fingerprint();

        $this->assertNotSame($before, $after);
    }

    public function test_the_output_path_defaults_to_the_configured_directory(): void
    {
        $this->assertSame(
            $this->workspace . '/out/post.png',
            $this->card()->outputPath(),
        );
    }
}
