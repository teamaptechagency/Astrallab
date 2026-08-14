<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use ZipArchive;

/**
 * Replacing this console's own code from inside itself.
 *
 * The alternative is File Manager: upload, right-click, Extract, overwriting.
 * That works and stays documented, but it is four steps in somebody else's
 * interface, and the step people miss is the last one — extracting into
 * public_html instead of the folder above it, which puts .env, and the signing
 * key in it, one URL away from anybody who guesses.
 *
 * So the same archive can be handed to this instead.
 *
 * What it deliberately does NOT do is touch the database. Replacing files
 * cannot lose data; migrations can. They stay two separate, deliberate acts —
 * the console asks for the second one on the screen straight afterwards.
 */
class SelfUpdate
{
    /**
     * Never written, whatever the archive says.
     *
     * The packager already excludes all three, so this is the second lock on
     * the same door: .env holds the signing key that every CMS ever sold
     * verifies against, storage/ holds the sessions of everybody currently
     * signed in. There is nothing else under those two that a build should replace.
     * A malformed or hand-edited zip must not be able to reach any of them.
     */
    private const PROTECTED = ['.env', 'storage/'];

    /** Proof this is the right archive, checked before anything is written. */
    private const REQUIRED = ['public_html/index.php'];

    /**
     * The real web root.
     *
     * Not public_path(): the deployed front controller lives in public_html/
     * beside the application rather than inside it, so Laravel's idea of the
     * public path points at a directory the web server has never heard of. The
     * running script's own directory is the one address that cannot be wrong.
     */
    public static function webRoot(): string
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $directory = $script ? realpath(dirname($script)) : false;

        if ($directory && is_file($directory.'/index.php')) {
            return $directory;
        }

