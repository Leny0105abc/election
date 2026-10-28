<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use Carbon\CarbonImmutable;
use Database\Factories\ElectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $school_year_id
 * @property string $name
 * @property ElectionType $type
 * @property string|null $description
 * @property string|null $banner_path
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property list<int>|null $eligible_grades
 * @property ElectionStatus $status
 * @property string|null $tie_resolution_rule
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $finalized_at
 * @property CarbonImmutable|null $results_published_at
 * @property-read SchoolYear $schoolYear
 */
class Election extends Model
{
    /** @use HasFactory<ElectionFactory> */
    use HasFactory;

    /** Status is never mass-assigned; it changes only through ElectionService. */
    protected $attributes = [
        'status' => 'draft',
        'type' => 'sslg',
    ];

    protected $fillable = [
        'school_year_id', 'name', 'type', 'description', 'banner_path',
        'starts_at', 'ends_at', 'eligible_grades', 'tie_resolution_rule', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ElectionType::class,
            'status' => ElectionStatus::class,
            'eligible_grades' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'finalized_at' => 'datetime',
            'results_published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SchoolYear, $this> */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /** @return HasMany<ElectionPosition, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(ElectionPosition::class)->orderBy('display_order')->orderBy('id');
    }

    /** @return HasMany<Candidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /** @return HasMany<Candidate, $this> */
    public function approvedCandidates(): HasMany
    {
        return $this->hasMany(Candidate::class)->where('status', CandidateStatus::Approved);
    }

    /** @return HasMany<PartyList, $this> */
    public function partyLists(): HasMany
    {
        return $this->hasMany(PartyList::class)->orderBy('name');
    }

    /** @return HasMany<ElectionVoter, $this> */
    public function voters(): HasMany
    {
        return $this->hasMany(ElectionVoter::class);
    }

    /** @return HasMany<ElectionResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(ElectionResult::class);
    }

    /** @return HasMany<TieResolution, $this> */
    public function tieResolutions(): HasMany
    {
        return $this->hasMany(TieResolution::class);
    }

    /**
     * Grades that may take part. Empty/null means every grade level.
     *
     * @return list<int>
     */
    public function eligibleGrades(): array
    {
        $grades = array_map('intval', $this->eligible_grades ?? []);

        return $grades ?: config('schoolvote.grade_levels');
    }

    /** Ballots are accepted only while Open AND inside the voting window. */
    public function isAcceptingVotes(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $this->status === ElectionStatus::Open
            && $this->starts_at !== null && $this->ends_at !== null
            && $now->greaterThanOrEqualTo($this->starts_at)
            && $now->lessThan($this->ends_at);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function resultsArePublished(): bool
    {
        return $this->results_published_at !== null
            && in_array($this->status, [ElectionStatus::Finalized, ElectionStatus::Archived], true);
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null;
    }

    /** Elections students can see on their portal. */
    public static function visibleToStudents()
    {
        return static::query()
            ->whereIn('status', [
                ElectionStatus::Scheduled, ElectionStatus::Open,
                ElectionStatus::Closed, ElectionStatus::Finalized,
            ])
            ->orderByDesc('starts_at');
    }
}
