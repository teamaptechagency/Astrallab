<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
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
