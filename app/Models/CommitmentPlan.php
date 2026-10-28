<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitmentPlan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['agreed_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function report()
    {
        return $this->morphTo();
    }

    public function cycle()
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function targets()
    {
        return $this->hasMany(PerformanceTarget::class)->where('plan_revision', $this->revision);
    }

    public function events()
    {
        return $this->hasMany(PlanningEvent::class)->latest('id');
    }

    public function currentTargets()
    {
        return $this->targets()->where('needs_reconciliation', false);
    }
}
