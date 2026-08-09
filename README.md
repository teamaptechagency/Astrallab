# manage.astralab

The licence, activation and update hub for self-hosted Astra Lab CMS installs.

Sits between the WooCommerce store that sells the product and the thousands of
customer sites that run it:

```
astralab.com  ──issue──▶  manage.astralab  ◀──activate / heartbeat──  customer sites
(WooCommerce)                    │
                                 └── later ──▶  Ayojon
```

## Why it exists

The CMS is sold as a **thin installer** containing no application code. The
installer asks for a licence key, this hub validates it, and only then is a
signed, time-limited link to the real package returned. Nothing downloads
without a valid key.

After that the same hub is the only channel through which updates reach a
customer — their sites are on shared hosting with no fixed address, so they
call us, never the other way round.

## Running it

```bash
npm install
npm run keys:generate    # copy the output into .env
npm run db:push
npm run db:seed
npm run dev              # http://localhost:3200
```

Then, with the server running:

```bash
npm run smoke
```

That exercises the whole lifecycle over real HTTP — issue, duplicate webhook,
activation, domain normalisation, seat limits, upgrade path, transfer.

## API

All routes are under `/api/v1`.

| Route | Caller | Auth |
| --- | --- | --- |
| `POST /licences` | WooCommerce store | HMAC over raw body (`X-Astralab-Signature`) |
| `POST /activate` | CMS installer | the licence key itself |
| `POST /deactivate` | CMS admin | the licence key itself |
| `POST /heartbeat` | CMS, on a schedule | the licence key itself |

### Things that are load-bearing

**Licence keys are never stored.** Only a keyed HMAC, plus the last four
characters so support can identify a licence a customer reads out. The
plaintext key exists in exactly one response, once — `POST /licences` cannot
reproduce it later, and a duplicate call deliberately withholds it.

**Issuing is idempotent on `(orderSource, orderRef)`.** WooCommerce redelivers
webhooks routinely; without this one purchase becomes three licences and
support has to guess which is real.

**Domains are normalised before storage.** `https://WWW.Shop.com/` and
`shop.com` are one domain, so a single licence cannot quietly occupy several
seats.

**Responses to installs are signed with Ed25519, not HMAC.** Every customer
site holds the public key and can verify us; none can forge a reply. An HMAC
would mean shipping the signing secret to every customer. The signature covers
issue and expiry timestamps, so an install can cache a valid response and keep
serving through an outage without being replayable after a revocation.

**The heartbeat returns an ordered upgrade path, not just "latest".** A release
can declare `minUpgradeFrom`, marking it a checkpoint; anything older stops
there first. Migrations run in sequence — skipping intermediate versions is how
a site ends up half-migrated.

**Security releases shorten the client's check interval** from 24 hours to 1,
because a patch reaches sites only as fast as they poll.

## Not built yet

- Admin authentication — the console at `/` is currently open. It must be
  gated before this is deployed anywhere reachable.
- Package upload and the actual signed-link download endpoint (tokens are
  minted; nothing serves them yet).
- Product sync, support tickets, design assignment.
- The WooCommerce plugin that calls `POST /licences`.

## Stack

Next.js 15, TypeScript, Prisma, SQLite for the prototype. Moving to Postgres is
a provider change in `prisma/schema.prisma` plus a connection string — the
schema avoids SQLite-only features for exactly that reason.
