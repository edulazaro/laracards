<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Support\Manifest;
use PHPUnit\Framework\TestCase as BaseTestCase;

class ManifestTest extends BaseTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/laracards-manifest-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    private function manifest(): Manifest
    {
        return new Manifest($this->dir . '/manifest.json');
    }

    public function test_a_card_with_no_output_file_is_stale(): void
    {
        $this->assertTrue($this->manifest()->isStale('post', 'abc', $this->dir . '/missing.png'));
    }

    public function test_an_unchanged_card_is_not_stale(): void
    {
        $output = $this->dir . '/post.png';
        file_put_contents($output, 'x');

        $manifest = $this->manifest();
        $manifest->put('post', 'abc');
        $manifest->save();

        $this->assertFalse($this->manifest()->isStale('post', 'abc', $output));
    }

    public function test_a_changed_fingerprint_makes_an_existing_card_stale(): void
    {
        $output = $this->dir . '/post.png';
        file_put_contents($output, 'x');

        $manifest = $this->manifest();
        $manifest->put('post', 'abc');
        $manifest->save();

        $this->assertTrue($this->manifest()->isStale('post', 'def', $output));
    }
}
