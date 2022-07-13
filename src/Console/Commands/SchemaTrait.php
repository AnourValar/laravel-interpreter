<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

use AnourValar\LaravelInterpreter\Exceptions\InputException;

trait SchemaTrait
{
    /**
     * @param string $schema
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     * @return array
     */
    protected function getSchema(?string $schema): array
    {
        $path = \App::langPath() . '/' . $schema . '_schema.json';

        if (! file_exists($path)) {
            throw new InputException('Schema file "'.$path.'" not exists.');
        }

        $schema = json_decode(file_get_contents($path), true);

        if (! is_array($schema)) {
            throw new InputException('Incorrect schema structure.');
        }
        if ($schema != array_replace(json_decode(file_get_contents(__DIR__.'/../../resources/schema.json'), true), $schema)) {
            throw new InputException('Incorrect schema structure.');
        }

        if ($schema['target_locale'] == $schema['source_locale']) {
            throw new InputException('Target locale should be different than "app.locale".');
        }

        return $schema;
    }

    /**
     * @param array $schema
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     * @return \AnourValar\LaravelInterpreter\Adapters\AdapterInterface
     */
    protected function getAdapter(array $schema): \AnourValar\LaravelInterpreter\Adapters\AdapterInterface
    {
        $adapter = \App::make($schema['adapter']);

        if (! $adapter instanceof \AnourValar\LaravelInterpreter\Adapters\AdapterInterface) {
            throw new InputException('Adapter must implements AdapterInterface.');
        }

        return $adapter;
    }

    /**
     * @param array $schema
     * @param string $value
     * @return bool
     */
    protected function isExcluded(array $schema, string $value): bool
    {
        if ($schema['include_pattern'] && !preg_match($schema['include_pattern'], $value)) {
            return true;
        }

        if (in_array($value, $schema['exclude_phrases'], true)) {
            return true;
        }

        return false;
    }
}
