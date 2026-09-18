# Handoff

Last updated: 2026-09-18. All phases in `PLAN.md` are complete. The app runs locally at http://secretsanta.test.

## Current state

- Laravel 13.32 on PHP 8.4.25 (site-only through mod_fcgid; see `PLAN.md` section 1), MySQL 5.7.24.
- Dev database `secretsanta`: 22 classes seeded; the "Secret Santa Car Show 2025 Test Show" event (is_test, active, **setup**) with 191 contestants and 195 cars imported from the 2025 workbook, **0 votes**. Login codes exist for all contestants (print from Admin > Contestants).
- **No admin account exists in the dev DB.** Create one with `$PHP artisan app:create-admin`. (A temporary admin used for the browser check was deleted, and the test event was reset.)
- Git: local repo, branch `master`, commits per phase. Nothing pushed; there is no remote.

## Commands

```bash
PHP=/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64/php.exe
$PHP artisan app:create-admin                     # interactive
$PHP artisan test                                  # full suite, MySQL secretsanta_testing
DB_DATABASE=secretsanta_testing_a $PHP artisan test  # isolated DB for parallel runs
npm run build
$PHP artisan app:seed-categories                   # idempotent, reference/carClasses.csv
$PHP artisan app:import-2025 [--dry-run]           # idempotent, reference/Carshow 2025 Repair-4.xlsm
$PHP artisan app:reset-test-event [--confirm="<event name>"]   # APP_ENV=local + is_test only
```

## Test results (latest run)

`$PHP artisan test`: **112 passed, 478 assertions**, about 36 s, on MySQL.

| Suite | Covers |
|---|---|
| `tests/Feature/Domain/AllowanceTest` | 5 votes per car, 10 for two, partial use, override/reset, cannot go below used, frozen after close |
| `tests/Feature/Domain/VoteServiceTest` | one vote per car across online and paper, self-vote, idempotency, atomic batches, invalid/duplicate/cross-event/over-quota rejection, immutability, preview |
| `tests/Feature/Domain/ResultsTest` | Best Overall first and excluded from its class, no double wins, same owner wins twice with different cars, zero votes, single car, unvoted class, overall tie blocks only affected classes, tiebreak persists and never rerolls, no cross-award effect, finalize snapshot |
| `tests/Feature/Domain/LifecycleAndRegistrationTest` | forward-only lifecycle, close stops online and paper voting and registration, freeze rules, auto-numbering, voter number, code rotation, likely matches |
| `tests/Feature/Concurrency/ConcurrentVotingTest` | **real parallel PHP processes on MySQL**: 10 parallel single-car ballots against a 5-vote allowance (exactly 5 stored), 6 parallel duplicates (1 stored), same idempotency key x4 (1 ballot), close racing 12 votes (in-flight votes kept, later ones rejected, close snapshot equals ledger; 6/6 split observed in 3 of 3 runs), lowering allowance during voting |
| `tests/Feature/Http/*` | gallery privacy (no contact data or codes), search/filter, code login normalization, car number is not a code, throttling, code cannot reach admin, online review/confirm/double-submit, session ended by rotation, no vote edit/delete routes, paper lookup/preview/confirm idempotency, overlap flags, results/tiebreak/finalize over HTTP |
| `tests/Feature/Admin/*` | contestant/car/class CRUD and freeze rules, likely matches, allowance via HTTP, photo re-encode to WebP, ballot print (code appears only there), reports reconcile, CSV has no codes or contact data, vote list shows no voter identity |
| `tests/Feature/Import/*` | BOM CSV, id preservation, idempotency, invalid mappings, synthetic workbook import rules, create-admin |

Concurrency limitation: races were verified with multiple OS processes against local MySQL 5.7 InnoDB. Production MySQL 8 or MariaDB uses the same locking statements, but rerun `tests/Feature/Concurrency` against the production engine version before the show if possible.

## Browser check (2026-09-18)

Desktop and 390px phone width (iframe; the browser window could not be resized): gallery, phone header, code login (lowercase with spaces accepted), ballot selection with search, review, confirm and "Saved" state, admin paper entry by keyboard (voter # then Enter, car numbers then Enter, duplicate flagged), printed ballot (2 cars = 10 votes), Voting Finished, class tie with tiebreak button, provisional and final print, reconciliation (2 online + 3 manual = 5), public final results. Fixed during the check: phone header truncation, Save button focus after a valid paper preview, "1 votes" plural, empty plates on no-winner results.

## Import result (real workbook)

Rows scanned 2005; real entrant rows 195; people 191; cars 195; skipped 0. Four rows flagged "shared email, not merged" (sheet rows 28, 32, 76, 82). Re-run: 0 created, 195 unchanged. `BallotVotes` intentionally not imported. Report: `storage/app/private/imports/` (git-ignored).

## Next steps (for the owner)

1. Run `$PHP artisan app:create-admin` and sign in at http://secretsanta.test/admin/login.
2. Review the 4 shared-email flags in the import report; attach cars to one owner manually only if they are truly the same person (before opening voting).
3. Choose production hosting and domain, then follow `deployment.md`.

## Open items / known limits

- Production environment is unknown; `deployment.md` marks values to confirm.
- No per-voter reporting by design. The vote list shows car, source, and entering admin only.
- Public results page shows only finalized results; provisional results are admin-only.
- Car photos: one current photo per car (no history).
- Impeccable skill reports a newer version (v4.3.1) is available; not updated.

## Changed files by phase

- Phase 0: scaffold, `PLAN.md`, `AGENTS.md`, `CLAUDE.md`, `.gitignore`, `phpunit.xml`, `.env.example`
- Phase 1: `database/migrations/*`, `app/Models/*`, `app/Enums/*`, `app/Services/{LoginCode,Registration,Allowance,Vote,TieBreak,VotingLifecycle}Service.php`, `app/Services/Results/*`, `tests/Feature/{Domain,Concurrency}`, `tests/Concurrency/worker.php`, `tests/Concerns/BuildsShow.php`
- Phase 2: `database/seeders/*`, `config/carshow.php`, `app/Services/Import/*`, `app/Console/Commands/{SeedCategories,Import2025,CreateAdmin}Command.php`, `tests/Feature/Import/*`
- Phases 3-5: `routes/web.php`, `bootstrap/app.php`, `app/Http/**`, `app/Policies/*`, `app/Services/{CarPhoto,Report}Service.php`, `resources/{css,js,views}/**`, `tests/Feature/{Http,Admin}/*`
- Phase 6: `ResetTestEventCommand.php`, `README.md`, `deployment.md`, this file, polish edits to views and `app.css`
