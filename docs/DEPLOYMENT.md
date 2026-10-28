# Deploying SchoolVote to Hostinger

Target: Hostinger **Business** or **Cloud** web hosting (or any plan with SSH, cron, PHP 8.4 and MySQL/MariaDB).
SchoolVote runs entirely on Hostinger — Vercel is not needed.

> Nothing in this guide has been run against a live server yet. Follow it on a staging subdomain first.

## 1. Check the plan

In hPanel confirm:

| Item | Needed |
|---|---|
| PHP version | **8.4** (hPanel → Advanced → PHP Configuration). The locked dependencies require PHP ≥ 8.4.1 |
| PHP extensions | pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, fileinfo, gd, zip, intl |
| `memory_limit` | 256M or more (PDF and Excel generation) |
| `max_execution_time` | 120 or more (bulk PIN generation) |
| Database | MySQL/MariaDB database + user (hPanel → Databases) |
| SSH | Enabled (hPanel → Advanced → SSH Access) |
| Cron jobs | Available (hPanel → Advanced → Cron Jobs) |
| SSL | Free SSL active for the (sub)domain |

## 2. Create the database

hPanel → Databases → Management: create a database and a user with a strong password. Note the names — Hostinger prefixes them (e.g. `u123456789_schoolvote`).

## 3. Upload the code

Recommended layout (application code **outside** `public_html`):

```
/home/u123456789/
├── schoolvote/                 ← the Laravel app (git clone or upload)
└── domains/election.nancapiannhs.com/public_html/   ← web root
```

```bash
ssh u123456789@your-server -p 65002
cd ~
git clone git@github.com:YOUR-ORG/schoolvote.git schoolvote   # private repo, use a deploy key
cd schoolvote
composer install --no-dev --optimize-autoloader
```

Build assets **locally or in CI** (Hostinger shared plans may not have Node) and upload `public/build/`:

```bash
npm ci && npm run build      # on your computer
# upload public/build/ to ~/schoolvote/public/build/
```

### Point the web root at `public/`

Preferred: set the subdomain's document root to `/home/u123456789/schoolvote/public` (hPanel → Domains → subdomain settings, when available).

Otherwise replace `public_html` with a symlink:

```bash
cd ~/domains/election.nancapiannhs.com
mv public_html public_html.bak
ln -s ~/schoolvote/public public_html
```

Never copy the whole app into `public_html`: `.env`, `storage/` and source code must not be web-accessible.

## 4. Configure `.env`

```bash
cp .env.production.example .env
nano .env        # fill DB_DATABASE / DB_USERNAME / DB_PASSWORD, APP_URL, mail settings
php artisan key:generate
chmod 600 .env
```

Key production values: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SCHOOLVOTE_REQUIRE_STAFF_2FA=true`, `LOG_CHANNEL=daily`, `LOG_LEVEL=warning`.

## 5. Migrate, seed, create the first admin

```bash
php artisan migrate --force          # additive only; never migrate:fresh on production
php artisan db:seed --force          # roles, school, default positions (idempotent, no accounts)
php artisan storage:link
php artisan schoolvote:create-admin  # first Super Administrator (asks for a strong password)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`migrate:fresh`, `db:wipe` and similar destructive commands are blocked in production by the app.

## 6. Cron (scheduler)

hPanel → Cron Jobs → every minute:

```
cd /home/u123456789/schoolvote && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

(Use the PHP 8.4 binary path shown by hPanel, e.g. `/opt/alt/php84/usr/bin/php`.) This opens and closes elections on schedule. Admins can also open/close manually.

## 7. Smoke test

1. Visit `https://election.nancapiannhs.com` — student login appears over HTTPS.
2. `/staff/login` — sign in, enable two-factor in **My account**.
3. Settings: school logo, address, school year, signatories.
4. Create a **test** election with 2–3 fictional students, vote on a phone, close, finalize, print reports.
5. Delete the test data (or archive) before importing real students.

## 8. Before election day: backup and rollback plan

**Before every deployment and before election day:**

```bash
mysqldump -u USER -p --single-transaction --routines DBNAME | gzip > ~/backups/schoolvote-$(date +%F-%H%M).sql.gz
tar czf ~/backups/schoolvote-files-$(date +%F-%H%M).tgz -C ~/schoolvote storage/app/public .env
```

Keep backups outside the web root, restrict permissions (`chmod 600`), and copy them off the server. Also enable Hostinger's automatic backups.

**Deploy update:**

```bash
php artisan down --secret="choose-a-bypass-token"
git pull
composer install --no-dev --optimize-autoloader
# upload new public/build/
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

**Rollback:** `git checkout <previous-tag>`, reinstall dependencies, restore the database dump if a migration changed data, clear caches. Do not deploy code changes while an election is **Open**.

## 9. Capacity notes

500 students with 20–50 voting at once is light work for a Business plan. Tested locally with 75 concurrent simulated voters (each submitting twice) with no duplicate or lost ballots. Repeat `php artisan schoolvote:load-test --students=75` on a **staging** copy of the Hostinger setup (never production) to confirm on the real server. Keep `SESSION_DRIVER=database` and `CACHE_STORE=database` (no Redis needed).
