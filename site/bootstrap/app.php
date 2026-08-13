<?php

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureNotInstalled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Before anything else on a web request: a site nobody has set up yet
        // sends its visitor to the installer rather than to an exception about
        // a database that does not exist. One is_file(), and no queries.
        $middleware->prependToGroup('web', EnsureInstalled::class);

        $middleware->alias([
            'not-installed' => EnsureNotInstalled::class,
        ]);

        // Laravel sends signed-out visitors to a route named "login". There
        // isn't one — the console's three states all live behind /apt-admin —
        // so without this, opening a console page while signed out is a 500
        // rather than the sign-in form.
        $middleware->redirectGuestsTo(fn () => '/apt-admin');

        // And somebody already signed in who opens /apt-admin gets the console
        // rather than a form they do not need.
        $middleware->redirectUsersTo(fn () => '/apt-admin');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
