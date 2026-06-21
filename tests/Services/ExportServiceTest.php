<?php

namespace AnourValar\LaravelInterpreter\Tests\Services;

use AnourValar\LaravelInterpreter\Services\ExportService;
use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class ExportServiceTest extends AbstractSuite
{
    /**
     * @var \AnourValar\LaravelInterpreter\Services\ExportService
     */
    protected ExportService $service;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ExportService();
    }

    /**
     * A schema that includes every lang/view file.
     *
     * @param array $overrides
     * @return array
     */
    protected function fullSchema(array $overrides = []): array
    {
        return $this->putSchema('ru', array_replace([
            'lang_files' => ['exclude' => [], 'include' => ['/'], 'exclude_keys' => []],
            'view_files' => ['exclude' => [], 'include' => ['/']],
        ], $overrides));
    }

    public function test_get_reads_source_php_and_json(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world']);
        $this->putLang('en.json', ['Just a phrase' => 'Just a phrase']);

        $data = $this->service->get($this->fullSchema(), true, false);

        $this->assertSame(['hello' => 'Hello world'], $data['/<locale>/messages.php']);
        $this->assertArrayHasKey('Just a phrase', $data['/<locale>.json']);
    }

    public function test_get_flat_uses_dot_notation_with_file_prefix(): void
    {
        $this->putLang('en/auth.php', ['failed' => 'These credentials do not match.']);

        $flat = $this->service->getFlat($this->fullSchema(), true);

        $this->assertSame('These credentials do not match.', $flat['/<locale>/auth.php.failed']);
    }

    public function test_it_collects_phrases_from_views(): void
    {
        $this->putView('home.blade.php', "<h1>@lang('Welcome home')</h1>");
        $this->putView('partials/footer.blade.php', "{{ __('All rights reserved') }}");

        $data = $this->service->get($this->fullSchema(), true, false);

        $this->assertArrayHasKey('Welcome home', $data['/<locale>.json']);
        $this->assertArrayHasKey('All rights reserved', $data['/<locale>.json']);
    }

    public function test_it_ignores_phrases_inside_blade_comments(): void
    {
        $this->putView('home.blade.php', "{{-- @lang('Hidden phrase') --}} @lang('Visible phrase')");

        $data = $this->service->get($this->fullSchema(), true, false);

        $this->assertArrayHasKey('Visible phrase', $data['/<locale>.json']);
        $this->assertArrayNotHasKey('Hidden phrase', $data['/<locale>.json']);
    }

    public function test_get_reads_target_locale(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world']);
        $this->putLang('ru/messages.php', ['hello' => 'Привет мир']);

        $data = $this->service->get($this->fullSchema(), false, false);

        $this->assertSame(['hello' => 'Привет мир'], $data['/<locale>/messages.php']);
    }

    public function test_lang_files_include_filter(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello']);
        $this->putLang('en/auth.php', ['failed' => 'Failed']);

        $schema = $this->fullSchema([
            'lang_files' => ['exclude' => [], 'include' => ['auth.php'], 'exclude_keys' => []],
        ]);

        $data = $this->service->get($schema, true, false);

        $this->assertArrayHasKey('/<locale>/auth.php', $data);
        $this->assertArrayNotHasKey('/<locale>/messages.php', $data);
    }

    public function test_lang_files_exclude_filter(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello']);
        $this->putLang('en/auth.php', ['failed' => 'Failed']);

        $schema = $this->fullSchema([
            'lang_files' => ['exclude' => ['auth.php'], 'include' => ['/'], 'exclude_keys' => []],
        ]);

        $data = $this->service->get($schema, true, false);

        $this->assertArrayHasKey('/<locale>/messages.php', $data);
        $this->assertArrayNotHasKey('/<locale>/auth.php', $data);
    }

    public function test_exclude_keys_filter(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello', 'bye' => 'Bye']);

        $schema = $this->fullSchema([
            'lang_files' => ['exclude' => [], 'include' => ['/'], 'exclude_keys' => ['messages.hello']],
        ]);

        $data = $this->service->get($schema, true, false);

        $this->assertArrayNotHasKey('hello', $data['/<locale>/messages.php']);
        $this->assertArrayHasKey('bye', $data['/<locale>/messages.php']);
    }

    public function test_include_json_can_be_disabled(): void
    {
        $this->putLang('ru.json', ['Phrase' => 'Перевод']);

        // include_json=true: the json bucket is loaded for the target locale
        $data = $this->service->get($this->fullSchema(['include_json' => true]), false, false);
        $this->assertArrayHasKey('/<locale>.json', $data);

        // include_json=false: the json bucket is skipped
        $data = $this->service->get($this->fullSchema(['include_json' => false]), false, false);
        $this->assertArrayNotHasKey('/<locale>.json', $data);
    }

    public function test_it_reads_vendor_packages(): void
    {
        $this->putLang('vendor/somepackage/en/messages.php', ['greeting' => 'Hi from vendor']);

        $data = $this->service->get($this->fullSchema(), true, false);

        $this->assertSame(['greeting' => 'Hi from vendor'], $data['/vendor/somepackage/<locale>/messages.php']);
    }

    public function test_get_missed_returns_untranslated_cyrillic_phrases(): void
    {
        $this->putView('home.blade.php', "<h1>Привет мир</h1> @lang('Already wrapped')");

        $missed = $this->service->getMissed($this->fullSchema());

        $this->assertCount(1, $missed);
        $phrases = array_values($missed)[0];
        $this->assertContains('Привет мир', $phrases);
    }

    public function test_get_missed_is_empty_when_everything_is_wrapped(): void
    {
        $this->putView('home.blade.php', "@lang('Hello') {{ __('World') }}");

        $this->assertSame([], $this->service->getMissed($this->fullSchema()));
    }
}
