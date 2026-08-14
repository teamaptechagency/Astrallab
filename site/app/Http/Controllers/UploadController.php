<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\UpdateEntry;
use App\Support\ChunkedUpload;
use App\Support\SelfUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * A new build of this site, in pieces.
 *
 * PHP will not take it in one request — hosting ships with the limit at 2 MB
 * and a build is several — and a request over that does not arrive truncated,
 * it does not arrive at all. So the browser cuts the file up and posts it a
 * few hundred kilobytes at a time.
 *
 * This is the whole of why the file manager is not part of shipping a bug fix.
 */
class UploadController extends Controller
{
    public function begin(Request $request)
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:190'],
        ]);

        if (! str_ends_with(strtolower($data['filename']), '.zip')) {
            return response()->json(['message' => 'A build is a zip archive.'], 422);
        }

        try {
            return response()->json(
                ChunkedUpload::begin($data['filename'], Auth::id()) + ['ok' => true]
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function chunk(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file'],
        ]);

        try {
            return response()->json(
                ChunkedUpload::append($data['token'], $data['index'], $request->file('chunk'), Auth::id()) + ['ok' => true]
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * All the pieces have arrived. Put them together and install.
     *
     * The assembled file is deleted whatever happens — an archive left in
     * storage after a failed install is several megabytes nobody will ever
     * think to look for.
     */
    public function finish(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string']]);

        try {
            $assembled = ChunkedUpload::assemble($data['token'], Auth::id());

            // Read before it is installed and deleted; afterwards there is
            // nothing left to ask.
            $build = SelfUpdate::describe($assembled);
            $result = SelfUpdate::installFrom($assembled);

            @unlink($assembled);
        } catch (Throwable $e) {
            ChunkedUpload::discard($data['token']);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        ChunkedUpload::discard($data['token']);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['message']], 422);
        }

        Setting::putMany(['build.installed' => now()->format('Y-m-d H:i').' · version '.$build['version']]);

        UpdateEntry::record(
            UpdateEntry::BUILD,
            'Version '.$build['version'].' installed — '.number_format($result['written']).' files replaced',
            basename($assembled).' ('.$build['kind'].' build)',
        );

        return response()->json([
            'ok' => true,
            'message' => number_format($result['written']).' files replaced. Reload, and apply any database changes.',
        ]);
    }
}
