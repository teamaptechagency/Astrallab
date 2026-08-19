<?php

/**
 * The front door on a read-only host.
 *
 * Vercel serves every request through one PHP file, and the filesystem it
 * serves it from cannot be written to — except /tmp, which lasts for the
 * length of one invocation and no longer.
 *
 * So this does the two things Laravel needs before it will boot in that
 * situation, and then hands over to the ordinary front controller. Nothing
 * about the application changes; the shared hosting install still uses
 * public/index.php exactly as before, and never runs this file.
 *
 * See DEPLOY-VERCEL.md for what does and does not work up there.
 */

// Blade compiles a template the first time it is rendered and writes the
// result to disk. Nothing here is precompiled, so on a read-only filesystem
// the first render of any page would be a 500 without somewhere to put it.
// /tmp survives one invocation, which means each cold start pays to compile
// the views it uses — cheap, and the only option that does not need a disk.
$compiled = '/tmp/views';

if (! is_dir($compiled)) {
    @mkdir($compiled, 0755, true);
}

$_ENV['VIEW_COMPILED_PATH'] = $compiled;
putenv('VIEW_COMPILED_PATH='.$compiled);

// Laravel wants somewhere to put its own scratch files. LARAVEL_STORAGE_PATH
// is the framework's own hook for exactly this, read straight from $_ENV, so
// nothing in the application has to know about it.
//
// None of what lands there survives the request, which is fine: sessions,
// cache and the queue are all in the database on this host, so nothing here is
// expected to outlive anything.
$_ENV['LARAVEL_STORAGE_PATH'] = '/tmp';

// The path the application thinks it was reached at. Without this the router
// sees /api/index.php and every generated URL carries it around.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';

require __DIR__.'/../public/index.php';
