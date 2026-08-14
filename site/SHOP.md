# Turning the shop on

Two applications, one sale. astrallabs.uk takes the order; manage.astrallabs.uk
decides the payment was real and issues the licence.

That split is deliberate. The shop is on the public internet — if it could mint
licences, anybody who got into it could mint licences. It holds a secret that
lets it *ask*, and nothing that lets it *decide*.

---

## 1 · On the hub (manage.astrallabs.uk)

**Products → open the product**

Set a **price** and **domains allowed**. Empty price means not for sale — the
shop leaves it out rather than showing ৳0.

**Settings → Payments**

Fill in bKash and Nagad:

| | |
| --- | --- |
| Offer this at checkout | tick it |
| Number money is sent to | your merchant/personal number |
| Instructions | what the customer should do, in their words |

A method with no number is never offered, however enabled it is. A checkout
that says "send the money to" and then stops is worse than one that never
mentioned it.

**Settings → API config**

Confirm `STORE_API_SECRET` reads **set**. That is the secret the shop carries.
If you need its value it is in `manage-app/.env` on the server.

---

## 2 · On the shop (astrallabs.uk)

Add two lines to `.env`:

```
ASTRALAB_HUB_URL=https://manage.astrallabs.uk
STORE_API_SECRET=<the same value as on the hub>
```

They must match exactly. If they disagree the shop gets a 401 on every order
and paying customers receive nothing.

Then apply the database changes: sign in at `/apt-admin` and press the button
on the dashboard. It creates the two tables the shop needs — the orders it
keeps for customers, and reviews.

Open `/shop`. If the products and prices are there, the two are talking.

---

## 3 · A sale, start to finish

1. Customer opens `/shop`, picks a product, sends the money by bKash or Nagad.
2. They enter their details and the transaction number. The order is created on
   the hub as **pending**. Nothing is issued.
3. **You** open **Revenue → Orders** on the hub. Check the money actually
   arrived — in the bKash app, not on the screen in front of you.
4. Press **Accept and issue**. That is the moment a licence exists.
5. The customer reloads their order page and their key is there.

**Step 3 is the whole system.** Everything else is plumbing around one person
confirming that money arrived.

### Two things that will happen

**A customer sends the wrong amount.** The Orders screen shows "short by ৳500"
or "over by ৳500" beside the claim. It does not refuse — somebody sending 4,500
for a 4,499 order is normal and turning that into a support ticket helps nobody
— but you should see it before accepting.

**A customer mistypes their transaction number.** Reject it with a note. The
order stays open, so they can send a corrected one against the same order rather
than paying twice.

---

## The key, and why it is only shown once

The hub stores a hash of a licence key, never the key. That is what makes a
stolen database useless.

So the plain key exists for one moment: the hub hands it to the shop the first
time the shop asks after a payment is accepted, and erases it in the same
statement. **The shop saves it** — that is the only reason `shop_orders` exists
on this side. A customer who closes the tab still finds their key when they open
their order address again.

Nobody can produce that key again. Not you, not the hub. If a customer loses
their order address, the fix is a new licence — which is why the order page
tells them to keep it.

---

## When something is wrong

**"We cannot show prices at the moment"** — the shop cannot reach the hub.
Check `ASTRALAB_HUB_URL`, and that the hub answers at
`https://manage.astrallabs.uk/api/v1/catalogue`. That address is public; open it
in a browser.

**Orders fail with nothing in the shop's log** — the two `STORE_API_SECRET`
values disagree.

**An order is paid on the hub but the shop still says "checking"** — the shop
asks the hub on every load until the key arrives. Reload once. If it persists,
the shop cannot reach the hub, as above.

**Prices are stale** — the catalogue is cached for five minutes, so the shop
survives the hub being quiet for a moment. Wait, or clear the cache.

---

## Not built yet

- **No email.** The key appears on the order page and nowhere else. If a
  customer loses the address, you cannot resend it.
- **Card payments.** The methods table has room for a gateway and nothing is
  written against one.
- **The payment-verify app.** When it exists it writes the same payment row and
  calls the same code an operator does, so step 3 stops needing a person. The
  design already assumes it; nothing here changes when it arrives.
