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
    protected $signature = 'interpreter:export {schema} {--slug} {--re-translate}';

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
     * @param \AnourValar\LaravelInterpreter\Services\ExportService $exportService
     * @return void
     */
    public function handle(\AnourValar\LaravelInterpreter\Services\ExportService $exportService)
    {
        try {
            // Input data
            $schema = $this->getSchema($this->argument('schema'));
            $filename = $this->getFileName($schema);

            // Get current state
            $sourceData = $exportService->getFlat($schema, true);
            $targetData = $exportService->getFlat($schema, false);

            // Handle
            $data = [];
            foreach ($sourceData as $key => $value) {
                if (in_array($value, $schema['exclude_phrases'], true)) {
                    continue;
                }

                if (isset($targetData[$key]) && $this->option('re-translate')) {

                    $data[$value] = $targetData[$key];

                } elseif (isset($targetData[$key]) && !$this->option('re-translate')) {

                    unset($data[$value]);
                    $schema['exclude_phrases'][] = $value;

                } elseif (! isset($data[$value])) {

                    $data[$value] = $value;

                }
            }

            // Sort the result
            $this->sort($data);

            // Slug?
            if ($this->option('slug')) {
                $data = $this->slug($data);
            }

            // Save it
            if (! count($data)) {
                $this->warn('Nothing to export.');
            } elseif (file_put_contents($filename, $this->getAdapter($schema)->export($data))) {
                $this->info('Translate successfully created. File: "'.$filename.'".');
            } else {
                $this->error('Cannot save to file "'.$filename.'".');
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

        if (file_exists($filename)) {
            throw new InputException('Translate already exists. File: "'.$filename.'".');
        }

        return $filename;
    }

    /**
     * @param array $data
     * @return void
     */
    protected function sort(array &$data): void
    {
        uksort($data, function ($a, $b)
        {
            if (mb_strlen($a) < mb_strlen($b)) {
                return 1;
            }

            if (mb_strlen($a) > mb_strlen($b)) {
                return -1;
            }

            return ($a < $b) ? 1 : -1;
        });
    }

    /**
     * @param array $data
     * @return array
     */
    protected function slug(array $data): array
    {
        $slugHelper = \App::make(\AnourValar\LaravelInterpreter\Helpers\SlugHelper::class);

        foreach ($data as &$value) {
            $value = $slugHelper->translit($value);
        }
        unset($value);

        return $data;
    }
}
