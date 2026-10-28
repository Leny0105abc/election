<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Voter-roll entry: WHO may vote in an election and WHETHER they voted.
 * Never contains ballot choices.
 *
 * @property int $id
 * @property int $election_id
 * @property int $student_id
 * @property int $grade_level
 * @property string $section
 * @property bool $has_voted
 * @property CarbonImmutable|null $voted_at
 * @property-read Student $student
 * @property-read Election $election
 */
class ElectionVoter extends Model
{
    protected $fillable = ['election_id', 'student_id', 'grade_level', 'section'];

    protected function casts(): array
    {
        return [
            'has_voted' => 'boolean',
            'voted_at' => 'datetime',
            'grade_level' => 'integer',
        ];
    }

    /** @return BelongsTo<Election, $this> */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
