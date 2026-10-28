<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Position catalogue entry (template copied into each election).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $display_order
 * @property int $max_winners
 * @property list<int>|null $eligible_voter_grades
 * @property list<int>|null $eligible_candidate_grades
 * @property bool $allow_abstain
 * @property bool $is_active
 */
class Position extends Model
{
    protected $fillable = [
        'name', 'description', 'display_order', 'max_winners',
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

    /** @return array<string, mixed> attributes to copy into an election */
    public function toElectionPositionAttributes(): array
    {
        return [
            'position_id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'display_order' => $this->display_order,
            'max_winners' => $this->max_winners,
            'eligible_voter_grades' => $this->eligible_voter_grades,
            'eligible_candidate_grades' => $this->eligible_candidate_grades,
            'allow_abstain' => $this->allow_abstain,
            'is_active' => true,
        ];
    }
}
