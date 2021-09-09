<?php

namespace AnourValar\LaravelInterpreter\Console\Commands;

use Illuminate\Console\Command;

class SchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpreter:schema {targetLocale}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create schema (config) for a locale';

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
        $targetLocale = $this->argument('targetLocale');
        $path = \App::langPath() . '/' . $targetLocale . '_schema.json';

        $schema = file_get_contents(__DIR__.'/../../resources/schema.json');
        $schema = str_replace('%LOCALE%', $targetLocale, $schema);
        $schema = str_replace('%DEFAULT_LOCALE%', config('app.locale'), $schema);

        if (file_exists($path)) {

            $this->error('Schema already exists. File: "'.$path.'".');

        } elseif (! file_put_contents($path, $schema)) {

            $this->error('Cannot save schema to file "'.$path.'".');

        } else {

            $this->info('Schema successfully created. File: "'.$path.'".');

        }
    }
}
