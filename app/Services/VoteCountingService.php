<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\TieResolution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic vote counting straight from the anonymous ballot tables.
 *
 * Ranking uses standard competition ranking (1, 2, 2, 4). Candidates are
 * listed by votes (desc), then name, then id, so the output never changes
 * between runs. A tie that decides who wins is NEVER broken automatically:
 * it is flagged as unresolved until the election committee records a
 * TieResolution for that position.
 */
class VoteCountingService
{
    /**
     * @return Collection<int, array{
     *     position: ElectionPosition,
     *     ballots: int,
     *     abstentions: int,
     *     rows: list<array{candidate: Candidate, votes: int, percentage: float, rank: int, is_winner: bool, is_tied: bool, status: string}>,
     *     has_unresolved_tie: bool,
     *     tie_resolution: TieResolution|null,
     * }>
     */
    public function tally(Election $election): Collection
    {
        $counts = DB::table('ballot_selections')
            ->where('election_id', $election->id)
            ->where('is_abstain', false)
            ->groupBy('election_position_id', 'candidate_id')
            ->select('election_position_id', 'candidate_id', DB::raw('COUNT(*) as votes'))
            ->get()
            ->groupBy('election_position_id');

        $abstentions = DB::table('ballot_selections')
            ->where('election_id', $election->id)
            ->where('is_abstain', true)
            ->groupBy('election_position_id')
            ->select('election_position_id', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'election_position_id');

        $ballotsPerPosition = DB::table('ballot_selections')
            ->where('election_id', $election->id)
            ->groupBy('election_position_id')
            ->select('election_position_id', DB::raw('COUNT(DISTINCT anonymous_ballot_id) as total'))
            ->pluck('total', 'election_position_id');

        $resolutions = TieResolution::where('election_id', $election->id)->get()->keyBy('election_position_id');

        $positions = $election->positions()
            ->where('is_active', true)
            ->with(['approvedCandidates.student', 'approvedCandidates.partyList'])
            ->get();

        return $positions->map(function (ElectionPosition $position) use ($counts, $abstentions, $ballotsPerPosition, $resolutions) {
            $votesByCandidate = collect($counts->get($position->id, []))
                ->pluck('votes', 'candidate_id')
                ->map(fn ($v) => (int) $v);

            $ballots = (int) ($ballotsPerPosition[$position->id] ?? 0);

            return $this->rankPosition(
                $position,
                $position->approvedCandidates,
                $votesByCandidate,
                $ballots,
                (int) ($abstentions[$position->id] ?? 0),
                $resolutions->get($position->id),
            );
        });
    }

    /**
     * Rank one position. Public so it can be unit-tested with plain data.
     *
     * @param  Collection<int, Candidate>  $candidates
     * @param  Collection<int|string, int>  $votesByCandidate
     * @return array<string, mixed>
     */
    public function rankPosition(
        ElectionPosition $position,
        Collection $candidates,
        Collection $votesByCandidate,
        int $ballots,
        int $abstentions,
        ?TieResolution $resolution = null,
    ): array {
        $seats = max(1, $position->max_winners);

        $sorted = $candidates
            ->map(fn (Candidate $c) => ['candidate' => $c, 'votes' => (int) ($votesByCandidate[$c->id] ?? 0)])
            ->sort(function (array $a, array $b) {
                return [$b['votes'], mb_strtolower($a['candidate']->display_name), $a['candidate']->id]
                    <=> [$a['votes'], mb_strtolower($b['candidate']->display_name), $b['candidate']->id];
            })
            ->values();

        // Competition ranks.
        $rank = 0;
        $previousVotes = null;
        $sorted = $sorted->map(function (array $row, int $index) use (&$rank, &$previousVotes) {
            if ($row['votes'] !== $previousVotes) {
                $rank = $index + 1;
                $previousVotes = $row['votes'];
            }

            return $row + ['rank' => $rank];
        });

        // Determine winners and whether a tie straddles the last winning seat.
        $winnerIds = [];
        $tiedIds = [];
        $unresolved = false;

        $cutoffVotes = $sorted->count() >= $seats ? $sorted[$seats - 1]['votes'] : null;
        $boundaryTie = $cutoffVotes !== null && $cutoffVotes > 0
            && $sorted->count() > $seats && $sorted[$seats]['votes'] === $cutoffVotes;

        if ($boundaryTie) {
            $clearWinners = $sorted->filter(fn ($r) => $r['votes'] > $cutoffVotes);
            $tied = $sorted->filter(fn ($r) => $r['votes'] === $cutoffVotes);
            $remainingSeats = $seats - $clearWinners->count();

            $winnerIds = $clearWinners->map(fn ($r) => $r['candidate']->id)->values()->all();
            $tiedIds = $tied->map(fn ($r) => $r['candidate']->id)->values()->all();

            $chosen = array_map('intval', $resolution?->winning_candidate_ids ?? []);
            $validResolution = $resolution
                && count($chosen) === $remainingSeats
                && array_diff($chosen, $tiedIds) === [];

            if ($validResolution) {
                $winnerIds = array_merge($winnerIds, $chosen);
            } else {
                $unresolved = true;
            }
        } else {
            $winnerIds = $sorted->take($seats)
                ->filter(fn ($r) => $r['votes'] > 0)
                ->map(fn ($r) => $r['candidate']->id)
                ->values()->all();
        }

        $rows = $sorted->map(function (array $row) use ($ballots, $winnerIds, $tiedIds, $unresolved) {
            $id = $row['candidate']->id;
            $isWinner = in_array($id, $winnerIds, true);
            $isTied = in_array($id, $tiedIds, true);

            return [
                'candidate' => $row['candidate'],
                'votes' => $row['votes'],
                'percentage' => $ballots > 0 ? round($row['votes'] / $ballots * 100, 2) : 0.0,
                'rank' => $row['rank'],
                'is_winner' => $isWinner,
                'is_tied' => $isTied,
                'status' => match (true) {
                    $isWinner && $isTied => 'Winner (tie resolved)',
                    $isWinner => 'Winner',
                    $isTied && $unresolved => 'Tie (unresolved)',
                    $isTied => 'Tie (lost draw)',
                    default => '',
                },
            ];
        })->values()->all();

        return [
            'position' => $position,
            'ballots' => $ballots,
            'abstentions' => $abstentions,
            'rows' => $rows,
            'has_unresolved_tie' => $unresolved,
            'tied_candidate_ids' => $tiedIds,
            'remaining_seats' => $boundaryTie ? $seats - count(array_diff($winnerIds, $tiedIds)) : 0,
            'tie_resolution' => $resolution,
        ];
    }

    /**
     * Integrity check: one anonymous ballot for every voter marked as voted.
     *
     * @return array{ballots: int, voted: int, consistent: bool}
     */
    public function integrity(Election $election): array
    {
        $ballots = DB::table('anonymous_ballots')->where('election_id', $election->id)->count();
        $voted = DB::table('election_voters')->where('election_id', $election->id)->where('has_voted', true)->count();

        return ['ballots' => $ballots, 'voted' => $voted, 'consistent' => $ballots === $voted];
    }
}
