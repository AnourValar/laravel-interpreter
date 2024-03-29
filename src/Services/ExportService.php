<?php

namespace AnourValar\LaravelInterpreter\Services;

class ExportService
{
    use WalkTrait;

    /**
     * Retrieve current translate
     *
     * @param array $schema
     * @param bool $source
     * @param bool $ignoreFilters
     * @return array
     */
    public function get(array $schema, bool $source, bool $ignoreFilters): array
    {
        if ($source) {
            $data = array_replace(
                $this->getStructure(\App::langPath()."/{$schema['source_locale']}/", $schema, $ignoreFilters, $schema['source_locale']),
                $this->getVendor($schema, $ignoreFilters, $schema['source_locale'])
            );

            $data['/<locale>.json'] = array_replace(($data['/<locale>.json'] ?? []), $this->walk($schema));

            return $data;
        }

        return array_replace(
            $this->getStructure(\App::langPath()."/{$schema['target_locale']}/", $schema, $ignoreFilters, $schema['target_locale']),
            $this->getVendor($schema, $ignoreFilters, $schema['target_locale'])
        );
    }

    /**
     * Get unwrapped phrases
     *
     * @param array $schema
     * @return array
     */
    public function getMissed(array $schema): array
    {
        return $this->walkForMissed($schema);
    }

    /**
     * Retrieve current translate (flat)
     *
     * @param array $schema
     * @param bool $source
     * @return array
     */
    public function getFlat(array $schema, bool $source): array
    {
        return $this->flatten($this->get($schema, $source, !$source));
    }

    /**
     * @param array $schema
     * @param bool $ignoreFilters
     * @param string $currLocale
     * @return array
     */
    protected function getVendor(array $schema, bool $ignoreFilters, string $currLocale): array
    {
        $data = [];

        $vendorPath = \App::langPath() . '/vendor/';
        if (is_dir($vendorPath)) {
            foreach (scandir($vendorPath) as $item) {
                if (in_array($item, ['.', '..'])) {
                    continue;
                }

                if (! is_dir($vendorPath.$item)) {
                    continue;
                }

                $data = array_replace(
                    $data,
                    $this->getStructure($vendorPath."$item/$currLocale/", $schema, $ignoreFilters, $currLocale)
                );
            }
        }

        return $data;
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
     * @param bool $ignoreFilters
     * @param string $currLocale
     * @return array
     */
    protected function getStructure(string $path, array $schema, bool $ignoreFilters, string $currLocale): array
    {
        $result = [];
        $path = rtrim($path, '/');

        if (is_file($path.'.json') && ($ignoreFilters || $schema['include_json'])) {
            $result['/<locale>.json'] = $this->load($path.'.json');
        }

        if (is_dir($path)) {
            foreach (scandir($path) as $structure) {
                if (in_array($structure, ['.', '..'])) {
                    continue;
                }

                $fullpath = "$path/$structure";
                $relativePath = mb_substr($fullpath, mb_strlen(\App::langPath()));
                $relativePath = preg_replace('#\/'.preg_quote($currLocale).'\/#', '/<locale>/', $relativePath, 1);
                $searchPath = preg_replace('#\/'.preg_quote($currLocale).'\/#', '/<locale>/', $fullpath);

                if (is_dir($fullpath)) {
                    $result = array_replace($result, $this->getStructure($fullpath, $schema, $ignoreFilters, $currLocale));
                } elseif ($ignoreFilters || $this->passes($searchPath, $schema['lang_files'])) {
                    $result[$relativePath] = $this->load($fullpath);

                    if (! $ignoreFilters) {
                        $keyMap = str_replace('/', '.', preg_replace('#\.php$#', '', $relativePath));

                        $result[$relativePath] = $this->excludeKeys($result[$relativePath], $schema['lang_files']['exclude_keys'], $keyMap);
                    }
                }
            }
        }

        return array_filter($result);
    }

    /**
     * @param array $data
     * @param array $excludeKeys
     * @param string $keyMap
     * @return array
     */
    protected function excludeKeys(array $data, array $excludeKeys, string $keyMap): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $currKeyMap = sprintf('%s.%s', $keyMap, $key);

            $currKeyMapArray = explode('.', $currKeyMap);
            while (! is_null(array_shift($currKeyMapArray))) {
                if (in_array(implode('.', $currKeyMapArray), $excludeKeys, true)) {
                    continue 2;
                }
            }

            if (is_array($value)) {
                $result[$key] = $this->excludeKeys($value, $excludeKeys, $currKeyMap);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param string $fullpath
     * @return array|null
     */
    protected function load(string $fullpath): ?array
    {
        if (preg_match('#\.php$#', $fullpath)) {
            return require $fullpath;
        }

        if (preg_match('#\.json$#', $fullpath)) {
            $data = json_decode(file_get_contents($fullpath), true);

            foreach ((array) $data as $key => $item) {
                if (! isset($item)) {
                    $data[$key] = $key;
                }
            }

            return $data;
        }

        return null;
    }
}
