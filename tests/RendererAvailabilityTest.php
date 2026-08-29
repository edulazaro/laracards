<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Renderers\ResvgRenderer;
use EduLazaro\Laracards\Renderers\RsvgRenderer;
use PHPUnit\Framework\TestCase as BaseTestCase;

/** Availability is about resolving the binary, so it needs no binary to test. */
class RendererAvailabilityTest extends BaseTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/laracards-bin-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    private function shim(): string
    {
        $path = $this->dir . '/rsvg-convert';
        file_put_contents($path, "#!/bin/sh\nexit 0\n");
        chmod($path, 0755);

        return $path;
    }

    public function test_an_absolute_path_to_an_executable_counts_as_available(): void
    {
        $this->assertTrue((new RsvgRenderer($this->shim()))->available());
        $this->assertTrue((new ResvgRenderer($this->shim()))->available());
    }

    public function test_an_absolute_path_that_does_not_exist_does_not(): void
    {
        $this->assertFalse((new RsvgRenderer($this->dir . '/missing'))->available());
    }

    public function test_a_bare_name_still_goes_through_the_path(): void
    {
        $this->assertTrue((new RsvgRenderer('sh'))->available());
        $this->assertFalse((new RsvgRenderer('definitely-not-a-real-binary-xyz'))->available());
    }
}
