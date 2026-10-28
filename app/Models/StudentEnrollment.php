<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\StudentEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $school_year_id
 * @property int $grade_level
 * @property string $section
 * @property EnrollmentStatus $enrollment_status
 * @property bool $is_eligible
 */
class StudentEnrollment extends Model
{
    /** @use HasFactory<StudentEnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id', 'school_year_id', 'grade_level', 'section',
        'enrollment_status', 'is_eligible',
    ];

    protected function casts(): array
    {
        return [
            'grade_level' => 'integer',
            'enrollment_status' => EnrollmentStatus::class,
            'is_eligible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<SchoolYear, $this> */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /** Enrolled and marked eligible to vote. */
    public function canVote(): bool
    {
        return $this->enrollment_status === EnrollmentStatus::Enrolled && $this->is_eligible;
    }
}
