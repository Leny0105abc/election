# SchoolVote

Secure online election management for Philippine schools. Built first for
**Nancapian National High School** (SSLG elections, ~500 student voters,
20–50 voting at once) and reusable by any school.

Students sign in with their **Student ID or LRN + a PIN**, vote on a
mobile-friendly ballot, and receive a confirmation that never shows their
choices. Staff manage elections, candidates, party lists and the voter roll,
watch live turnout, canvass and finalize results, and print branded reports.

| | |
|---|---|
| Framework | Laravel 13, PHP 8.4 |
| UI | Blade, Livewire 4, Alpine.js, Tailwind CSS 4, Chart.js, Lucide icons |
| Database | MySQL 8 / MariaDB 10.6+ (InnoDB). SQLite works for local development only |
| Auth | Laravel Fortify for staff (with two-factor), custom student guard (ID/LRN + PIN) |
| Reports | DomPDF (PDF), Laravel Excel (XLSX), printable HTML |
| Tests | Pest (137 tests) + an HTTP concurrency test (`schoolvote:load-test`) |

## Features

- **Roles**: Super Administrator, Election Administrator, Election Committee (staff) and Student Voter.
- **Elections**: SSLG, classroom, club and other elections; lifecycle Draft → Scheduled → Open → Closed → Finalized → Archived, with automatic open/close on schedule.
- **Positions**: the 13 default SSLG positions (President … Grade 12 Representative) plus optional Peace Officer, Business Manager, Sergeant-at-Arms. Per-position number of winners, voter grades, candidate grades and abstain policy. Grade representatives are voted on only by their grade.
- **Candidates and party lists**: photos, platforms, biographies, approval/rejection/withdrawal, grade-level eligibility checks. Party lists never pre-fill a ballot.
- **Students**: CRUD, search and filters, Excel/CSV import with preview and error report, downloadable template, bulk PIN generation as printable slips, single PIN reset.
- **Voting**: step-by-step ballot with progress, review screen, confirmation dialog, abstain option; atomic, duplicate-proof submission; secret ballot.
- **Monitoring**: dashboard with turnout by grade, voted vs not voted, participation over time. Vote totals stay sealed until voting closes.
- **Results**: deterministic counting, ranks, percentages, tie detection (never auto-broken), committee tie resolution, finalization (frozen, read-only tallies), publication to students.
- **Reports** (PDF, Excel, print): Official Results, Canvassing Report, Winners, Candidate Vote Summary, Voter Turnout, Grade-Level Participation, Registered Voters, Voted, Not Voted, Audit Report — with school logo and configurable signatories.
- **Audit log**: append-only record of sign-ins and administrative actions (never ballot choices).

## Local setup (Laravel Herd on Windows)

Requirements: Laravel Herd (PHP 8.4), Composer, Node.js 20+, MySQL 8 or MariaDB.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the database and a dedicated user (in MySQL Workbench, HeidiSQL or the `mysql` client, signed in as an administrator). Choose your own password:

```sql
CREATE DATABASE schoolvote CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'schoolvote_local'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON schoolvote.* TO 'schoolvote_local'@'localhost';
FLUSH PRIVILEGES;
```

Put that password in `.env` (`DB_PASSWORD=`), then:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan schoolvote:create-admin
npm run build
```

Open `http://schoolvote.test` (link the folder in Herd with `herd link schoolvote`, or use the folder name, e.g. `http://election3.test`). Staff sign in at `/staff/login`.

> Herd's terminal uses Herd's PHP. If another PHP is first on your PATH, use `herd php artisan …`.

### Fictional demo data (local only)

```bash
php artisan db:seed --class=DemoSeeder
```

Creates ~500 fictional students (Grades 7–12), three demo staff accounts and a draft demo SSLG election with party lists and candidates. Random credentials are written to `storage/app/private/demo-credentials.csv` (git-ignored). Never run on production; the seeder refuses to.

### Running the scheduler locally

Elections open and close automatically at their scheduled times:

```bash
php artisan schedule:work
```

## Testing

```bash
php artisan test
```

Covers student/staff login, invalid PINs, rate limiting, role authorization, eligibility and grade restrictions, ballot validation, duplicate and racing submissions, closed elections, transaction rollback, counting, abstentions, ties, finalization, result sealing, Excel import, PDF/Excel/print reports, mobile markup, database constraints and ballot secrecy.

### Concurrency / load test

With the site running (Herd), simulate students voting at the same time over real HTTP. Each ballot is submitted **twice simultaneously** to imitate double-taps and retries:

```bash
php artisan schoolvote:load-test --students=75
```

It creates a temporary fictional election, runs login → open ballot → submit for every student in parallel, verifies that every student has exactly one ballot (no duplicates, nothing lost) and removes the test data. Run it against MySQL/MariaDB before the real election (SQLite is single-writer and only suitable for development). Never run it against production.

Last local run (SQLite, WAL mode, Herd on Windows): 75 students, 150 simultaneous submissions → 75 ballots, 0 duplicates, 0 errors, ~40 ms average response.

## Documentation

- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — Hostinger deployment, backups, rollback
- [docs/SECURITY.md](docs/SECURITY.md) — security model, ballot secrecy, privacy limitations, Data Privacy Act notes
- [docs/ELECTION-DAY.md](docs/ELECTION-DAY.md) — step-by-step checklist for running an election

## Repository hygiene

Never commit `.env`, passwords, API keys, database exports, student lists, PIN slips or backups. `.gitignore` already excludes `.env`, `storage/app/private`, logs and build output.
