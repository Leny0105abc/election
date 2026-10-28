# Running an election — checklist

## Two weeks before

- [ ] Settings: school name, DepEd School ID, logo, colours, current school year, report signatories.
- [ ] Staff accounts created; every staff member has enabled two-factor authentication.
- [ ] Students imported (Students → Import, use the template). Fix every row with errors.
- [ ] Ineligible students marked "Not eligible"; transferred/dropped students updated.
- [ ] Election created (Elections → New election): dates/times in Philippine time, grade levels, tie-resolution rule.
- [ ] Positions reviewed (winners, grade restrictions, abstain).
- [ ] Party lists and candidates registered, photos uploaded, candidates **approved**.

## One week before

- [ ] Schedule the election (generates the voter roll snapshot). Check the Voter Roll tab.
- [ ] Generate PINs per grade/section (Students → Generate PINs) and distribute slips privately.
- [ ] Rehearsal on a staging copy: a few test students vote on phones; close; finalize; print reports.
- [ ] Load test on staging: `php artisan schoolvote:load-test --students=75`.
- [ ] Backup taken (see DEPLOYMENT.md). Cron job confirmed running.

## Election day

- [ ] Election opens automatically at the start time (or press **Open voting**).
- [ ] Monitor the Dashboard (turnout refreshes every 30 s). Vote totals stay sealed.
- [ ] Lost PIN → Students → reset that student's PIN (shown once) after verifying identity.
- [ ] Late enrollee → add/fix the student, then **Refresh voter roll** on the election overview.
- [ ] Outage → **Extend voting time** with a reason (recorded in the audit log).
- [ ] At the end time the election closes automatically (or press **Close voting**).

## After voting closes

- [ ] Results → check the integrity banner (ballots = voters who voted).
- [ ] Resolve any **unresolved tie** using the approved method; record method, witnesses and resolution number.
- [ ] **Finalize results** (Super Administrator). Totals are now locked.
- [ ] Print and sign: Official Results, Canvassing Report, Winners, Voter Turnout, Audit Report.
- [ ] **Publish to students** when the committee approves.
- [ ] Backup the database. Archive the election when done.

If a finalized result must be corrected, follow the documented procedure: a written committee resolution, then on the server
`php artisan schoolvote:unfinalize {election-id} --reason="Committee Res. No. …"` (audited; ballots are never changed), re-canvass and finalize again.
