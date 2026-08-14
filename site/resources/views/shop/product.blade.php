@extends('layouts.site')

@section('title', 'Buy '.$product['name'])
@section('description', $product['summary'] ?: 'Buy '.$product['name'].' — one payment, bKash or Nagad accepted.')

@section('content')
  <main>
    <section class="wrap page-head">
      <p class="eyebrow">{{ $product['name'] }}</p>
      <h1 style="margin-top:12px">
        ৳{{ number_format($product['price'] / 100) }}, once
        @if (! empty($product['compare_price']))
          <s style="font-size:1.35rem;font-weight:400;color:#6b7280">৳{{ number_format($product['compare_price'] / 100) }}</s>
        @endif
      </h1>

      @if (! empty($product['compare_price']))
        <p style="margin-top:8px;color:#12a06d;font-weight:600">
          Save ৳{{ number_format(($product['compare_price'] - $product['price']) / 100) }}
          — {{ $product['discount'] }}% off
        </p>
      @endif
      <p class="lede" style="margin:18px auto 0">
        {{ $product['summary'] }}
      </p>
    </section>

    <section class="section">
      <div class="wrap" style="max-width:760px">

        @if (session('problem'))
          <div class="card" style="border-color:#e5484d;margin-bottom:18px">
            <p style="color:#c0392b;margin:0">{{ session('problem') }}</p>
          </div>
        @endif

        @if (empty($methods))
          <div class="card">
            <h3>We cannot take payments at the moment</h3>
            <p style="margin-top:6px">
              <a href="{{ route('contact') }}">Message us</a> and we will take
              your order by hand.
            </p>
          </div>
        @else
          <form method="post" action="{{ route('order.place', $product['slug']) }}">
            @csrf

            <div class="card" style="margin-bottom:14px">
              <h3>1 · Send the payment</h3>
              <p style="margin-top:6px">
                Send exactly <b>৳{{ number_format($product['price'] / 100) }}</b>
                to one of these, then come back and fill in the box below.
              </p>

              <div class="grid" style="gap:12px;margin-top:14px">
                @foreach ($methods as $index => $method)
                  <label class="card" style="cursor:pointer;padding:14px">
                    <span style="display:flex;align-items:center;gap:10px">
                      <input type="radio" name="method" value="{{ $method['key'] }}"
                             @checked(old('method', $index === 0 ? $method['key'] : null) === $method['key'])
                             required>
                      <b>{{ $method['label'] }}</b>
                      @if (! empty($method['account']))
                        <span style="font-family:ui-monospace,monospace">{{ $method['account'] }}</span>
                      @endif
                    </span>

                    @if (! empty($method['instructions']))
                      <span style="display:block;margin-top:6px;color:#6b7280;font-size:.9375rem">
                        {{ $method['instructions'] }}
                      </span>
                    @endif
                  </label>
                @endforeach
              </div>

              @error('method')<p style="color:#c0392b;margin-top:8px">{{ $message }}</p>@enderror
            </div>

            <div class="card">
              <h3>2 · Tell us about it</h3>
              <p style="margin-top:6px">
                We check the payment arrived before issuing your key. That is
                usually quick, and you will see it on the next page the moment
                it is done.
              </p>

              <div class="grid" style="gap:12px;margin-top:14px">
                <label>
                  <span>Your name</span>
                  <input name="name" value="{{ old('name') }}" required>
                  @error('name')<p style="color:#c0392b">{{ $message }}</p>@enderror
                </label>

                <label>
                  <span>Email</span>
                  <input type="email" name="email" value="{{ old('email') }}" required>
                  <small>Your key and your receipt go here. Please check it twice.</small>
                  @error('email')<p style="color:#c0392b">{{ $message }}</p>@enderror
                </label>

                <label>
                  <span>Phone</span>
                  <input name="phone" value="{{ old('phone') }}" placeholder="01XXXXXXXXX">
                </label>

                <label>
                  <span>The number you paid from</span>
                  <input name="sender" value="{{ old('sender') }}" placeholder="01XXXXXXXXX">
                </label>

                <label>
                  <span>Transaction number</span>
                  <input name="reference" value="{{ old('reference') }}" placeholder="8H2K9MXQ" required>
                  <small>From your bKash or Nagad confirmation message.</small>
                  @error('reference')<p style="color:#c0392b">{{ $message }}</p>@enderror
                </label>
              </div>

              <p style="margin-top:18px">
                <button class="btn btn-primary" type="submit">Place the order</button>
              </p>
            </div>
          </form>
        @endif

        <section style="margin-top:36px">
          <h2>What customers say</h2>

          @if ($summary['count'])
            <p style="margin-top:6px;color:#6b7280">
              {{ $summary['average'] }} out of 5, from {{ $summary['count'] }}
              {{ \Illuminate\Support\Str::plural('review', $summary['count']) }}.
            </p>
          @else
            <p style="margin-top:6px;color:#6b7280">
              No reviews yet. If you have bought this, yours would be the first.
            </p>
          @endif

          @if (session('ok'))
            <div class="card" style="margin-top:14px"><p style="margin:0">{{ session('ok') }}</p></div>
          @endif

          <div class="grid" style="gap:12px;margin-top:14px">
            @foreach ($reviews as $review)
              <div class="card">
                <p style="margin:0">
                  <b>{{ $review->name }}</b>
                  <span style="color:#6b7280">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                  @if ($review->verified)
                    {{-- The only claim about a review this site can actually
                         check, and therefore the only one it makes. --}}
                    <span style="color:#12a06d;font-size:.8125rem">verified purchase</span>
                  @endif
                </p>
                <p style="margin-top:6px">{{ $review->body }}</p>
                <p style="margin-top:6px;color:#6b7280;font-size:.8125rem">{{ $review->created_at->format('j F Y') }}</p>
              </div>
            @endforeach
          </div>

          <details class="card" style="margin-top:14px">
            <summary style="cursor:pointer"><b>Write a review</b></summary>

            <form method="post" action="{{ route('review', $product['slug']) }}" style="margin-top:14px">
              @csrf

              <div class="grid" style="gap:12px">
                <label>
                  <span>Your name</span>
                  <input name="name" value="{{ old('name') }}" required>
                </label>

                <label>
                  <span>Email</span>
                  <input type="email" name="email" value="{{ old('email') }}">
                  <small>Not shown. Only used to mark your review as a verified purchase.</small>
                </label>

                <label>
                  <span>Rating</span>
                  <select name="rating" required>
                    @foreach ([5, 4, 3, 2, 1] as $score)
                      <option value="{{ $score }}" @selected(old('rating') == $score)>{{ $score }} / 5</option>
                    @endforeach
                  </select>
                </label>

                <label>
                  <span>Your review</span>
                  <textarea name="body" rows="4" required>{{ old('body') }}</textarea>
                  @error('body')<p style="color:#c0392b">{{ $message }}</p>@enderror
                </label>
              </div>

              <p style="margin-top:14px">
                <button class="btn" type="submit">Send it</button>
                <small style="display:block;margin-top:6px;color:#6b7280">
                  Reviews appear once we have read them.
                </small>
              </p>
            </form>
          </details>
        </section>
      </div>
    </section>
  </main>
@endsection
