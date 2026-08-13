@extends('admin.layout')

@section('title', 'Overview')

@section('content')
  <p class="eyebrow">Console</p>
  <h1 style="margin-top:10px">Hello, {{ $operator->name }}</h1>

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
        installed shops call. Those are the next build.</p>
    </div>
  </div>
@endsection
