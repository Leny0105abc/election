<?php

use App\Enums\ElectionStatus;
use App\Enums\Role;
use App\Exceptions\ElectionStateException;
use App\Livewire\Admin\Elections\Candidates;
use App\Livewire\Admin\Elections\Form;
use App\Livewire\Admin\Elections\Positions;
use App\Livewire\Admin\Elections\Show;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Position;
use App\Services\CandidateEligibilityService;
use App\Services\ElectionService;
use Carbon\CarbonImmutable;
use Database\Seeders\PositionSeeder;
use Livewire\Livewire;

it('creates an election with the 13 default positions', function () {
    $this->seed(PositionSeeder::class);
    $admin = staff(Role::ElectionAdmin);

    Livewire::actingAs($admin)->test(Form::class)
        ->set('name', 'SSLG Election 2026-2027')
        ->set('type', 'sslg')
        ->set('starts_at', '2026-11-05T07:00')
        ->set('ends_at', '2026-11-05T16:00')
        ->call('save')
        ->assertHasNoErrors();

    $election = Election::firstWhere('name', 'SSLG Election 2026-2027');
    expect($election->status)->toBe(ElectionStatus::Draft)
        ->and($election->positions()->count())->toBe(13)
        ->and($election->positions()->where('name', 'Grade 9 Representative')->first()->eligible_voter_grades)->toBe([9])
        // Entered as Manila time (UTC+8), stored as UTC.
        ->and($election->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-04 23:00');

    expect(Position::where('name', 'Peace Officer')->exists())->toBeTrue()
        ->and($election->positions()->where('name', 'Peace Officer')->exists())->toBeFalse();
});

it('schedules only when ready and snapshots the voter roll', function () {
    $s = votingElection(ElectionStatus::Draft);
    makeStudent(9);
    $service = app(ElectionService::class);

    $service->schedule($s['election']);

    expect($s['election']->fresh()->status)->toBe(ElectionStatus::Scheduled)
        ->and(ElectionVoter::where('election_id', $s['election']->id)->count())->toBeGreaterThan(0);
});

it('blocks scheduling an election with no approved candidates', function () {
    $election = Election::factory()->create();
    makeStudent(9);

    expect(fn () => app(ElectionService::class)->schedule($election))
        ->toThrow(ElectionStateException::class, 'approved candidate');
});

it('opens, closes and archives through the allowed transitions only', function () {
    $s = votingElection(ElectionStatus::Draft);
    $service = app(ElectionService::class);

    expect(fn () => $service->open($s['election']))->toThrow(ElectionStateException::class);

    $service->schedule($s['election']);
    $service->open($s['election']->fresh());
    expect($s['election']->fresh()->status)->toBe(ElectionStatus::Open);

    $service->close($s['election']->fresh());
    expect($s['election']->fresh()->status)->toBe(ElectionStatus::Closed);

    expect(fn () => $service->archive($s['election']->fresh()))->toThrow(ElectionStateException::class);
});

it('locks schedule changes once an election is open', function () {
    $s = votingElection();
    expect(fn () => app(ElectionService::class)->update($s['election'], ['starts_at' => now()->subDays(3)]))
        ->toThrow(ElectionStateException::class);
});

it('locks positions and candidates once voting opens', function () {
    $s = votingElection();
    $admin = staff(Role::ElectionAdmin);

    Livewire::actingAs($admin)->test(Positions::class, ['election' => $s['election']])
        ->call('delete', $s['g8rep']->id)
        ->assertDispatched('notify', type: 'error');

    Livewire::actingAs($admin)->test(Candidates::class, ['election' => $s['election']])
        ->call('withdraw', $s['c']['p1']->id)
        ->assertDispatched('notify', type: 'error');

    expect($s['g8rep']->fresh())->not->toBeNull()
        ->and($s['c']['p1']->fresh()->status->value)->toBe('approved');
});

it('requires a reason and a super admin to reopen a closed election', function () {
    $s = votingElection(ElectionStatus::Closed);

    Livewire::actingAs(staff(Role::ElectionAdmin))->test(Show::class, ['election' => $s['election']])
        ->set('newEndsAt', now()->addHour()->format('Y-m-d\TH:i'))
        ->set('reason', 'Power outage in Building B')
        ->call('reopen')
        ->assertForbidden();

    expect(fn () => app(ElectionService::class)->reopen($s['election'], CarbonImmutable::now()->addHour(), ''))
        ->toThrow(ElectionStateException::class);

    app(ElectionService::class)->reopen($s['election'], CarbonImmutable::now()->addHour(), 'Committee Res. 12: power outage');
    expect($s['election']->fresh()->status)->toBe(ElectionStatus::Open);
});

it('cannot reopen a finalized election from the app', function () {
    $s = votingElection(ElectionStatus::Finalized);
    expect(fn () => app(ElectionService::class)->reopen($s['election'], CarbonImmutable::now()->addHour(), 'Please reopen'))
        ->toThrow(ElectionStateException::class);
});

it('auto-opens and auto-closes elections on schedule', function () {
    $s = votingElection(ElectionStatus::Draft);
    app(ElectionService::class)->schedule($s['election']);

    $this->artisan('elections:sync-status')->assertSuccessful();
    expect($s['election']->fresh()->status)->toBe(ElectionStatus::Open);

    $this->travel(7)->hours();
    $this->artisan('elections:sync-status')->assertSuccessful();
    expect($s['election']->fresh()->status)->toBe(ElectionStatus::Closed);
});

it('checks candidate grade eligibility', function () {
    $s = votingElection(ElectionStatus::Draft);
    $g9 = makeStudent(9);
    $service = app(CandidateEligibilityService::class);

    expect($service->problems($g9, $s['g7rep']))->toContain('Grade 7 Representative is limited to candidates from Grade 7.')
        ->and($service->problems($g9, $s['president']))->toBe([]);

    // Already a candidate in the election.
    $existing = $s['c']['p1']->student;
    expect($service->problems($existing, $s['councilor']))->toContain('The student is already a candidate in this election.');
});

it('lets committee members monitor but not change election status', function () {
    $s = votingElection();
    Livewire::actingAs(staff(Role::Committee))->test(Show::class, ['election' => $s['election']])
        ->assertOk()
        ->call('close')
        ->assertForbidden();

    expect($s['election']->fresh()->status)->toBe(ElectionStatus::Open);
});

it('applies the election policy to editing and deleting', function () {
    $admin = staff(Role::ElectionAdmin);
    $draft = Election::factory()->create();
    $finalized = votingElection(ElectionStatus::Finalized)['election'];

    expect($admin->can('update', $draft))->toBeTrue()
        ->and($admin->can('delete', $draft))->toBeTrue()
        ->and($admin->can('update', $finalized))->toBeFalse()
        ->and($admin->can('delete', $finalized))->toBeFalse()
        ->and(staff(Role::Committee)->can('update', $draft))->toBeFalse();

    $this->actingAs($admin)->get("/admin/elections/{$finalized->id}/edit")->assertForbidden();
});
