<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentEnrollment>
 */
class StudentEnrollmentFactory extends Factory
{
    protected $model = StudentEnrollment::class;

    public function definition(): array
    {
        $grade = fake()->randomElement([7, 8, 9, 10, 11, 12]);

        return [
            'student_id' => Student::factory(),
            'school_year_id' => fn () => SchoolYear::ensureCurrent()->id,
            'grade_level' => $grade,
            'section' => fake()->randomElement(FilipinoNames::SECTIONS[$grade]),
            'enrollment_status' => EnrollmentStatus::Enrolled,
            'is_eligible' => true,
        ];
    }

    public function grade(int $grade, ?string $section = null): static
    {
        return $this->state(fn () => [
            'grade_level' => $grade,
            'section' => $section ?? FilipinoNames::SECTIONS[$grade][0],
        ]);
    }
}
