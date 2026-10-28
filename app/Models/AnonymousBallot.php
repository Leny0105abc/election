<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An anonymous ballot. Has no link to the voter and no timestamps.
 * Only written by VoteSubmissionService and read in aggregate by VoteCountingService.
 *
 * @property string $id Random UUIDv4 (not time-ordered)
 * @property int $election_id
 */
class AnonymousBallot extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'election_id'];

    /** @return HasMany<BallotSelection, $this> */
    public function selections(): HasMany
    {
        return $this->hasMany(BallotSelection::class);
    }
}
