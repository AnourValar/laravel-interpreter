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
            $phrases = array_merge($phrases, $this->parsePhrases($view));
        }

        $phrases = array_filter(array_unique($phrases));
        return array_combine($phrases, $phrases);
    }

    /**
     * @param array $schema
     * @return array
     */
    protected function walkForMissed(array $schema): array
    {
        $phrases = [];

        foreach ($this->getViews((array)config('view.paths'), $schema) as $path => $view) {
            $phrases = array_merge($phrases, $this->parseMissed($view, $path));
        }

        return $phrases;
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
                    $result[$item] = file_get_contents($item);
                }
            }
        }

        return $result;
    }

    /**
     * @param string $view
     * @return array
     */
    protected function parsePhrases(string $view): array
    {
        foreach (config('interpreter.regexp_garbage') as $regexp) {
            $view = preg_replace($regexp, '<>', $view);
        }

        $result = [];

        foreach (config('interpreter.regexp_wrap') as $regexp) {
            preg_match_all($regexp, $view, $patterns);

            if (isset($patterns[2])) {
                $result = array_merge($result, $patterns[2]);
            }
        }

        return $result;
    }

    /**
     * @param string $view
     * @param string $path
     * @return array
     */
    protected function parseMissed(string $view, string $path): array
    {
        foreach (config('interpreter.regexp_garbage') as $regexp) {
            $view = preg_replace($regexp, '<>', $view);
        }

        $result = [];

        foreach (config('interpreter.regexp_wrap') as $regexp) {
            $view = preg_replace($regexp, '', $view);
        }

        foreach (config('interpreter.regexp_miss') as $regexp) {
            preg_match_all($regexp, $view, $patterns);
            if ($patterns[0]) {
                $result[$path] = array_merge(($result[$path] ?? []), $patterns[0]);
            }
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
            if (stripos(str_replace('\\', '/', $path), str_replace('\\', '/', $item)) !== false) {
                return false;
            }
        }

        foreach ($rules['include'] as $item) {
            if (stripos(str_replace('\\', '/', $path), str_replace('\\', '/', $item)) !== false) {
                return true;
            }
        }

        return false;
    }
}
