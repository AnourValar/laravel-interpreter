<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

use AnourValar\LaravelInterpreter\Exceptions\InputException;
use Illuminate\Console\Command;
use AnourValar\LaravelInterpreter\Services\ExportService;
use AnourValar\LaravelInterpreter\Services\ImportService;

class ImportCommand extends Command
{
    use SchemaTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpreter:import {schema} {--re-translate} {chmod=0755}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update project\'s structure from a filled single file';

    /**
     * Execute the console command.
     *
     * @param \AnourValar\LaravelInterpreter\Services\ExportService $exportService
     * @param \AnourValar\LaravelInterpreter\Services\ImportService $importService
     * @return int
     */
    public function handle(ExportService $exportService, ImportService $importService)
    {
        try {
            // Input data
            $schema = $this->getSchema($this->argument('schema'));

            // Get current state
            $sourceData = $exportService->get($schema, true, false);
            $fullSourceData = $exportService->get($schema, true, true);
            $targetData = $exportService->get($schema, false, true);

            // Prepare data for import
            $sourceDataFlat = $exportService->getFlat($schema, true);
            $targetDataFlat = $exportService->getFlat($schema, false);

            $translate = [];
            foreach (array_keys($targetDataFlat) as $key) {
                if (isset($sourceDataFlat[$key])) {
                    $translate[$sourceDataFlat[$key]] = $targetDataFlat[$key];
                }
            }

            $translate = array_replace($translate, $this->load($schema));

            // Handle
            $imported = false;
            foreach ($sourceData as $path => $data) {
                if (! isset($targetData[$path])) {
                    $targetData[$path] = [];
                }

                $keyMap = str_replace('/', '.', preg_replace('#\.php$#', '', $path));
                $data = $this->replace($data, $translate, $schema, $keyMap);
                $data = $this->excludeClean($data, $schema);
                $data = $this->clean($data);
                if (! $this->option('re-translate')) {
                    $data = array_replace_recursive($data, $targetData[$path]);
                }

                if ($data) {
                    $data = $this->sort($data, $fullSourceData[$path], ($path == '/<locale>.json'));

                    if ($data != $targetData[$path]) {
                        $path = str_replace('<locale>', $schema['target_locale'], $path);

                        if (! $importService->save(\App::langPath() . $path, $data, $this->argument('chmod'))) {
                            throw new InputException('Cannot save to the file "'.$path.'".');
                        }
                        $imported = true;
                    }
                }
            }

            // Response
            if ($imported) {
                $this->info('Translate successfully imported.');
            } else {
                $this->warn('Nothing to import.');
            }
        } catch (InputException $e) {
            $this->error($e->getMessage());
        }

        return Command::SUCCESS;
    }

    /**
     * @param array $schema
     * @return array
     */
    protected function load(array $schema): array
    {
        $data = [];

        $filename = \App::langPath() . '/' . $schema['filename'];
        if (file_exists($filename)) {
            $data = $this->getAdapter($schema)->import(file_get_contents($filename));
        }

        return $data;
    }

    /**
     * @param array $source
     * @param array $data
     * @param array $schema
     * @param string $keyMap
     * @return array
     */
    protected function replace(array $source, array $data, array $schema, string $keyMap): array
    {
        foreach ($source as $key => $value) {
            $currKeyMap = sprintf('%s.%s', $keyMap, $key);

            $currKeyMapArray = explode('.', $currKeyMap);
            while (! is_null(array_shift($currKeyMapArray))) {
                if (in_array(implode('.', $currKeyMapArray), $schema['lang_files']['exclude_keys'], true)) {
                    unset($source[$key]);
                    continue 2;
                }
            }

            if (is_scalar($value)) {
                foreach ($data as $original => $translate) {
                    $valueFirst = mb_substr($value, 0, 1);
                    if ($valueFirst === mb_strtoupper($valueFirst)) {
                        $original = mb_strtoupper(mb_substr($original, 0, 1)) . mb_substr($original, 1);
                        $translate = mb_strtoupper(mb_substr($translate, 0, 1)) . mb_substr($translate, 1);
                    } elseif ($valueFirst === mb_strtolower($valueFirst)) {
                        $original = mb_strtolower(mb_substr($original, 0, 1)) . mb_substr($original, 1);
                        $translate = mb_strtolower(mb_substr($translate, 0, 1)) . mb_substr($translate, 1);
                    }

                    if ($value == $original) {
                        $source[$key] = $translate;

                        preg_match_all('#(\:[a-z][a-z\d]*)#i', $original, $checkOriginal);
                        preg_match_all('#(\:[a-z][a-z\d]*)#i', $translate, $checkTranslate);

                        if ($checkOriginal != $checkTranslate) {
                            $this->warn("Different parameters: '$original' => '$translate'");
                        }

                        continue 2;
                    }
                }

                if (! $this->isExcluded($schema, $source[$key])) { // remove not translated phrases
                    unset($source[$key]);
                }
            } else {
                $source[$key] = $this->replace($value, $data, $schema, $currKeyMap);
            }
        }

        return $source;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function clean(array $data): array
    {
        foreach ($data as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $data[$key] = $this->clean($data[$key]);

            if (! count($data[$key])) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function excludeClean(array $data, array $schema): array
    {
        foreach ($data as $key => $value) {
            $values = $this->getValues($value);

            foreach ($values as $value) {
                if (! is_string($value) || ! $this->isExcluded($schema, $value)) {
                    continue 2;
                }
            }

            unset($data[$key]);
        }

        return $data;
    }

    /**
     * @param array $data
     * @param array $reference
     * @param bool $json
     * @return array
     */
    protected function sort(array $data, array $reference, bool $json): array
    {
        if ($json) {
            uksort(
                $data,
                function ($a, $b) {
                    if (mb_strlen($a) > mb_strlen($b)) {
                        return -1;
                    }

                    if (mb_strlen($a) < mb_strlen($b)) {
                        return 1;
                    }

                    return $a > $b ? -1 : 1;
                }
            );

            return $data;
        }

        uksort(
            $data,
            function ($a, $b) use ($reference) {
                foreach (array_keys($reference) as $key) {
                    if ($key == $a) {
                        return -1;
                    }

                    if ($key == $b) {
                        return 1;
                    }
                }

                return 1;
            }
        );

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sort($data[$key], ($reference[$key] ?? $data[$key]), $json);
            }
        }

        return $data;
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private function getValues($data)
    {
        $values = [];

        if (is_array($data)) {
            foreach ($data as $item) {
                $values = array_merge($values, $this->getValues($item));
            }
        } else {
            $values[] = $data;
        }

        return $values;
    }
}
