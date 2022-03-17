<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

use AnourValar\LaravelInterpreter\Exceptions\InputException;
use Illuminate\Console\Command;
use AnourValar\LaravelInterpreter\Services\ExportService;
use AnourValar\LaravelInterpreter\Services\ImportService;

class WrapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpreter:wrap {template} {wrap=@lang(\'%s\')}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'To wrap skipped phrases';

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
            $templatePath = base_path($this->argument('template'));
            if (! is_file($templatePath)) {
                throw new InputException('File "'.$templatePath.'" not exists.');
            }

            // Handle
            $template = $this->wrap(file_get_contents($templatePath), $this->argument('wrap'), $counter);

            // Result
           if (! $counter) {
                $this->info('Nothing to wrap.');
            } elseif ( file_put_contents($templatePath, $template) !== false) {
                $this->info("Template wrapped. Patterns: $counter");
            } else {
                $this->error('Something went wrong.');
            }
        } catch (InputException $e) {
            $this->error($e->getMessage());
        }

        return Command::SUCCESS;
    }

    /**
     * @param string $template
     * @param string $wrap
     * @param int $counter
     * @return string
     */
    protected function wrap(string $template, string $wrap, int &$counter = null): string
    {
        // Patterns for ignore
        $ignorePatterns = [];

        $regexps = [
            '#\{\{.*\}\}#U',
            '#\{\!\!.*\!\!\}#U',
            '#\<\?.*\?\>#U',
            '#\<[\/a-z].*\>#iU',
            '#\@[a-z]+\(.*\)#iU',
        ];

        foreach ($regexps as $regexp) {
            $template = preg_replace_callback(
                $regexp,
                function ($pattern) use (&$ignorePatterns)
                {
                    $key = "<<!@#-- ".sha1($pattern[0])." --#@!>>";
                    $ignorePatterns[$key] = $pattern[0];

                    return $key;
                },
                $template
            );
        }

        $ignorePatterns = array_reverse($ignorePatterns);


        // Patterns for replace
        $template = preg_replace_callback(
            '#([^\<\>\n]+)#',
            function ($pattern) use ($wrap, &$counter)
            {
                foreach (config('interpreter.regexp_miss') as $regexp) {
                    if (preg_match($regexp, $pattern[0])) {
                        $counter++;

                        $value = str_pad('', (mb_strlen($pattern[0]) - mb_strlen(ltrim($pattern[0]))), ' ', STR_PAD_LEFT);
                        $value .= sprintf($wrap, addcslashes(preg_replace('#\s+#', ' ', trim($pattern[0])), "\\'"));
                        $value .= str_pad('', (mb_strlen($pattern[0]) - mb_strlen(rtrim($pattern[0]))), ' ', STR_PAD_RIGHT);

                        return $value;
                    }
                }

                return $pattern[0];
            },
            $template
        );


        // Get the result
        return str_replace(array_keys($ignorePatterns), array_values($ignorePatterns), $template);
    }
}
