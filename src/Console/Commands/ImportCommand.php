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
    protected $signature = 'interpreter:import {schema} {chmod=0755}';

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
     * @param \AnourValar\LaravelInterpreter\Helpers\FilesystemHelper $filesystemHelper
     * @return void
     */
    public function handle(\AnourValar\LaravelInterpreter\Helpers\FilesystemHelper $filesystemHelper)
    {
        try {
            $schema = $this->getSchema($this->argument('schema'));
            $filename = $this->getFileName($schema);

            $sourceData = $filesystemHelper->getStructure(\App::langPath()."/{$schema['source_locale']}/", $schema);
            $targetData = $filesystemHelper->getStructure(\App::langPath()."/{$schema['target_locale']}/", $schema);

            $viewsData = \App::make(\AnourValar\LaravelInterpreter\Sources\ViewsSource::class)->extract($schema);
            $sourceData['.json'] = array_replace(
                ($sourceData['.json'] ?? []),
                array_combine($viewsData, $viewsData)
            );

            $translate = $this->getAdapter($schema)->import(file_get_contents($filename));

            $imported = false;
            foreach ($sourceData as $path => $data) {
                if (! isset($targetData[$path])) {
                    $targetData[$path] = [];
                }

                $data = $this->replace($data, $translate, $schema['lang_files']['exclude_keys']);
                $data = $this->clean($data);
                $data = array_replace_recursive($data, $targetData[$path]);

                if ($data) {
                    $data = $this->sort($data, $sourceData[$path]);

                    if ($data != $targetData[$path]) {
                        $this->save(\App::langPath()."/{$schema['target_locale']}{$path}", $data, $this->argument('chmod'));
                        $imported = true;
                    }
                }
            }

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
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     * @return string
     */
    protected function getFilename(array $schema): string
    {
        $filename = \App::langPath() . '/' . $schema['filename'];

        if (! file_exists($filename)) {
            throw new InputException('Translate not exists. File: "'.$filename.'".');
        }

        return $filename;
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
