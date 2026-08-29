<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Support\DataUri;
use PHPUnit\Framework\TestCase as BaseTestCase;

class DataUriTest extends BaseTestCase
{
    public function test_a_missing_file_yields_null(): void
    {
        $this->assertNull(DataUri::fromFile('/does/not/exist.png'));
        $this->assertNull(DataUri::fromFile(null));
    }

    public function test_it_encodes_the_file_with_its_mime_type(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'img') . '.png';
        imagepng(imagecreatetruecolor(2, 2), $path);

        $uri = DataUri::fromFile($path);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertSame(file_get_contents($path), base64_decode(explode(',', $uri, 2)[1]));

        @unlink($path);
    }
}
