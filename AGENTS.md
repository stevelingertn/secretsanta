# Agent instructions (shared by Codex and Claude Code)

Project: **Secret Santa Car Show**, a Laravel 13 app (Blade + Tailwind 4 + Alpine) for car registration, public gallery, contestant voting (online + paper), and award results. Local URL `http://secretsanta.test`.

## Read first
1. `PLAN.md`: decisions, schema, freeze rules, locking strategy, phases with checkboxes.
2. `HANDOFF.md`: latest state, next steps, commands, test results, open issues.

## Environment (Windows + Laragon)
- Use PHP 8.4, not Laragon's global PHP 8.1:
  `PHP=/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64/php.exe`
  Composer: `$PHP /c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64/composer.phar`
- MySQL 5.7 at `127.0.0.1:3306`, user `secretsanta` (password in `.env`). DBs: `secretsanta` (app), `secretsanta_testing` (tests; `secretsanta_testing_a/_b/_c` exist for parallel agents via `DB_DATABASE=...`).
- Tests: `$PHP artisan test` (runs on MySQL; concurrency tests need MySQL, not SQLite).
- Assets: `npm run build` (Node 20.19+; Node 24 is on PATH).
- Apache vhost: `C:\laragon\etc\apache2\sites-enabled\secretsanta.test.conf` (PHP 8.4 via mod_fcgid). The user reloads Apache from the Laragon menu.

## Non-negotiable domain rules
- Each car grants its owner 5 votes; allowance override replaces the default total; never below votes used.
- One contestant vote per car per show, across online and paper. Votes are final: no edit/delete paths.
- All vote writes go through `App\Services\VoteService` (locks: event row, then participant row).
- System tiebreaker votes live only in `award_tiebreaks`, never in `votes`.
- Best Overall first, then one winner per class excluding it; a car wins at most once; zero votes means no winner.
- Login codes are secrets: never log, display publicly, or put in reports/CSV. Car numbers are not secrets.
- No payments, donations, raffles, judged scoring, Top 20, or specialty awards.
- Never commit `reference/`, `.env`, import reports, generated ballots, or real contact data.

## Conventions
- Laravel defaults: small controllers, form requests, policies, focused services in `app/Services`. No repository layer.
- Copy: plain, short, no em dashes, no emoji, no invented slogans.
- Update `PLAN.md` checkboxes and `HANDOFF.md` at the end of each phase.
