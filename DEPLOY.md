# Putting manage.astrallabs.uk live

This is a Next.js application. It needs a live Node process, which **Hostinger's
shared plans do not provide** — Premium and Business run PHP only. It needs a
VPS, or any host that runs Node.

Hostinger KVM 1 is enough: 1 vCPU, 4 GB RAM, around $5–7/month. This workload is
thousands of small requests a day, not heavy computation.

## What you are putting where

| | |
| --- | --- |
| `astrallabs.uk` | shared hosting, the Laravel public site — already live |
| `manage.astrallabs.uk` | **the VPS, this application** |

The subdomain currently resolves to Hostinger and serves their default page. Its
DNS **A record** has to point at the VPS instead — that is done in hPanel under
Domains → DNS, not on the VPS.

## 1. The server

Ubuntu 24.04. As root:

```bash
apt update && apt install -y nginx git
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt install -y nodejs
npm install -g pm2
```

## 2. The application

```bash
mkdir -p /var/www/manage/data
cd /var/www/manage
# Upload the repository here, or clone it.
npm ci
npx prisma generate
```

## 3. Settings

```bash
cp .env.production.example .env
nano .env
```

Fill in every blank. Generate the keys with:

```bash
npm run keys:generate
```

**`SIGNING_PRIVATE_KEY` is the one to be careful with.** Its public half is
compiled into every copy of the CMS ever shipped. If you generate a *new* one
here, no installed shop will believe anything this hub says. Use the key that
already exists in your development `.env` — the one the CMS was built against —
and back it up somewhere that is not this server.

## 4. The database

```bash
npx prisma db push
npm run db:seed        # products and release history; skip on a real launch
```

The path in `DATABASE_URL` must be **absolute**. A relative path resolves
against the working directory, which differs between your shell and PM2, and the
result is a second empty database that looks exactly like every licence having
vanished.

## 5. Run it

```bash
npm run build
pm2 start npm --name manage -- start
pm2 save
pm2 startup          # run the line it prints, so it survives a reboot
```

## 6. Nginx and HTTPS

`/etc/nginx/sites-available/manage`:

```nginx
server {
    server_name manage.astrallabs.uk;

    location / {
        proxy_pass http://127.0.0.1:3200;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/manage /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
apt install -y certbot python3-certbot-nginx
certbot --nginx -d manage.astrallabs.uk
```

HTTPS is not optional here. Licence keys travel in response bodies, and the
operator session cookie is only marked `secure` in production — over plain HTTP
it would not be sent at all, and nobody could sign in.

## 7. First run

Open `https://manage.astrallabs.uk`. With no operator accounts it redirects to
`/setup`, where you create the first one.

## 8. Point everything at it

| Where | Setting |
| --- | --- |
| WooCommerce → Settings → Astra Lab | Hub URL `https://manage.astrallabs.uk`, API secret = `STORE_API_SECRET` |
| The CMS | already ships pointing here — `config/astralab.php` |
| `astrallabs.uk` | already fetches its catalogue from here |

## Backups

Three things, and losing any one of them is a different kind of bad day:

```bash
/var/www/manage/data/production.db     # every licence and customer
/var/www/manage/.env                   # the signing key
/var/www/manage/packages/              # the release archives customers download
```

A nightly copy off the server is enough. The database is one file; `sqlite3
production.db ".backup /tmp/backup.db"` copies it safely while the app is
running, which a plain `cp` does not guarantee.

## Updating

```bash
cd /var/www/manage
git pull
npm ci
npx prisma db push
npm run build
pm2 restart manage
```

## Moving to the Laravel hub later

Nothing installed in the field points at a server — it points at
`manage.astrallabs.uk`. When the Laravel port reaches parity, change the DNS
record, and no shop notices. That is the entire reason the API lives on a
subdomain rather than a path.
