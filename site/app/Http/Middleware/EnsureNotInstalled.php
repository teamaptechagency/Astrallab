<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The wizard closes behind itself.
 *
 * Every step is unauthenticated — it has to be, since it creates the first
 * account and writes the database credentials. That is only safe while there
 * is nothing to take over. Once the site is installed these routes stop
 * existing, or they would be a way for a stranger to repoint it at their own
 * database and make themselves the operator.
 *
 * Deleting the marker file is the supported way to run it again, and that
 * needs access to the hosting account.
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Installer::isInstalled(), 404);

        return $next($request);
    }
}