        return public_path();
    }

    /**
     * What PHP will accept, in bytes.
     *
     * Shown on the screen rather than discovered by failing. An upload over
     * post_max_size does not arrive truncated — it does not arrive at all, and
     * PHP hands the application an empty request with no error in it, which
     * looks exactly like a form that does nothing when pressed.
     *
     * @return array{upload: int, post: int, effective: int}
     */
    public static function limits(): array
    {
        $upload = self::bytes((string) ini_get('upload_max_filesize'));
        $post = self::bytes((string) ini_get('post_max_size'));

        return ['upload' => $upload, 'post' => $post, 'effective' => min($upload, $post)];
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * What is actually in this file, in one phrase.
     *
     * The top-level names and the size are enough to tell the three cases
     * apart at a glance: the wrong build names itself, a truncated upload has
     * a size that is obviously wrong, and something that is not an archive at
     * all has nothing in it.
     */
    private static function summarise(ZipArchive $zip, string $path): string
    {
        $tops = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $tops[explode('/', (string) $zip->getNameIndex($i))[0]] = true;
        }

        return ($tops === [] ? 'nothing at all' : implode(', ', array_slice(array_keys($tops), 0, 4)))
            .' — '.number_format(filesize($path) / 1048576, 1).' MB, '.$zip->numFiles.' files.';
    }

    /**
     * What an archive says it is: version, kind, and the lock it was built on.
     *
     * @return array{version: string, kind: string, lock: ?string}
     */
    public static function describe(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return ['version' => 'unknown', 'kind' => 'unknown', 'lock' => null];
        }

        $build = json_decode((string) $zip->getFromName('astralab-app/astralab-build.json'), true);
        $zip->close();

        return [
            // Older archives were built before this existed and say nothing.
            // Named as such rather than guessed at.
            'version' => is_array($build) ? ($build['version'] ?? 'unversioned') : 'unversioned',
            'kind' => is_array($build) ? ($build['kind'] ?? 'full') : 'full',
            'lock' => is_array($build) ? ($build['lock'] ?? null) : null,
        ];
    }

    /**
     * Is this the archive it claims to be?
     *
     * Every check happens before a single file is written. Once extraction has
     * started there is no way back on hosting with no shell — a half-replaced
     * application is a white page and a support call — so the only safe place
     * to refuse is here.
     *
     * @return array{ok: bool, message: string, files: int}
     */
    public static function inspect(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return ['ok' => false, 'message' => 'That file is not a zip archive, or it did not finish uploading.', 'files' => 0];
        }

        /*
         * What arrived, described before anything is judged.
         *
         * Every refusal says this, not only some of them. "That is the wrong
         * file" and "that is the right file, damaged on the way here" read
         * identically otherwise, and they need opposite actions — pick a
         * different file, or upload the same one again.
         */
        $summary = self::summarise($zip, $path);

        foreach (self::REQUIRED as $required) {
            if ($zip->locateName($required) === false) {
                $zip->close();

                return [
                    'ok' => false,
                    'files' => 0,
                    'message' => 'This is not a build for this site — '.$required.' is missing from it. '
                        .'It contains '.$summary.' '
                        .'The hub build (astralab-manage) belongs on manage.astrallabs.uk, not here.',
                ];
            }
        }

        $files = $zip->numFiles;

        /*
         * Is any of this ours?
         *
         * The CMS release archive has the same public_html/ at its top and a
         * differently named application folder, so it passes the check above.
         * It is the archive most likely to be picked by mistake — the two sit
         * beside each other in a Downloads folder — and the wrong one here
         * would overwrite the console with a shop. Named rather than refused
         * generically, because the message has to say where it does belong.
         */
        $ours = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with((string) $zip->getNameIndex($i), 'astralab-app/')) {
                $ours = true;
                break;
            }
        }

        if (! $ours) {
            $zip->close();

            return [
                'ok' => false,
                'files' => 0,
                'message' => 'This is not a build for this site. It contains '.$summary.' '
                    .'A full build has astralab-app/ and public_html/ and is about 8 MB. '
                    .'The hub build (astralab-manage) belongs on manage.astrallabs.uk, not here.',
            ];
        }

        // Two shapes are allowed. A full build carries vendor/; an update build
        // does not, and runs against the dependencies already here.
        $full = $zip->locateName('astralab-app/vendor/autoload.php') !== false;
        $build = json_decode((string) $zip->getFromName('astralab-app/astralab-build.json'), true);

        $zip->close();

        if (! $full) {
            if (! is_array($build) || empty($build['lock'])) {
                return [
                    'ok' => false,
                    'files' => 0,
                    'message' => 'This archive has no vendor/ and does not say what it was built against, '
                        .'so it cannot be checked. Use the full build.',
                ];
            }

            // Said before the lock comparison, because a console old enough to
            // be missing this check is a console where the answer is "install
            // the full build once" rather than anything about dependencies.
            if (! is_file(base_path('composer.lock'))) {
                return [
                    'ok' => false,
                    'files' => 0,
                    'message' => 'This install has no composer.lock to compare against, so a small update '
                        .'cannot be verified. Install the full build once; after that, updates work.',
                ];
            }

            /*
             * The whole safety of an update archive.
             *
             * Without vendor/, the code that lands has to run against what is
             * already on this server. If composer.json moved since this was
             * built, it will not — and the failure is a fatal error on a class
             * that is not there, which reads as the update having corrupted
             * something rather than as the wrong archive.
             */
            $here = is_file(base_path('composer.lock'))
                ? hash_file('sha256', base_path('composer.lock'))
                : null;

            if ($here !== $build['lock']) {
                return [
                    'ok' => false,
                    'files' => 0,
                    'message' => 'This update was built against different dependencies from the ones installed '
                        .'here, so it would land on libraries it does not have. Use the full build for this one.',
                ];
            }
        }

        return ['ok' => true, 'message' => '', 'files' => $files];
    }

    /**
     * Put the new files in place.
     *
     * The order matters. Maintenance mode goes on using the code that is
     * currently loaded and running; extraction replaces the files underneath
     * it; and everything afterwards is done with plain filesystem calls rather
     * than Artisan, because by that point the classes on disk are the new ones
     * and loading a new class into a half-old request is how this ends as a
     * fatal error with the site still closed.
     *
     * @return array{ok: bool, message: string, written: int}
     */
    public static function install(UploadedFile $file): array
    {
        return self::installFrom($file->getRealPath());
    }

    /**
     * The same, from a file already on disk.
     *
     * Which is how a build over PHP's upload limit arrives: in pieces, put back
     * together by ChunkedUpload, and never an UploadedFile at all.
     *
     * @return array{ok: bool, message: string, written: int}
     */
    public static function installFrom(string $path): array
    {
        $inspection = self::inspect($path);

        if (! $inspection['ok']) {
            return ['ok' => false, 'message' => $inspection['message'], 'written' => 0];
        }

        $root = base_path();
        $web = self::webRoot();

        foreach ([$root => 'the application folder', $web => 'the public folder'] as $directory => $described) {
            if (! is_writable($directory)) {
                return [
                    'ok' => false,
                    'written' => 0,
                    'message' => 'PHP cannot write to '.$described.' ('.$directory.'). '
                        .'Hosting sets this; upload through File Manager instead.',
                ];
            }
        }

        // Closed while the files are being swapped. Installs calling in get a
        // 503 and try later, which is the honest answer for the few seconds
        // when half the application is one build and half is another.
        /*
         * Thousands of files, written one at a time, on shared hosting.
         *
         * That is what outran PHP's execution limit: the request died in the
         * middle, and everything after it — including the line that opens the
         * site again — never ran. The site stayed closed and nothing on the
         * server was going to change that.
         */
        @set_time_limit(0);
        @ignore_user_abort(true);

        /*
         * Closing the site is a risk, so it is only taken when it buys
         * something.
         *
         * A full build replaces thousands of files and takes real seconds. An
         * update build replaces about a hundred, in well under a second — and
         * for that, going down trades a millisecond of inconsistency for the
         * chance of a shop that never comes back up. That trade was wrong.
         */
        $swapIsLong = $inspection['files'] > 1000;

        try {
            // Rendered, so the few seconds of downtime look like an
            // explanation rather than a bare 503 on a white page.
            if ($swapIsLong) {
                Artisan::call('down', ['--retry' => 30, '--render' => 'errors.503']);
            }
        } catch (Throwable) {
            // Not worth refusing the update over. Being unable to close is a
            // smaller problem than being unable to update at all.
        }

        try {
            $written = self::extractInto($path, $root, $web);
        } catch (Throwable $e) {
            self::reopen();

            return ['ok' => false, 'message' => 'The archive stopped part way through: '.$e->getMessage(), 'written' => 0];
        }

        // Compiled config, routes and views describe the build that has just
        // been replaced. Deleted by hand rather than by artisan optimize:clear,
        // which would load the new code into this old request.
        self::clearCompiled($root);

        self::reopen();

        return ['ok' => true, 'message' => '', 'written' => $written];
    }

    /**
     * The file-writing itself, with both roots passed in.
     *
     * Public so it can be pointed at a pair of throwaway directories and made
     * to prove what it will and will not write. A test of this that ran against
     * the real roots would replace the application it is testing.
     *
     * @throws \RuntimeException
     */
    public static function extractInto(string $archive, string $root, string $web): int
    {
        $zip = new ZipArchive;
        $zip->open($archive);

        $written = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (str_starts_with($name, 'astralab-app/')) {
                $relative = substr($name, strlen('astralab-app/'));
                $destination = $root.'/'.$relative;
            } elseif (str_starts_with($name, 'public_html/')) {
                $relative = substr($name, strlen('public_html/'));
                $destination = $web.'/'.$relative;
            } else {
                // Anything not under one of the two known roots is not part of
                // this layout and is left alone.
                continue;
            }

            if (self::isProtected($relative) || str_ends_with($name, '/')) {
                continue;
            }

            $directory = dirname($destination);

            if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new \RuntimeException('could not create '.$directory);
            }

            $contents = $zip->getFromIndex($i);

            if ($contents === false || file_put_contents($destination, $contents) === false) {
                throw new \RuntimeException('could not write '.$relative);
            }

            $written++;
        }

        $zip->close();

        return $written;
    }

    private static function isProtected(string $relative): bool
    {
        foreach (self::PROTECTED as $protected) {
            if ($relative === rtrim($protected, '/') || str_starts_with($relative, $protected)) {
                return true;
            }
        }

        return false;
    }

    private static function clearCompiled(string $root): void
    {
        $stale = array_merge(
            glob($root.'/bootstrap/cache/*.php') ?: [],
            glob($root.'/storage/framework/views/*.php') ?: [],
        );

        foreach ($stale as $file) {
            @unlink($file);
        }
    }

    /**
     * Open again, without asking Artisan.
     *
     * The whole reason this is a file rather than a command is that it has to
     * work when the code around it has just been replaced. Laravel decides it
     * is closed by the presence of one file; removing it is the same decision
     * with none of the risk.
     */
    private static function reopen(): void
    {
        foreach (self::maintenanceFiles() as $file) {
            @unlink($file);
        }
    }

    /**
     * Both of them, because there are two and only one of them decides.
     *
     * `down` is the flag Laravel actually reads. `maintenance.php` is the
     * pre-rendered page the front controller serves when it exists. Removing
     * only the second one takes the nice page away and leaves the site shut —
     * which is exactly what happened: every update ended with the console
     * closed and no way back except a file manager.
     *
     * @return array<int, string>
     */
    private static function maintenanceFiles(): array
    {
        return [
            storage_path('framework/down'),
            storage_path('framework/maintenance.php'),
        ];
    }

    /** Whether the console is closed — including left closed by a failed run. */
    public static function isClosed(): bool
    {
        foreach (self::maintenanceFiles() as $file) {
            if (is_file($file)) {
                return true;
            }
        }

        return false;
    }
}
