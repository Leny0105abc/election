<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Election committee decision that breaks a tie affecting the winners
 * (e.g. toss coin / draw lots, as approved in the election rules).
 *
 * @property int $id
 * @property int $election_id
 * @property int $election_position_id
 * @property list<int> $winning_candidate_ids
 * @property string $method_and_notes
 * @property int|null $resolved_by
 */
class TieResolution extends Model
{
    protected $fillable = [
        'election_id', 'election_position_id', 'winning_candidate_ids', 'method_and_notes', 'resolved_by',
    ];

    protected function casts(): array
    {
        return ['winning_candidate_ids' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
