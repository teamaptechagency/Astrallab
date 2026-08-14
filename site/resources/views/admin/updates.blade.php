@extends('admin.layout')

@section('title', 'Updates')

@section('content')
  <p class="eyebrow">Updates</p>
  <h1 style="margin-top:10px">Bug fixes and new features</h1>
  <p class="lede" style="margin-top:14px">
    Build {{ $version }}. There is no git pull on this hosting — this screen is
    what replaces it.
  </p>

  @if ($pending)
    {{-- First, and unmissable. Files that arrived without their tables are
         invisible until a page touches one, and by then it is a 500 with
         nothing to read. --}}
    <div class="card" style="margin-top:30px;border-color:var(--brand)">
      <h3>{{ count($pending) }} database {{ \Illuminate\Support\Str::plural('change', count($pending)) }} waiting</h3>
      <p style="margin-top:6px">
        A newer build is in place and its tables have not been created yet.
        Parts of the site will not work until they are. This is the step that
        changes data rather than files — if you keep backups, take one first.
      </p>

      <ul style="margin:12px 0 0 18px;line-height:1.9">
        @foreach ($pending as $migration)
          <li><code>{{ $migration }}</code></li>
        @endforeach
      </ul>

      <form method="post" action="/apt-admin/updates" style="margin-top:18px">
        @csrf
        <button class="btn btn--primary" type="submit">Apply them</button>
      </form>
    </div>
  @endif

  <div class="card" style="margin-top:22px">
    <h3>Install a new build</h3>
    <p style="margin-top:6px">
      Replaces this site's own files. Your settings, your orders and your
      reviews are in the database and are not touched — whatever a build changes
      there is applied separately, above.
    </p>

    @include('partials.uploader', [
      'purpose' => 'build',
      'label' => 'Build archive',
      'hint' => 'Sent in pieces of '.number_format($chunk / 1024).' KB, so the '.number_format($limit / 1048576, $limit < 1048576 ? 1 : 0).' MB this server accepts in one request does not matter.',
    ])

    <p style="margin-top:14px;color:#9ca3af;font-size:.9375rem">
      The site closes for a few seconds while the files are swapped.
      @if ($installed)
        Last installed: <b>{{ $installed }}</b>.
      @endif
    </p>
  </div>

  <h2 style="margin-top:38px;font-size:1.125rem">What has been done</h2>

  @if ($log->isEmpty())
    <div class="card" style="margin-top:14px">
      <p style="margin:0">
        Nothing yet. Installing a build or applying database changes is written
        down here — what, when, and by whom.
      </p>
    </div>
  @else
    {{-- Asked in the past tense, weeks later, by somebody looking at behaviour
         nobody expected. File modification times cannot answer it: updating
         rewrites them by definition. --}}
    <div class="card" style="margin-top:14px;padding:0;overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.9375rem">
        <thead>
          <tr style="text-align:left">
            <th style="padding:12px 16px">When</th>
            <th style="padding:12px 16px">What</th>
            <th style="padding:12px 16px">Summary</th>
            <th style="padding:12px 16px">By</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($log as $entry)
            <tr style="border-top:1px solid rgba(255,255,255,.08)">
              <td style="padding:12px 16px;white-space:nowrap">{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
              <td style="padding:12px 16px">{{ $entry->kind }}</td>
              <td style="padding:12px 16px">
                {{ $entry->summary }}
                @if ($entry->detail)
                  <span style="display:block;color:#9ca3af;font-size:.8125rem;white-space:pre-line">{{ $entry->detail }}</span>
                @endif
              </td>
              <td style="padding:12px 16px">{{ $entry->actor ?: '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
