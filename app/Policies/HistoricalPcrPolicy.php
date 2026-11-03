<?php

namespace App\Policies;

use App\Models\HistoricalPcr;
use App\Models\User;

class HistoricalPcrPolicy
{
    public function view(User $user, HistoricalPcr $pcr): bool
    {
        return $user->hasRole('Administrator') || $pcr->owner_id === $user->id
            || $pcr->participants()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Administrator');
    }

    public function update(User $user, HistoricalPcr $pcr): bool
    {
        return $user->hasRole('Administrator');
    }
}
