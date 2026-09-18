# Secret Santa Car Show: Implementation Plan

Mobile-first Laravel app for car registration, a public gallery, contestant voting (online + paper), and award results.
Local: `C:\laragon\www\secretsanta` served at `http://secretsanta.test`.

Status legend: `[x]` done, `[ ]` open. Update this file at the end of each phase. `HANDOFF.md` holds the running log.

---

## 1. Environment decisions (verified 2026-09-18)

| Item | Choice | Why |
|---|---|---|
| PHP | 8.4.25 NTS at `C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64` | Laragon's global PHP is 8.1 (only Laravel 10, EOL). User chose a site-only PHP 8.4. |
| Web server | Laragon Apache 2.4.35, custom vhost `C:\laragon\etc\apache2\sites-enabled\secretsanta.test.conf` using 64-bit `mod_fcgid` 2.3.10 (Apache Lounge) | Other Laragon sites keep global mod_php 8.1. Docroot is `public/`. |
| Framework | Laravel 13.x (security fixes until Q1 2028) | Current supported release; requires PHP >= 8.3. |
| DB | MySQL 5.7.24 (Laragon). Databases `secretsanta` and `secretsanta_testing` | Laravel 13 supports MySQL 5.7+. Production assumption: MySQL 8.0+ or MariaDB 10.6+. |
| Frontend | Blade, Tailwind CSS 4 (Vite), Alpine.js | No SPA, no API backend. |
| Excel | `phpoffice/phpspreadsheet` ^5 (read-only, data-only) | Maintained reader. Macros never executed. Cached formula values only. |
| Composer | `C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64\composer.phar` (2.10) | Laragon's composer is 2.3 on PHP 8.1. |
| CA bundle | `...\php-8.4.25...\extras\ssl\cacert.pem` (curl.se) | Laragon's bundle was stale. |

Command prefix used everywhere (Git Bash):
```
PHP=/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64/php.exe
$PHP artisan ...        $PHP /c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64/composer.phar ...
```

## 2. Domain decisions

1. **Event record** scopes everything (`events`). One event is `is_active`. States: `setup` -> `voting_open` -> `voting_closed` -> `finalized`. No reopen/reset UI. Local-only `app:reset-test-event` for the test event.
2. **Users** are people. Admins (`is_admin`, username + password) and contestants (no email/password). Contact fields are private, nullable.
3. **Participants** (`participants`): a user's participation in one event. Holds voter number, login code (encrypted + keyed hash), allowance override, session version. A person is one participant per event regardless of car count.
4. **Voter number** = owner's lowest car entry number at the moment their first car is assigned. Stable after that. Not a secret.
5. **Login code**: 10 chars from `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (31 symbols, ~49.5 bits), shown as `XXXXX-XXXXX`. Stored as `Crypt::encryptString` (admin reprint) + HMAC-SHA256 lookup hash keyed from `APP_KEY`. Input normalized (uppercase, strip spaces/dashes). Throttled 10/min per IP + 30/hour per IP. Rotation bumps `session_version`, invalidating existing contestant sessions.
6. **Cars** belong to one event, one participant (owner), one category. `entry_number` unique per event; auto-number = max + 1, admin may type another unused number.
7. **Allowance** = `allowance_override ?? votes_per_car (5) x cars owned`. Override nonnegative, never below votes used. Reset clears override.
8. **Votes** (`votes`): contestant votes only. `UNIQUE(event_id, participant_id, car_id)`. Source `online` or `manual` (UI label exactly **Manually entered**). Manual votes record `entered_by`. Immutable: no update/delete paths; model throws on update/delete.
9. **Ballot submissions** (`ballot_submissions`): one row per online/paper batch, `UNIQUE(event_id, idempotency_key)`. Retries with same key return the existing submission.
10. **System tiebreaker votes** live in `award_tiebreaks` (award-scoped, typed separately). Never in `votes`.
11. **Awards**: Best Overall = most contestant votes (>= 1). Then each class winner among its cars excluding the Best Overall winner, >= 1 vote. No car wins twice. Owner may win through different cars. Ties -> provisional + admin tie button. Class ties whose outcome depends on an unresolved Best Overall tie wait for it.
12. **Finalize** writes immutable `awards` rows (snapshot of car number, vehicle, owner name, class, counts, tiebreak flag, explanation). Printing reads the snapshot.
13. **Categories** are global (CSV ids preserved). Rename blocked once referenced by a car in a non-setup event. Delete blocked if referenced. Best Overall is not a category.
14. Historical `BallotVotes` data is **not** imported into the vote ledger. Only entrants/cars into the "2025 Test Show" event with zero votes.

### Freeze rules

| Change | setup | voting_open | voting_closed / finalized |
|---|---|---|---|
| Add contestant / car | yes | yes | no |
| Change car owner or class, delete car | yes | no | no |
| Edit car vehicle text / photo | yes | yes | yes until finalized |
| Allowance override / reset | yes | yes (>= used) | no |
| Rotate login code, print ballot | yes | yes | print only |
| Category create/rename | yes (rename blocked if used by non-setup event) | create only | no |

### Locking strategy (MySQL InnoDB)

Order is always: **event row, then participant row(s)**.

- Vote submission (online + manual): `events` row `LOCK IN SHARE MODE`, then `participants` row `FOR UPDATE`, re-read state, count used votes, validate, insert. Many contestants can vote in parallel; one contestant's batches serialize.
- Allowance change, car/contestant registration: event `SHARE`, participant `FOR UPDATE`.
- Open/close/finalize/tiebreak: event `FOR UPDATE` (exclusive). Close waits for in-flight vote transactions and blocks new ones until it commits; they then see `voting_closed` and fail. The closed tally is exactly the votes committed before close.
- Unique indexes are the final guard (duplicate vote, duplicate idempotency key, duplicate tiebreak scope).
- `DB::transaction(..., attempts: 3)` retries deadlocks.

## 3. Schema

```
users(id, name, username UNIQUE NULL, password NULL, is_admin bool, email NULL, phone NULL,
      address NULL, city NULL, state NULL, zip NULL, remember_token, timestamps)
