<?php

namespace MatinUtils\LogSystem;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton('log-system', function ($app) {
            $logSys = new LogSystem;
            return $logSys;
        });
        app('log-system');
        if (class_exists('Laravel\Lumen\Application') && is_a($this->app, 'Laravel\Lumen\Application')) {
            $this->app->middleware([Middleware::class]);
        }else{
            ///> add \MatinUtils\LogSystem\Middleware::class to kernel.php
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
    }

    public function provides()
    {
        return [];
    }
}
