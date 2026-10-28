<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use Database\Factories\ElectionPositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A position as it appears on one election's ballot.
 *
 * @property int $id
 * @property int $election_id
 * @property int|null $position_id
 * @property string $name
 * @property string|null $description
 * @property int $display_order
 * @property int $max_winners
 * @property list<int>|null $eligible_voter_grades
 * @property list<int>|null $eligible_candidate_grades
 * @property bool $allow_abstain
 * @property bool $is_active
 * @property-read Election $election
 */
class ElectionPosition extends Model
{
    /** @use HasFactory<ElectionPositionFactory> */
    use HasFactory;

    protected $fillable = [
        'election_id', 'position_id', 'name', 'description', 'display_order', 'max_winners',
        'eligible_voter_grades', 'eligible_candidate_grades', 'allow_abstain', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'eligible_voter_grades' => 'array',
            'eligible_candidate_grades' => 'array',
            'allow_abstain' => 'boolean',
            'is_active' => 'boolean',
            'max_winners' => 'integer',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Election, $this> */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /** @return HasMany<Candidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /** @return HasMany<Candidate, $this> */
    public function approvedCandidates(): HasMany
    {
        return $this->hasMany(Candidate::class)
            ->where('status', CandidateStatus::Approved)
            ->orderBy('ballot_name')
            ->orderBy('id');
    }

    public function voterGradeAllowed(int $grade): bool
    {
        $grades = array_map('intval', $this->eligible_voter_grades ?? []);

        return $grades === [] || in_array($grade, $grades, true);
    }

    public function candidateGradeAllowed(int $grade): bool
    {
        $grades = array_map('intval', $this->eligible_candidate_grades ?? []);

        return $grades === [] || in_array($grade, $grades, true);
    }

    public function voterGradesLabel(): string
    {
        $grades = $this->eligible_voter_grades ?? [];

        return $grades === [] ? 'All grades' : 'Grade '.implode(', ', $grades);
    }

    public function candidateGradesLabel(): string
    {
        $grades = $this->eligible_candidate_grades ?? [];

        return $grades === [] ? 'All grades' : 'Grade '.implode(', ', $grades);
    }
}
