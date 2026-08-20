<?php

/**
 * The front door on a read-only host.
 *
 * Vercel serves every request through one PHP file, and the filesystem it
 * serves it from cannot be written to — except /tmp, which lasts for the
 * length of one invocation and no longer.
 *
 * So this does what Laravel needs before it will boot in that situation, says
 * plainly when a setting is missing, and then hands over to the ordinary front
 * controller. Nothing about the application changes: the shared hosting
 * install still uses public/index.php exactly as before and never runs this.
 *
 * See DEPLOY-VERCEL.md.
 */

// ---------------------------------------------------------------------------
// Somewhere to write
// ---------------------------------------------------------------------------
//
// LARAVEL_STORAGE_PATH is the framework's own hook, read straight from $_ENV,
// so nothing in the application has to know about any of this. None of what
// lands there survives the request, which is fine: sessions, cache and the
// queue all live in the database on this host.
//
// The directories are made here rather than left to whatever needs them first.
// Blade compiles a template the first time it renders one, and a missing
// directory at that moment is a 500 on a page that would otherwise have worked.

$storage = '/tmp/storage';

foreach ([
    $storage,
    $storage.'/logs',
    $storage.'/framework',
    $storage.'/framework/cache',
    $storage.'/framework/cache/data',
    $storage.'/framework/sessions',
    $storage.'/framework/views',
    $storage.'/app',
] as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }
}

$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;

$_ENV['VIEW_COMPILED_PATH'] = $storage.'/framework/views';
putenv('VIEW_COMPILED_PATH='.$storage.'/framework/views');

// There is no log file to keep on a host that forgets its disk between
// requests, and a log channel writing to one is a 500 waiting to happen.
// Defaulted rather than forced, so setting it explicitly still wins.
if (! getenv('LOG_CHANNEL') && ! isset($_ENV['LOG_CHANNEL'])) {
    $_ENV['LOG_CHANNEL'] = 'stderr';
    putenv('LOG_CHANNEL=stderr');
}

// ---------------------------------------------------------------------------
// Say what is missing, rather than failing blankly
// ---------------------------------------------------------------------------
//
// Without these the application cannot start, and what a visitor gets is an
// unexplained 500 while the reason sits in a log they would have to go and
// find. Names only — never a value, never a stack trace, and only when the
// site is already unable to serve anything.

$missing = [];

foreach ([
    'APP_KEY' => 'Run "php artisan key:generate --show" on your own machine and paste the whole base64: line.',
    'ASTRALAB_INSTALLED' => 'Set it to true. This host cannot run the setup wizard, so the settings arrive here instead.',
    'DB_CONNECTION' => 'mysql or pgsql, pointing at a database this deployment can reach.',
    'DB_HOST' => 'The database host.',
    'DB_DATABASE' => 'The database name.',
    'DB_USERNAME' => 'The database user.',
] as $name => $why) {
    $value = getenv($name) ?: ($_ENV[$name] ?? '');

    if ($value === '' || $value === false) {
        $missing[$name] = $why;
    }
}

if ($missing !== []) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');

    echo '<!doctype html><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Not configured yet</title>';
    echo '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:38rem;margin:12vh auto;padding:0 1.5rem;'
        .'color:#111;background:#fff}code{background:#f4f4f5;padding:.1em .35em;border-radius:.25rem}'
        .'li{margin:.75rem 0}@media(prefers-color-scheme:dark){body{background:#111;color:#eee}'
        .'code{background:#27272a}}</style>';
    echo '<h1>This deployment is not configured yet</h1>';
    echo '<p>The application cannot start until these are set in the Vercel project, '
        .'under Settings &rarr; Environment Variables. Add them, then redeploy.</p><ul>';

    foreach ($missing as $name => $why) {
        echo '<li><code>'.htmlspecialchars($name, ENT_QUOTES).'</code> — '
            .htmlspecialchars($why, ENT_QUOTES).'</li>';
    }

    echo '</ul><p>The full list, and what each one is for, is in '
        .'<code>site/DEPLOY-VERCEL.md</code>.</p>';

    exit;
}

// ---------------------------------------------------------------------------
// Over to Laravel
// ---------------------------------------------------------------------------
//
// SCRIPT_NAME so the router sees the address the visitor actually used —
// without it every generated URL carries /api/index.php around.

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';

require __DIR__.'/../public/index.php';
