@extends('install.layout')

@section('title', 'Your account')

@section('content')
  <p class="eyebrow">Step 3 of 3</p>
  <h1 style="margin-top:10px">Create your sign-in</h1>
  <p class="lede" style="margin-top:14px">
    The account you will run the console with. Pressing the button builds the
    database tables and finishes the install.
  </p>

  <form method="post" action="/install/account" style="margin-top:32px">
    @csrf

    <div class="field-row">
      <label for="name">Your name</label>
      <input id="name" name="name" value="{{ old('name', $saved['name'] ?? '') }}" required maxlength="120" autocomplete="name">
      @error('name') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="email">Email</label>
      <span class="hint">This is your sign-in.</span>
      <input id="email" name="email" type="email" value="{{ old('email', $saved['email'] ?? '') }}" required autocomplete="username">
      @error('email') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="password">Password</label>
      <span class="hint">Ten characters or more. A phrase you will remember beats a short one you will not — this account can revoke every customer's licence.</span>
      <input id="password" name="password" type="password" required autocomplete="new-password">
      @error('password') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="password_confirmation">Password again</label>
      <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
    </div>

    <button class="btn btn--primary btn--lg" type="submit">Finish the install</button>
  </form>
@endsection
