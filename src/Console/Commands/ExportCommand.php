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
    protected $signature = 'interpreter:export {schema} {--slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create translate file';

    /**
     * List of sources
     *
     * @var array
     */
    protected $sources = [
        \AnourValar\LaravelInterpreter\Sources\LangSource::class,
        \AnourValar\LaravelInterpreter\Sources\ViewsSource::class,
    ];

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
            $filename = $this->getFileName($schema);

            $data = [];

            foreach ($this->sources as $source) {
                $source = \App::make($source);
                if (! $source instanceof \AnourValar\LaravelInterpreter\Sources\SourceInterface) {
                    throw new \LogicException('Source must implements SourceInterface.');
                }

                $curr = $source->extract($schema);
                $data = array_replace($data, array_combine($curr, $curr));
            }

            $data = $this->excludePhrases($data, $schema);

            $this->sort($data);

            if ($this->option('slug')) {
                $data = $this->slug($data);
            }

            if (! count($data)) {
                $this->warn('Nothing to export.');
            } elseif (file_put_contents($filename, $this->getAdapter($schema)->export($data))) {
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
     * @param array $schema
     * @return array
     */
    protected function excludePhrases(array $data, array $schema): array
    {
        foreach ($data as $item) {
            if (in_array($item, $schema['exclude_phrases'])) {
                unset($data[$item]);
            }
        }

        return $data;
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
