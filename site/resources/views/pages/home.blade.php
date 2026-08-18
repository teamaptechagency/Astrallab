@extends('layouts.site')

@section('title', 'Astra Lab — self-hosted e-commerce for Bangladesh')
@section('description', 'Own your online store. A self-hosted e-commerce CMS that installs on ordinary Bangladeshi shared hosting, takes bKash and Nagad, and costs one payment — not a monthly fee.')

@section('content')
<main>

  <section class="hero wrap">
    <div class="hero-grid">
      <div>
        <p class="eyebrow">Self-hosted · One-time payment</p>
        <h1>Own your online store. Not a subscription to one.</h1>
        <p class="lede" style="margin-top:18px">
          Astra Lab is an e-commerce CMS you install on your own hosting. It runs on ordinary
          Bangladeshi shared hosting, takes bKash and Nagad through your own merchant account, and
          the data stays on your server — because it is your business.
        </p>

        <div class="hero-actions">
          <a class="btn btn--primary btn--lg" href="#products">See what we sell</a>
          <a class="btn btn--ghost btn--lg" href="{{ route('docs') }}">Read the install guide</a>
        </div>

        <p class="hero-note">
          Pay once. Updates and support included. No monthly fee, ever.<br>
          Built and supported in partnership with
          <a href="https://aptechagency.com" style="color:var(--brand);font-weight:600;text-decoration:none">AP Tech Agency</a>.
        </p>

        <div class="badges">
          <span class="badge">Works on cPanel shared hosting</span>
          <span class="badge">bKash &amp; Nagad ready</span>
          <span class="badge">Bangla support</span>
        </div>
      </div>

      <div class="terminal" aria-hidden="true">
        <div class="terminal-bar"><i class="dot"></i><i class="dot"></i><i class="dot"></i></div>
        <pre><span class="c"># 1. upload the installer to your hosting</span>
$ unzip astralab-installer.zip

<span class="c"># 2. open it in your browser and paste your key</span>
Licence key: ASTRA-7K2M9-QX4RT-8NBVC-3WHDZ
<span class="g">✓ verified</span>  ·  downloading core files…

