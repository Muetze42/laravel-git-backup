<?php

namespace NormanHuth\LaravelGitBackup;

use Illuminate\Support\ServiceProvider as Provider;

class ServiceProvider extends Provider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/git-backup.php' => config_path('git-backup.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands($this->getCommands());
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/git-backup.php', 'git-backup'
        );
    }

    /**
     * Get all package commands
     *
     * @return array
     */
    protected function getCommands(): array
    {
        return array_filter(array_map(function ($item) {
            return '\\'.__NAMESPACE__.'\\Console\\Commands\\'.pathinfo($item, PATHINFO_FILENAME);
        }, glob(__DIR__.'/Console/Commands/*.php')), function ($item) {
            return class_basename($item) != 'Command';
        });
    }
}
