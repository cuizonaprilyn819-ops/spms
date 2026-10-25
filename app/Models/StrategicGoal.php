<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategicGoal extends Model
{
    protected $fillable = ['title', 'description', 'created_by'];

    public function objectives(): HasMany { return $this->hasMany(StrategicObjective::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
