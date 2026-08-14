<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UploadController;
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
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/shop/{slug}', [ShopController::class, 'product'])->name('product');
Route::post('/shop/{slug}', [ShopController::class, 'place'])->middleware('throttle:10,1')->name('order.place');
Route::post('/shop/{slug}/review', [ShopController::class, 'review'])->middleware('throttle:5,10')->name('review');

// Addressed by the hub's reference, which is random rather than sequential: an
// order number that can be counted up lets anybody read the next customer's.
Route::get('/order/{reference}', [ShopController::class, 'order'])->name('order');

/*
|--------------------------------------------------------------------------
| The console
|--------------------------------------------------------------------------
|
| One address in three states — set up, sign in, or use it — because on shared
| hosting there is no shell to run migrations in and no second way in. The
| setup screen is unauthenticated and runs migrations, which is safe only
| because it refuses the moment a first account exists.
|
| Which hostname it answers on is a setting, not a code change. Left blank, the
| console is reachable wherever the app is — which is what you want before the
| subdomain's DNS exists, because restricting it first would lock everybody out
| of a console on a hostname that does not resolve yet.
|
| Set ASTRALAB_MANAGE_HOST once manage.astrallabs.uk points here, and the public
| domain stops serving the console entirely. The same app, one address for
| buyers and one for the back office.
|
| Rate limited: this is the one form on the site worth guessing at.
*/
Route::domain(config('astralab.manage_host') ?: null)->prefix('apt-admin')->group(function () {
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
        // Running the migrations that came with the last upload. Behind the
        // sign-in, unlike the installer: by this point there is somebody to
        // sign in as, so there is no reason for it to be open.
        Route::get('/updates', [AdminController::class, 'updates'])->name('admin.updates');
        Route::post('/updates', [AdminController::class, 'applyUpdates']);

        // A build arrives in pieces: PHP will not take it in one request.
        Route::post('/uploads/begin', [UploadController::class, 'begin'])->name('upload.begin');
        Route::post('/uploads/chunk', [UploadController::class, 'chunk'])->name('upload.chunk');
        Route::post('/uploads/finish', [UploadController::class, 'finish'])->name('upload.finish');

        Route::get('/settings', [AdminController::class, 'settings']);
        Route::post('/settings', [AdminController::class, 'saveSettings']);

        // Asked of the hub itself. "Saved" only means the values were written
        // down; this is the only thing that says whether they are right.
        Route::post('/settings/test', [AdminController::class, 'testHub'])->middleware('throttle:10,1');
    });
});
