<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The settings an operator can edit.
 *
 * Read on every page — the footer alone wants the company name and the
 * address — so the whole table is fetched once and cached, rather than a query
 * per key. There are a few dozen of them and they change a few times a year.
 *
 * Every read is wrapped against failure. This is asked for while rendering a
 * page, and a shop whose database is briefly unreachable should show its own
 * defaults rather than a stack trace. It is also asked for before the table
 * exists, on the very first request after upload, and that must not be an
 * error either.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    private const CACHE = 'settings.all';

    /**
     * Every setting, as key => value.
     *
     * Named map() rather than all(), which belongs to Eloquent and takes a
     * column list. Overriding it with a different signature is a fatal error at
     * class-load time — the whole application, not just this model.
     *
     * @return array<string, string>
     */
    public static function map(): array
    {
        // The cache call is inside the try, not only the query. With the
        // database cache driver — Laravel's default — reading the cache is
        // itself a query against a `cache` table that a fresh install has not
        // created yet. Guarding only the inner query left the first request
        // after upload throwing from the line that was supposed to be the safe
        // one.
        try {
            return Cache::rememberForever(
                self::CACHE,
                fn () => static::query()->pluck('value', 'key')->all()
            );
        } catch (Throwable) {
            // No tables yet, or no database. The first is a fresh upload and
            // the second is somebody else's outage; neither is worth a broken
            // page, and both are temporary.
            return [];
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::map()[$key] ?? null;

        // A blank setting means "not set" rather than "set to nothing" —
        // somebody clearing the phone box wants the phone number gone, and
        // wants the default back if there is one.
        return ($value === null || $value === '') ? $default : $value;
    }

    /** @param  array<string, string|null>  $values */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE);
    }
}
