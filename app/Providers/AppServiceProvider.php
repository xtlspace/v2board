<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (config('v2board.force_https')) {
            resolve(\Illuminate\Routing\UrlGenerator::class)->forceScheme('https');
        }

        $this->app['view']->addNamespace('theme', public_path() . '/theme');
    }
}
