<?php

namespace AnourValar\LaravelInterpreter\Sources;

class LangSource implements SourceInterface
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
        $data = [];

        $sourceData = $this->filesystemHelper->getStructure(\App::langPath()."/{$schema['source_locale']}/", $schema);
        $targetData = $this->filesystemHelper->getStructure(\App::langPath()."/{$schema['target_locale']}/", $schema);

        foreach ($this->getDiff($sourceData, $targetData, $schema['lang_files']['exclude_keys']) as $item) {
            $item = collect($item)->flatten()->toArray();
            $data = array_merge($data, $item);
        }

        return array_unique($data);
    }

    /**
     * @param array $array1
     * @param array $array2
     * @param array $excludeKeys
     * @return array
     */
    protected function getDiff(array $array1, array $array2, array $excludeKeys): array
    {
        $result = [];

        foreach ($array1 as $key => $value) {
            if (in_array($key, $excludeKeys)) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->getDiff($value, ($array2[$key] ?? []), $excludeKeys);

                if ($value) {
                    $result[$key] = $value;
                }
            } elseif (! isset($array2[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
