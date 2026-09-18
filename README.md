# Secret Santa Car Show

Car registration, public gallery with live vote counts, contestant voting (online and paper), and award results for the Secret Santa Car Show in Oakwood, Georgia.

Laravel 13, Blade, Tailwind CSS 4, Alpine.js, MySQL/MariaDB. No SPA, no external services.

- Local URL: http://secretsanta.test
- Plan and decisions: `PLAN.md`
- Current state and next steps: `HANDOFF.md`
- Production notes: `deployment.md`
- Agent instructions (Codex and Claude): `AGENTS.md`

## Local setup (Windows + Laragon)

### Prerequisites

| Tool | Version | Where |
|---|---|---|
| PHP | 8.4 (8.3+ required) | `C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64` |
| Extensions | pdo_mysql, mbstring, openssl, intl, fileinfo, gd (with WebP), zip, bcmath, exif | enabled in that folder's `php.ini` |
| Composer | 2.x | `composer.phar` in the PHP 8.4 folder |
| Node | 20.19+ (24 installed) | on PATH |
| MySQL | 5.7+ or MariaDB 10.3+ (Laragon has 5.7.24) | Laragon |

Laragon's global PHP is 8.1, which cannot run Laravel 13. This site alone runs on PHP 8.4 through a custom Apache vhost, `C:\laragon\etc\apache2\sites-enabled\secretsanta.test.conf`, which uses 64-bit `mod_fcgid` (`C:\laragon\etc\apache2\modules\mod_fcgid-2.3.10-win64-VS16.so`). Other Laragon sites are unchanged. Reload Apache from the Laragon menu after editing the vhost.

In Git Bash, set these once per shell:

```bash
PHP=/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64/php.exe
COMPOSER="$PHP /c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64/composer.phar"
cd /c/laragon/www/secretsanta
```

### Install

```bash
$COMPOSER install
npm ci
npm run build
cp .env.example .env        # first time only
$PHP artisan key:generate   # first time only; never on an existing install (see below)
```

### Database

Create the databases and a dedicated user (run as MySQL root; pick your own password):

```sql
CREATE DATABASE secretsanta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE secretsanta_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'secretsanta'@'localhost' IDENTIFIED BY 'choose-a-password';
CREATE USER 'secretsanta'@'127.0.0.1' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON secretsanta.* TO 'secretsanta'@'localhost', 'secretsanta'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `secretsanta\_testing%`.* TO 'secretsanta'@'localhost', 'secretsanta'@'127.0.0.1';
```

Put the credentials in `.env` (`DB_DATABASE=secretsanta`, `DB_USERNAME=secretsanta`, `DB_PASSWORD=...`), then:

```bash
$PHP artisan migrate
$PHP artisan db:seed            # car classes only, from reference/carClasses.csv
$PHP artisan storage:link       # serves uploaded car photos
```

### Create the first admin

```bash
$PHP artisan app:create-admin
```

It asks for a display name, username and password (hidden, at least 12 characters). Sign in at http://secretsanta.test/admin/login. Contestant login codes never grant admin access.

### Import the 2025 test show (local only)

Put the private inputs in `reference/` (git-ignored, outside `public/`):

- `reference/carClasses.csv` (authoritative class list, UTF-8 with BOM is fine)
- `reference/Carshow 2025 Repair-4.xlsm` (macros are never run; only cached cell values are read)

```bash
$PHP artisan app:seed-categories
$PHP artisan app:import-2025 --dry-run   # preview counts, writes nothing
$PHP artisan app:import-2025
```

This creates the event "Secret Santa Car Show 2025 Test Show" (flagged as a test event) with contestants and cars from the `Name Entry` sheet and zero votes. Historical `BallotVotes` data is not imported because it has no reliable voter attribution. Re-running changes nothing. An exception report is written to `storage/app/private/imports/`.

Login codes are generated for every imported contestant. Print them from **Admin > Contestants > Print all ballots**.

To clear votes from the test event and start over (local only, asks you to type the event name):

```bash
$PHP artisan app:reset-test-event
# or without the prompt:
$PHP artisan app:reset-test-event --confirm="Secret Santa Car Show 2025 Test Show"
```

Production starts from classes and a clean event created on the Admin Event page. Do not import the 2025 contacts into production.

## Tests

```bash
$PHP artisan test
```

Tests run on MySQL (`secretsanta_testing`), not SQLite, so locking behaviour is real. `tests/Feature/Concurrency` starts parallel PHP processes against the test database to race votes, closing, and allowance changes. To run the suite against another database: `DB_DATABASE=secretsanta_testing_a $PHP artisan test`.

## Show-day workflow

1. **Admin > Event**: check the name, date, location and votes per car (default 5). Check the classes under **Classes**.
2. **Contestants > Register contestant**: name, optional private contact, first car. The form warns about likely duplicates so a second car goes on the existing owner. Add more cars from the contestant page.
3. **Print ballots**: one per contestant, or **Print all ballots**. Each shows the voter number, the contestant's cars, their vote allowance, their login code and the sign-in address.
4. **Event > Open voting**. New contestants and cars can still be registered. Numbers, owners and classes of existing cars lock.
5. Contestants vote on their phones (sign in with the code) or return the paper ballot. Enter paper ballots at **Paper ballots**: type the voter number, type the car numbers, check, save.
6. Check **Reports > Reconciliation** before closing.
7. **Event > Voting Finished** after every returned paper ballot is entered. Online and paper voting both stop.
8. **Results**: resolve any ties (Best Overall first), then **Finalize**. Print the results.
9. Back up the database and uploaded photos (see `deployment.md`).

Phones at the show need to reach a hosted address. `secretsanta.test` only works on this computer.
