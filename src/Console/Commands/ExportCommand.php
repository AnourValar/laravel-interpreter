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
            $excludes = $this->getExcludes($schema);

            $data = [];
            $sourceData = $this->getStructure(\App::langPath()."/$sourceLocale/", $excludes);
            $targetData = $this->getStructure(\App::langPath()."/$targetLocale/", $excludes);

            foreach ($this->getDiff($sourceData, $targetData) as $item) {
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
     * @return array
     */
    protected function getDiff(array $array1, array $array2) : array
    {
        $result = [];

        foreach ($array1 as $key => $value) {
            if (! isset($array2[$key])) {
                $result[$key] = $value;
            } else if (is_array($value)) {
                $value = $this->getDiff($value, $array2[$key]);

                if ($value) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}
