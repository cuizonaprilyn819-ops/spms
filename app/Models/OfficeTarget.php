<?php

namespace App\Models;

use App\Notifications\TargetAssignedNotification;
use App\Services\PlanningOrganization;
use App\Services\TargetHierarchy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OfficeTarget extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('campusPlanningAccess', function ($query) {
            $user = auth()->user();
            if (! $user || $user->hasRole('Administrator')) {
                return;
            }
            $visible = app(PlanningOrganization::class)->visiblePlanIds($user);
            $query->where(fn ($q) => $q->whereDoesntHave('evaluationCycle', fn ($c) => $c->where('multi_campus_enabled', true))
                ->orWhereIn('performance_target_id', PerformanceTarget::whereIn('commitment_plan_id', $visible)->select('root_target_id')));
        });
    }

    public function performanceTarget()
    {
        return $this->belongsTo(PerformanceTarget::class);
    }

    protected $guarded = [];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = ['evaluation_cycle_id', 'strategic_objective_id', 'strategic_goal', 'strategic_goal_description', 'strategic_objective', 'title', 'description', 'success_indicator', 'target_value', 'unit_of_measure', 'weight', 'start_date', 'deadline', 'priority', 'instructions', 'file_path', 'original_name', 'created_by'];

    protected function casts(): array
    {
        return ['target_value' => 'decimal:2', 'weight' => 'decimal:2', 'start_date' => 'date', 'deadline' => 'date'];
    }

    public function evaluationCycle(): BelongsTo
    {
        return $this->belongsTo(EvaluationCycle::class);
    }

    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'office_target_employee')->withPivot(['assigned_by', 'accepted_at', 'completed_at', 'allocated_target_value', 'completed_value'])->withTimestamps();
    }

    public function notifyAssignedSupervisors(array $officeIds, bool $updated = false): void
    {
        $officeIds = app(TargetHierarchy::class)->reportingOfficeIds($officeIds);
        $headIds = Office::whereIn('id', $officeIds)->whereNotNull('head_id')->pluck('head_id');
        User::query()->where(function ($query) use ($officeIds, $headIds) {
            $query->where(fn ($q) => $q->whereIn('office_id', $officeIds)->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['Supervisor', 'Unit Head', 'Division Head', 'HR Head', 'Campus Director'])))
                ->orWhereIn('id', $headIds);
        })
            ->each(function (User $supervisor) use ($updated): void {
                $alreadyNotified = $supervisor->notifications()
                    ->where('type', TargetAssignedNotification::class)
                    ->where('data->target_id', $this->id)->exists();
                if ($updated || ! $alreadyNotified) {
                    $supervisor->notify(new TargetAssignedNotification($this, $updated));
                }
            });
    }

    public function strategicObjective(): BelongsTo
    {
        return $this->belongsTo(StrategicObjective::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class, 'office_target_office')->withPivot(['allocated_target_value', 'ai_briefing', 'upcr_id'])->withTimestamps();
    }
}
