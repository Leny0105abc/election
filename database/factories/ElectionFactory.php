<?php

namespace Database\Factories;

use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use App\Models\Election;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Election>
 */
class ElectionFactory extends Factory
{
    protected $model = Election::class;

    public function definition(): array
    {
        return [
            'school_year_id' => fn () => SchoolYear::ensureCurrent()->id,
            'name' => 'SSLG Election '.fake()->unique()->numberBetween(1000, 9999),
            'type' => ElectionType::Sslg,
            'description' => 'Fictional test election.',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(6),
            'eligible_grades' => [7, 8, 9, 10, 11, 12],
        ];
    }

    public function status(ElectionStatus $status): static
    {
        return $this->afterMaking(fn (Election $e) => $e->status = $status)
            ->afterCreating(function (Election $e) use ($status) {
                $e->status = $status;
                $e->save();
            });
    }

    public function open(): static
    {
        return $this->status(ElectionStatus::Open);
    }
}