events(id, name, year, location NULL, show_date NULL, details TEXT NULL, is_test bool, is_active bool,
       status ENUM(setup,voting_open,voting_closed,finalized), votes_per_car tinyint default 5,
       voting_opened_at, voting_closed_at, finalized_at, closed_tally_hash NULL, timestamps)
categories(id [CSV id preserved], name UNIQUE, timestamps)
participants(id, event_id FK, user_id FK, voter_number NULL, login_code_encrypted TEXT,
       login_code_hash CHAR(64) UNIQUE, session_version int, allowance_override int unsigned NULL,
       code_rotated_at NULL, timestamps,
       UNIQUE(event_id,user_id), UNIQUE(event_id,voter_number), UNIQUE(id,event_id))
cars(id, event_id FK, participant_id, category_id FK RESTRICT, entry_number int unsigned,
     year NULL, make NULL, model NULL, description, photo_path NULL, thumb_path NULL, timestamps,
     UNIQUE(event_id,entry_number), UNIQUE(id,event_id),
     FK(participant_id,event_id) -> participants(id,event_id) RESTRICT)
ballot_submissions(id, event_id FK, participant_id, source ENUM(online,manual), entered_by NULL FK users,
     idempotency_key CHAR(36), vote_count, created_at,
     UNIQUE(event_id,idempotency_key), FK(participant_id,event_id) -> participants RESTRICT)
votes(id, event_id FK, participant_id, user_id FK, car_id, ballot_submission_id FK, source ENUM(online,manual),
     entered_by NULL FK users, created_at,
     UNIQUE(event_id,participant_id,car_id),
     FK(participant_id,event_id) -> participants(id,event_id), FK(car_id,event_id) -> cars(id,event_id)) all RESTRICT
award_tiebreaks(id, event_id FK, scope_key ('overall' | 'category:{id}'), category_id NULL FK,
     candidates JSON [{car_id, entry_number, votes}], tied_votes int, chosen_car_id FK cars,
     system_votes tinyint = 1, tally_hash CHAR(64), resolved_by FK users, created_at,
     UNIQUE(event_id,scope_key))
awards(id, event_id FK, scope_key, category_id NULL, position int, car_id NULL FK, outcome ENUM(winner,no_votes,no_eligible),
     contestant_votes int, system_votes int, entry_number NULL, vehicle NULL, owner_name NULL, category_name NULL,
     explanation NULL, created_at, UNIQUE(event_id,scope_key))
