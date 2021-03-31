<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS;

use Illuminate\Support\ServiceProvider;
use Auth;

class IFRSServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ifrs.php', 'ifrs');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/ifrs.php' => config_path('ifrs.php'),
        ]);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadFactoriesFrom(__DIR__ . '/../database/factories');

        dd(Auth::user());
    }
}
