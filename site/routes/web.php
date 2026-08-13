<?php

use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

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

Route::get('/apt-admin', fn () => 'Operator console — not built yet.')->name('admin');
