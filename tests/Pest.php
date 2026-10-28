<?php

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\Role;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\VoterEligibilityService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed([RoleSeeder::class, SchoolSeeder::class]);
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers (all data is fictional)
|--------------------------------------------------------------------------
*/

function staff(Role $role = Role::SuperAdmin): User
{
    return User::factory()->role($role)->create();
}

/** Create an enrolled, eligible student in a grade with a known PIN. */
function makeStudent(int $grade = 10, string $pin = '135790', array $attributes = []): Student
{
    $student = Student::factory()->withPin($pin)->create($attributes);
    StudentEnrollment::factory()->for($student)->grade($grade)->create();

    return $student->fresh();
}

/**
 * A ready-to-vote election:
 *  - President (all grades, 1 winner, abstain allowed): 2 candidates
 *  - Councilor (all grades, 2 winners, no abstain): 3 candidates
 *  - Grade 7 Representative (grade 7 only): 2 candidates
 *  - Grade 8 Representative (grade 8 only): 1 candidate
 *
 * @return array{election: Election, president: ElectionPosition, councilor: ElectionPosition, g7rep: ElectionPosition, g8rep: ElectionPosition, c: array<string, Candidate>}
 */
function votingElection(ElectionStatus $status = ElectionStatus::Open): array
{
    $election = Election::factory()->create();

    $president = ElectionPosition::factory()->for($election)->create(['name' => 'President', 'display_order' => 1, 'allow_abstain' => true]);
    $councilor = ElectionPosition::factory()->for($election)->create(['name' => 'Councilor', 'display_order' => 2, 'max_winners' => 2, 'allow_abstain' => false]);
    $g7rep = ElectionPosition::factory()->for($election)->create(['name' => 'Grade 7 Representative', 'display_order' => 3, 'eligible_voter_grades' => [7], 'eligible_candidate_grades' => [7]]);
    $g8rep = ElectionPosition::factory()->for($election)->create(['name' => 'Grade 8 Representative', 'display_order' => 4, 'eligible_voter_grades' => [8], 'eligible_candidate_grades' => [8]]);

    $mk = fn (ElectionPosition $p, int $grade, string $name) => Candidate::factory()->create([
        'election_id' => $election->id,
        'election_position_id' => $p->id,
        'student_id' => makeStudent($grade, '000000')->id,
        'grade_level' => $grade,
        'ballot_name' => $name,
        'status' => CandidateStatus::Approved,
    ]);

    $c = [
        'p1' => $mk($president, 11, 'Alpha President'),
        'p2' => $mk($president, 12, 'Bravo President'),
        'c1' => $mk($councilor, 10, 'Charlie Councilor'),
        'c2' => $mk($councilor, 9, 'Delta Councilor'),
        'c3' => $mk($councilor, 11, 'Echo Councilor'),
        'g7a' => $mk($g7rep, 7, 'Foxtrot Seven'),
        'g7b' => $mk($g7rep, 7, 'Golf Seven'),
        'g8a' => $mk($g8rep, 8, 'Hotel Eight'),
    ];

    $election->status = $status;
    $election->save();

    return compact('election', 'president', 'councilor', 'g7rep', 'g8rep', 'c');
}

/** Put students on the election's voter roll (snapshot). */
function syncRoll(Election $election): array
{
    return app(VoterEligibilityService::class)->syncVoterRoll($election);
}

/** A complete valid ballot for a Grade 10 voter (no grade-rep positions). */
function grade10Ballot(array $s): array
{
    return [
        $s['president']->id => [$s['c']['p1']->id],
        $s['councilor']->id => [$s['c']['c1']->id, $s['c']['c2']->id],
    ];
}
