{{-- astralab.com — the public pages.

     The header and footer were copied into three static files before this, so
     a nav change meant three edits and the docs page had already drifted (it
     was missing Pricing). One layout is most of the reason for merging the
     site and the console into one application.

     Everything absolute — canonical, Open Graph, the sitemap — is built from
     config('astralab.url), so the domain is set once in .env rather than
     stamped into five files at build time. That was a real bug: pages went out
     announcing astralab.com as the canonical home of a site live somewhere
     else, which keeps the live one out of search entirely. --}}

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>@yield('title')</title>
<meta name="description" content="@yield('description')">

<link rel="canonical" href="{{ $canonical }}">
<link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
<meta name="theme-color" content="#12a06d">

{{-- What Facebook and WhatsApp show when the link is shared, which in
     Bangladesh is how most of this site will actually be passed around. --}}
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:site_name" content="Astra Lab">
<meta property="og:locale" content="en_GB">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:title" content="@yield('og_title', View::yieldContent('title'))">
<meta property="og:description" content="@yield('description')">
<meta property="og:image" content="{{ url('assets/share.png') }}">
<meta name="twitter:card" content="summary_large_image">

@hasSection('noindex')
  <meta name="robots" content="noindex">
@endif

<link rel="stylesheet" href="{{ asset('assets/styles.css') }}">
</head>
<body>

<header class="site-header">
  <div class="wrap">
    <a class="logo" href="{{ route('home') }}">
      <span class="logo-mark" aria-hidden="true">A</span>
      <span>Astra Lab<span class="logo-sub">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</span></span>
    </a>

    <button class="nav-toggle" aria-expanded="false" aria-controls="site-nav">
      <span class="sr-only">Menu</span>
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
        <path d="M4 7h16M4 12h16M4 17h16"/>
      </svg>
    </button>

    <nav class="nav" id="site-nav">
      <a href="{{ route('home') }}#products">Products</a>
      <a href="{{ route('home') }}#how">How it works</a>
      <a href="{{ route('home') }}#pricing">Pricing</a>
      <a href="{{ route('services') }}">Support</a>
      <a href="{{ route('docs') }}">Docs</a>
      <a class="btn btn--primary" href="/shop/">Buy now</a>
    </nav>
  </div>
</header>

@yield('content')

{{-- The full footer on every page, not only the home page. It was written once
     and pasted three times, so docs and services quietly shipped without the
     legal links — the two pages a cautious buyer is most likely to be on when
     they go looking for them. --}}
<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <a class="logo" href="{{ route('home') }}" style="margin-bottom:12px">
          <span class="logo-mark" aria-hidden="true">A</span>
          <span>Astra Lab</span>
        </a>
        <p>Software you install and own, built in Bangladesh.</p>
        <p style="margin-top:10px">
          In partnership with
          <a href="https://aptechagency.com" style="color:var(--brand);text-decoration:none;font-weight:600">{{ config('astralab.company.partner') }}</a>.
        </p>
      </div>

      <div>
        <h4>Product</h4>
        <ul>
          <li><a href="{{ route('home') }}#products">Products</a></li>
          <li><a href="{{ route('home') }}#pricing">Pricing</a></li>
          <li><a href="{{ route('home') }}#how">How it works</a></li>
          <li><a href="{{ route('shop') }}">Buy</a></li>
        </ul>
      </div>

      <div>
        <h4>Help</h4>
        <ul>
          <li><a href="{{ route('docs') }}">Documentation</a></li>
          <li><a href="{{ route('docs') }}#requirements">Server requirements</a></li>
          <li><a href="{{ route('services') }}">Support &amp; care plans</a></li>
          <li><a href="{{ route('contact') }}">Contact</a></li>
        </ul>
      </div>

      <div>
        <h4>Legal</h4>
        <ul>
          <li><a href="{{ route('terms') }}">Terms</a></li>
          <li><a href="{{ route('privacy') }}">Privacy</a></li>
          <li><a href="{{ route('refund') }}">Refund policy</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; {{ date('Y') }} {{ config('astralab.company.name') }} — a partner of {{ config('astralab.company.partner') }}. All rights reserved.</span>
      <span>{{ config('astralab.contact.address') ?: 'Dhaka, Bangladesh' }}</span>
    </div>
  </div>
</footer>

<script src="{{ asset('assets/site.js') }}"></script>
</body>
</html>
