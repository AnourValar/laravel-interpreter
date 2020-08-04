<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

use Illuminate\Console\Command;

class WalkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpreter:walk {sourceLocale?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bypassing templates (views) and saving translations';

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
        $sourceLocale = $this->argument('sourceLocale');
        if (! $sourceLocale) {
            $sourceLocale = config('app.locale');
        }

        $phrases = [];
        foreach ($this->getViews(config('view.paths')) as $view) {
            $phrases = array_merge($phrases, $this->getPhrases($view));
        }

        $phrases = $this->clean($phrases);
        if (! count($phrases)) {
            $this->warn('Nothing to update.');
            return;
        }

        $path = \App::langPath()."/{$sourceLocale}_walk.json";
        if ($this->save($phrases, $path)) {
            $this->info('Translate successfully updated. File: "'.$path.'".');
        } else {
            $this->error('Cannot save to file "'.$path.'".');
        }
    }

    /**
     * @param mixed $paths
     * @return array
     */
    protected function getViews($paths): array
    {
        $result = [];

        foreach ((array)$paths as $path) {
            foreach (scandir($path) as $item) {
                if (in_array($item, ['.', '..'])) {
                    continue;
                }

                $item = "$path/$item";

                if (is_dir($item)) {
                    $result = array_merge($result, $this->getViews($item));
                } elseif (stripos($item, '.php')) {
                    $result[] = file_get_contents($item);
                }
            }
        }

        return $result;
    }

    /**
     * @param string $view
     * @return array
     */
    protected function getPhrases(string $view): array
    {
        $view = preg_replace('#\{\{\-\-.*\-\-\}\}#sU', '<>', $view);

        $result = [];

        preg_match_all('|\@lang\(\s*([\'\"])(.*?)(?<!\\\)\1|s', $view, $patterns);
        if (isset($patterns[2])) {
            $result = array_merge($result, $patterns[2]);
        }

        preg_match_all('|\_\_\(\s*([\'\"])(.*?)(?<!\\\)\1|s', $view, $patterns);
        if (isset($patterns[2])) {
            $result = array_merge($result, $patterns[2]);
        }

        return $result;
    }

    /**
     * @param array $result
     * @return array
     */
    protected function clean(array $phrases): array
    {
        $phrases = array_unique($phrases);
        $phrases = array_filter($phrases);

        return $phrases;
    }

    /**
     * @param array $phrases
     * @param string $path
     * @return bool
     */
    protected function save(array $phrases, string $path): bool
    {
        $phrases = array_fill_keys($phrases, null);

        uksort($phrases, function ($a, $b)
        {
            if (mb_strlen($a) < mb_strlen($b)) {
                return 1;
            }

            if (mb_strlen($a) > mb_strlen($b)) {
                return -1;
            }

            return ($a < $b) ? 1 : -1;
        });

        return (bool)file_put_contents(
            $path,
            json_encode($phrases, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }
}
