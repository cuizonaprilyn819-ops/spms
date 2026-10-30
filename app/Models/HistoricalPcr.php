<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoricalPcr extends Model
{
    protected $guarded = ['id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(HistoricalPcrRevision::class, 'current_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(HistoricalPcrRevision::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(HistoricalPcrParticipant::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(HistoricalPcrCorrection::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(! $user->hasRole('Administrator'), fn ($q) => $q->where(fn ($access) => $access->where('owner_id', $user->id)->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id))));
    }
}
