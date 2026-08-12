# Putting astralab.com live on Hostinger

Static HTML, CSS and one script. No build step, no Node on the server, nothing
to compile — you upload files and it works. That is the whole reason the site
was written this way, and it means the Premium plan runs it fine.

## What to upload

Everything in this folder **except** the two development files:

| Upload | Skip |
| --- | --- |
| `index.html`, `docs.html`, `services.html`, `404.html` | `serve.mjs` — local preview only |
| `assets/` | `DEPLOY.md`, `README.md` |
| `.htaccess`, `robots.txt`, `sitemap.xml` | |

They go into **`public_html`**, at the top level — not in a subfolder.

`.htaccess` starts with a dot, so hPanel's File Manager hides it by default.
Turn on *Settings → Show hidden files* before you upload, or it will silently
not be there and none of the redirects below will work.

## Steps

1. **hPanel → Files → File Manager**, open `public_html`, and delete Hostinger's
   `default.php` placeholder if it is there.
2. Upload the files above. Uploading a zip and using *Extract* is faster than
   dragging a folder, and does not drop files on a slow connection.
3. **hPanel → Security → SSL**, install the free certificate for the domain and
   turn on *Force HTTPS*. The `.htaccess` also forces it, so this is a belt and
   braces — but Hostinger's own switch handles the edge cases at their proxy.
4. Open the domain. You should land on the home page.

## Check these four things before telling anyone it is live

| Address | Should do |
| --- | --- |
| `http://astralab.com` | redirect to `https://` |
| `https://www.astralab.com` | redirect to `https://astralab.com` |
| `https://astralab.com/docs.html` | redirect to `/docs` |
| `https://astralab.com/nonsense` | show the styled 404, not Hostinger's |

If the last two do nothing, `.htaccess` did not upload — see the hidden files
note above. Everything still renders; the URLs are just untidy.

## The product grid

The homepage fills its Products section from the hub's public catalogue at
`https://manage.astralab.com/api/public/products`. The address is worked out
from the address bar: a real domain talks to the real hub, `localhost` talks to
`localhost:3200`. There is nothing to configure.

**Until the hub is live, that section will show its "could not reach us"
message with a contact link.** That is the same thing it does during an outage,
and it is deliberate — a permanent loading shimmer reads as "this whole site is
broken". The rest of the page is unaffected.

## What is still missing

These are linked from the site and do not exist yet. Every one of them is a
404 today:

- `/shop/` — every **Buy now** button. WooCommerce.
- `/contact/` — the support link at the bottom of the docs page.
- `/terms/`, `/privacy/`, `/refund/` — linked in the footer.

`/shop/` is the one that matters: it is on nine buttons, and a visitor who
reaches the end of the page and clicks it is the visitor you most wanted.
Either get WooCommerce up before announcing the site, or point those links at
`/contact/` in the meantime.

## Search engines

After the domain is serving:

1. Google Search Console → add the property → verify by DNS record.
2. Submit `https://astralab.com/sitemap.xml`.

`robots.txt` already names the sitemap, so this only speeds things up.

## Sharing on Facebook and WhatsApp

Every page carries Open Graph tags, which is what those apps read. They all
point at `https://astralab.com/assets/share.png` — **a file that does not exist
yet.** Until it does, shares show a bare link with no picture. It wants to be
1200×630 and legible at thumbnail size; drop it in `assets/` and nothing else
needs changing.

## Changing something later

Edit the file, upload it, done. Assets are cached for a week by `.htaccess`, so
after a CSS change either wait, or rename the file and update the three `<link>`
tags. HTML is set to revalidate every time, so text changes appear immediately.
