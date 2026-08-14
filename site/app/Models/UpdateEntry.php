<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * One thing that was done to this console.
 *
 * Written at the two moments that change what is running: files replaced, and
 * migrations applied.
 */
class UpdateEntry extends Model
{
    protected $table = 'update_log';

    public const BUILD = 'build';

    public const DATABASE = 'database';

    protected $fillable = ['kind', 'summary', 'detail', 'actor'];

    /**
     * Record it, and never fail because of it.
     *
     * This is called immediately after an update has already happened. If
     * writing the note throws — the table not existing yet being the obvious
     * way, since this table arrived in an update itself — the update is still
     * done, and turning a successful one into a 500 would send somebody
     * looking for a problem that is not there.
     */
    public static function record(string $kind, string $summary, ?string $detail = null): void
    {
        try {
            static::create([
                'kind' => $kind,
                'summary' => $summary,
                'detail' => $detail,
                'actor' => Auth::user()?->name,
            ]);
        } catch (Throwable) {
            //
        }
    }

    /** @return \Illuminate\Support\Collection<int, static> */
    public static function recent(int $limit = 30)
    {
        try {
            return static::latest()->limit($limit)->get();
        } catch (Throwable) {
            // Before this table exists, which is exactly the state a console
            // is in when it is about to be updated for the first time.
            return collect();
        }
    }
}
