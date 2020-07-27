<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

use AnourValar\LaravelInterpreter\Exceptions\InputException;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    use SchemaTrait;

    /**
     * @var integer
     */
    protected const CHMOD = 0755;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpreter:import {schema}';

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
     * @return void
     */
    public function handle()
    {
        try {
            $schema = $this->getSchema($this->argument('schema'));

            $sourceLocale = $this->getSourceLocale($schema);
            $targetLocale = $this->getTargetLocale($schema);
            $filename = $this->getFileName($schema);
            $adapter = $this->getAdapter($schema);
            $filters = $this->getFilters($schema);

            $sourceData = $this->getStructure(\App::langPath()."/$sourceLocale/", $filters);
            $targetData = $this->getStructure(\App::langPath()."/$targetLocale/", $filters);

            $translate = $adapter->import(file_get_contents($filename));

            $imported = false;
            foreach ($sourceData as $path => $data) {
                if (! isset($targetData[$path])) {
                    $targetData[$path] = [];
                }

                $data = $this->replace($data, $translate);
                $data = $this->clean($data);
                $data = array_replace_recursive($data, $targetData[$path]);

                if ($data) {
                    $data = $this->sort($data, $sourceData[$path]);

                    if ($data != $targetData[$path]) {
                        $this->save(\App::langPath()."/{$targetLocale}{$path}", $data);
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
     * @return array
     */
    protected function replace(array $source, array $data): array
    {
        foreach ($source as $key => $value) {
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
                $source[$key] = $this->replace($value, $data);
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
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     */
    protected function save(string $path, array $data): void
    {
        if (preg_match('#\.php$#i', $path)) {
            $array = $this->exportArray($data, 4);

            $data = file_get_contents(__DIR__.'/../../resources/template.tpl');
            $data = str_replace('%PASTE HERE%', $array, $data);
        } else {
            $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), static::CHMOD, true);
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
}
