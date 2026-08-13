<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A site nobody has set up yet sends every visitor to the wizard.
 *
 * What this replaces is the first experience of a fresh upload: a Laravel
 * exception about a database that does not exist, or a blank white page. One
 * is_file() against a path, no query.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Installer::isInstalled() || $request->is('install', 'install/*', 'up')) {
            return $next($request);
        }

        return redirect('/install');
    }
}
