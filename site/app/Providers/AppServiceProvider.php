<?php

namespace App\Providers;

use App\Support\FirstBoot;
use App\Support\Settings;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Before anything in the web middleware group runs. A shop uploaded to
        // hosting nobody can log into has no APP_KEY until somebody types one
        // in, and without it every page is a bare 500 while /up — outside that
        // group — answers 200. See FirstBoot for why that is worth automating.
        FirstBoot::ensureWritablePaths();
        FirstBoot::ensureAppKey();

        // Saved settings over the ones .env supplied. A setting never saved
        // leaves the .env value alone, so an install configured by hand keeps
        // working exactly as it did until somebody uses the screen.
        Settings::apply();

        // Every page needs its own canonical URL and the layout is the only
        // place that uses it, so it is composed in rather than passed by each
        // route. Built from the current path against APP_URL, so the domain
        // lives in one setting rather than being stamped into the pages.
        View::composer('layouts.site', function ($view) {
            $path = request()->path();

            $view->with('canonical', rtrim(config('app.url'), '/').'/'.($path === '/' ? '' : $path));
        });
    }
}
