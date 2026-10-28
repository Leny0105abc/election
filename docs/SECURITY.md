# SchoolVote security and privacy model

## Accounts and authentication

| Who | How | Protections |
|---|---|---|
| Staff (Super Admin, Election Admin, Committee) | Email + password via Laravel Fortify | 12+ char passwords with mixed case, number, symbol (and breached-password check in production); TOTP two-factor (can be required with `SCHOOLVOTE_REQUIRE_STAFF_2FA=true`); 5 attempts/min per email+IP; no self-registration; inactive accounts blocked |
| Students | Student ID **or** LRN + numeric PIN, separate `student` guard | PIN stored only as a bcrypt hash; generic error for every failure (no account enumeration, including timing); 5 attempts/min per ID and 120/min per IP (shared school Wi-Fi); session ID regenerated on login; 10-minute idle sign-out; LRN is never used as a password |

PINs are generated with `random_int`, weak patterns are rejected, and the plain PIN exists only in the printed slip PDF (or the one-time reset dialog). It is never logged or stored.

## Authorization

Permissions are defined per role in `app/Enums/Role.php` and enforced through Gates, `ElectionPolicy`, route `can:` middleware **and** inside every Livewire action (Livewire actions are public endpoints, so each one re-checks).

| Permission | Super Admin | Election Admin | Committee |
|---|:-:|:-:|:-:|
| Settings, staff accounts | ✓ | | |
| Elections, positions, candidates, party lists | ✓ | ✓ | view |
| Open / close / extend voting | ✓ | ✓ | |
| Reopen a closed election (with reason) | ✓ | | |
| Resolve ties, finalize, publish results | ✓ | | |
| Students, import, PINs | ✓ | ✓ | |
| Turnout monitoring | ✓ | ✓ | ✓ |
| Canvass (after voting closes) | ✓ | ✓ | ✓ |
| Voter lists (names) | ✓ | ✓ | |
| Audit log | ✓ | | ✓ |

## Ballot integrity

`app/Services/VoteSubmissionService.php` records a ballot in **one database transaction**:

1. Re-reads the election under a shared lock and checks it is Open and inside the voting window (closing an election takes an exclusive lock, so it waits for in-flight ballots).
2. Locks the student's voter-roll row (`SELECT … FOR UPDATE`).
3. Re-validates every choice server-side: every ballot position answered, candidates approved and belonging to that position, grade restrictions from the roll snapshot, selection limits, abstain policy, no duplicates.
4. Flips `has_voted` with a compare-and-set `UPDATE … WHERE has_voted = 0`; if 0 rows change, the ballot is rejected.
5. Inserts the anonymous ballot and selections, then commits.

Any failure rolls back everything. Database constraints back this up: one voter-roll row per student per election, one candidacy per student per election, no duplicate selection per ballot, foreign keys that stop deleting voted-for candidates. The student only sees "recorded" after the database commit; failures say clearly that the vote was **not** recorded. Official tallies are frozen at finalization and cannot be edited through the app. An integrity check (ballots = voters marked as voted) runs on the canvass page, reports and before finalization.

## Ballot secrecy

- `election_voters` (who voted) and `anonymous_ballots` / `ballot_selections` (what was chosen) share **no** foreign key, voter id or timestamp.
- Ballot and selection ids are random UUIDv4 (not sequential, not time-ordered), so their storage order does not follow voting order.
- `voted_at` is rounded down to the minute.
- No screen, report, export or log shows an individual's choices. The confirmation page shows no choices and there is no receipt code.
- No staff screen lists raw ballots; only aggregate counts are shown, and only after voting closes.
- The audit logger strips keys such as `pin`, `password`, `selections`, `candidate_id`.

### Known limitations (be transparent with the committee)

1. **Database administrators** with direct server access can see low-level artefacts (binary logs, InnoDB transaction ids, the MySQL general log if enabled) that could, with effort, correlate the timing of a voter-roll update with a ballot insert. Mitigation: restrict DB and SSH access to the system administrator, keep the general query log off, and treat backups as confidential.
2. **Small groups**: a ballot that contains a Grade 12 Representative choice came from a Grade 12 student. If very few students from one grade vote, their combined choices are easier to infer. Mitigation: publish results only per position (as SchoolVote does) and encourage full turnout.
3. **Shared devices**: a student who leaves a ballot open on a lab computer could be seen. Mitigations: idle sign-out, no-store caching headers, "Sign out" prompts; supervise voting stations.
4. **PIN distribution** is a physical process — slips must be handed privately and unused slips destroyed.
5. Audit log rows are append-only in the application, but a database administrator can still alter tables directly. Export the Audit Report PDF right after finalization and keep it with the signed results.

## Data Privacy Act of 2012 (RA 10173) notes

- **Data minimization**: only Student ID, optional LRN, name, grade, section, enrollment and eligibility are stored. No birthdays, addresses, phone numbers or photos of voters (only candidate photos, with consent).
- **Purpose limitation**: data is used only to run school elections.
- **Security**: HTTPS, hashed credentials, role-based access, audit trail, encrypted sessions, security headers, no caching of signed-in pages.
- **Retention**: suggested policy — keep finalized results and reports permanently; delete or anonymize voter-roll participation records and PIN hashes one school year after the election; remove students who left the school. Ask the school's Data Protection Officer to approve the retention period.
- Use **fictional data** for testing. Never import real student records into a local or staging copy that is not secured.
- Inform students and parents (privacy notice) about what is collected and why.

## Other web protections

CSRF tokens on every form, Eloquent/query-builder parameter binding (no raw SQL with user input), Blade escaping, upload validation (image type, size, dimensions; stored with random names), `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS on HTTPS, forced HTTPS URLs in production, destructive Artisan commands blocked in production.
