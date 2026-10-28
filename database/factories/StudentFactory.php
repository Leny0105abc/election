<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'school_id' => fn () => School::current()->id,
            'student_number' => 'TEST-'.fake()->unique()->numerify('######'),
            'lrn' => fake()->boolean(70) ? fake()->unique()->numerify('9##########') : null,
            'first_name' => FilipinoNames::first(),
            'middle_name' => FilipinoNames::last(),
            'last_name' => FilipinoNames::last(),
            'is_active' => true,
        ];
    }

    /** Give the student a known PIN (tests and local demo only). */
    public function withPin(string $pin): static
    {
        return $this->state(fn () => [])->afterCreating(function (Student $student) use ($pin) {
            $student->forceFill([
                'pin_hash' => Hash::make($pin, ['rounds' => (int) config('schoolvote.pin_hash_rounds')]),
                'pin_generated_at' => now(),
            ])->save();
        });
    }
}
