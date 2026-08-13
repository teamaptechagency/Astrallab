<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Applying database changes after the first install.
 *
 * The installer builds the tables once and then closes behind itself, which is
 * right — leaving an unauthenticated screen that can repoint the database at
 * somebody else's would be a way in. But it left no way to run the migrations
 * that arrive with every later upload, and there is no shell on this hosting to
 * run them from.
 *
 * Without this, uploading a new build silently does nothing: the code expects
 * tables the database does not have, and the first page that touches one is a
 * 500 with no explanation.
 *
 * So the console asks. It shows what is waiting, an operator presses a button,
 * and the same command a developer would type runs. Behind a sign-in, because
 * by this point there is somebody to sign in as.
 */
class Updates
{
    /**
     * Migrations on disk that this database has not run.
     *
     * Names only, so the console can say what is about to happen rather than
     * asking somebody to trust a number.
     *
     * @return array<int, string>
     */
    public static function pending(): array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));

            // The repository is the migrations table itself. On a database that
            // has never been migrated it does not exist, and asking is an
            // exception rather than an empty list.
            if (! $migrator->repositoryExists()) {
                return array_keys($files);
            }

            return array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
        } catch (Throwable) {
            // A database that cannot be reached is not the same as one that is
            // up to date, but neither is it something this screen can fix. The
            // pages that need the database will say so in their own way.
            return [];
        }
    }

    /**
     * Run them.
     *
     * --force because this is not an interactive terminal and Laravel would
     * otherwise refuse in production. Migrations are recorded as they succeed,
     * so a run that dies halfway continues from where it stopped rather than
     * repeating what it already did.
     *
     * @return array{ok: bool, message: string}
     */
    public static function apply(): array
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        // Compiled config and views describe the build that has just been
        // replaced. Left alone, the first visitor after an upload gets the old
        // ones — which looks exactly like the update not having worked.
        foreach (['config:clear', 'view:clear', 'route:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
                // Not worth failing an otherwise good migration over.
            }
        }

        return ['ok' => true, 'message' => trim(Artisan::output()) ?: 'Nothing was waiting.'];
    }
}
