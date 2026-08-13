@extends('install.layout')

@section('title', 'Before we start')

@section('content')
  <p class="eyebrow">Step 1 of 3</p>
  <h1 style="margin-top:10px">Let us check your hosting</h1>
  <p class="lede" style="margin-top:14px">
    Everything below has to be true before the site can run. Where something is
    missing, the line underneath says which panel to fix it in.
  </p>

  @if ($exposure['known'] && $exposure['exposed'])
    {{-- The one failure worth stopping everything for. If .env can be
         downloaded, the database password becomes public the moment it is
         written, and continuing would be handing it over. --}}
    <p class="flash" style="margin-top:26px">
      <strong>Stop.</strong> Your settings file can be downloaded from the web
      at <code>/.env</code>. Anything saved here — including the database
      password you are about to type — would be readable by anybody. The
      application must sit <em>beside</em> <code>public_html</code>, not inside
      it. Fix that before going on.
    </p>
  @endif

  <div class="checks">
    @foreach ($checks as $check)
      <div class="check {{ $check['ok'] ? 'check--ok' : 'check--bad' }}">
        <span class="check__mark" aria-hidden="true">{{ $check['ok'] ? '✓' : '✕' }}</span>
        <span class="check__what">
          {{ $check['name'] }}
          @unless ($check['ok'])
            <span class="check__detail">{{ $check['detail'] }}</span>
          @endunless
        </span>
      </div>
    @endforeach
  </div>

  @if ($blocked)
    <p class="lede">Fix the ones marked ✕, then check again.</p>
    <a class="btn btn--ghost btn--lg" href="/install" style="margin-top:18px">Check again</a>
  @else
    <a class="btn btn--primary btn--lg" href="/install/database">Continue</a>
  @endif
@endsection
