# Deploying Secret Santa Car Show

This describes deploying this application as built: Laravel 13, Blade views, Vite-built CSS/JS, MySQL, file uploads on the local `public` disk, database sessions and cache. It has **no queue workers and no scheduled tasks**.

Production details are not known yet. Everything in `<ANGLE_BRACKETS>` is a value to confirm. The commands assume a Linux server (Ubuntu 24.04 LTS style) with PHP-FPM behind Nginx or Apache. Package names and service commands differ on other distributions: confirm them for the real host.

Do not deploy during voting. Do not run destructive database commands (`migrate:fresh`, `migrate:reset`, `db:wipe`, `app:reset-test-event`) on production.

---

## 1. Requirements

| Item | Requirement | Notes |
|---|---|---|
| PHP | 8.3 or 8.4 (built and tested on 8.4.25) | Laravel 13 requires 8.3+. |
| PHP extensions | `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd` (with WebP support), `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`, `bcmath` | `gd` WebP is needed for photo re-encoding. `zip` and `xml` are only needed if you run the 2025 import, which is not a production step. `exif` is optional (fixes phone photo rotation). |
| Composer | 2.x | Install with `--no-dev`. |
| Node | 20.19+ with npm | Only to build assets. Can run on a build machine instead of the server. |
| Database | MySQL 8.0+ or MariaDB 10.6+ (InnoDB) | Built and tested on MySQL 5.7.24 locally; 5.7 is end of life, so use a supported version in production. Row locking (`SELECT ... FOR UPDATE` / `LOCK IN SHARE MODE`) and composite foreign keys are required. |
| Web server | Nginx or Apache 2.4 with PHP-FPM | Document root must be `public/`. |
| TLS | Required | Contestants sign in with a code; use HTTPS only. |

Example package install (confirm names for your host):

```bash
sudo apt install php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-gd php8.4-intl php8.4-zip php8.4-bcmath unzip
```

## 2. Get the code onto the server

Use a git checkout or an archive of the repository. Paths below use `<APP_DIR>` (for example `/var/www/secretsanta`) and `<APP_USER>` (the deploy user) and `<WEB_USER>` (for example `www-data`).

```bash
cd <APP_DIR>
git clone <REPO_URL> .          # or unpack a release archive
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build                   # creates public/build
```

If you build assets elsewhere, deploy `public/build/` together with the code. Do **not** deploy: `node_modules/`, `reference/`, `.env` from a workstation, `storage/app/private/imports/`, `tests/`, or any spreadsheet. `vendor/` is created on the server by Composer (locked by `composer.lock`).

## 3. Environment (`.env`)

```bash
cp .env.example .env
```

Set at least:

```dotenv
APP_NAME="Secret Santa Car Show"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<DOMAIN>
APP_DISPLAY_TIMEZONE=America/New_York

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=<DB_HOST>
DB_PORT=3306
DB_DATABASE=<DB_NAME>
DB_USERNAME=<DB_USER>
DB_PASSWORD=<DB_PASSWORD>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
```

Timestamps are stored in UTC (`config/app.php` `timezone`). Pages and printouts display `APP_DISPLAY_TIMEZONE`. Leave the app timezone as UTC.

### APP_KEY: generate once, then protect it

```bash
php artisan key:generate    # NEW INSTALL ONLY
```

Contestant login codes are stored encrypted with `APP_KEY`, and code lookups use a hash keyed from it. If `APP_KEY` changes, every login code and every printed ballot stops working. On upgrades, restores and server moves, **copy the existing `APP_KEY`**; never regenerate it. Keep a copy of `.env` (or at least `APP_KEY`) in a secure place separate from the server.

## 4. Web server

### Nginx (example)

```nginx
server {
    listen 80;
    server_name <DOMAIN>;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name <DOMAIN>;
    root <APP_DIR>/public;
    index index.php;

    ssl_certificate     <PATH_TO_FULLCHAIN_PEM>;
    ssl_certificate_key <PATH_TO_PRIVKEY_PEM>;

    client_max_body_size 12m;   # car photos up to 8 MB
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:<PHP_FPM_SOCKET>;   # e.g. /run/php/php8.4-fpm.sock
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Apache (example)

```apache
<VirtualHost *:443>
    ServerName <DOMAIN>
    DocumentRoot <APP_DIR>/public
    <Directory <APP_DIR>/public>
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile <PATH_TO_FULLCHAIN_PEM>
    SSLCertificateKeyFile <PATH_TO_PRIVKEY_PEM>
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:<PHP_FPM_SOCKET>|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

Enable `mod_rewrite`; `public/.htaccess` handles routing. Set PHP `upload_max_filesize = 10M` and `post_max_size = 12M` or higher.

