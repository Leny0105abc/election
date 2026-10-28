<?php

namespace App\Models;

use Database\Factories\PartyListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $election_id
 * @property string $name
 * @property string|null $acronym
 * @property string $color
 * @property string|null $logo_path
 * @property string|null $description
 */
class PartyList extends Model
{
    /** @use HasFactory<PartyListFactory> */
    use HasFactory;

    protected $fillable = ['election_id', 'name', 'acronym', 'color', 'logo_path', 'description'];

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

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function displayName(): string
    {
        return $this->acronym ? "{$this->name} ({$this->acronym})" : $this->name;
    }
}
