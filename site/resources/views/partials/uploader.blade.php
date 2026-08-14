{{-- A large file, sent a piece at a time by upload.js.

     Not a <form>. There is nothing to submit — the browser posts the pieces
     itself — and a real form would offer a fallback that cannot work: the
     whole reason this exists is that one POST of this size does not arrive.

     Needs: $purpose ('package' or 'build'), $label, $hint. --}}

<div data-upload="{{ $purpose }}"
     data-begin="/apt-admin/uploads/begin"
     data-chunk="/apt-admin/uploads/chunk"
     data-finish="/apt-admin/uploads/finish">

  <div class="field-row" style="margin-top:16px">
    <span>{{ $label }}</span>
    <input class="field" type="file" accept=".zip,application/zip">
    <span class="hint">{{ $hint }}</span>
  </div>

  <div class="upload-bar" aria-hidden="true"><i data-upload-bar></i></div>

  <div style="display:flex;align-items:center;gap:14px;margin-top:10px">
    <button class="btn btn--primary" type="button" data-upload-go>Upload</button>
    <span class="hint" data-upload-status></span>
  </div>

  <noscript>
    <p class="error" style="margin-top:10px">
      This needs JavaScript. Without it, put the file in place through your
      hosting file manager instead.
    </p>
  </noscript>
</div>
