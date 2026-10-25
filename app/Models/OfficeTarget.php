<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OfficeTarget extends Model
{
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = ['evaluation_cycle_id', 'strategic_objective_id', 'strategic_goal', 'strategic_goal_description', 'strategic_objective', 'title', 'description', 'success_indicator', 'target_value', 'unit_of_measure', 'weight', 'start_date', 'deadline', 'priority', 'instructions', 'file_path', 'original_name', 'created_by'];

    protected function casts(): array
    {
        return ['target_value' => 'decimal:2', 'weight' => 'decimal:2', 'start_date' => 'date', 'deadline' => 'date'];
    }

    public function evaluationCycle(): BelongsTo { return $this->belongsTo(EvaluationCycle::class); }
    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'office_target_employee')->withPivot(['assigned_by', 'accepted_at', 'completed_at', 'allocated_target_value', 'completed_value'])->withTimestamps();
    }
    public function notifyAssignedSupervisors(array $officeIds): void
    {
        User::query()->whereIn('office_id', $officeIds)
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['Supervisor', 'HR Head']))
            ->each(function (User $supervisor): void {
                $alreadyNotified = $supervisor->notifications()
                    ->where('type', \App\Notifications\TargetAssignedNotification::class)
                    ->where('data->target_id', $this->id)->exists();
                if (! $alreadyNotified) {
                    $supervisor->notify(new \App\Notifications\TargetAssignedNotification($this));
                }
            });
    }

    public function strategicObjective(): BelongsTo { return $this->belongsTo(StrategicObjective::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function offices(): BelongsToMany { return $this->belongsToMany(Office::class, 'office_target_office')->withPivot(['allocated_target_value', 'ai_briefing'])->withTimestamps(); }
}
