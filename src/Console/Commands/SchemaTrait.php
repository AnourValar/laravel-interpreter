<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

trait SchemaTrait
{
    /**
     * @param string $schema
     * @throws \InvalidArgumentException
     * @return array
     */
    protected function getSchema(?string $schema): array
    {
        $path = \App::langPath() . '/' . $schema . '_schema.json';

        if (! file_exists($path)) {
            throw new \InvalidArgumentException('Schema file "'.$path.'" does not exist.');
        }

        $schema = json_decode(file_get_contents($path), true);

        if (! is_array($schema)) {
            throw new \InvalidArgumentException('Incorrect schema structure.');
        }
        if ($schema != array_replace(json_decode(file_get_contents(__DIR__.'/../../resources/schema.json'), true), $schema)) {
            throw new \InvalidArgumentException('Incorrect schema structure.');
        }

        if ($schema['target_locale'] == $schema['source_locale']) {
            throw new \InvalidArgumentException('Target locale should be different than "app.locale".');
        }

        return $schema;
    }

    /**
     * @param array $schema
     * @throws \InvalidArgumentException
     * @return \AnourValar\LaravelInterpreter\Adapters\AdapterInterface
     */
    protected function getAdapter(array $schema): \AnourValar\LaravelInterpreter\Adapters\AdapterInterface
    {
        $adapter = \App::make($schema['adapter']);

        if (! $adapter instanceof \AnourValar\LaravelInterpreter\Adapters\AdapterInterface) {
            throw new \InvalidArgumentException('Adapter must implements AdapterInterface.');
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
