{{-- The installer. Its own layout, because the site's header links to pages
     that do not work yet, and a nav offering Docs and Pricing during setup is
     an invitation to wander off half-installed. --}}

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') — Astra Lab</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('assets/styles.css') }}">
<style>
  .steps-bar { display: flex; gap: 8px; margin-bottom: 34px; }
  .steps-bar span { flex: 1; height: 4px; border-radius: 999px; background: var(--line); }
  .steps-bar span.is-done { background: var(--brand); }

  .field-row { display: grid; gap: 6px; margin-bottom: 20px; }
  .field-row label { font-weight: 600; font-size: .9375rem; }
  .field-row .hint { font-size: .8125rem; color: var(--ink-3); }
  .field-row input {
    width: 100%; padding: 11px 13px; border: 1px solid var(--line); border-radius: 10px;
    font: inherit; font-size: .9375rem; background: var(--surface); color: var(--ink);
  }
  .field-row input:focus {
    outline: 0; border-color: var(--brand);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 28%, transparent);
  }
  .field-row .error { font-size: .8125rem; color: #c0392b; }

  .flash {
    padding: 13px 16px; border-radius: 10px; margin-bottom: 26px; font-size: .9375rem;
    background: color-mix(in srgb, #c0392b 8%, var(--surface)); color: #c0392b;
    border: 1px solid color-mix(in srgb, #c0392b 25%, transparent);
  }

  .checks { display: grid; gap: 2px; margin: 26px 0; }
  .check {
    display: flex; align-items: flex-start; gap: 12px; padding: 11px 0;
    border-bottom: 1px solid var(--line); font-size: .9375rem;
  }
  .check__mark { width: 18px; flex: none; font-weight: 700; }
  .check--ok .check__mark { color: var(--brand); }
  .check--bad .check__mark { color: #c0392b; }
  .check__what { flex: 1; }
  .check__detail { display: block; font-size: .8125rem; color: var(--ink-3); margin-top: 3px; }
</style>
</head>
<body>

<main class="wrap" style="padding-block:52px 80px;max-width:640px">
  <span class="logo" style="margin-bottom:28px">
    <span class="logo-mark" aria-hidden="true">A</span>
    <span>Astra Lab<span class="logo-sub">Setting up</span></span>
  </span>

  <div class="steps-bar" aria-hidden="true">
    @for ($i = 1; $i <= 3; $i++)
      <span @class(['is-done' => $i <= $step])></span>
    @endfor
  </div>

  @if ($errors->has('install') || $errors->has('database'))
    <p class="flash">{{ $errors->first('install') ?: $errors->first('database') }}</p>
  @endif

  @yield('content')
</main>

</body>
</html>
