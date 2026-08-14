{{-- Shown for the few seconds a build is being swapped in.

     A bare "503 Service Unavailable" on a white page tells whoever is looking
     at it nothing — least of all whether they have broken something. This says
     what is happening and roughly how long, which is the whole difference
     between waiting and panicking. --}}

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Updating — Astra Lab</title>
<meta name="robots" content="noindex, nofollow">
{{-- Refreshes itself, so nobody sits on a dead page wondering. The swap takes
     seconds; this brings them back the moment it is done. --}}
<meta http-equiv="refresh" content="15">
<style>
  :root { --brand: #12a06d; --ink: #161a26; --ink-2: #4c5468; --line: #e4e7ee; }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
    font: 15px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: var(--ink); background: #f7f8fa;
  }
  .card {
    background: #fff; border: 1px solid var(--line); border-radius: 12px;
    padding: 30px; max-width: 460px; width: 100%;
  }
  .mark {
    width: 34px; height: 34px; border-radius: 10px; background: var(--brand);
    color: #fff; display: grid; place-items: center; font-weight: 700; margin-bottom: 18px;
  }
  h1 { margin: 0; font-size: 1.25rem; letter-spacing: -0.015em; }
  p { margin: 10px 0 0; color: var(--ink-2); }
  .quiet { font-size: .8125rem; color: #8b93a7; margin-top: 18px; }
</style>
</head>
<body>
  <div class="card">
    <div class="mark" aria-hidden="true">A</div>

    <h1>Updating</h1>

    <p>
      A new build is being put in place. This takes a few seconds, and the page
      will come back on its own.
    </p>

    <p class="quiet">
      Installed shops calling in during this get a "try again shortly" and do
      exactly that — nothing is lost. If this page is still here after a few
      minutes, the update did not finish; see the note in DEPLOY.md about
      removing the maintenance file.
    </p>
  </div>
</body>
</html>
