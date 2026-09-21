# DigiPay Ghana

A digital fee-payment platform for a Ghanaian tertiary institution (final year project,
GCTU BSc Business Information Technology). See `DigiPay_Ghana_PRD.pdf` and
`DigiPay_Ghana_ClaudeCode_Prompt.md` for the full requirements.

## Sprint 1: Auth + schema

- MySQL schema via hand-rolled migrations (`database/migrations/`)
- Demo seed data (`database/seed.php`)
- Session-based auth: login, logout, RBAC middleware (deny-by-default)
- Account lockout after 5 failed attempts (15 min lock), logged to `audit_logs`
- CSRF protection on all state-changing forms
- Session timeout (20 min inactivity) with re-auth redirect
- Rate limiting on `/login` and `/password-reset` (file-based, per IP)
- Password reset flow (token emailed — for this prototype the "email" is
  written to `storage/logs/mail.log` instead of sent, since no SMTP is
  configured). Completing a reset also clears any active lockout.
## Sprint 2: Student dashboard

- Balance + itemized fee breakdown (`FeeService`), computed live from
  `fee_structures` minus verified/pending amounts per category
- Payment flow (full or partial, oldest-due-first allocation, capped so a
  mistyped amount can't overpay): amount → method → confirm → simulated
  processing → success/failure, all state-changing steps CSRF-protected
- `PaymentSimulator` stands in for a live gateway — a weighted coin flip
  (`PAYMENT_SIMULATOR_SUCCESS_RATE`) decides the outcome; `PaymentService`
  resolves a batch idempotently (a repeated callback for an already-resolved
  attempt is logged as `duplicate_callback` and returns the cached outcome
  instead of reprocessing)
- Receipts generated on demand and access-checked against the requesting
  student (never a static file). "Download" is implemented as
  print-to-PDF (`window.print()`) rather than a generated PDF file — a
  deliberate simplification; swapping in a PDF library (e.g. dompdf) later
  wouldn't change the receipt data model
- Payment history with status filters, disputes (raise + track), profile
  edit (name/email/phone), in-app notifications (created on payment
  verified/failed)
- Overpayment guard: allocation is capped during preview so it shouldn't
  occur in the normal flow, but `resolveBatch()` defensively clamps and
  logs `overpayment_detected` if it ever would

## Sprint 3: Accounts Office dashboard

**Architectural correction from Sprint 2:** a simulator-confirmed payment now
lands as `pending` rather than auto-`verified`. The provider confirming a
charge and the institution accepting it are different events — FR-ACC-03
requires a human to verify/reject before a receipt is issued, which Sprint 2
had skipped entirely. `PaymentService::resolveBatch()` now stops at `pending`
+ notifies the student; `TransactionReviewService` (Office) does the
verify/reject that used to happen automatically.

- Live transactions feed with search (name/ID/transaction #) and status
  filter (all/pending/verified/failed)
- Verify / reject a pending transaction, each optionally paired with a
  reconciliation remark; verifying issues the receipt and notifies the
  student, rejecting zeroes `amount_paid` and notifies with the reason
- Reconciliation remarks are stored as audit log entries
  (`action = 'reconciliation_remark'`) rather than a new column, since the
  given schema has no remarks field on Transaction — the detail page reads
  them back as a timeline
- Fee structure CRUD that respects effective-dating (FR-ACC-06): revising an
  amount closes the current row as of yesterday and inserts a new one
  effective today, so already-verified transactions keep resolving against
  the fee amount that was actually in force when they were paid — verified
  live (`fee_id=1` closed at ₵3,200, `fee_id=7` opened at ₵3,400)
- Reports: date-range summary (total received, verified/pending/failed
  counts) and a streamed CSV export. PDF export is not implemented (the
  design mock shows a PDF button) — same simplification as the student
  receipt's print-to-PDF; a real export library can be added later without
  touching the report data itself
- Dispute queue: open → under review → resolved/rejected, with notes fed
  back to the student as a notification

Verified live end-to-end: student payment → office transaction list shows it
pending → verify with remarks → receipt issued + audit trail
(`payment_initiated` → `payment_received` → `reconciliation_remark` →
`payment_verified_by_office`) → student sees the receipt. Also verified the
reject path, fee revision history, CSV export content, the dispute
respond → student-sees-response loop, and that RBAC still denies all
cross-role access (student → office, office → student, office → admin all
403).

All of the above was driven end-to-end against a live MySQL + PHP dev server
during development, not just linted.

## Sprint 4: Administrator dashboard

- User management: create/edit/deactivate/reactivate/soft-delete accounts for
  all three roles, with role reassignment (converting a user to `student`
  on the fly creates the missing `students` row if program/level are
  supplied). Self-protection guards: an admin cannot demote, deactivate, or
  delete their own account
- Deletion is a soft delete (`deleted_at`) surfaced as a "Deleted" filter
  rather than hidden, so the account and its audit history stay intact and
  restorable
- Audit log viewer: searchable, filterable by success/failure, with a
  best-effort category badge (`payment` / `security` / `user` / `config` /
  `system`) derived from the action name for readability — the category
  itself isn't stored, since the schema doesn't define one
- System Settings is intentionally read-only/derived rather than fake CRUD:
  fee categories and academic terms are *distinct values already present* in
  `fee_structures` (there's no separate categories/terms table in the given
  schema, and Accounts Office already free-types a category per fee item —
  adding a second, disconnected "manage categories" screen that doesn't
  actually constrain fee creation would be misleading UI, not a real feature).
  Notification templates are shown as reference text (they're generated in
  code, not stored/edited in the DB)
- Analytics overview: real-time total collections, active users by role,
  pending-transaction count, and a 6-month collections trend chart, all
  computed live from `transactions`/`users` — no mock numbers
- Backup (Could-priority, FR-ADM-07): deliberately minimal and safe —
  `BackupService` dumps every table's rows to a timestamped JSON file via
  PDO (never a shell-out to `mysqldump`, which would be its own command-
  injection surface for a "Could" feature), stripping `password_hash` /
  `reset_token_hash` before writing. **Restore is intentionally not
  implemented** — the UI shows why: blindly overwriting live data from a
  file is a destructive action disproportionate to this feature's priority.
  Backup history is just a directory listing (no DB table needed)

Verified live: created a new Accounts Office user, logged in as them
immediately; deactivated them and confirmed login is blocked with the
correct message; deleted, saw them under the Deleted filter, restored, saw
them active again; converted that user to a `student` role with program/
level and confirmed they could then reach `/student`; confirmed an admin
cannot deactivate/demote their own account; ran a full RBAC matrix (below);
generated a backup and confirmed (after catching a red herring in my own
test script) zero occurrences of `password_hash` in the output file.

**A bug the automated test suite caught, not manual testing:** during Sprint
5's unit tests, `FeeService::balanceFor()` was found to mislabel a fee item
that's *fully* covered by one pending payment as `paid` instead of
`pending` — the `remaining <= 0` check ran before the `pending > 0` check,
so a fully-covered-but-unverified item looked "done" to the student. Manual
testing across Sprints 2–3 never hit this because those runs always paid
partial amounts that left `remaining > 0`. Fixed by checking `pending`
first; re-verified live afterward (see `src/Services/FeeService.php`).

## Sprint 5: Cross-role integration, testing

- **PHPUnit 11** (installed as a standalone `phpunit.phar` — no Composer
  needed) with 21 tests / 76 assertions, all passing, focused on the
  payment/reconciliation logic as the build brief asked:
  - `PaymentService`: allocation preview (full, partial, oldest-due-first,
    overpayment capping, "nothing owed" error), batch creation, and —the
    core requirement — **idempotent** `resolveBatch()` (duplicate callback
    returns the cached outcome, doesn't double-credit, doesn't issue a
    second receipt)
  - `TransactionReviewService`: verify issues exactly one receipt, reject
    zeroes `amount_paid`, and a transaction can't be verified or rejected
    twice
  - `FeeStructureService`: revising a fee preserves history via
    effective-dating rather than overwriting it
  - `FeeService`: balance status transitions across unpaid/pending/paid/failed
  - **Integration test** (`tests/Integration/PaymentLifecycleTest.php`):
    the full pay → provider callback → office verify → student receipt
    flow end-to-end, including a receipt-ownership check (student B cannot
    fetch student A's receipt) and the duplicate-callback case
  - Tests run against a disposable `digipay_ghana_test` database (created
    and migrated automatically by `tests/bootstrap.php`), truncated between
    tests — never touches your dev/demo data
  - **Known gap**: no Xdebug/PCOV is installed in this XAMPP setup, so
    there's no numeric coverage percentage — reporting one without a
    coverage driver would just be a guess. The 21 tests deliberately target
    every branch of the payment/reconciliation/idempotency logic; running
    `phpunit.phar --coverage-html` after installing Xdebug will produce a
    real report if your dissertation needs the percentage itself
- **Manual RBAC checklist** — every {student, office, admin} × 11 protected
  routes combination tested live (33 total): the 3 "home" routes return 200
  for their own role, everything else returns 403. Zero unauthorized
  cross-role access, matching PRD success metric §14. Unauthenticated
  requests to any guarded route redirect to `/login` (verified for one route
  per role tier)
- **Cross-role notification wiring**: already threaded through since
  Sprint 2/3 (payment pending/verified/rejected, dispute responses all
  create a `notifications` row for the affected student) — Sprint 5 confirmed
  the full chain end-to-end: student pays → notification created → office
  verifies → second notification created → shows correctly in
  `/student/notifications` → and the same event is independently visible in
  the admin audit log and collections total

To run the tests yourself:
```
C:\xampp\php\php.exe phpunit.phar --testdox
```

No Composer install is required to run the app itself: `src/bootstrap.php`
registers a small PSR-4 autoloader by hand. `composer.json` is included for
IDE tooling; `phpunit.phar` (downloaded directly, gitignored) is what
actually runs the tests above.

## Known limitations (deliberate scope decisions, not oversights)

- **No PDF export** for receipts or reports — both use browser print-to-PDF
  instead. Swapping in a real PDF library later wouldn't change any data model.
- **Backup has no restore** — see Sprint 4 section above.
- **System Settings is read-only/derived** — see Sprint 4 section above.
- **Rate limiting is file-based**, scoped to a single server process/machine
  — fine for this deployment target (XAMPP, single instance), would need a
  shared backend (Redis, DB) to work across multiple app servers.
- **Login and password-reset-completion use GET-triggered navigation**
  where a state-changing POST would be marginally more correct (e.g.
  `/logout` is a plain link) — an extremely common, low-risk simplification
  in real-world apps of this size.

## Requirements

- PHP 8.2+ (bundled with XAMPP at `C:\xampp\php\php.exe`)
- MySQL 8 (bundled with XAMPP)

## Setup

1. Copy `.env.example` to `.env` and adjust DB credentials if needed (defaults
   match a stock XAMPP install: `root` with no password on `127.0.0.1:3306`).
2. Make sure MySQL is running (XAMPP Control Panel → Start MySQL).
3. Run the migrations (creates the `digipay_ghana` database and all tables):
   ```
   C:\xampp\php\php.exe database/migrate.php
   ```
4. Seed demo data (institution, 3 users, fee structure):
   ```
   C:\xampp\php\php.exe database/seed.php
   ```
5. Run the app:
   - **Quick local testing** (no Apache config needed):
     ```
     C:\xampp\php\php.exe -S localhost:8000 -t public
     ```
     then open http://localhost:8000
   - **Via XAMPP Apache**: point your vhost's `DocumentRoot` at this
     project's `public/` folder (recommended), or copy/symlink this whole
     project into `C:\xampp\htdocs\digipay-ghana` and browse to
     `http://localhost/digipay-ghana/public/`.

### Demo credentials (seeded)

| Role           | Login ID       | Password       |
|----------------|----------------|----------------|
| Student        | STU/2024/0847  | DigiPay@2026   |
| Accounts Office| STF/0001       | DigiPay@2026   |
| Administrator  | ADM/0001       | DigiPay@2026   |

## Project structure

```
public/            Web root — front controller, .htaccess, CSS
src/
  Config/          Env loader, PDO connection
  Core/            Router, Request/Response, Session, CSRF, RateLimiter, Middleware, Money
  Support/         Migrator (shared by database/migrate.php and the test bootstrap)
  Controllers/
    Student/       Home, Payment, History, Receipt, Dispute, Profile, Notification
    Office/        Transaction, FeeStructure, Report, Dispute
    Admin/         Overview, User, AuditLog, Settings, Backup
  Services/        Business logic — Auth, PasswordReset, Payment(+Simulator), Receipt,
                   TransactionReview, FeeStructure, Report, Dispute(+Office), Profile,
                   AdminUser, AdminAnalytics, Backup, Notification, AuditLogger
  Repositories/    All SQL (prepared statements only)
  Views/           Server-rendered PHP templates, one subfolder per role + shared partials
database/
  migrations/      Numbered .sql files, applied in order by migrate.php
  migrate.php      Migration runner (idempotent, tracks applied files)
  seed.php         Demo data seeder (idempotent)
tests/
  bootstrap.php    Points DB_NAME at a throwaway test DB, migrates it
  TestCase.php     Shared fixture helpers (makeStudent, makeFeeStructure, etc.)
  Unit/            PaymentService, TransactionReviewService, FeeService, FeeStructureService
  Integration/     Full pay → callback → verify → receipt lifecycle
storage/
  logs/            mail.log (simulated password-reset emails)
  ratelimit/       Rate limiter state files
  backups/         Admin-triggered JSON dumps (gitignored)
```

## Design source

`DigiPay Ghana.dc.html` is the approved visual design (Claude Design canvas
export). `public/assets/css/tokens.css` extracts its color palette and radii;
`public/assets/css/app.css` implements the shared components (auth card,
buttons, alerts, app header) used by the server-rendered views so they match
pixel-for-pixel rather than reinventing the look.

**Post-launch layout split**: the original design was mobile-first
throughout. The Student experience keeps that exactly as designed (bottom
nav, stacked cards) — it's what a student actually uses, on a phone, to pay
fees. Accounts Office and Administrator are desk-bound, data-dense roles, so
they were rebuilt around a fixed left sidebar (`.sidebar` / `.desktop-shell`
in `app.css`, `office_chrome_*.php` / `admin_chrome_*.php` in
`src/Views/partials/`) with wide multi-column content: real `<table>`s for
transactions/users/audit log instead of stacked mobile cards, and two-column
form+detail layouts (`.desktop-two-col`) instead of everything stacking
vertically. The sidebar collapses to an icon rail under 860px so the desktop
layout still functions on a narrower window rather than overflowing, but it
is not a mobile-optimized experience — that's the Student side's job. All
existing routes, controllers, and business logic are unchanged; this was a
view-layer-only change, re-verified live end-to-end afterward (transaction
verify, fee revision, dispute respond all re-tested through the new UI).

## Status

All five sprints from the build brief are implemented and verified live
(see each Sprint section above for what was specifically tested). What's
left is optional polish, not missing functionality — see
"Known limitations" above for the deliberate scope boundaries.
