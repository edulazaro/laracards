<?php

namespace EduLazaro\Laracards\Tests;

use EduLazaro\Laracards\Support\Template;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TemplateTest extends BaseTestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'tpl') . '.svg';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    private function template(string $contents): Template
    {
        file_put_contents($this->file, $contents);

        return new Template($this->file);
    }

    public function test_it_replaces_both_placeholder_styles(): void
    {
        $svg = $this->template('<a>{{title}}</a><image href="__LOGO_URI__"/>')
            ->render(['title' => 'Hola', 'logo_uri' => 'data:image/png;base64,AAA']);

        $this->assertStringContainsString('<a>Hola</a>', $svg);
        $this->assertStringContainsString('href="data:image/png;base64,AAA"', $svg);
    }

    public function test_it_strips_placeholders_that_were_never_filled(): void
    {
        $svg = $this->template('<a>{{title}}</a><b>{{missing}}</b><c href="__ALSO_MISSING__"/>')
            ->render(['title' => 'Hola']);

        $this->assertStringNotContainsString('{{', $svg);
        $this->assertStringNotContainsString('__', $svg);
    }

    public function test_it_fails_loudly_when_the_template_is_absent(): void
    {
        $this->expectExceptionMessageMatches('/template not found/');

        new Template('/does/not/exist.svg');
    }
}
