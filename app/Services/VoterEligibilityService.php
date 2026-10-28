<?php

namespace App\Services;

use App\Enums\CandidateStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\ElectionVoter;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Decides who may vote in an election and which positions appear on each ballot.
 */
class VoterEligibilityService
{
    /**
     * Enrollments that qualify for the election's voter roll.
     *
     * @return Builder<StudentEnrollment>
     */
    public function eligibleEnrollments(Election $election): Builder
    {
        return StudentEnrollment::query()
            ->where('school_year_id', $election->school_year_id)
            ->where('enrollment_status', EnrollmentStatus::Enrolled)
            ->where('is_eligible', true)
            ->whereIn('grade_level', $election->eligibleGrades())
            ->whereHas('student', fn (Builder $q) => $q->where('is_active', true));
    }

    /**
     * Create / refresh the voter roll snapshot.
     *
     * - Adds newly eligible students.
     * - Refreshes grade/section snapshots for students who have NOT voted.
     * - Removes students who are no longer eligible and have NOT voted.
     * - Never touches the record of a student who has already voted.
     *
     * @return array{added: int, removed: int, total: int}
     */
    public function syncVoterRoll(Election $election): array
    {
        return DB::transaction(function () use ($election) {
            $eligible = $this->eligibleEnrollments($election)
                ->get(['student_id', 'grade_level', 'section'])
                ->keyBy('student_id');

            $existing = ElectionVoter::where('election_id', $election->id)
                ->get(['id', 'student_id', 'grade_level', 'section', 'has_voted'])
                ->keyBy('student_id');

            // Remove ineligible students who have not voted.
            $toRemove = $existing
                ->filter(fn (ElectionVoter $v) => ! $v->has_voted && ! $eligible->has($v->student_id))
                ->pluck('id');

            if ($toRemove->isNotEmpty()) {
                ElectionVoter::whereIn('id', $toRemove)->where('has_voted', false)->delete();
            }

            // Refresh snapshots of not-yet-voted students whose grade/section changed.
            foreach ($existing as $studentId => $voter) {
                $enrollment = $eligible->get($studentId);
                if ($enrollment && ! $voter->has_voted
                    && ($voter->grade_level !== $enrollment->grade_level || $voter->section !== $enrollment->section)) {
                    ElectionVoter::whereKey($voter->id)->where('has_voted', false)->update([
                        'grade_level' => $enrollment->grade_level,
                        'section' => $enrollment->section,
                    ]);
                }
            }

            // Add new eligible students.
            $now = Carbon::now();
            $rows = $eligible
                ->reject(fn ($e) => $existing->has($e->student_id))
                ->map(fn ($e) => [
                    'election_id' => $election->id,
                    'student_id' => $e->student_id,
                    'grade_level' => $e->grade_level,
                    'section' => $e->section,
                    'has_voted' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values();

            foreach ($rows->chunk(500) as $chunk) {
                ElectionVoter::insertOrIgnore($chunk->all());
            }

            return [
                'added' => $rows->count(),
                'removed' => $toRemove->count(),
                'total' => ElectionVoter::where('election_id', $election->id)->count(),
            ];
        });
    }

    public function voterRecord(Election $election, Student $student): ?ElectionVoter
    {
        return ElectionVoter::where('election_id', $election->id)
            ->where('student_id', $student->id)
            ->first();
    }

    /**
     * Positions shown on a voter's ballot: active, open to the voter's grade
     * (from the roll snapshot) and with at least one approved candidate.
     *
     * @return Collection<int, ElectionPosition>
     */
    public function ballotPositions(Election $election, int $voterGrade): Collection
    {
        return ElectionPosition::query()
            ->where('election_id', $election->id)
            ->where('is_active', true)
            ->whereHas('candidates', fn (Builder $q) => $q->where('status', CandidateStatus::Approved))
            ->with(['approvedCandidates.student', 'approvedCandidates.partyList'])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ElectionPosition $p) => $p->voterGradeAllowed($voterGrade))
            ->values();
    }
}
