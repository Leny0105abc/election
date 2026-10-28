<?php

use App\Enums\ElectionStatus;
use App\Livewire\Student\Ballot;
use App\Livewire\Student\Portal;
use App\Services\VoteSubmissionService;
use Livewire\Livewire;

it('shows the portal with the vote button for an eligible student', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    Livewire::actingAs($student, 'student')->test(Portal::class)
        ->assertSee($s['election']->name)
        ->assertSee('Vote now')
        ->assertSee('You have not voted yet.');
});

it('only shows positions the voter grade may vote for', function () {
    $s = votingElection();
    $student = makeStudent(7);
    syncRoll($s['election']);

    $component = Livewire::actingAs($student, 'student')->test(Ballot::class, ['election' => $s['election']]);
    $names = $component->instance()->positions->pluck('name')->all();

    expect($names)->toBe(['President', 'Councilor', 'Grade 7 Representative']);
});

it('navigates, enforces answers and limits selections', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    Livewire::actingAs($student, 'student')->test(Ballot::class, ['election' => $s['election']])
        ->assertSee('President')
        ->assertSee('Alpha President')
        ->call('next')                                   // unanswered -> stays
        ->assertSet('step', 0)
        ->assertDispatched('notify')
        ->call('toggleCandidate', $s['president']->id, $s['c']['p1']->id)
        ->call('toggleCandidate', $s['president']->id, $s['c']['p2']->id) // single seat: replaces
        ->assertSet('selections.'.$s['president']->id, [$s['c']['p2']->id])
        ->call('next')
        ->assertSet('step', 1)
        ->call('toggleCandidate', $s['councilor']->id, $s['c']['c1']->id)
        ->call('toggleCandidate', $s['councilor']->id, $s['c']['c2']->id)
        ->call('toggleCandidate', $s['councilor']->id, $s['c']['c3']->id) // over the limit of 2
        ->assertSet('selections.'.$s['councilor']->id, [$s['c']['c1']->id, $s['c']['c2']->id])
        ->call('toggleAbstain', $s['councilor']->id)        // abstain not allowed here
        ->assertSet('abstain', [])
        ->call('review')
        ->assertSet('reviewing', true)
        ->assertSee('Review your ballot')
        ->assertSee('Bravo President')
        ->assertSee('Submit final ballot')
        ->call('goTo', 0)
        ->assertSet('reviewing', false);
});

it('ignores candidates that are not on this ballot', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    Livewire::actingAs($student, 'student')->test(Ballot::class, ['election' => $s['election']])
        ->call('toggleCandidate', $s['president']->id, $s['c']['g7a']->id)
        ->assertSet('selections', []);
});

it('redirects students who already voted to the confirmation page', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);
    app(VoteSubmissionService::class)->submit($s['election'], $student, grade10Ballot($s));

    Livewire::actingAs($student, 'student')->test(Ballot::class, ['election' => $s['election']])
        ->assertRedirect(route('student.ballot.done', $s['election']));
});

it('keeps students out of a closed election ballot', function () {
    $s = votingElection(ElectionStatus::Closed);
    $student = makeStudent(10);
    syncRoll($s['election']);

    Livewire::actingAs($student, 'student')->test(Ballot::class, ['election' => $s['election']])
        ->assertRedirect(route('student.portal'));
});

it('renders mobile-friendly markup for the ballot', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $this->actingAs($student, 'student')->withSession(['student_last_activity' => time()])
        ->get(route('student.ballot', $s['election']))
        ->assertOk()
        ->assertSee('width=device-width, initial-scale=1', false)
        ->assertSee('min-h-14', false)          // large touch targets
        ->assertSee('sm:grid-cols-2', false)    // single column on phones, two on larger screens
        ->assertSee('aria-pressed', false)
        ->assertSee('role="progressbar"', false);
});
