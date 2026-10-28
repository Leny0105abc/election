<?php

namespace Database\Factories;

use App\Models\Election;
use App\Models\PartyList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyList>
 */
class PartyListFactory extends Factory
{
    protected $model = PartyList::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Bagong Pag-asa', 'Kabataang Makabayan', 'Lingkod Mag-aaral', 'Tinig ng Kabataan', 'Sulong Estudyante']);

        return [
            'election_id' => Election::factory(),
            'name' => $name,
            'acronym' => collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->implode(''),
            'color' => fake()->hexColor(),
        ];
    }
}
