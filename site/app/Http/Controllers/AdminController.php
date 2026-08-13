<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\Recovery;
use App\Support\Settings;
use App\Support\Updates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The operator console.
 *
 * Three states behind one address, because on shared hosting there is no shell
 * to run a command in and no second way in:
 *
 *   no database tables    → set it up: run the migrations, make the first account
 *   nobody signed in      → sign in
 *   signed in             → the console
 *
 * The first is the one that usually needs a terminal. There isn't one here, so
 * the screen runs the migrations itself. That is safe only because it refuses
 * once an account exists — see canSetUp().
 */
class AdminController extends Controller
{
    /** Whichever of the three states this installation is in. */
    public function entry()
    {
        if ($this->canSetUp()) {
            return view('admin.setup');
        }

        if (! Auth::check()) {
            return view('admin.login');
        }

        return view('admin.dashboard', [
            'operator' => Auth::user(),
            'configured' => collect(Settings::current())->filter(fn ($v) => $v !== '' && $v !== null)->count(),
            'total' => count(Settings::fields()),
            // On the first screen after signing in, because an upload whose
            // migrations have not run is invisible until something breaks.
            'pending' => Updates::pending(),
        ]);
    }

    /**
     * Whether the setup screen may be shown at all.
     *
     * True only while there is nobody to sign in as. The moment a first account
     * exists this returns false for good, which is what stops the unauthenticated
     * setup screen from being a way to make yourself an operator on a live
     * console.
     *
     * A missing users table counts as "nobody", because that is exactly the
     * state a fresh upload is in.
     */
    private function canSetUp(): bool
    {
        try {
            return User::query()->count() === 0;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Build the database and create the first operator.
     *
     * Migrations run here rather than over SSH because this hosting has no SSH.
     * They are idempotent — Laravel records what it has applied — so a reload
     * partway through continues rather than starting again.
     */
    public function install(Request $request)
    {
        abort_unless($this->canSetUp(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            // Length over character classes: a long passphrase is both stronger
            // and likelier to be remembered than a short one with a symbol
            // bolted onto the end.
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'setup' => 'The database could not be built: '.$e->getMessage()
                    .' — check the DB_ values in .env.',
            ]);
        }

        // Checked again after the migrations, not only before. Two people
        // opening this screen at the same moment would otherwise both pass the
        // check above and both create an account.
        if (User::query()->count() > 0) {
            return redirect('/apt-admin');
        }

        $operator = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($operator);
        $request->session()->regenerate();

        return redirect('/apt-admin/settings')
            ->with('ok', 'Console ready. These are the details your public pages use.');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            // One message for both failures. Saying which was wrong tells
            // somebody guessing which addresses are real, and that is half the
            // work of getting in.
            throw ValidationException::withMessages([
                'email' => 'That email and password do not match.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/apt-admin');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/apt-admin');
    }

    /**
     * The way back in when the password is gone.
     *
     * Shown whether or not the window is open, because somebody who has just
     * been told "incorrect password" needs to read what to do next, not another
     * 404. The form only appears once the file exists.
     */
    public function recover()
    {
        return view('admin.recover', [
            'open' => Recovery::isOpen(),
            'minutes' => (int) ceil(Recovery::remaining() / 60),
            'path' => 'storage/app/recover',
        ]);
    }

    public function resetPassword(Request $request)
    {
        // Checked again here, not only when the form was drawn. The window can
        // close between loading the page and submitting it, and a form that
        // still worked afterwards would make the expiry decorative.
        abort_unless(Recovery::isOpen(), 403, 'The recovery window has closed. Create the file again.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $operator = User::firstWhere('email', $data['email']);

        if ($operator) {
            $operator->update(['password' => Hash::make($data['password'])]);
        } else {
            // No account with that address. Creating one is deliberate: if the
            // only account was lost entirely — a mistyped email at install —
            // resetting nothing would leave the console permanently shut.
            $operator = User::create([
                'name' => $data['name'] ?: 'Operator',
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
        }

        $closed = Recovery::consume();

        Auth::login($operator);
        $request->session()->regenerate();

        return redirect('/apt-admin')->with(
            'ok',
            $closed
                ? 'Password set. The recovery file has been deleted.'
                : 'Password set — but the recovery file could not be deleted. Remove '
                    .'storage/app/recover yourself, or anyone reaching it can reset this again.'
        );
    }

    /**
     * Apply the migrations that arrived with the last upload.
     *
     * Behind a sign-in, unlike the installer: by this point there is somebody
     * to sign in as, so there is no reason for it to be open.
     */
    public function applyUpdates()
    {
        $result = Updates::apply();

        return back()->with(
            $result["ok"] ? "ok" : "problem",
            $result["ok"]
                ? "Database updated."
                : "The update failed: ".$result["message"]
        );
    }

    public function settings()
    {
        return view('admin.settings', [
            'groups' => Settings::grouped(),
            'values' => Settings::current(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $fields = Settings::fields();

        $rules = [];
        foreach ($fields as $key => $field) {
            // Dots are how Laravel addresses nested input, so the form names
            // them with underscores and they are translated back here.
            $rules[str_replace('.', '_', $key)] = $field['rules'];
        }

        $data = $request->validate($rules, [
            'contact_whatsapp.regex' => 'WhatsApp must be digits only, with the country code and no plus — 8801XXXXXXXXX.',
        ]);

        $values = [];
        foreach (array_keys($fields) as $key) {
            $input = $data[str_replace('.', '_', $key)] ?? null;
            $values[$key] = ($input === null || $input === '') ? null : (string) $input;
        }

        Setting::putMany($values);

        return back()->with('ok', 'Saved. Your public pages are using these now.');
    }
}
