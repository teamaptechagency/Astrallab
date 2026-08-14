<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\Hub;
use App\Support\Recovery;
use App\Support\Settings;
use App\Support\Updates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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
            // Whether each secret has a value — never what it is.
            'secrets' => Settings::secretsSet(),
        ]);
    }

    /**
     * Ask the hub whether this shop is talking to it.
     *
     * "Saved" only ever meant the values were written down. This is the only
     * thing that answers whether they are right — and it separates the three
     * ways it can be wrong, because "it does not work" sends somebody checking
     * the wrong one of them.
     */
    public function testHub()
    {
        $base = rtrim((string) config('astralab.hub_url'), '/');

        if ($base === '') {
            return back()->with('hub', [
                'ok' => false,
                'headline' => 'No hub address',
                'detail' => 'Fill in the hub address above and save, then try again.',
            ]);
        }

        // Cleared first: otherwise a test right after fixing the address is
        // answered from the five-minute cache and reports the old failure.
        Cache::forget('hub.catalogue');

        $catalogue = Hub::catalogue();

        if (! $catalogue['ok']) {
            return back()->with('hub', [
                'ok' => false,
                'headline' => 'The hub did not answer',
                'detail' => $base.'/api/v1/catalogue could not be read. Check the address is exactly right '
                    .'and that the hub is up — that page is public, so you can open it in a browser yourself.',
            ]);
        }

        // Reached, and reachable without the secret. Whether the secret is
        // accepted is a different question, and it is the one that decides
        // whether an order can be placed at all.
        $secret = (string) config('astralab.store_secret');

        if ($secret === '') {
            return back()->with('hub', [
                'ok' => false,
                'headline' => 'The hub answered, but no store secret is set',
                'detail' => 'Orders would be refused. Paste the secret above and save.',
            ]);
        }

        $accepted = Hub::secretAccepted();

        if (! $accepted) {
            return back()->with('hub', [
                'ok' => false,
                'headline' => 'The hub answered, and refused the secret',
                'detail' => 'The two do not match. Copy STORE_API_SECRET from the hub again — it must be '
                    .'identical, with no space at either end.',
            ]);
        }

        $products = count($catalogue['products']);
        $methods = count($catalogue['payment_methods']);

        // Connected, which is not the same as ready to sell — and saying so
        // now saves finding out from a customer.
        $missing = [];

        if ($products === 0) {
            $missing[] = 'no product has a price yet (Products, on the hub)';
        }

        if ($methods === 0) {
            $missing[] = 'no payment method is switched on with a number (Settings → Payments, on the hub)';
        }

        return back()->with('hub', [
            'ok' => $missing === [],
            'headline' => $missing === []
                ? 'Connected — '.$products.' '.\Illuminate\Support\Str::plural('product', $products)
                    .' and '.$methods.' '.\Illuminate\Support\Str::plural('payment method', $methods)
                : 'Connected, but the shop has nothing to sell',
            'detail' => $missing === []
                ? 'The shop can take orders. A sale appears on the hub under Orders, and the licence is '
                    .'issued when you accept the payment.'
                : 'The secret is accepted and the hub is answering, but '.implode(', and ', $missing).'.',
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
        foreach ($fields as $key => $field) {
            $input = $data[str_replace('.', '_', $key)] ?? null;
            $empty = $input === null || $input === '';

            // An empty secret means "leave it as it is", not "erase it". The
            // box arrives empty because the value is never rendered back into
            // the page — so without this, saving any other field here would
            // wipe the one thing nobody can retype from memory.
            if ($empty && ($field['secret'] ?? false)) {
                continue;
            }

            $values[$key] = $empty ? null : (string) $input;
        }

        Setting::putMany($values);

        return back()->with('ok', 'Saved. Your public pages are using these now.');
    }
}
