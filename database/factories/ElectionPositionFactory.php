<?php

namespace Database\Factories;

use App\Models\Election;
use App\Models\ElectionPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ElectionPosition>
 */
class ElectionPositionFactory extends Factory
{
    protected $model = ElectionPosition::class;

    public function definition(): array
    {
        return [
            'election_id' => Election::factory(),
            'name' => fake()->unique()->jobTitle(),
            'display_order' => fake()->numberBetween(1, 50),
            'max_winners' => 1,
            'eligible_voter_grades' => null,
            'eligible_candidate_grades' => null,
            'allow_abstain' => true,
            'is_active' => true,
        ];
    }
}