TLS: use a certificate for `<DOMAIN>` (for example Let's Encrypt via certbot, if the host allows it). Confirm with the host.

Access logs: login codes are only ever sent in POST bodies, never in URLs, so standard access logs do not record them. Do not enable request-body logging.

## 5. Permissions and storage

```bash
sudo chown -R <APP_USER>:<WEB_USER> <APP_DIR>
sudo find <APP_DIR> -type f -exec chmod 644 {} \;
sudo find <APP_DIR> -type d -exec chmod 755 {} \;
sudo chmod -R ug+rwX <APP_DIR>/storage <APP_DIR>/bootstrap/cache
chmod 640 <APP_DIR>/.env
php artisan storage:link        # public/storage -> storage/app/public (car photos)
```

Uploaded photos are re-encoded to WebP and stored in `storage/app/public/cars/`.

## 6. Database: first install

```bash
php artisan migrate --force
php artisan db:seed --force     # seeds the 22 car classes only (needs reference/carClasses.csv)
php artisan app:create-admin    # interactive; password is never echoed
```

`db:seed` reads `reference/carClasses.csv`. Copy only that CSV to the server for the first install (outside `public/`), or set `CARSHOW_CATEGORIES_CSV=<path>` in `.env`. Delete it afterwards if you like; classes can also be managed on the Admin Classes page.

Then sign in at `https://<DOMAIN>/admin/login` and create the event on **Admin > Event**. Production starts empty: no 2025 contacts, no test ballots. Never run `app:import-2025` on production.

## 7. Cache and optimize

After every deploy:

```bash
php artisan optimize          # caches config, routes, views, events
```

If you change `.env`, run `php artisan optimize` again (cached config ignores `.env` edits). To undo caching: `php artisan optimize:clear`.

No queue worker, no scheduler/cron entry and no websocket server are needed.

## 8. Backups

Back up three things:

1. **Database** (all show data, votes, awards, audit log):
   ```bash
   mysqldump --single-transaction --routines --triggers -h <DB_HOST> -u <DB_USER> -p <DB_NAME> | gzip > secretsanta-$(date +%F-%H%M).sql.gz
   ```
2. **Uploaded photos**: `<APP_DIR>/storage/app/public/`
3. **`APP_KEY` / `.env`**, stored securely offline. Without the same `APP_KEY`, a restored database has unusable login codes.

Store copies off the server. **Test a restore before show day** on a separate machine or database.

Restore:

```bash
gunzip < secretsanta-<DATE>.sql.gz | mysql -h <DB_HOST> -u <DB_USER> -p <DB_NAME>
rsync -a <BACKUP>/public/ <APP_DIR>/storage/app/public/
# .env with the ORIGINAL APP_KEY, then:
php artisan optimize
```

Recommended backup moments: after registration closes, right after Voting Finished, and after results are finalized.

## 9. Upgrades and rollback

- Upgrade only outside voting (before voting opens or after results are final).
- Take a database and photo backup first.
- `php artisan down` during the upgrade, `php artisan up` after.
- Steps: pull code, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan optimize`.
- Keep `APP_KEY` unchanged.
- Rollback: restore the previous code release and, if a migration ran, the pre-upgrade database backup. Do not rely on `migrate:rollback` for data safety.

## 10. Smoke checks after deploy

1. `https://<DOMAIN>/up` returns 200.
2. The gallery `https://<DOMAIN>/` loads, with styles and the logo.
3. `https://<DOMAIN>/admin/login` works with the admin account.
4. Admin > Contestants > print one ballot; sign in with its code on a phone at `https://<DOMAIN>/login`.
5. A car photo upload appears in the gallery.
6. `https://<DOMAIN>/reference/carClasses.csv` and `https://<DOMAIN>/.env` return 404/403.

## 11. Troubleshooting

- Logs: `storage/logs/laravel-YYYY-MM-DD.log` (with `LOG_CHANNEL=daily`). The application never logs login codes or contact details; do not add debug logging of request input.
- 500 errors with a blank page: check the log and file permissions on `storage/` and `bootstrap/cache/`.
- Styles missing: `public/build/manifest.json` is missing; run `npm run build` and deploy `public/build`.
- "Login code not recognized" for every code after a move: `APP_KEY` differs from the original.
- Never set `APP_DEBUG=true` on production; error pages would expose configuration.

## 12. Show-day sequence

1. Configure the event (name, date, location, votes per car) and check the classes.
2. Register contestants and their cars. Attach extra cars to existing owners.
3. Print ballots (each contains the contestant's login code; handle them like tickets).
4. **Open voting** on the Event page.
5. Enter returned paper ballots on the Paper ballots page as they come in.
6. Check **Reports > Reconciliation** (votes cast never exceed the total allowance).
7. Click **Voting Finished** after all returned paper ballots are entered.
8. Resolve ties on the Results page (Best Overall first).
9. **Finalize** and print the results.
10. Back up the database and photos.

## 13. Network access at the show

Attendees' phones must reach the app over the internet at a hosted domain, or over a local network you set up (for example a laptop server on the show Wi-Fi with a local DNS name and certificate). `secretsanta.test` on the development workstation is not reachable from anyone's phone. There is no offline mode or later synchronization: if the connection drops, use paper ballots and enter them when the admin screen is reachable.
