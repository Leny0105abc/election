<?php

namespace App\Services;

use App\Exceptions\VotingException;
use App\Models\AnonymousBallot;
use App\Models\BallotSelection;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records a ballot atomically.
 *
 * Guarantees:
 *  - All selections and the "has voted" flag are written in ONE transaction,
 *    so a failure never leaves a partial ballot.
 *  - A student can be marked as voted only once: the voter row is locked
 *    (SELECT ... FOR UPDATE) and then flipped with a compare-and-set UPDATE
 *    (WHERE has_voted = 0). A concurrent duplicate submission either waits for
 *    the lock and then sees has_voted = 1, or updates zero rows; both roll back.
 *  - The ballot row stores no voter identity and no timestamp, and uses a
 *    random UUIDv4 id, so it cannot be linked back to the voter.
 *  - Ballot choices are never written to logs or the audit trail.
 */
class VoteSubmissionService
{
    public const ABSTAIN = 'abstain';

    public function __construct(private readonly VoterEligibilityService $eligibility) {}

    /**
     * @param  array<int|string, list<int|string>|string>  $selections  positionId => candidate ids, or "abstain"
     */
    public function submit(Election $election, Student $student, array $selections): void
    {
        DB::transaction(function () use ($election, $student, $selections) {
            // 1. Re-read the election under a shared lock so it cannot be closed mid-transaction.
            $election = Election::whereKey($election->id)->sharedLock()->first();

            if (! $election || ! $election->isAcceptingVotes()) {
                throw new VotingException(VotingException::NOT_OPEN);
            }

            // 2. Lock this student's voter-roll row.
            $voter = ElectionVoter::where('election_id', $election->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $voter || ! $student->fresh()?->is_active) {
                throw new VotingException(VotingException::NOT_ELIGIBLE);
            }

            if ($voter->has_voted) {
                throw new VotingException(VotingException::ALREADY_VOTED);
            }

            // 3. Validate every selection against the server-side ballot definition.
            $rows = $this->validatedSelections($election, $voter, $selections);

            // 4. Compare-and-set: only one transaction can flip has_voted from false to true.
            $votedAt = CarbonImmutable::now()->startOfMinute();
            $updated = ElectionVoter::whereKey($voter->id)
                ->where('has_voted', false)
                ->update(['has_voted' => true, 'voted_at' => $votedAt, 'updated_at' => $votedAt]);

            if ($updated !== 1) {
                throw new VotingException(VotingException::ALREADY_VOTED);
            }

            // 5. Store the anonymous ballot (no voter id, no timestamp, random id).
            $ballotId = (string) Str::uuid();
            AnonymousBallot::create(['id' => $ballotId, 'election_id' => $election->id]);

            BallotSelection::insert(array_map(fn (array $row) => $row + [
                'id' => (string) Str::uuid(),
                'anonymous_ballot_id' => $ballotId,
                'election_id' => $election->id,
            ], $rows));

            // 6. Audit the participation only (never the choices).
            AuditLogger::log('ballot.submitted', $voter, [], 'Ballot submitted', $election->id);
        }, attempts: 3);
    }

    /**
     * Turns user input into selection rows, rejecting anything that does not
     * match the ballot this voter is entitled to.
     *
     * @param  array<int|string, list<int|string>|string>  $selections
     * @return list<array{election_position_id: int, candidate_id: int|null, is_abstain: bool}>
     */
    public function validatedSelections(Election $election, ElectionVoter $voter, array $selections): array
    {
        $positions = $this->eligibility->ballotPositions($election, $voter->grade_level)->keyBy('id');

        if ($positions->isEmpty()) {
            throw new VotingException(VotingException::INVALID_BALLOT);
        }

        $submittedIds = array_map('intval', array_keys($selections));
        sort($submittedIds);
        $expectedIds = $positions->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();

        // Every ballot position must be answered, and no other position may appear.
        if ($submittedIds !== $expectedIds) {
            throw new VotingException(VotingException::INVALID_BALLOT);
        }

        $rows = [];

        foreach ($selections as $positionId => $choice) {
            $position = $positions->get((int) $positionId);

            if ($choice === self::ABSTAIN) {
                if (! $position->allow_abstain) {
                    throw new VotingException(VotingException::INVALID_BALLOT);
                }
                $rows[] = ['election_position_id' => $position->id, 'candidate_id' => null, 'is_abstain' => true];

                continue;
            }

            if (! is_array($choice)) {
                throw new VotingException(VotingException::INVALID_BALLOT);
            }

            $candidateIds = [];
            foreach ($choice as $id) {
                if (! is_numeric($id) || (int) $id <= 0) {
                    throw new VotingException(VotingException::INVALID_BALLOT);
                }
                $candidateIds[] = (int) $id;
            }

            $allowed = $position->approvedCandidates->pluck('id')->map(fn ($id) => (int) $id)->all();

            if ($candidateIds === []
                || count($candidateIds) !== count(array_unique($candidateIds))
                || count($candidateIds) > $position->max_winners
                || array_diff($candidateIds, $allowed) !== []) {
                throw new VotingException(VotingException::INVALID_BALLOT);
            }

            foreach ($candidateIds as $candidateId) {
                $rows[] = ['election_position_id' => $position->id, 'candidate_id' => $candidateId, 'is_abstain' => false];
            }
        }

        return $rows;
    }
}
