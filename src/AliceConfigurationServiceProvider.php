<?php

namespace Alice\Configuration;

use Illuminate\Support\ServiceProvider;

class AliceConfigurationServiceProvider extends ServiceProvider {

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        // $this->loadViewsFrom(__DIR__.'/views', 'contact');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        if (\File::exists(__DIR__ . '/Helper.php')) {
            require __DIR__ . '/Helper.php';
        }

        $this->mergeConfigFrom(
            __DIR__.'/config/AliceConstants.php', 'aliceConstants'
        );
        $this->publishes([
            __DIR__.'/config/AliceConstants.php' => config_path('aliceConstants.php'),
        ]);

    }

    public function register()
    {

    }

}