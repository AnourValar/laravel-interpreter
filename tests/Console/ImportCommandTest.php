<?php

namespace AnourValar\LaravelInterpreter\Tests\Console;

use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class ImportCommandTest extends AbstractSuite
{
    /**
     * A schema that includes every lang/view file.
     *
     * @param array $overrides
     * @return array
     */
    protected function importSchema(array $overrides = []): array
    {
        return $this->putSchema('ru', array_replace([
            'lang_files' => ['exclude' => [], 'include' => ['/'], 'exclude_keys' => []],
            'view_files' => ['exclude' => [], 'include' => ['/']],
        ], $overrides));
    }

    public function test_it_imports_translations_into_php_files(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world']);
        $this->putLang('ru_i18.json', ['Hello world' => 'Привет мир']);
        $this->importSchema();

        $this->artisan('interpreter:import', ['schema' => 'ru'])->assertSuccessful();

        $path = $this->langPath . '/ru/messages.php';
        $this->assertFileExists($path);
        $this->assertSame(['hello' => 'Привет мир'], require $path);
    }

    public function test_it_imports_translations_into_json_files(): void
    {
        $this->putLang('en.json', ['Welcome' => 'Welcome']);
        $this->putLang('ru_i18.json', ['Welcome' => 'Добро пожаловать']);
        $this->importSchema();

        $this->artisan('interpreter:import', ['schema' => 'ru'])->assertSuccessful();

        $path = $this->langPath . '/ru.json';
        $this->assertFileExists($path);
        $this->assertSame(['Welcome' => 'Добро пожаловать'], json_decode(file_get_contents($path), true));
    }

    public function test_it_warns_when_there_is_nothing_to_import(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world']);
        $this->importSchema();
        // no ru_i18.json with translations

        $this->artisan('interpreter:import', ['schema' => 'ru'])
            ->expectsOutputToContain('Nothing to import')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->langPath . '/ru/messages.php');
    }

    public function test_it_keeps_existing_target_translations(): void
    {
        $this->putLang('en/messages.php', ['hello' => 'Hello world', 'bye' => 'Bye']);
        $this->putLang('ru/messages.php', ['hello' => 'Старый перевод']);
        $this->putLang('ru_i18.json', ['Bye' => 'Пока']);
        $this->importSchema();

        $this->artisan('interpreter:import', ['schema' => 'ru'])->assertSuccessful();

        $data = require $this->langPath . '/ru/messages.php';
        $this->assertSame('Старый перевод', $data['hello']); // preserved
        $this->assertSame('Пока', $data['bye']); // newly imported
    }
}
