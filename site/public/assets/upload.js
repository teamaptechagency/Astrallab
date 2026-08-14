/* Sending a large file to shared hosting, a piece at a time.
 *
 * PHP will not take a 44 MB POST — hosting ships with the limit at 2 MB, and a
 * request over it does not arrive truncated, it does not arrive at all. Even
 * raised, one long upload is the sort of request shared hosting cuts off, with
 * nothing to resume.
 *
 * So the file is sliced here and posted in small, short requests. That is the
 * shape this hosting is good at, and it is what keeps the file manager out of
 * the weekly routine.
 *
 * Plain JavaScript on purpose. This console has no build step, because it is
 * uploaded to hosting that has no toolchain to run one.
 */
(function () {
  'use strict';

  var token = document.querySelector('meta[name="csrf-token"]');
  var csrf = token ? token.getAttribute('content') : '';

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      body: body,
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().catch(function () {
        // An HTML error page rather than JSON. Almost always the session
        // having expired, which is worth saying rather than "unexpected".
        throw new Error(response.status === 419
          ? 'Your session expired. Reload the page and sign in again.'
          : 'The server answered with something unreadable (' + response.status + ').');
      }).then(function (data) {
        if (!response.ok || data.ok === false) {
          throw new Error(data.message || 'The upload was refused.');
        }
        return data;
      });
    });
  }

  function attach(form) {
    var input = form.querySelector('input[type="file"]');
    var button = form.querySelector('[data-upload-go]');
    var bar = form.querySelector('[data-upload-bar]');
    var status = form.querySelector('[data-upload-status]');
    var purpose = form.getAttribute('data-upload');

    function say(message, bad) {
      status.textContent = message;
      status.className = bad ? 'error' : 'hint';
    }

    function progress(fraction) {
      bar.style.width = Math.round(fraction * 100) + '%';
    }

    button.addEventListener('click', function () {
      var file = input.files && input.files[0];

      if (!file) {
        say('Choose a file first.', true);
        return;
      }

      button.disabled = true;
      input.disabled = true;
      progress(0);
      say('Starting…');

      var begin = new FormData();
      begin.append('filename', file.name);
      begin.append('purpose', purpose);

      post(form.getAttribute('data-begin'), begin).then(function (started) {
        var size = started.chunk;
        var total = Math.max(1, Math.ceil(file.size / size));
        var index = 0;

        function sendNext() {
          if (index >= total) {
            say('Finishing…');

            var finish = new FormData();
            finish.append('token', started.token);
            finish.append('purpose', purpose);

            return post(form.getAttribute('data-finish'), finish).then(function (done) {
              progress(1);
              say(done.message || 'Done.');

              // Reload rather than patch the page: what changed is on the
              // server — a file in packages/, or the whole application.
              setTimeout(function () { window.location.reload(); }, 1200);
            });
          }

          var slice = file.slice(index * size, (index + 1) * size);

          var body = new FormData();
          body.append('token', started.token);
          body.append('index', index);
          body.append('purpose', purpose);
          body.append('chunk', slice, 'chunk');

          return post(form.getAttribute('data-chunk'), body).then(function () {
            index++;
            progress(index / total);
            say('Uploaded ' + index + ' of ' + total + ' pieces');
            return sendNext();
          });
        }

        return sendNext();
      }).catch(function (error) {
        say(error.message, true);
        button.disabled = false;
        input.disabled = false;
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('[data-upload]');

    for (var i = 0; i < forms.length; i++) {
      attach(forms[i]);
    }
  });
})();
