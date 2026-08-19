<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Throwable;

/**
 * First run, for somebody with a browser and nothing else.
 *
 * The database details used to be typed into .env through a hosting file
 * manager: a hidden file, no validation, and a syntax mistake shows up as a
 * blank white page with no explanation. This asks for them on a screen, tries
 * them before believing them, and says what is wrong in words.
 *
 * Whether an installation is finished is a file on disk, not a row in the
 * database. Before the database exists there is nothing to ask, and the guard
 * that sends a fresh upload to the wizard runs on every request without needing
 * a connection.
 */
class Installer
{
    /** Matches composer.json. Shared hosts often default an account to an older one. */
    public const PHP = '8.3.0';

    /** @var array<string, string|null> */
    public const EXTENSIONS = [
        'pdo_mysql' => 'Talking to your database',
        'mbstring' => 'Bengali text',
        'openssl' => 'Secure connections',
        'curl' => 'Reaching payment gateways and customer shops',
        'fileinfo' => 'Knowing an uploaded file is really an image',
        'zip' => 'Building and serving release packages',
        'ctype' => null,
        'json' => null,
        'tokenizer' => null,
        'xml' => null,
    ];

    public const WRITABLE = [
        'storage/app',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'bootstrap/cache',
    ];

    public static function lockPath(): string
    {
        return config('astralab.install_lock') ?: storage_path('app/installed.json');
    }

    public static function isInstalled(): bool
    {
        // Said outright, for deployments with no writable disk. Nothing there
        // can write the lock file, so without this every visitor is sent to an
        // installer that cannot finish and the shop is one long redirect loop.
        // See config/astralab.php and DEPLOY-VERCEL.md.
        if (config("astralab.installed")) {
            return true;
        }

        return is_file(self::lockPath());
    }

    /**
     * Everything the host has to provide, each as a line somebody can act on.
     *
     * @return array<int, array{name: string, ok: bool, detail: string, fatal: bool}>
     */
    public static function requirements(): array
    {
        $checks = [[
            'name' => 'PHP '.self::PHP.' or newer',
            'ok' => version_compare(PHP_VERSION, self::PHP, '>='),
            'detail' => 'You have PHP '.PHP_VERSION.'. In hPanel this is under Advanced → PHP Configuration.',
            'fatal' => true,
        ]];

        foreach (self::EXTENSIONS as $extension => $what) {
            $checks[] = [
                'name' => 'PHP extension: '.$extension,
                'ok' => extension_loaded($extension),
                'detail' => ($what ? $what.'. ' : '').'Tick it in hPanel under PHP Configuration → Extensions.',
                'fatal' => true,
            ];
        }

        foreach (self::WRITABLE as $path) {
            $full = base_path($path);

            $checks[] = [
                'name' => 'Writable: '.$path,
                'ok' => is_dir($full) && is_writable($full),
                'detail' => 'Set this folder to 755 in the file manager, applied to subfolders.',
                'fatal' => true,
            ];
        }

        $env = base_path('.env');

        $checks[] = [
            'name' => 'Settings file (.env) can be written',
            'ok' => is_file($env) ? is_writable($env) : is_writable(base_path()),
            'detail' => 'The installer writes your database details into it.',
            'fatal' => true,
        ];

        return $checks;
    }

    /** @return array<int, array<string, mixed>> */
    public static function failed(array $checks): array
    {
        return array_values(array_filter($checks, fn ($c) => ! $c['ok'] && $c['fatal']));
    }

    /**
     * Whether the settings file is being served to the public.
     *
     * Worth asking over HTTP rather than on disk, because the answer depends on
     * the server rather than the files. If it can be downloaded, the database
     * password is public and the install must not continue.
     *
     * A connection failure is unknown, not a pass, and is reported as such.
     *
     * @return array{known: bool, exposed: bool}
     */
    public static function envExposure(string $baseUrl): array
    {
        $context = stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true, 'follow_location' => 0],
            // A staging domain with a half-finished certificate is not what this
            // check is about, and refusing to look would leave no answer at all.
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $body = @file_get_contents(rtrim($baseUrl, '/').'/.env', false, $context);

        if ($body === false) {
            return ['known' => false, 'exposed' => false];
        }

