@extends('install.layout')

@section('title', 'Your database')

@section('content')
  <p class="eyebrow">Step 2 of 3</p>
  <h1 style="margin-top:10px">Connect your database</h1>
  <p class="lede" style="margin-top:14px">
    Create one in hPanel under <strong>Databases → MySQL Databases</strong>,
    then copy the details here. We try them before going on, so a mistake shows
    up now rather than as a blank page later.
  </p>

  <form method="post" action="/install/database" style="margin-top:32px">
    @csrf

    <div class="field-row">
      <label for="database">Database name</label>
      <span class="hint">Usually starts with your account number — u427028527_something.</span>
      <input id="database" name="database" value="{{ old('database', $saved['database'] ?? '') }}" required>
      @error('database') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="username">Database user</label>
      <input id="username" name="username" value="{{ old('username', $saved['username'] ?? '') }}" required>
      @error('username') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="password">Database password</label>
      <input id="password" name="password" type="password" value="{{ old('password', $saved['password'] ?? '') }}">
      @error('password') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="host">Host</label>
      <span class="hint">Leave as localhost unless your host told you otherwise.</span>
      <input id="host" name="host" value="{{ old('host', $saved['host'] ?? 'localhost') }}" required>
      @error('host') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="field-row">
      <label for="port">Port</label>
      <input id="port" name="port" value="{{ old('port', $saved['port'] ?? '3306') }}" required>
      @error('port') <span class="error">{{ $message }}</span> @enderror
    </div>

    <button class="btn btn--primary btn--lg" type="submit">Test and continue</button>
  </form>
@endsection
