<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Frozen official tally row, written once at finalization.
 * Rows cannot be edited through the application.
 *
 * @property int $id
 * @property int $election_id
 * @property int $election_position_id
 * @property int|null $candidate_id
 * @property bool $is_abstain_row
 * @property int $votes
 * @property string $percentage
 * @property int|null $rank
 * @property bool $is_winner
 * @property bool $is_tied
 */
class ElectionResult extends Model
{
    protected $fillable = [
        'election_id', 'election_position_id', 'candidate_id', 'is_abstain_row',
        'votes', 'percentage', 'rank', 'is_winner', 'is_tied',
    ];

    protected function casts(): array
    {
        return [
            'is_abstain_row' => 'boolean',
            'is_winner' => 'boolean',
            'is_tied' => 'boolean',
            'votes' => 'integer',
            'rank' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Vote totals may never be modified manually.
        static::updating(fn () => throw new LogicException('Official election results are read-only.'));
    }

    /** @return BelongsTo<ElectionPosition, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(ElectionPosition::class, 'election_position_id');
    }

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
