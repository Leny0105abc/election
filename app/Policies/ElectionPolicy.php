<?php

namespace App\Policies;

use App\Enums\ElectionStatus;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\User;

/**
 * Model-level rules for elections. Role permissions come from App\Enums\Role;
 * this policy adds the election's state on top of them.
 */
class ElectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-monitoring');
    }

    public function view(User $user, Election $election): bool
    {
        return $user->hasPermission('view-monitoring');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-elections');
    }

    /** Details may be edited; structural fields are additionally locked by ElectionService. */
    public function update(User $user, Election $election): bool
    {
        return $user->hasPermission('manage-elections')
            && ! in_array($election->status, [ElectionStatus::Finalized, ElectionStatus::Archived], true);
    }

    public function delete(User $user, Election $election): bool
    {
        return $user->hasPermission('manage-elections')
            && $election->status === ElectionStatus::Draft
            && ! ElectionVoter::where('election_id', $election->id)->where('has_voted', true)->exists();
    }

    public function viewResults(User $user, Election $election): bool
    {
        return $user->hasPermission('view-canvass') && $election->status->allowsCanvass();
    }

    public function finalize(User $user, Election $election): bool
    {
        return $user->hasPermission('finalize-election') && $election->status === ElectionStatus::Closed;
    }
}
