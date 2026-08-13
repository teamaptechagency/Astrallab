@extends('layouts.site')

@section('title', 'Contact us — Astra Lab')
@section('description', 'Reach the Astra Lab team about buying, installing or supporting your shop. We answer in Bangla and English.')

@section('content')
  <main>
    <section class="wrap page-head">
      <p class="eyebrow">Contact</p>
      <h1 style="margin-top:12px">Talk to a person</h1>
      <p class="lede" style="margin:18px auto 0">
        {{ config('astralab.contact.hours') }}. We answer in Bangla or English,
        whichever you write in.
      </p>
    </section>

    <section class="section">
      <div class="wrap" style="max-width:900px">
        <div class="grid grid--2">

          @if ($whatsapp = config('astralab.contact.whatsapp'))
            {{-- First, and deliberately. Nearly every message about a shop in
                 Bangladesh arrives this way, and asking somebody to write an
                 email instead is asking them not to write at all. --}}
            <div class="card">
              <h3>WhatsApp</h3>
              <p style="margin-top:6px">The quickest way to reach us, and the
                easiest for sending a screenshot of whatever is wrong.</p>
              <a class="btn btn--primary" style="margin-top:16px"
                 href="https://wa.me/{{ $whatsapp }}">Message us</a>
            </div>
          @endif

          @if ($email = config('astralab.contact.email'))
            <div class="card">
              <h3>Email</h3>
              <p style="margin-top:6px">Best for anything with detail — hosting
                credentials, a licence question, an invoice.</p>
              <a class="btn btn--ghost" style="margin-top:16px"
                 href="mailto:{{ $email }}">{{ $email }}</a>
            </div>
          @endif

          @if ($phone = config('astralab.contact.phone'))
            <div class="card">
              <h3>Phone</h3>
              <p style="margin-top:6px">{{ config('astralab.contact.hours') }}.</p>
              <a class="btn btn--ghost" style="margin-top:16px"
                 href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>
            </div>
          @endif

          <div class="card">
            <h3>Already have a shop?</h3>
            <p style="margin-top:6px">Use <strong>Report a problem</strong> inside
              your own admin panel instead. It sends your version and server
              details with the message, which usually saves a day of asking
              what they are.</p>
          </div>

        </div>

        @if ($address = config('astralab.contact.address'))
          <div class="card" style="margin-top:18px">
            <h3>Where we are</h3>
            <p style="margin-top:6px">{{ $address }}</p>
          </div>
        @endif

        {{-- Said plainly rather than left for somebody to discover after they
             have paid and are waiting for a reply at 11pm. --}}
        <p class="hero-note" style="margin-top:26px;text-align:center">
          We are a small team. Outside the hours above, a message will be read
          the next working morning.
        </p>
      </div>
    </section>
  </main>
@endsection
