<?php

namespace F2re\Aero;

use Illuminate\Support\ServiceProvider;

class AeroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make('F2re\Aero\Controllers\AeroController');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        include __DIR__.'/routes.php';     
    }
}
