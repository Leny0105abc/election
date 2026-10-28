<?php

use App\Enums\ElectionStatus;
use App\Models\TieResolution;
use App\Services\VoteCountingService;
use App\Services\VoteSubmissionService;

/** Cast $n ballots from new Grade 10 voters with the given choices. */
function castBallots(array $s, int $n, array $ballot): void
{
    $s['election']->update(['status' => ElectionStatus::Open]);
    $s['election']->status = ElectionStatus::Open;
    for ($i = 0; $i < $n; $i++) {
        $student = makeStudent(10);
        syncRoll($s['election']);
        app(VoteSubmissionService::class)->submit($s['election']->fresh(), $student, $ballot);
    }
}

function tallyFor(array $s, string $position): array
{
    return app(VoteCountingService::class)->tally($s['election']->fresh())->firstWhere('position.id', $s[$position]->id);
}

it('counts votes, percentages and ranks deterministically', function () {
    $s = votingElection();
    castBallots($s, 3, [$s['president']->id => [$s['c']['p1']->id], $s['councilor']->id => [$s['c']['c1']->id, $s['c']['c2']->id]]);
    castBallots($s, 1, [$s['president']->id => [$s['c']['p2']->id], $s['councilor']->id => [$s['c']['c3']->id]]);

    $president = tallyFor($s, 'president');

    expect($president['ballots'])->toBe(4)
        ->and($president['rows'][0]['candidate']->id)->toBe($s['c']['p1']->id)
        ->and($president['rows'][0]['votes'])->toBe(3)
        ->and($president['rows'][0]['percentage'])->toBe(75.0)
        ->and($president['rows'][0]['rank'])->toBe(1)
        ->and($president['rows'][0]['is_winner'])->toBeTrue()
        ->and($president['rows'][1]['is_winner'])->toBeFalse()
        ->and($president['has_unresolved_tie'])->toBeFalse();

    // Running the count again gives identical output.
    $again = tallyFor($s, 'president');
    expect(collect($again['rows'])->pluck('votes')->all())->toBe(collect($president['rows'])->pluck('votes')->all());
});

it('elects the configured number of winners for multi-seat positions', function () {
    $s = votingElection();
    castBallots($s, 2, [$s['president']->id => [$s['c']['p1']->id], $s['councilor']->id => [$s['c']['c1']->id, $s['c']['c2']->id]]);
    castBallots($s, 1, [$s['president']->id => [$s['c']['p1']->id], $s['councilor']->id => [$s['c']['c3']->id]]);

    $winners = collect(tallyFor($s, 'councilor')['rows'])->where('is_winner', true)->pluck('candidate.id')->sort()->values()->all();
    expect($winners)->toBe(collect([$s['c']['c1']->id, $s['c']['c2']->id])->sort()->values()->all());
});

it('counts abstentions separately', function () {
    $s = votingElection();
    castBallots($s, 2, [$s['president']->id => VoteSubmissionService::ABSTAIN, $s['councilor']->id => [$s['c']['c1']->id]]);
    castBallots($s, 1, [$s['president']->id => [$s['c']['p2']->id], $s['councilor']->id => [$s['c']['c1']->id]]);

    $president = tallyFor($s, 'president');
    expect($president['abstentions'])->toBe(2)
        ->and($president['ballots'])->toBe(3)
        ->and(collect($president['rows'])->firstWhere('candidate.id', $s['c']['p2']->id)['votes'])->toBe(1);
});

it('detects a tie that decides the winner and does not declare one', function () {
    $s = votingElection();
    castBallots($s, 2, [$s['president']->id => [$s['c']['p1']->id], $s['councilor']->id => [$s['c']['c1']->id]]);
    castBallots($s, 2, [$s['president']->id => [$s['c']['p2']->id], $s['councilor']->id => [$s['c']['c1']->id]]);

    $president = tallyFor($s, 'president');
    expect($president['has_unresolved_tie'])->toBeTrue()
        ->and(collect($president['rows'])->where('is_winner', true))->toBeEmpty()
        ->and(collect($president['rows'])->every(fn ($r) => $r['is_tied'] && $r['rank'] === 1))->toBeTrue();
});

it('ignores ties that do not affect the winners', function () {
    $s = votingElection();
    // Councilor (2 seats): c1=3, c2=1, c3=1 -> tie between c2 and c3 for the 2nd seat matters.
    // President: p1=3, p2=0 — no tie.
    castBallots($s, 3, [$s['president']->id => [$s['c']['p1']->id], $s['councilor']->id => [$s['c']['c1']->id]]);
    $president = tallyFor($s, 'president');
    expect($president['has_unresolved_tie'])->toBeFalse();
});

it('applies a recorded committee tie resolution', function () {
    $s = votingElection();
    castBallots($s, 1, [$s['president']->id => [$s['c']['p1']->id], $s['councilor']->id => [$s['c']['c1']->id]]);
    castBallots($s, 1, [$s['president']->id => [$s['c']['p2']->id], $s['councilor']->id => [$s['c']['c1']->id]]);

    TieResolution::create([
        'election_id' => $s['election']->id,
        'election_position_id' => $s['president']->id,
        'winning_candidate_ids' => [$s['c']['p2']->id],
        'method_and_notes' => 'Toss coin witnessed by the committee.',
    ]);

    $president = tallyFor($s, 'president');
    expect($president['has_unresolved_tie'])->toBeFalse()
        ->and(collect($president['rows'])->firstWhere('candidate.id', $s['c']['p2']->id)['is_winner'])->toBeTrue()
        ->and(collect($president['rows'])->firstWhere('candidate.id', $s['c']['p1']->id)['is_winner'])->toBeFalse();
});

it('never declares a winner with zero votes', function () {
    $s = votingElection();
    castBallots($s, 1, [$s['president']->id => VoteSubmissionService::ABSTAIN, $s['councilor']->id => [$s['c']['c1']->id]]);

    $president = tallyFor($s, 'president');
    expect(collect($president['rows'])->where('is_winner', true))->toBeEmpty()
        ->and($president['has_unresolved_tie'])->toBeFalse();
});

it('reports ballot integrity', function () {
    $s = votingElection();
    castBallots($s, 3, [$s['president']->id => [$s['c']['p1']->id], $s['councilor']->id => [$s['c']['c1']->id]]);

    expect(app(VoteCountingService::class)->integrity($s['election']))
        ->toBe(['ballots' => 3, 'voted' => 3, 'consistent' => true]);
});
