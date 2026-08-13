@extends('layouts.site')

@section('title', 'Installation guide — Astra Lab')
@section('description', 'Server requirements and step-by-step installation for the Astra Lab e-commerce CMS.')

@section('content')
<main class="wrap prose-page">

  <p class="eyebrow">Documentation</p>
  <h1 style="margin-top:12px">Installing Astra Lab</h1>
  <p class="lede" style="margin-top:16px">
    Most people finish in under half an hour. You do not need to use a terminal, and you do not
    need to know how to administer a server.
  </p>

  <section id="requirements" style="margin-top:52px">
    <h2>Before you start</h2>
    <p class="lede" style="margin-top:12px">Check these with your hosting provider — it takes one
      support message, and it saves buying something that will not run.</p>

    <div class="grid grid--2" style="margin-top:22px">
      <div class="card">
        <h3>Required</h3>
        <ul class="tick-list" style="margin-top:12px">
          <li><span class="tick" aria-hidden="true">✓</span> PHP 8.3 or newer</li>
          <li><span class="tick" aria-hidden="true">✓</span> MySQL 5.7 or MariaDB 10.3+</li>
          <li><span class="tick" aria-hidden="true">✓</span> 256 MB PHP memory limit</li>
          <li><span class="tick" aria-hidden="true">✓</span> Outgoing HTTPS allowed</li>
        </ul>
      </div>
      <div class="card">
        <h3>Strongly recommended</h3>
        <ul class="tick-list" style="margin-top:12px">
          <li><span class="tick" aria-hidden="true">✓</span> An SSL certificate (most hosts give one free)</li>
          <li><span class="tick" aria-hidden="true">✓</span> Daily backups from your host</li>
          <li><span class="tick" aria-hidden="true">✓</span> A domain already pointed at the hosting</li>
        </ul>
      </div>
    </div>

    <div class="card" style="margin-top:18px;border-left:3px solid var(--brand)">
      <h3>The one that catches people out</h3>
      <p style="margin-top:6px">Some cheap hosting blocks outgoing connections. Astra Lab needs
        them to verify your licence and fetch updates. Ask your host: <em>“Are outgoing HTTPS
        requests from PHP allowed?”</em> If the answer is no, choose a different plan before
        buying.</p>
    </div>
  </section>

  <section id="install" style="margin-top:52px">
    <h2>Installing</h2>

    <div class="grid" style="margin-top:22px;gap:14px">
      <div class="card">
        <div class="step"><span class="step-num">1</span>
          <div><h3>Download the installer</h3>
            <p style="margin-top:4px">After paying you get a licence key by email and a small
              installer file from your account page. The installer is only a few hundred kilobytes —
              the rest downloads later, so you are not uploading a large file over a slow line.</p>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="step"><span class="step-num">2</span>
          <div><h3>Upload and extract</h3>
            <p style="margin-top:4px">In cPanel, open File Manager, go to <code>public_html</code>,
              upload the zip and choose Extract.</p>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="step"><span class="step-num">3</span>
          <div><h3>Create a database</h3>
            <p style="margin-top:4px">In cPanel, use MySQL Database Wizard to make a database and a
              user, and give that user all privileges. Write down the four values: database name,
              username, password and host (usually <code>localhost</code>).</p>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="step"><span class="step-num">4</span>
          <div><h3>Open your domain and enter your key</h3>
            <p style="margin-top:4px">Visit your site in a browser. The installer checks your
              server, asks for your licence key, verifies it with us and then downloads the
              application. Keep the tab open while it works.</p>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="step"><span class="step-num">5</span>
          <div><h3>Create your admin account</h3>
            <p style="margin-top:4px">Set your shop name, currency and the first admin login. Then
              delete the installer file when prompted, and you are live.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section style="margin-top:52px">
    <h2>Afterwards</h2>
    <div class="faq" style="margin-top:18px">
      <details open>
        <summary>Moving to a different domain</summary>
        <p>In your admin panel go to Licence and choose Deactivate. That frees the key. Install on
          the new domain and enter the same key. No charge, no waiting.</p>
      </details>
      <details>
        <summary>Applying an update</summary>
        <p>Updates appear in your admin with release notes. Your site takes a backup first, applies
          the update, and restores the backup automatically if anything goes wrong. Security
          updates are marked clearly — apply those promptly.</p>
      </details>
      <details>
        <summary>Reporting a problem</summary>
        <p>Use Report Issue inside your admin panel rather than email. It sends your version and
          server details with the report, which usually saves a day of back-and-forth.</p>
      </details>
      <details>
        <summary>If the installer stops partway</summary>
        <p>Reload the page. The installer resumes from where it stopped rather than starting over —
          shared hosting often cuts off long requests, and it is built to expect that.</p>
      </details>
    </div>
  </section>

  <div class="card" style="margin-top:44px;text-align:center">
    <h3>Still stuck?</h3>
    <p style="margin-top:6px">Send us your hosting details and the step you reached, and we will
      walk you through it in Bangla or English.</p>
    <a class="btn btn--primary" href="{{ route('contact') }}" style="margin-top:16px">Contact support</a>
  </div>

</main>
@endsection