audit_logs(id, event_id NULL, actor_id NULL FK users, action, subject_type NULL, subject_id NULL, details JSON NULL, created_at)
```
All foreign keys `ON DELETE RESTRICT`. No cascades on ballots, votes, tiebreaks, awards, audit rows.

## 4. Code layout

- `app/Services/VoteService.php`: the only writer of votes (online + manual). `submit(Participant, array $entryNumbers|carIds, source, ?User $admin, string $idempotencyKey): BallotSubmission`, `preview(...)`.
- `app/Services/AllowanceService.php`: allowance math, override/reset under locks.
- `app/Services/RegistrationService.php`: create contestant, attach car, voter number, freeze rules.
- `app/Services/LoginCodeService.php`: generate, hash, encrypt, rotate, find.
- `app/Services/VotingLifecycleService.php`: open, close (tally hash), finalize (awards snapshot).
- `app/Services/ResultsCalculator.php`: pure calculation from tally + tiebreaks.
- `app/Services/TieBreakService.php`: secure uniform pick, persist once.
- `app/Services/ReportService.php`: report datasets + CSV rows.
- `app/Console/Commands`: `app:create-admin`, `app:seed-categories` (also via `CategorySeeder`), `app:import-2025`, `app:reset-test-event`.
- Controllers: `Public\GalleryController`, `Public\CarController`, `Auth\ContestantLoginController`, `Auth\AdminLoginController`, `Contestant\BallotController`, `Admin\{Dashboard,Event,Contestant,Car,Category,ManualBallot,Vote,Result,Report}Controller`.
- Policies: `CarPolicy`, `CategoryPolicy`, `ParticipantPolicy`, `VotePolicy` (no update/delete abilities). Middleware `admin`, `contestant` (checks participant session version).

## 5. Phases

### Phase 0: Environment + docs [x]
- [x] PHP 8.4 + vhost + CA bundle, Laravel 13 scaffold, PhpSpreadsheet, MySQL DBs
- [x] PLAN.md, AGENTS.md, CLAUDE.md, HANDOFF.md, .gitignore, git init
Accept: `curl http://secretsanta.test` served by PHP 8.4, `reference/` not public.

### Phase 1: Schema, models, domain services, tests [ ]
- [ ] Migrations + models + factories (synthetic only)
- [ ] LoginCodeService, AllowanceService, RegistrationService
- [ ] VoteService with locking + idempotency
- [ ] VotingLifecycleService, ResultsCalculator, TieBreakService, finalize snapshot
- [ ] Feature/service tests on MySQL `secretsanta_testing`; concurrency test with parallel processes
Accept: all domain tests green on MySQL.

### Phase 2: Seed + import [ ]
- [ ] `CategorySeeder` from `reference/carClasses.csv` (BOM-safe, idempotent, ids preserved)
- [ ] `app:import-2025` from `Name Entry` (cached values, real rows only, null N/A, owner matching, exception report to `storage/app/private/imports/`)
- [ ] `app:create-admin`
- [ ] Import tests with a synthetic xlsx fixture
Accept: re-running seed/import changes nothing; exception report lists issues.

### Phase 3: Auth + public + contestant UI [ ]
- [ ] Layout, design tokens, logo asset, checkered accents
- [ ] Gallery (search, class filter, pagination, tallies, refresh time), car detail
- [ ] Code login (throttled), admin login, logout, session version check
- [ ] Ballot: select, review, confirm; history; closed state
Accept: phone-width pass on gallery, login, review/confirm.

### Phase 4: Admin [ ]
- [ ] Dashboard, event settings + lifecycle buttons (open, Voting Finished confirm)
- [ ] Contestants (duplicate hints, contact, cars, allowance override/reset with reason, rotate code, printable ballot)
- [ ] Cars (register/edit, auto-number, photo upload re-encoded), categories
- [ ] Manual ballot entry (search/code, preview, confirm, idempotent)
- [ ] Results: provisional/final, tie buttons, finalize
Accept: authorization tests pass; paper entry flow works in browser.

### Phase 5: Reports [ ]
- [ ] Votes by car, votes by category, entries by class, awards, reconciliation
- [ ] Print CSS (US Letter, repeated headers), CSV export
Accept: totals reconcile in tests.

### Phase 6: Polish + docs [ ]
- [ ] impeccable/ui-ux-pro-max review pass, copy cleanup
- [ ] Browser check (phone + desktop) of gallery, login, vote confirm, paper entry, print
- [ ] README.md, deployment.md, final HANDOFF.md

## 6. Acceptance criteria (global)

See the "Focused verification" list in the original brief, mirrored as tests in `tests/Feature/*`. Every item there maps to a named test (listed in HANDOFF.md once written).
