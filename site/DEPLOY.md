# Putting astralab.com live on Hostinger

One Laravel application: the public site, the operator console at `/apt-admin`,
and the API that customer shops call. Premium shared hosting runs it — it is
PHP and MySQL and nothing else.

## The one thing that goes wrong

Laravel serves from `public/`. cPanel serves from `public_html`. Nearly every
failed Laravel install on shared hosting is that mismatch, usually "fixed" by
dumping the whole application into `public_html` — which puts `.env`, with the
database password in it, one URL away from anybody who guesses.

The archive is already split so this cannot happen:

```
astralab-app/     the application — outside the web root, unreachable by URL
public_html/      the front controller, .htaccess and assets
```

`public_html/index.php` already points across at `astralab-app/`. Nothing to
edit.

## Build it

```bash
cd "D:/AP Tech Server/Astralab/site"
php artisan astralab:package
```

Produces `astralab-site.zip`, about 34 MB. It includes `vendor/` because shared
hosting has no Composer, and deliberately excludes `.env` — that is written on
the server, once.

## Upload

1. **hPanel → Advanced → PHP Configuration**, set **PHP 8.3**. The application
   will not run on anything older, and Hostinger often defaults an account to
   an older one.
2. **hPanel → Files → File Manager.** Go **up one level from `public_html`**,
   to the folder that *contains* it. This is your account home — usually
   `/home/uXXXXXXXXX/`. **Not inside `public_html`.**
3. Upload `astralab-site.zip` there and choose **Extract**.

   You should end up with `astralab-app/` sitting beside `public_html/`, and
   `public_html/index.php` newly overwritten.

4. Turn on **Settings → Show hidden files** in File Manager. You need it for
   the next step, and for checking `.htaccess` arrived.

## Create `.env`

In File Manager, inside **`astralab-app/`**, create a file named `.env` with:

```ini
APP_NAME="Astra Lab"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://astralab.com

LOG_CHANNEL=stack
LOG_LEVEL=error

# Sessions and cache as files. There is no database yet, and on this hosting a
# file is quicker than a query anyway.
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# How people reach you. Anything blank is left off the contact page rather
# than shown empty. WhatsApp is digits only, country code first, no plus.
ASTRALAB_EMAIL=
ASTRALAB_PHONE=
ASTRALAB_WHATSAPP=
ASTRALAB_ADDRESS=

# Named on the terms, privacy and refund pages.
ASTRALAB_COMPANY="Astra Lab"
ASTRALAB_TRADE_LICENCE=
ASTRALAB_REFUND_DAYS=7
```

**Fill in at least one of `ASTRALAB_EMAIL` or `ASTRALAB_WHATSAPP`.** With all
three blank, the contact page has nothing on it but a note about the admin
panel — which is worse than no contact page at all.

`APP_KEY` must be filled in or every page returns a 500. Generate one on your
own machine and paste the whole `base64:...` string in:

```bash
cd "D:/AP Tech Server/Astralab/site" && php artisan key:generate --show
```

Do not send that key over chat or email. It signs every session cookie.

`APP_DEBUG=false` matters. Left on, a stack trace on any error shows your
database credentials to whoever triggered it.

The `DB_*` values stay blank until there is a database to point at — nothing
uses one yet.

## Permissions

The application writes to two directories. In File Manager, right-click each
and set permissions to **755** (or **775** if the host is strict), with
*apply to subdirectories* ticked:

- `astralab-app/storage`
- `astralab-app/bootstrap/cache`

A white blank page after everything else is right is almost always this.

## SSL

**hPanel → Security → SSL**, install the free certificate and turn on *Force
HTTPS*. The `.htaccess` also redirects, so this is belt and braces.

## Check it

| Address | Should |
| --- | --- |
| `https://astralab.com` | show the home page |
| `https://astralab.com/docs` | the install guide |
| `https://astralab.com/services` | the support plans |
| `https://astralab.com/apt-admin` | a placeholder — the console is not built |
| `http://astralab.com` | redirect to `https://` |
| `https://www.astralab.com` | redirect to the bare domain |
| `https://astralab.com/.env` | **404 or 403 — never the file** |

That last row is the one to check twice. If it downloads a file, stop and tell
me: it means the application went into `public_html` rather than beside it.

## If it does not work

**Hostinger's "This Page Does Not Exist" page** — the skateboarder. That is
their error page, not yours, and it means nothing of yours is being served.
Check the folder name in the File Manager breadcrumb is exactly
`public_html`. A folder called `public_htm` looks identical at a glance and
serves nothing. If the domain is a second domain on the account rather than the
primary one, its root is `domains/astralab.com/public_html/` instead.

**A blank white page** — permissions on `storage`, above. Failing that, read
`astralab-app/storage/logs/laravel.log` in File Manager; the error is at the
bottom.

**500 on every page** — `APP_KEY` is empty.

**The page loads but has no styling** — `public_html/assets/` did not extract.
Check it is a folder, not a file called `assets\styles.css`.

## Updating later

Rebuild the zip, upload, extract, overwrite. `.env` is not in the archive, so
your settings survive. `astralab-app/storage` is only created when missing, so
logs and sessions survive too.
