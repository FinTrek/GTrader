<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        /*
        // Dump all queries
        DB::listen(function ($query) {

            $replace = function ($sql, $bindings) {
                $needle = '?';
                foreach ($bindings as $replace) {
                    $pos = strpos($sql, $needle);
                    if ($pos !== false) {
                        $sql = substr_replace($sql, "'".$replace."'", $pos, strlen($needle));
                    }
                }
                return $sql;
            };
            $sql = $replace($query->sql, $query->bindings);
            dump($sql);
        });
        */


        // Save some memory
        DB::connection()->disableQueryLog();

        // Set memory limit
        ini_set('memory_limit', config('app.memory_limit', '512M'));

        Blade::if('env', function ($environment) {
            return app()->environment($environment);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
