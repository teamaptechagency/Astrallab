@extends('layouts.site')

@section('title', 'Order '.$order->reference)
@section('description', 'Your Astra Lab order.')

@section('content')
  <main>
    <section class="wrap page-head">
      <p class="eyebrow">Order {{ $order->reference }}</p>

      @if ($order->licence_key)
        <h1 style="margin-top:12px">Here is your licence key</h1>
      @elseif ($order->isPaid())
        <h1 style="margin-top:12px">Payment accepted</h1>
      @else
        <h1 style="margin-top:12px">We are checking your payment</h1>
      @endif
    </section>

    <section class="section">
      <div class="wrap" style="max-width:680px">

        @if ($order->licence_key)
          <div class="card" style="border-color:#12a06d">
            <p style="margin:0;font-family:ui-monospace,monospace;font-size:1.35rem;letter-spacing:.04em;user-select:all">
              {{ $order->licence_key }}
            </p>
            <p style="margin-top:12px">
              Keep it somewhere safe. You will need it when you install, and
              again if you ever move to different hosting.
            </p>
          </div>

          <div class="card" style="margin-top:14px">
            <h3>What now</h3>
            <ol style="margin:10px 0 0 18px;line-height:1.9">
              <li>Download the installer below and upload it to your hosting.</li>
              <li>Open it in your browser and paste the key above.</li>
              <li>Follow the <a href="{{ route('docs') }}">install guide</a> — most
                  people are live the same afternoon.</li>
            </ol>

            {{-- This line used to say "download the installer" with nothing to
                 download: no link, no route, nothing. Anybody who bought was
                 told to fetch a file that was never offered. --}}
            <p style="margin-top:16px">
              <a class="btn btn--primary" href="{{ route('installer', $order->product_slug) }}">
                Download the installer
              </a>
              <span style="display:block;margin-top:8px;opacity:.7;font-size:.9375rem">
                One small PHP file, about 16&nbsp;KB. It fetches the rest itself, so
                there is nothing large to upload over a slow connection.
              </span>
            </p>
          </div>
        @elseif (! $order->isPaid())
          <div class="card">
            <h3>Nothing more to do</h3>
            <p style="margin-top:6px">
              We are confirming that your payment arrived. This is usually quick.
              <b>Keep this page</b> — the key appears here the moment it is done,
              and this address is yours:
            </p>
            <p style="margin-top:10px;font-family:ui-monospace,monospace;user-select:all">
              {{ url()->current() }}
            </p>
            <p style="margin-top:10px">
              Reload in a few minutes. If something looks wrong,
              <a href="{{ route('contact') }}">message us</a> and quote
              <b>{{ $order->reference }}</b>.
            </p>
          </div>
        @else
          {{-- Paid, and the key is not here. Either it is on its way or it was
               collected by a browser that is not this one. Said honestly, with
               the one thing that fixes it. --}}
          <div class="card">
            <h3>Your payment was accepted</h3>
            <p style="margin-top:6px">
              Your key is not showing on this page. Please
              <a href="{{ route('contact') }}">message us</a> quoting
              <b>{{ $order->reference }}</b> and we will send it to
              {{ $order->customer_email }}.
            </p>
          </div>
        @endif

        <div class="card" style="margin-top:14px">
          <h3>Your order</h3>
          <p style="margin-top:6px;color:#6b7280">
            {{ $order->product_name }} · ৳{{ number_format($order->amount / 100) }} ·
            {{ $order->created_at->format('j F Y') }}<br>
            {{ $order->customer_name }} · {{ $order->customer_email }}
          </p>
          <p style="margin-top:10px;color:#6b7280;font-size:.9375rem">
            {{ config('astralab.refund_days') }} days to change your mind — see
            the <a href="{{ route('refund') }}">refund policy</a>.
          </p>
        </div>
      </div>
    </section>
  </main>
@endsection
