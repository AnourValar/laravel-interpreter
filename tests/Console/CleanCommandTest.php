<?php

namespace AnourValar\LaravelInterpreter\Tests\Console;

use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class CleanCommandTest extends AbstractSuite
{
    /**
     * A schema that includes every lang/view file.
     *
     * @param array $overrides
     * @return array
     */
    protected function cleanSchema(array $overrides = []): array
    {
        return $this->putSchema('ru', array_replace([
            'lang_files' => ['exclude' => [], 'include' => ['/'], 'exclude_keys' => []],
            'view_files' => ['exclude' => [], 'include' => ['/']],
        ], $overrides));
    }

    public function test_it_removes_obsolete_keys_within_a_file(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello']);
        $this->putLang('ru/messages.php', ['hello' => 'Привет', 'obsolete' => 'Старое']);
        $this->cleanSchema();

        $this->artisan('interpreter:clean', ['schema' => 'ru'])
            ->expectsOutputToContain('successfully cleaned')
            ->assertSuccessful();

        $this->assertSame(['hello' => 'Привет'], require $this->langPath . '/ru/messages.php');
    }

    public function test_it_deletes_a_file_when_all_keys_become_obsolete(): void
    {
        $this->putLang('en/messages.php', ['new' => 'New']);
        $this->putLang('ru/messages.php', ['old' => 'Старое']);
        $this->cleanSchema();

        $this->artisan('interpreter:clean', ['schema' => 'ru'])->assertSuccessful();

        $this->assertFileDoesNotExist($this->langPath . '/ru/messages.php');
    }

    public function test_it_warns_when_there_is_nothing_to_clean(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello']);
        $this->putLang('ru/messages.php', ['hello' => 'Привет']);
        $this->cleanSchema();

        $this->artisan('interpreter:clean', ['schema' => 'ru'])
            ->expectsOutputToContain('Nothing to clean')
            ->assertSuccessful();

        $this->assertSame(['hello' => 'Привет'], require $this->langPath . '/ru/messages.php');
    }
}
