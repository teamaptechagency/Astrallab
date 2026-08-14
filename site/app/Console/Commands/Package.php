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
        // The front end has no build step — no Blade file references @vite, and
        // the stylesheets are plain CSS. These are Laravel's defaults, and
        // shipping them says a toolchain is involved on hosting where none
        // exists and none is needed.
        'package.json',
        'package-lock.json',
        'vite.config.js',
        'tests',
        'storage/logs',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        // The marker that says "this site is already set up". Shipping it would
        // mean every fresh upload skips the installer and comes up pointed at
        // the database on the machine that built the archive — which is to say,
        // no database at all.
        'storage/app/installed.json',
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

        $zip->addFromString('public_html/index.php', $this->frontController($root));

        /*
         * A second front door, at the top of the archive.
         *
         * This assumes the domain points at the public_html that comes out of
         * the zip, with the application beside it. Extracted one level in — and
         * that is where a file manager puts it if you are already standing in
         * public_html — the domain's folder has no index.php, so every page
         * answers 403 Forbidden. Apache is not refusing the application; it has
         * nothing to serve and will not list a directory.
         *
         * The fix was moving five files up a level by hand, with hidden files
         * switched on so the .htaccess came too. That is a ritual, not a fix,
         * and the failure it prevents looks exactly like broken software.
         *
         * So the zip brings its own. Whichever of the two folders the domain is
         * aimed at, one of them answers. In the intended layout this pair lands
         * outside the web root, where nothing can ask for it.
         */
        $zip->addFromString('index.php', $this->frontController($root));

        $zip->addFromString('.htaccess', <<<'HTACCESS'
        # Only used when the domain points at this folder rather than at
        # public_html. See the note beside index.php in the packager.
        <IfModule mod_rewrite.c>
            <IfModule mod_negotiation.c>
                Options -MultiViews -Indexes
            </IfModule>

            RewriteEngine On

            RewriteCond %{HTTP:Authorization} .
            RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

            # The shipped static files live one level down. Served from there
            # rather than copied, so there is one of each.
            RewriteRule ^(assets/.*|favicon\.(ico|svg)|robots\.txt|sitemap\.xml)$ public_html/$1 [L]

            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteRule ^ index.php [L]
        </IfModule>

        HTACCESS);

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

    /**
     * The front controller, taught to find the application itself.
     *
     * Laravel's own index.php reaches up one level, which is right when public/
     * sits inside the application. Here it does not: the application is beside
     * the web root so that .env cannot be fetched.
     *
     * But where the web root *is* gets decided by hosting and by whoever is
     * standing in a file manager. Two copies of this file ship, at the two
     * places a domain might be pointed, and each finds the application in
     * either position — so the one edit everybody has to make by hand, and half
     * of them get wrong, is not an edit any more.
     */
    private function frontController(string $root): string
    {
        $preamble = <<<'PHP'
        <?php

        /*
         * Where the application lives, relative to this file.
         *
         * Three places, furthest from the web root first. astralab-app/ holds
         * .env; inside the web root those files have URLs, and the only thing
         * in front of them is an .htaccess that denies everything — which
         * works until a server stops honouring .htaccess, and then stops
         * silently. Two levels up is outside every document root on the
         * account: protection by the filesystem rather than by a rule.
         */
        $candidates = [
            __DIR__.'/../../astralab-app',
            __DIR__.'/../astralab-app',
            __DIR__.'/astralab-app',
        ];

        $astralab = null;

        foreach ($candidates as $candidate) {
            if (is_file($candidate.'/vendor/autoload.php')) {
                $astralab = $candidate;
                break;
            }
        }

        if ($astralab === null) {
            http_response_code(500);
            exit('The application folder was not found. astralab-app/ should be beside '
                .'public_html, beside this index.php, or two levels above it.');
        }

        PHP;

        $original = file_get_contents($root.'/public/index.php');
        $original = preg_replace('/^<\?php\s*/', '', $original, 1);

        return $preamble."\n".str_replace("__DIR__.'/../", '$astralab.\'/', $original);
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
