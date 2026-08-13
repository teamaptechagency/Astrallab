@extends('layouts.site')

@section('title', 'Buy Astra Lab')
@section('description', 'How to buy Astra Lab today: send us a message and we will send you a licence key and the installer.')

@section('content')
  <main>
    <section class="wrap page-head">
      <p class="eyebrow">Buying</p>
      <h1 style="margin-top:12px">Checkout is not open yet</h1>
      <p class="lede" style="margin:18px auto 0">
        Automatic card and bKash checkout is being built. Until it is, buying
        takes one message — which is how most shops here buy software anyway.
      </p>
    </section>

    <section class="section">
      <div class="wrap" style="max-width:760px">

        <div class="grid" style="gap:14px">
          <div class="card">
            <div class="step"><span class="step-num">1</span>
              <div>
                <h3>Tell us what you want</h3>
                <p style="margin-top:4px">Message us with your domain name and
                  whether you want installation included. We will confirm the
                  price and how to pay — bKash, Nagad or bank transfer.</p>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="step"><span class="step-num">2</span>
              <div>
                <h3>Pay once</h3>
                <p style="margin-top:4px">No monthly fee, ever. The price on
                  the <a href="{{ route('home') }}#pricing">pricing section</a>
                  is the whole of it.</p>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="step"><span class="step-num">3</span>
              <div>
                <h3>We send your licence key</h3>
                <p style="margin-top:4px">With the installer and the
                  <a href="{{ route('docs') }}">install guide</a>. Most people
                  are live the same afternoon. Or add installation and we do it
                  for you.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="hero-actions" style="justify-content:center;margin-top:32px">
          <a class="btn btn--primary btn--lg" href="{{ route('contact') }}">Message us to buy</a>
          <a class="btn btn--ghost btn--lg" href="{{ route('home') }}#pricing">See the price</a>
        </div>

        {{-- Stated because somebody about to send money to a small company they
             found online is entitled to know what happens if it goes wrong. --}}
        <p class="hero-note" style="margin-top:26px;text-align:center">
          {{ config('astralab.refund_days') }}-day refund if it will not run on
          your hosting — see <a href="{{ route('refund') }}">refunds</a>.
        </p>

      </div>
    </section>
  </main>
@endsection
