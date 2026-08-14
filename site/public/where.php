<?php

/*
 * Which copy of the application is actually running?
 *
 * A temporary diagnostic, not part of the site. Upload it beside index.php,
 * open it in a browser, read the answer, delete it.
 *
 * It exists because an archive extracted in the wrong place does not fail — it
 * leaves two copies of the application, and the front controller quietly
 * prefers one of them. The site then keeps working, on the older code, and
 * every symptom points somewhere else: a screen that is missing, a route that
 * answers 405, a fix that appears not to have been applied.
 *
 * This prints paths and version numbers only. No settings, no keys.
 */

$candidates = [
    'two levels up (account root)' => __DIR__.'/../../astralab-app',
    'one level up (beside public_html)' => __DIR__.'/../astralab-app',
    'directly underneath' => __DIR__.'/astralab-app',
];

function versionOf(string $app): string
{
    $config = $app.'/config/astralab.php';

    if (! is_file($config)) {
        return 'no config';
    }

    // Read rather than included: including someone else's config file to find
    // out what it says is how a diagnostic becomes the thing that breaks.
    $text = (string) file_get_contents($config);

    return preg_match("/'version'\s*=>\s*env\('ASTRALAB_VERSION',\s*'([^']+)'/", $text, $m)
        ? $m[1]
        : 'no version (older build)';
}

header('Content-Type: text/plain; charset=utf-8');

echo "This file is at:\n  ".__DIR__."\n\n";
echo "Looking for the application, nearest the account root first:\n\n";

$chosen = null;

foreach ($candidates as $label => $path) {
    $real = realpath($path);
    $usable = $real && is_file($real.'/vendor/autoload.php');

    printf("  %-36s %s\n", $label, $real ?: '(not there)');

    if ($real) {
        printf("  %-36s %s, version %s%s\n", '', $usable ? 'usable' : 'NOT usable (no vendor/)',
            versionOf($real), $usable && ! $chosen ? '   <-- THIS ONE IS RUNNING' : '');
    }

    if ($usable && ! $chosen) {
        $chosen = $real;
    }

    echo "\n";
}

if (! $chosen) {
    echo "None of them is usable. That is why the site is not working.\n";
    exit;
}

echo "So: put the new build where that arrow is, not beside it.\n";
echo "If more than one is listed above, delete the ones you are not using —\n";
echo "two copies is how an update appears to do nothing.\n\n";
echo "Delete this file when you are done.\n";
