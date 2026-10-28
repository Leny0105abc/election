<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Single-school deployment: the first row is "the" school.
 *
 * @property int $id
 * @property string $name
 * @property string|null $school_code
 * @property string|null $address
 * @property string|null $division
 * @property string|null $region
 * @property string|null $logo_path
 * @property string $primary_color
 * @property string $secondary_color
 */
class School extends Model
{
    protected $fillable = [
        'name', 'school_code', 'address', 'division', 'region',
        'logo_path', 'primary_color', 'secondary_color',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('schoolvote.school'));
    }

    /**
     * The configured school (created with defaults if missing).
     *
     * Only the plain attribute array is cached: Laravel does not unserialize
     * arbitrary objects (such as models) from the cache store.
     */
    public static function current(): self
    {
        $attributes = Cache::remember('schoolvote.school', 3600, function () {
            $school = static::query()->orderBy('id')->first()
                ?? static::create(['name' => 'Nancapian National High School']);

            return $school->getAttributes();
        });

        return (new static)->newFromBuilder($attributes);
    }

    /** @return HasMany<SchoolYear, $this> */
    public function schoolYears(): HasMany
    {
        return $this->hasMany(SchoolYear::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** Absolute file path for embedding the logo in PDFs. */
    public function logoFilePath(): ?string
    {
        return $this->logo_path && Storage::disk('public')->exists($this->logo_path)
            ? Storage::disk('public')->path($this->logo_path)
            : null;
    }
}
