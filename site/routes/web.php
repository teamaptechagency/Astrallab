<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| First run
|--------------------------------------------------------------------------
|
| Unauthenticated, because this is what creates the first account and writes
| the database credentials. What makes that safe is that the whole group stops
| existing the moment the site is installed, and until then every request
| outside it is redirected here.
|
| Rate limited all the same: step two opens a connection to whatever host it is
| given, and that should not be usable as a port scanner.
*/

Route::middleware(['not-installed', 'throttle:20,1'])->group(function () {
    Route::get('/install', [InstallController::class, 'welcome']);

    Route::get('/install/database', [InstallController::class, 'database']);
    Route::post('/install/database', [InstallController::class, 'saveDatabase']);

    Route::get('/install/account', [InstallController::class, 'account']);
    Route::post('/install/account', [InstallController::class, 'saveAccount']);
});

/*
|--------------------------------------------------------------------------
| astralab.com
|--------------------------------------------------------------------------
|
| One application, three jobs, one domain:
|
|   /              the public site — what we sell, how it installs, what it costs
|   /apt-admin     the operator console — licences, releases, customers
|   /api/v1/*      what every customer's CMS calls: activate, heartbeat, download
|
| They were two deployments before, on two runtimes. Merging them means one
| upload to one shared-hosting account, one set of credentials, and the
| catalogue the home page fetches becomes same-origin — no CORS at all.
|
| The console is at /apt-admin rather than /admin because /admin is the first
| path every scanner tries. It is not a security control on its own — the
| password is — but it keeps the login form out of the logs.
|
*/

Route::view('/', 'pages.home')->name('home');
Route::view('/docs', 'pages.docs')->name('docs');
Route::view('/services', 'pages.services')->name('services');
Route::view('/contact', 'pages.contact')->name('contact');

Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/refund', [LegalController::class, 'refund'])->name('refund');

/*
| Buying.
|
| WooCommerce was going to own this on a separate WordPress install, which is
| no longer the plan now that there is one application. Until checkout exists,
| every Buy now button lands on a page that says so and offers the way people
| actually buy things here anyway — by sending a message.
|
| A dead link is worse than an honest one: the visitor who clicks Buy now is
| the visitor who had already decided.
|
| One route, not two. Laravel treats /shop and /shop/ as the same path, so
| registering a redirect from the trailing-slash form makes it redirect /shop
| to itself until the browser gives up.
*/
Route::view('/shop', 'pages.shop')->name('shop');

/*
| The console.
|
| One address in three states — set up, sign in, or use it — because on shared
| hosting there is no shell to run migrations in and no second way in. The
| setup screen is unauthenticated and runs migrations, which is safe only
| because it refuses the moment a first account exists.
|
| Rate limited: this is the one form on the site worth guessing at.
*/
Route::prefix('apt-admin')->group(function () {
    Route::get('/', [AdminController::class, 'entry'])->name('admin');

    Route::post('/setup', [AdminController::class, 'install'])->middleware('throttle:10,1');
    Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [AdminController::class, 'logout']);

    // Getting back in without a reset email, which this console must not have:
    // it can revoke every customer's licence, and a reset link is a way in for
    // whoever reaches the mailbox. Proof is a file on the server instead.
    Route::get('/recover', [AdminController::class, 'recover']);
    Route::post('/recover', [AdminController::class, 'resetPassword'])->middleware('throttle:10,1');

    Route::middleware('auth')->group(function () {
        Route::get('/settings', [AdminController::class, 'settings']);
        Route::post('/settings', [AdminController::class, 'saveSettings']);
    });
});
