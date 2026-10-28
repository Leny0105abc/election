<?php

namespace Database\Factories;

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use App\Models\ElectionPosition;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'election_position_id' => ElectionPosition::factory(),
            'election_id' => fn (array $attrs) => ElectionPosition::find($attrs['election_position_id'])->election_id,
            'student_id' => Student::factory(),
            'grade_level' => 10,
            'platform' => 'Fictional platform: '.fake()->sentence(12),
            'status' => CandidateStatus::Approved,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => CandidateStatus::Pending]);
    }
}
