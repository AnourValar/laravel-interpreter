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
        $path = \App::langPath() .'/' . $schema . '_schema.json';

        if (! file_exists($path)) {
            throw new InputException('Schema file "'.$path.'" not exists.');
        }

        $schema = json_decode(file_get_contents($path), true);
        if (! is_array($schema)) {
            throw new InputException('Incorrect schema structure.');
        }

        if (!isset($schema['source_locale'], $schema['target_locale'], $schema['filename'], $schema['adapter']) ||
            !isset($schema['include_files'], $schema['exclude_files'], $schema['exclude_phrases'])
        ) {
            throw new InputException('Incorrect schema structure.');
        }

        return $schema;
    }

    /**
     * @param array $schema
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     * @return string
     */
    protected function getSourceLocale(array $schema): string
    {
        return $schema['source_locale'];
    }

    /**
     * @param array $schema
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     * @return string
     */
    protected function getTargetLocale(array $schema): string
    {
        $targetLocale = $schema['target_locale'];

        if ($targetLocale == $schema['source_locale']) {
            throw new InputException('Target locale should be different then "app.locale".');
        }

        return $targetLocale;
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
     * @return array
     */
    protected function getFilters(array $schema): array
    {
        return [
            'include_json' => ($schema['include_json'] ?? false),
            'include_files' => ($schema['include_files'] ?? []),

            'exclude_files' => ($schema['exclude_files'] ?? []),
            'exclude_phrases' => ($schema['exclude_phrases'] ?? []),
        ];
    }

    /**
     * @param string $path
     * @param array $filters
     * @param integer $trimLength
     * @return array
     */
    protected function getStructure(string $path, array $filters, int $trimLength = 0): array
    {
        $result = [];
        $path = rtrim($path, '/');

        if (! $trimLength) {
            $trimLength = mb_strlen($path);
        }

        foreach ([$path.'.json', $path.'_walk.json'] as $item) {
            if (is_file($item) && $filters['include_json']) {
                $result['.json'] = array_replace(($result['.json'] ?? []), $this->load($item));
            }
        }

        if (is_dir($path)) {
            foreach (scandir($path) as $structure) {
                if (in_array($structure, ['.', '..'])) {
                    continue;
                }
                $fullpath = "$path/$structure";
                $relativePath = mb_substr($fullpath, $trimLength);

                if (is_dir($fullpath)) {
                    $result = array_replace($result, $this->getStructure($fullpath, $filters, $trimLength));
                } elseif ($this->isIncluded($relativePath, $filters)) {
                    $result[$relativePath] = $this->load($fullpath);
                }
            }
        }

        return array_filter($result);
    }

    /**
     * @param array $array
     * @param integer $indentSize
     * @return string
     */
    protected function exportArray(array $array, int $indentSize): string
    {
        $result = '';

        foreach ($array as $key => $value) {
            if ($result) {
                $result .= "\n";
            }

            $key = "'".addCslashes($key, "'")."'";

            if (is_array($value)) {
                $result .= str_pad('', $indentSize, ' ', STR_PAD_LEFT) . "$key => [";

                $sub = $this->exportArray($value, $indentSize + 4);

                if ($sub) {
                    $result .= "\n" . $sub . "\n" . str_pad('', $indentSize, ' ', STR_PAD_LEFT) . "],";
                } else {
                    $result .= "],";
                }
            } else {
                if (is_null($value)) {
                    $value = 'null';
                } elseif (is_string($value)) {
                    $value = "'".addCslashes($value, "'")."'";
                }

                $result .= str_pad('', $indentSize, ' ', STR_PAD_LEFT) . "$key => $value,";
            }
        }

        return $result;
    }

    /**
     * @param string $path
     * @param array $filters
     * @return boolean
     */
    private function isIncluded(string $path, array $filters): bool
    {
        if ($filters['include_files'] && !in_array($path, $filters['include_files'])) {
            return false;
        }

        if (in_array($path, $filters['exclude_files'])) {
            return false;
        }

        return true;
    }

    /**
     * @param string $fullpath
     * @return array|NULL
     */
    private function load(string $fullpath): ?array
    {
        if (preg_match('#\.php$#', $fullpath)) {
            return require $fullpath;
        }

        if (preg_match('#\.json$#', $fullpath)) {
            $data = json_decode(file_get_contents($fullpath), true);

            foreach ((array)$data as $key => $item) {
                if (! isset($item)) {
                    $data[$key] = $key;
                }
            }

            return $data;
        }

        return null;
    }
}
