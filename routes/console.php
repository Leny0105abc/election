<?php

use App\Enums\ElectionStatus;
use App\Enums\Role;
use App\Models\Election;
use App\Models\ElectionResult;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogger;
use App\Services\ElectionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

/*
| Opens scheduled elections at their start time and closes them at their
| end time. Requires the Laravel scheduler cron entry (see docs/DEPLOYMENT.md).
*/
Artisan::command('elections:sync-status', function (ElectionService $service) {
    $result = $service->syncStatuses();
    $this->info("Opened {$result['opened']}, closed {$result['closed']}.");
})->purpose('Open/close elections according to their schedule');

Schedule::command('elections:sync-status')->everyMinute()->withoutOverlapping();

/*
| Creates (or promotes) a staff account. Use this to create the first
| Super Administrator instead of seeding a default password.
*/
Artisan::command('schoolvote:create-admin {--role=super_admin}', function () {
    $role = Role::tryFrom((string) $this->option('role'));
    if (! $role) {
        $this->error('Role must be one of: '.implode(', ', array_column(Role::cases(), 'value')));

        return 1;
    }

    $name = $this->ask('Full name');
    $email = strtolower((string) $this->ask('Email address'));
    $password = $this->secret('Password (min 12 chars, upper/lower case, number, symbol)');
    $confirm = $this->secret('Confirm password');

    $validator = Validator::make(
        ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
        ['name' => 'required|max:255', 'email' => 'required|email|max:255', 'password' => ['required', 'confirmed', User::passwordRule()]],
    );

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = User::updateOrCreate(['email' => $email], [
        'name' => $name,
        'password' => $password,
        'role_id' => UserRole::idFor($role),
        'is_active' => true,
    ]);

    AuditLogger::log('staff.created_via_cli', $user, ['role' => $role->value]);
    $this->info("{$role->label()} account ready for {$email}.");

    return 0;
})->purpose('Create or update a staff account (e.g. the first Super Administrator)');

/*
| DOCUMENTED ADMINISTRATIVE PROCEDURE — un-finalize an election.
| Only for correcting a finalization error, with written approval of the
| Election Committee. Requires server (SSH) access, is fully audited, and
| returns the election to "Closed". Ballots are never modified.
*/
Artisan::command('schoolvote:unfinalize {election} {--reason=}', function () {
    $election = Election::find($this->argument('election'));
    $reason = trim((string) $this->option('reason'));

    if (! $election || $election->status !== ElectionStatus::Finalized) {
        $this->error('Election not found or not finalized.');

        return 1;
    }

    if (strlen($reason) < 15) {
        $this->error('Provide --reason with the committee resolution reference (at least 15 characters).');

        return 1;
    }

    if (! $this->confirm("Return \"{$election->name}\" to Closed and discard the frozen tally? Ballots are kept.")) {
        return 1;
    }

    DB::transaction(function () use ($election, $reason) {
        ElectionResult::where('election_id', $election->id)->delete();
        $election->status = ElectionStatus::Closed;
        $election->finalized_at = null;
        $election->finalized_by = null;
        $election->results_published_at = null;
        $election->save();
        AuditLogger::log('results.unfinalized', $election, ['reason' => $reason], 'Un-finalized via CLI: '.$reason);
    });

    $this->info('Election returned to Closed. Re-run canvassing and finalize again.');

    return 0;
})->purpose('Documented procedure: return a finalized election to Closed');
