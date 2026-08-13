@extends('layouts.site')

@section('title', 'Page not found — Astra Lab')
@section('description', 'That address does not exist on this site.')
@section('noindex', true)

@section('content')
  <main class="wrap page-head">
    <p class="eyebrow">404</p>
    <h1 style="margin-top:12px">That page has moved, or never existed.</h1>
    <p class="lede" style="margin:18px auto 0">
      Nothing is wrong with your shop — this is the wrong address on our
      website.
    </p>

    <div class="hero-actions" style="justify-content:center;margin-top:30px">
      <a class="btn btn--primary btn--lg" href="{{ route('home') }}">Home</a>
      <a class="btn btn--ghost btn--lg" href="{{ route('docs') }}">Install guide</a>
      <a class="btn btn--ghost btn--lg" href="{{ route('contact') }}">Contact us</a>
    </div>

    <p class="hero-note" style="margin-top:26px">
      Looking for your own shop's admin? That lives on your own domain, not
      here.
    </p>
  </main>
@endsection
