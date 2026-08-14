<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Getting a large file onto shared hosting through a browser.
 *
 * PHP will not accept one. A CMS package is around 44 MB, hosting ships with
 * upload_max_filesize at 2 and post_max_size at 8, and a request over the limit
 * does not arrive truncated — it does not arrive at all. Even where the limits
 * are raised, a single 44 MB POST across a domestic connection runs long enough
 * that shared hosting cuts it off, and there is nothing to resume.
 *
 * So the file is cut up in the browser and posted a few hundred kilobytes at a
 * time. Every request is small and short, which is the shape this hosting is
 * good at. The parts are written to disk under a token, and only assembled once
 * all of them have arrived.
 *
 * This is what stops the file manager being part of the weekly routine.
 */
class ChunkedUpload
{
    /** Under storage/app, which is outside the web root. */
    private const ROOT = 'chunked-uploads';

    /** Abandoned parts are swept after this long. */
    private const KEEP_HOURS = 24;

    /**
     * How much to send at a time.
     *
     * Half of whatever PHP will accept, floored at 128 KB and capped at 1 MB.
     * Half rather than all of it because the chunk is not the whole request —
     * there is a token, an index and multipart framing around it, and a chunk
     * sized exactly at the limit is a chunk that fails.
     */
    public static function chunkSize(): int
    {
        $limit = SelfUpdate::limits()['effective'];

        return max(131072, min(1048576, (int) ($limit / 2)));
    }

    /** @return array{token: string, chunk: int} */
    public static function begin(string $filename, int $operatorId): array
    {
        self::sweep();

        $token = Str::random(40);
        $directory = self::directory($token);

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create a place to put the upload.');
        }

        file_put_contents($directory.'/meta.json', json_encode([
            // basename, because this ends up as a path. Nothing arriving from a
            // browser gets to choose a directory.
            'filename' => basename($filename),
            'operator' => $operatorId,
            'started' => time(),
        ]));

        return ['token' => $token, 'chunk' => self::chunkSize()];
    }

    /**
     * @return array{received: int}
     *
     * @throws RuntimeException
     */
    public static function append(string $token, int $index, UploadedFile $chunk, int $operatorId): array
    {
        $meta = self::meta($token, $operatorId);

        // Numbered rather than appended to one file. The browser sends these in
        // order, but a retry after a dropped connection does not have to be in
        // order — and an append that arrives twice silently corrupts a file
        // whose checksum is then computed from the corruption.
        $chunk->move(self::directory($token), $index.'.part');

        return ['received' => count(glob(self::directory($token).'/*.part') ?: [])];
    }

    /**
     * Put it back together.
     *
     * Returns the path to the assembled file, which the caller owns and is
     * responsible for moving or deleting.
     *
     * @throws RuntimeException
     */
    public static function assemble(string $token, int $operatorId): string
    {
        $meta = self::meta($token, $operatorId);
        $directory = self::directory($token);

        $parts = glob($directory.'/*.part') ?: [];

        if ($parts === []) {
            throw new RuntimeException('Nothing arrived.');
        }

        // Sorted by number, not by name: 10.part sorts before 2.part as text,
        // and a file assembled in that order is a corrupt file with a perfectly
        // healthy-looking size.
        usort($parts, fn ($a, $b) => (int) basename($a, '.part') <=> (int) basename($b, '.part'));

        foreach ($parts as $position => $part) {
            if ((int) basename($part, '.part') !== $position) {
                throw new RuntimeException('Part '.$position.' never arrived. Start the upload again.');
            }
        }

        $assembled = $directory.'/'.$meta['filename'];
        $out = fopen($assembled, 'wb');

        if (! $out) {
            throw new RuntimeException('Could not open the assembled file for writing.');
        }

        foreach ($parts as $part) {
            $in = fopen($part, 'rb');

            if (! $in) {
                fclose($out);

                throw new RuntimeException('Could not read part '.basename($part).'.');
            }

            // Streamed rather than read into memory. 44 MB read whole is 44 MB
            // of a memory limit that is 128 on most of this hosting.
            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);

        foreach ($parts as $part) {
            @unlink($part);
        }

        return $assembled;
    }

    /**
     * Tidying up never fails.
     *
     * This is called from the catch block around everything else, so a token
     * rejected as malformed used to be rejected a second time on the way out —
     * and that second throw escaped as a 500 rather than the refusal the first
     * one had already decided on. There is nothing to clean up for a token that
     * was never real.
     */
    public static function discard(string $token): void
    {
        try {
            $directory = self::directory($token);
        } catch (RuntimeException) {
            return;
        }

        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }

    /** @return array{filename: string, operator: int, started: int} */
    private static function meta(string $token, int $operatorId): array
    {
        $path = self::directory($token).'/meta.json';

        if (! is_file($path)) {
            throw new RuntimeException('That upload has expired. Start it again.');
        }

        $meta = json_decode((string) file_get_contents($path), true);

        // The token is unguessable, but it is also the only thing standing
        // between one operator's upload and another's. Checked rather than
        // assumed.
        if (! is_array($meta) || ($meta['operator'] ?? null) !== $operatorId) {
            throw new RuntimeException('That upload belongs to somebody else.');
        }

        return $meta;
    }

    private static function directory(string $token): string
    {
        // The token comes from a request. Anything that is not what begin()
        // generates cannot be allowed to walk out of this folder.
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            throw new RuntimeException('That is not an upload.');
        }

        return storage_path('app/'.self::ROOT.'/'.$token);
    }

    /**
     * Throw away what somebody started and abandoned.
     *
     * A closed tab half way through a 44 MB upload leaves 20 MB on a disk that
     * is measured in gigabytes and shared. Nothing else would ever remove it.
     */
    private static function sweep(): void
    {
        $root = storage_path('app/'.self::ROOT);

        if (! is_dir($root)) {
            return;
        }

        $cutoff = time() - (self::KEEP_HOURS * 3600);

        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (filemtime($directory) < $cutoff) {
                foreach (glob($directory.'/*') ?: [] as $file) {
                    @unlink($file);
                }

                @rmdir($directory);
            }
        }
    }
}
