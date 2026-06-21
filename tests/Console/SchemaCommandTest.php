<?php

namespace AnourValar\LaravelInterpreter\Tests\Console;

use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class SchemaCommandTest extends AbstractSuite
{
    public function test_it_creates_a_schema_file(): void
    {
        $this->artisan('interpreter:schema', ['targetLocale' => 'ru'])
            ->assertSuccessful();

        $path = $this->langPath . '/ru_schema.json';
        $this->assertFileExists($path);

        $schema = json_decode(file_get_contents($path), true);
        $this->assertSame('en', $schema['source_locale']);
        $this->assertSame('ru', $schema['target_locale']);
        $this->assertSame('ru_i18.json', $schema['filename']);
        $this->assertSame(\AnourValar\LaravelInterpreter\Adapters\JsonAdapter::class, $schema['adapter']);
    }

    public function test_it_does_not_overwrite_an_existing_schema(): void
    {
        $this->putSchema('ru', ['filename' => 'custom.json']);

        $this->artisan('interpreter:schema', ['targetLocale' => 'ru'])
            ->expectsOutputToContain('already exists')
            ->assertSuccessful();

        // The original file must be untouched.
        $schema = json_decode($this->getLang('ru_schema.json'), true);
        $this->assertSame('custom.json', $schema['filename']);
    }
}
