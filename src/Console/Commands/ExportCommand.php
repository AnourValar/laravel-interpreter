<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

use AnourValar\LaravelInterpreter\Exceptions\InputException;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    use SchemaTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpreter:export {schema}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create translate file';

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

            $data = [];
            $sourceData = $this->getStructure(\App::langPath()."/$sourceLocale/", $filters);
            $targetData = $this->getStructure(\App::langPath()."/$targetLocale/", $filters);

            foreach ($this->getDiff($sourceData, $targetData, $filters) as $item) {
                $item = collect($item)->flatten()->toArray();
                $data = array_replace($data, array_combine($item, $item));
            }

            if (! count($data)) {
                $this->warn('Nothing to export.');
            } else if (file_put_contents($filename, $adapter->export($data))) {
                $this->info('Translate successfully created. File: "'.$filename.'".');
            } else {
                $this->error('Cannot save create file "'.$filename.'".');
            }
        } catch (InputException $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     * @return string
     */
    protected function getFilename(array $schema) : string
    {
        $filename = \App::langPath() . '/' . $schema['filename'];

        if (file_exists($filename)) {
            throw new InputException('Translate already exists. File: "'.$filename.'".');
        }

        return $filename;
    }

    /**
     * @param array $array1
     * @param array $array2
     * @param array $filters
     * @return array
     */
    protected function getDiff(array $array1, array $array2, array $filters) : array
    {
        $result = [];

        foreach ($array1 as $key => $value) {
            if (in_array($value, $filters['exclude_phrases'])) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->getDiff($value, ($array2[$key] ?? []), $filters);

                if ($value) {
                    $result[$key] = $value;
                }
            } else if (! isset($array2[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
