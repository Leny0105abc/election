<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Student voter. Authenticates through the "student" guard with
 * Student ID (or LRN) + PIN. The PIN is stored only as a hash.
 *
 * @property int $id
 * @property int $school_id
 * @property string $student_number
 * @property string|null $lrn
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property string|null $pin_hash
 * @property CarbonImmutable|null $pin_generated_at
 * @property bool $is_active
 * @property-read string $full_name
 * @property-read StudentEnrollment|null $currentEnrollment
 */
class Student extends Authenticatable
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'student_number', 'lrn', 'first_name', 'middle_name',
        'last_name', 'suffix', 'is_active',
    ];

    protected $hidden = ['pin_hash', 'remember_token'];

    /** Students never get "remember me" tokens. */
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'pin_generated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'pin_hash';
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<StudentEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /** @return HasOne<StudentEnrollment, $this> */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)
            ->whereHas('schoolYear', fn (Builder $q) => $q->where('is_current', true))
            ->latestOfMany();
    }

    /** @return HasMany<ElectionVoter, $this> */
    public function electionVoters(): HasMany
    {
        return $this->hasMany(ElectionVoter::class);
    }

    public function getFullNameAttribute(): string
    {
        $middleInitial = $this->middle_name ? ' '.mb_substr($this->middle_name, 0, 1).'.' : '';
        $suffix = $this->suffix ? ' '.$this->suffix : '';

        return trim($this->first_name.$middleInitial.' '.$this->last_name.$suffix);
    }

    public function getSortableNameAttribute(): string
    {
        return trim($this->last_name.', '.$this->first_name.' '.($this->middle_name ?? ''));
    }

    /** @param Builder<Student> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $like = '%'.$term.'%';
            $q->where('student_number', 'like', $like)
                ->orWhere('lrn', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like);
        });
    }

    /** Find a student by Student ID or LRN (login identifier). */
    public static function findByIdentifier(string $identifier): ?self
    {
        $identifier = trim($identifier);

        return static::where('student_number', $identifier)
            ->orWhere(fn (Builder $q) => $q->whereNotNull('lrn')->where('lrn', $identifier))
            ->first();
    }
}
