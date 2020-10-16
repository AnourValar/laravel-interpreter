<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

use AnourValar\LaravelInterpreter\Exceptions\InputException;
use Illuminate\Console\Command;

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
    protected $description = 'Update locale from filled file';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param \AnourValar\LaravelInterpreter\Services\ExportService $exportService
     * @return void
     */
    public function handle(\AnourValar\LaravelInterpreter\Services\ExportService $exportService)
    {
        try {
            // Input data
            $schema = $this->getSchema($this->argument('schema'));

            // Get current state
            $sourceData = $exportService->get($schema, true);
            $targetData = $exportService->get($schema, false);

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

                $data = $this->replace($data, $translate, $schema['lang_files']['exclude_keys']);
                $data = $this->clean($data);
                if (! $this->option('re-translate')) {
                    $data = array_replace_recursive($data, $targetData[$path]);
                }

                if ($data) {
                    $data = $this->sort($data, $sourceData[$path]);

                    if ($data != $targetData[$path]) {
                        $this->save(\App::langPath()."/{$schema['target_locale']}{$path}", $data, $this->argument('chmod'));
                        $imported = true;
                    }
                }
            }

            // Feedback
            if ($imported) {
                $this->info('Translate successfully imported.');
            } else {
                $this->warn('Nothing to import.');
            }
        } catch (InputException $e) {
            $this->error($e->getMessage());
        }
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
     * @param array $excludeKeys
     * @return array
     */
    protected function replace(array $source, array $data, array $excludeKeys): array
    {
        foreach ($source as $key => $value) {
            if (in_array($key, $excludeKeys)) {
                unset($source[$key]);
                continue;
            }

            if (is_scalar($value)) {
                foreach ($data as $original => $translate) {
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

                unset($source[$key]);
            } else {
                $source[$key] = $this->replace($value, $data, $excludeKeys);
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
     * @param string $path
     * @param array $data
     * @param string $chmod
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     */
    protected function save(string $path, array $data, string $chmod): void
    {
        if (preg_match('#\.php$#i', $path)) {
            $array = $this->exportArray($data, 4);

            $data = file_get_contents(__DIR__.'/../../resources/template.tpl');
            $data = str_replace('%PASTE HERE%', $array, $data);
        } else {
            $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), $chmod, true);
        }

        if (! file_put_contents($path, $data)) {
            throw new InputException('Cannot save to file "'.$path.'".');
        }
    }

    /**
     * @param array $data
     * @param array $reference
     * @return array
     */
    protected function sort(array $data, array $reference): array
    {
        uksort(
            $data,
            function ($a, $b) use ($reference)
            {
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
                $data[$key] = $this->sort($data[$key], $reference[$key]);
            }
        }

        return $data;
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
}
