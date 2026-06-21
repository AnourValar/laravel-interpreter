<?php

namespace AnourValar\LaravelInterpreter\Tests\Console;

use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class SchemaTraitTest extends AbstractSuite
{
    /**
     * @var object
     */
    protected object $concrete;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->concrete = new class ()
        {
            use \AnourValar\LaravelInterpreter\Console\Commands\SchemaTrait;

            public function callGetSchema(?string $schema): array
            {
                return $this->getSchema($schema);
            }

            public function callGetAdapter(array $schema): \AnourValar\LaravelInterpreter\Adapters\AdapterInterface
            {
                return $this->getAdapter($schema);
            }

            public function callIsExcluded(array $schema, string $value): bool
            {
                return $this->isExcluded($schema, $value);
            }
        };
    }

    public function test_get_schema_returns_a_valid_schema(): void
    {
        $this->putSchema('ru');

        $schema = $this->concrete->callGetSchema('ru');

        $this->assertSame('en', $schema['source_locale']);
        $this->assertSame('ru', $schema['target_locale']);
    }

    public function test_get_schema_throws_when_file_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $this->concrete->callGetSchema('missing');
    }

    public function test_get_schema_throws_on_invalid_structure(): void
    {
        file_put_contents($this->langPath . '/broken_schema.json', '{"source_locale": "en"}');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Incorrect schema structure.');

        $this->concrete->callGetSchema('broken');
    }

    public function test_get_schema_throws_when_not_an_array(): void
    {
        file_put_contents($this->langPath . '/scalar_schema.json', '"just a string"');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Incorrect schema structure.');

        $this->concrete->callGetSchema('scalar');
    }

    public function test_get_schema_throws_when_locales_match(): void
    {
        // source_locale is config('app.locale') === 'en'
        $this->putSchema('en');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Target locale should be different');

        $this->concrete->callGetSchema('en');
    }

    public function test_get_adapter_resolves_the_default_json_adapter(): void
    {
        $schema = $this->putSchema('ru');

        $adapter = $this->concrete->callGetAdapter($schema);

        $this->assertInstanceOf(\AnourValar\LaravelInterpreter\Adapters\JsonAdapter::class, $adapter);
    }

    public function test_get_adapter_throws_for_non_adapter_class(): void
    {
        $schema = $this->putSchema('ru', ['adapter' => \stdClass::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Adapter must implements AdapterInterface.');

        $this->concrete->callGetAdapter($schema);
    }

    public function test_is_excluded_by_phrases_list(): void
    {
        $schema = $this->putSchema('ru');

        $this->assertTrue($this->concrete->callIsExcluded($schema, 'ID'));
        $this->assertTrue($this->concrete->callIsExcluded($schema, 'Email'));
        $this->assertFalse($this->concrete->callIsExcluded($schema, 'Hello world'));
    }

    public function test_is_excluded_by_include_pattern(): void
    {
        $schema = $this->putSchema('ru', ['include_pattern' => '/^[A-Z]/']);

        // does not match the pattern -> excluded
        $this->assertTrue($this->concrete->callIsExcluded($schema, 'lowercase start'));
        // matches the pattern -> not excluded
        $this->assertFalse($this->concrete->callIsExcluded($schema, 'Uppercase start'));
    }
}
