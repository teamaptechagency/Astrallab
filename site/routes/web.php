<?php

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

Route::get('/apt-admin', fn () => 'Operator console — not built yet.')->name('admin');
