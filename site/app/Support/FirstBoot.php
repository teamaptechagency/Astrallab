<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Making the first request work on a host nobody can log into.
 *
 * Laravel needs an APP_KEY before it can encrypt a session cookie. Without one
 * every page in the web middleware group returns a bare 500 — while /up, which
 * sits outside that group, cheerfully returns 200. That combination is
 * genuinely baffling from the outside: the framework is clearly running, the
 * assets serve, and every page is broken.
 *
 * On hosting with a shell that is a one-line fix. On the shared hosting these
 * accounts are bought on, it means hand-typing a base64 key into a file through
 * a web file manager, and getting it wrong in any of the usual ways — pasting
 * without the base64: prefix, or File Manager helpfully saving ".env" as
 * ".env.txt", which is not a file Laravel will ever read.
 *
 * So this generates one and writes it. A random key is safe to generate: the
 * only thing lost is that sessions issued before it existed stop validating,
 * and on a first boot there are none.
 *
 * It also writes a whole .env when there is none, because a missing settings
 * file produces exactly the same symptom and the same confusion.
 */
class FirstBoot
{
    /**
     * Called from AppServiceProvider::boot(), which is early enough: the
     * encrypter is resolved lazily, when the cookie middleware first runs.
     */
    public static function ensureAppKey(): void
    {
        if (! empty(config('app.key'))) {
            return;
        }

        $path = base_path('.env');
        $key = 'base64:'.base64_encode(random_bytes(32));

        // This request has already read its configuration, so the new key has
        // to be put where the encrypter will look — not only on disk, or the
        // page that triggered this would still be the 500 nobody can explain.
        config(['app.key' => $key]);

        if (! is_file($path)) {
            self::writeDefaults($path, $key);

            // With no .env, Laravel's own defaults apply — and the default
            // session driver is the database, of which there is none. That
            // makes the very first request a 500 even though the file has
            // just been written correctly for every request after it. The
            // person watching sees the error, not the recovery.
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);

            return;
        }

        if (! is_writable($path)) {
            // Nothing more to do: the key above keeps this process working,
            // and the next request generates another. Sessions will not
            // survive between requests, which is visibly wrong in a way that
            // sends somebody to the logs — better than silence.
            return;
        }

        $contents = file_get_contents($path);

        $contents = preg_match('/^APP_KEY=.*$/m', $contents)
            ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $contents)
            : rtrim($contents, "\n")."\nAPP_KEY=".$key."\n";

        file_put_contents($path, $contents);
    }

    /**
     * A settings file for a host that has none.
     *
     * Deliberately conservative: production, debug off, files for sessions and
     * cache, and no database — the pages that are live today need none, and a
     * half-filled database block is worse than an empty one because it fails
     * later and further from the cause.
     */
    private static function writeDefaults(string $path, string $key): void
    {
        if (! is_writable(dirname($path))) {
            return;
        }

        file_put_contents($path, <<<ENV
        APP_NAME="Astra Lab"
        APP_ENV=production
        APP_KEY={$key}
        APP_DEBUG=false
        APP_URL=https://astralab.com

        LOG_CHANNEL=stack
        LOG_LEVEL=error

        # Files rather than the database. There is nothing in a database this
        # site needs yet, and on shared hosting a file beats a query anyway.
        SESSION_DRIVER=file
        CACHE_STORE=file
        QUEUE_CONNECTION=sync

        DB_CONNECTION=mysql
        DB_HOST=localhost
        DB_PORT=3306
        DB_DATABASE=
        DB_USERNAME=
        DB_PASSWORD=

        # How people reach you. Anything left blank is left off the contact
        # page rather than shown empty — a page listing a blank phone number is
        # worse than one listing two ways to reach somebody.
        # WhatsApp is digits only, country code first, no plus: 8801XXXXXXXXX
        ASTRALAB_EMAIL=
        ASTRALAB_PHONE=
        ASTRALAB_WHATSAPP=
        ASTRALAB_ADDRESS=
        ASTRALAB_HOURS="Saturday to Thursday, 10am – 7pm"

        # Named on the terms, privacy and refund pages.
        ASTRALAB_COMPANY="Astra Lab"
        ASTRALAB_PARTNER="AP Tech Agency"
        ASTRALAB_TRADE_LICENCE=
        ASTRALAB_REFUND_DAYS=7

        ENV);
    }

    /**
     * The directories the framework writes to, created if the upload lost them.
     *
     * A zip built from a clean checkout carries no storage tree — git keeps
     * those folders with .gitignore files and some extractors drop empty
     * directories entirely. The first request then fails to write a session,
     * and does so before the logger has anywhere to record why.
     */
    public static function ensureWritablePaths(): void
    {
        foreach ([
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }
}
