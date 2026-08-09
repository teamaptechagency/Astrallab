# astralab.com — store setup

The store is mostly configuration. The only code is the `astralab-licence`
plugin in this folder, which turns a paid order into a licence key from
`manage.astralab`.

Work through this in order. Steps 1–4 are done in a browser, not in code.

## 1. Hosting and WordPress

Any host running PHP 8.1+ and MySQL 5.7+ will do. Most Bangladeshi hosts offer
one-click WordPress.

Requirements that actually matter here:

- **HTTPS.** Licence keys travel in the response body from the hub and are
  shown on the thank-you page. Non-negotiable.
- **Outbound HTTPS allowed.** Some cheap shared hosts block outgoing requests,
  which would silently break every licence issue. Confirm before committing.
- **Working cron.** The plugin retries failed issues via `wp_schedule_single_event`.
  If WP-Cron is disabled, set a real system cron hitting `wp-cron.php`.

## 2. WooCommerce

Install the WooCommerce plugin, run its setup wizard, and set:

- Currency: **BDT**
- Selling location: worldwide (buyers outside Bangladesh are expected)
- Shipping: **disable it entirely** — nothing physical ships
- Taxes: off unless your accountant says otherwise

## 3. Payment

Add bKash and Nagad via a payment gateway plugin, plus a card gateway if you
want international buyers.

One thing to get right: the plugin issues a licence on `processing` or
`completed`. Make sure your gateway moves orders to `processing` **only after
payment is confirmed**, not on order creation. A gateway that marks orders
processing optimistically will hand out free licences.

Do not enable Cash on Delivery. There is nothing to deliver, and it would
create paid-status orders with no money behind them.

## 4. The product

Create one **Simple product**, and tick **Virtual** (this suppresses shipping).

- Price: 1,050 BDT (or whatever you settle on)
- Do **not** attach a downloadable file. The installer is fetched from the hub
  after licence validation — that is the entire point of the model.
- In **Product data → General**, set **Astra Lab product slug** to
  `astralab-cms`. This field appears once the licence plugin is active, and it
  is what tells the plugin to issue a licence for this product.

Leaving that slug empty means no licence is issued. That is the single most
likely misconfiguration.

## 5. The licence plugin

```bash
cd woocommerce-plugin
zip -r astralab-licence.zip astralab-licence
```

Upload via **Plugins → Add New → Upload Plugin**, activate, then go to
**WooCommerce → Settings → Astra Lab**:

| Setting | Value |
| --- | --- |
| Hub URL | `https://manage.astralab.com` (no trailing slash) |
| API secret | the `STORE_API_SECRET` from the hub's `.env` |
| Installation guide URL | wherever your docs live |

The secret must match the hub exactly. If it does not, the hub returns 401 and
every order gets an "issue failed" note.

## 6. Test before going live

Put the store in a test mode you can take real orders through — a 1 BDT test
product, or your gateway's sandbox.

Check, in this order:

1. Place an order and pay. The thank-you page shows a licence key.
2. The confirmation email contains the same key.
3. **My Account → Orders → View** still shows it later.
4. The hub console lists the licence as `unactivated`.
5. Refresh the thank-you page. The key does not change and no second licence
   appears in the hub.
6. Temporarily set a wrong API secret and order again. The order gets a failure
   note and a retry is scheduled — the customer is not silently left with
   nothing.

Step 5 matters more than it looks. WooCommerce redelivers webhooks and status
transitions routinely, and one purchase must never produce two licences.

## What the store deliberately does not do

- It never sees a licence after issuing it. Validation, domain binding and
  updates all happen between the customer's install and the hub.
- It cannot recover a lost key. The hub reveals the plaintext once, and the
  store stores it against the order — if a customer loses it, reissue from the
  hub rather than expecting WooCommerce to know it.
