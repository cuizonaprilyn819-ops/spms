<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategicObjective extends Model
{
    protected $fillable = ['strategic_goal_id', 'title', 'description'];

    public function strategicGoal(): BelongsTo { return $this->belongsTo(StrategicGoal::class); }
    public function officeTargets(): HasMany { return $this->hasMany(OfficeTarget::class); }
}
