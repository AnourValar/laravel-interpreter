<?php

namespace AnourValar\LaravelInterpreter\Providers;

use Illuminate\Support\ServiceProvider;

class LaravelInterpreterServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // config
        $this->mergeConfigFrom(__DIR__.'/../resources/config/interpreter.php', 'interpreter');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // config
        $this->publishes([__DIR__.'/../resources/config/interpreter.php' => config_path('interpreter.php')], 'config');

        // commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AnourValar\LaravelInterpreter\Console\Commands\CleanCommand::class,
                \AnourValar\LaravelInterpreter\Console\Commands\ExportCommand::class,
                \AnourValar\LaravelInterpreter\Console\Commands\ImportCommand::class,
                \AnourValar\LaravelInterpreter\Console\Commands\SchemaCommand::class,
                \AnourValar\LaravelInterpreter\Console\Commands\WrapCommand::class,
            ]);
        }
    }
}
