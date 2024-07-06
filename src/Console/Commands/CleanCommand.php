<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

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
    protected $description = 'Clean unused translates (garbage)';

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
                $path = str_replace('<locale>', $schema['target_locale'], $path);
                if ($newData) {
                    if (! $importService->save(\App::langPath() . $path, $newData)) {
                        throw new \InvalidArgumentException('Cannot save to the file "'.$path.'".');
                    }
                } else {
                    unlink(\App::langPath() . $path);
                }
            }

            // Response
            if ($saved) {
                $this->info('Translate successfully cleaned.');
            } else {
                $this->warn('Nothing to clean.');
            }
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
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
