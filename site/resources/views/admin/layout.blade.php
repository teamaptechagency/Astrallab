{{-- The console. Deliberately plain: it is a handful of forms used by two or
     three people, and the storefront's stylesheet already has buttons, cards
     and fields that work. A second design system here would be a second thing
     to maintain for no one's benefit. --}}

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Console') — Astra Lab</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('assets/styles.css') }}">
<style>
  /* The few things the public stylesheet has no reason to carry. */
  .console-bar {
    border-bottom: 1px solid var(--line); background: var(--surface);
    position: sticky; top: 0; z-index: 5;
  }
  .console-bar .wrap { display: flex; align-items: center; gap: 20px; height: 60px; }
  .console-nav { margin-left: auto; display: flex; align-items: center; gap: 6px; }
  .console-nav a, .console-nav button {
    font: inherit; font-size: .9375rem; padding: 8px 12px; border-radius: 8px;
    text-decoration: none; color: var(--ink-2); border: 0; background: none; cursor: pointer;
  }
  .console-nav a:hover, .console-nav button:hover { background: var(--surface-2); color: var(--ink); }
  .console-nav a.is-here { background: var(--brand-tint); color: var(--brand-dark); font-weight: 600; }

  .field-row { display: grid; gap: 6px; margin-bottom: 20px; }
  .field-row label { font-weight: 600; font-size: .9375rem; }
  .field-row .hint { font-size: .8125rem; color: var(--ink-3); }
  .field-row input, .field-row textarea {
    width: 100%; padding: 11px 13px; border: 1px solid var(--line); border-radius: 10px;
    font: inherit; font-size: .9375rem; background: var(--surface); color: var(--ink);
  }
  .field-row input:focus, .field-row textarea:focus {
    outline: 0; border-color: var(--brand);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 28%, transparent);
  }
  .field-row textarea { min-height: 90px; resize: vertical; }
  .field-row .error { font-size: .8125rem; color: #c0392b; }

  .flash {
    padding: 13px 16px; border-radius: 10px; margin-bottom: 24px; font-size: .9375rem;
    background: var(--brand-tint); color: var(--brand-dark);
    border: 1px solid color-mix(in srgb, var(--brand) 25%, transparent);
  }
  .flash--bad {
    background: color-mix(in srgb, #c0392b 8%, var(--surface)); color: #c0392b;
    border-color: color-mix(in srgb, #c0392b 25%, transparent);
  }
</style>
</head>
<body>

@auth
  <div class="console-bar">
    <div class="wrap">
      <a class="logo" href="/apt-admin">
        <span class="logo-mark" aria-hidden="true">A</span>
        <span>Console<span class="logo-sub">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</span></span>
      </a>

      <nav class="console-nav">
        <a href="/apt-admin" @class(['is-here' => request()->is('apt-admin')])>Overview</a>
        <a href="/apt-admin/settings" @class(['is-here' => request()->is('apt-admin/settings')])>Settings</a>
        <a href="/" target="_blank" rel="noopener">View site ↗</a>
        <form method="post" action="/apt-admin/logout" style="display:inline">
          @csrf
          <button type="submit">Sign out</button>
        </form>
      </nav>
    </div>
  </div>
@endauth

<main class="wrap" style="padding-block:44px 72px;max-width:820px">
  @if (session('ok'))
    <p class="flash">{{ session('ok') }}</p>
  @endif

  @if ($errors->has('setup'))
    <p class="flash flash--bad">{{ $errors->first('setup') }}</p>
  @endif

  @yield('content')
</main>

</body>
</html>
