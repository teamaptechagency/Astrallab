# astrallabs.uk on Vercel

The shop can run on Vercel. The hub cannot, and should not be moved there —
see the last section.

**Not tested from here.** The files in this repository were checked locally by
serving every request through `api/index.php`, which is how Vercel serves a PHP
project, and the pages render. Nobody has yet run an actual deployment. Expect
to fix one or two things on the first try, and read "If it fails" below before
concluding it is broken.

## Import settings

Two things must be set on the Vercel project or it will not build:

- **Root Directory: `site`.** The repository holds `site/`, `website/` and
  `woocommerce-plugin/`. Vercel has to be told which one.
- **Production Branch: `master`.** Vercel looks for `main` by default, and this
  repository does not have one.

**No comments in `vercel.json`.** JSON has none, and Vercel rejects unknown
properties outright — a `"//"` key used to explain a setting fails the whole
deployment with *"should NOT have additional property"*. Anything worth saying
about that file is said here instead.
**Root Directory is not optional.** This runtime requires `composer.json` to sit
beside `vercel.json`, and both are in `site/`. If Vercel's project root is the
repository root it finds neither, falls back to guessing, builds nothing, and
reports *"No Output Directory named public found"*. That error means this file
was never read — it is almost never a problem with this file's contents.

If the first import already saved Build settings, clear them too:
**Settings -> Build and Deployment** -> Framework Preset **Other**, and leave
Build Command and Output Directory empty (Override off).

`vendor/` is not in the repository, so the build runs `composer install`
itself. That triggers Laravel's `package:discover`, which boots the
application — which is why `storage/` is deliberately not in `.vercelignore`.
Only the empty directory skeleton ships; nothing writes to it at runtime.

## Before you start

Vercel deploys from a Git repository. This code is not on GitHub yet — create a
**private** repository (it is the whole source of a product you sell), push, and
import it into Vercel.

You also need a database Vercel can reach. The shop keeps orders, reviews,
sessions, cache and the queue in it. Any hosted MySQL or Postgres works —
PlanetScale, Neon, Railway, or a MySQL on your existing Hostinger plan if it
allows remote connections.

## Environment variables

Set these in the Vercel project's Settings → Environment Variables. They replace
`.env`, which is not deployed and could not be written there anyway.

| Variable | Value |
|---|---|
| `APP_KEY` | `base64:…` — run `php artisan key:generate --show` locally and paste it |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://astrallabs.uk` |
| `ASTRALAB_INSTALLED` | `true` — **required**, see below |
| `LOG_CHANNEL` | `stderr` — **required**, there is nowhere to write a log file |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `DB_CONNECTION` | `mysql` or `pgsql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | your hosted database |
| `ASTRALAB_HUB_URL` | `https://manage.astrallabs.uk` |
| `STORE_API_SECRET` | the store secret from the hub |
| the `ASTRALAB_*` contact and company values | as in `.env.example` |

Run the migrations once against that database from your own machine, with the
same `DB_*` values in your local `.env`:

```bash
php artisan migrate --force
```

There is no way to run them from Vercel, and no reason to want one.

## Why `ASTRALAB_INSTALLED` is required

The site decides between the shop and the setup wizard by looking for a file the
installer wrote. Nothing on Vercel can write that file, so without this every
visitor is redirected into a wizard that cannot finish — the whole site, one
redirect loop. Setting it says "the settings arrive as environment variables,
there is nothing to install".

It is off by default on purpose. A site that wrongly believes it is installed
shows a shop with no settings behind it, which is harder to diagnose than being
offered a wizard you did not need.

## What does not work up there

Everything a customer touches works: the front page, the catalogue, product
pages, placing an order, the order page, the licence key, reviews, and the
installer download. That is the shop.

These do not, because they all write to the application's own files, and a
Vercel deployment is immutable:

- **The setup wizard** (`/install`) — cannot write `.env`. Use the environment
  variables above.
- **Self-update** — cannot replace its own files. A new version reaches Vercel
  by being deployed, which is the platform's whole model.
- **Uploading a build** in the console — a 44 MB upload arrives in pieces across
  several requests, and each request is a separate invocation with its own empty
  `/tmp`. The pieces never meet.

If you need those, that copy of the site belongs on ordinary hosting.

## If it fails

- **500 on every page** — almost always `APP_KEY` missing, or the database
  unreachable. Check the function logs in the Vercel dashboard; `LOG_CHANNEL=stderr`
  is what puts Laravel's own errors there.
- **Every request redirects to `/install`** — `ASTRALAB_INSTALLED` is not set.
- **Styles missing** — the routes in `vercel.json` serve `/assets/*` from
  `public/`. If you add another static folder, add a route for it too.
- **`vercel-php` runtime not found** — the version pinned in `vercel.json`
  (`vercel-php@0.9.0`) may have moved on. The runtime is community-maintained,
  not Vercel's own: https://github.com/vercel-community/php
- **"No Output Directory named public found"** — `vercel.json` is not being
  read at all. The Root Directory is not `site`, or a Build Command or Output
  Directory saved from an earlier import is overriding it. This error is about
  where Vercel is looking, never about what is in the file.
- **"does not contain the requested branch"** — Production Branch is still
  `main`. This repository uses `master`.
- **"should NOT have additional property"** — something in `vercel.json` is not
  a key Vercel knows. It validates the file strictly; there are no comments and
  no spare keys.

## Why the hub stays on Hostinger

`manage.astrallabs.uk` writes to its own filesystem in seven places, and the
three features it exists for are all of them: applying an update, receiving a
build in chunks, and serving 44 MB packages from `packages/`. None survives a
read-only, per-invocation filesystem. Moving it to Vercel would produce a
console that deploys cleanly and fails at the three things it is for.
