@extends('admin.layout')

@section('title', 'Sign in')

@section('content')
  <div style="max-width:420px;margin:40px auto 0">
    <a class="logo" href="/" style="margin-bottom:20px">
      <span class="logo-mark" aria-hidden="true">A</span>
      <span>Astra Lab<span class="logo-sub">Operator console</span></span>
    </a>

    <form method="post" action="/apt-admin/login">
      @csrf

      <div class="field-row">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        @error('email') <span class="error">{{ $message }}</span> @enderror
      </div>

      <div class="field-row">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </div>

      <button class="btn btn--primary" type="submit" style="width:100%">Sign in</button>
    </form>
  </div>
@endsection
