<?php

namespace EduLazaro\Laracards\Support;

use RuntimeException;

/**
 * Wraps a title into a fixed number of lines and picks the largest font size
 * that still fits.
 *
 * The width is MEASURED with the real font through GD instead of estimated
 * from a character count. That difference is why "IIIIII" and "MMMMMM" no
 * longer get the same budget, and why wide titles stop overflowing the card.
 */
class TextFitter
{
    /**
     * GD takes a point size and renders at 96 dpi, while an SVG font-size is in
     * user units (CSS px). Measuring without converting reports widths a third
     * too wide, so every headline wraps earlier and sets smaller than it needs
     * to. Verified against a rasterised sample: 940px measured, 705px rendered.
     */
    private const POINTS_PER_PIXEL = 72 / 96;

    /** @var array<string,array{lines:string[],size:int}> */
    private array $memo = [];

    public function __construct(private string $fontPath)
    {
        if (! is_file($this->fontPath)) {
            throw new RuntimeException("Laracards: font file not found at {$this->fontPath}.");
        }
    }

    /**
     * @param  int[]  $sizes  Candidate font sizes, largest first.
     * @return array{lines:string[],size:int}
     */
    public function fit(string $text, int $maxWidth, int $maxLines, array $sizes): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return ['lines' => [], 'size' => (int) end($sizes)];
        }

        $key = md5($text . '|' . $maxWidth . '|' . $maxLines . '|' . implode(',', $sizes));

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        foreach ($sizes as $size) {
            $lines = $this->wrap($text, $maxWidth, (int) $size);

            if (count($lines) <= $maxLines) {
                return $this->memo[$key] = ['lines' => $lines, 'size' => (int) $size];
            }
        }

        // Even the smallest size overflows: keep the smallest and ellipsize.
        $smallest = (int) end($sizes);
        $lines = array_slice($this->wrap($text, $maxWidth, $smallest), 0, $maxLines);
        $last = count($lines) - 1;

        if ($last >= 0) {
            $lines[$last] = $this->ellipsize($lines[$last], $maxWidth, $smallest);
        }

        return $this->memo[$key] = ['lines' => $lines, 'size' => $smallest];
    }

    /**
     * Breaks text into lines that fit $maxWidth at $size, never splitting words
     * unless a single word is itself wider than the line.
     *
     * @return string[]
     */
    public function wrap(string $text, int $maxWidth, int $size): array
    {
        $lines = [];
        $current = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if ($this->width($candidate, $size) <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            // A single word wider than the line has to be broken by character.
            if ($this->width($word, $size) > $maxWidth) {
                $pieces = $this->breakWord($word, $maxWidth, $size);
                $current = (string) array_pop($pieces);

                foreach ($pieces as $piece) {
                    $lines[] = $piece;
                }

                continue;
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /** Rendered width of $text at $size, in pixels. */
    public function width(string $text, int $size): int
    {
        if ($text === '') {
            return 0;
        }

        $box = imagettfbbox($size * self::POINTS_PER_PIXEL, 0, $this->fontPath, $text);

        if ($box === false) {
            throw new RuntimeException('Laracards: imagettfbbox failed. Is GD compiled with FreeType support?');
        }

        return (int) abs($box[2] - $box[0]);
    }

    /** @return string[] */
    private function breakWord(string $word, int $maxWidth, int $size): array
    {
        $pieces = [];
        $current = '';
        $length = mb_strlen($word);

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($word, $i, 1);

            if ($current !== '' && $this->width($current . $char, $size) > $maxWidth) {
                $pieces[] = $current;
                $current = $char;
                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    private function ellipsize(string $line, int $maxWidth, int $size): string
    {
        $line = rtrim($line, " .,;:");

        while ($line !== '' && $this->width($line . '...', $size) > $maxWidth) {
            $line = mb_substr($line, 0, mb_strlen($line) - 1);
        }

        return rtrim($line) . '...';
    }
}
