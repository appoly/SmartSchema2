<?php

namespace Appoly\SmartSchema2;

use Appoly\SmartSchema2\Console\Commands\GenerateSchema;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class SmartSchema2ServiceProvider extends ServiceProvider
{
    /**
     * Publishes configuration file.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSchema::class
            ]);
        }
    }

    /**
     * Make config publishment optional by merging the config from the package.
     *
     * @return void
     */
    public function register()
    { }
}
