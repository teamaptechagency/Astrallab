# astralab.com — storefront

The public-facing site: what Astra Lab sells, how it installs, what it costs.

```bash
node website/serve.mjs      # http://localhost:3300
```

No build step and no dependencies. Plain HTML, one stylesheet, one script — so
editing a headline is editing a headline, not running a toolchain.

## What is live vs static

Everything is static **except the product grid**, which fetches
`GET /api/public/products` from `manage.astralab` on page load.

That matters: version numbers, download sizes and whether something is on sale
are never stale copy that someone forgot to update after a release. Publish a
release in the hub and this page reflects it on the next visit.

`Astra Lab Portfolio` currently renders as *Coming soon* purely because it has
no published release — nothing here says so.

If the hub is unreachable the grid shows a short apology and a contact link.
It never leaves loading placeholders on screen: a permanent shimmer reads as
"this whole site is broken" and loses the visitor.

## Pointing it at the real hub

The script defaults to `http://localhost:3200`. In production, set the hub
before loading it:

```html
<script>window.ASTRALAB_HUB = "https://manage.astralab.com";</script>
<script src="/assets/site.js"></script>
```

The hub's catalogue endpoint already sends `Access-Control-Allow-Origin: *`,
so no server-side proxy is needed.

## Turning this into the WordPress theme

astralab.com runs WordPress + WooCommerce, because that is where bKash and
Nagad gateways exist. This is the design and the copy; moving it across is
mechanical:

| This file | Becomes |
| --- | --- |
| `index.html` header | `header.php` |
| `index.html` footer | `footer.php` |
| `index.html` body | `front-page.php` |
| `docs.html` | a WordPress page using a `page-docs.php` template |
| `assets/` | the theme's `assets/`, enqueued in `functions.php` |

Two things to keep when porting:

**`/shop/` links go to WooCommerce.** Every "Buy now" points there rather than
to a hard-coded product URL, so the shop page stays the single place a
purchase begins.

**The product grid stays a client-side fetch.** Rendering it in PHP would mean
the hub being briefly slow makes every page load slow, and a hub outage would
take the whole storefront down instead of one section of it.

## Not built yet

- `/shop/`, `/contact/`, `/terms/`, `/privacy/`, `/refund/` — WooCommerce and
  WordPress pages, created in the admin rather than here
- Screenshots of the CMS admin for the product section
- Bangla translation. The copy is written to translate cleanly, but is
  English-only today