<span class="c"># 3. that's it</span>
<span class="g">✓ your store is live at yourshop.com</span></pre>
      </div>
    </div>
  </section>

  <section class="section section--tint" id="products">
    <div class="wrap">
      <div class="center stack">
        <p class="eyebrow">Products</p>
        <h2>Built to be installed, not rented</h2>
        <p class="lede">Each product is a one-time purchase with a licence key, free updates and
          support. Live versions below come straight from our release server.</p>
      </div>

      {{-- Rendered here, by the same call the shop page makes.

           This used to be fetched from the browser, across origins, from
           /api/public/products — an address taken from the plan rather than
           from the routing table, and one that has never existed. So the one
           section that says what this company sells has been apologising for a
           catalogue outage that was not happening.

           Server-side also means the products are in the HTML: no second
           address to keep in step, no cross-origin request to be blocked, and
           something to read for anybody whose JavaScript never runs — search
           engines included. --}}
      <div class="grid grid--2" id="product-grid" style="margin-top:36px">
        @forelse ($catalogue['products'] as $product)
          <article class="product">
            <div class="product-top">
              <h3>{{ $product['name'] }}</h3>
            </div>

            @if (! empty($product['summary']))
              <p>{{ $product['summary'] }}</p>
            @endif

            <p style="margin-top:14px;font-size:1.5rem;font-weight:700">
              ৳{{ number_format($product['price'] / 100) }}
              <span style="font-size:.875rem;font-weight:400;opacity:.7">once</span>

              @if (! empty($product['compare_price']))
                <s style="font-size:1rem;font-weight:400;opacity:.6">৳{{ number_format($product['compare_price'] / 100) }}</s>
                <span style="font-size:.8125rem;font-weight:600;color:var(--brand)">{{ $product['discount'] }}% off</span>
              @endif
            </p>

            <p style="margin-top:4px;opacity:.7;font-size:.9375rem">
              {{ $product['seats'] }} {{ \Illuminate\Support\Str::plural('domain', $product['seats']) }}
              · updates and support included
            </p>

            <p style="margin-top:18px">
              <a class="btn btn--primary" href="{{ route('product', $product['slug']) }}">Buy it</a>
            </p>
          </article>
        @empty
          <article class="product">
            <div class="product-top"><h3>Nothing on sale just yet</h3></div>
            <p>
              @if ($catalogue['ok'])
                Check back shortly, or
              @else
                Our release server is not answering just now —
              @endif
              <a href="{{ route('contact') }}">message us</a> and we will send the details directly.
            </p>
          </article>
        @endforelse
      </div>
    </div>
  </section>

  <section class="section" id="how">
    <div class="wrap">
      <div class="center stack">
        <p class="eyebrow">How it works</p>
        <h2>From payment to live store in an afternoon</h2>
      </div>

      <div class="grid grid--3 steps" style="margin-top:36px">
        <div class="card">
          <div class="step" style="margin-bottom:12px"><span class="step-num">1</span><h3 style="align-self:center">Buy</h3></div>
          <p>Pay with bKash, Nagad or card. A licence key is generated and emailed to you
            immediately — no waiting for anyone to approve it by hand.</p>
        </div>
        <div class="card">
          <div class="step" style="margin-bottom:12px"><span class="step-num">2</span><h3 style="align-self:center">Install</h3></div>
          <p>Upload one small installer to your hosting and enter your key. The installer verifies
            it and downloads the rest — nothing large to upload over a slow connection.</p>
        </div>
        <div class="card">
          <div class="step" style="margin-bottom:12px"><span class="step-num">3</span><h3 style="align-self:center">Sell</h3></div>
          <p>Add products, connect your payment account and open. Updates arrive in your admin
            panel, and you choose when to apply them.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--tint">
    <div class="wrap">
      <div class="center stack">
        <p class="eyebrow">Why self-hosted</p>
        <h2>The difference is who holds your data</h2>
        <p class="lede">Hosted platforms keep your customers, orders and products on their servers,
          and charge every month for the privilege. This runs on yours.</p>
      </div>

      <div class="grid grid--3" style="margin-top:36px">
        <div class="card">
          <div class="card-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 7v5c0 5 3.4 8.3 8 9 4.6-.7 8-4 8-9V7l-8-4Z"/></svg>
          </div>
          <h3>Your data, your server</h3>
          <p>Products, orders and customer records live in your own database. Stop paying us and
            your shop keeps running.</p>
        </div>
        <div class="card">
          <div class="card-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 4.5 4h15L21 9.5M3 9.5h18M5 12v8h14v-8"/></svg>
          </div>
          <h3>Runs on cheap hosting</h3>
          <p>Built for PHP and MySQL on ordinary shared hosting — the kind that costs a few hundred
            taka a month, not a VPS you have to administer.</p>
        </div>
        <div class="card">
          <div class="card-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
          </div>
          <h3>Updates you control</h3>
          <p>New versions appear in your admin with release notes. Your site backs itself up before
            applying one, and rolls back if anything fails.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="pricing">
    <div class="wrap">
      <div class="center stack">
        <p class="eyebrow">Pricing</p>
        <h2>One payment. One store. No renewals.</h2>
      </div>

      {{-- The same number as the Products section above, from the same place.

           It was typed in here as ৳1,050, and by the time anybody noticed, the
           catalogue was charging something else — so this page quoted two
           different prices for one product, about a screen apart. A price
           belongs in the place that charges it. --}}
      @php($cms = collect($catalogue['products'])->firstWhere('slug', 'astralab-cms')
        ?? ($catalogue['products'][0] ?? null))

      <div style="max-width:520px;margin:36px auto 0">
        <div class="price-card">
          <p class="eyebrow">{{ $cms['name'] ?? 'E-commerce CMS' }}</p>

          @if ($cms)
            <p class="price">৳{{ number_format($cms['price'] / 100) }}<small> once</small></p>

            @if (! empty($cms['compare_price']))
              <p style="margin-top:2px">
                <s style="opacity:.6">৳{{ number_format($cms['compare_price'] / 100) }}</s>
                <span style="font-weight:600;color:var(--brand)">{{ $cms['discount'] }}% off</span>
              </p>
            @endif
          @else
            {{-- The hub is not answering. Better to send them to the shop,
                 which will say so plainly, than to invent a figure here. --}}
            <p class="price" style="font-size:1.5rem">Ask us for today's price</p>
          @endif

          <p style="color:var(--ink-2);font-size:.9375rem;margin-top:6px">
            One licence, one live domain. Move it to another domain whenever you like.
          </p>

          <ul class="tick-list">
            <li><span class="tick" aria-hidden="true">✓</span> Full source on your own server</li>
            <li><span class="tick" aria-hidden="true">✓</span> Free updates, including security patches</li>
            <li><span class="tick" aria-hidden="true">✓</span> Bug reports answered from inside your admin panel</li>
            <li><span class="tick" aria-hidden="true">✓</span> Physical and digital products</li>
            <li><span class="tick" aria-hidden="true">✓</span> Transfer your licence to a new domain yourself</li>
          </ul>

          <a class="btn btn--primary btn--lg" href="{{ route('shop') }}" style="margin-top:26px;width:100%">Buy now</a>
          <p style="color:var(--ink-3);font-size:.8125rem;margin-top:12px">
            Payment handled securely by our store. You receive your key straight away.
          </p>
        </div>

        <div class="card" style="margin-top:18px;text-align:center">
          <h3>Want us to set it up?</h3>
          <p style="margin-top:6px">
            Add installation to your order for <strong>৳200</strong> instead of ৳500, and we install
            it on your hosting for you. Product uploads, SEO and ad management are available as
            monthly, 6-month or yearly plans.
          </p>
          <a class="btn btn--ghost" href="{{ route('services') }}" style="margin-top:16px">See support plans</a>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--tint" id="faq">
    <div class="wrap" style="max-width:820px">
      <div class="center stack" style="margin-bottom:28px">
        <p class="eyebrow">FAQ</p>
        <h2>Questions people actually ask</h2>
      </div>

      <div class="faq">
        <details>
          <summary>What hosting do I need?</summary>
          <p>Any shared hosting with PHP 8.3 or newer and MySQL — which is nearly all of it in
            Bangladesh. You do not need a VPS, and you do not need to know how to administer a
            server. If your host blocks outgoing connections, updates will not reach you, so check
            that before buying.</p>
        </details>
        <details>
          <summary>Can I move my store to a different domain later?</summary>
          <p>Yes, and you do it yourself. Deactivate the licence on the old domain from your admin
            panel, then activate it on the new one. There is no charge and no waiting for approval.</p>
        </details>
        <details>
          <summary>What happens if I stop paying?</summary>
          <p>Nothing — there is nothing to stop paying. It is a single purchase. Your store keeps
            running whatever happens to us, because the code and the data are on your server.</p>
        </details>
        <details>
          <summary>Can I use one licence on two websites?</summary>
          <p>No. One licence activates one live domain at a time. A staging copy on a separate
            domain needs its own licence, or you can move the licence back and forth.</p>
        </details>
        <details>
          <summary>Do you take a cut of my sales?</summary>
          <p>No. You connect your own bKash, Nagad or card merchant account, and money goes
            directly to you. We never touch your payments.</p>
        </details>
        <details>
          <summary>What if an update breaks my site?</summary>
          <p>The updater takes a backup before it changes anything and restores it if the update
            fails. You also choose when to update — nothing is applied to your site without you
            pressing the button.</p>
        </details>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <div class="cta">
        <h2>Ready to run your own shop?</h2>
        <p class="lede" style="margin:12px auto 0;color:rgba(255,255,255,.9)">
          Buy once, install this afternoon, and keep it for as long as you want it.
        </p>
        <div class="hero-actions" style="justify-content:center">
          <a class="btn btn--ghost btn--lg" href="{{ route('docs') }}">Read the install guide</a>
          <a class="btn btn--lg" href="{{ route('shop') }}" style="background:#fff;color:var(--brand-dark)">Buy now</a>
        </div>
      </div>
    </div>
  </section>

</main>
@endsection
