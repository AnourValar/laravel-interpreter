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
    protected $signature = 'interpreter:export {schema} {--slug} {--re-translate} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a single file with phrases for translate';

    /**
     * @var \AnourValar\LaravelInterpreter\Services\ExportService
     */
    private $exportService;

    /**
     * Create a new command instance.
     *
     * @param \AnourValar\LaravelInterpreter\Services\ExportService $exportService
     * @return void
     */
    public function __construct(\AnourValar\LaravelInterpreter\Services\ExportService $exportService)
    {
        $this->exportService = $exportService;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            // Input data
            $schema = $this->getSchema($this->argument('schema'));
            $filename = $this->getFilename($schema);

            // Get current state
            $sourceData = $this->exportService->getFlat($schema, true);
            $targetData = $this->exportService->getFlat($schema, false);

            // Handle
            $data = [];
            foreach ($sourceData as $key => $value) {
                if ($this->isExcluded($schema, $value)) {
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

                $valueUpper = mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
                $valueLower = mb_strtolower(mb_substr($value, 0, 1)) . mb_substr($value, 1);
                if ($valueUpper !== $valueLower) {
                    if ($value === $valueUpper && isset($data[$valueLower])) {
                        unset($data[$value]);
                    }

                    if ($value === $valueLower && isset($data[$valueUpper])) {
                        unset($data[$value]);
                    }
                }
            }

            // Sort the result
            $this->sort($data);

            // Slug?
            if ($this->option('slug')) {
                $data = $this->slug($data);
            }

            // Missed phrases?
            $this->reportForMissed($schema);

            // Result
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

        return Command::SUCCESS;
    }

    /**
     * @throws \AnourValar\LaravelInterpreter\Exceptions\InputException
     * @return string
     */
    protected function getFilename(array $schema): string
    {
        $filename = \App::langPath() . '/' . $schema['filename'];

        if (!$this->option('force') && file_exists($filename)) {
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
        uksort($data, function ($a, $b) {
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
        foreach ($data as &$value) {
            if (is_string($value)) {
                $value = \Illuminate\Support\Str::ascii($value, 'en');
            }
        }
        unset($value);

        return $data;
    }

    /**
     * @param array $schema
     * @return void
     */
    protected function reportForMissed(array $schema): void
    {
        $data = [];
        foreach ($this->exportService->getMissed($schema) as $file => $phrases) {
            $data[] = [str_replace(resource_path(''), '', $file), implode(' / ', $phrases)];
        }

        if ($data) {
            $this->getOutput()->newLine();
            $this->alert('Missed phrases');
            $this->table(['View', 'Phrases'], $data);
            $this->getOutput()->newLine(2);
        }
    }
}
