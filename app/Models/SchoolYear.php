<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property bool $is_current
 */
class SchoolYear extends Model
{
    protected $fillable = ['school_id', 'name', 'starts_on', 'ends_on', 'is_current'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
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

    /** @return HasMany<Election, $this> */
    public function elections(): HasMany
    {
        return $this->hasMany(Election::class);
    }

    public static function current(): ?self
    {
        return static::where('is_current', true)->orderByDesc('id')->first();
    }

    /** The current school year, creating a sensible default if none exists yet. */
    public static function ensureCurrent(): self
    {
        if ($current = static::current()) {
            return $current;
        }

        $year = (int) now()->setTimezone(config('schoolvote.display_timezone'))->format('Y');
        // Philippine school years start around June.
        $start = (int) now()->format('n') >= 6 ? $year : $year - 1;

        $sy = static::firstOrCreate(
            ['school_id' => School::current()->id, 'name' => $start.'-'.($start + 1)],
            ['starts_on' => "{$start}-06-01", 'ends_on' => ($start + 1).'-04-30'],
        );
        $sy->makeCurrent();

        return $sy;
    }

    /** Mark this school year as the only current one. */
    public function makeCurrent(): void
    {
        static::where('id', '!=', $this->id)->update(['is_current' => false]);
        $this->update(['is_current' => true]);
    }
}
