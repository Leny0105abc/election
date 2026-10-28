<?php

use App\Enums\ElectionStatus;
use App\Exceptions\VotingException;
use App\Models\AnonymousBallot;
use App\Models\BallotSelection;
use App\Models\ElectionVoter;
use App\Services\VoteSubmissionService;
use Illuminate\Support\Facades\DB;

function submitBallot($election, $student, array $selections): void
{
    app(VoteSubmissionService::class)->submit($election, $student, $selections);
}

it('records a valid ballot atomically and marks the student as voted', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    submitBallot($s['election'], $student, grade10Ballot($s));

    $voter = ElectionVoter::where('student_id', $student->id)->first();
    expect($voter->has_voted)->toBeTrue()
        ->and(AnonymousBallot::count())->toBe(1)
        ->and(BallotSelection::count())->toBe(3)
        ->and(BallotSelection::where('candidate_id', $s['c']['p1']->id)->count())->toBe(1);
});

it('submits through the HTTP form and shows a confirmation without choices', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $this->actingAs($student, 'student')
        ->post(route('student.ballot.submit', $s['election']), [
            'selections' => [
                $s['president']->id => [$s['c']['p1']->id],
                $s['councilor']->id => [$s['c']['c1']->id, $s['c']['c3']->id],
            ],
        ])
        ->assertRedirect(route('student.ballot.done', $s['election']));

    $this->actingAs($student, 'student')
        ->withSession(['just_submitted' => true, 'student_last_activity' => time()])
        ->get(route('student.ballot.done', $s['election']))
        ->assertOk()
        ->assertSee('Your ballot has been recorded')
        ->assertDontSee('Alpha President')
        ->assertDontSee('Charlie Councilor');
});

it('prevents a student from voting twice', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    submitBallot($s['election'], $student, grade10Ballot($s));

    expect(fn () => submitBallot($s['election'], $student, grade10Ballot($s)))
        ->toThrow(VotingException::class, 'already voted');

    expect(AnonymousBallot::count())->toBe(1);
});

it('handles double-clicks and retries of the HTTP submission safely', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);
    $payload = ['selections' => [
        $s['president']->id => [$s['c']['p2']->id],
        $s['councilor']->id => [$s['c']['c2']->id],
    ]];

    $first = $this->actingAs($student, 'student')->post(route('student.ballot.submit', $s['election']), $payload);
    $second = $this->actingAs($student, 'student')->post(route('student.ballot.submit', $s['election']), $payload);

    $first->assertRedirect(route('student.ballot.done', $s['election']));
    $second->assertRedirect(route('student.ballot.done', $s['election']));
    expect(AnonymousBallot::count())->toBe(1)->and(BallotSelection::count())->toBe(2);
});

it('rejects ballots when the election is not open', function (ElectionStatus $status) {
    $s = votingElection($status);
    $student = makeStudent(10);
    syncRoll($s['election']);

    expect(fn () => submitBallot($s['election'], $student, grade10Ballot($s)))
        ->toThrow(VotingException::class);
    expect(AnonymousBallot::count())->toBe(0);
})->with([ElectionStatus::Draft, ElectionStatus::Scheduled, ElectionStatus::Closed, ElectionStatus::Finalized]);

it('rejects ballots outside the voting window even when open', function () {
    $s = votingElection();
    $s['election']->update(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(3)]);
    $student = makeStudent(10);
    syncRoll($s['election']);

    expect(fn () => submitBallot($s['election']->fresh(), $student, grade10Ballot($s)))
        ->toThrow(VotingException::class, 'not open');
});

it('rejects students who are not on the voter roll', function () {
    $s = votingElection();
    syncRoll($s['election']);
    $lateStudent = makeStudent(10); // enrolled after the roll was generated

    expect(fn () => submitBallot($s['election'], $lateStudent, grade10Ballot($s)))
        ->toThrow(VotingException::class, 'not on the voter list');
});

it('rejects a candidate from a different position', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $ballot = grade10Ballot($s);
    $ballot[$s['president']->id] = [$s['c']['c1']->id];

    expect(fn () => submitBallot($s['election'], $student, $ballot))->toThrow(VotingException::class);
    expect(ElectionVoter::where('student_id', $student->id)->value('has_voted'))->toBeFalse();
});

it('rejects non-existent and malformed candidate ids', function ($bad) {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $ballot = grade10Ballot($s);
    $ballot[$s['president']->id] = $bad;

    expect(fn () => submitBallot($s['election'], $student, $ballot))->toThrow(VotingException::class);
})->with([[[999999]], [['abc']], [[-1]], [[]], ['nonsense']]);

it('rejects more selections than the number of winners', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $ballot = grade10Ballot($s);
    $ballot[$s['councilor']->id] = [$s['c']['c1']->id, $s['c']['c2']->id, $s['c']['c3']->id];

    expect(fn () => submitBallot($s['election'], $student, $ballot))->toThrow(VotingException::class);
});

it('rejects selecting the same candidate twice', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $ballot = grade10Ballot($s);
    $ballot[$s['councilor']->id] = [$s['c']['c1']->id, $s['c']['c1']->id];

    expect(fn () => submitBallot($s['election'], $student, $ballot))->toThrow(VotingException::class);
});

it('requires every ballot position to be answered', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    expect(fn () => submitBallot($s['election'], $student, [$s['president']->id => [$s['c']['p1']->id]]))
        ->toThrow(VotingException::class);
});

