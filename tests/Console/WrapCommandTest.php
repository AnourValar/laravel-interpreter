<?php

namespace AnourValar\LaravelInterpreter\Tests\Console;

use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class WrapCommandTest extends AbstractSuite
{
    /**
     * Subclass exposing the protected wrap() algorithm.
     *
     * @var object
     */
    protected object $command;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new class () extends \AnourValar\LaravelInterpreter\Console\Commands\WrapCommand
        {
            public function publicWrap(string $template, string $wrap, ?int &$counter = null): string
            {
                return $this->wrap($template, $wrap, $counter);
            }
        };
    }

    public function test_it_wraps_a_plain_phrase(): void
    {
        $counter = null;
        $result = $this->command->publicWrap('<div>Привет мир</div>', "@lang('%s')", $counter);

        $this->assertSame(1, $counter);
        $this->assertStringContainsString("@lang('Привет мир')", $result);
        $this->assertStringStartsWith('<div>', $result);
        $this->assertStringEndsWith('</div>', $result);
    }

    public function test_it_ignores_already_wrapped_phrases(): void
    {
        $counter = null;
        $result = $this->command->publicWrap("@lang('Привет')", "@lang('%s')", $counter);

        $this->assertNull($counter);
        $this->assertSame("@lang('Привет')", $result);
    }

    public function test_it_ignores_blade_and_html_but_wraps_text(): void
    {
        $counter = null;
        $template = "<p>Текст</p> @lang('Готово') {{ \$var }}";
        $result = $this->command->publicWrap($template, "@lang('%s')", $counter);

        $this->assertSame(1, $counter);
        $this->assertStringContainsString("<p>@lang('Текст')</p>", $result);
        $this->assertStringContainsString("@lang('Готово')", $result);
        $this->assertStringContainsString('{{ $var }}', $result);
    }

    public function test_it_does_not_wrap_when_there_is_nothing_to_wrap(): void
    {
        $counter = null;
        $result = $this->command->publicWrap('<div>Just english text</div>', "@lang('%s')", $counter);

        $this->assertNull($counter);
        $this->assertSame('<div>Just english text</div>', $result);
    }

    public function test_command_wraps_a_template_file(): void
    {
        $relative = 'interpreter_wrap_' . uniqid() . '.blade.php';
        $path = base_path($relative);
        file_put_contents($path, '<div>Привет мир</div>');

        try {
            $this->artisan('interpreter:wrap', ['template' => $relative])
                ->expectsOutputToContain('Template wrapped')
                ->assertSuccessful();

            $this->assertStringContainsString("@lang('Привет мир')", file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_command_reports_nothing_to_wrap(): void
    {
        $relative = 'interpreter_wrap_' . uniqid() . '.blade.php';
        $path = base_path($relative);
        file_put_contents($path, '<div>Only english here</div>');

        try {
            $this->artisan('interpreter:wrap', ['template' => $relative])
                ->expectsOutputToContain('Nothing to wrap')
                ->assertSuccessful();
        } finally {
            @unlink($path);
        }
    }

    public function test_command_fails_for_a_missing_file(): void
    {
        $this->artisan('interpreter:wrap', ['template' => 'definitely/missing.blade.php'])
            ->expectsOutputToContain('does not exist')
            ->assertFailed();
    }
}
