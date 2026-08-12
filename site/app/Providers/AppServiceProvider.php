<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every page needs its own canonical URL and the layout is the only
        // place that uses it, so it is composed in rather than passed by each
        // route. Built from the current path against APP_URL: the domain lives
        // in one setting, not stamped into the pages.
        View::composer("layouts.site", function ($view) {
            $view->with("canonical", rtrim(config("app.url"), "/")."/".ltrim(request()->path() === "/" ? "" : request()->path(), "/"));
        });
        //
    }
}