        $status = 0;

        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
        }

        return ['known' => true, 'exposed' => $status === 200 && str_contains($body, 'APP_KEY')];
    }

    /**
     * Try the details before believing them.
     *
     * PDO directly rather than through Laravel's connection manager: this
     * process was configured from a .env that has not been written yet, and a
     * wrong password should come back as a sentence on this page rather than an
     * exception on the next one.
     *
     * @return array{ok: bool, message: string}
     */
    public static function testDatabase(array $c): array
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $c['host'], $c['port'], $c['database']);

        try {
            $pdo = new PDO($dsn, $c['username'], (string) ($c['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => self::explain($e)];
        }

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        // Somebody else's data in here would either break the migrations
        // halfway or quietly join this install to it. Both are worse than
        // stopping. Our own tables are fine — that is a reinstall.
        $foreign = array_values(array_diff($tables, [
            'settings', 'users', 'password_reset_tokens', 'sessions',
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
            'migrations',
        ]));

        if ($foreign !== []) {
            return [
                'ok' => false,
                'message' => 'That database already has other things in it ('
                    .implode(', ', array_slice($foreign, 0, 3))
                    .(count($foreign) > 3 ? ', …' : '')
                    .'). Create an empty database for this, or empty that one first.',
            ];
        }

        return ['ok' => true, 'message' => 'Connected.'];
    }

    /**
     * A database error in words rather than a driver code.
     *
     * These are the whole of what goes wrong in practice, and the raw message —
     * "SQLSTATE[HY000] [1045] Access denied for user" — sends people to us
     * instead of to their hosting panel.
     */
    private static function explain(PDOException $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, '1045') => 'The username or password was not accepted. In hPanel, check the user is attached to this database under MySQL Databases.',
            str_contains($message, '1049') => 'There is no database with that name. Create it in hPanel under MySQL Databases, and copy the full name — it usually starts with your account number.',
            str_contains($message, '2002') || str_contains($message, '2005') => 'Could not reach the database server. On Hostinger the host is localhost.',
            default => 'The database refused the connection: '.$message,
        };
    }

    /**
     * Write settings into .env, leaving everything else in it alone.
     *
     * Rewrites lines rather than regenerating the file, because a host or a
     * person may have added things to it and an installer that threw those away
     * would be a bug reported as "my email stopped working".
     *
     * @param  array<string, string>  $values
     */
    public static function writeEnv(array $values): void
    {
        $path = base_path('.env');
        $contents = is_file($path) ? file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);

            $contents = preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)
                ? preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents)
                : rtrim($contents, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $contents);
    }

    /** A value with a space or a hash in it truncates unless it is quoted. */
    private static function quote(string $value): string
    {
        return preg_match('/\s|#|"|\'/', $value)
            ? '"'.str_replace(['\\', '"'], ['\\\\', '\"'], $value).'"'
            : $value;
    }

    /**
     * Build the tables against the connection the wizard just tested.
     *
     * Configured at runtime, because the settings for this process were read
     * when it started and the .env written a moment ago is for the next one.
     *
     * @return array{ok: bool, message: string}
     */
    public static function migrate(array $database): array
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $database['host'],
            'database.connections.mysql.port' => $database['port'],
            'database.connections.mysql.database' => $database['database'],
            'database.connections.mysql.username' => $database['username'],
            'database.connections.mysql.password' => $database['password'] ?? '',
        ]);

        DB::purge('mysql');

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'The tables could not be built: '.$e->getMessage()];
        }

        return ['ok' => true, 'message' => Artisan::output()];
    }

    /**
     * Mark it done.
     *
     * Written last, after there is an account to sign in with. An installation
     * with a database but nobody who can open it is not finished, and the next
     * visitor should be sent back to the wizard rather than to a sign-in form
     * nobody holds the password for.
     */
    public static function lock(array $details = []): void
    {
        $path = self::lockPath();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($details + [
            'installed_at' => now()->toIso8601String(),
            'php' => PHP_VERSION,
        ], JSON_PRETTY_PRINT));
    }

    public static function unlock(): void
    {
        if (is_file(self::lockPath())) {
            unlink(self::lockPath());
        }
    }
}
