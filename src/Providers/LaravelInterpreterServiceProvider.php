<?php

namespace AnourValar\LaravelInterpreter\Providers;

use Illuminate\Support\ServiceProvider;

class LaravelInterpreterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AnourValar\LaravelInterpreter\Console\Commands\CleanCommand::class,
                \AnourValar\LaravelInterpreter\Console\Commands\ExportCommand::class,
                \AnourValar\LaravelInterpreter\Console\Commands\ImportCommand::class,
                \AnourValar\LaravelInterpreter\Console\Commands\SchemaCommand::class,
            ]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }
}
