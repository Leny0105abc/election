<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $election_id
 * @property int $election_position_id
 * @property int $student_id
 * @property int|null $party_list_id
 * @property string|null $ballot_name
 * @property int $grade_level
 * @property string|null $photo_path
 * @property string|null $platform
 * @property string|null $biography
 * @property CandidateStatus $status
 * @property string|null $status_reason
 * @property-read Student $student
 * @property-read ElectionPosition $position
 * @property-read PartyList|null $partyList
 * @property-read string $display_name
 */
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    protected $fillable = [
        'election_id', 'election_position_id', 'student_id', 'party_list_id', 'ballot_name',
        'grade_level', 'photo_path', 'platform', 'biography', 'status', 'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => CandidateStatus::class,
            'grade_level' => 'integer',
        ];
    }

    /** @return BelongsTo<Election, $this> */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /** @return BelongsTo<ElectionPosition, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(ElectionPosition::class, 'election_position_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<PartyList, $this> */
    public function partyList(): BelongsTo
    {
        return $this->belongsTo(PartyList::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->ballot_name ?: (string) $this->student?->full_name;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function partyLabel(): string
    {
        return $this->partyList?->acronym ?: ($this->partyList?->name ?? 'Independent');
    }

    public function initials(): string
    {
        $name = $this->display_name;
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr((string) end($parts), 0, 1));
    }
}
