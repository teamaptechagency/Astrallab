@extends('admin.layout')

@section('title', 'Settings')

@section('content')
  <p class="eyebrow">Settings</p>
  <h1 style="margin-top:10px">Your details</h1>
  <p class="lede" style="margin-top:14px">
    Everything here shows on the public pages. Leave a box empty and it is left
    off rather than shown blank.
  </p>

  <form method="post" action="/apt-admin/settings" style="margin-top:34px">
    @csrf

    @foreach ($groups as $group => $fields)
      <section style="margin-bottom:38px">
        <h2 style="font-size:1.125rem;margin-bottom:18px">{{ $group }}</h2>

        @foreach ($fields as $key => $field)
          @php($name = str_replace('.', '_', $key))

          <div class="field-row">
            <label for="{{ $name }}">{{ $field['label'] }}</label>
            @isset($field['hint'])
              <span class="hint">{{ $field['hint'] }}</span>
            @endisset

            @if ($field['secret'] ?? false)
              {{-- Never rendered back into the page. The box arrives empty, and
                   leaving it empty keeps whatever is already saved — a secret
                   on a screen is a secret in a screenshot. --}}
              <input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="off"
                     placeholder="{{ ($secrets[$key] ?? false) ? 'Set — leave empty to keep it' : 'Not set yet' }}">
            @elseif (($field['type'] ?? 'text') === 'textarea')
              <textarea id="{{ $name }}" name="{{ $name }}">{{ old($name, $values[$key]) }}</textarea>
            @else
              <input id="{{ $name }}" name="{{ $name }}" type="{{ $field['type'] ?? 'text' }}"
                     value="{{ old($name, $values[$key]) }}">
            @endif

            @error($name) <span class="error">{{ $message }}</span> @enderror
          </div>
        @endforeach
      </section>
    @endforeach

    <button class="btn btn--primary btn--lg" type="submit">Save</button>
  </form>

  {{-- Asked of the hub itself rather than inferred from the two boxes above.
       "Saved" only ever meant the values were written down; this is the only
       thing that answers whether they are right. --}}
  <section style="margin-top:44px;padding-top:28px;border-top:1px solid #e5e7eb">
    <h2 style="font-size:1.125rem">Is the hub answering?</h2>

    @if (session('hub'))
      @php($hub = session('hub'))

      <div class="card" style="margin-top:14px;border-color:{{ $hub['ok'] ? '#12a06d' : '#e5484d' }}">
        <p style="margin:0"><b>{{ $hub['headline'] }}</b></p>
        <p style="margin-top:6px">{{ $hub['detail'] }}</p>
      </div>
    @else
      <p class="lede" style="margin-top:10px">
        Checks that the address is reachable, that the secret is accepted, and
        that there is something priced to sell.
      </p>
    @endif

    <form method="post" action="/apt-admin/settings/test" style="margin-top:16px">
      @csrf
      <button class="btn" type="submit">Test the connection</button>
    </form>
  </section>
@endsection
