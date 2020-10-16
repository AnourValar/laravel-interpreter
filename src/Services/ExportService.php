<?php

namespace AnourValar\LaravelInterpreter\Services;

class ExportService
{
    use WalkTrait;

    /**
     * Retrieve current translate
     *
     * @param array $schema
     * @param boolean $source
     * @return array
     */
    public function get(array $schema, bool $source): array
    {
        if ($source) {
            $data = $this->getStructure(\App::langPath()."/{$schema['source_locale']}/", $schema);
            $data['.json'] = array_replace(($data['.json'] ?? []), $this->walk($schema));

            return $data;
        }

        return $this->getStructure(\App::langPath()."/{$schema['target_locale']}/", $schema, true);
    }

    /**
     * Retrieve current translate (flat)
     *
     * @param array $schema
     * @param boolean $source
     * @return array
     */
    public function getFlat(array $schema, bool $source): array
    {
        return $this->flatten( $this->get($schema, $source) );
    }

    /**
     * @param array $data
     * @param array $path
     * @return array
     */
    protected function flatten(array $data, array $path = []): array
    {
        $result = [];

        foreach ($data as $key => $item) {
            $currPath = array_merge($path, [$key]);

            if (is_array($item)) {
                $result = array_replace($result, $this->flatten($item, $currPath));
            } else {
                $result[implode('.', $currPath)] = $item;
            }
        }

        return $result;
    }

    /**
     * @param string $path
     * @param array $schema
     * @param boolean $ignoreFilter
     * @return array
     */
    protected function getStructure(string $path, array $schema, bool $ignoreFilter = false, $trimLength = 0): array
    {
        $result = [];
        $path = rtrim($path, '/');

        if (! $trimLength) {
            $trimLength = mb_strlen($path);
        }

        if (is_file($path.'.json') && ($ignoreFilter || $schema['include_json'])) {
            $result['.json'] = $this->load($path.'.json');
        }

        if (is_dir($path)) {
            foreach (scandir($path) as $structure) {
                if (in_array($structure, ['.', '..'])) {
                    continue;
                }

                $fullpath = "$path/$structure";
                $relativePath = mb_substr($fullpath, $trimLength);

                if (is_dir($fullpath)) {
                    $result = array_replace($result, $this->getStructure($fullpath, $schema, $ignoreFilter, $trimLength));
                } elseif ($ignoreFilter || $this->passes($fullpath, $schema['lang_files'])) {
                    $result[$relativePath] = $this->load($fullpath);

                    if (! $ignoreFilter) {
                        $result[$relativePath] = $this->excludeKeys($result[$relativePath], $schema['lang_files']['exclude_keys']);
                    }
                }
            }
        }

        return array_filter($result);
    }

    /**
     * @param array $data
     * @param array $excludeKeys
     * @return array
     */
    protected function excludeKeys(array $data, array $excludeKeys): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $excludeKeys)) {
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->excludeKeys($value, $excludeKeys);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param string $fullpath
     * @return array|NULL
     */
    protected function load(string $fullpath): ?array
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
