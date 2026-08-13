<?php

namespace App\Support;

/**
 * Getting back into a console nobody can sign into.
 *
 * There is no "forgot password" email here, and there should not be: this
 * console can revoke every customer's licence, and a reset link is a way in for
 * whoever reaches the mailbox. There is also no shell to run a command in.
 *
 * So the proof is the filesystem. Create a file called `recover` in
 * storage/app/ through the hosting file manager, and a reset form opens for a
 * short while. Anybody who can do that already has the database credentials and
 * could change the password row directly — this only saves them working out a
 * bcrypt hash.
 *
 * Two things make it safe enough. It expires, so a file somebody forgot to
 * delete is not a permanent way in. And it is consumed on use, so the window
 * closes the moment it has done its job.
 */
class Recovery
{
    /**
     * How long the file stays good for.
     *
     * Long enough to find the right screen in a file manager on a slow
     * connection; short enough that forgetting to delete it is not a hole.
     */
    public const MINUTES = 30;

    public static function path(): string
    {
        return storage_path('app/recover');
    }

    /** Whether a reset may be offered right now. */
    public static function isOpen(): bool
    {
        return self::remaining() > 0;
    }

    /** Seconds left, or 0. Shown on the form so the clock is not a surprise. */
    public static function remaining(): int
    {
        $path = self::path();

        if (! is_file($path)) {
            return 0;
        }

        // Modified rather than created: PHP cannot read a creation time
        // portably, and recreating the file to extend the window is exactly
        // what somebody who ran out of time should do.
        $age = time() - (int) filemtime($path);

        return max(0, (self::MINUTES * 60) - $age);
    }

    /**
     * Close the window.
     *
     * Called after a successful reset. If the file cannot be deleted — a
     * read-only mount, an odd permission — that is worth knowing about rather
     * than ignoring, because it means the window stays open until it expires.
     */
    public static function consume(): bool
    {
        $path = self::path();

        return ! is_file($path) || @unlink($path);
    }
}
