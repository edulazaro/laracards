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

    public function test_a_section_is_kept_when_its_key_has_a_value(): void
    {
        $svg = $this->template('{{#logo_uri}}<image href="__LOGO_URI__"/>{{/logo_uri}}')
            ->render(['logo_uri' => 'data:image/png;base64,AAA']);

        $this->assertSame('<image href="data:image/png;base64,AAA"/>', $svg);
    }

    public function test_a_section_is_dropped_when_its_key_is_empty_or_missing(): void
    {
        $template = '<a/>{{#logo_uri}}<image href="__LOGO_URI__"/>{{/logo_uri}}<b/>';

        $this->assertSame('<a/><b/>', $this->template($template)->render(['logo_uri' => '']));
        $this->assertSame('<a/><b/>', $this->template($template)->render([]));
    }

    public function test_a_multiline_section_is_dropped_whole(): void
    {
        $svg = $this->template("<a/>{{#bg}}\n  <g>\n    <image href=\"__BG__\"/>\n  </g>\n{{/bg}}<b/>")
            ->render(['bg' => '']);

        $this->assertStringNotContainsString('<image', $svg);
        $this->assertStringNotContainsString('<g>', $svg);
    }

    public function test_it_fails_loudly_when_the_template_is_absent(): void
    {
        $this->expectExceptionMessageMatches('/template not found/');

        new Template('/does/not/exist.svg');
    }
}
