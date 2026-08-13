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

            @if (($field['type'] ?? 'text') === 'textarea')
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
@endsection
