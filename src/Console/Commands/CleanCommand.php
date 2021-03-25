<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

use AnourValar\LaravelInterpreter\Exceptions\InputException;
use Illuminate\Console\Command;
use AnourValar\LaravelInterpreter\Services\ExportService;
use AnourValar\LaravelInterpreter\Services\ImportService;

class CleanCommand extends Command
{
    use SchemaTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpreter:clean {schema}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleaning localization keys';

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
     * @param \AnourValar\LaravelInterpreter\Services\ImportService $importService
     * @return void
     */
    public function handle(ExportService $exportService, ImportService $importService)
    {
        try {
            // Input data
            $schema = $this->getSchema($this->argument('schema'));

            // Get current state
            $sourceData = $exportService->get($schema, true, true);
            $targetData = $exportService->get($schema, false, false);

            // Proccess
            $saved = false;
            foreach ($targetData as $path => $data) {
                if (! isset($sourceData[$path])) {
                    unlink(\App::langPath()."/{$schema['target_locale']}{$path}");
                    $saved = true;
                    continue;
                }

                $newData = $this->cleanStructure($data, $sourceData[$path]);
                if ($newData == $data) {
                    continue;
                }

                $saved = true;
                if ($newData) {
                    if (! $importService->save(\App::langPath()."/{$schema['target_locale']}{$path}", $newData)) {
                        throw new InputException('Cannot save to file "'.$path.'".');
                    }
                } else {
                    unlink(\App::langPath()."/{$schema['target_locale']}{$path}");
                }
            }

            // Feedback
            if ($saved) {
                $this->info('Translate successfully cleaned.');
            } else {
                $this->warn('Nothing to clean.');
            }
        } catch (InputException $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * @param array $targetData
     * @param array $sourceData
     * @throws \LogicException
     * @return array
     */
    private function cleanStructure(array $targetData, array $sourceData): array
    {
        foreach ($targetData as $key => $value) {
            if (! isset($sourceData[$key])) {
                unset($targetData[$key]);
                continue;
            }

            if (is_array($value)) {
                if (! is_array($sourceData[$key])) {
                    throw new \LogicException("\"$key\" key must be array in source locale.");
                }

                $targetData[$key] = $this->cleanStructure($value, $sourceData[$key]);
            }
        }

        return $targetData;
    }
}
