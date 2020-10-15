<?php

namespace AnourValar\LaravelInterpreter\Sources;

class ViewsSource implements SourceInterface
{
    /**
     * @var \AnourValar\LaravelInterpreter\Helpers\FilesystemHelper
     */
    protected $filesystemHelper;

    /**
     * DI
     *
     * @param \AnourValar\LaravelInterpreter\Helpers\FilesystemHelper $filesystemHelper
     * @return void
     */
    public function __construct(\AnourValar\LaravelInterpreter\Helpers\FilesystemHelper $filesystemHelper)
    {
        $this->filesystemHelper = $filesystemHelper;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\LaravelInterpreter\Sources\SourceInterface::get()
     */
    public function extract(array $schema): array
    {
        $phrases = [];

        foreach ($this->getViews((array)config('view.paths'), $schema) as $view) {
            $phrases = array_merge($phrases, $this->getPhrases($view));
        }

        $phrases = array_filter(array_unique($phrases));
        return $this->getDiff($phrases, $schema);
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
                } elseif (stripos($item, '.php') && $this->filesystemHelper->passes($item, $schema['view_files'])) {
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
     * @param array $phrases
     * @param array $schema
     * @return array
     */
    protected function getDiff(array $phrases, array $schema): array
    {
        $targetData = $this->filesystemHelper->getStructure(\App::langPath()."/{$schema['target_locale']}/", $schema, true);

        if (isset($targetData['.json'])) {
            foreach ($phrases as $key => $value) {
                if (array_key_exists($value, $targetData['.json'])) {
                    unset($phrases[$key]);
                }
            }
        }

        return $phrases;
    }
}
