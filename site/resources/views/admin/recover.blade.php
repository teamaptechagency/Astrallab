@extends('admin.layout')

@section('title', 'Get back in')

@section('content')
  <div style="max-width:520px;margin:20px auto 0">
    <p class="eyebrow">Recovery</p>
    <h1 style="margin-top:10px">Locked out</h1>

    @unless ($open)
      <p class="lede" style="margin-top:14px">
        There is no reset email, on purpose — this console can revoke every
        customer's licence, and a reset link is a way in for whoever reaches the
        mailbox. Proving you own the server is the bar instead.
      </p>

      <div class="card" style="margin-top:26px">
        <h3>What to do</h3>
        <ol style="margin:12px 0 0 18px;color:var(--ink-2);line-height:1.9">
          <li>Open your hosting file manager.</li>
          <li>Go to <code>astralab-app/{{ $path }}</code>'s folder — that is
              <code>astralab-app/storage/app/</code>.</li>
          <li>Create an empty file named exactly <code>recover</code>. No
              extension. Watch out for file managers that add <code>.txt</code>.</li>
          <li>Reload this page. You will have {{ \App\Support\Recovery::MINUTES }} minutes.</li>
        </ol>
      </div>

      <a class="btn btn--ghost" href="/apt-admin/recover" style="margin-top:22px">Reload</a>
    @else
      <p class="lede" style="margin-top:14px">
        Recovery file found. Set a new password — this window closes in
        {{ $minutes }} {{ \Illuminate\Support\Str::plural('minute', $minutes) }},
        and the file is deleted as soon as you are done.
      </p>

      <form method="post" action="/apt-admin/recover" style="margin-top:30px">
        @csrf

        <div class="field-row">
          <label for="email">Email</label>
          <span class="hint">The account to reset. If no account has this address, one is created.</span>
          <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username">
          @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field-row">
          <label for="name">Name</label>
          <span class="hint">Only used if a new account has to be created.</span>
          <input id="name" name="name" value="{{ old('name') }}" maxlength="120">
          @error('name') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field-row">
          <label for="password">New password</label>
          <span class="hint">Ten characters or more.</span>
          <input id="password" name="password" type="password" required autocomplete="new-password">
          @error('password') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field-row">
          <label for="password_confirmation">New password again</label>
          <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>

        <button class="btn btn--primary btn--lg" type="submit">Set it and sign in</button>
      </form>
    @endunless
  </div>
@endsection
