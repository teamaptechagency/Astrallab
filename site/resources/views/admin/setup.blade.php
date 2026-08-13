@extends('admin.layout')

@section('title', 'Set up the console')

@section('content')
  <p class="eyebrow">First run</p>
  <h1 style="margin-top:10px">Set up the console</h1>
  <p class="lede" style="margin-top:14px">
    This builds the database tables and creates the first account. It can only
    be done once — the moment an account exists this screen stops existing.
  </p>

  <form method="post" action="/apt-admin/setup" style="margin-top:34px">
    @csrf

    <div class="field-row">
      <label for="name">Your name</label>
      <input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
      @error('name') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="email">Email</label>
      <span class="hint">This is your sign-in.</span>
      <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username">
      @error('email') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="password">Password</label>
      <span class="hint">Ten characters or more. A phrase you will remember beats a short one you will not.</span>
      <input id="password" name="password" type="password" required autocomplete="new-password">
      @error('password') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="password_confirmation">Password again</label>
      <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
    </div>

    <button class="btn btn--primary btn--lg" type="submit">Build it</button>
  </form>
@endsection
