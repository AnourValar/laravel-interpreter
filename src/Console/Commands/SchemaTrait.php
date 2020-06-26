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
    protected function getSchema(?string $schema) : array
    {
        $path = \App::langPath() .'/' . $schema . '_schema.json';

        if (! file_exists($path)) {
            throw new InputException('Schema file "'.$path.'" not exists.');
        }

        $schema = json_decode(file_get_contents($path), true);
        if (! is_array($schema)) {
            throw new InputException('Incorrect schema structure.');
        }

        if (! isset($schema['source_locale'], $schema['target_locale'], $schema['filename'], $schema['adapter'], $schema['excludes'])) {
            throw new InputException('Incorrect schema structure.');
        }

        return $schema;
    }

    /**
     * @param array $schema
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     * @return string
     */
    protected function getSourceLocale(array $schema) : string
    {
        return $schema['source_locale'];
    }

    /**
     * @param array $schema
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     * @return string
     */
    protected function getTargetLocale(array $schema) : string
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
    protected function getAdapter(array $schema) : \AnourValar\LaravelInterpreter\Adapters\AdapterInterface
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
    protected function getExcludes(array $schema) : array
    {
        return $schema['excludes'];
    }

    /**
     * @param string $path
     * @param array $excludes
     * @return array
     */
    protected function getStructure(string $path, array $excludes) : array
    {
        $result = [];
        $path = rtrim($path, '/');

        $fullpath = $path.'.json';
        if (is_file($fullpath) && !$this->isExcluded($fullpath, $excludes)) {
            $result['.json'] = $this->load($fullpath);
        }

        if (is_dir($path)) {
            foreach (scandir($path) as $structure) {
                if (in_array($structure, ['.', '..'])) {
                    continue;
                }
                $fullpath = "$path/$structure";

                if (is_dir($fullpath)) {
                    foreach ($this->getStructure($fullpath, $excludes) as $key => $item) {
                        $result["/{$structure}{$key}"] = $item;
                    }
                }

                if (! $this->isExcluded($fullpath, $excludes)) {
                    $result["/$structure"] = $this->load($fullpath);
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
    protected function exportArray(array $array, int $indentSize) : string
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
                } else if (is_string($value)) {
                    $value = "'".addCslashes($value, "'")."'";
                }

                $result .= str_pad('', $indentSize, ' ', STR_PAD_LEFT) . "$key => $value,";
            }
        }

        return $result;
    }

    /**
     * @param string $fullpath
     * @param array $excludes
     * @return boolean
     */
    private function isExcluded(string $fullpath, array $excludes) : bool
    {
        foreach ($excludes as $exclude) {
            if (preg_match($exclude, $fullpath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $fullpath
     * @return array|NULL
     */
    private function load(string $fullpath) : ?array
    {
        if (preg_match('#\.php$#', $fullpath)) {
            return require $fullpath;
        }

        if (preg_match('#\.json$#', $fullpath)) {
            return json_decode(file_get_contents($fullpath), true);
        }

        return null;
    }
}
