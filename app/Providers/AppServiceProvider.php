<?php

namespace App\Providers;

use App\Console\Commands\ServeCommand;
use Illuminate\Foundation\Console\ServeCommand as FrameworkServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FrameworkServeCommand::class, ServeCommand::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
