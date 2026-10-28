<?php

namespace App\Models;

use Database\Factories\PerformanceTargetFactoryLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PerformanceTarget extends Model
{
    public function scopeCurrentPlanningRevision(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('commitment_plan_id')->orWhereHas('plan', fn ($p) => $p->whereColumn('commitment_plans.revision', 'performance_targets.plan_revision')));
    }

    protected $fillable = [
        'targetable_type',
        'targetable_id',
        'target',
        'success_indicator',
        'weight',
        'deadline',
        'created_by',
        'commitment_plan_id', 'parent_target_id', 'root_target_id', 'source_revision', 'plan_revision',
        'measurement', 'quantity', 'unit', 'whole_numbers', 'standards', 'source_evidence',
        'allocation_basis', 'needs_reconciliation', 'supersedes_id',
        'contributes_only',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'weight' => 'decimal:2',
            'allocation_basis' => 'array', 'whole_numbers' => 'boolean', 'needs_reconciliation' => 'boolean',
            'contributes_only' => 'boolean',
        ];
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }

    public function plan()
    {
        return $this->belongsTo(CommitmentPlan::class, 'commitment_plan_id');
    }

    public function parentTarget()
    {
        return $this->belongsTo(self::class, 'parent_target_id');
    }

    public function outputs()
    {
        return $this->hasMany(PlanningOutput::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accomplishments(): HasMany
    {
        return $this->hasMany(Accomplishment::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public static function factoryLike(): PerformanceTargetFactoryLike
    {
        return new PerformanceTargetFactoryLike;
    }
}
