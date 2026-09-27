<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Support\Color;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function test_hex_notations(): void
    {
        $this->assertSame([255, 255, 255, 0], Color::parse('#fff'));
        $this->assertSame([163, 230, 53, 0], Color::parse('#A3E635'));
        $this->assertSame([0, 0, 0, 127], Color::parse('#00000000'));
    }

    public function test_rgb_and_rgba(): void
    {
        $this->assertSame([12, 34, 56, 0], Color::parse('rgb(12, 34, 56)'));
        $this->assertSame([255, 255, 255, 13], Color::parse('rgba(255, 255, 255, 0.9)'));
        $this->assertSame([255, 255, 255, 70], Color::parse('rgba(255,255,255,0.45)'));
    }

    public function test_an_unknown_notation_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::parse('white');
    }
}
