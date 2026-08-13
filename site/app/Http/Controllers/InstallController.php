<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Installer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Setting the site up, in three screens.
 *
 * Nothing here is authenticated, because this is what creates the first
 * account. The wizard closes behind itself instead — see EnsureNotInstalled.
 *
 * Nothing is written until the last step. Each screen collects and checks its
 * own answers into the session; only Finish touches .env, the database or the
 * account. A wizard that wrote as it went would leave somebody who closed the
 * tab at step two with a half-configured site and no way to start again.
 */
class InstallController extends Controller
{
    private const DATABASE = 'install.database';

    private const ACCOUNT = 'install.account';

    /** What this host can and cannot do. */
    public function welcome()
    {
        $checks = Installer::requirements();

        return view('install.welcome', [
            'step' => 1,
            'checks' => $checks,
            'blocked' => Installer::failed($checks) !== [],
            'exposure' => Installer::envExposure(request()->getSchemeAndHttpHost()),
        ]);
    }

    public function database()
    {
        return view('install.database', [
            'step' => 2,
            // What Hostinger gives nearly everybody, prefilled because it is
            // right far more often than not, and because somebody who does not
            // know what a database host is has one fewer box to be frightened
            // of.
            'saved' => session(self::DATABASE, ['host' => 'localhost', 'port' => '3306']),
        ]);
    }

    public function saveDatabase(Request $request)
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'numeric'],
            'database' => ['required', 'string', 'max:190'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
        ]);

        $result = Installer::testDatabase($data);

        if (! $result['ok']) {
            // Back to the form with what they typed and why it did not work.
            // The password comes back too: retyping it is how the second
            // attempt fails for a different reason.
            return back()->withInput()->withErrors(['database' => $result['message']]);
        }

        session([self::DATABASE => $data]);

        return redirect('/install/account');
    }

    public function account()
    {
        if (! session()->has(self::DATABASE)) {
            return redirect('/install/database');
        }

        return view('install.account', [
            'step' => 3,
            'saved' => session(self::ACCOUNT, []),
        ]);
    }

    public function saveAccount(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            // Length over character classes: a long phrase is both stronger and
            // likelier to be remembered than a short one with a symbol on the
            // end. This account can revoke every customer's licence.
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        session([self::ACCOUNT => $data]);

        return $this->install($request);
    }

    /**
     * Build it.
     *
     * The order is the whole of the care. Settings first, so a run that dies
     * halfway can be finished by hand; then the tables; then the account,
     * because a site with a database and nobody who can open it is worse than
     * the other way round; and the marker that closes this wizard last of all.
     */
    private function install(Request $request)
    {
        $database = session(self::DATABASE);
        $account = session(self::ACCOUNT);

        if (! $database || ! $account) {
            return redirect('/install');
        }

        Installer::writeEnv([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            // Taken from the address actually being used rather than asked for.
            // Getting this wrong is invisible — the site works perfectly while
            // telling search engines its real home is somewhere else.
            'APP_URL' => $request->getSchemeAndHttpHost(),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['host'],
            'DB_PORT' => $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => (string) ($database['password'] ?? ''),
            // Files for both. The wizard needs a session before there is
            // anywhere to store one, and on this hosting a file beats a query.
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
        ]);

        // Written above for the next request; this one was configured when it
        // started, so the cache has to be pointed at the new place by hand.
        config(['cache.default' => 'file']);

        $built = Installer::migrate($database);

        if (! $built['ok']) {
            return back()->withInput()->withErrors(['install' => $built['message']]);
        }

        try {
            $operator = User::create([
                'name' => $account['name'],
                'email' => $account['email'],
                'password' => Hash::make($account['password']),
            ]);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors([
                'install' => 'The tables were built but the account could not be created: '.$e->getMessage(),
            ]);
        }

        Installer::lock(['operator' => $operator->email]);

        // Compiled config and routes from before this describe a different
        // .env. Cleared here rather than left for the first visitor to trip on.
        foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
                // A host with an unwritable cache directory has already failed
                // the requirements check; this is not the place to stop.
            }
        }

        Auth::login($operator);
        $request->session()->regenerate();

        return redirect('/apt-admin/settings')
            ->with('ok', 'Your site is installed. These details show on the public pages.');
    }
}
