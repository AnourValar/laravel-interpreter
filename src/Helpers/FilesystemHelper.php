<?php

namespace AnourValar\LaravelInterpreter\Helpers;

class FilesystemHelper extends \Illuminate\Support\Str
{
    /**
     * Passes filters rules
     *
     * @param string $path
     * @param array $rules
     * @return boolean
     */
    public function passes(string $path, array $rules): bool
    {
        foreach ($rules['exclude'] as $item) {
            if (stripos($path, $item) !== false) {
                return false;
            }
        }

        foreach ($rules['include'] as $item) {
            if (stripos($path, $item) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $path
     * @param array $schema
     * @param boolean $ignoreFilter
     * @return array
     */
    public function getStructure(string $path, array $schema, bool $ignoreFilter = false, $trimLength = 0): array
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
                }
            }
        }

        return array_filter($result);
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
