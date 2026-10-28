<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One choice on an anonymous ballot (a candidate, or an abstention).
 *
 * @property string $id
 * @property string $anonymous_ballot_id
 * @property int $election_id
 * @property int $election_position_id
 * @property int|null $candidate_id
 * @property bool $is_abstain
 */
class BallotSelection extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'anonymous_ballot_id', 'election_id', 'election_position_id', 'candidate_id', 'is_abstain',
    ];

    protected function casts(): array
    {
        return ['is_abstain' => 'boolean'];
    }
}
