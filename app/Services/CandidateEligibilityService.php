<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\Student;
use App\Models\StudentEnrollment;

/**
 * Checks whether a student may run for a position.
 */
class CandidateEligibilityService
{
    /**
     * Returns a list of human-readable problems (empty when eligible).
     *
     * @return list<string>
     */
    public function problems(Student $student, ElectionPosition $position, ?Candidate $existing = null): array
    {
        $problems = [];
        $election = $position->election;

        if (! $student->is_active) {
            $problems[] = 'The student account is inactive.';
        }

        $enrollment = $this->enrollmentFor($student, $election);

        if (! $enrollment) {
            $problems[] = "The student is not enrolled in school year {$election->schoolYear->name}.";

            return $problems;
        }

        if (! $enrollment->canVote()) {
            $problems[] = 'The student is not currently enrolled and eligible.';
        }

        if (! in_array($enrollment->grade_level, $election->eligibleGrades(), true)) {
            $problems[] = "Grade {$enrollment->grade_level} students are not part of this election.";
        }

        if (! $position->candidateGradeAllowed($enrollment->grade_level)) {
            $problems[] = "{$position->name} is limited to candidates from {$position->candidateGradesLabel()}.";
        }

        $duplicate = Candidate::where('election_id', $election->id)
            ->where('student_id', $student->id)
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->exists();

        if ($duplicate) {
            $problems[] = 'The student is already a candidate in this election.';
        }

        return $problems;
    }

    public function enrollmentFor(Student $student, Election $election): ?StudentEnrollment
    {
        return StudentEnrollment::where('student_id', $student->id)
            ->where('school_year_id', $election->school_year_id)
            ->first();
    }
}
