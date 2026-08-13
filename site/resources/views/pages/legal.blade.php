{{-- Terms, privacy and refunds share a shape: a short page of plain sentences
     under a heading. One template rather than three keeps them consistent, and
     consistency is most of what makes a legal page readable.

     What is on them is written from what this software actually does — the
     licence is per-domain and movable, the hub receives a known list of fields,
     the CMS keeps shop data on the customer's own server. None of it is copied
     from a template, and none of it should be replaced with one without
     reading what it currently says. --}}

@extends('layouts.site')

@section('title', $heading.' — Astra Lab')
@section('description', $summary)

@section('content')
  <main class="wrap prose-page">
    <p class="eyebrow">{{ $eyebrow }}</p>
    <h1 style="margin-top:12px">{{ $heading }}</h1>
    <p class="lede" style="margin-top:16px">{{ $summary }}</p>

    @foreach ($sections as $section)
      <section style="margin-top:44px">
        <h2>{{ $section['title'] }}</h2>
        @foreach ($section['body'] as $paragraph)
          <p style="margin-top:12px;color:var(--ink-2)">{!! $paragraph !!}</p>
        @endforeach
      </section>
    @endforeach

    <div class="card" style="margin-top:44px;text-align:center">
      <h3>Anything unclear?</h3>
      <p style="margin-top:6px">Ask before you buy rather than after. We would
        rather answer a question than issue a refund.</p>
      <a class="btn btn--primary" href="{{ route('contact') }}" style="margin-top:16px">Contact us</a>
    </div>
  </main>
@endsection