it('accepts abstentions only where allowed', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    // Councilor does not allow abstain.
    $ballot = grade10Ballot($s);
    $ballot[$s['councilor']->id] = VoteSubmissionService::ABSTAIN;
    expect(fn () => submitBallot($s['election'], $student, $ballot))->toThrow(VotingException::class);

    // President allows abstain.
    $ballot = grade10Ballot($s);
    $ballot[$s['president']->id] = VoteSubmissionService::ABSTAIN;
    submitBallot($s['election'], $student, $ballot);

    expect(BallotSelection::where('is_abstain', true)->where('election_position_id', $s['president']->id)->count())->toBe(1);
});

it('rejects choosing candidates and abstaining for the same position', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $this->actingAs($student, 'student')->post(route('student.ballot.submit', $s['election']), [
        'selections' => [$s['president']->id => [$s['c']['p1']->id], $s['councilor']->id => [$s['c']['c1']->id]],
        'abstain' => [$s['president']->id => '1'],
    ])->assertRedirect(route('student.ballot', $s['election']));

    expect(AnonymousBallot::count())->toBe(0);
});

it('enforces grade-level restrictions for grade representatives', function () {
    $s = votingElection();
    $g7 = makeStudent(7);
    $g10 = makeStudent(10);
    syncRoll($s['election']);

    // A Grade 10 voter may not vote for the Grade 7 representative.
    $ballot = grade10Ballot($s) + [$s['g7rep']->id => [$s['c']['g7a']->id]];
    expect(fn () => submitBallot($s['election'], $g10, $ballot))->toThrow(VotingException::class);

    // A Grade 7 voter must answer the Grade 7 representative and may not answer Grade 8's.
    $g7Ballot = grade10Ballot($s) + [$s['g8rep']->id => [$s['c']['g8a']->id]];
    expect(fn () => submitBallot($s['election'], $g7, $g7Ballot))->toThrow(VotingException::class);

    submitBallot($s['election'], $g7, grade10Ballot($s) + [$s['g7rep']->id => [$s['c']['g7b']->id]]);
    expect(BallotSelection::where('candidate_id', $s['c']['g7b']->id)->count())->toBe(1);
});

it('uses the roll snapshot so later grade changes do not affect the ballot', function () {
    $s = votingElection();
    $student = makeStudent(7);
    syncRoll($s['election']);

    // The registrar later edits the grade to 8 — the roll snapshot still says 7.
    $student->enrollments()->update(['grade_level' => 8]);

    submitBallot($s['election'], $student, grade10Ballot($s) + [$s['g7rep']->id => [$s['c']['g7a']->id]]);
    expect(ElectionVoter::where('student_id', $student->id)->value('grade_level'))->toBe(7);
});

it('rolls back everything if any part of the ballot fails to save', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    // Force the selection insert to fail after the voter row was updated.
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'insert into "ballot_selections"')) {
            throw new RuntimeException('Simulated database failure');
        }
    });

    expect(fn () => submitBallot($s['election'], $student, grade10Ballot($s)))->toThrow(RuntimeException::class);

    expect(ElectionVoter::where('student_id', $student->id)->value('has_voted'))->toBeFalse()
        ->and(AnonymousBallot::count())->toBe(0)
        ->and(BallotSelection::count())->toBe(0);
});

it('never reports success when the server fails', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $this->mock(VoteSubmissionService::class)->shouldReceive('submit')->andThrow(new RuntimeException('DB down'));

    $this->actingAs($student, 'student')
        ->post(route('student.ballot.submit', $s['election']), ['selections' => []])
        ->assertRedirect(route('student.ballot', $s['election']))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'NOT recorded'));
});

it('only shows the confirmation page to students who really voted', function () {
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $this->actingAs($student, 'student')->get(route('student.ballot.done', $s['election']))
        ->assertRedirect(route('student.portal'));
});

it('blocks a racing submission at the compare-and-set step', function () {
    // Another request marks the voter as voted right after this request read
    // the row (the race the row lock + conditional UPDATE protect against).
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $fired = false;
    DB::listen(function ($query) use (&$fired, $student) {
        if (! $fired && str_contains($query->sql, 'from "election_voters"') && str_contains($query->sql, 'select')) {
            $fired = true;
            DB::table('election_voters')->where('student_id', $student->id)->update(['has_voted' => true]);
        }
    });

    expect(fn () => submitBallot($s['election'], $student, grade10Ballot($s)))
        ->toThrow(VotingException::class, 'already voted');

    expect(AnonymousBallot::count())->toBe(0)->and(BallotSelection::count())->toBe(0);
});

it('lets repeated duplicate submissions record only one ballot', function () {
    // Simulates the race: two requests both passed validation, then compete for
    // the compare-and-set update. Only the first flips has_voted.
    $s = votingElection();
    $student = makeStudent(10);
    syncRoll($s['election']);

    $results = [];
    foreach (range(1, 5) as $attempt) {
        try {
            submitBallot($s['election'], $student, grade10Ballot($s));
            $results[] = 'ok';
        } catch (VotingException $e) {
            $results[] = $e->reason;
        }
    }

    expect(array_count_values($results))->toBe(['ok' => 1, VotingException::ALREADY_VOTED => 4])
        ->and(AnonymousBallot::count())->toBe(1);
});
