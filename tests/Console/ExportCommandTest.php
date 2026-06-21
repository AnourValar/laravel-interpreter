<?php

namespace AnourValar\LaravelInterpreter\Tests\Console;

use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class ExportCommandTest extends AbstractSuite
{
    /**
     * A schema that includes every lang/view file.
     *
     * @param array $overrides
     * @return array
     */
    protected function exportSchema(array $overrides = []): array
    {
        return $this->putSchema('ru', array_replace([
            'lang_files' => ['exclude' => [], 'include' => ['/'], 'exclude_keys' => []],
            'view_files' => ['exclude' => [], 'include' => ['/']],
        ], $overrides));
    }

    public function test_it_exports_untranslated_phrases(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world', 'ident' => 'ID']);
        $this->putView('home.blade.php', "@lang('Welcome home')");
        $this->exportSchema();

        $this->artisan('interpreter:export', ['schema' => 'ru'])->assertSuccessful();

        $data = json_decode($this->getLang('ru_i18.json'), true);

        // untranslated phrases map to themselves
        $this->assertSame('Hello world', $data['Hello world']);
        $this->assertSame('Welcome home', $data['Welcome home']);
        // "ID" is in the default exclude_phrases list
        $this->assertArrayNotHasKey('ID', $data);
    }

    public function test_it_fails_when_the_file_already_exists(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world']);
        $this->exportSchema();
        $this->putLang('ru_i18.json', ['existing' => 'existing']);

        $this->artisan('interpreter:export', ['schema' => 'ru'])
            ->expectsOutputToContain('already exists')
            ->assertFailed();
    }

    public function test_it_overwrites_with_the_force_option(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world']);
        $this->exportSchema();
        $this->putLang('ru_i18.json', '{"old":"old"}');

        $this->artisan('interpreter:export', ['schema' => 'ru', '--force' => true])
            ->assertSuccessful();

        $data = json_decode($this->getLang('ru_i18.json'), true);
        $this->assertArrayNotHasKey('old', $data);
        $this->assertArrayHasKey('Hello world', $data);
    }

    public function test_re_translate_keeps_existing_translations(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world']);
        $this->putLang('ru/messages.php', ['hello' => 'Привет мир']);
        $this->exportSchema();

        $this->artisan('interpreter:export', ['schema' => 'ru', '--re-translate' => true])
            ->assertSuccessful();

        $data = json_decode($this->getLang('ru_i18.json'), true);
        $this->assertSame('Привет мир', $data['Hello world']);
    }

    public function test_it_skips_already_translated_phrases_without_re_translate(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world']);
        $this->putLang('ru/messages.php', ['hello' => 'Привет мир']);
        $this->exportSchema();

        $this->artisan('interpreter:export', ['schema' => 'ru'])
            ->expectsOutputToContain('Nothing to export')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->langPath . '/ru_i18.json');
    }

}
