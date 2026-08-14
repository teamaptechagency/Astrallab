@extends('layouts.site')

@section('title', 'Buy Astra Lab')
@section('description', 'Buy Astra Lab and get your licence key. Pay with bKash or Nagad — one payment, no monthly fee.')

@section('content')
  <main>
    <section class="wrap page-head">
      <p class="eyebrow">Buying</p>
      <h1 style="margin-top:12px">One payment. Yours to keep.</h1>
      <p class="lede" style="margin:18px auto 0">
        Pay with bKash or Nagad, and your licence key arrives on the next screen.
        No monthly fee, ever.
      </p>
    </section>

    <section class="section">
      <div class="wrap" style="max-width:900px">

        @if (! $catalogue['ok'])
          {{-- Said plainly. The hub being unreachable is not the visitor's
               problem to work out, and a page of empty product cards would
               leave them thinking the company had closed. --}}
          <div class="card">
            <h3>We cannot show prices at the moment</h3>
            <p style="margin-top:6px">
              Our order system is not answering. It is being looked at. Please
              try again shortly, or
              <a href="{{ route('contact') }}">message us</a> and we will take
              your order by hand.
            </p>
          </div>
        @elseif (empty($catalogue['products']))
          <div class="card">
            <h3>Nothing is on sale just yet</h3>
            <p style="margin-top:6px">
              <a href="{{ route('contact') }}">Message us</a> — we will tell you
              the moment it is.
            </p>
          </div>
        @else
          <div class="grid" style="gap:16px">
            @foreach ($catalogue['products'] as $product)
              @php($rating = $ratings[$product['slug']] ?? ['average' => null, 'count' => 0])

              <div class="card">
                <h3>{{ $product['name'] }}</h3>

                @if (! empty($product['summary']))
                  <p style="margin-top:6px">{{ $product['summary'] }}</p>
                @endif

                <p style="margin-top:14px;font-size:1.6rem;font-weight:700">
                  ৳{{ number_format($product['price'] / 100) }}
                  <span style="font-size:.9375rem;font-weight:400;color:#6b7280">once</span>
                </p>

                <p style="margin-top:4px;color:#6b7280;font-size:.9375rem">
                  {{ $product['seats'] }}
                  {{ \Illuminate\Support\Str::plural('domain', $product['seats']) }} ·
                  updates and support included

                  @if ($rating['count'])
                    {{-- The count, always. An average with nothing behind it is
                         how "5.0" comes to mean one review from a friend. --}}
                    · {{ $rating['average'] }}/5 from {{ $rating['count'] }}
                    {{ \Illuminate\Support\Str::plural('review', $rating['count']) }}
                  @endif
                </p>

                <p style="margin-top:16px">
                  <a class="btn btn-primary" href="{{ route('product', $product['slug']) }}">
                    Buy {{ $product['name'] }}
                  </a>
                </p>
              </div>
            @endforeach
          </div>
        @endif

        <p style="margin-top:22px;color:#6b7280;font-size:.9375rem">
          Every licence includes {{ config('astralab.refund_days') }} days to
          change your mind — see the <a href="{{ route('refund') }}">refund
          policy</a>.
        </p>
      </div>
    </section>
  </main>
@endsection
