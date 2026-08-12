<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Builds the upload for shared hosting.
 *
 *   php artisan astralab:package
 *
 * Laravel serves from public/. cPanel serves from public_html. That mismatch is
 * the single most common way a Laravel app fails on the hosting these accounts
 * are bought on, and the usual advice — "move public/ contents up and edit the
 * paths" — is a manual step done under pressure, at midnight, over FTP.
 *
 * So the archive already has the split baked in:
 *
 *   astralab-app/   everything the public must never fetch — .env, the
 *                   database config, the whole application
 *   public_html/    the front controller, .htaccess and assets, with the
 *                   paths in index.php already rewritten to point up and
 *                   across at astralab-app/
 *
 * Extracted in the account's home folder, both land where they belong and
 * nothing has to be edited. The application ends up outside the web root,
 * which is the arrangement that makes .env unreachable by URL no matter what
 * the server does with .htaccess.
 */
class Package extends Command
{
    protected $signature = 'astralab:package {--out= : Where to write the zip}';

    protected $description = 'Build the deployable archive for shared hosting';

    /**
     * Never shipped.
     *
     * .env holds the production secrets and is written on the server, once.
     * Putting it in an archive that travels by email and sits in a Downloads
     * folder is how database passwords leak.
     */
    private const SKIP = [
        '.env',
        '.env.example',
        '.git',
        '.gitignore',
        '.gitattributes',
        'node_modules',
        'tests',
        'storage/logs',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'database/database.sqlite',
        'phpunit.xml',
        'README.md',
        'DEPLOY.md',
    ];

    public function handle(): int
    {
        $root = base_path();
        $out = $this->option('out') ?: $root.DIRECTORY_SEPARATOR.'astralab-site.zip';

        if (! extension_loaded('zip')) {
            $this->error('The zip extension is not loaded, so nothing can be packaged.');

            return self::FAILURE;
        }

        if (! is_dir($root.'/vendor')) {
            $this->error('vendor/ is missing. Run composer install first — shared hosting has no composer.');

            return self::FAILURE;
        }

        @unlink($out);

        $zip = new ZipArchive;
        $zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $app = 0;
        $web = 0;

        foreach ($this->files($root) as $absolute) {
            $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));

            if ($this->skipped($relative)) {
                continue;
            }

            // public/ becomes public_html/; everything else goes below the web
            // root where a URL cannot reach it.
            if (str_starts_with($relative, 'public/')) {
                $zip->addFile($absolute, 'public_html/'.substr($relative, strlen('public/')));
                $web++;

                continue;
            }

            $zip->addFile($absolute, 'astralab-app/'.$relative);
            $app++;
        }

        // The front controller, pointed at its new neighbour rather than its
        // parent. This is the edit everybody has to make by hand and half of
        // them get wrong.
        $zip->addFromString('public_html/index.php', str_replace(
            "__DIR__.'/../",
            "__DIR__.'/../astralab-app/",
            file_get_contents($root.'/public/index.php')
        ));

        // Laravel keeps these directories with .gitignore files, so an archive
        // built from a clean checkout has no storage tree at all — and the
        // first request writes a session, fails, and shows a blank white page.
        foreach ([
            'astralab-app/storage/framework/cache/data',
            'astralab-app/storage/framework/sessions',
            'astralab-app/storage/framework/views',
            'astralab-app/storage/logs',
            'astralab-app/storage/app/private',
            'astralab-app/bootstrap/cache',
        ] as $dir) {
            $zip->addEmptyDir($dir);
            $zip->addFromString($dir.'/.gitignore', "*\n!.gitignore\n");
        }

        $zip->close();

        $this->newLine();
        $this->info(basename($out).' — '.number_format((filesize($out) / 1048576), 1).' MB');
        $this->line("  public_html/    {$web} files (plus a rewritten index.php)");
        $this->line("  astralab-app/   {$app} files");
        $this->newLine();
        $this->line('Upload to the account home folder — the one that contains public_html — and Extract.');
        $this->line('Then create .env on the server. See DEPLOY.md.');

        return self::SUCCESS;
    }

    /** @return iterable<string> */
    private function files(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                yield $item->getPathname();
            }
        }
    }

    private function skipped(string $relative): bool
    {
        if (str_ends_with($relative, '.zip')) {
            return true;
        }

        foreach (self::SKIP as $skip) {
            if ($relative === $skip || str_starts_with($relative, $skip.'/')) {
                return true;
            }
        }

        return false;
    }
}
