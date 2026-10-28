<?php

use App\Enums\ElectionStatus;
use App\Enums\Role;
use App\Exceptions\ElectionStateException;
use App\Livewire\Admin\Elections\Results;
use App\Models\ElectionResult;
use App\Services\ResultFinalizationService;
use App\Services\VoteSubmissionService;
use Livewire\Livewire;

function closedElectionWithVotes(bool $tie = false): array
{
    $s = votingElection();
    $vote = function (int $candidate) use ($s) {
        $student = makeStudent(10);
        syncRoll($s['election']);
        app(VoteSubmissionService::class)->submit($s['election']->fresh(), $student, [
            $s['president']->id => [$candidate],
            $s['councilor']->id => [$s['c']['c1']->id],
        ]);
    };
    $vote($s['c']['p1']->id);
    $vote($s['c']['p1']->id);
    $vote($s['c']['p2']->id);
    if ($tie) {
        $vote($s['c']['p2']->id);
    }

    $s['election']->update(['status' => ElectionStatus::Closed]);
    $s['election']->status = ElectionStatus::Closed;
    $s['election']->save();

    return $s;
}

it('finalizes a closed election and freezes the tally', function () {
    $s = closedElectionWithVotes();
    $admin = staff(Role::SuperAdmin);

    app(ResultFinalizationService::class)->finalize($s['election'], $admin);

    $election = $s['election']->fresh();
    expect($election->status)->toBe(ElectionStatus::Finalized)
        ->and($election->finalized_by)->toBe($admin->id)
        ->and(ElectionResult::where('candidate_id', $s['c']['p1']->id)->value('votes'))->toBe(2)
        ->and(ElectionResult::where('candidate_id', $s['c']['p1']->id)->value('is_winner'))->toBeTrue();
});

it('refuses to finalize while voting is open', function () {
    $s = votingElection();
    expect(fn () => app(ResultFinalizationService::class)->finalize($s['election'], staff()))
        ->toThrow(ElectionStateException::class);
});

it('refuses to finalize with an unresolved tie, then accepts after resolution', function () {
    $s = closedElectionWithVotes(tie: true);
    $admin = staff();
    $service = app(ResultFinalizationService::class);

    expect(fn () => $service->finalize($s['election'], $admin))->toThrow(ElectionStateException::class, 'Resolve every tie');

    $service->resolveTie($s['election'], $s['president'], [$s['c']['p2']->id], 'Toss coin on Oct 28, witnessed by both candidates. Res. No. 5', $admin);
    $service->finalize($s['election']->fresh(), $admin);

    expect(ElectionResult::where('candidate_id', $s['c']['p2']->id)->value('is_winner'))->toBeTrue()
        ->and(ElectionResult::where('candidate_id', $s['c']['p1']->id)->value('is_winner'))->toBeFalse();
});

it('rejects an invalid tie resolution', function () {
    $s = closedElectionWithVotes(tie: true);
    expect(fn () => app(ResultFinalizationService::class)->resolveTie($s['election'], $s['president'], [$s['c']['p1']->id, $s['c']['p2']->id], 'Both win somehow', staff()))
        ->toThrow(ElectionStateException::class);
});

it('does not allow official vote totals to be edited', function () {
    $s = closedElectionWithVotes();
    app(ResultFinalizationService::class)->finalize($s['election'], staff());

    $row = ElectionResult::where('candidate_id', $s['c']['p1']->id)->first();
    expect(fn () => $row->update(['votes' => 999]))->toThrow(LogicException::class);
});

it('keeps vote totals sealed while voting is open', function () {
    $s = votingElection();
    Livewire::actingAs(staff())->test(Results::class, ['election' => $s['election']])
        ->assertSee('Results are sealed')
        ->assertDontSee('Alpha President');
});

it('shows the canvass to the committee after closing but only super admins can finalize', function () {
    $s = closedElectionWithVotes();

    Livewire::actingAs(staff(Role::Committee))->test(Results::class, ['election' => $s['election']])
        ->assertSee('Alpha President')
        ->call('finalize')
        ->assertForbidden();

    Livewire::actingAs(staff(Role::ElectionAdmin))->test(Results::class, ['election' => $s['election']])
        ->call('finalize')
        ->assertForbidden();

    Livewire::actingAs(staff(Role::SuperAdmin))->test(Results::class, ['election' => $s['election']])
        ->call('finalize')
        ->assertDispatched('notify');

    expect($s['election']->fresh()->status)->toBe(ElectionStatus::Finalized);
});

it('shows results to students only after publication', function () {
    $s = closedElectionWithVotes();
    $student = makeStudent(10);
    $service = app(ResultFinalizationService::class);
    $service->finalize($s['election'], staff());

    $this->actingAs($student, 'student')->get(route('student.results', $s['election']))->assertNotFound();

    $service->publish($s['election']->fresh());

    $this->actingAs($student, 'student')->withSession(['student_last_activity' => time()])
        ->get(route('student.results', $s['election']))
        ->assertOk()->assertSee('Alpha President')->assertSee('Winner');
});
