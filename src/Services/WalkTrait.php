<?php

namespace AnourValar\LaravelInterpreter\Services;

trait WalkTrait
{
    /**
     * @param array $schema
     * @return array
     */
    protected function walk(array $schema): array
    {
        $phrases = [];

        foreach ($this->getViews((array)config('view.paths'), $schema) as $view) {
            $phrases = array_merge($phrases, $this->getPhrases($view));
        }

        $phrases = array_filter(array_unique($phrases));
        return array_combine($phrases, $phrases);
    }

    /**
     * @param array $paths
     * @param array $schema
     * @return array
     */
    protected function getViews(array $paths, array $schema): array
    {
        $result = [];

        foreach ($paths as $path) {
            foreach (scandir($path) as $item) {
                if (in_array($item, ['.', '..'])) {
                    continue;
                }

                $item = "$path/$item";

                if (is_dir($item)) {
                    $result = array_merge($result, $this->getViews((array)$item, $schema));
                } elseif (stripos($item, '.php') && $this->passes($item, $schema['view_files'])) {
                    $result[] = file_get_contents($item);
                }
            }
        }

        return $result;
    }

    /**
     * @param string $view
     * @return array
     */
    protected function getPhrases(string $view): array
    {
        $view = preg_replace('#\{\{\-\-.*\-\-\}\}#sU', '<>', $view);

        $result = [];

        preg_match_all('|\@lang\(\s*([\'\"])(.*?)(?<!\\\)\1|s', $view, $patterns);
        if (isset($patterns[2])) {
            $result = array_merge($result, $patterns[2]);
        }

        preg_match_all('|\_\_\(\s*([\'\"])(.*?)(?<!\\\)\1|s', $view, $patterns);
        if (isset($patterns[2])) {
            $result = array_merge($result, $patterns[2]);
        }

        return $result;
    }

    /**
     * Passes filters rules
     *
     * @param string $path
     * @param array $rules
     * @return boolean
     */
    protected function passes(string $path, array $rules): bool
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
}
