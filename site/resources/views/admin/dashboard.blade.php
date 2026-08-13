@extends('admin.layout')

@section('title', 'Overview')

@section('content')
  <p class="eyebrow">Console</p>
  <h1 style="margin-top:10px">Hello, {{ $operator->name }}</h1>

  @if ($pending)
    {{-- First, and unmissable. An upload whose migrations have not run is
         invisible until a page touches a table that is not there, and then it
         is a 500 with nothing to read. --}}
    <div class="card" style="margin-top:26px;border-left:3px solid var(--brand)">
      <h3>{{ count($pending) }} database {{ \Illuminate\Support\Str::plural('update', count($pending)) }} waiting</h3>
      <p style="margin-top:6px">
        A newer build has been uploaded. Its tables have not been created yet,
        and parts of the console will not work until they are.
      </p>

      <ul style="margin:12px 0 0 18px;color:var(--ink-3);font-size:.8125rem;line-height:1.8">
        @foreach ($pending as $migration)
          <li><code>{{ $migration }}</code></li>
        @endforeach
      </ul>

      <form method="post" action="/apt-admin/updates" style="margin-top:18px">
        @csrf
        <button class="btn btn--primary" type="submit">Apply them</button>
      </form>
    </div>
  @endif

  <div class="grid grid--2" style="margin-top:32px">
    <div class="card">
      <h3>Site details</h3>
      <p style="margin-top:6px">{{ $configured }} of {{ $total }} filled in. These
        appear on the contact page, the footer and the legal pages.</p>
      <a class="btn btn--primary" href="/apt-admin/settings" style="margin-top:16px">Edit settings</a>
    </div>

    <div class="card">
      <h3>Not built yet</h3>
      <p style="margin-top:6px">Licences, releases, customers and the API that
        installed shops call. The database is ready for them; the screens are
        the next build.</p>
    </div>
  </div>
@endsection
