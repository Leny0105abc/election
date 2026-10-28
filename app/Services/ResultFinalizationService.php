<?php

namespace App\Services;

use App\Enums\ElectionStatus;
use App\Exceptions\ElectionStateException;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\ElectionResult;
use App\Models\TieResolution;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tie resolution, finalization (freezing official tallies) and publication.
 */
class ResultFinalizationService
{
    public function __construct(private readonly VoteCountingService $counter) {}

    /**
     * Record the committee's decision for a tie that affects the winners.
     *
     * @param  list<int>  $winningCandidateIds
     */
    public function resolveTie(Election $election, ElectionPosition $position, array $winningCandidateIds, string $notes, User $user): TieResolution
    {
        if ($election->status !== ElectionStatus::Closed) {
            throw new ElectionStateException('Ties can only be resolved after voting closes and before results are finalized.');
        }

        if (trim($notes) === '') {
            throw new ElectionStateException('Describe how the tie was resolved (method, witnesses, date).');
        }

        $tally = $this->counter->tally($election)->firstWhere('position.id', $position->id);

        if (! $tally || $tally['tied_candidate_ids'] === []) {
            throw new ElectionStateException('There is no tie affecting the winners for this position.');
        }

        $winningCandidateIds = array_values(array_unique(array_map('intval', $winningCandidateIds)));

        if (count($winningCandidateIds) !== $tally['remaining_seats']
            || array_diff($winningCandidateIds, $tally['tied_candidate_ids']) !== []) {
            throw new ElectionStateException("Select exactly {$tally['remaining_seats']} of the tied candidates.");
        }

        $resolution = TieResolution::updateOrCreate(
            ['election_position_id' => $position->id],
            [
                'election_id' => $election->id,
                'winning_candidate_ids' => $winningCandidateIds,
                'method_and_notes' => $notes,
                'resolved_by' => $user->id,
            ],
        );

        AuditLogger::log('results.tie_resolved', $position, ['notes' => $notes], "Tie resolved for {$position->name}", $election->id);

        return $resolution;
    }

    /** Freeze the official results. Requires a closed election with no unresolved ties. */
    public function finalize(Election $election, User $user): void
    {
        if ($election->status !== ElectionStatus::Closed) {
            throw new ElectionStateException('Only a closed election can be finalized.');
        }

        DB::transaction(function () use ($election, $user) {
            Election::whereKey($election->id)->lockForUpdate()->first();

            $integrity = $this->counter->integrity($election);
            if (! $integrity['consistent']) {
                throw new ElectionStateException(
                    "Integrity check failed: {$integrity['ballots']} ballots vs {$integrity['voted']} voters marked as voted. Do not finalize; contact the system administrator."
                );
            }

            $tally = $this->counter->tally($election);

            if ($tally->contains(fn ($p) => $p['has_unresolved_tie'])) {
                throw new ElectionStateException('Resolve every tie that affects the winners before finalizing.');
            }

            ElectionResult::where('election_id', $election->id)->delete();

            foreach ($tally as $position) {
                foreach ($position['rows'] as $row) {
                    ElectionResult::create([
                        'election_id' => $election->id,
                        'election_position_id' => $position['position']->id,
                        'candidate_id' => $row['candidate']->id,
                        'is_abstain_row' => false,
                        'votes' => $row['votes'],
                        'percentage' => $row['percentage'],
                        'rank' => $row['rank'],
                        'is_winner' => $row['is_winner'],
                        'is_tied' => $row['is_tied'],
                    ]);
                }

                ElectionResult::create([
                    'election_id' => $election->id,
                    'election_position_id' => $position['position']->id,
                    'candidate_id' => null,
                    'is_abstain_row' => true,
                    'votes' => $position['abstentions'],
                    'percentage' => $position['ballots'] > 0 ? round($position['abstentions'] / $position['ballots'] * 100, 2) : 0,
                ]);
            }

            $election->status = ElectionStatus::Finalized;
            $election->finalized_at = CarbonImmutable::now();
            $election->finalized_by = $user->id;
            $election->save();

            AuditLogger::log('results.finalized', $election, $integrity);
        });
    }

    public function publish(Election $election): void
    {
        if (! in_array($election->status, [ElectionStatus::Finalized, ElectionStatus::Archived], true)) {
            throw new ElectionStateException('Results can be published only after they are finalized.');
        }

        $election->results_published_at = CarbonImmutable::now();
        $election->save();
        AuditLogger::log('results.published', $election);
    }

    public function unpublish(Election $election): void
    {
        $election->results_published_at = null;
        $election->save();
        AuditLogger::log('results.unpublished', $election);
    }

    /**
     * Official results grouped by position, read from the frozen table.
     *
     * @return Collection<int, array{position: ElectionPosition, rows: Collection<int, ElectionResult>, abstentions: int, ballots: int}>
     */
    public function officialResults(Election $election)
    {
        $results = ElectionResult::where('election_id', $election->id)
            ->with(['candidate.student', 'candidate.partyList'])
            ->get()
            ->groupBy('election_position_id');

        return $election->positions()->get()
            ->filter(fn ($p) => $results->has($p->id))
            ->map(function (ElectionPosition $position) use ($results) {
                $rows = $results[$position->id];
                $abstain = $rows->firstWhere('is_abstain_row', true);
                $candidates = $rows->where('is_abstain_row', false)->sortBy([['rank', 'asc'], ['id', 'asc']])->values();

                return [
                    'position' => $position,
                    'rows' => $candidates,
                    'abstentions' => (int) ($abstain?->votes ?? 0),
                    'ballots' => $this->ballotsFor($position),
                ];
            })
            ->values();
    }

    private function ballotsFor(ElectionPosition $position): int
    {
        return (int) DB::table('ballot_selections')
            ->where('election_position_id', $position->id)
            ->distinct()
            ->count('anonymous_ballot_id');
    }
}
